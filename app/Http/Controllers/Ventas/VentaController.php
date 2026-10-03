<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller; // Clase base común de los controladores Laravel.
use App\Services\Ventas\CarritoService; // Obtiene el estado inicial del carrito de sesión.
use App\Services\Ventas\VentaService; // Ejecuta la lógica y transacción de cobro.
use App\Support\UsuarioActual; // Obtiene el operador desde sesión o configuración demo.
use Illuminate\Http\JsonResponse; // Tipo de respuesta JSON del endpoint de cobro.
use Illuminate\Http\Request; // Petición HTTP y body enviado por el frontend.
use Illuminate\Validation\Rule; // Permite validar medios de pago contra una lista permitida.

/**
 * VentaController  —  Punto de entrada de la pantalla "Punto de Venta".
 *
 * ¿QUÉ ES UN CONTROLLER?
 *   Es la capa que RECIBE la petición HTTP del navegador, valida los datos
 *   que llegan, llama al Service que tiene la lógica y devuelve la respuesta.
 *   Los controllers de este módulo son "finos": NO contienen reglas de negocio
 *   (esas viven en app/Services/Ventas).
 *
 * ENDPOINTS DE ESTE CONTROLLER (definidos en routes/ventas.php)
 *   GET  /ventas          -> index()   Devuelve la VISTA resources/views/ventas.blade.php
 *   POST /ventas/cobrar   -> cobrar()  Botón "COBRAR"
 *
 * DEPENDE DE : CarritoService, VentaService, UsuarioActual.
 *
 * IMPORTANCIA:
 *   Es la entrada HTTP para abrir la pantalla y confirmar una venta. Mantiene
 *   el controlador delgado: valida la petición y delega las reglas de negocio
 *   y la transacción a VentaService.
 *
 * GUÍA PARA EL FRONT:
 *   - GET /ventas renderiza la vista con $carrito y $config.
 *   - POST /ventas/cobrar recibe pagos y devuelve el comprobante lógico de venta.
 *   - Enviar Accept: application/json, Content-Type: application/json y el
 *     token X-CSRF-TOKEN para las peticiones de escritura.
 *   - Después de cobrar, pedir GET /ventas/carrito para mostrar el carrito vacío:
 *     la respuesta de cobro no incluye el nuevo estado del carrito.
 *
 * La vista resources/views/ventas.blade.php actualmente es un placeholder;
 * el frontend debe consumir las variables que index() le entrega.
 */
class VentaController extends Controller
{
    public function __construct(
        private CarritoService $carrito,
        private VentaService $ventas
    ) {
        // Laravel resuelve ambos servicios por inyección de dependencias.
        // Este controlador no los instancia ni contiene sus reglas de negocio.
    }

    /**
     * GET /ventas  (ruta con nombre 'ventas': la usa home.blade.php con route('ventas'))
     *
     * Muestra la pantalla. A la vista (ventas.blade.php) se le pasan:
     *   $carrito -> estado completo del carrito (mismo formato que GET /ventas/carrito).
     *               Sirve para pintar la pantalla ya con datos al cargar/recargar.
     *   $config  -> { medios_pago: [...], descuento_manual_maximo: 100 }
     *               Para armar el selector de medio de pago y validar el tope de descuento en el front.
     */
    public function index()
    {
        // Renderiza resources/views/ventas.blade.php.
        return view('ventas', [
            // Estado inicial calculado por CarritoService; la vista puede usarlo
            // para dibujar la pantalla sin hacer primero otra petición GET.
            'carrito' => $this->carrito->estado(),

            // Configuración que necesita la interfaz para formar controles.
            'config'  => [
                // Valores permitidos en el selector de medios de pago.
                'medios_pago'             => config('ventas.medios_pago'),
                // Tope mostrado en la interfaz; el backend vuelve a validar reglas.
                'descuento_manual_maximo' => config('ventas.descuento_manual_maximo'),
            ],
        ]);
        // La vista recibe estas variables como $carrito y $config.
    }

    /**
     * POST /ventas/cobrar     —  Botón "COBRAR"
     *
     * BODY (JSON):
     * {
     *   "pagos": [                              // opcional. Vacío/ausente = todo a cuenta corriente
     *       { "medio_pago": "efectivo",      "monto": 5000 },
     *       { "medio_pago": "tarjeta",       "monto": 3000.50 }
     *   ],
     *   "observaciones": "Entrega por la tarde"  // opcional, máx 255
     * }
     *
     * RESPUESTA OK (200):
     * { "ok": true, "venta": { id_venta, fecha, estado, total, pagado, vuelto, pendiente, comprobantes[] } }
     *      -> 'vuelto' es lo que se le devuelve al cliente en mano.
     *      -> 'pendiente' > 0 significa que quedó deuda en la cuenta del cliente.
     *
     * RESPUESTA ERROR (422): { "ok": false, "mensaje": "...", "errores": {...} }
     *      (carrito vacío, stock insuficiente, Consumidor Final sin pagar completo, etc.)
     *
     * FRONT: al recibir éxito, mostrar el resultado de "venta" y consultar GET
     * /ventas/carrito para redibujar el carrito vacío. Ante errores, mostrar
     * mensaje o errores; la validación HTTP puede usar el formato Laravel
     * { message, errors }, distinto del formato de VentaException.
     */
    public function cobrar(Request $request): JsonResponse
    {
        // Valida la forma del body antes de invocar la lógica de cobro.
        $datos = $request->validate([
            // Ausente o null equivale a no enviar pagos; VentaService decide
            // si eso se permite según Consumidor Final o cliente registrado.
            'pagos'              => ['nullable', 'array'],
            // Cada elemento requiere un medio permitido y un importe positivo.
            'pagos.*.medio_pago' => ['required', Rule::in(config('ventas.medios_pago'))],
            'pagos.*.monto'      => ['required', 'numeric', 'gt:0'],
            // Texto opcional para guardar observaciones asociadas a la venta.
            'observaciones'      => ['nullable', 'string', 'max:255'],
        ]);

        // Delegación de la operación. El usuario nunca se toma del body del front;
        // UsuarioActual lo resuelve en el servidor para registrar al operador.
        $venta = $this->ventas->cobrar(
            $datos['pagos'] ?? [],
            $datos['observaciones'] ?? null,
            UsuarioActual::id()
        );

        // El servicio retorna id, total, estado, pagado, vuelto, pendiente y comprobantes.
        return response()->json(['ok' => true, 'venta' => $venta]);
    }
}
