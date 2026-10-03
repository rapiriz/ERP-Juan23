<?php

/*
|--------------------------------------------------------------------------
| config/ventas.php  —  Configuración del módulo de VENTAS (Punto de Venta)
|--------------------------------------------------------------------------
|
| ¿POR QUÉ ESTE ARCHIVO?
|   Todo valor "de negocio" que podría cambiar (CUIT de consumidor final,
|   medios de pago, reglas de promociones, etc.) vive acá y NO dentro del
|   código. Así, si mañana cambia una regla, se toca un solo archivo.
|
| ¿CÓMO SE LEE?
|   config('ventas.medios_pago')                    -> array
|   config('ventas.consumidor_final.cuit')          -> '11.111.111-1'
|
| ¿QUIÉN LO USA?
|   - CarritoService   (datos de Consumidor Final, tope de descuento manual)
|   - PromocionService (reglas_promocion)
|   - VentaService     (medios_pago)
|   - ProductoController / ClienteController (max_resultados_busqueda)
|   - App\Support\UsuarioActual (usuario_demo_id)
|
| NOTA: después de modificar este archivo, si algo "no toma" el cambio:
|       php artisan config:clear
*/

return [

    /*
    | Datos que se muestran en la pantalla cuando NO hay cliente seleccionado
    | (bloque "EMITIR FACTURA A:" de la pantalla). En la base de datos una
    | venta a Consumidor Final se guarda con venta.id_cliente = NULL
    | (la columna lo permite), por eso estos datos NO están en la tabla cliente.
    */
    'consumidor_final' => [
        'nombre'        => 'Consumidor Final',
        'cuit'          => '11.111.111-1',
        'condicion_iva' => 'Consumidor Final',
        'domicilio'     => '',
    ],

    /*
    | Usuario que se registra como "vendedor" (venta.id_usuario, cobro.id_usuario,
    | movimiento_stock.id_usuario, etc.) MIENTRAS NO EXISTA el módulo de login.
    | 1 = usuario "admin" de los datos de prueba.
    | Cuando el login esté hecho, ver App\Support\UsuarioActual.
    */
    'usuario_demo_id' => 1,

    /*
    | Medios de pago válidos. DEBEN coincidir con el ENUM de la base
    | (cobro.medio_pago): 'efectivo','transferencia','cheque','tarjeta'.
    */
    'medios_pago' => ['efectivo', 'transferencia', 'cheque', 'tarjeta'],

    /*
    | Tope (en %) del descuento manual que el cajero puede poner a una línea
    | desde el botón DESCUENTOS. 100 = sin tope práctico. Ajustar según la
    | política de la distribuidora (ej: 20).
    */
    'descuento_manual_maximo' => 100,

    /* Cantidad máxima de resultados en los buscadores (productos y clientes). */
    'max_resultados_busqueda' => 15,

    /*
    | REGLAS EXTRA DE PROMOCIONES  (clave = promocion.id_promocion)
    |
    | La tabla `promocion` solo guarda: nombre, % de descuento (columna `total`),
    | vigencia y estado. Pero los datos de prueba tienen promos con condiciones
    | que están SOLO en la descripción:
    |     Promo 1: "5% comprando 100 o más alfajores"
    |     Promo 2: "10% en limpieza para clientes mayoristas"
    | Como la tabla no tiene columnas para eso (y la base la comparten otros
    | grupos, no conviene alterarla), las condiciones se declaran acá.
    |
    | Claves soportadas por promoción:
    |   'cantidad_minima' => int     unidades MÍNIMAS de ESE producto en el carrito
    |   'lista'           => string  'minorista' | 'mayorista' (lista de precios activa)
    |
    | Una promo SIN entrada acá se aplica siempre que esté vigente.
    */
    'reglas_promocion' => [
        1 => ['cantidad_minima' => 100],
        2 => ['lista' => 'mayorista'],
    ],
];
