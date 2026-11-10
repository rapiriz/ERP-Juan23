<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Productos\Models\Producto;
use App\Modules\Stock\Models\Lote;
use App\Modules\Stock\Services\LotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoteController extends Controller
{
    public function page()
    {
        return view('inventario.lotes', ['products' => Producto::where('estado', 'activo')->orderBy('nombre')->get()]);
    }

    public function index(Request $request)
    {
        $data = $request->validate(['dias' => 'nullable|integer|in:7,15,30,90,365', 'estado' => 'nullable|in:todos,vigente,proximo,vencido,consumido,pendiente_revision',
            'id_producto' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $days = (int) ($data['dias'] ?? 30);
        $model = new Producto;
        $query = DB::table('lote as l')->leftJoin($model->getTable().' as p', 'p.'.$model->getKeyName(), '=', 'l.id_producto')
            ->select('l.*', 'p.nombre as producto', 'p.codigo');
        $query->when($data['id_producto'] ?? null, fn ($q, $id) => $q->where('l.id_producto', $id));
        $query->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('l.nro_lote', 'like', '%'.$term.'%')->orWhere('p.nombre', 'like', '%'.$term.'%')->orWhere('p.codigo', 'like', '%'.$term.'%')));
        $state = $data['estado'] ?? $request->route('estado') ?? 'todos';
        if ($state === 'pendiente_revision') {
            $query->where('l.estado_trazabilidad', 'pendiente_revision');
        } elseif ($state === 'consumido') {
            $query->where('l.cantidad_actual', '<=', 0);
        } elseif ($state === 'vencido') {
            $query->where('l.cantidad_actual', '>', 0)->whereDate('l.fecha_vencimiento', '<', today());
        } elseif (in_array($state, ['vigente', 'proximo'], true)) {
            $query->where('l.cantidad_actual', '>', 0)->whereDate('l.fecha_vencimiento', '>=', today());
            if ($state === 'proximo') {
                $query->whereDate('l.fecha_vencimiento', '<=', today()->addDays($days));
            }
        }
        $rows = $query->orderBy('l.fecha_vencimiento')->orderBy('l.id_lote')->paginate(50);
        $rows->getCollection()->transform(function ($lot) use ($days) {
            $lot->estado = match (true) {
                (int) $lot->cantidad_actual <= 0 => 'Consumido',
                substr($lot->fecha_vencimiento, 0, 10) < today()->toDateString() => 'Vencido',
                substr($lot->fecha_vencimiento, 0, 10) <= today()->addDays($days)->toDateString() => 'Próximo a vencer',
                default => 'Vigente',
            };
            return $lot;
        });
        return response()->json(['data' => $rows->items(), 'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
            'alertas' => ['vencidos' => Lote::where('estado_trazabilidad', 'controlado')->where('cantidad_actual', '>', 0)->whereDate('fecha_vencimiento', '<', today())->count(),
                'proximos' => Lote::where('estado_trazabilidad', 'controlado')->where('cantidad_actual', '>', 0)->whereBetween('fecha_vencimiento', [today(), today()->addDays($days)])->count(),
                'historicos_pendientes' => Lote::where('estado_trazabilidad', 'pendiente_revision')->count()]]);
    }

    public function store(Request $request, LotService $lots)
    {
        return response()->json(['data' => $lots->register($request->all(), $request->user()->id)], 201);
    }

    public function adopt(Request $request, int $id, LotService $lots)
    {
        $request->validate(['confirmar' => 'required|accepted']);
        return response()->json(['data' => $lots->adopt($id, $request->user()->id)]);
    }

    public function history(int $id)
    {
        Lote::findOrFail($id);
        return response()->json(['data' => DB::table('stock_lote_movimientos')->where('id_lote', $id)->orderByDesc('id')->paginate(50)]);
    }
}
