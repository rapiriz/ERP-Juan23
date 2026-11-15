<?php

namespace App\Modules\ConciliacionBancaria\Services;

class BankStatementParserService
{
    /**
     * Regex para detección de CUIT/CUIL con o sin guiones.
     */
    public const CUIT_REGEX = '/(20|23|27|30|33|34)-\d{8}-\d{1}|\b(20|23|27|30|33|34)\d{9}\b/';

    /**
     * Limpia espacios múltiples, expande abreviaturas bancarias frecuentes y pasa a mayúsculas.
     */
    private function normalizarDescripcion(string $descripcion): string
    {
        $limpia = mb_strtoupper(trim($descripcion), 'UTF-8');
        $limpia = preg_replace('/[^\p{L}\p{N}\s\-\/\.\,\(\)]/u', ' ', $limpia);
        $limpia = preg_replace('/\s+/', ' ', $limpia);

        $abreviaturas = [
            '/\bTRANSF\.?\b/u' => 'TRANSFERENCIA',
            '/\bTRF\.?\b/u' => 'TRANSFERENCIA',
            '/\bDEB\.?\b/u' => 'DEBITO',
            '/\bCRED\.?\b/u' => 'CREDITO',
            '/\bIMP\.?\b/u' => 'IMPUESTO',
            '/\bCOM\.?\b/u' => 'COMISION',
            '/\bCHQ\.?\b/u' => 'CHEQUE',
            '/\bECHEQ\.?\b/u' => 'E-CHEQ',
            '/\bDEP\.?\b/u' => 'DEPOSITO',
            '/\bSERV\.?\b/u' => 'SERVICIO',
            '/\bAUT\.?\b/u' => 'AUTOMATICO',
            '/\bMANT\.?\b/u' => 'MANTENIMIENTO',
            '/\bCTA\.?\b/u' => 'CUENTA',
            '/\bCTE\.?\b/u' => 'CORRIENTE',
            '/\bBCO\.?\b/u' => 'BANCO',
            '/\bINTERBANC\.?\b/u' => 'INTERBANCARIA',
            '/\bACRED\.?\b/u' => 'ACREDITACION',
            '/\bLIQ\.?\b/u' => 'LIQUIDACION',
            '/\bRET\.?\b/u' => 'RETENCION',
            '/\bPERC\.?\b/u' => 'PERCEPCION',
            '/\bING\.?\s*BRUTOS\b/u' => 'INGRESOS BRUTOS',
            '/\bII\.?\s*BB\.?\b/u' => 'INGRESOS BRUTOS',
            '/\bVAR\.?\b/u' => 'VARIOS',
        ];

        $limpia = preg_replace(array_keys($abreviaturas), array_values($abreviaturas), $limpia);
        return trim(preg_replace('/\s+/', ' ', $limpia));
    }

    /**
     * Extrae CUIT/CUIL de la descripción si existe.
     */
    public function extraerCuit(string $texto): ?string
    {
        if (preg_match(self::CUIT_REGEX, $texto, $matches)) {
            $cuitLimpio = preg_replace('/\D/', '', $matches[0]);
            if (strlen($cuitLimpio) === 11) {
                return $cuitLimpio;
            }
        }
        return null;
    }

    /**
     * Extrae número de comprobante o referencia de la descripción si existe.
     */
    public function extraerComprobante(string $texto): ?string
    {
        if (preg_match('/\b(COMP|NRO|NUM|REC|OPER|FAC|S\.)\s*[:#.]?\s*(\d+)\b/i', $texto, $matches)) {
            return $matches[2];
        }
        return null;
    }

