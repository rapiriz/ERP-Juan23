<?php

namespace App\Support;

/**
 * UsuarioActual  —  Devuelve el id del usuario que está operando el sistema.
 *
 * ¿POR QUÉ EXISTE?
 *   Casi todas las tablas de ventas guardan "quién lo hizo"
 *   (venta.id_usuario, cobro.id_usuario, movimiento_stock.id_usuario,
 *   caja_movimiento.id_usuario, log_auditoria.id_usuario...).
 *
 *   El login de este proyecto usa la tabla propia `usuario` (NO la tabla
 *   `users` estándar de Laravel), por lo tanto Auth::id() NO sirve acá.
 *   Centralizamos la respuesta en UN solo lugar para que, cuando el equipo
 *   del módulo de login termine, se cambie ESTE archivo y nada más.
 *
 * ESTADO ACTUAL (provisorio):
 *   - Si el login guardó el id en la sesión como 'usuario_id', se usa ese.
 *   - Si no hay sesión de login, se usa config('ventas.usuario_demo_id') (admin).
 *
 * DEPENDENCIA CON EL MÓDULO DE LOGIN:
 *   Al iniciar sesión correctamente, el login debe hacer:
 *       session(['usuario_id' => $usuario->id_usuario]);
 *   Cuando eso exista, se puede quitar el fallback de abajo.
 *
 * LO USAN: VentaController, ClienteController, CajaController.
 *
 * IMPORTANCIA Y CONEXIONES:
 *   Evita que cada controller resuelva el operador de una manera distinta.
 *   VentaController lo pasa a VentaService para registrar venta, cobro, stock
 *   y auditoría; ClienteController lo registra como creador; CajaController
 *   lo usa para filtrar movimientos y ventas del arqueo.
 *
 * FRONT:
 *   El navegador nunca debe enviar el ID del operador en el body, query string
 *   o un campo oculto. Este helper lo resuelve del lado servidor desde la sesión.
 *   El front solo envía los datos propios de la operación y conserva la cookie
 *   de sesión en las rutas web.
 *
 * SEGURIDAD / ESTADO ACTUAL:
 *   El fallback fijo al usuario demo es temporal y no autentica a nadie ni
 *   comprueba que el usuario exista o esté activo. Antes de producción, el login
 *   debe establecer usuario_id en sesión y las rutas deben exigir autenticación;
 *   luego se debe retirar el fallback demo.
 */
class UsuarioActual
{
    /**
     * Devuelve el operador asociado a la petición actual.
     * Prioriza el ID de sesión del login y usa la configuración demo solo si falta.
     */
    public static function id(): int
    {
        // ?? usa el valor de sesión salvo que sea null; si no existe, lee
        // ventas.usuario_demo_id. El cast normaliza el resultado a entero.
        return (int) (session('usuario_id') ?? config('ventas.usuario_demo_id'));
    }
}
