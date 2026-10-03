<?php

namespace App\Services\Ventas;

use App\Exceptions\VentaException; // Excepción de negocio que Laravel convierte en JSON de error.
use App\Models\Cliente; // Consulta clientes activos y sus listas de precios.
use App\Models\Producto; // Fuente de precio, nombre, estado y stock actuales.
use App\Models\Promocion; // Se usa para agregar los productos de una promo vigente.

/**
 * CarritoService  —  Toda la lógica del CARRITO del Punto de Venta.
 *
 * ============================== IDEA CENTRAL ==============================
 *  El carrito vive en la SESIÓN de Laravel (por usuario/navegador), pero ahí
 *  se guarda lo MÍNIMO: ids y cantidades, nunca precios ni totales.
 *
 *       session['ventas.carrito'] = [
 *           'cliente_id' => null | 3,            // null = Consumidor Final
 *           'lista'      => 'minorista' | 'mayorista',
 *           'lineas'     => [
 *               8 => ['cantidad' => 100, 'descuento_manual' => null],  // id_producto => ...
 *           ],
 *       ];
 *
 *  Cada vez que se pide el carrito (estado()) se RECALCULA todo leyendo la
 *  base de datos: precio vigente, promociones, stock, totales. Así:
 *    - Nunca hay precios "viejos" si alguien cambió el precio mientras tanto.
 *    - El FRONT no calcula nada: solo muestra lo que devuelve estado().
 * ==========================================================================
 *
 * REGLAS DE NEGOCIO QUE IMPLEMENTA
 *   - Lista 1 = Minorista (producto.precioMin) / Lista 2 = Mayorista (producto.precioMay).
 *   - Al elegir un cliente, la lista cambia sola a su tipo_cliente (se puede
 *     cambiar a mano después con cambiarLista()).
 *   - No se puede agregar más cantidad que el stock disponible.
 *   - Descuento de una línea:
 *        a) Si el cajero puso un descuento MANUAL (aunque sea 0%), manda ese.
 *        b) Si no, se aplica la mejor promoción vigente (PromocionService).
 *        c) Si no hay ninguna, descuento 0.
 *     descuento($) = round(precio * cantidad * porcentaje / 100, 2)
 *     subtotal     = precio * cantidad - descuento
 *
 * DEPENDE DE : PromocionService, modelos Producto / Cliente / Promocion, config/ventas.php.
 * LO USAN    : CarritoController (cada botón de la pantalla), VentaService (al cobrar),
 *              VentaController@index (estado inicial), ProductoController (lista activa).
 *
 * FLUJO DE INTEGRACIÓN:
 *   El frontend llama a las rutas de CarritoController; este servicio cambia
 *   únicamente la sesión o consulta los modelos y devuelve estado(). El controller
 *   responde { ok: true, carrito: ... }. VentaService vuelve a consultar estado()
 *   al cobrar y valida stock nuevamente dentro de una transacción.
 *
 * GUÍA PARA EL FRONT:
 *   Después de cada acción exitosa, reemplazar el estado local con el objeto
 *   "carrito" recibido; no reconstruir precios, promos o totales en JavaScript.
 *   En errores, manejar VentaException ({ mensaje, errores }) y errores de
 *   validación HTTP ({ message, errors }). Las rutas usan sesión web y CSRF.
 *
 * IMPORTANTE:
 *   La sesión guarda solo ids/cantidades/selecciones, no precios. estado() consulta
 *   valores actuales; por eso una respuesta posterior puede reflejar cambios de
 *   precio o stock ocurridos desde la operación anterior.
 */
class CarritoService
{
    /** Clave de sesión donde se guarda el estado mínimo del carrito. */
    private const SESSION_KEY = 'ventas.carrito';

    public function __construct(private PromocionService $promociones)
    {
        // Laravel inyecta el servicio que determina la mejor promo aplicable
        // a cada producto según cantidades, vigencia y lista activa.
    }

    // ======================================================================
    //  ACCIONES (cada una corresponde a algo que el usuario hace en pantalla)
    // ======================================================================

