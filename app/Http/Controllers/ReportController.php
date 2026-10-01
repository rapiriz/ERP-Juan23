<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\ClientHistoryRequest;
use App\Models\Cliente;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function dailySales(Request $request, ReportService $reports): View
    {
        $validated = $request->validate(['fecha' => ['nullable', 'date']]);
        $date = CarbonImmutable::parse($validated['fecha'] ?? today()->toDateString());
        $sales = $reports->dailySales($date);

        return view('reports.daily-sales', [
            'fecha' => $date->toDateString(),
            'ventas' => $sales,
            'totalFacturado' => $sales->sum(fn ($sale) => (float) $sale->total),
        ]);
    }

    public function lowStock(Request $request, ReportService $reports): View
    {
        $validated = $request->validate(['orden' => ['nullable', 'in:asc,desc']]);
        $order = $validated['orden'] ?? 'asc';

        return view('reports.low-stock', [
            'productos' => $reports->lowStock($order),
            'orden' => $order,
        ]);
    }

    public function clientHistory(ClientHistoryRequest $request, ReportService $reports): View
    {
        $data = $request->validated();
        $client = isset($data['cliente_id']) ? Cliente::query()->findOrFail($data['cliente_id']) : null;

        return view('reports.client-history', [
            'clientes' => Cliente::query()->orderBy('apellido_razon_social')->orderBy('nombre')->get(),
            'cliente' => $client,
            'ventas' => $client ? $reports->clientSales($client, $data['desde'] ?? null, $data['hasta'] ?? null) : collect(),
            'desde' => $data['desde'] ?? '',
            'hasta' => $data['hasta'] ?? '',
        ]);
    }
}
