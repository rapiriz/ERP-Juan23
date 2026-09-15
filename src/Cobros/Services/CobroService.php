<?php

namespace App\Cobros\Services;

use App\Cobros\Repositories\CobroRepository;
use App\Cobros\Models\Cobro;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CobroService
{
    public function __construct(private CobroRepository $repository)
    {
    }

    /**
     * Registra un nuevo cobro a cliente cumpliendo todos los criterios de aceptación.
     */
    public function registrarCobro(array $datos): array
    {
        // 1. Validaciones de Dominio / Negocio
        $monto = (float) ($datos['monto_total'] ?? 0);
        if ($monto <= 0) {
            throw new InvalidArgumentException("El monto del cobro debe ser mayor a cero.");
        }

        $idCliente = (int) ($datos['id_cliente'] ?? 0);
        if (!$this->repository->clienteEstaActivo($idCliente)) {
            throw new InvalidArgumentException("El cliente no existe o no se encuentra activo.");
        }

        $medioPago = $datos['medio_pago'] ?? Cobro::MEDIO_EFECTIVO;
        $mediosValidos = [Cobro::MEDIO_EFECTIVO, Cobro::MEDIO_TRANSFERENCIA, Cobro::MEDIO_CHEQUE];
        if (!in_array($medioPago, $mediosValidos, true)) {
            throw new InvalidArgumentException("Medio de pago inválido. Permitidos: " . implode(', ', $mediosValidos));
        }

        // 2. Generar número de comprobante si no fue provisto
        $comprobanteNro = $datos['comprobante_nro'] ?? ('REC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

        // 3. Ejecutar transacción atómica
        return DB::transaction(function () use ($datos, $idCliente, $monto, $medioPago, $comprobanteNro) {
            // A. Crear entidad Cobro
            $cobro = $this->repository->crear([
                'id_cliente' => $idCliente,
                'id_usuario' => (int) ($datos['id_usuario'] ?? 1),
                'fecha' => $datos['fecha'] ?? now(),
                'monto_total' => $monto,
                'medio_pago' => $medioPago,
                'comprobante_nro' => $comprobanteNro,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            // B. Aplicar cobro a las ventas/facturas pendientes (Estrategia FIFO)
            $ventasPendientes = $this->repository->obtenerVentasPendientesCliente($idCliente);
            $montoRestantePorAplicar = $monto;
            $ventasAplicadas = [];

            foreach ($ventasPendientes as $venta) {
                if ($montoRestantePorAplicar <= 0) {
                    break;
                }

                $totalVenta = (float) $venta->total;
                $montoAAplicar = min($montoRestantePorAplicar, $totalVenta);

                $this->repository->asociarVenta($cobro->id_cobro, $venta->id_venta, $montoAAplicar);
                $ventasAplicadas[] = [
                    'id_venta' => $venta->id_venta,
                    'monto_aplicado' => $montoAAplicar,
                ];

                if ($montoAAplicar >= $totalVenta) {
                    $this->repository->marcarVentaComoPagada($venta->id_venta);
                }

                $montoRestantePorAplicar -= $montoAAplicar;
            }

            // C. Si el medio de pago es efectivo, impactar en Caja
            $movimientoCaja = null;
            if ($medioPago === Cobro::MEDIO_EFECTIVO) {
                $movimientoCaja = $this->repository->crearMovimientoCaja([
                    'monto' => $monto,
                    'concepto' => "Cobro #{$cobro->id_cobro} - Comprobante: {$comprobanteNro}",
                    'id_usuario' => $cobro->id_usuario,
                ]);
            }

            // D. Devolver resultado estructurado para el Controller
            return [
                'id_cobro' => $cobro->id_cobro,
                'id_cliente' => $cobro->id_cliente,
                'id_usuario' => $cobro->id_usuario,
                'fecha' => $cobro->fecha,
                'monto_total' => $cobro->monto_total,
                'medio_pago' => $cobro->medio_pago,
                'comprobante_nro' => $cobro->comprobante_nro,
                'estado' => $cobro->estado,
                'observaciones' => $cobro->observaciones,
                'ventas_asociadas' => $ventasAplicadas,
                'movimiento_caja' => $movimientoCaja ? $movimientoCaja->id_movimiento_caja : null,
            ];
        });
    }

    /**
     * Consulta el saldo total pendiente de un cliente.
     */
    public function obtenerSaldoPendiente(int $idCliente): float
    {
        $ventas = $this->repository->obtenerVentasPendientesCliente($idCliente);
        $total = 0.0;
        foreach ($ventas as $v) {
            $total += (float) $v->total;
        }
        return round($total, 2);
    }
}
