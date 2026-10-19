<?php

namespace Tests\Unit\Integraciones;

use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Contracts\ProductoGateway;
use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Exceptions\IntegracionNoDisponibleException;
use App\Modules\Ventas\Integraciones\HttpClienteApi;
use App\Modules\Ventas\Integraciones\HttpProductoApi;
use App\Modules\Ventas\Integraciones\HttpStockApi;
use App\Modules\Ventas\Integraciones\MockStockApi;
use App\Services\MockClienteApi;
use App\Services\MockProductoApi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests de la API REAL de stock (app/Modules/Ventas/Integraciones/HttpStockApi.php)
 * y del interruptor mock/http (app/Providers/AppServiceProvider.php).
 */
class HttpStockApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ventas.servicios.stock.url' => 'http://stock.test']);
    }

    public function test_ajustar_envia_los_cambios_con_signo(): void
    {
        Http::fake(['stock.test/*' => Http::response(['ok' => true], 201)]);

        (new HttpStockApi)->ajustar([1 => -2, 5 => 3, 6 => 0], 42, 'modificacion');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === 'http://stock.test/api/stock/movimientos'
            && $r['venta_id'] === 42
            && $r['motivo'] === 'modificacion'
            && $r['origen'] === 'ventas'
            // el producto 6 no cambió (0) y no se envía
            && $r['items'] === [['id_producto' => 1, 'cantidad' => -2], ['id_producto' => 5, 'cantidad' => 3]]);
    }

    public function test_ajustar_sin_cambios_no_llama_a_la_api(): void
    {
        Http::fake();

        (new HttpStockApi)->ajustar([1 => 0], 1, 'modificacion');

        Http::assertNothingSent();
    }

    public function test_ajustar_con_stock_caido_lanza_503(): void
    {
        Http::fake(['stock.test/*' => Http::response('', 500)]);

        $this->expectException(IntegracionNoDisponibleException::class);
        (new HttpStockApi)->ajustar([1 => -1], 1, 'anulacion');
    }

    public function test_disponible(): void
    {
        Http::fake([
            'stock.test/api/stock/1' => Http::response(['disponible' => 48]),
            'stock.test/api/stock/2' => Http::response([], 404),
        ]);

        $this->assertSame(48, (new HttpStockApi)->disponible(1));
        $this->assertNull((new HttpStockApi)->disponible(2));
    }

    public function test_disponible_con_error_lanza_503(): void
    {
        Http::fake(['stock.test/*' => Http::response('', 500)]);

        $this->expectException(IntegracionNoDisponibleException::class);
        (new HttpStockApi)->disponible(1);
    }

    public function test_mock_de_stock_anota_los_avisos_y_lee_el_stock_del_mock_de_productos(): void
    {
        $mock = new MockStockApi(new MockProductoApi);
        $mock->ajustar([1 => -2], 9, 'anulacion');

        $this->assertSame([['venta_id' => 9, 'motivo' => 'anulacion', 'cambios' => [1 => -2]]], $mock->avisos());
        $this->assertSame(48, $mock->disponible(1));
        $this->assertNull($mock->disponible(999));
    }

    public function test_por_defecto_se_usan_los_mocks(): void
    {
        $this->assertInstanceOf(MockProductoApi::class, app(ProductoGateway::class));
        $this->assertInstanceOf(MockClienteApi::class, app(ClienteGateway::class));
        $this->assertInstanceOf(MockStockApi::class, app(StockGateway::class));
    }

    public function test_con_integracion_http_se_usan_las_apis_reales(): void
    {
        config(['ventas.integracion' => 'http']);
        app()->forgetScopedInstances();

        $this->assertInstanceOf(HttpProductoApi::class, app(ProductoGateway::class));
        $this->assertInstanceOf(HttpClienteApi::class, app(ClienteGateway::class));
        $this->assertInstanceOf(HttpStockApi::class, app(StockGateway::class));
    }
}
