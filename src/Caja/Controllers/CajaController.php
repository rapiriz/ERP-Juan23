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
 * PROVISORIO: Sanctum se usa solo para probar roles hasta que G1 termine el login.
 */
class CajaController extends Controller
{
    public function __construct(private CajaService $service)
    {
    }

    private function esAdmin(Request $request): bool
    {
        return $request->user()->rol === Caja::ROL_ADMIN;
    }

    /**
     * POST /caja/apertura — cada usuario abre su propia caja (el vendedor no maneja caja)
     */
    public function abrir(Request $request)
    {
        if (!Caja::rolManejaCaja($request->user()->rol)) {
            Response::error('Tu rol no maneja caja.', 403);
            return;
        }

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
        $idUsuario = $this->esAdmin($request)
            ? ($datos['id_usuario'] ?? null)
            : $request->user()->id_usuario;

        Response::json($this->service->listarCajas($datos['estado'] ?? null, $idUsuario));
    }

    /**
     * GET /caja/{fecha} — todas las cajas de ese día (solo admin)
     */
    public function porFecha(Request $request, string $fecha)
    {
        if (!$this->esAdmin($request)) {
            Response::error('Solo un administrador puede ver las cajas de otros usuarios.', 403);
            return;
        }

        Response::json($this->service->obtenerCajasDeFecha($fecha));
    }

    /**
     * GET /caja/{fecha}/{id_usuario} — cajas de un usuario en esa fecha
     * (lista: puede haber más de una el mismo día). Admin o el propio usuario.
     */
    public function porUsuarioYFecha(Request $request, string $fecha, int $id_usuario)
    {
        if (!$this->esAdmin($request) && (int) $request->user()->id_usuario !== $id_usuario) {
            Response::error('No podés ver la caja de otro usuario.', 403);
            return;
        }

        Response::json($this->service->obtenerCajasDeUsuarioYFecha($id_usuario, $fecha));
    }

    /**
     * PATCH /caja/{id}/cerrar — la cierra su dueño, sin aprobación.
     * El admin también puede cerrar la caja de un repartidor.
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
            $datos['observaciones'] ?? null,
            $request->user()->rol
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
     * PATCH /caja/{id}/completar-conteo — carga el monto contado de una caja
     * que se cerró sola a las 72 h (dueño, o admin si el dueño es repartidor).
     */
    public function completarConteo(Request $request, int $id)
    {
        $datos = $request->validate([
            'monto_final' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $resultado = $this->service->completarConteoCierreAutomatico(
            $id,
            $request->user()->id_usuario,
            (float) $datos['monto_final'],
            $datos['observaciones'] ?? null,
            $request->user()->rol
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
     * GET /caja/cierres?id_usuario=&desde=YYYY-MM-DD&hasta=YYYY-MM-DD
     * Quien no es admin solo ve sus propios cierres.
     */
    public function historialCierres(Request $request)
    {
        $datos = $request->validate([
            'id_usuario' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $idUsuario = $this->esAdmin($request)
            ? ($datos['id_usuario'] ?? null)
            : $request->user()->id_usuario;

        Response::json($this->service->obtenerHistorialCierres(
            $idUsuario !== null ? (int) $idUsuario : null,
            $datos['desde'] ?? null,
            $datos['hasta'] ?? null
        ));
    }

    /**
     * GET /caja/cierres/{id} — admin o dueño de la caja
     */
    public function detalleCierre(Request $request, int $id)
    {
        $cierre = $this->service->obtenerDetalleCierre($id);

        // 404 también cuando no es suyo, para no revelar que el id existe
        if ($cierre === null
            || (!$this->esAdmin($request) && (int) $cierre['id_usuario'] !== (int) $request->user()->id_usuario)) {
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
     * GET /caja/movimiento/{id} — admin o quien lo cargó
     */
    public function detalleMovimiento(Request $request, int $id)
    {
        $movimiento = $this->service->obtenerMovimiento($id);

        if ($movimiento === null
            || (!$this->esAdmin($request) && (int) $movimiento['id_usuario'] !== (int) $request->user()->id_usuario)) {
            Response::error('Movimiento no encontrado.', 404);
            return;
        }

        Response::json($movimiento);
    }

    /**
     * PATCH /caja/movimiento/{id} — solo admin, y solo si la caja sigue abierta.
     * Guarda el monto anterior, quién lo cambió y cuándo.
     */
    public function modificarMovimiento(Request $request, int $id)
    {
        if (!$this->esAdmin($request)) {
            Response::error('Solo un administrador puede modificar un movimiento.', 403);
            return;
        }

        $datos = $request->validate([
            'concepto' => ['required', 'string', 'max:200'],
            'monto' => ['required', 'numeric', 'min:0.01'],
        ]);

        $resultado = $this->service->modificarMovimiento(
            $id,
            $datos['concepto'],
            (float) $datos['monto'],
            $request->user()->id_usuario
        );

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
