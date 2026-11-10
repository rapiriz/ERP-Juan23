<?php

namespace App\Modules\Proveedores\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SupplierPerformance
{
    public function report(array $filters): array
    {
        $purchases = DB::table('compra')->where('estado', '!=', 'cancelada')
            ->when($filters['desde'] ?? null, fn ($q, $date) => $q->whereDate('fecha_compra', '>=', $date))
            ->when($filters['hasta'] ?? null, fn ($q, $date) => $q->whereDate('fecha_compra', '<=', $date))
            ->when($filters['id_proveedor'] ?? null, fn ($q, $id) => $q->where('id_proveedor', $id))->get();
        $received = DB::table('recepcion')->whereIn('id_compra', $purchases->pluck('id_compra'))
            ->selectRaw('id_compra, MAX(fecha_recepcion) as ultima')->groupBy('id_compra')->pluck('ultima', 'id_compra');
        $suppliers = DB::table('proveedor')->when($filters['id_proveedor'] ?? null, fn ($q, $id) => $q->where('id_proveedor', $id))->orderBy('razon_social')->get();
        $rows = [];
        foreach ($suppliers as $supplier) {
            $all = $purchases->where('id_proveedor', $supplier->id_proveedor);
            $known = $all->filter(fn ($p) => $p->fecha_entrega_prevista !== null);
            $complete = $known->filter(fn ($p) => $p->estado === 'completada' && $received->has($p->id_compra));
            $delays = $complete->map(fn ($p) => max(0, (int) CarbonImmutable::parse($p->fecha_entrega_prevista)->startOfDay()
                ->diffInDays(CarbonImmutable::parse($received->get($p->id_compra))->startOfDay(), false)));
            $onTime = $delays->filter(fn ($days) => $days === 0)->count();
            $rows[] = ['id_proveedor' => (int) $supplier->id_proveedor, 'proveedor' => $supplier->razon_social,
                'compras' => $all->count(), 'sin_fecha_comprometida' => $all->count() - $known->count(),
                'entregas_evaluables' => $complete->count(), 'en_termino' => $onTime,
                'porcentaje_en_termino' => $complete->isEmpty() ? null : round($onTime * 100 / $complete->count(), 2),
                'demora_promedio_dias' => $delays->isEmpty() ? null : round($delays->avg(), 2),
                'pendientes_vencidas' => $known->filter(fn ($p) => $p->estado !== 'completada' && substr($p->fecha_entrega_prevista, 0, 10) < today()->toDateString())->count(),
                'completadas_sin_recepcion_verificable' => $known->filter(fn ($p) => $p->estado === 'completada' && ! $received->has($p->id_compra))->count()];
        }
        return ['data' => $rows, 'criterio' => 'Compras no canceladas por fecha de compra. Cumplimiento sobre compras completas con compromiso y recepción verificables; demora en días calendario, incluyendo cero en las entregas a tiempo. Sin fecha no equivale a incumplimiento.',
            'filters' => $filters, 'consultado_en' => now()->toIso8601String()];
    }
}
