<?php

namespace App\Modules\Reportes\Services;

use App\Modules\Clientes\Models\Cliente;
use App\Modules\Reportes\Enums\SaleStatus;
use App\Services\Gateways\ExternalCatalogGateway;
use App\Services\Gateways\ExternalFinanceGateway;
use App\Services\Gateways\ExternalJsonClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/** Lecturas completas por los contratos de los módulos de origen. */
class OperationalReportService
{
    public function __construct(private ExternalJsonClient $api, private ExternalFinanceGateway $finance,
        private ReportData $data, private ReportService $reports, private ExternalCatalogGateway $catalog) {}

    public function dataset(string $report, array $filters): array
    {
        return match ($report) {
            'daily-sales' => $this->dailySales($filters['fecha']),
            'daily-cash' => $this->dailyCash($filters['fecha']),
            'low-stock' => $this->lowStock($filters['orden'] ?? 'asc'),
            'due-invoices' => $this->dueInvoices($filters['fecha'], (int) ($filters['dias'] ?? 7)),
            'debtors' => $this->debtors($filters['orden'] ?? 'mayor'),
            'product-ranking' => $this->ranking($filters),
            'client-product-reach' => $this->buyers($filters),
            default => abort(404),
        };
    }

    public function dailySales(string $date): array
    {
        abort_unless($this->api->isConfigured('sales'), 503, 'Ventas no está disponible.');
        $sales = $this->data->sales($this->api->getList('sales', config('services.sales.history_path'), ['desde' => $date, 'hasta' => $date]));
        abort_if($sales->contains(fn ($sale) => $sale->fecha->timezone(config('app.timezone'))->toDateString() !== $date), 502, 'Ventas respondió fuera de la fecha solicitada.');
        $sales = $sales->reject(fn ($sale) => in_array($sale->estado->value, ['pendiente', 'cancelada'], true));
        $total = $sales->sum(fn ($sale) => (int) round($sale->total * 100));
        $invoiced = $sales->where('estado', SaleStatus::Facturada)->sum(fn ($sale) => (int) round($sale->total * 100));

        return ['title' => 'Ventas diarias', 'headers' => ['Fecha', 'Comprobante', 'Cliente', 'Estado', 'Importe'],
            'rows' => $sales->map(fn ($sale) => [$sale->fecha->format('d/m/Y'), $sale->numero_factura ?: 'Sin comprobante',
                $sale->cliente->nombre, ucfirst($sale->estado->value), $sale->total])->values()->all(),
            'summary' => ['Operaciones' => $sales->count(), 'Total vendido' => $this->money($total), 'En estado facturada' => $this->money($invoiced)],
            'description' => 'Ventas confirmadas y posteriores de la fecha seleccionada. Se excluyen pendientes y canceladas. El estado facturada no certifica emisión fiscal.'];
    }

