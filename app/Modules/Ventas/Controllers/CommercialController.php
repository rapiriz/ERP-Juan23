<?php

namespace App\Modules\Ventas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Services\ClientWriter;
use App\Modules\CuentaCorriente\Services\FinancialReadService;
use App\Modules\Ventas\Models\Venta;
use App\Modules\Ventas\Models\VentaHistorial;
use App\Modules\Ventas\Services\CommercialReturnService;
use App\Modules\Ventas\Services\CreditLimitService;
use App\Support\UsuarioActual;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommercialController extends Controller
{
    public function credit(int $id, CreditLimitService $credit)
    {
        return response()->json(['data' => $credit->status($id)])->header('Cache-Control', 'no-store');
    }

    public function setCredit(Request $request, int $id, ClientWriter $writer, CreditLimitService $credit)
    {
        $data = $request->validate(['limite_credito' => ['present', 'nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2']]);
        $writer->save($data, Cliente::findOrFail($id));

        return response()->json(['data' => $credit->status($id)]);
    }

    public function comment(Request $request, int $id)
    {
        $data = $request->validate(['observaciones' => ['present', 'nullable', 'string', 'max:150'],
            'items' => ['prohibited'], 'total' => ['prohibited'], 'cliente_id' => ['prohibited'], 'estado' => ['prohibited']]);
        $result = DB::transaction(function () use ($id, $data, $request) {
            $sale = Venta::whereKey($id)->lockForUpdate()->firstOrFail();
            $before = $sale->observaciones;
            $after = trim($data['observaciones'] ?? '');
            if (($before ?? '') !== $after) {
                $sale->update(['observaciones' => $after]);
                VentaHistorial::create(['id_venta' => $id, 'accion' => 'observaciones', 'estado_anterior' => $sale->estado,
                    'estado_nuevo' => $sale->estado, 'datos' => ['anterior' => $before, 'nuevo' => $after], 'id_usuario' => UsuarioActual::id($request)]);
            }

            return ['id' => $id, 'observaciones' => $after];
        });

        return response()->json(['data' => $result]);
    }

    public function index(Request $request)
    {
        $data = $request->validate(['cliente_id' => 'nullable|integer|min:1', 'venta_id' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1']);
        $query = DB::table('g4_devoluciones')->when($data['cliente_id'] ?? null, fn ($q, $v) => $q->where('cliente_id', $v))
            ->when($data['venta_id'] ?? null, fn ($q, $v) => $q->where('venta_id', $v))->orderByDesc('id');

        return response()->json($query->paginate(20))->header('Cache-Control', 'no-store');
    }

    public function detail(int $id)
    {
        $return = DB::table('g4_devoluciones')->where('id', $id)->firstOrFail();

        return response()->json(['data' => ['devolucion' => $return,
            'items' => DB::table('g4_devolucion_detalles')->where('devolucion_id', $id)->orderBy('id')->get(),
            'nota_credito' => DB::table('g4_notas_credito')->where('devolucion_id', $id)->first(),
            'cuenta' => app(FinancialReadService::class)->account($return->cliente_id)['balance']]])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, int $id, CommercialReturnService $returns)
    {
        $result = $returns->register($id, $request->all(), $request->user());

        return response()->json(['data' => $result], $result['repetido'] ? 200 : 201);
    }

    public function notePdf(int $id)
    {
        $note = DB::table('g4_notas_credito')->where('devolucion_id', $id)->firstOrFail();
        $return = DB::table('g4_devoluciones')->where('id', $id)->firstOrFail();

        return Pdf::loadView('devoluciones.nota', [
            'note' => $note, 'return' => $return, 'client' => Cliente::findOrFail($note->cliente_id),
            'items' => DB::table('g4_devolucion_detalles')->where('devolucion_id', $id)->get(),
        ])->download($note->numero.'.pdf');
    }
}
