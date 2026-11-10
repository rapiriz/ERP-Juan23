<?php

namespace App\Modules\ConciliacionBancaria\Services;

use App\Modules\ConciliacionBancaria\Models\ArchivoImportadoLog;
use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use App\Modules\Shared\Services\G2OperationAudit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BankStatementImportService
{
    public function __construct(private BankColumnMapper $mapper, private BankPeriodService $periods) {}

    public function importar(UploadedFile $file, int $actor): array
    {
        $this->periods->actor($actor);
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xls', 'txt'], true)) {
            throw ValidationException::withMessages(['archivo' => 'Sólo se aceptan XLS y TXT.']);
        }
        $parsed = $this->mapper->parsear($file->getRealPath(), $extension);
        if ($parsed['errores']) {
            throw ValidationException::withMessages(['archivo' => implode(' ', array_slice($parsed['errores'], 0, 20))]);
        }
        $hash = hash_file('sha256', $file->getRealPath());
        $stored = null;
        try {
            return DB::transaction(function () use ($file, $extension, $parsed, $actor, $hash, &$stored) {
                DB::table('usuarios')->orderBy('id')->lockForUpdate()->first();
                abort_if(ArchivoImportadoLog::where('hash_sha256', $hash)->exists(), 409, 'El archivo ya fue importado.');
                $stored = $file->store('extractos_bancarios', 'local');
                if (! is_string($stored) || $stored === '') {
                    throw ValidationException::withMessages(['archivo' => 'No se pudo conservar el extracto privado.']);
                }
                $log = ArchivoImportadoLog::create(['nombre_original' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'ruta_almacenada' => $stored, 'hash_sha256' => $hash,
                    'formato_extension' => $extension, 'cantidad_filas_leidas' => count($parsed['movimientos']), 'cantidad_filas_insertadas' => 0, 'cantidad_filas_omitidas' => 0, 'cantidad_filas_error' => 0,
                    'id_usuario' => $actor, 'saldo_inicial' => $parsed['saldo_inicial'], 'saldo_final' => $parsed['saldo_final'], 'fecha_desde' => $parsed['fecha_desde'], 'fecha_hasta' => $parsed['fecha_hasta']]);
                $inserted = $skipped = $eligible = 0;
                foreach ($parsed['movimientos'] as $move) {
                    $period = $this->periods->forDate($move['fecha_operacion'], $actor);
                    $duplicate = MovimientoBancario::whereDate('fecha_operacion', $move['fecha_operacion'])->where('monto', $move['monto'])->where('signo_monto', $move['signo_monto'])
                        ->where('nro_comprobante_extraido', $move['nro_comprobante_extraido'])->where('descripcion_normalizada', $move['descripcion_normalizada'])->exists();
                    if ($duplicate) {
                        $skipped++;

                        continue;
                    }
                    unset($move['nro_fila']);
                    MovimientoBancario::create([...$move, 'id_periodo' => $period->id_periodo, 'id_usuario' => $actor, 'id_archivo_importado_log' => $log->id_archivo_importado_log]);
                    $inserted++;
                    if ($move['tipo_movimiento'] === 'cobranza_cliente' && $move['estado_conciliacion'] === 'pendiente') {
                        $eligible++;
                    }
                }
                $log->update(['cantidad_filas_insertadas' => $inserted, 'cantidad_filas_omitidas' => $skipped]);
                G2OperationAudit::record('bancos', $log->id_archivo_importado_log, 'extracto_importado', $actor, ['hash' => $hash, 'insertadas' => $inserted, 'omitidas' => $skipped]);

                return ['success' => true, 'mensaje' => 'Extracto importado íntegramente.', 'archivo_id' => $log->id_archivo_importado_log, 'filas_leidas' => count($parsed['movimientos']),
                    'filas_insertadas' => $inserted, 'filas_omitidas' => $skipped, 'filas_error' => 0, 'acreditaciones_para_conciliar' => $eligible, 'errores_detalle' => []];
            });
        } catch (\Throwable $error) {
            if (is_string($stored)) {
                Storage::disk('local')->delete($stored);
            }
            throw $error;
        }
    }
}