    public function dailyCash(string $date): array
    {
        $response = $this->api->request('cash', str_replace('{fecha}', $date, config('services.cash.path')));
        abort_unless($response->successful(), 502, 'Caja no pudo responder.');
        $cash = $response->json();
        abort_unless(is_array($cash) && ! Validator::make($cash, [
            'fecha' => ['required', 'date_format:Y-m-d'], 'ingresos' => ['required', 'numeric', 'min:0'],
            'egresos' => ['required', 'numeric', 'min:0'], 'saldo_final' => ['required', 'numeric'],
            'cantidad_movimientos' => ['required', 'integer', 'min:0'], 'movimientos' => ['present', 'array'],
            'movimientos.*.id_movimiento_caja' => ['required', 'integer', 'distinct'],
            'movimientos.*.fecha' => ['required', 'date_format:Y-m-d'],
            'movimientos.*.sentido' => ['required', 'in:ingreso,egreso'], 'movimientos.*.monto' => ['required', 'numeric', 'min:0'],
            'movimientos.*.tipo' => ['required', 'string'], 'movimientos.*.concepto' => ['required', 'string'],
            'movimientos.*.medio_pago' => ['required', 'string'],
        ])->fails(), 502, 'Caja devolvió datos incompletos.');
        abort_unless($cash['fecha'] === $date && array_is_list($cash['movimientos']) && count($cash['movimientos']) === (int) $cash['cantidad_movimientos'], 502, 'Caja devolvió otro período o un listado incompleto.');
        $moves = collect($cash['movimientos']);
        abort_if($moves->contains(fn ($move) => $move['fecha'] !== $date), 502, 'Caja devolvió movimientos de otra fecha.');
        $in = $moves->where('sentido', 'ingreso')->sum(fn ($m) => (int) round($m['monto'] * 100));
        $out = $moves->where('sentido', 'egreso')->sum(fn ($m) => (int) round($m['monto'] * 100));
        abort_unless($in === (int) round($cash['ingresos'] * 100) && $out === (int) round($cash['egresos'] * 100)
            && $in - $out === (int) round($cash['saldo_final'] * 100), 502, 'Los totales de Caja no coinciden con sus movimientos.');

        return ['title' => 'Caja diaria', 'headers' => ['Movimiento', 'Fecha', 'Tipo', 'Concepto', 'Medio', 'Sentido', 'Importe'],
            'rows' => $moves->map(fn ($m) => [$m['id_movimiento_caja'], $m['fecha'], $m['tipo'], $m['concepto'], $m['medio_pago'], $m['sentido'], (float) $m['monto']])->all(),
            'summary' => ['Movimientos' => $moves->count(), 'Ingresos' => $this->money($in), 'Egresos' => $this->money($out), 'Neto del día' => $this->money($in - $out)],
            'description' => 'Movimientos de todas las cajas en la fecha seleccionada. El neto es ingresos menos egresos; no incluye saldos de apertura de otros días.'];
    }

    public function lowStock(string $order): array
    {
        $rows = $this->api->getList('catalog', config('services.catalog.products_path'));
        abort_if(Validator::make($rows, ['*.id_producto' => ['required', 'integer', 'distinct'], '*.codigo' => ['required', 'string'],
            '*.estado' => ['required', 'in:activo,inactivo'], '*.stock_disponible' => ['required', 'integer', 'min:0'],
            '*.stock_minimo' => ['required', 'integer', 'min:0']])->fails(), 502, 'Productos no informó disponibilidad y mínimos válidos.');
        $products = collect($rows)->filter(fn ($p) => $p['estado'] === 'activo' && $p['stock_disponible'] <= $p['stock_minimo'])
            ->sort(fn ($a, $b) => (($order === 'desc' ? -1 : 1) * ($a['stock_disponible'] <=> $b['stock_disponible'])) ?: ($a['id_producto'] <=> $b['id_producto']));

        return ['title' => 'Stock bajo', 'headers' => ['Código', 'Producto', 'Disponible', 'Mínimo', 'Faltante para mínimo', 'Disponibilidad'],
            'rows' => $products->map(fn ($p) => [$p['codigo'], $p['nombre'] ?? $p['descripcion'] ?? 'Sin nombre', (int) $p['stock_disponible'], (int) $p['stock_minimo'],
                max(0, $p['stock_minimo'] - $p['stock_disponible']), (int) $p['stock_disponible'] === 0 ? 'Agotado' : 'Stock bajo'])->values()->all(),
            'summary' => ['Productos con alerta' => $products->count(), 'Agotados' => $products->where('stock_disponible', 0)->count()],
            'description' => 'Productos activos con disponibilidad menor o igual al mínimo informado. No se interpretan datos faltantes como stock cero.'];
    }

