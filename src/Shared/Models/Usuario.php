<?php

namespace App\Shared\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo compartido para autenticación (Sanctum). Apunta a la tabla USUARIO
 * real del proyecto, NO a la tabla `users` que trae Laravel por defecto.
 *
 * Vive en Shared porque cualquier módulo que necesite saber "quién está
 * haciendo este request" lo va a usar, no es exclusivo de Caja.
 *
 * PROVISORIO: cuando G1 tenga resuelto su propio sistema de login (hoy es un
 * sitio PHP tradicional con $_SESSION, incompatible con esto — ver discusión
 * pendiente en equipo), este modelo y el login de abajo deberían
 * reemplazarse o conciliarse con lo que ellos definan.
 */
class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'USUARIO';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $fillable = [
        'user',
        'password',
        'rol',
        'estado',
    ];

    protected $hidden = [
        'password',
    ];

    public function getAuthPassword()
    {
        return $this->password;
    }
}