<?php

namespace App\Modules\ConciliacionBancaria\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class BankStatementParserService
{
    /**
     * Parsea un archivo de extracto bancario (.txt, .csv, .xls, .xlsx)
     * y devuelve un arreglo normalizado de movimientos.
     */
    public function parse(UploadedFile $archivo): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        $ruta = $archivo->getRealPath();

        if (!$ruta || !is_readable($ruta)) {
            throw new RuntimeException('No se pudo leer el archivo subido.');
        }

        $contenidoBinario = file_get_contents($ruta);
        if ($contenidoBinario === false || trim($contenidoBinario) === '') {
            throw new RuntimeException('El archivo está vacío.');
        }

        // Detectar firma binaria OLE2 (.xls antiguo) o ZIP (.xlsx)
        $esOle2 = str_starts_with($contenidoBinario, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
        $esZip = str_starts_with($contenidoBinario, "PK\x03\x04");

        if ($esOle2 || $esZip || in_array($extension, ['xls', 'xlsx'], true)) {
            return $this->parsearExcel($ruta, $contenidoBinario, $esOle2);
        }

        return $this->parsearTexto($contenidoBinario);
    }

    /**
     * Parsea archivos .txt de ancho fijo (formato BIP 190 chars) o delimitados (.csv/.txt).
     */
    private function parsearTexto(string $contenido): array
    {
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
        }

        $lineas = preg_split('/\r\n|\r|\n/', $contenido);
        $lineasNoVacias = array_values(array_filter($lineas, fn ($l) => trim($l) !== ''));

        if (empty($lineasNoVacias)) {
            throw new RuntimeException('El archivo de texto no contiene líneas legibles.');
        }

        // Detectar formato de ancho fijo Banco Provincia (líneas H... / D... / T...)
        $tieneRegistrosD = false;
        foreach ($lineasNoVacias as $linea) {
            if (preg_match('/^D\s*\d+\d{2}-\d{2}-\d{4}/', $linea)) {
                $tieneRegistrosD = true;
                break;
            }
        }

        if ($tieneRegistrosD) {
            return $this->parsearTextoAnchoFijoBip($lineasNoVacias);
        }

