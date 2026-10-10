<?php

namespace App\Modules\Venta\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VentaController;
use App\Modules\Venta\Logging\VentaLogger;
use App\Modules\Venta\Models\Venta;
use App\Services\MockClienteApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * PASO 3 - CONTROLADOR (EL CEREBRO) DEL MÓDULO VENTAS
 * Recibe las peticiones del front, valida, guarda con los modelos
 * y siempre responde JSON con un código HTTP.
 */
class VentasController extends Controller
{
    /**
     * GET /api/ventas
     * Lista todas las ventas (la más nueva primero) con sus productos.
     */
    public function index(): JsonResponse
    {
        // with('detalles') trae los detalles en la misma consulta (evita N+1 consultas).
        $ventas = Venta::with('detalles')->orderByDesc('id_venta')->get();

        // 200 = OK
        return response()->json(['data' => $ventas], 200);
    }

    /**
     * GET /api/ventas/{id}
     * Devuelve una sola venta con sus detalles.
     */
    public function show(int $id): JsonResponse
    {
        // find() busca por clave primaria (id_venta). Si no existe devuelve null.
        $venta = Venta::with('detalles')->find($id);

        if ($venta === null) {
            // 404 = no encontrado
            return response()->json(['mensaje' => 'La venta no existe.'], 404);
        }

        return response()->json(['data' => $venta], 200);
    }

