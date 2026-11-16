<?php

namespace App\Modules\ConciliacionBancaria\Services;

use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use App\Modules\ConciliacionBancaria\Repositories\ConciliacionRepository;
use App\Modules\Rendiciones\Models\RendicionCobro;
use App\Modules\Shared\Services\ClienteService;
use Illuminate\Support\Carbon;
use Exception;

class ConciliacionService
{
    private ConciliacionRepository $repository;

    public function __construct(ConciliacionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * HU-02: Obtener propuestas de auto-matching.
     */
    public function obtenerSugerencias(): array
    {
        // 1. Obtener movimientos bancarios pendientes y sin rendición asignada
        $movimientosPendientes = MovimientoBancario::where('estado_conciliacion', 'pendiente')
            ->whereNull('id_rendicion_cobro')
            ->get();

        // 2. Obtener cobros del ERP pendientes (que no estén ya vinculados)
        $idsAsociados = MovimientoBancario::whereNotNull('id_rendicion_cobro')->pluck('id_rendicion_cobro');
        $cobrosPendientes = RendicionCobro::whereNotIn('id_rendicion_cobro', $idsAsociados)->get();

        $altaConfianza = [];
        $paraRevisar = [];

        foreach ($movimientosPendientes as $mov) {
            foreach ($cobrosPendientes as $cobro) {
                // Criterio 1: El monto debe ser exactamente igual (redondeado a 2 decimales)
                if (round(floatval($mov->monto), 2) !== round(floatval($cobro->monto), 2)) {
                    continue; 
                }

                // Limpiamos de guiones y espacios el CUIT del movimiento bancario
                $cuitMov = preg_replace('/\D/', '', (string) ($mov->cuit_extraido ?? ''));

                // Obtenemos información del cliente del ERP y limpiamos su CUIT
                $clienteInfo = ClienteService::obtenerCliente((int) $cobro->id_cliente);
                $cuitCliente = preg_replace('/\D/', '', (string) ($clienteInfo['dni_cuit'] ?? ($clienteInfo['cuit'] ?? '')));
                
                // Evaluamos coincidencia de CUIT (si ambos existen y coinciden total o parcialmente)
                $coincideCuit = ($cuitMov !== '' && $cuitCliente !== '' && ($cuitMov === $cuitCliente || str_contains($cuitCliente, $cuitMov) || str_contains($cuitMov, $cuitCliente)));
                
                // Evaluamos proximidad de fechas (diferencia menor o igual a 5 días hábiles)
                $fechaCobro = $cobro->fecha_registro ?? $cobro->fecha ?? now();
                $diferenciaDias = abs(Carbon::parse($mov->fecha_operacion)->diffInDays(Carbon::parse($fechaCobro)));
                $fechaCercana = ($diferenciaDias <= 5);

                $propuesta = [
                    'movimiento' => $mov,
                    'cobro' => $cobro,
                    'cliente' => $clienteInfo,
                    'diferencia_dias' => $diferenciaDias
                ];

                // Si el CUIT coincide y la fecha está cerca -> Va a "Coincidencias sugeridas" (Alta confianza)
                if ($coincideCuit && $fechaCercana) {
                    $altaConfianza[] = $propuesta;
                } else {
                    // Si el monto coincide pero la fecha está un poco más alejada o el CUIT no vino en el banco -> Va a "Coincidencias por revisar"
                    $paraRevisar[] = $propuesta;
                }
            }
        }

        return [
            'alta_confianza' => $altaConfianza,
            'para_revisar' => $paraRevisar
        ];
    }

    /**
     * HU-02: Ejecutar auto-matching de todas las propuestas de alta confianza.
     */
    public function ejecutarAutoMatching(): array
    {
        $propuestas = $this->repository->obtenerPropuestasAutoMatching();
        $conciliados = [];

        foreach ($propuestas['alta_confianza'] as $item) {
            $mov = $item['movimiento'];
            $cobro = $item['cobro'];

            $res = $this->repository->conciliarMovimientoConCobro(
                $mov->id_movimiento_bancario,
                $cobro->id_rendicion_cobro,
                'Conciliado automáticamente por Auto-Matching (monto y fecha coincidentes)'
            );
            $conciliados[] = $res;
        }

        return [
            'total_procesados' => count($conciliados),
            'conciliados' => $conciliados,
        ];
    }

    /**
     * HU-02: Conciliación manual entre un movimiento y un cobro seleccionado.
     */
    public function conciliarManual(int $idMovimiento, int $idRendicionCobro, ?string $observaciones = null): MovimientoBancario
    {
        return $this->repository->conciliarMovimientoConCobro($idMovimiento, $idRendicionCobro, $observaciones);
    }

    /**
     * HU-03: Gestión de excepciones y estados sin tablas adicionales.
     */
    public function gestionarExcepcion(int $idMovimiento, string $estado, ?string $motivo = null): MovimientoBancario
    {
        $estadosValidos = ['en_disputa', 'revision_manual', 'no_aplica', 'cerrado_definitivo', 'pendiente'];
        if (!in_array($estado, $estadosValidos)) {
            throw new Exception("Estado de conciliación inválido: {$estado}");
        }

        return $this->repository->cambiarEstadoMovimiento($idMovimiento, $estado, $motivo);
    }
}
