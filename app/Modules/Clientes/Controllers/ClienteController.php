<?php

namespace App\Modules\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Models\Zona;
use App\Modules\Clientes\Requests\SearchClienteRequest;
use App\Modules\Clientes\Requests\StoreClienteRequest;
use App\Modules\Clientes\Requests\UpdateClienteRequest;
use App\Modules\Clientes\Services\ClientWriter;
use App\Modules\NotificacionesTransferencia\Models\TransferNotification;
use App\Modules\Reportes\Services\ReportService;
use App\Services\Gateways\ExternalFinanceGateway;
use App\Services\Gateways\RendicionesGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ClienteController extends Controller
{
    public function index(SearchClienteRequest $request): View
    {
        $search = trim((string) $request->validated('q', ''));
        $clientes = Cliente::with('zona')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $pattern = '%'.$search.'%';
                    $query->where('nombre', 'like', $pattern)
                        ->orWhere('apellido_razon_social', 'like', $pattern)
                        ->orWhere('dni_cuit', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern)
                        ->orWhere('telefono', 'like', $pattern)
                        ->orWhere('localidad', 'like', $pattern)
                        ->orWhere('direccion', 'like', $pattern);
                    $query->orWhereHas('zona', fn ($zone) => $zone->where('nombre', 'like', $pattern));
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

        return view('clientes.create', ['zonas' => Zona::orderBy('nombre')->get()]);
    }

    public function show(Request $request, Cliente $cliente, ReportService $reports, ExternalFinanceGateway $finance, RendicionesGateway $rendiciones): View
    {
        Gate::authorize('view', $cliente);
        $filters = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'aviso_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $snapshot = $finance->clientSnapshot($cliente->id);
        $collections = $rendiciones->clientSnapshot($cliente->id);
        $appliedIds = collect($snapshot['payments'])->pluck('rendicion_cobro_id')->filter()->all();
        foreach ($collections['rows'] as &$collection) {
            if (in_array($collection['estado_revision'] ?? null, ['anulado', 'rechazado'], true)) {
                $collection['aplicacion'] = ucfirst($collection['estado_revision']);

                continue;
            }
            $collection['aplicacion'] = in_array($collection['id'], $appliedIds)
                ? 'Aplicado' : ($collection['estado_rendicion'] === 'aprobada' ? 'Aprobada · sin aplicación informada'
                    : ($collection['estado_rendicion'] === 'rechazada' ? 'Rechazado' : 'Declarado · pendiente de aprobación'));
        }
        unset($collection);
        $transferencia = isset($filters['aviso_id']) ? TransferNotification::whereKey($filters['aviso_id'])
            ->where('cliente_id', $cliente->id)->where('estado', 'validada')->firstOrFail() : null;

        $salesReady = $reports->salesAvailable();
        $sales = collect();
        if ($salesReady) {
            try {
                $sales = $reports->clientSales($cliente, $filters['desde'] ?? null, $filters['hasta'] ?? null);
            } catch (HttpExceptionInterface $error) {
                if (! in_array($error->getStatusCode(), [502, 503], true)) {
                    throw $error;
                }
                $salesReady = false;
            }
        }

        return view('clientes.show', [
            'cliente' => $cliente,
            'saldoDisponible' => $snapshot['balance_ready'],
            'saldo' => $snapshot['balance']['saldo'] ?? null,
            'saldoAFavor' => $snapshot['balance']['saldo_a_favor'] ?? 0,
            'pagosDisponibles' => config('services.balances.transport') === 'internal',
            'cobrosDisponibles' => $snapshot['payments_ready'],
            'cobros' => $snapshot['payments'],
            'cobrosRendidos' => $collections,
            'transferencia' => $transferencia,
            'salesReady' => $salesReady, 'ventas' => $sales,
            'desde' => $filters['desde'] ?? '',
            'hasta' => $filters['hasta'] ?? '',
        ]);
    }

    public function store(StoreClienteRequest $request, ClientWriter $writer): RedirectResponse
    {
        $writer->save($request->validated(), creator: $request->user()->id);

        return redirect()->route('clientes.create')
            ->with('success', 'Cliente registrado correctamente.');
    }

    public function edit(Cliente $cliente): View
    {
        Gate::authorize('update', $cliente);

        return view('clientes.edit', ['cliente' => $cliente->load('zona'), 'zonas' => Zona::orderBy('nombre')->get()]);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente, ClientWriter $writer): RedirectResponse
    {
        $writer->save($request->validated(), $cliente);

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }
}
