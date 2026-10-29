<?php

namespace Tests\Feature\Ventas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaModuloVentas;
use Tests\TestCase;

/**
 * Tests de las CONSULTAS de la API de ventas:
 *   GET /api/ventas            (lista)
 *   GET /api/ventas/estados    (catálogo de estados)
 *   GET /api/ventas/{id}       (una venta)
 */
class ConsultarVentasApiTest extends TestCase
{
    use PreparaModuloVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararModuloVentas();
    }

    // ------------------------------------------------------------------
    // GET /api/ventas
    // ------------------------------------------------------------------

    public function test_lista_devuelve_json_y_no_la_pantalla_html(): void
    {
        // Antes esta ruta devolvía la vista del POS (HTML) por error.
        $this->getJson('/api/ventas')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_lista_la_mas_nueva_primero_con_detalle_y_botones(): void
    {
        $vieja = $this->crearVenta('confirmada');
        $nueva = $this->crearVenta('pagada', [[2, 1, 1800, 0], [3, 2, 4200, 0]]);

        $this->getJson('/api/ventas')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $nueva)
            ->assertJsonPath('data.1.id', $vieja)
            ->assertJsonCount(2, 'data.0.detalles')
            ->assertJsonPath('data.0.cantidad_articulos', 3)
            ->assertJsonPath('data.0.acciones.puede_modificar', false)  // pagada
            ->assertJsonPath('data.1.acciones.puede_modificar', true)   // confirmada
            ->assertJsonMissingPath('data.0.historial');                // el listado no trae historial
    }

    public function test_filtro_por_estado(): void
    {
        $this->crearVenta('confirmada');
        $pagada = $this->crearVenta('pagada');

        $this->getJson('/api/ventas?estado=pagada')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pagada);
    }

    public function test_filtro_por_cliente(): void
    {
        $this->crearVenta();
        $otra = $this->crearVenta('confirmada', [[1, 1, 2500, 0]], self::OTRO_CLIENTE);

        $this->getJson('/api/ventas?cliente_id='.self::OTRO_CLIENTE)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $otra)
            ->assertJsonPath('data.0.cliente.nombre', 'Almacén Don Pedro');
    }

    public function test_filtro_por_fechas(): void
    {
        $id = $this->crearVenta();
        DB::table('venta')->where('id_venta', $id)->update(['fecha' => '2026-09-01']);
        $this->crearVenta(); // fecha 2026-10-13

        $this->getJson('/api/ventas?desde=2026-08-01&hasta=2026-09-30')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
    }

    public function test_filtro_por_texto_busca_en_comprobante_y_observaciones(): void
    {
        $id = $this->crearVenta();
        DB::table('venta')->where('id_venta', $id)->update(['observaciones' => 'Entregar el viernes']);
        $this->crearVenta();

        $this->getJson('/api/ventas?texto=viernes')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
    }

    public function test_paginacion(): void
    {
        foreach (range(1, 5) as $i) {
            $this->crearVenta();
        }

        $this->getJson('/api/ventas?por_pagina=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.current_page', 3);
    }

    public function test_filtros_invalidos_devuelven_422(): void
    {
        $this->getJson('/api/ventas?estado=inventado')->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->getJson('/api/ventas?desde=13/10/2026')->assertUnprocessable()->assertJsonValidationErrors('desde');
        $this->getJson('/api/ventas?desde=2026-10-10&hasta=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $this->getJson('/api/ventas?por_pagina=1000')->assertUnprocessable()->assertJsonValidationErrors('por_pagina');
    }

    // ------------------------------------------------------------------
    // GET /api/ventas/estados
    // ------------------------------------------------------------------

    public function test_catalogo_de_estados(): void
    {
        $this->getJson('/api/ventas/estados')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.0.valor', 'pendiente')
            ->assertJsonPath('data.4.valor', 'pagada')
            ->assertJsonPath('data.4.permite_modificar', false)
            ->assertJsonPath('data.6.permite_restaurar', true);
    }

    // ------------------------------------------------------------------
    // GET /api/ventas/productos
    // ------------------------------------------------------------------

    public function test_catalogo_de_productos_para_el_editor(): void
    {
        $this->getJson('/api/ventas/productos')
            ->assertOk()
            ->assertJsonCount(count(app(\App\Services\MockProductoApi::class)->todos()), 'data')
            ->assertJsonPath('data.0', [
                'id' => 1, 'codigo' => 'P001', 'nombre' => 'Coca-Cola 1.5L',
                'precioMin' => 2500, 'precioMay' => 2200, 'stock' => 48,
            ]);
    }

    // ------------------------------------------------------------------
    // GET /api/ventas/{id}
    // ------------------------------------------------------------------

    public function test_ver_una_venta_completa(): void
    {
        $id = $this->crearVenta('preparada', [[1, 2, 2500, 100], [6, 3, 950, 0]]);

        $this->getJson("/api/ventas/{$id}")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'numero_comprobante', 'fecha', 'cliente' => ['id', 'nombre', 'cuit'], 'usuario' => ['id', 'nombre'],
                'estado' => ['valor', 'etiqueta'], 'anulada', 'total', 'descuento_global', 'observaciones',
                'cantidad_articulos', 'detalles' => [['id_producto', 'codigo', 'producto', 'cantidad', 'precio_unitario', 'descuento', 'subtotal']],
                'acciones' => ['puede_modificar', 'puede_eliminar', 'puede_restaurar'], 'transiciones_posibles', 'historial',
            ]])
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.cliente.nombre', 'María González')
            ->assertJsonPath('data.usuario.nombre', 'Administrador')
            ->assertJsonPath('data.estado.valor', 'preparada')
            ->assertJsonPath('data.total', 7650)
            ->assertJsonPath('data.cantidad_articulos', 5)
            ->assertJsonPath('data.detalles.0.producto', 'Coca-Cola 1.5L')
            ->assertJsonPath('data.detalles.1.codigo', 'P006')
            ->assertJsonPath('data.transiciones_posibles', ['entregada', 'pagada'])
            ->assertJsonPath('data.historial', []);
    }

    public function test_venta_inexistente_devuelve_404_con_codigo(): void
    {
        $this->getJson('/api/ventas/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'La venta #999 no existe.', 'codigo' => 'VENTA_NO_ENCONTRADA', 'venta_id' => 999]);
    }

    public function test_id_no_numerico_no_matchea_ninguna_ruta(): void
    {
        $this->getJson('/api/ventas/abc')->assertNotFound();
    }

    public function test_productos_simulados_sigue_funcionando(): void
    {
        // Lo usa resources/js/promociones.js: no se puede romper.
        $this->getJson('/api/ventas/productos-simulados')
            ->assertOk()
            ->assertJsonPath('0.nombre', 'Coca-Cola 1.5L');
    }

    public function test_venta_con_estado_desconocido_no_rompe_y_no_habilita_botones(): void
    {
        $id = $this->crearVenta('raro');

        $this->getJson("/api/ventas/{$id}")
            ->assertOk()
            ->assertJsonPath('data.estado.etiqueta', 'Desconocido')
            ->assertJsonPath('data.acciones', ['puede_modificar' => false, 'puede_eliminar' => false, 'puede_restaurar' => false])
            ->assertJsonPath('data.transiciones_posibles', []);
    }
}
