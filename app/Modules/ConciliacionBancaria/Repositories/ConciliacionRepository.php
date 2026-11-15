<?php

namespace App\Modules\ConciliacionBancaria\Repositories;

use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use App\Modules\Rendiciones\Models\RendicionCobro;
use App\Modules\Shared\Services\ClienteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConciliacionRepository
{
    /**
     * Propone coincidencias automáticas cruzando MOVIMIENTO_BANCARIO ('pendiente', 'cobranza_cliente')
     * contra RENDICION_COBRO (rendición aprobada, transferencias/cheques no conciliados).
     */
    public function obtenerPropuestasAutoMatching(int $toleranciaDias = 5): array
    {
        $movimientos = MovimientoBancario::where('estado_conciliacion', 'pendiente')
            ->where('tipo_movimiento', 'cobranza_cliente')
            ->where('signo_monto', 'ingreso')
            ->orderBy('fecha_operacion', 'asc')
            ->get();

        $idsYaConciliados = MovimientoBancario::where('estado_conciliacion', 'matcheado')
            ->whereNotNull('id_rendicion_cobro')
            ->pluck('id_rendicion_cobro')
            ->toArray();

        $cobrosDisponibles = RendicionCobro::query()
            ->join('RENDICION', 'RENDICION_COBRO.id_rendicion', '=', 'RENDICION.id_rendicion')
            ->where('RENDICION.estado', 'aprobada')
            ->whereIn('RENDICION_COBRO.medio_pago', ['transferencia', 'cheque'])
            ->whereNotIn('RENDICION_COBRO.id_rendicion_cobro', $idsYaConciliados)
            ->select('RENDICION_COBRO.*')
            ->get();

        $altaConfianza = [];
        $paraRevisar = [];
        $cobrosAsignados = [];

        foreach ($movimientos as $mov) {
            $montoMov = (float)$mov->monto;
            $fechaMov = Carbon::parse($mov->fecha_operacion);
            $cuitMov = $mov->cuit_extraido ? preg_replace('/\D/', '', $mov->cuit_extraido) : null;

            $mejorCoincidencia = null;
            $nivelConfianza = null;

            foreach ($cobrosDisponibles as $cobro) {
                if (in_array($cobro->id_rendicion_cobro, $cobrosAsignados)) {
                    continue;
                }

                $montoCobro = (float)$cobro->monto;
                $fechaCobro = Carbon::parse($cobro->fecha_registro);
                $diasDiferencia = abs($fechaMov->diffInDays($fechaCobro, false));

                // Si coincide monto exacto
                if (abs($montoMov - $montoCobro) < 0.01) {
                    $cliente = ClienteService::obtenerCliente((int)$cobro->id_cliente);
                    $cuitCliente = !empty($cliente['dni_cuit']) ? preg_replace('/\D/', '', $cliente['dni_cuit']) : null;
                    $coincideCuit = ($cuitMov && $cuitCliente && $cuitMov === $cuitCliente);

                    if ($diasDiferencia <= $toleranciaDias) {
                        if ($coincideCuit || $diasDiferencia <= 2) {
                            $mejorCoincidencia = [
                                'movimiento' => $mov,
                                'cobro' => $cobro,
                                'cliente' => $cliente,
                                'diferencia_dias' => $diasDiferencia,
                                'coincide_cuit' => $coincideCuit,
                            ];
                            $nivelConfianza = 'alta';
                            break;
                        } else {
                            $mejorCoincidencia = [
                                'movimiento' => $mov,
                                'cobro' => $cobro,
                                'cliente' => $cliente,
                                'diferencia_dias' => $diasDiferencia,
                                'coincide_cuit' => false,
                            ];
                            $nivelConfianza = 'revisar';
                        }
                    }
                }
            }

            if ($mejorCoincidencia) {
                $cobrosAsignados[] = $mejorCoincidencia['cobro']->id_rendicion_cobro;
                if ($nivelConfianza === 'alta') {
                    $altaConfianza[] = $mejorCoincidencia;
                } else {
                    $paraRevisar[] = $mejorCoincidencia;
                }
            }
        }

        return [
            'alta_confianza' => $altaConfianza,
            'para_revisar' => $paraRevisar,
        ];
    }

    /**
     * Ejecuta la conciliación (match) de un movimiento bancario con un cobro de rendición.
     */
    public function conciliarMovimientoConCobro(int $idMovimiento, int $idRendicionCobro, ?string $observaciones = null): MovimientoBancario
    {
        return DB::transaction(function () use ($idMovimiento, $idRendicionCobro, $observaciones) {
            $movimiento = MovimientoBancario::findOrFail($idMovimiento);
            $cobro = RendicionCobro::findOrFail($idRendicionCobro);

            $movimiento->update([
                'estado_conciliacion' => 'matcheado',
                'id_rendicion_cobro' => $cobro->id_rendicion_cobro,
                'observaciones_conciliacion' => $observaciones ?? "Conciliado con Rendición Cobro #{$cobro->id_rendicion_cobro}",
            ]);

            return $movimiento;
        });
    }

    /**
     * Aplica cambio de estado administrativo a un movimiento (HU-03).
     */
    public function cambiarEstadoMovimiento(int $idMovimiento, string $nuevoEstado, ?string $motivo = null): MovimientoBancario
    {
        $movimiento = MovimientoBancario::findOrFail($idMovimiento);
        $movimiento->update([
            'estado_conciliacion' => $nuevoEstado,
            'observaciones_conciliacion' => $motivo ?? $movimiento->observaciones_conciliacion,
        ]);

        return $movimiento;
    }
}
