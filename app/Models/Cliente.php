<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; // Modelo base ORM de Laravel (Eloquent).

/**
 * Modelo Cliente  ->  tabla `cliente`
 *
 * ¿QUÉ ES UN MODELO?
 *   Es la "puerta de entrada" de PHP a una tabla de MySQL. En vez de escribir
 *   SQL a mano, usamos Cliente::find(3), Cliente::where(...)->get(), etc.
 *
 * DATOS CLAVE PARA VENTAS:
 *   - tipo_cliente ('minorista' | 'mayorista') define QUÉ LISTA DE PRECIOS se
 *     usa al elegir el cliente en el POS (Lista 1 = minorista, Lista 2 = mayorista).
 *   - saldo = deuda del cliente (cuenta corriente). Sube cuando se le vende
 *     "a cuenta" o con pago parcial (lo hace VentaService).
 *   - Consumidor Final NO existe como fila: es "sin cliente" (id_cliente NULL).
 *
 * USADO POR: CarritoService, VentaService, ClienteController.
 *
 * CONEXIÓN CON EL FRONT:
 *   El navegador no usa este modelo directamente. ClienteController toma sus
 *   datos para buscar/crear clientes, y CarritoService arma el bloque cliente
 *   del estado del carrito. nombre_completo y domicilio_completo son atributos
 *   calculados; no son columnas que deban enviarse al crear un cliente.
 *
 * SEGURIDAD:
 *   $fillable permite asignación masiva de las columnas listadas, pero no valida
 *   datos. Los controllers deben validar y filtrar el body antes de llamar create().
 */
class Cliente extends Model // Representa una fila de la tabla cliente.
{
    protected $table = 'cliente'; // Nombre real de la tabla (singular, en español).
    protected $primaryKey = 'id_cliente'; // Clave primaria, distinta de la convención id.

    // La tabla tiene created_at y updated_at; Eloquent los mantiene automáticamente.
    public $timestamps = true;

    // Lista blanca de atributos que Cliente::create([...]) puede asignar en bloque.
    // No reemplaza la validación HTTP: solo controla qué campos acepta Eloquent.
    protected $fillable = [
        // Identidad y datos de contacto.
        'nombre', 'apellido_razon_social', 'dni_cuit', 'telefono', 'email',
        // Domicilio y zona geográfica relacionada.
        'direccion', 'localidad', 'id_zona', 'tipo_cliente', 'condicion_iva',
        // Datos internos de operación/cuenta corriente que el servidor administra.
        'estado', 'saldo', 'creado_por',
    ];

    // Convierte los atributos al leer el modelo; evita que saldo llegue como texto.
    protected $casts = [
        'saldo' => 'float',
    ];

    /**
     * Accessor: al leer $cliente->nombre_completo, combina nombre y apellido/razón social.
     * No crea ni requiere una columna nombre_completo en la base.
     */
    public function getNombreCompletoAttribute(): string
    {
        // trim elimina espacios sobrantes, por ejemplo si uno de los campos está vacío.
        return trim($this->nombre . ' ' . $this->apellido_razon_social);
    }

    /**
     * Accessor usado para mostrar domicilio en una sola línea.
     * localidad se agrega solo cuando tiene un valor.
     */
    public function getDomicilioCompletoAttribute(): string
    {
        return trim($this->direccion . ($this->localidad ? ', ' . $this->localidad : ''));
    }

    /**
     * Scope local reutilizable: Cliente::activo() agrega el filtro estado = activo.
     * Lo usa la búsqueda y CarritoService al seleccionar un cliente.
     */
    public function scopeActivo($query)
    {
        return $query->where('estado', 'activo');
    }
}
//Clientes es del grupo 1
