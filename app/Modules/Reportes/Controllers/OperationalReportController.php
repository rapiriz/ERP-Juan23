<?php

namespace App\Modules\Reportes\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reportes\Requests\OperationalReportRequest;
use App\Modules\Reportes\Services\OperationalReportService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class OperationalReportController extends Controller
{
    public function show(OperationalReportRequest $request, string $report, OperationalReportService $reports)
    {
        $filters = $request->filters();
        $titles = ['daily-sales' => 'Ventas diarias', 'daily-cash' => 'Caja diaria', 'low-stock' => 'Stock bajo', 'due-invoices' => 'Vencimientos'];
        abort_unless(isset($titles[$report]), 404);
        try {
            $data = $reports->dataset($report, $filters);
            $ready = true;
        } catch (HttpExceptionInterface $error) {
            if (! in_array($error->getStatusCode(), [502, 503], true)) {
                throw $error;
            }
            $data = ['title' => $titles[$report], 'rows' => [], 'headers' => [], 'summary' => []];
            $ready = false;
        }

        return response()->view('reports.operational', compact('data', 'report', 'filters', 'ready'))->header('Cache-Control', 'no-store');
    }

    public function data(OperationalReportRequest $request, string $report, OperationalReportService $reports)
    {
        return response()->json(['data' => $reports->dataset($report, $request->filters())])->header('Cache-Control', 'no-store');
    }
}