    /**
     * Agrega un producto (o suma unidades si ya estaba).
     * PANTALLA: seleccionar un resultado del buscador "Buscar producto por nombre o código...".
     */
    public function agregarProducto(int $idProducto, int $cantidad = 1): void
    {
        // Defensa de negocio adicional a la validación del Controller.
        if ($cantidad < 1) {
            throw new VentaException('La cantidad debe ser al menos 1.');
        }

        // Solo se agregan productos activos; productoActivoOFallar responde 404 si no existe.
        $producto = $this->productoActivoOFallar($idProducto);
        // Lee cliente, lista y líneas actuales de esta sesión.
        $carrito  = $this->leer();

        // Agregar es acumulativo: suma la cantidad solicitada a la que ya estaba.
        $actual = $carrito['lineas'][$idProducto]['cantidad'] ?? 0;
        $nueva  = $actual + $cantidad;
        // Verifica stock contra la cantidad total resultante, no solo el incremento.
        $this->validarStock($producto, $nueva);

        // Persiste solo cantidad y descuento manual; precio y subtotal se recalculan.
        $carrito['lineas'][$idProducto] = [
            'cantidad'         => $nueva,
            // si la línea ya existía conservamos su descuento manual
            'descuento_manual' => $carrito['lineas'][$idProducto]['descuento_manual'] ?? null,
        ];
        $this->guardar($carrito);
    }

    /**
     * Cambia la cantidad de una línea (la columna CANTIDAD de la tabla del carrito).
     */
    public function cambiarCantidad(int $idProducto, int $cantidad): void
    {
        // Se debe cambiar una línea existente; no crea una nueva.
        $carrito = $this->leer();
        $this->lineaOFallar($carrito, $idProducto);

        if ($cantidad < 1) {
            throw new VentaException('La cantidad debe ser al menos 1. Para sacar el producto use "quitar".');
        }

        // A diferencia de agregarProducto(), cantidad reemplaza el total de unidades.
        $this->validarStock($this->productoActivoOFallar($idProducto), $cantidad);

        // Conserva el descuento manual previamente guardado para esta línea.
        $carrito['lineas'][$idProducto]['cantidad'] = $cantidad;
        $this->guardar($carrito);
    }

    /** Quita una línea completa del carrito. */
    public function quitarProducto(int $idProducto): void
    {
        // Exige que la línea exista para diferenciar un producto no presente.
        $carrito = $this->leer();
        $this->lineaOFallar($carrito, $idProducto);

        // Elimina solo este producto; no cambia el cliente ni la lista de precios.
        unset($carrito['lineas'][$idProducto]);
        $this->guardar($carrito);
    }

