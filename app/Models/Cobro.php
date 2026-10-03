<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; // ORM de Laravel para leer y escribir filas de la base.

/**
 * Modelo Cobro  ->  tabla `cobro`
 *
 * Un cobro (pago) registrado: monto, medio de pago, comprobante.
 * Una venta puede tener VARIOS cobros (ej: mitad efectivo, mitad tarjeta); se
 * vinculan por la tabla intermedia `cobro_venta`.
 * USADO POR: VentaService, CajaService.
 *
 * $guarded = [] : permite create([...]) con cualquier columna. Es seguro
 * porque este modelo SOLO se escribe desde los Services (con datos ya
 * validados), jamás directamente con lo que llega del request.
 *
 * CONEXIONES:
 *   - VentaService crea un Cobro por cada medio de pago aplicado a una venta.
 *   - VentaService vincula cada cobro con la venta usando la tabla cobro_venta.
 *   - CajaService une cobro con caja_movimiento para agrupar ingresos por medio.
 *   - El frontend no consume este modelo directamente; recibe el resumen de venta
 *     y los números de comprobante que devuelve VentaService.
 *
 * CUIDADO:
 *   $guarded = [] permite asignar cualquier columna mediante create()/update().
 *   No pasarle datos crudos del Request; construir arreglos explícitos en servicios.
 */
class Cobro extends Model // Representa un pago individual persistido en la tabla cobro.
{
    protected $table = 'cobro'; // Tabla física de pagos.
    protected $primaryKey = 'id_cobro'; // Clave primaria declarada por el esquema.
    public $timestamps = false; // Eloquent no debe asumir created_at/updated_at en esta tabla.
    protected $guarded = []; // Sin columnas protegidas: solo usar con datos internos confiables.
}

//Grupo 2 tiene cobros