        return $this->parsearCsvDelimitado($lineasNoVacias);
    }

    /**
     * Formato ancho fijo 190 caracteres (Banco Provincia / Red Link):
     * D + seq(6) + fecha(10) + desc(80) + comp(18) + filler(24) + cuit(11) + sucursal(8) + signo(1) + monto(15) + saldo(16)
     */
    private function parsearTextoAnchoFijoBip(array $lineas): array
    {
        $movimientos = [];

        foreach ($lineas as $linea) {
            if (!str_starts_with($linea, 'D')) {
                continue;
            }

            if (mb_strlen($linea, 'UTF-8') < 174) {
                continue;
            }

            $fechaRaw = trim(mb_substr($linea, 7, 10, 'UTF-8'));
            $descOriginal = trim(mb_substr($linea, 17, 80, 'UTF-8'));
            $comprobante = trim(mb_substr($linea, 97, 18, 'UTF-8'));
            $cuitBloque = trim(mb_substr($linea, 139, 11, 'UTF-8'));
            $signoChar = mb_substr($linea, 158, 1, 'UTF-8');
            $montoCentavos = trim(mb_substr($linea, 159, 15, 'UTF-8'));
            $saldoCentavos = mb_strlen($linea, 'UTF-8') >= 190
                ? trim(mb_substr($linea, 174, 16, 'UTF-8'))
                : null;

            $fecha = $this->normalizarFecha($fechaRaw);
            if (!$fecha || !ctype_digit($montoCentavos)) {
                continue;
            }

            $monto = round(((int) $montoCentavos) / 100, 2);
            if ($monto <= 0) {
                continue;
            }

            $signo = ($signoChar === '-') ? 'egreso' : 'ingreso';
            $saldo = ($saldoCentavos !== null && ctype_digit($saldoCentavos))
                ? round(((int) $saldoCentavos) / 100, 2)
                : null;

            $cuit = preg_match('/^\d{11}$/', $cuitBloque)
                ? $cuitBloque
                : $this->extraerCuit($descOriginal);

            $comprobanteFinal = $comprobante !== '' ? $comprobante : $this->extraerComprobante($descOriginal);
            $descNormalizada = $this->normalizarDescripcion($descOriginal);

            $movimientos[] = [
                'fecha_operacion' => $fecha,
                'descripcion_original' => $descOriginal,
                'descripcion_normalizada' => $descNormalizada,
                'monto' => $monto,
                'signo_monto' => $signo,
                'saldo_posterior' => $saldo,
                'cuit_extraido' => $cuit,
                'nro_comprobante_extraido' => $comprobanteFinal,
                'tipo_movimiento' => $this->clasificarTipoMovimiento($descNormalizada, $signo),
            ];
        }

        if (empty($movimientos)) {
            throw new RuntimeException('No se encontraron registros de detalle válidos (líneas D) en el archivo TXT.');
        }

        return $movimientos;
    }

    /**
     * Parsea archivos .xls y .xlsx convirtiéndolos en filas tabulares.
     */
    private function parsearExcel(string $ruta, string $contenidoBinario, bool $esOle2): array
    {
        $filas = [];

        if (class_exists(IOFactory::class)) {
            $spreadsheet = IOFactory::load($ruta);
            $hoja = $spreadsheet->getActiveSheet();
            $filas = $hoja->toArray(null, true, true, false);
        } elseif ($esOle2) {
            $filas = $this->extraerFilasBiff8Nativo($contenidoBinario);
        } else {
            throw new RuntimeException('No está disponible el lector de Excel en el servidor.');
        }

        return $this->procesarFilasTabulares($filas);
    }

    /**
     * Procesa la matriz de filas extraída de Excel buscando las columnas Fecha, Descripción, Importe y Saldo.
     */
    private function procesarFilasTabulares(array $filas): array
    {
        $idxFecha = null;
        $idxDesc = null;
        $idxImporte = null;
        $idxSaldo = null;
        $filaInicioDatos = 0;

        foreach ($filas as $numFila => $celdas) {
            if (!is_array($celdas)) {
                continue;
            }
            $textos = array_map(fn ($v) => mb_strtolower(trim((string) $v), 'UTF-8'), $celdas);

            $posFecha = array_search('fecha', $textos, true);
            $posDesc = array_search('descripción', $textos, true);
            if ($posDesc === false) {
                $posDesc = array_search('descripcion', $textos, true);
            }
            $posImp = array_search('importe', $textos, true);
            if ($posImp === false) {
                $posImp = array_search('monto', $textos, true);
            }
            $posSal = array_search('saldo', $textos, true);

            if ($posFecha !== false && $posDesc !== false && $posImp !== false) {
                $idxFecha = $posFecha;
                $idxDesc = $posDesc;
                $idxImporte = $posImp;
                $idxSaldo = $posSal !== false ? $posSal : null;
                $filaInicioDatos = $numFila + 1;
                break;
            }
        }

        if ($idxFecha === null || $idxDesc === null || $idxImporte === null) {
            throw new RuntimeException('No se encontraron los encabezados esperados (Fecha, Descripción, Importe) en la planilla.');
        }

        $movimientos = [];
        for ($i = $filaInicioDatos; $i < count($filas); $i++) {
            $fila = $filas[$i] ?? [];
            $fechaRaw = trim((string) ($fila[$idxFecha] ?? ''));
            $descOriginal = trim((string) ($fila[$idxDesc] ?? ''));
            $importeRaw = $fila[$idxImporte] ?? null;
            $saldoRaw = $idxSaldo !== null ? ($fila[$idxSaldo] ?? null) : null;

            if ($fechaRaw === '' || $descOriginal === '' || $importeRaw === null || $importeRaw === '') {
                continue;
            }

            $fecha = $this->normalizarFecha($fechaRaw);
            if (!$fecha) {
                continue;
            }

            $importeFloat = $this->parsearNumero($importeRaw);
            if ($importeFloat === null || abs($importeFloat) < 0.001) {
                continue;
            }

            $signo = $importeFloat < 0 ? 'egreso' : 'ingreso';
            $monto = round(abs($importeFloat), 2);
            $saldo = $saldoRaw !== null && $saldoRaw !== '' ? $this->parsearNumero($saldoRaw) : null;
            $descNormalizada = $this->normalizarDescripcion($descOriginal);

            $movimientos[] = [
                'fecha_operacion' => $fecha,
                'descripcion_original' => $descOriginal,
                'descripcion_normalizada' => $descNormalizada,
                'monto' => $monto,
                'signo_monto' => $signo,
                'saldo_posterior' => $saldo !== null ? round($saldo, 2) : null,
                'cuit_extraido' => $this->extraerCuit($descOriginal),
                'nro_comprobante_extraido' => $this->extraerComprobante($descOriginal),
                'tipo_movimiento' => $this->clasificarTipoMovimiento($descNormalizada, $signo),
            ];
        }

        if (empty($movimientos)) {
            throw new RuntimeException('No se encontraron movimientos válidos dentro de la planilla Excel.');
        }

        return $movimientos;
    }

    private function parsearCsvDelimitado(array $lineas): array
    {
        $delimitador = str_contains($lineas[0], ';') ? ';' : ',';
        $filas = array_map(fn ($l) => str_getcsv($l, $delimitador), $lineas);
        return $this->procesarFilasTabulares($filas);
    }

    /**
     * Lector nativo BIFF8 (.xls) en PHP puro para entornos sin PhpSpreadsheet.
     */
    private function extraerFilasBiff8Nativo(string $binario): array
    {
        // Extraer cadenas Unicode/ASCII legibles del stream BIFF8 si no está PhpSpreadsheet
        $texto = mb_convert_encoding($binario, 'UTF-8', 'UTF-16LE');
        preg_match_all('/[\p{L}\p{N}\s\.\-\/\:\(\)\,]{3,}/u', $texto, $matches);
        throw new RuntimeException('El servidor requiere la extensión/paquete phpoffice/phpspreadsheet para leer archivos .xls binarios.');
    }

    /**
     * Convierte fechas en español ("28-sept-2026", "16-nov-2026", "20/10/2026") a YYYY-MM-DD.
     */
    public function normalizarFecha(string $raw): ?string
    {
        $limpia = mb_strtolower(trim($raw), 'UTF-8');
        if ($limpia === '') {
            return null;
        }

        $meses = [
            'ene' => '01', 'enero' => '01',
            'feb' => '02', 'febrero' => '02',
            'mar' => '03', 'marzo' => '03',
            'abr' => '04', 'abril' => '04',
            'may' => '05', 'mayo' => '05',
            'jun' => '06', 'junio' => '06',
            'jul' => '07', 'julio' => '07',
            'ago' => '08', 'agosto' => '08',
            'sep' => '09', 'sept' => '09', 'septiembre' => '09',
            'oct' => '10', 'octubre' => '10',
            'nov' => '11', 'noviembre' => '11',
            'dic' => '12', 'diciembre' => '12',
        ];

        // Formato "16-nov-2026" o "28-sept-2026"
        if (preg_match('/^(\d{1,2})[\-\/]([a-záéíóú]+)[\-\/](\d{4})$/u', $limpia, $m)) {
            $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mesTxt = rtrim($m[2], '.');
            $anio = $m[3];
            if (isset($meses[$mesTxt])) {
                return "{$anio}-{$meses[$mesTxt]}-{$dia}";
            }
        }

        // Formato DD-MM-YYYY o DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[\-\/](\d{1,2})[\-\/](\d{4})$/', $limpia, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // Formato YYYY-MM-DD
        if (preg_match('/^(\d{4})[\-\/](\d{1,2})[\-\/](\d{1,2})/', $limpia, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public function parsearNumero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        $str = trim((string) $valor);
        $str = str_replace(['$', 'ARS', ' '], '', $str);
        if ($str === '' || !preg_match('/^[\-\+]?[\d\.,]+$/', $str)) {
            return null;
        }

        if (str_contains($str, ',') && str_contains($str, '.')) {
            if (strrpos($str, ',') > strrpos($str, '.')) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }

        return is_numeric($str) ? (float) $str : null;
    }

    public function normalizarDescripcion(string $descripcion): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', $descripcion));

        $abreviaturas = [
            '/\bDB\.?\s*DEBIN\b/iu' => 'DEBITO DEBIN',
            '/\bCR\.?\s*DEBIN\b/iu' => 'CREDITO DEBIN',
            '/\bCDNI\b/iu' => 'CUENTA DNI',
            '/\bTRANSF\.?\b/iu' => 'TRANSFERENCIA',
            '/\bTRF\.?\b/iu' => 'TRANSFERENCIA',
            '/\bDEB\.?\b/iu' => 'DEBITO',
            '/\bCRED\.?\b/iu' => 'CREDITO',
            '/\bIMP\.?\b/iu' => 'IMPUESTO',
            '/\bCOM\.?\b/iu' => 'COMISION',
            '/\bCHQ\.?\b/iu' => 'CHEQUE',
            '/\bECHEQ\.?\b/iu' => 'E-CHEQ',
            '/\bDEP\.?\b/iu' => 'DEPOSITO',
            '/\bSERV\.?\b/iu' => 'SERVICIO',
            '/\bAUT\.?\b/iu' => 'AUTOMATICO',
            '/\bAUTOM\.?\b/iu' => 'AUTOMATICO',
            '/\bMANT\.?\b/iu' => 'MANTENIMIENTO',
            '/\bCTA\.?\b/iu' => 'CUENTA',
            '/\bCTE\.?\b/iu' => 'CORRIENTE',
            '/\bBCO\.?\b/iu' => 'BANCO',
            '/\bACREDIT\.?\b/iu' => 'ACREDITACION',
            '/\bACRED\.?\b/iu' => 'ACREDITACION',
            '/\bLIQ\.?\b/iu' => 'LIQUIDACION',
            '/\bRET\.?\b/iu' => 'RETENCION',
            '/\bPERC\.?\b/iu' => 'PERCEPCION',
            '/\bVAR\.?\b/iu' => 'VARIOS',
            '/\bING\.?\s*BRUTOS\b/iu' => 'INGRESOS BRUTOS',
            '/\bII\.?\s*BB\.?\b/iu' => 'INGRESOS BRUTOS',
        ];

        foreach ($abreviaturas as $patron => $reemplazo) {
            $texto = preg_replace($patron, $reemplazo, $texto);
        }

        return trim($texto);
    }

    public function extraerCuit(string $descripcion): ?string
    {
        // Soporta "(27408744739)", "C:27408744739", "D:20320266336" o CUIT con guiones
        if (preg_match('/(?:[CD]\s*:\s*|\()(\d{2}-?\d{8}-?\d)(?:\))?/i', $descripcion, $m)) {
            $soloDigitos = preg_replace('/\D/', '', $m[1]);
            if (strlen($soloDigitos) === 11) {
                return $soloDigitos;
            }
        }
        return null;
    }

    public function extraerComprobante(string $descripcion): ?string
    {
        // Soporta "-S.551445" o "-C.046358"
        if (preg_match('/-[SC]\.(\d+)/i', $descripcion, $m)) {
            return $m[1];
        }
        return null;
    }

    public function clasificarTipoMovimiento(string $descNormalizada, string $signo): string
    {
        $d = mb_strtoupper($descNormalizada, 'UTF-8');

        if (str_contains($d, 'INTERES')) {
            return 'otro';
        }
        if (str_contains($d, 'IMPUESTO') || str_contains($d, 'INGRESOS BRUTOS') || str_contains($d, 'PERCEPCION') || str_contains($d, 'RETENCION')) {
            return 'impuesto_bancario';
        }
        if (str_contains($d, 'COMISION') || str_contains($d, 'MANTENIMIENTO')) {
            return 'gasto_bancario';
        }
        if (str_contains($d, 'RECHAZ')) {
            return 'debito_rechazo';
        }
        if ($signo === 'ingreso' && (str_contains($d, 'TRANSFERENCIA') || str_contains($d, 'DEBIN') || str_contains($d, 'CUENTA DNI') || str_contains($d, 'DEPOSITO'))) {
            return 'cobranza_cliente';
        }
        if ($signo === 'egreso' && (str_contains($d, 'DEBIN') || str_contains($d, 'CUENTA DNI') || str_contains($d, 'TRANSFERENCIA'))) {
            return 'pago_proveedor';
        }

        return 'desconocido';
    }
    
    public function clasificar(string $descNormalizada, float $monto = 0.0, string $signo = 'ingreso'): array
    {
        $tipo = $this->clasificarTipoMovimiento($descNormalizada, $signo);

        return [
            'tipo_movimiento' => $tipo,
            'estado_conciliacion' => 'pendiente',
        ];
    }
}