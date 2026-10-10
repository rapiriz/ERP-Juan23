<?php

// El namespace tiene que coincidir con la carpeta: app/Modules/Venta/Models
// (composer.json mapea "App\" a la carpeta "app/").
namespace App\Modules\Venta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PASO 1 - MODELO
 * Representa la tabla "venta". Con este modelo podemos insertar, leer
 * y actualizar ventas sin escribir SQL a mano (Venta::create, Venta::find, etc.).
 */
class Venta extends Model
{
    // Nombre real de la tabla (Laravel por defecto buscaría "ventas").
    protected $table = 'venta';

    // Clave primaria real (Laravel por defecto buscaría "id").
    protected $primaryKey = 'id_venta';

    // La tabla no tiene las columnas created_at / updated_at.
    public $timestamps = false;

    // Columnas que se pueden cargar con Venta::create([...]).
    // Cualquier otra columna que llegue se ignora (protección de "asignación masiva").
    protected $fillable = [
        'id_cliente',
        'fecha',
        'total',
        'descuento_global',
        'numFactura',
        'estado',
        'observaciones',
        'id_usuario',
    ];

    // Convierte los valores de la BD al tipo correcto cuando se leen.
    protected $casts = [
        'fecha' => 'date:Y-m-d',       // se devuelve como "2026-10-10" en el JSON
        'total' => 'decimal:2',        // 2 decimales
        'descuento_global' => 'decimal:2',
    ];

    /**
     * Relación: una venta TIENE MUCHOS detalles (una fila por producto vendido).
     * Uso: $venta->detalles  o  $venta->detalles()->createMany([...])
     */
    public function detalles(): HasMany
    {
        // (Modelo relacionado, FK en detalle_venta, PK en venta)
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }
}
