<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller; // Clase base común de los controladores Laravel.
use App\Models\Producto; // Modelo Eloquent conectado a la tabla producto.
use App\Services\Ventas\CarritoService; // Informa qué lista de precios está activa en la sesión.
use Illuminate\Http\JsonResponse; // Tipo de respuesta JSON del endpoint.
use Illuminate\Http\Request; // Petición HTTP, de donde se lee el término de búsqueda.

/**
 * ProductoController  —  Buscador "Buscar producto por nombre o código...".
 *
 * GET /ventas/productos/buscar?q=agua
 *
 * Busca (solo productos ACTIVOS) por coincidencia parcial en `nombre` o `codigo`.
 * Los códigos que coinciden EXACTO salen primero: pensado para lector de
 * código de barras (el front puede agregar directo si llega 1 resultado con
 * "coincidencia_exacta": true).
 *
 * RESPUESTA:
 * {
 *   "ok": true,
 *   "lista": "minorista",
 *   "productos": [
 *     { "id_producto": 1, "codigo": "BEB-001", "nombre": "Agua mineral 2L",
 *       "precio": 1200.0,         // ya según la lista activa del carrito
 *       "stock": 250, "sin_stock": false, "coincidencia_exacta": false }
 *   ]
 * }
 *
 * Si "q" viene vacío devuelve una lista vacía (no vuelca todo el catálogo).
 *
 * DEPENDE DE : modelo Producto, CarritoService (para conocer la lista activa).
 *
 * IMPORTANCIA EN EL POS:
 *   Entrega al buscador los productos seleccionables con su precio ya resuelto
 *   para la lista actual. El front muestra estos datos; no calcula el precio.
 *   Este endpoint solo consulta: agregar el producto al carrito es otra ruta.
 *
 * CONEXIÓN CON EL FRONT:
 *   GET /ventas/productos/buscar?q=texto (URL-encodear q si contiene espacios
 *   o caracteres especiales). Enviar Accept: application/json y conservar la
 *   cookie de sesión, porque la lista activa sale del carrito en sesión.
 *   Si hay un único resultado y coincidencia_exacta es true, el front puede
 *   ofrecer agregarlo directamente; esta acción no ocurre en este endpoint.
 *   Puede haber productos con stock 0: mostrar sin_stock y no permitir agregarlos.
 *
 *   Respuesta: { ok, lista, productos: [{ id_producto, codigo, nombre,
 *   precio, stock, sin_stock, coincidencia_exacta }] }.
 */
class ProductoController extends Controller
{
    public function __construct(private CarritoService $carrito)
    {
        // Laravel inyecta CarritoService. Se usa solo para leer la lista activa;
        // la búsqueda no modifica el carrito.
    }

    public function buscar(Request $request): JsonResponse
    {
        // Lee q de la query string (?q=...) y elimina espacios exteriores.
        // Si falta, el valor por defecto es una cadena vacía.
        $q     = trim((string) $request->query('q', ''));

        // Consulta la lista de precios guardada en el carrito de esta sesión.
        $lista = $this->carrito->listaActual();

        if ($q === '') {
            // Evita listar todo el catálogo y aun así informa la lista activa.
            return response()->json(['ok' => true, 'lista' => $lista, 'productos' => []]);
        }

        // Escapa comodines de LIKE (% y _) para que se busquen como caracteres
        // literales. Eloquent parametriza el valor para la consulta SQL.
        $like = '%' . addcslashes($q, '%_\\') . '%';

        $productos = Producto::activo()
            // El scope activo() excluye productos cuyo estado no sea activo.
            ->where(fn ($w) => $w->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like))
            // Prioriza el código igual al texto ingresado (lector de barras).
            ->orderByRaw('codigo = ? DESC', [$q])   // código exacto primero
            // Orden estable para los demás resultados.
            ->orderBy('nombre')
            // Límite compartido con el buscador de clientes, definido en config/ventas.php.
            ->limit((int) config('ventas.max_resultados_busqueda'))
            ->get()
            // Expone solo lo que necesita el selector; no serializa todo el modelo.
            ->map(fn (Producto $p) => [
                'id_producto'         => $p->id_producto,
                'codigo'              => $p->codigo,
                'nombre'              => $p->nombre,
                // Devuelve el precio adecuado para que el front solo lo muestre.
                // Producto castea ambos precios a float.
                'precio'              => $lista === 'mayorista' ? $p->precioMay : $p->precioMin,
                'stock'               => $p->stock,
                // Se informa stock cero; no se filtra de los resultados.
                'sin_stock'           => $p->stock <= 0,
                // La comparación es exacta e insensible a mayúsculas/minúsculas.
                'coincidencia_exacta' => strcasecmp($p->codigo, $q) === 0,
            ]);

        // Devuelve resultados y lista juntos, incluso si la búsqueda no encontró productos.
        return response()->json(['ok' => true, 'lista' => $lista, 'productos' => $productos]);
    }
}
