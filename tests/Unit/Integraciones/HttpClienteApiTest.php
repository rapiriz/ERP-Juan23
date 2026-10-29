<?php

namespace Tests\Unit\Integraciones;

use App\Modules\Ventas\Exceptions\IntegracionNoDisponibleException;
use App\Modules\Ventas\Integraciones\HttpClienteApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests de la API REAL de clientes (app/Modules/Ventas/Integraciones/HttpClienteApi.php).
 */
class HttpClienteApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ventas.servicios.clientes.url' => 'http://clientes.test']);
    }

    public function test_todos_normaliza_razon_social_e_id_cliente(): void
    {
        Http::fake(['clientes.test/api/clientes' => Http::response(['data' => [
            ['id_cliente' => 10, 'razon_social' => 'Tuvi S.A.', 'cuit' => '27-1'],
        ]])]);

        $this->assertSame([['id' => 10, 'nombre' => 'Tuvi S.A.', 'cuit' => '27-1']], (new HttpClienteApi)->todos());
    }

    public function test_buscar_cliente_existente(): void
    {
        Http::fake(['clientes.test/api/clientes/10' => Http::response(['id' => 10, 'nombre' => 'WoW SRL', 'cuit' => '27-2'])]);

        $this->assertSame('WoW SRL', (new HttpClienteApi)->buscar(10)['nombre']);
    }

    public function test_buscar_cliente_inexistente_devuelve_null(): void
    {
        Http::fake(['clientes.test/*' => Http::response([], 404)]);

        $this->assertNull((new HttpClienteApi)->buscar(1));
    }

    public function test_falla_del_modulo_clientes_es_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(IntegracionNoDisponibleException::class);
        (new HttpClienteApi)->todos();
    }

    public function test_error_500_al_buscar_es_503(): void
    {
        Http::fake(['clientes.test/*' => Http::response('', 500)]);

        $this->expectException(IntegracionNoDisponibleException::class);
        (new HttpClienteApi)->buscar(1);
    }
}
