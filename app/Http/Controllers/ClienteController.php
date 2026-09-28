<?php

namespace App\Http\Controllers;

use App\Enums\ClientStatus;
use App\Http\Requests\Clientes\SearchClienteRequest;
use App\Http\Requests\Clientes\StoreClienteRequest;
use App\Http\Requests\Clientes\UpdateClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(SearchClienteRequest $request): View
    {
        $search = trim((string) $request->validated('q', ''));
        $clientes = Cliente::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $pattern = '%'.$search.'%';
                    $query->where('nombre', 'like', $pattern)
                        ->orWhere('apellido_razon_social', 'like', $pattern)
                        ->orWhere('dni_cuit', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern)
                        ->orWhere('telefono', 'like', $pattern)
                        ->orWhere('direccion', 'like', $pattern);
                });
            })
            ->orderBy('apellido_razon_social')
            ->orderBy('nombre')
            ->limit(100)
            ->get();

        return view('clientes.index', compact('clientes', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('create', Cliente::class);

        return view('clientes.create');
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $cliente = new Cliente($request->validated());
        $cliente->estado = ClientStatus::ACTIVO;
        $cliente->creado_por = $request->user()->id;
        $cliente->save();

        return redirect()->route('clientes.create')
            ->with('success', 'Cliente registrado correctamente.');
    }

    public function edit(Cliente $cliente): View
    {
        Gate::authorize('update', $cliente);

        return view('clientes.edit', compact('cliente'));
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }
}
