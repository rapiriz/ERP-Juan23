<?php

namespace App\Caja\Controllers;

use App\Caja\Services\CajaService;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\Response;
use Illuminate\Http\Request;

/**
 * Controller único para el módulo Caja.
 * Toda la lógica de negocio vive en CajaService — este Controller solo valida
 * el request y traduce el resultado del Service a HTTP.
 */
class CajaController extends Controller
{
    public function __construct(private CajaService $service)
    {
    }

    /**
     * POST /caja/apertura
     */
    public function abrir(Request $request)
    {
        $datos = $request->validate([
            'monto_inicial' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            // TODO: reemplazar por el usuario de la sesión cuando la
            // autenticación esté resuelta (ver SesionMiddleware, pendiente).
            'id_usuario' => ['required', 'integer'],
        ]);

        $resultado = $this->service->abrirCaja(
            $datos['id_usuario'],
            (float) $datos['monto_inicial'],
            $datos['observaciones'] ?? null
        );

        if ($resultado['error'] ?? false) {
            Response::error($resultado['mensaje'], 409);
            return;
        }

        Response::json($resultado, 201);
    }

    /**
     * GET /caja/{fecha} — todas las cajas de ese día (vista admin)
     */
    public function porFecha(string $fecha)
    {
        Response::json($this->service->obtenerCajasDeFecha($fecha));
    }

    /**
     * GET /caja/{fecha}/{id_usuario} — caja puntual de un usuario
     */
    public function porUsuarioYFecha(string $fecha, int $id_usuario)
    {
        $caja = $this->service->obtenerCajaDeUsuarioYFecha($id_usuario, $fecha);

        if ($caja === null) {
            Response::error('No existe una caja para ese usuario en esa fecha.', 404);
            return;
        }

        Response::json($caja);
    }

    /**
     * PATCH /caja/{id}/cerrar
     */
    public function cerrar(Request $request, int $id)
    {
        $datos = $request->validate([
            'monto_final' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'id_usuario' => ['required', 'integer'],
        ]);

        $resultado = $this->service->cerrarCaja(
            $id,
            $datos['id_usuario'],
            (float) $datos['monto_final'],
            $datos['observaciones'] ?? null
        );

        if ($resultado === null) {
            Response::error('Caja no encontrada.', 404);
            return;
        }

        if ($resultado['error'] ?? false) {
            Response::error($resultado['mensaje'], 409);
            return;
        }

        Response::json($resultado);
    }

    /**
     * GET /caja/cierres — historial de cierres (filtrable por id_usuario en query string)
     */
    public function historialCierres(Request $request)
    {
        $idUsuario = $request->query('id_usuario');

        Response::json($this->service->obtenerHistorialCierres($idUsuario !== null ? (int) $idUsuario : null));
    }

    /**
     * GET /caja/cierres/{id}
     */
    public function detalleCierre(int $id)
    {
        $cierre = $this->service->obtenerDetalleCierre($id);

        if ($cierre === null) {
            Response::error('No existe un cierre con ese id.', 404);
            return;
        }

        Response::json($cierre);
    }

    /**
     * POST /caja/movimiento
     */
    public function registrarMovimiento(Request $request)
    {
        $datos = $request->validate([
            'id_caja' => ['required', 'integer'],
            'tipo' => ['required', 'string'],
            'concepto' => ['required', 'string', 'max:200'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'id_usuario' => ['required', 'integer'],
        ]);

        $resultado = $this->service->registrarMovimiento(
            (int) $datos['id_caja'],
            $datos['id_usuario'],
            $datos['tipo'],
            $datos['concepto'],
            (float) $datos['monto']
        );

        if ($resultado === null) {
            Response::error('Caja no encontrada.', 404);
            return;
        }

        if ($resultado['error'] ?? false) {
            Response::error($resultado['mensaje'], 409);
            return;
        }

        Response::json($resultado, 201);
    }

    /**
     * GET /caja/movimiento/{id}
     */
    public function detalleMovimiento(int $id)
    {
        $movimiento = $this->service->obtenerMovimiento($id);

        if ($movimiento === null) {
            Response::error('Movimiento no encontrado.', 404);
            return;
        }

        Response::json($movimiento);
    }

    /**
     * PATCH /caja/movimiento/{id} — solo admin
     * TODO: rol "administrativo" usado en las HU no existe en el enum de
     * USUARIO (admin|vendedor|repartidor) — sin validación de rol real acá
     * todavía porque no hay sesión implementada (ver SesionMiddleware).
     */
    public function modificarMovimiento(Request $request, int $id)
    {
        $datos = $request->validate([
            'concepto' => ['required', 'string', 'max:200'],
            'monto' => ['required', 'numeric', 'min:0.01'],
        ]);

        $resultado = $this->service->modificarMovimiento($id, $datos['concepto'], (float) $datos['monto']);

        if ($resultado === null) {
            Response::error('Movimiento no encontrado.', 404);
            return;
        }

        if ($resultado['error'] ?? false) {
            Response::error($resultado['mensaje'], 409);
            return;
        }

        Response::json($resultado);
    }
}
