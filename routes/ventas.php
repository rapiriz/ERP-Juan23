<?php
/*¿Para qué sirve?
    Es el mapa de URLs del módulo de ventas. No calcula precios, no modifica el carrito y no consulta
    directamente la base de datos: cada definición relaciona un método HTTP y una URL con una acción
    de un controlador.

    El recorrido típico es:

    Frontend → ruta de este archivo → controlador → servicio/modelos → respuesta HTTP/JSON
*/
use App\Http\Controllers\Ventas\CajaController;
use App\Http\Controllers\Ventas\CarritoController;
use App\Http\Controllers\Ventas\ClienteController;
use App\Http\Controllers\Ventas\ProductoController;
use App\Http\Controllers\Ventas\PromocionController;
use App\Http\Controllers\Ventas\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| routes/ventas.php  —  Rutas del módulo de VENTAS (Punto de Venta)
|--------------------------------------------------------------------------
|
| ¿POR QUÉ UN ARCHIVO APARTE?
|   Para no pisarnos con el resto del equipo editando routes/web.php.
|   Se "enchufa" con UNA línea al final de routes/web.php:
|
|       require __DIR__.'/ventas.php';
|
| ¿POR QUÉ web.php Y NO api.php?
|   Estas rutas usan SESIÓN (el carrito vive ahí) y protección CSRF, que
|   solo están activas en el grupo "web". Al hacer require desde web.php,
|   heredan ese grupo automáticamente.
|
| IMPORTANTE: la ruta con nombre 'ventas' YA EXISTE en web.php (la usa
|   home.blade.php con route('ventas')). Hay que BORRAR esa línea vieja de
|   web.php, porque acá se redefine apuntando al controller real.
|   El nombre 'ventas' se mantiene, así que home.blade.php no se toca.
|
| Convención de nombres: las rutas de la API interna se llaman ventas.* (ventas.carrito.mostrar, etc.)
*/

// Pantalla del Punto de Venta (HTML). Nombre 'ventas' = el que ya usa home.blade.php.


Route::get('/ventas', [VentaController::class, 'index'])->name('ventas');
/*
    -get define una petición GET, apropiada para mostrar o consultar.
    -/ventas es la URL de la pantalla.
    -VentaController::class indica qué controlador la atiende.
    -'index' indica el método que Laravel ejecutará.
    -name('ventas') asigna el nombre 'ventas', útil para generar el enlace desde Blade con route('ventas').
*/


// Endpoints JSON que consume la pantalla (todos bajo /ventas/...)
Route::prefix('ventas')->name('ventas.')->group(function () {
/* El grupo aplica dos prefijos a todas las rutas que contiene:
    prefix('ventas') = agrega /ventas al inicio de la URL.
    name('ventas.') = agrega ventas. al inicio del nombre de la ruta.
    group(...) = agrupa varias rutas para no repetir los prefijos.
    Ejemplo: la ruta 'productos.buscar' queda con URL /ventas/productos/buscar.
*/


    // ---- Buscadores ----
    // Buscar productos por texto o código de barras (botón F2)
    Route::get('productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');
    // Buscar clientes
    Route::get('clientes/buscar',  [ClienteController::class, 'buscar'])->name('clientes.buscar');
    // Crear nuevo cliente
    Route::post('clientes',        [ClienteController::class, 'store'])->name('clientes.store');   // NUEVO CLIENTE (F1)

    // ---- Promociones vigentes (botón DESCUENTOS) ----
    //Consultar las promociones activas (vigentes) para mostrar en la pantalla de ventas.
    Route::get('promociones/activas', [PromocionController::class, 'activas'])->name('promociones.activas');

    // ---- Carrito ----
    //Obtener el carrito calculado actual (productos, cantidades, precios, descuentos, totales).
    Route::get('carrito',    [CarritoController::class, 'mostrar'])->name('carrito.mostrar');
    //Vaciar el carrito (elimina todos los productos y promociones).
    Route::delete('carrito', [CarritoController::class, 'limpiar'])->name('carrito.limpiar');          // LIMPIAR

    //agregar un producto
    Route::post('carrito/productos', [CarritoController::class, 'agregarProducto'])->name('carrito.agregar');
    //cambiar cantidad de un producto
    Route::patch('carrito/productos/{idProducto}', [CarritoController::class, 'cambiarCantidad'])
        ->whereNumber('idProducto')->name('carrito.cantidad');
    //quitar un producto
    Route::delete('carrito/productos/{idProducto}', [CarritoController::class, 'quitarProducto'])
        ->whereNumber('idProducto')->name('carrito.quitar');
    // cambiar descuento manual de un producto
    Route::patch('carrito/productos/{idProducto}/descuento', [CarritoController::class, 'descuentoManual'])
        ->whereNumber('idProducto')->name('carrito.descuento');


    //agregar una promoción al carrito
    Route::post('carrito/promociones/{idPromocion}', [CarritoController::class, 'agregarPromocion'])
        ->whereNumber('idPromocion')->name('carrito.promocion');
    //elegir lista minorista o mayorista
    Route::put('carrito/lista',   [CarritoController::class, 'cambiarLista'])->name('carrito.lista');
    //Elegir cliente o volver a consumidor final
    Route::put('carrito/cliente', [CarritoController::class, 'seleccionarCliente'])->name('carrito.cliente');

    // ---- Cobro ----
    //confirma la venta y registra el cobro
    Route::post('cobrar', [VentaController::class, 'cobrar'])->name('cobrar');                         // COBRAR

    // ---- Caja ----
    //Consultar el arqueo esperado del día (resumen de ingresos/egresos y ventas).
    Route::get('caja/resumen', [CajaController::class, 'resumen'])->name('caja.resumen');
    //Enviar el efectivo contado y recibir la diferencia calculada entre lo que debería haber y lo que hay
    Route::post('caja/cerrar', [CajaController::class, 'cerrar'])->name('caja.cerrar');                // CERRAR CAJA
});

/*
Los segmentos entre llaves, como {idProducto}, son parámetros variables de la URL.
Por ejemplo, /ventas/carrito/productos/12 le pasa el identificador 12 al controlador.

En las tres rutas con whereNumber('idProducto') o whereNumber('idPromocion'),
Laravel exige que el parámetro sea numérico. Una URL con letras en lugar del ID no coincide con esa ruta.

Los métodos HTTP también forman parte del contrato:

GET: consultar, sin cambiar el estado.
POST: crear o ejecutar una acción, como agregar un producto o cobrar.
PATCH: modificar parcialmente algo existente, como cantidad o descuento.
PUT: reemplazar una selección, como cliente o lista activa.
DELETE: quitar o limpiar.

FRONT:
Para conectar el frontend, tienen que coincidir tres cosas: el método HTTP, la URL y el formato del body que espera el controlador.
Las peticiones que modifican datos deben enviar el token CSRF de la sesión, además de indicar que esperan JSON.

Otra precisión para el frontend: POST /ventas/caja/cerrar calcula el arqueo y la diferencia, pero no guarda un cierre definitivo en la base.
 Ese límite pertenece al servicio/esquema, no a este archivo de rutas.
*/
