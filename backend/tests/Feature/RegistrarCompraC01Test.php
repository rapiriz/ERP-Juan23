<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Modules\Compras\Models\Compra;
use App\Modules\Compras\Models\DetalleCompra;
use App\Modules\Compras\Models\PagoCompra;
use App\Modules\Compras\Models\PagoProveedor;
use App\Modules\Productos\Models\Producto;
use App\Modules\Proveedores\Models\Proveedor;
use App\Modules\Proveedores\Models\ProductoProveedor;
use App\Modules\Stock\Models\MovimientoStock;
use Carbon\Carbon;

/**
 * Test exhaustivo para la Historia de Usuario C01:
 * "Como administrador quiero registrar compras de mercadería para gestionar el abastecimiento de productos."
 */
class RegistrarCompraC01Test extends TestCase
{
    use DatabaseTransactions;

    private Proveedor $proveedorActivo;
    private Proveedor $proveedorInactivo;
    private Producto $producto1;
    private Producto $producto2;

    protected function setUp(): void
    {
        parent::setUp();

        // Proveedor activo
        $this->proveedorActivo = Proveedor::where('estado', 'activo')->first() ?? Proveedor::create([
            'cuit' => '30' . rand(10000000, 99999999) . '1',
            'razon_social' => 'Distribuidora Test SA',
            'estado' => 'activo',
            'condicion_iva' => 'Responsable Inscripto',
            'plazo_entrega_promedio_dias' => 5,
        ]);

        // Proveedor inactivo
        $this->proveedorInactivo = Proveedor::where('estado', 'inactivo')->first() ?? Proveedor::create([
            'cuit' => '30' . rand(10000000, 99999999) . '2',
            'razon_social' => 'Proveedor Inactivo SRL',
            'estado' => 'inactivo',
            'condicion_iva' => 'Responsable Inscripto',
            'plazo_entrega_promedio_dias' => 10,
        ]);

        // Productos activos
        $prods = Producto::where('estado', 'activo')->take(2)->get();
        if ($prods->count() >= 2) {
            $this->producto1 = $prods[0];
            $this->producto2 = $prods[1];
        } else {
            $this->producto1 = Producto::create([
                'codigo' => 'PROD-TEST-' . rand(100, 999),
                'nombre' => 'Producto Test 1',
                'estado' => 'activo',
                'precio_unitario' => 100.0,
            ]);
            $this->producto2 = Producto::create([
                'codigo' => 'PROD-TEST-' . rand(100, 999),
                'nombre' => 'Producto Test 2',
                'estado' => 'activo',
                'precio_unitario' => 200.0,
            ]);
        }
    }

