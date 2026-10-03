<?php
/*
Función
 CarritoController.php recibe las acciones del usuario sobre el carrito: agregar/quitar productos,
 cambiar cantidades, cliente, lista de precios, descuentos y promociones. No calcula precios ni guarda
 la lógica de negocio; valida lo que llega, llama a CarritoService y devuelve el carrito actualizado.

El flujo es:

Frontend → ruta en ventas.php → método del controlador → CarritoService → respuesta JSON
*/


//Imports y conexión con laravel
namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\CarritoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CarritoController  —  Todos los botones/campos que modifican el carrito.
 *
 * REGLA ÚNICA PARA EL FRONT:
 *   TODOS los endpoints de este controller responden lo mismo:
 *       { "ok": true, "carrito": { ...estado completo... } }
 *   El formato de "carrito" está documentado en CarritoService::estado().
 *   => Después de cada acción, el front solo tiene que REDIBUJAR la pantalla
 *      con ese objeto. No hay que calcular precios ni totales en JavaScript.
 *
 * EN CASO DE ERROR (stock, cliente inactivo, etc.): HTTP 422
 *       { "ok": false, "mensaje": "...", "errores": {} }
 *   (el front debería mostrar "mensaje" al usuario).
 *
 * IMPORTANTE PARA EL FRONT: enviar siempre las cabeceras
 *       Accept: application/json
 *       X-CSRF-TOKEN: <token de <meta name="csrf-token">>
 *       Content-Type: application/json
 *
 * Mapa Pantalla -> Endpoint:
 *   Carga/recarga de la pantalla ............ GET    /ventas/carrito
 *   Elegir producto del buscador ............ POST   /ventas/carrito/productos
 *   Editar columna CANTIDAD ................. PATCH  /ventas/carrito/productos/{id}
 *   Quitar una fila ......................... DELETE /ventas/carrito/productos/{id}
 *   Botón DESCUENTOS (descuento a una fila) . PATCH  /ventas/carrito/productos/{id}/descuento
 *   Botón DESCUENTOS (agregar promo/combo) .. POST   /ventas/carrito/promociones/{id}
 *   Botones Lista 1 / Lista 2 ............... PUT    /ventas/carrito/lista
 *   Elegir cliente / Consumidor Final ....... PUT    /ventas/carrito/cliente
 *   Botón LIMPIAR ........................... DELETE /ventas/carrito
 *
 * DEPENDE DE : CarritoService.
 */
class CarritoController extends Controller
{
    //Request permite leer y validar lo enviado por el frontend.
    // JsonResponse declara que los métodos devuelven respuestas JSON.
    public function __construct(private CarritoService $carrito)
    {
    }

    /*
    Métodos y rutas
    Cada ruta de abajo está declarada en ventas.php. Los métodos mostrar, agregarProducto, cambiarCantidad,
    quitarProducto, descuentoManual, agregarPromocion, cambiarLista, seleccionarCliente y limpiar terminan
    llamando a respuesta(), que devuelve el carrito completo.
    */

    //Pide el estado actual a CarritoService y lo devuelve. No cambia el carrito.
    /** GET /ventas/carrito — Devuelve el estado actual sin modificar nada. */
    public function mostrar(): JsonResponse
    {
        return $this->respuesta();
    }

    /**
     * POST /ventas/carrito/productos
     * BODY: { "id_producto": 8, "cantidad": 1 }     (cantidad opcional, por defecto 1)
     * Si el producto ya estaba en el carrito, SUMA la cantidad.
     */

