<?php

namespace App\Caja\Controllers;

use App\Caja\Models\Caja;
use App\Caja\Models\CajaMovimiento;
use App\Caja\Services\CajaService;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controller único para el módulo Caja.
 * Toda la lógica de negocio vive en CajaService — este Controller solo valida
 * el request y traduce el resultado del Service a HTTP.
 *
 * Todas las rutas de Caja están protegidas con auth:sanctum (ver routes/api.php),
 * y $request->user() devuelve el App\Shared\Models\Usuario autenticado.
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
        ]);

        $resultado = $this->service->abrirCaja(
            $request->user()->id_usuario,
            $request->user()->rol,
            (float) $datos['monto_inicial'],
            $datos['observaciones'] ?? null
        );

        if ($resultado['error'] ?? false) {
            Response::json($resultado, 409); // incluye id_caja_pendiente y vencida
            return;
        }

        Response::json($resultado, 201);
    }

    /**
     * GET /caja?estado=abierta&id_usuario=3
     */
    public function listar(Request $request)
    {
        $datos = $request->validate([
            'estado' => ['nullable', Rule::in([Caja::ESTADO_ABIERTA, Caja::ESTADO_CERRADA])],
            'id_usuario' => ['nullable', 'integer'],
        ]);

        // Quien no es admin solo ve sus propias cajas
        $idUsuario = $request->user()->rol === 'admin'
            ? ($datos['id_usuario'] ?? null)
            : $request->user()->id_usuario;

        Response::json($this->service->listarCajas($datos['estado'] ?? null, $idUsuario));
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
        ]);

        $resultado = $this->service->cerrarCaja(
            $id,
            $request->user()->id_usuario,
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
            'tipo' => ['required', Rule::in(CajaMovimiento::TIPOS_VALIDOS)],
            'concepto' => ['required', 'string', 'max:200'],
            'monto' => ['required', 'numeric', 'min:0.01'],
        ]);

        $resultado = $this->service->registrarMovimiento(
            (int) $datos['id_caja'],
            $request->user()->id_usuario,
            $request->user()->rol,
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
     */
    public function modificarMovimiento(Request $request, int $id)
    {
        if ($request->user()->rol !== 'admin') {
            Response::error('Solo un administrador puede modificar un movimiento.', 403);
            return;
        }

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