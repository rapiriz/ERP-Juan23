<?php

namespace App\Modules\Shared\Services;

use Illuminate\Support\Facades\DB;

/** Sólo lectura: no infiere vínculos por coincidencias de fecha o importe. */
class FinancialIntegrityReview
{
    public function findings(int $limit = 100): array
    {
        $queries = [
            'notas_credito_sin_respaldo' => DB::table('cuenta_corriente_movimientos as m')
                ->where('m.tipo', 'nota_credito')->where('m.importe', '>', 0)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('g4_notas_credito as n')
                    ->whereColumn('n.movimiento_id', 'm.id')->whereColumn('n.cliente_id', 'm.cliente_id')->whereColumn('n.total', 'm.importe')
                    ->where(fn ($s) => $s->whereNull('m.venta_id')->orWhereColumn('n.venta_id', 'm.venta_id')))
                ->select('m.id', 'm.cliente_id', 'm.importe'),
            'asientos_sin_pago' => DB::table('cuenta_corriente_movimientos as m')->leftJoin('cuenta_corriente_pagos as p', 'p.movimiento_cuenta_id', '=', 'm.id')
                ->whereIn('m.tipo', ['pago', 'cobro'])->where('m.importe', '>', 0)->whereNull('p.id')->select('m.id', 'm.cliente_id', 'm.importe'),
            'cobros_sin_aplicacion' => DB::table('cobro as c')->leftJoin('g2_cobro_aplicaciones as a', 'a.id_cobro', '=', 'c.id_cobro')
                ->where('c.estado', 'registrado')->whereNull('a.id_cobro')->select('c.id_cobro as id', 'c.id_cliente as cliente_id', 'c.monto_total as importe'),
            'pagos_con_respaldo_inconsistente' => DB::table('cuenta_corriente_pagos as p')
                ->leftJoin('cuenta_corriente_movimientos as m', 'm.id', '=', 'p.movimiento_cuenta_id')
                ->leftJoin('CAJA_MOVIMIENTO as c', 'c.id_movimiento_caja', '=', 'p.movimiento_caja_id')
                ->leftJoin('g2_cobro_aplicaciones as a', 'a.pago_id', '=', 'p.id')
                ->where(fn ($q) => $q->whereNull('m.id')->orWhereNull('c.id_movimiento_caja')->orWhereNull('a.id_cobro')
                    ->orWhereColumn('m.cliente_id', '!=', 'p.cliente_id')->orWhereColumn('m.importe', '!=', 'p.monto')->orWhereColumn('c.monto', '!=', 'p.monto'))
                ->select('p.id', 'p.cliente_id', 'p.monto as importe'),
        ];
        $result = [];
        foreach ($queries as $key => $query) {
            $result[$key] = ['total' => (clone $query)->count(), 'registros' => $query->orderBy(match ($key) {
                'asientos_sin_pago', 'notas_credito_sin_respaldo' => 'm.id', 'cobros_sin_aplicacion' => 'c.id_cobro', default => 'p.id'
            })->limit(max(1, min(200, $limit)))->get()->all()];
        }

        return $result;
    }
}
