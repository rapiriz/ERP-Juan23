<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClientStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClientWriteRequest;
use App\Http\Resources\ClientResource;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'buscar' => ['nullable', 'string', 'max:160'],
            'estado' => ['nullable', 'in:activo,inactivo'],
            'tipo' => ['nullable', 'in:minorista,mayorista'],
            'zona_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $search = trim($filters['buscar'] ?? '');
        $clients = Cliente::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $pattern = '%'.$search.'%';
                    $query->where('nombre', 'like', $pattern)
                        ->orWhere('apellido_razon_social', 'like', $pattern)
                        ->orWhere('dni_cuit', 'like', $pattern);
                });
            })
            ->when(isset($filters['estado']), fn ($query) => $query->where('estado', $filters['estado']))
            ->when(isset($filters['tipo']), fn ($query) => $query->where('tipo_cliente', $filters['tipo']))
            ->when(isset($filters['zona_id']), fn ($query) => $query->where('zona_id', $filters['zona_id']))
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'data' => $clients->getCollection()->map(fn (Cliente $client) => (new ClientResource($client))->toArray($request)),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    public function show(Request $request, Cliente $cliente): JsonResponse
    {
        return response()->json(['data' => (new ClientResource($cliente))->toArray($request)]);
    }

    public function store(ClientWriteRequest $request): JsonResponse
    {
        $client = new Cliente($request->validated());
        $client->estado = ClientStatus::ACTIVO;
        $client->creado_por = $request->user()->id;
        $client->save();
        $client->refresh();

        return response()->json(['data' => (new ClientResource($client))->toArray($request)], 201)
            ->header('Location', url('/api/v1/clientes/'.$client->id));
    }

    public function replace(ClientWriteRequest $request, Cliente $cliente): JsonResponse
    {
        $cliente->update($request->validated());

        return response()->json(['data' => (new ClientResource($cliente))->toArray($request)]);
    }

    public function patch(ClientWriteRequest $request, Cliente $cliente): JsonResponse
    {
        $cliente->update($request->validated());

        return response()->json(['data' => (new ClientResource($cliente))->toArray($request)]);
    }

    public function balance(Cliente $cliente): JsonResponse
    {
        return response()->json(['data' => [
            'cliente_id' => $cliente->id,
            'saldo' => $cliente->saldo,
            'moneda' => 'ARS',
        ]]);
    }

    public function payments(Request $request, Cliente $cliente): JsonResponse
    {
        $filters = $request->validate(['per_page' => ['nullable', 'integer', 'between:1,100']]);
        $payments = $cliente->cobros()->with('usuario:id,nombre')
            ->where('estado', PaymentStatus::CONFIRMADO->value)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'data' => $payments->getCollection()->map(fn ($payment) => [
                'id' => $payment->id,
                'cliente_id' => $payment->cliente_id,
                'fecha' => $payment->fecha->toIso8601String(),
                'monto_total' => $payment->monto_total,
                'medio_pago' => $payment->medio_pago->value,
                'comprobante_nro' => $payment->comprobante_nro,
                'observaciones' => $payment->observaciones,
                'usuario_id' => $payment->usuario_id,
                'usuario_nombre' => $payment->usuario->nombre,
            ]),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }
}
