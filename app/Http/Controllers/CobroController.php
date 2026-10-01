<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\Cobros\StoreCobroRequest;
use App\Models\Cliente;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CobroController extends Controller
{
    public function create(Cliente $cliente): View
    {
        return view('cobros.create', [
            'cliente' => $cliente,
            'mediosPago' => PaymentMethod::cases(),
        ]);
    }

    public function store(
        StoreCobroRequest $request,
        Cliente $cliente,
        PaymentService $paymentService
    ): RedirectResponse {
        $paymentService->register($cliente, $request->user(), $request->validated());

        return redirect()->route('clientes.show', $cliente)
            ->with('success', 'Pago registrado. El saldo fue actualizado correctamente.');
    }
}
