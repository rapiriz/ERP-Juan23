<?php

namespace App\Services\Ventas;

use App\Models\Promocion; // Modelo que filtra promociones y relaciona cada una con sus productos.

/**
 * PromocionService  —  Decide QUÉ PROMOCIÓN le corresponde a cada producto.
 *
 * ¿QUÉ PROBLEMA RESUELVE?
 *   Un producto puede estar en varias promociones a la vez (tabla
 *   `promocion_producto`). Este servicio contesta: "dado lo que hay en el
 *   carrito, ¿qué promo (si alguna) se le aplica a cada producto?".
 *
 * REGLAS (en orden):
 *   1. La promo tiene que estar VIGENTE (estado activo + fechas) -> Promocion::vigente().
 *   2. El producto tiene que pertenecer a la promo (promocion_producto).
 *   3. Se cumplen las reglas extra declaradas en config/ventas.php
 *      ('cantidad_minima' y/o 'lista').
 *   4. Si varias promos califican para el mismo producto, GANA la de mayor %.
 *      (Nunca se acumulan: un producto lleva como máximo UN descuento.)
 *
 * DEPENDE DE : modelo Promocion, config/ventas.php (reglas_promocion).
 * LO USA     : CarritoService (para calcular precios) y PromocionController.
 *
 * IMPORTANCIA Y FLUJO:
 *   Centraliza la decisión de qué porcentaje aplica a cada producto para que
 *   CarritoService no repita las reglas. Recibe datos simples (cantidades y lista),
 *   consulta promociones vigentes y devuelve un mapa por id_producto.
 *   PromocionController también usa reglasDe() para explicar condiciones en la vista.
 *
 * CONEXIÓN CON EL FRONT:
 *   El navegador no llama directamente a este servicio. Puede consultar
 *   GET /ventas/promociones/activas para mostrar promos y condiciones; ese listado
 *   es informativo. Al agregar productos, CarritoService vuelve a evaluar las
 *   reglas y el estado devuelto por /ventas/carrito es la fuente de verdad del precio.
 *
 * DESEMPATE:
 *   Si dos promociones aplicables tienen el mismo porcentaje, el código conserva
 *   la primera que entregue la consulta. Como la consulta no define un orden de
 *   prioridad, ese empate no tiene un resultado garantizado.
 */
class PromocionService
{
    /**
     * @param  array<int,int> $cantidades  [id_producto => cantidad en el carrito]
     * @param  string         $lista       'minorista' | 'mayorista'
     * @return array<int,array{promocion:Promocion,porcentaje:float}>
     *         [id_producto => ['promocion' => Promocion, 'porcentaje' => 10.0]]
     *         Solo contiene los productos que SÍ tienen promo aplicable.
     */
    public function mejoresPorProducto(array $cantidades, string $lista): array
    {
        // Sin productos en el carrito, no hace falta consultar la base.
        if (empty($cantidades)) {
            return [];
        }

        // Se usan los IDs para limitar la búsqueda a promociones relacionadas
        // con alguno de los productos actualmente en el carrito.
        $ids = array_keys($cantidades);

        // Una consulta principal: solo promociones vigentes que incluyen algún
        // producto del carrito; with() precarga la relación para evitar N+1.
        $promos = Promocion::vigente()
            ->whereHas('productos', fn ($q) => $q->whereIn('producto.id_producto', $ids))
            ->with('productos')
            ->get();

        // Resultado indexado por ID de producto; cada producto tendrá como máximo una promo.
        $mejores = [];

        foreach ($promos as $promo) {
            // Accessor del modelo: interpreta la columna promocion.total como porcentaje.
            $porcentaje = $promo->porcentaje;
            if ($porcentaje <= 0) {
                continue; // una promo de 0% no aporta nada
            }

            // La promoción puede incluir más productos que los presentes en el carrito.
            foreach ($promo->productos as $producto) {
                $idProducto = $producto->id_producto;

                // Ignora productos relacionados con la promo que no están en el carrito.
                if (!isset($cantidades[$idProducto])) {
                    continue; // ese producto de la promo no está en el carrito
                }
                // La promo debe satisfacer reglas extra, como mínimo de unidades o lista.
                if (!$this->cumpleReglas($promo, $cantidades[$idProducto], $lista)) {
                    continue;
                }
                // Sustituye la candidata solo si esta ofrece un descuento mayor.
                // Si el porcentaje empata, conserva la primera candidata encontrada.
                if (!isset($mejores[$idProducto]) || $porcentaje > $mejores[$idProducto]['porcentaje']) {
                    $mejores[$idProducto] = ['promocion' => $promo, 'porcentaje' => $porcentaje];
                }
            }
        }

        return $mejores;
    }

    /**
     * Reglas extra de una promoción (las que la tabla `promocion` no puede guardar).
     * Se definen en config/ventas.php -> 'reglas_promocion'.
     */
    public function cumpleReglas(Promocion $promo, int $cantidad, string $lista): bool
    {
        // Las reglas no viven en columnas del esquema: se buscan por ID en config/ventas.php.
        $reglas = $this->reglasDe($promo);

        // Si requiere cierta cantidad, la cantidad de esta línea debe alcanzar el mínimo.
        if (isset($reglas['cantidad_minima']) && $cantidad < (int) $reglas['cantidad_minima']) {
            return false;
        }
        // Si está restringida a una lista, debe coincidir con la lista activa del carrito.
        if (isset($reglas['lista']) && $reglas['lista'] !== $lista) {
            return false;
        }

        // Una regla no configurada no restringe; si no falla ninguna, la promo aplica.
        return true;
    }

    /** Devuelve las reglas configuradas de una promo (array vacío si no tiene). Lo usa también PromocionController. */
    public function reglasDe(Promocion $promo): array
    {
        // Ejemplo: ventas.reglas_promocion.2; [] indica que no hay condiciones extra.
        return config('ventas.reglas_promocion.' . $promo->id_promocion, []);
    }
}
