<?php

declare(strict_types=1);

namespace App\Modules\Compras\Services;

use App\Modules\Compras\Models\Compra;
use App\Modules\Compras\Models\PagoProveedor;
use App\Modules\Compras\Repositories\PagoProveedorRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * C08 - Registrar múltiples métodos de pago de una compra.
 *
 * Una compra admite tantos pagos como haga falta, incluso del mismo método
 * (PAGO_PROVEEDOR es 1..N contra COMPRA). Cada pago descuenta del saldo a pagar.
 *
 * Nota sobre el saldo: COMPRA.saldo_pendiente lo recalcula RecepcionController
 * (C06) con el sentido de "importe de mercadería todavía no recibida"
 * (suma de cantidades pendientes por el precio de cada detalle). Ese cálculo no se toca
 * para no alterar C06, así que la deuda por pagos se expone aparte como
 * saldo_a_pagar = importe_total - total_pagado. Quien necesite el saldo con el
 * sentido estándar (deuda) debe usar saldo_a_pagar.
 */
class PagoCompraService
{
    public function __construct(private PagoProveedorRepository $pagos) {}

    public function anular(int $idCompra, int $idPago, int $actor, string $reason = 'Anulación administrativa de pago'): array
    {
        $reason = trim($reason);
        abort_unless(\App\Modules\Login\Models\User::whereKey($actor)->where('estado', 'activo')->where('rol', 'administrativo')->exists(), 403);
        validator(['motivo' => $reason], ['motivo' => 'required|string|min:3|max:1000'])->validate();
        return DB::transaction(function () use ($idCompra, $idPago, $actor, $reason) {
            $purchase = Compra::whereKey($idCompra)->lockForUpdate()->firstOrFail();
            $payment = PagoProveedor::where('id_compra', $idCompra)->whereKey($idPago)->lockForUpdate()->firstOrFail();
            if ($payment->anulado_en) return ['repetido' => true, 'pago' => self::formatear($payment), 'resumen' => $this->resumen($idCompra)];
            abort_if($purchase->estado === 'cancelada', 409, 'No se pueden cambiar pagos de una compra anulada.');
            $payment->update(['anulado_en' => now(), 'anulado_por' => $actor, 'motivo_anulacion' => trim($reason)]);
            DB::table('log_auditoria')->insert(['tabla_afectada' => 'pago_proveedor', 'id_registro' => $idPago, 'accion' => 'UPDATE',
                'valores_anteriores' => json_encode(['estado' => 'registrado', 'importe' => $payment->importe], JSON_THROW_ON_ERROR),
                'valores_nuevos' => json_encode(['estado' => 'anulado', 'id_compra' => $idCompra, 'motivo' => trim($reason)], JSON_THROW_ON_ERROR),
                'id_usuario' => $actor, 'fecha_hora' => now()]);
            return ['repetido' => false, 'pago' => self::formatear($payment), 'resumen' => $this->resumen($idCompra)];
        }, 3);
    }

    /** Un lote es una única operación: un pago inválido revierte todos los anteriores. */
    public function registrarMultiples(int $idCompra, array $pagos, int $idUsuario): array
    {
        if (! $pagos) {
            throw new RuntimeException('Debe indicar al menos un pago.');
        }

        return DB::transaction(function () use ($idCompra, $pagos, $idUsuario): array {
            $registrados = [];
            foreach ($pagos as $pago) {
                $registrados[] = $this->registrar($idCompra, $pago, $idUsuario)['pago'];
            }

            return ['pagos' => $registrados, 'resumen' => $this->resumen($idCompra)];
        });
    }

