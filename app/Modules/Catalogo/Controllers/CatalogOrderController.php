<?php

namespace App\Modules\Catalogo\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalogo\Services\CatalogOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogOrderController extends Controller
{
    public function cart()
    {
        return response()->view('catalogo.cart')->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, CatalogOrderService $orders)
    {
        return response()->json($orders->submit($request->all()), 201)->header('Cache-Control', 'no-store');
    }

    public function receipt(string $token)
    {
        $order = DB::table('g1_catalog_orders')->where('receipt_hash', hash('sha256', $token))->firstOrFail();
        return response()->view('catalogo.receipt', ['order' => $order,
            'items' => DB::table('g1_catalog_order_items')->where('order_id', $order->id)->get()])
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function index(Request $request)
    {
        $data = $request->validate(['estado' => 'nullable|in:solicitado,en_revision,convertido,rechazado']);
        return view('catalogo.orders', ['orders' => DB::table('g1_catalog_orders')->when($data['estado'] ?? null, fn ($q, $state) => $q->where('estado', $state))->orderByDesc('id')->paginate(25)]);
    }

    public function show(int $id)
    {
        return view('catalogo.order', ['order' => DB::table('g1_catalog_orders')->where('id', $id)->firstOrFail(),
            'items' => DB::table('g1_catalog_order_items')->where('order_id', $id)->get(),
            'events' => DB::table('g1_catalog_order_events')->where('order_id', $id)->orderBy('id')->get(),
            'clients' => DB::table('clientes')->where('estado', 'activo')->orderBy('apellido_razon_social')->get()]);
    }

    public function review(Request $request, int $id, CatalogOrderService $orders)
    {
        $result = $orders->review($id, $request->all(), $request->user());
        if ($request->expectsJson()) {
            return response()->json(['data' => $result]);
        }
        return redirect()->route('catalogo.orders.show', $id)->with('status', 'Solicitud actualizada.');
    }
}