    //Valida el producto y la cantidad; el servicio suma esa cantidad a la
    //línea existente o crea una nueva. Si se omite cantidad, usa 1.
    public function agregarProducto(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_producto' => ['required', 'integer', 'exists:producto,id_producto'],
            'cantidad'    => ['nullable', 'integer', 'min:1'],
        ]);

        $this->carrito->agregarProducto($datos['id_producto'], $datos['cantidad'] ?? 1);

        return $this->respuesta();
    }

    /**
     * PATCH /ventas/carrito/productos/{idProducto}
     * BODY: { "cantidad": 12 }      -> REEMPLAZA la cantidad (no suma). Mínimo 1.
     */
    //Reemplaza la cantidad de esa línea, no la suma. Exige un entero mínimo de 1.
    public function cambiarCantidad(Request $request, int $idProducto): JsonResponse
    {
        $datos = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);

        $this->carrito->cambiarCantidad($idProducto, $datos['cantidad']);

        return $this->respuesta();
    }

    /** DELETE /ventas/carrito/productos/{idProducto} — Saca la fila completa. */
    //Saca la fila completa del carrito.
    public function quitarProducto(int $idProducto): JsonResponse
    {
        $this->carrito->quitarProducto($idProducto);

        return $this->respuesta();
    }

    /**
     * PATCH /ventas/carrito/productos/{idProducto}/descuento    (botón DESCUENTOS)
     * BODY: { "porcentaje": 15 }
     *   - número 0–tope : descuento manual en % (pisa la promo automática)
     *   - 0             : "sin descuento" (anula incluso la promo automática)
     *   - null          : quita el manual y vuelve a la promo automática (si hay)
     */
    //Aplica el porcentaje manual indicado a esa línea.
    public function descuentoManual(Request $request, int $idProducto): JsonResponse
    {
        $datos = $request->validate([
            'porcentaje' => ['present', 'nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $porcentaje = $datos['porcentaje'] === null ? null : (float) $datos['porcentaje'];
        $this->carrito->descuentoManual($idProducto, $porcentaje);

        return $this->respuesta();
    }

    /**
     * POST /ventas/carrito/promociones/{idPromocion}
     * Agrega 1 unidad de cada producto de la promo (combo). Todo o nada.
     */
    //Delega al servicio la incorporación de la promoción.
    //El servicio añade una unidad de cada producto, si puede hacerlo para todos.
    public function agregarPromocion(int $idPromocion): JsonResponse
    {
        $this->carrito->agregarPromocion($idPromocion);

        return $this->respuesta();
    }

    /**
     * PUT /ventas/carrito/lista
     * BODY: { "lista": "minorista" }   o   { "lista": "mayorista" }
     * Todos los precios del carrito se recalculan.
     */
    //Recalcula todos los precios del carrito según la lista seleccionada.
    public function cambiarLista(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'lista' => ['required', 'in:minorista,mayorista'],
        ]);

        $this->carrito->cambiarLista($datos['lista']);

        return $this->respuesta();
    }

    /**
     * PUT /ventas/carrito/cliente
     * BODY: { "id_cliente": 3 }      -> selecciona cliente (y pasa a SU lista de precios)
     *       { "id_cliente": null }   -> vuelve a Consumidor Final (lista minorista)
     */
    public function seleccionarCliente(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_cliente' => ['present', 'nullable', 'integer', 'exists:cliente,id_cliente'],
        ]);

        $this->carrito->seleccionarCliente($datos['id_cliente']);

        return $this->respuesta();
    }

    /** DELETE /ventas/carrito — Botón "LIMPIAR". */
    public function limpiar(): JsonResponse
    {
        $this->carrito->limpiar();

        return $this->respuesta();
    }

    /** Respuesta estándar de todos los endpoints del carrito. */
    private function respuesta(): JsonResponse
    {
        return response()->json(['ok' => true, 'carrito' => $this->carrito->estado()]);
    }
}

/*
Qué valida cada método
-agregarProducto() exige id_producto, que sea entero y exista en producto. cantidad es opcional, pero si se envía debe ser un entero de al menos 1.
-cambiarCantidad() exige cantidad entera de al menos 1.
-descuentoManual() exige que la clave porcentaje esté presente y que sea nula o numérica entre 0 y 100.
-cambiarLista() solo acepta minorista o mayorista.
-seleccionarCliente() exige que la clave id_cliente esté presente. Acepta null o un ID entero existente en cliente.

Hay dos precisiones importantes sobre el descuento:

1.Para quitar el descuento manual y volver a la promoción automática, el frontend debe enviar explícitamente {"porcentaje":null}. Si omite la clave, falla la regla present.
2.El controlador permite hasta 100, pero CarritoService también compara con config('ventas.descuento_manual_maximo').
Ese límite del servicio es la regla efectiva; si la configuración se reduce, el servicio rechazará valores mayores aunque superen la validación inicial del controlador.

Los parámetros {idProducto} y {idPromocion} de las rutas están restringidos a números con whereNumber(...). Además, para las acciones sobre un producto, el servicio comprueba que esa línea exista en el carrito.

Respuesta común
El método privado respuesta() es el punto común:

<?php
private function respuesta(): JsonResponse
{
    return response()->json([
        'ok' => true,
        'carrito' => $this->carrito->estado(),
    ]);
}

Casos especiales para el frontend
-Quitar una línea y vaciar todo son operaciones distintas: DELETE /ventas/carrito/productos/{idProducto} quita una línea; DELETE /ventas/carrito limpia el carrito completo.
-Cambiar cantidad reemplaza el valor: mandar 12 significa “dejar 12”, no “sumar 12”.
-Cambiar cliente puede cambiar la lista de precios: después de seleccionar un cliente, usá el carrito devuelto para actualizar la selección visual y los precios.
-Una promoción puede fallar como operación completa: si no se puede agregar alguno de sus productos, el servicio no agrega ninguno.
-Enviar Accept: application/json ayuda a que los errores de validación de Laravel vuelvan en JSON. Las peticiones que modifican el carrito también deben incluir Content-Type:
application/json y X-CSRF-TOKEN, porque estas rutas pasan por el middleware web.

*/