    /**
     * POST /api/ventas
     * Registra una venta, sus detalles y el movimiento en la cuenta corriente.
     *
     * JSON que manda el front (resources/views/ventas.blade.php):
     * {
     *   cliente_id: 472891,
     *   lista: "minorista" | "mayorista",
     *   observaciones: "texto opcional",
     *   descuento_global: { modo: "porcentaje" | "monto", valor: 10 },
     *   items: [ { id: 1, cantidad: 2, descuento: 100 } ]
     * }
     *
     * Cada venta deja un informe paso a paso en storage/logs/ventas/ (ver VentaLogger).
     */
    public function store(Request $request, MockClienteApi $clientes): JsonResponse
    {
        // Abre el informe de esta venta en el log (anota endpoint y datos recibidos).
        $log = new VentaLogger($request);

        // Por ahora productos y clientes son simulados (todavía no hay tablas cargadas).
        // Cuando existan, se reemplaza por Producto::find(...) y Cliente::find(...).
        $productos = collect(VentaController::productosSimulados())->keyBy('id');

        // ==========================================================
        // 1. VALIDAR: nunca confiar en lo que llega de internet.
        // Si algo falla se lanza ValidationException: Laravel responde 422 con
        // los errores en "errors" (el front ya sabe leer ese formato).
        // El catch de abajo solo lo anota en el log y la vuelve a lanzar.
        // ==========================================================
        try {
            $log->paso(
                'Validación del formato de los datos',
                'El sistema comprueba que lleguen todos los campos obligatorios y con el tipo correcto.'
            );
            $datos = $request->validate([
                'cliente_id' => ['required', 'integer'],                                  // obligatorio y número
                'lista' => ['required', Rule::in(['minorista', 'mayorista'])],            // solo esos 2 valores
                'observaciones' => ['nullable', 'string', 'max:150'],                     // opcional, hasta 150 caracteres
                'items' => ['required', 'array', 'min:1', 'max:100'],                     // al menos 1 producto
                'items.*.id' => ['required', 'integer'],                                  // id del producto (su existencia se valida en el paso 3)
                'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
                'items.*.descuento' => ['required', 'numeric', 'min:0'],                  // descuento en $ por unidad
                'descuento_global' => ['sometimes', 'array'],                             // opcional
                'descuento_global.modo' => ['required_with:descuento_global', Rule::in(['porcentaje', 'monto'])],
                'descuento_global.valor' => ['required_with:descuento_global', 'numeric', 'min:0'],
            ]);
            $log->ok('Datos completos y con formato correcto (lista de precios: '.$datos['lista'].', '.count($datos['items']).' producto/s).');

            // Validación de negocio: el cliente tiene que existir.
            $log->paso(
                'Validación del cliente',
                'El sistema comprueba que el cliente exista (fuente: App\Services\MockClienteApi, simulado).'
            );
            $cliente = $clientes->buscar((int) $datos['cliente_id']);
            if ($cliente === null) {
                throw ValidationException::withMessages([
                    'cliente_id' => ["Seleccioná un cliente válido. No existe el cliente #{$datos['cliente_id']}."],
                ]);
            }
            $log->ok("Cliente encontrado: #{$cliente['id']} - {$cliente['nombre']} (CUIT {$cliente['cuit']}).");

            // Qué columna de precio usar según la lista elegida.
            $columnaPrecio = $datos['lista'] === 'mayorista' ? 'precioMay' : 'precioMin';

            // Calcula cada línea del carrito con el precio del SERVIDOR
            // (nunca usamos el precio que manda el front, se podría manipular).
            $log->paso(
                'Validación de los productos',
                'El sistema comprueba que cada producto exista y que su descuento no supere el precio unitario '
                .'(fuente: VentaController::productosSimulados, simulado).'
            );
            $detalles = [];
            foreach ($datos['items'] as $indice => $item) {
                $producto = $productos->get((int) $item['id']);

                // El producto tiene que existir en el catálogo.
                if ($producto === null) {
                    throw ValidationException::withMessages([
                        "items.{$indice}.id" => ["El producto #{$item['id']} no existe en el catálogo."],
                    ]);
                }

                $precio = (float) $producto[$columnaPrecio];
                $descuento = round((float) $item['descuento'], 2);

                // El descuento por unidad no puede ser mayor al precio.
                if ($descuento > $precio) {
                    throw ValidationException::withMessages([
                        "items.{$indice}.descuento" => ["El descuento no puede superar el precio unitario actual del producto \"{$producto['nombre']}\"."],
                    ]);
                }

                // Arma la fila tal cual se va a guardar en detalle_venta.
                $subtotalLinea = round((int) $item['cantidad'] * ($precio - $descuento), 2);
                $detalles[] = [
                    'id_producto' => $producto['id'],
                    'id_promocion' => null,
                    'cantidad' => (int) $item['cantidad'],
                    'precio_unitario' => $precio,
                    'descuento' => $descuento,
                    'subtotal' => $subtotalLinea,
                ];

                $log->ok(sprintf(
                    'Producto #%d "%s" x%d · precio %s · desc. %s c/u · subtotal %s',
                    $producto['id'],
                    $producto['nombre'],
                    (int) $item['cantidad'],
                    VentaLogger::pesos($precio),
                    VentaLogger::pesos($descuento),
                    VentaLogger::pesos($subtotalLinea)
                ));
            }

            // Suma de todas las líneas (ya con su descuento individual).
            $log->paso(
                'Cálculo del total',
                'El sistema suma los subtotales, aplica el descuento global y comprueba que el total sea mayor a cero.'
            );
            $subtotal = round(array_sum(array_column($detalles, 'subtotal')), 2);

            // Descuento global: si no vino, es 0.
            $descuentoGlobal = $datos['descuento_global'] ?? ['modo' => 'monto', 'valor' => 0];
            $valorDescuento = (float) $descuentoGlobal['valor'];

            if ($descuentoGlobal['modo'] === 'porcentaje' && $valorDescuento > 100) {
                throw ValidationException::withMessages([
                    'descuento_global.valor' => ['El porcentaje de descuento global debe estar entre 0 y 100.'],
                ]);
            }

            // Pasa el descuento global a pesos ($). Si es monto, no puede superar el subtotal.
            $montoDescuentoGlobal = $descuentoGlobal['modo'] === 'porcentaje'
                ? round($subtotal * $valorDescuento / 100, 2)
                : round(min($valorDescuento, $subtotal), 2);

            $total = round($subtotal - $montoDescuentoGlobal, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages(['items' => ['La venta debe tener un total mayor a cero.']]);
            }

            $log->detalle('Subtotal ..........: '.VentaLogger::pesos($subtotal));
            $log->detalle('Descuento global ..: '.VentaLogger::pesos($montoDescuentoGlobal)
                .($descuentoGlobal['modo'] === 'porcentaje' ? " ({$valorDescuento}%)" : ' (monto fijo)'));
            $log->ok('TOTAL A COBRAR ....: '.VentaLogger::pesos($total));
        } catch (ValidationException $e) {
            // Venta RECHAZADA: el log anota en qué paso falló, por qué y qué se respondió.
            $log->rechazada($e);

            throw $e; // Laravel la convierte en la respuesta 422 para el front.
        }

        // Si no escribieron observaciones, se pone un texto por defecto.
        $observaciones = trim($datos['observaciones'] ?? '') ?: 'Venta POS a cuenta corriente';

        try {
            // ======================================================
            // 2. PROCESAR: guardar usando los modelos.
            // DB::transaction = "todo o nada": si falla cualquier insert,
            // se deshace todo y no quedan ventas a medias en la BD.
            // ======================================================
            $venta = DB::transaction(function () use ($cliente, $detalles, $total, $montoDescuentoGlobal, $observaciones, $log) {
                // Inserta la cabecera en la tabla "venta".
                $log->paso(
                    'Guardar la venta',
                    'El sistema inserta la cabecera de la venta en la tabla "venta" (modelo App\Modules\Venta\Models\Venta).'
                );
                $venta = Venta::create([
                    'id_cliente' => $cliente['id'],
                    'fecha' => now()->toDateString(),                      // fecha de hoy
                    'total' => $total,
                    'descuento_global' => $montoDescuentoGlobal,
                    'numFactura' => 'POS-'.Str::ulid(),                    // número único
                    'estado' => 'confirmada',
                    'observaciones' => $observaciones,
                    'id_usuario' => config('cuenta_corriente.id_usuario_prueba'), // usuario de prueba hasta tener login
                ]);
                $log->ok("Venta guardada con id_venta = {$venta->id_venta} · N° {$venta->numFactura} · estado: {$venta->estado}.");

                // Inserta todas las líneas en "detalle_venta".
                // createMany completa solo el id_venta gracias a la relación detalles().
                $log->paso(
                    'Guardar los productos de la venta',
                    'El sistema inserta una fila por producto en la tabla "detalle_venta" (modelo DetalleVenta).'
                );
                $venta->detalles()->createMany($detalles);
                $log->ok(count($detalles).' fila/s guardada/s en detalle_venta.');

                // Registra la deuda en la cuenta corriente del cliente (importe negativo = debe).
                $log->paso(
                    'Cargar la venta a la cuenta corriente',
                    'El sistema registra la deuda del cliente en la tabla "cuenta_corriente_movimientos".'
                );
                DB::table('cuenta_corriente_movimientos')->insert([
                    'cliente_id' => $cliente['id'],
                    'venta_id' => $venta->id_venta,
                    'tipo' => 'venta',
                    'descripcion' => "Venta POS #{$venta->id_venta}",
                    'importe' => -$total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $log->ok('Movimiento cargado: '.VentaLogger::pesos(-$total)." al cliente #{$cliente['id']}.");

                return $venta;
            });

            // ======================================================
            // 3. RESPUESTA EXITOSA: JSON + 201 (Created).
            // El front usa "venta_id" y "total" para mostrar el aviso.
            // ======================================================
            $respuesta = [
                'mensaje' => 'Venta registrada y cargada a la cuenta corriente.',
                'venta_id' => $venta->id_venta,
                'cliente_id' => $cliente['id'],
                'descuento_global' => $montoDescuentoGlobal,
                'total' => $total,
                'data' => $venta->load('detalles')->toArray(),   // la venta completa con sus detalles
            ];

            // Cierra el informe del log con lo que se devuelve al front.
            $log->exito($respuesta, 201);

            return response()->json($respuesta, 201);
        } catch (\Throwable $e) {
            // ======================================================
            // 4. ERROR CONTROLADO: si la BD rechaza algo (FK, columna, conexión...),
            // no dejamos que la app explote. El log anota en qué paso falló
            // y el error real; al front le devolvemos un JSON limpio.
            // 500 = error del servidor (los datos eran válidos, falló la BD).
            // ======================================================
            $respuesta = [
                'message' => 'Hubo un error al procesar la venta.',          // el front muestra "message"
                // El detalle técnico solo se muestra con APP_DEBUG=true (desarrollo).
                'detalle' => config('app.debug') ? $e->getMessage() : null,
            ];

            $log->error($e, $respuesta);

            return response()->json($respuesta, 500);
        }
    }
}
