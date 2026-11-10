<?php

namespace App\Modules\Reportes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Reportes\Requests\ClientHistoryRequest;
use App\Modules\Reportes\Requests\OperationalReportRequest;
use App\Modules\Reportes\Services\OperationalReportService;
use App\Modules\Reportes\Services\ReportService;
use App\Modules\Reportes\Services\SpreadsheetExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function operational(OperationalReportRequest $request, string $report, string $format,
        OperationalReportService $reports, SpreadsheetExportService $spreadsheets): Response|StreamedResponse
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $data = $reports->dataset($report, $request->filters());
        $filters = $request->filters();
        $date = Carbon::parse($filters['fecha'])->format('d/m/Y');
        $context = match ($report) {
            'daily-sales', 'daily-cash' => 'Fecha: '.$date,
            'low-stock' => 'Disponibilidad: '.(($filters['orden'] ?? 'asc') === 'asc' ? 'menor a mayor' : 'mayor a menor'),
            'due-invoices' => 'Desde '.$date.' | próximos '.$filters['dias'].' días',
            'debtors' => 'Deuda: '.(($filters['orden'] ?? 'mayor') === 'mayor' ? 'mayor a menor' : 'menor a mayor'),
            'product-ranking' => 'Desde: '.($filters['desde'] ?? 'inicio').' | Hasta: '.($filters['hasta'] ?? 'hoy'),
            'client-product-reach' => 'Producto #'.($filters['producto_id'] ?? ''),
        };

        return $this->export($format, $data['title'].' - '.$context, $data['headers'], $data['rows'], $report.'-'.$filters['fecha'], $spreadsheets, $data['summary'] ?? []);
    }

    public function clientHistory(
        ClientHistoryRequest $request,
        string $format,
        ReportService $reports,
        SpreadsheetExportService $spreadsheets
    ): Response|StreamedResponse {
        $data = $request->validated();
        abort_unless(isset($data['cliente_id']), 422, 'Debe seleccionar un cliente.');
        $client = Cliente::query()->findOrFail($data['cliente_id']);
        $sales = $reports->clientSales($client, $data['desde'] ?? null, $data['hasta'] ?? null);
        $rows = $sales->map(fn ($sale): array => [
            $sale->fecha->format('d/m/Y H:i'),
            $sale->numero_factura ?: 'Sin comprobante',
            $sale->detalles->map(fn ($detail): string => $detail->cantidad.' x '.$detail->producto->nombre)->join(', ') ?: 'Sin detalle',
            ucfirst($sale->estado->value),
            (float) $sale->total,
        ])->all();

        return $this->export(
            $format,
            'Historial - '.$client->apellido_razon_social.', '.$client->nombre,
            ['Fecha', 'Comprobante', 'Productos', 'Estado', 'Total'],
            $rows,
            'historial-cliente-'.$client->id,
            $spreadsheets
        );
    }

    private function export(
        string $format,
        string $title,
        array $headers,
        array $rows,
        string $filename,
        SpreadsheetExportService $spreadsheets,
        array $summary = []
    ): Response|StreamedResponse {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        if ($format === 'xlsx') {
            return $spreadsheets->download($title, $headers, $rows, $filename.'.xlsx', $summary);
        }

        return Pdf::loadView('reports.pdf', compact('title', 'headers', 'rows', 'summary'))
            ->setPaper('a4', 'landscape')
            ->download($filename.'.pdf');
    }
}
