<?php

namespace App\Shared\Auth\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\Response;
use App\Shared\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * PROVISORIO — no reemplaza el login real de G1, es un puente para que Caja
 * (y cualquier módulo que quiera sesión real) tenga tokens Sanctum mientras
 * se resuelve a nivel equipo qué hacer con la implementación real de G1
 * (hoy es un sitio PHP tradicional con $_SESSION, no una API JSON).
 *
 * Cuando eso se resuelva, este archivo debería desaparecer o adaptarse.
 */
class DevLoginController extends Controller
{
    public function login(Request $request)
    {
        // Solo disponible en entorno local. Fuera de local responde 404.
        abort_unless(app()->environment('local'), 404);

        $datos = $request->validate([
            'user' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $usuario = Usuario::where('user', $datos['user'])->first();

        if ($usuario === null || $usuario->estado !== 'activo' || !Hash::check($datos['password'], $usuario->password)) {
            Response::error('Credenciales inválidas.', 401);
            return;
        }

        $token = $usuario->createToken('dev-login')->plainTextToken;

        Response::json([
            'token' => $token,
            'id_usuario' => $usuario->id_usuario,
            'user' => $usuario->user,
            'rol' => $usuario->rol,
        ]);
    }
}