    /**
     * Botón "LIMPIAR": vacía el carrito y vuelve a Consumidor Final / Lista 1.
     */
    public function limpiar(): void
    {
        // Al borrar la clave, leer() devolverá los valores iniciales:
        // Consumidor Final, lista minorista y líneas vacías.
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Botones "Lista 1 — Minorista" / "Lista 2 — Mayorista".
     * Al cambiar la lista, todos los precios del carrito se recalculan solos
     * (porque estado() siempre lee el precio según la lista actual).
     */
    public function cambiarLista(string $lista): void
    {
        // La validación del servicio protege también llamadas que no vengan del controller.
        if (!in_array($lista, ['minorista', 'mayorista'], true)) {
            throw new VentaException('Lista de precios inválida.');
        }

        // Solo cambia la preferencia; estado() recalcula todas las líneas al consultarse.
        $carrito = $this->leer();
        $carrito['lista'] = $lista;
        $this->guardar($carrito);
    }

    /**
     * Botón "BUSCAR CLIENTE" (al elegir uno) o "Consumidor Final" (null).
     * Efecto secundario IMPORTANTE: la lista de precios pasa a ser la del tipo
     * de cliente (minorista/mayorista). Consumidor Final -> minorista.
     */
    public function seleccionarCliente(?int $idCliente): void
    {
        $carrito = $this->leer();

        if ($idCliente === null) {
            // Consumidor Final no tiene fila cliente: se representa con id null.
            $carrito['cliente_id'] = null;
            $carrito['lista']      = 'minorista';
        } else {
            // Un cliente inactivo no puede seleccionarse en el POS.
            $cliente = Cliente::activo()->find($idCliente);
            if (!$cliente) {
                throw new VentaException('El cliente no existe o está inactivo.', 404);
            }
            // La lista inicial se toma del tipo del cliente; puede cambiarse luego.
            $carrito['cliente_id'] = $cliente->id_cliente;
            $carrito['lista']      = $cliente->tipo_cliente; // 'minorista' | 'mayorista'
        }

        $this->guardar($carrito);
    }

    /**
     * Botón "DESCUENTOS" -> descuento manual sobre UNA línea.
     *
     * @param float|null $porcentaje 0–100. null = QUITAR el manual y volver a la promo automática.
     *                               0 = "sin descuento" (anula incluso la promo automática).
     */
    public function descuentoManual(int $idProducto, ?float $porcentaje): void
    {
        // El producto debe estar ya en el carrito.
        $carrito = $this->leer();
        $this->lineaOFallar($carrito, $idProducto);

        if ($porcentaje !== null) {
            // El tope se configura en config/ventas.php y se vuelve a validar acá.
            $maximo = (float) config('ventas.descuento_manual_maximo');
            if ($porcentaje < 0 || $porcentaje > $maximo) {
                throw new VentaException("El descuento debe estar entre 0% y {$maximo}%.");
            }
        }

        // null quita la preferencia manual; 0 la mantiene y anula promos automáticas.
        $carrito['lineas'][$idProducto]['descuento_manual'] = $porcentaje;
        $this->guardar($carrito);
    }

    /**
     * Botón "Agregar" de una promoción (pantalla Promociones / modal DESCUENTOS):
     * suma 1 unidad de CADA producto de la promo. El descuento no se "pega" acá:
     * se aplica solo en estado() porque esos productos pertenecen a la promo vigente.
     * Es "todo o nada": si un producto no tiene stock, no se agrega ninguno.
     */
    public function agregarPromocion(int $idPromocion): void
    {
        // Rechaza promociones inexistentes, inactivas o fuera de vigencia.
        $promo = Promocion::vigente()->with('productos')->find($idPromocion);
        if (!$promo) {
            throw new VentaException('La promoción no existe o no está vigente.', 404);
        }

        // Solo se consideran productos activos; el stock se valida a continuación.
        $productos = $promo->productos->where('estado', 'activo');
        if ($productos->isEmpty()) {
            throw new VentaException('La promoción no tiene productos disponibles.');
        }

        $carrito = $this->leer();

        // 1) Validar TODO primero (todo o nada)
        foreach ($productos as $producto) {
            $nueva = ($carrito['lineas'][$producto->id_producto]['cantidad'] ?? 0) + 1;
            $this->validarStock($producto, $nueva);
        }
        // 2) Recién ahora modificar el carrito
        foreach ($productos as $producto) {
            $id = $producto->id_producto;
            $carrito['lineas'][$id] = [
                'cantidad'         => ($carrito['lineas'][$id]['cantidad'] ?? 0) + 1,
                'descuento_manual' => $carrito['lineas'][$id]['descuento_manual'] ?? null,
            ];
        }
        $this->guardar($carrito);
    }

    // ======================================================================
    //  LECTURA: el estado completo y calculado del carrito
    // ======================================================================

    /** Lista de precios activa ('minorista' | 'mayorista'). La usa ProductoController para mostrar el precio correcto. */
    public function listaActual(): string
    {
        // Lectura liviana para ProductoController y PromocionController;
        // no consulta productos ni recalcula todas las líneas.
        return $this->leer()['lista'];
    }

    /**
     * Devuelve TODO lo que el front necesita para dibujar la pantalla.
     * (Es el mismo JSON que devuelven todos los endpoints del carrito.)
     *
     * FORMA DEL RESULTADO
     * [
     *   'cliente' => [                  // bloque "EMITIR FACTURA A:"
     *       'id' => null|int, 'nombre', 'cuit', 'condicion_iva', 'domicilio',
     *       'tipo_cliente' => null|'minorista'|'mayorista', 'saldo' => float,
     *       'es_consumidor_final' => bool,
     *   ],
     *   'lista'  => 'minorista'|'mayorista',   // botón de lista que debe verse activo
     *   'lineas' => [                          // filas de la tabla (puede estar vacío)
     *       [
     *         'id_producto', 'codigo' (CÓD.), 'descripcion' (DESCRIPCIÓN),
     *         'cantidad' (CANTIDAD), 'precio_unitario' (PRECIO U.),
     *         'bruto' (precio*cantidad), 'descuento_pct', 'descuento' (DESC. en $),
     *         'origen_descuento' => null|'promo'|'manual',
     *         'promocion' => null|['id','nombre'], 'descuento_manual' => null|float,
     *         'subtotal' (SUBTOTAL), 'stock_disponible', 'problema' => null|string,
     *       ], ...
     *   ],
     *   'totales' => [                         // pie de la tabla
     *       'articulos' => int  (cantidad de líneas)   -> "N art."
     *       'unidades'  => int  (suma de cantidades)   -> "N unid."
     *       'subtotal_bruto' => float, 'descuento_total' => float,
     *       'total' => float                            -> "Total $ 0,00"
     *   ],
     *   'puede_cobrar' => bool,    // false => botón COBRAR deshabilitado (carrito vacío o con problemas)
     *   'advertencias' => string[] // mensajes generales para mostrar
     * ]
     */
    public function estado(): array
    {
        // Punto de recomputación: nunca se confía en precios guardados en sesión.
        $carrito = $this->leer();
        $lista   = $carrito['lista'];
        $advertencias = [];

        // --- Cargar de la base todo lo necesario (pocas consultas, no una por línea) ---
        // Prepara el mapa requerido por PromocionService: id_producto => cantidad.
        $cantidades = [];
        foreach ($carrito['lineas'] as $idProducto => $linea) {
            $cantidades[$idProducto] = (int) $linea['cantidad'];
        }

        // Carga en una consulta los productos presentes y crea acceso por id.
        // Incluye inactivos para poder informar el problema en la línea.
        $productos = Producto::whereIn('id_producto', array_keys($cantidades))->get()->keyBy('id_producto');
        // Devuelve solo las promociones que realmente cumplen cantidad y lista.
        $promos    = $this->promociones->mejoresPorProducto($cantidades, $lista);

        // --- Construir cada línea ---
        $lineas = [];
        $subtotalBruto = $descuentoTotal = $total = 0.0;
        $unidades = 0;
        $hayProblemas = false;
        $seDepuro = false;

        foreach ($carrito['lineas'] as $idProducto => $linea) {
            $producto = $productos->get($idProducto);

            if (!$producto) {
                // Autocorrección: producto desaparecido de la base -> se saca de la sesión.
                unset($carrito['lineas'][$idProducto]);
                $seDepuro = true;
                $advertencias[] = "Se quitó del carrito el producto #{$idProducto} porque ya no existe.";
                continue;
            }

            // Usa precio actual y lista vigente para esta sesión.
            $cantidad = (int) $linea['cantidad'];
            $precio   = $lista === 'mayorista' ? (float) $producto->precioMay : (float) $producto->precioMin;
            $bruto    = round($precio * $cantidad, 2);

            // Prioridad del descuento: manual (incluso 0) > promo automática > nada
            // Un descuento manual, incluso 0%, tiene prioridad sobre promociones.
            $manual = $linea['descuento_manual'];
            if ($manual !== null) {
                $porcentaje = (float) $manual;
                $promo      = null;
                $origen     = $porcentaje > 0 ? 'manual' : null;
            } elseif (isset($promos[$idProducto])) {
                $porcentaje = $promos[$idProducto]['porcentaje'];
                $promo      = $promos[$idProducto]['promocion'];
                $origen     = 'promo';
            } else {
                $porcentaje = 0.0;
                $promo      = null;
                $origen     = null;
            }

            // Importes monetarios se redondean a dos decimales por línea.
            $descuento = round($bruto * $porcentaje / 100, 2);
            $subtotal  = round($bruto - $descuento, 2);

            // Problemas que impiden cobrar (el front los marca en rojo en esa fila)
            // Señala problemas que bloquean el cobro; no oculta la línea al front.
            $problema = null;
            if ($producto->estado !== 'activo') {
                $problema = 'El producto está inactivo.';
            } elseif ($cantidad > $producto->stock) {
                $problema = "Stock insuficiente: disponible {$producto->stock}.";
            }
            $hayProblemas = $hayProblemas || $problema !== null;

            $lineas[] = [
                'id_producto'      => $producto->id_producto,
                'codigo'           => $producto->codigo,
                'descripcion'      => $producto->nombre,
                'cantidad'         => $cantidad,
                'precio_unitario'  => $precio,
                'bruto'            => $bruto,
                'descuento_pct'    => $porcentaje,
                'descuento'        => $descuento,
                'origen_descuento' => $origen,
                'promocion'        => $promo ? ['id' => $promo->id_promocion, 'nombre' => $promo->nombre] : null,
                'descuento_manual' => $manual,
                'subtotal'         => $subtotal,
                'stock_disponible' => $producto->stock,
                'problema'         => $problema,
            ];

            $subtotalBruto  += $bruto;
            $descuentoTotal += $descuento;
            $total          += $subtotal;
            $unidades       += $cantidad;
        }

        // Si se quitaron ids de producto que ya no existen, guarda la sesión limpia.
        if ($seDepuro) {
            $this->guardar($carrito);
        }

        // Este objeto es el contrato principal que reciben todos los endpoints del carrito.
        return [
            'cliente'  => $this->datosCliente($carrito['cliente_id']),
            'lista'    => $lista,
            'lineas'   => $lineas,
            'totales'  => [
                'articulos'      => count($lineas),
                'unidades'       => $unidades,
                'subtotal_bruto' => round($subtotalBruto, 2),
                'descuento_total'=> round($descuentoTotal, 2),
                'total'          => round($total, 2),
            ],
            'puede_cobrar' => count($lineas) > 0 && !$hayProblemas,
            'advertencias' => $advertencias,
        ];
    }

    // ======================================================================
    //  AUXILIARES PRIVADOS
    // ======================================================================

    /** Datos del bloque "EMITIR FACTURA A:". null = Consumidor Final (datos de config/ventas.php). */
    private function datosCliente(?int $idCliente): array
    {
        // id null (o referencia ya eliminada) usa los datos configurables de Consumidor Final.
        $cliente = $idCliente ? Cliente::find($idCliente) : null;

        if (!$cliente) {
            return array_merge(config('ventas.consumidor_final'), [
                'id' => null, 'tipo_cliente' => null, 'saldo' => 0.0, 'es_consumidor_final' => true,
            ]);
        }

        return [
            'id'                  => $cliente->id_cliente,
            'nombre'              => $cliente->nombre_completo,
            'cuit'                => $cliente->dni_cuit,
            'condicion_iva'       => $cliente->condicion_iva,
            'domicilio'           => $cliente->domicilio_completo,
            'tipo_cliente'        => $cliente->tipo_cliente,
            'saldo'               => $cliente->saldo,
            'es_consumidor_final' => false,
        ];
    }

    private function productoActivoOFallar(int $idProducto): Producto
    {
        // Busca solo productos vendibles y centraliza el error 404 de negocio.
        $producto = Producto::activo()->find($idProducto);
        if (!$producto) {
            throw new VentaException('El producto no existe o está inactivo.', 404);
        }
        return $producto;
    }

    private function validarStock(Producto $producto, int $cantidadDeseada): void
    {
        // Validación anticipada para facilitar feedback; VentaService revalida
        // bajo bloqueo de filas al cobrar para cubrir ventas simultáneas.
        if ($cantidadDeseada > $producto->stock) {
            throw new VentaException(
                "Stock insuficiente de \"{$producto->nombre}\": disponible {$producto->stock}, pedido {$cantidadDeseada}."
            );
        }
    }

    private function lineaOFallar(array $carrito, int $idProducto): void
    {
        // Los cambios y descuentos solo se aplican a líneas guardadas en la sesión.
        if (!isset($carrito['lineas'][$idProducto])) {
            throw new VentaException('Ese producto no está en el carrito.', 404);
        }
    }

    private function leer(): array
    {
        // Inicializa el carrito la primera vez que se accede a esta sesión.
        return session(self::SESSION_KEY, [
            'cliente_id' => null,
            'lista'      => 'minorista',
            'lineas'     => [],
        ]);
    }

    private function guardar(array $carrito): void
    {
        // Persiste el estado mínimo en la sesión web actual.
        session([self::SESSION_KEY => $carrito]);
    }
}

/*
Acción	Petición
Consultar el estado =	GET /ventas/carrito
Agregar producto =	POST /ventas/carrito/productos, body {"id_producto":8,"cantidad":1}
Reemplazar cantidad =	PATCH /ventas/carrito/productos/8, body {"cantidad":12}
Quitar una línea	= DELETE /ventas/carrito/productos/8
Vaciar todo =	DELETE /ventas/carrito
Cambiar lista =	PUT /ventas/carrito/lista, body {"lista":"mayorista"}
Elegir cliente o Consumidor Final =	PUT /ventas/carrito/cliente, body {"id_cliente":3} o {"id_cliente":null}
Aplicar/quitar descuento manual =	PATCH /ventas/carrito/productos/8/descuento, body {"porcentaje":15} o {"porcentaje":null}
Agregar promoción =	POST /ventas/carrito/promociones/2

*/