    public function dueInvoices(string $date, int $days): array
    {
        $invoices = collect($this->finance->pendingInvoices());
        $names = $this->clientNames($invoices->pluck('cliente_id'));
        $end = CarbonImmutable::parse($date)->addDays($days)->toDateString();
        $selected = $invoices->filter(fn ($row) => $row['vencimiento'] === null || substr($row['vencimiento'], 0, 10) <= $end)
            ->sortBy(fn ($row) => $row['vencimiento'] ?? '9999-12-31');
        $label = fn ($row) => $row['vencimiento'] === null ? 'Sin vencimiento informado' : (substr($row['vencimiento'], 0, 10) < $date ? 'Vencida' : 'Próximo vencimiento');

        return ['title' => 'Vencimientos', 'headers' => ['Cliente', 'Comprobante interno', 'Vencimiento', 'Importe pendiente', 'Situación'],
            'rows' => $selected->map(fn ($row) => [$names[$row['cliente_id']] ?? 'Cliente #'.$row['cliente_id'], $row['numero'],
                $row['vencimiento'] ?? 'Sin informar', (float) $row['importe_pendiente'], $label($row)])->values()->all(),
            'summary' => ['Próximos '.$days.' días' => $selected->filter(fn ($row) => $label($row) === 'Próximo vencimiento')->count(),
                'Vencidas' => $selected->filter(fn ($row) => $label($row) === 'Vencida')->count(), 'Sin fecha informada' => $invoices->whereNull('vencimiento')->count()],
            'description' => "Importes pendientes actuales. Ventana inclusiva desde $date hasta $end; también se muestran vencidas y comprobantes sin fecha. No reconstruye saldos históricos ni inventa vencimientos."];
    }

    private function debtors(string $order): array
    {
        $balances = collect($this->finance->debtorBalances())->sortBy('saldo', SORT_NUMERIC, $order !== 'menor');
        $names = $this->clientNames($balances->pluck('cliente_id'));
        $balances = $balances->filter(fn ($row) => isset($names[$row['cliente_id']]));

        return ['title' => 'Clientes deudores', 'headers' => ['Cliente', 'Deuda'], 'rows' => $balances->map(fn ($row) => [$names[$row['cliente_id']] ?? 'Cliente #'.$row['cliente_id'], (float) $row['saldo']])->values()->all()];
    }

    private function ranking(array $filters): array
    {
        return ['title' => 'Ranking comercial', 'headers' => ['Producto', 'Unidades', 'Importe neto'],
            'rows' => collect($this->finance->productRanking($filters['desde'] ?? null, $filters['hasta'] ?? null))
                ->sortByDesc('cantidad_vendida')->map(fn ($row) => [$row['producto_nombre'], (int) $row['cantidad_vendida'], (float) $row['monto_facturado']])->values()->all()];
    }

    private function buyers(array $filters): array
    {
        abort_unless(isset($filters['producto_id']), 422, 'Seleccione un producto.');
        $id = (int) $filters['producto_id'];
        if ($this->catalog->isConfigured()) {
            abort_unless($this->catalog->products()->contains('id', $id), 404);
            $ids = $this->reports->productBuyerIds($id);
        } else {
            $reach = $this->reports->salesProductReach($id);
            abort_unless($reach['products']->contains('id', $id), 404);
            $ids = $reach['buyer_ids'];
        }
        $clients = Cliente::query()->whereIn('id', $ids)->orderBy('apellido_razon_social')->orderBy('nombre')->get();
        $total = Cliente::query()->count();

        return ['title' => 'Clientes por producto', 'headers' => ['Cliente', 'Documento', 'Email'],
            'summary' => ['Compradores' => $clients->count(), 'Base de clientes' => $total,
                'Cobertura del producto' => number_format($total ? $clients->count() * 100 / $total : 0, 2, ',', '.').'%'],
            'rows' => $clients->map(fn ($client) => [$client->apellido_razon_social.', '.$client->nombre, $client->dni_cuit, $client->email])->all()];
    }

    private function clientNames(Collection $ids): array
    {
        return Cliente::query()->whereIn('id', $ids)->get()->mapWithKeys(fn ($client) => [$client->id => $client->apellido_razon_social.', '.$client->nombre])->all();
    }

    private function money(int $cents): string
    {
        return '$ '.number_format($cents / 100, 2, ',', '.');
    }
}
