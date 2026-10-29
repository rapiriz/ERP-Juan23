<?php

namespace Tests\Unit\Integraciones;

use App\Modules\Ventas\Exceptions\IntegracionNoDisponibleException;
use App\Modules\Ventas\Integraciones\HttpProductoApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests de la API REAL de productos (app/Modules/Ventas/Integraciones/HttpProductoApi.php).
 * Http::fake() reemplaza internet por respuestas inventadas: no se llama a
 * ningún servidor de verdad.
 */
class HttpProductoApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ventas.servicios.productos.url' => 'http://productos.test']);
    }

    public function test_todos_normaliza_el_formato_del_otro_modulo(): void
    {
        Http::fake(['productos.test/api/productos' => Http::response(['data' => [
            ['id_producto' => 7, 'codigo' => 'X7', 'descripcion' => 'Harina', 'precio_minorista' => 900, 'precio_mayorista' => 800, 'stock' => 3],
        ]])]);

        $this->assertSame([
            ['id' => 7, 'codigo' => 'X7', 'nombre' => 'Harina', 'precioMin' => 900.0, 'precioMay' => 800.0, 'stock' => 3],
        ], (new HttpProductoApi)->todos());
    }

    public function test_si_solo_hay_precio_unitario_se_usa_para_ambas_listas(): void
    {
        Http::fake(['productos.test/api/productos' => Http::response([
            ['id' => 1, 'nombre' => 'Yerba', 'precio_unitario' => 4200],
        ])]);

        $producto = (new HttpProductoApi)->todos()[0];
        $this->assertSame(4200.0, $producto['precioMin']);
        $this->assertSame(4200.0, $producto['precioMay']);
        $this->assertNull($producto['stock']);
    }

    public function test_todos_llama_una_sola_vez_por_pedido(): void
    {
        Http::fake(['productos.test/*' => Http::response([])]);
        $api = new HttpProductoApi;

        $api->todos();
        $api->todos();

        Http::assertSentCount(1);
    }

    public function test_buscar_devuelve_null_si_el_producto_no_existe(): void
    {
        Http::fake(['productos.test/api/productos/99' => Http::response(['message' => 'no'], 404)]);

        $this->assertNull((new HttpProductoApi)->buscar(99));
    }

    public function test_buscar_devuelve_el_producto(): void
    {
        Http::fake(['productos.test/api/productos/1' => Http::response(['id' => 1, 'nombre' => 'Coca', 'precioMin' => 2500, 'precioMay' => 2200])]);

        $this->assertSame('Coca', (new HttpProductoApi)->buscar(1)['nombre']);
    }

    public function test_error_500_del_otro_modulo_se_convierte_en_503(): void
    {
        Http::fake(['productos.test/*' => Http::response('caído', 500)]);

        $this->expectException(IntegracionNoDisponibleException::class);
        (new HttpProductoApi)->todos();
    }

    public function test_error_500_al_buscar_se_convierte_en_503(): void
    {
        Http::fake(['productos.test/*' => Http::response('caído', 500)]);

        $this->expectException(IntegracionNoDisponibleException::class);
        (new HttpProductoApi)->buscar(1);
    }

    public function test_servidor_apagado_se_convierte_en_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        try {
            (new HttpProductoApi)->todos();
            $this->fail('Se esperaba IntegracionNoDisponibleException');
        } catch (IntegracionNoDisponibleException $e) {
            $this->assertSame(503, $e->codigoHttp());
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious()); // se guarda la causa real
        }
    }
}
