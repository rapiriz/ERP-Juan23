<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller; // Clase base común de los controladores Laravel.
use App\Models\Promocion; // Modelo Eloquent de promociones y su relación con productos.
use App\Services\Ventas\CarritoService; // Informa la lista de precios activa en esta sesión.
use App\Services\Ventas\PromocionService; // Lee reglas adicionales configuradas para cada promoción.
use Illuminate\Http\JsonResponse; // Tipo de respuesta JSON de este endpoint.

/**
 * PromocionController  —  Lista de promociones vigentes (botón "DESCUENTOS").
 *
 * GET /ventas/promociones/activas
 *
 * Devuelve las promos vigentes HOY con sus productos, para que el front arme
 * el panel/modal de DESCUENTOS. Para agregar una promo al carrito:
 *       POST /ventas/carrito/promociones/{id_promocion}
 *
 * RESPUESTA:
 * {
 *   "ok": true,
 *   "promociones": [
 *     {
 *       "id_promocion": 2,
 *       "nombre": "Limpieza mayorista 10% OFF",
 *       "porcentaje": 10.0,
 *       "descripcion": "10% en productos de limpieza para clientes mayoristas",
 *       "vigencia_desde": "2026-09-01", "vigencia_hasta": "2026-12-31",
 *       "reglas": { "lista": "mayorista" },       // condiciones extra (config/ventas.php); {} si no hay
 *       "productos": [
 *          { "id_producto": 6, "codigo": "LIM-001", "nombre": "Lavandina 1L",
 *            "precio_lista": 800.0,               // según la lista de precios activa
 *            "precio_con_descuento": 720.0, "stock": 106 }
 *       ],
 *       "total_combo": 2250.0     // suma de 1 unidad de CADA producto con el % aplicado
 *                                 // (acá se muestra un solo producto por brevedad: 720 + 1530 = 2250)
 *     }
 *   ]
 * }
 *
 * DEPENDE DE : modelo Promocion, PromocionService (reglas), CarritoService (lista activa).
 * NOTA: la pantalla "Promociones" (promociones.blade.php) hoy tiene datos
 *       escritos a mano; puede alimentarse de este mismo endpoint.
 *
 * IMPORTANCIA Y LÍMITES:
 *   Este controlador arma la información para mostrar promociones; no agrega
 *   productos al carrito ni decide por sí solo si una regla de cantidad/lista
 *   ya se cumple. Incluye esas condiciones en "reglas" para que el front pueda
 *   explicarlas. El carrito vuelve a evaluar la elegibilidad al recalcularse.
 *   "precio_con_descuento" y "total_combo" son valores de referencia: no deben
 *   tratarse como precio final garantizado si la regla aún no se cumple.
 *
 * CONEXIÓN CON EL FRONT:
 *   GET /ventas/promociones/activas devuelve { ok, promociones }.
 *   Para agregar una promoción, el front llama aparte a
 *   POST /ventas/carrito/promociones/{id_promocion}, y luego redibuja con
 *   el objeto "carrito" devuelto por ese endpoint.
 *   Enviar Accept: application/json y mantener la sesión para usar la lista activa.
 */
class PromocionController extends Controller
{
    public function __construct(
        private PromocionService $promociones,
        private CarritoService $carrito
    ) {
        // Laravel inyecta ambos servicios. PromocionService aporta las reglas
        // de configuración; CarritoService solo informa la lista de esta sesión.
    }

    /** GET /ventas/promociones/activas — Prepara promociones vigentes para mostrarlas. */
    public function activas(): JsonResponse
    {
        // La misma promoción puede mostrar precios distintos según el tipo de cliente.
        $lista = $this->carrito->listaActual();

        // vigente() filtra por estado y fechas; with() precarga los productos
        // relacionados para evitar una consulta adicional por cada promoción.
        // El orden por nombre hace estable la presentación en el selector.
        // Esta consulta NO filtra por reglas de lista o cantidad mínima.
        $promos = Promocion::vigente()->with('productos')->orderBy('nombre')->get()
            ->map(function (Promocion $promo) use ($lista) {
                // Accessor del modelo: porcentaje de descuento derivado de la columna total.
                $pct = $promo->porcentaje;

                // De la relación precargada conserva solo productos activos.
                // El filtro no excluye productos con stock cero; el front recibe stock.
                $productos = $promo->productos->where('estado', 'activo')->map(function ($p) use ($lista, $pct) {
                    // El precio de referencia depende de la lista de la sesión.
                    $precio = $lista === 'mayorista' ? $p->precioMay : $p->precioMin;

                    return [
                        'id_producto'          => $p->id_producto,
                        'codigo'               => $p->codigo,
                        'nombre'               => $p->nombre,
                        'precio_lista'         => $precio,
                        // Cálculo orientativo con el porcentaje de la promoción.
                        // No comprueba acá las reglas de elegibilidad.
                        'precio_con_descuento' => round($precio * (1 - $pct / 100), 2),
                        'stock'                => $p->stock,
                    ];
                // values() reindexa los productos para que JSON los represente como una lista.
                })->values();

                return [
                    'id_promocion'   => $promo->id_promocion,
                    'nombre'         => $promo->nombre,
                    'porcentaje'     => $pct,
                    'descripcion'    => $promo->descripcion,
                    'vigencia_desde' => $promo->vigencia_desde,
                    'vigencia_hasta' => $promo->vigencia_hasta,
                    // Fuerza {} cuando no hay reglas, en vez de [] en el JSON.
                    // Son datos descriptivos para la vista, no un filtro aplicado aquí.
                    'reglas'         => (object) $this->promociones->reglasDe($promo),
                    'productos'      => $productos,
                    // Suma el precio descontado de una unidad de cada producto activo.
                    // No multiplica cantidades ni verifica reglas, stock o elegibilidad.
                    'total_combo'    => round($productos->sum('precio_con_descuento'), 2),
                ];
            });

        // La vista recibe la colección preparada bajo una envoltura estable.
        return response()->json(['ok' => true, 'promociones' => $promos]);
    }
}