    /**
     * Clasifica el tipo de movimiento y el estado de conciliación inicial según las directivas legales y normativas.
     *
     * @return array{tipo_movimiento: string, estado_conciliacion: string}
     */
    public function clasificar(string $descNormalizada, float $monto, string $signo): array
    {
        $esCredito = ($signo === 'ingreso' || $monto > 0);

        // 1. Detección de retenciones, contracargos o fondos suspendidos
        if (preg_match('/(CONTRACARGO|DEBITO SUSPENDIDO|RETENCION PREVENTIVA|EMBARGO|INVESTIGACION)/i', $descNormalizada)) {
            return [
                'tipo_movimiento' => 'otro',
                'estado_conciliacion' => 'en_disputa',
            ];
        }

        // 2. Débito por rechazo de eCheq
        if (preg_match('/(DEB\.?\s*RECHAZO ECHEQ|RECHAZO ECHEQ DESCONTADO|RECHAZO CHEQUE)/i', $descNormalizada)) {
            return [
                'tipo_movimiento' => 'debito_rechazo',
                'estado_conciliacion' => 'no_aplica',
            ];
        }

        // 3. Impuestos bancarios (Ley 25413, IVA, SIRCREB, Sellos)
        if (preg_match('/(LEY\s*25413|DB\.?\s*LEY|IMP\.?\s*DEB\/CRED|IMPUESTO DEBITOS CREDITOS|IVA COMISION|IVA TASA GENERAL|IVA 21%|SIRCREB|PERC IIBB|RET IIBB|IMP DE SELLOS|IMP\.?\s*SELLOS)/i', $descNormalizada)) {
            return [
                'tipo_movimiento' => 'impuesto_bancario',
                'estado_conciliacion' => 'no_aplica',
            ];
        }

        // 4. Gastos bancarios y comisiones
        if (preg_match('/(MANT CUENTA|GASTO MANTENIMIENTO|COMISION|COM\.?\s*TRANSF|GASTO CHEQUERA|MANTENIMIENTO POS|IVA COMISION)/i', $descNormalizada)) {
            return [
                'tipo_movimiento' => 'gasto_bancario',
                'estado_conciliacion' => 'no_aplica',
            ];
        }

        // 5. Transferencias o DEBIN salientes (pago a proveedores)
        if (!$esCredito && preg_match('/(TRANSFERENCIA ENVIADA|TRANSF\.?\s*EMITIDA|DEBIN DEBITO|DEBIN ENVIADO|PAGO PROVEEDOR)/i', $descNormalizada)) {
            return [
                'tipo_movimiento' => 'pago_proveedor',
                'estado_conciliacion' => 'no_aplica',
            ];
        }

        // 6. Cobranza cliente (Créditos / Acreditaciones)
        if ($esCredito) {
            $esCobranza = preg_match(
                '/(ACRED\.?\s*ECHEQ|ACREDITACION ECHEQ|CLEARING ECHEQ|LIQ\.?\s*NEGOCIACION ECHEQ|DESCUENTO ECHEQ|ACRED\.?\s*ECHEQ AVALADO|PAGO AVAL SGR|TRANSFERENCIA RECIBIDA|TRANSF\.?\s*RECIBIDA|TRANSF\.?\s*CUIT|TRANSF DE|CREDITO TRASPASO|DEBIN ACREDITACION|DEBIN CREDITO|MERCADO\s*PAGO|MERCADOPAGO|DEPOSITO ATM|DEPOSITO AUTOSERVICIO|DEPOSITO)/i',
                $descNormalizada
            );

            $tieneCuit = $this->extraerCuit($descNormalizada) !== null;

            if ($esCobranza || $tieneCuit) {
                return [
                    'tipo_movimiento' => 'cobranza_cliente',
                    'estado_conciliacion' => 'pendiente',
                ];
            }
        }

        // 7. Pagos de sueldos o servicios
        if (preg_match('/(HABERES|SUELDOS|PAGO SUELDO)/i', $descNormalizada)) {
            return [
                'tipo_movimiento' => 'pago_sueldos',
                'estado_conciliacion' => 'no_aplica',
            ];
        }
        if (preg_match('/(PAGO DE SERVICIO|PAGO SERVICIOS|AFIP|ARBA)/i', $descNormalizada)) {
            return [
                'tipo_movimiento' => 'pago_servicios',
                'estado_conciliacion' => 'no_aplica',
            ];
        }

        // 8. Desconocido / Revisar
        return [
            'tipo_movimiento' => 'desconocido',
            'estado_conciliacion' => $esCredito ? 'revision_manual' : 'no_aplica',
        ];
    }
}
