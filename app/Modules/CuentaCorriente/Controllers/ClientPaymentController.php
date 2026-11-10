<?php

namespace App\Modules\CuentaCorriente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\CuentaCorriente\Requests\ClientPaymentRequest;
use App\Modules\CuentaCorriente\Services\ClientPaymentService;

class ClientPaymentController extends Controller
{
    public function store(ClientPaymentRequest $request, Cliente $cliente, ClientPaymentService $payments)
    {
        abort_if($request->routeIs('clientes.payments.store') && config('services.balances.transport') !== 'internal',
            503, 'El registro de pagos no está habilitado para esta conexión.');
        $data = $payments->register($cliente->id, $request->validated(), $request->user());
        if ($request->expectsJson()) {
            return response()->json(['data' => $data], $data['repetido'] ? 200 : 201)->header('Cache-Control', 'no-store');
        }

        return redirect()->route('clientes.show', $cliente)->with('success', $data['repetido']
            ? 'El pago ya estaba registrado; se conservó una sola operación.' : 'Pago registrado. La deuda restante es $ '.number_format($data['saldo_restante'], 2, ',', '.').'.');
    }
}