    /**
     * Criterio: Debe permitir seleccionar un proveedor, agregar uno o varios productos,
     * registrar cantidades y precios, calcular automáticamente el importe total,
     * registrar la fecha, generar identificador único y estado inicial 'pendiente'.
     */
    public function test_criterio_registrar_compra_con_multiples_productos_exitosamente(): void
    {
        $cant1 = 5;
        $precio1 = 1500.50;
        $subtotal1 = round($cant1 * $precio1, 2); // 7502.50

        $cant2 = 12;
        $precio2 = 450.25;
        $subtotal2 = round($cant2 * $precio2, 2); // 5403.00

        $totalEsperado = round($subtotal1 + $subtotal2, 2); // 12905.50
        $fechaHoy = Carbon::today()->toDateString();
        $comprobante = 'FC-A-' . rand(1000, 9999);

        $response = $this->postJson('/api/compras', [
            'id_proveedor' => $this->proveedorActivo->id_proveedor,
            'numero_comprobante' => $comprobante,
            'fecha_compra' => $fechaHoy,
            'items' => [
                [
                    'id_producto' => $this->producto1->id_producto,
                    'cantidad' => $cant1,
                    'precio_unitario' => $precio1,
                ],
                [
                    'id_producto' => $this->producto2->id_producto,
                    'cantidad' => $cant2,
                    'precio_unitario' => $precio2,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Compra registrada exitosamente.',
        ]);

        $compraData = $response->json('data');

        // 1. Identificador único generado
        $this->assertNotEmpty($compraData['id_compra']);
        $this->assertNotEmpty($compraData['numero_compra']);

        // 2. Proveedor asociado
        $this->assertEquals($this->proveedorActivo->id_proveedor, $compraData['id_proveedor']);

        // 3. Importe total calculado automáticamente
        $this->assertEquals($totalEsperado, (float) $compraData['importe_total']);
        $this->assertEquals($totalEsperado, (float) $compraData['saldo_pendiente']);

        // 4. Estado inicial registrado como "pendiente"
        $this->assertEquals('pendiente', $compraData['estado']);

        // 5. Fecha de compra registrada
        $this->assertEquals($fechaHoy, $compraData['fecha_compra']);

        // 6. Verificar persistencia en base de datos
        $compraDb = Compra::find($compraData['id_compra']);
        $this->assertNotNull($compraDb);
        $this->assertEquals('pendiente', $compraDb->estado);
        $this->assertEquals($totalEsperado, (float) $compraDb->importe_total);
        $this->assertEquals($comprobante, $compraDb->numero_comprobante);

        // 7. Verificar detalle de ítems persistidos
        $detalles = DetalleCompra::where('id_compra', $compraDb->id_compra)->get();
        $this->assertCount(2, $detalles);

        $det1 = $detalles->firstWhere('id_producto', $this->producto1->id_producto);
        $this->assertNotNull($det1);
        $this->assertEquals($cant1, $det1->cantidad);
        $this->assertEquals($precio1, (float) $det1->precio_unitario);
        $this->assertEquals($subtotal1, (float) $det1->subtotal);
        $this->assertEquals(0, $det1->cantidad_recibida, 'No debe registrar mercadería recibida al crear compra');

        $det2 = $detalles->firstWhere('id_producto', $this->producto2->id_producto);
        $this->assertNotNull($det2);
        $this->assertEquals($cant2, $det2->cantidad);
        $this->assertEquals($precio2, (float) $det2->precio_unitario);
        $this->assertEquals($subtotal2, (float) $det2->subtotal);
    }

    /**
     * Criterio / Alcance: Dependencia PV05 (Asociar Productos a Proveedores).
     * Al registrar una compra, si el producto no estaba asociado al proveedor, debe crearse la asociación.
     */
    public function test_dependencia_pv05_asocia_producto_al_proveedor_al_comprar(): void
    {
        // Asegurar que no exista asociación previa entre producto1 y proveedorActivo
        ProductoProveedor::where('id_producto', $this->producto1->id_producto)
            ->where('id_proveedor', $this->proveedorActivo->id_proveedor)
            ->delete();

        $response = $this->postJson('/api/compras', [
            'id_proveedor' => $this->proveedorActivo->id_proveedor,
            'items' => [
                [
                    'id_producto' => $this->producto1->id_producto,
                    'cantidad' => 3,
                    'precio_unitario' => 850.00,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $asociacion = ProductoProveedor::where('id_producto', $this->producto1->id_producto)
            ->where('id_proveedor', $this->proveedorActivo->id_proveedor)
            ->first();

        $this->assertNotNull($asociacion, 'Debe asociar automáticamente el producto al proveedor según PV05');
        $this->assertTrue((bool) $asociacion->activo);
        $this->assertEquals(850.00, (float) $asociacion->precio_acordado);
    }

    /**
     * Alcance fuera de scope:
     * - NO incluye la recepción de mercadería ni actualización de stock.
     * - NO incluye el registro de pagos.
     */
    public function test_alcance_no_modifica_stock_ni_genera_pagos(): void
    {
        $conteoMovimientosAntes = MovimientoStock::count();
        $conteoPagosCompraAntes = PagoCompra::count();
        $conteoPagosProvAntes = PagoProveedor::count();

        $response = $this->postJson('/api/compras', [
            'id_proveedor' => $this->proveedorActivo->id_proveedor,
            'items' => [
                [
                    'id_producto' => $this->producto1->id_producto,
                    'cantidad' => 20,
                    'precio_unitario' => 500.00,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $this->assertEquals($conteoMovimientosAntes, MovimientoStock::count(), 'El registro de compra NO debe alterar movimientos de stock');
        $this->assertEquals($conteoPagosCompraAntes, PagoCompra::count(), 'El registro de compra NO debe registrar pagos en PagoCompra');
        $this->assertEquals($conteoPagosProvAntes, PagoProveedor::count(), 'El registro de compra NO debe registrar pagos en PagoProveedor');
    }

    /**
     * Validaciones y mensajes de error:
     * - Falla sin proveedor.
     * - Falla con proveedor inactivo.
     * - Falla sin productos en items.
     * - Falla con cantidad <= 0.
     * - Falla con precio negativo.
     * - Falla con fecha futura.
     */
    public function test_validaciones_de_entradas_y_mensajes_de_error(): void
    {
        // 1. Sin proveedor
        $r1 = $this->postJson('/api/compras', [
            'items' => [
                ['id_producto' => $this->producto1->id_producto, 'cantidad' => 1, 'precio_unitario' => 100]
            ]
        ]);
        $r1->assertStatus(400);
        $r1->assertJson(['status' => 'error', 'message' => 'Debe seleccionar un proveedor.']);

        // 2. Proveedor inexistente
        $r2 = $this->postJson('/api/compras', [
            'id_proveedor' => 999999,
            'items' => [
                ['id_producto' => $this->producto1->id_producto, 'cantidad' => 1, 'precio_unitario' => 100]
            ]
        ]);
        $r2->assertStatus(404);
        $r2->assertJson(['status' => 'error', 'message' => 'El proveedor seleccionado no existe.']);

        // 3. Proveedor inactivo
        $r3 = $this->postJson('/api/compras', [
            'id_proveedor' => $this->proveedorInactivo->id_proveedor,
            'items' => [
                ['id_producto' => $this->producto1->id_producto, 'cantidad' => 1, 'precio_unitario' => 100]
            ]
        ]);
        $r3->assertStatus(400);
        $r3->assertJson(['status' => 'error', 'message' => 'No se puede crear una compra con un proveedor inactivo.']);

        // 4. Sin productos (items vacío)
        $r4 = $this->postJson('/api/compras', [
            'id_proveedor' => $this->proveedorActivo->id_proveedor,
            'items' => []
        ]);
        $r4->assertStatus(400);
        $r4->assertJson(['status' => 'error', 'message' => 'Debe agregar al menos un producto a la compra.']);

        // 5. Cantidad menor o igual a cero
        $r5 = $this->postJson('/api/compras', [
            'id_proveedor' => $this->proveedorActivo->id_proveedor,
            'items' => [
                ['id_producto' => $this->producto1->id_producto, 'cantidad' => 0, 'precio_unitario' => 100]
            ]
        ]);
        $r5->assertStatus(400);
        $r5->assertJson(['status' => 'error', 'message' => 'Cada ítem debe indicar una cantidad mayor a cero.']);

        // 6. Precio unitario negativo
        $r6 = $this->postJson('/api/compras', [
            'id_proveedor' => $this->proveedorActivo->id_proveedor,
            'items' => [
                ['id_producto' => $this->producto1->id_producto, 'cantidad' => 2, 'precio_unitario' => -50]
            ]
        ]);
        $r6->assertStatus(400);
        $r6->assertJson(['status' => 'error', 'message' => 'Cada ítem debe indicar un precio unitario válido.']);

        // 7. Fecha futura
        $r7 = $this->postJson('/api/compras', [
            'id_proveedor' => $this->proveedorActivo->id_proveedor,
            'fecha_compra' => Carbon::tomorrow()->toDateString(),
            'items' => [
                ['id_producto' => $this->producto1->id_producto, 'cantidad' => 1, 'precio_unitario' => 100]
            ]
        ]);
        $r7->assertStatus(400);
        $r7->assertJson(['status' => 'error', 'message' => 'La fecha de la compra no puede ser posterior a la fecha actual.']);
    }

    /**
     * Verificación de la vista Blade y controles de interfaz para C01.
     */
    public function test_interfaz_blade_elementos_c01_presentes(): void
    {
        $response = $this->get('/compras');
        $response->assertOk();

        // Botón y modal C01
        $response->assertSee('Nueva Compra (C01)');
        $response->assertSee('modalNuevaCompra');
        $response->assertSee('Registrar Compra de Mercadería (C01)');

        // Campos del formulario
        $response->assertSee('compraProveedor');
        $response->assertSee('compraComprobante');
        $response->assertSee('compraFecha');
        $response->assertSee('compraVencimiento');

        // Campos de items
        $response->assertSee('compraItemProducto');
        $response->assertSee('compraItemCantidad');
        $response->assertSee('compraItemPrecio');
        $response->assertSee('agregarItemCompra()');

        // Tabla de detalle y total
        $response->assertSee('detalleCompraItems');
        $response->assertSee('compraTotalDisplay');
        $response->assertSee('guardarNuevaCompra(event)');
    }
}