    /**
     * Registra un pago contra la compra.
     *
     * @param  array  $datos  metodo_pago, importe, fecha_pago
     */
    public function registrar(int $idCompra, array $datos, int $idUsuario): array
    {
        return DB::transaction(function () use ($idCompra, $datos, $idUsuario): array {
            $compra = Compra::query()->whereKey($idCompra)->lockForUpdate()->first();
            if (! $compra) {
                throw new RuntimeException('La compra no existe.');
            }

            if ($compra->estado === 'cancelada') {
                throw new RuntimeException('No se pueden registrar pagos en una compra cancelada.');
            }

            $metodo = (string) $datos['metodo_pago'];
            if (! in_array($metodo, PagoProveedor::METODOS, true)) {
                throw new RuntimeException(
                    'Método de pago inválido. Valores permitidos: '.implode(', ', PagoProveedor::METODOS).'.'
                );
            }

            if (! is_numeric($datos['importe']) || ! is_finite((float) $datos['importe'])
                || (float) $datos['importe'] > 9999999999.99
                || abs((float) $datos['importe'] * 100 - round((float) $datos['importe'] * 100)) > 0.00001) {
                throw new RuntimeException('El importe debe ser finito y tener como máximo dos decimales.');
            }
            $importe = round((float) $datos['importe'], 2);
            if ($importe <= 0) {
                throw new RuntimeException('El importe del pago debe ser mayor a cero.');
            }

            $saldo = $this->saldoAPagar($compra);
            if ($importe > $saldo) {
                throw new RuntimeException(
                    'El importe del pago excede el saldo a pagar ($'.number_format($saldo, 2, ',', '.').').'
                );
            }

            $pago = $this->pagos->crear([
                'id_compra' => $idCompra,
                'metodo_pago' => $metodo,
                'importe' => $importe,
                'fecha_pago' => $datos['fecha_pago'],
                'id_usuario' => $idUsuario,
            ]);

            return [
                'pago' => self::formatear($pago),
                'resumen' => $this->resumen($idCompra),
            ];
        });
    }

    /**
     * Detalle de los pagos de una compra y cómo queda su deuda.
     */
    public function resumen(int $idCompra): array
    {
        $compra = Compra::find($idCompra);
        if (! $compra) {
            throw new RuntimeException('La compra no existe.');
        }

        $importeTotal = round((float) $compra->importe_total, 2);
        $totalPagado = $this->pagos->totalPagado($idCompra);
        $saldoAPagar = $this->saldoAPagar($compra);

        return [
            'compra' => [
                'id_compra' => $compra->id_compra,
                'numero_compra' => $compra->numero_compra,
                'numero_comprobante' => $compra->numero_comprobante,
                'estado' => $compra->estado,
            ],
            'importe_total' => $importeTotal,
            'total_pagado' => $totalPagado,
            'saldo_a_pagar' => $saldoAPagar,
            'cantidad_pagos' => $this->pagos->contar($idCompra),
            'pagos_completos' => $saldoAPagar <= 0,
            'por_metodo' => $this->pagos->totalesPorMetodo($idCompra),
            'pagos' => $this->pagos->listarPorCompra($idCompra)
                ->map(fn (PagoProveedor $p) => self::formatear($p))
                ->toArray(),
            'lista' => $this->pagos->historial($idCompra)->map(fn (PagoProveedor $p) => self::formatear($p))->all(),
        ];
    }

    /** Saldo adeudado = importe total menos lo pagado, nunca negativo. */
    private function saldoAPagar(Compra $compra): float
    {
        if ($compra->estado === 'cancelada') return 0.0;
        $saldo = round((float) $compra->importe_total - $this->pagos->totalPagado($compra->id_compra), 2);

        return max(0.0, $saldo);
    }

    public static function formatear(PagoProveedor $p): array
    {
        return [
            'id_pago' => $p->id_pago,
            'id_compra' => $p->id_compra,
            'metodo_pago' => $p->metodo_pago,
            'importe' => round((float) $p->importe, 2),
            'fecha_pago' => $p->fecha_pago?->format('Y-m-d'),
            'id_usuario' => $p->id_usuario,
            'estado' => $p->anulado_en ? 'anulado' : 'registrado',
            'anulado_en' => $p->anulado_en?->toIso8601String(),
            'anulado_por' => $p->anulado_por,
            'motivo_anulacion' => $p->motivo_anulacion,
        ];
    }
}
