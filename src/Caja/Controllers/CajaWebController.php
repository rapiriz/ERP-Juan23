<?php

namespace App\Caja\Controllers;

use App\Caja\Services\CajaService;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CajaWebController extends Controller
{
    private const ID_USUARIO_PROVISORIO = 1;

    public function __construct(private CajaService $service)
    {
    }

    public function diaActual()
    {
        $hoy = Carbon::now()->toDateString();
        $caja = $this->service->obtenerCajaDeUsuarioYFecha(self::ID_USUARIO_PROVISORIO, $hoy);

        if ($caja === null) {
            return view('caja.abrir');
        }

        $montoActual = $this->service->obtenerMontoActual($caja['id_caja']);

        return view('caja.dia', ['caja' => $caja, 'montoActual' => $montoActual]);
    }

    /**
     * Muestra una caja puntual por id (usado, por ejemplo, para ir a cerrar
     * una caja de un dia anterior que quedo sin cerrar - CAJ-01).
     */
    public function mostrarCaja(int $id)
    {
        $caja = $this->service->obtenerCaja($id);

        if ($caja === null) {
            abort(404, 'Caja no encontrada');
        }

        $montoActual = $caja['estado'] === 'abierta' ? $this->service->obtenerMontoActual($id) : null;

        return view('caja.dia', ['caja' => $caja, 'montoActual' => $montoActual]);
    }

    public function abrir(Request $request)
    {
        $datos = $request->validate([
            'monto_inicial' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $resultado = $this->service->abrirCaja(
            self::ID_USUARIO_PROVISORIO,
            (float) $datos['monto_inicial'],
            $datos['observaciones'] ?? null
        );

        if ($resultado['error'] ?? false) {
            return back()
                ->with('error', $resultado['mensaje'])
                ->with('id_caja_pendiente', $resultado['id_caja_pendiente'] ?? null);
        }

        $montoFormateado = number_format($resultado['monto_inicial'], 2, ',', '.');

        return redirect()
            ->route('caja.dia')
            ->with('mensaje', "Caja abierta correctamente con monto inicial \${$montoFormateado}.");
    }

    public function registrarMovimiento(Request $request, int $id)
    {
        $datos = $request->validate([
            'tipo' => ['required', 'string'],
            'concepto' => ['required', 'string', 'max:200'],
            'monto' => ['required', 'numeric', 'min:0.01'],
        ]);

        $resultado = $this->service->registrarMovimiento(
            $id,
            self::ID_USUARIO_PROVISORIO,
            $datos['tipo'],
            $datos['concepto'],
            (float) $datos['monto']
        );

        if ($resultado === null) {
            if ($request->wantsJson()) {
                return response()->json(['error' => true, 'mensaje' => 'Caja no encontrada.'], 404);
            }
            abort(404, 'Caja no encontrada');
        }

        if ($resultado['error'] ?? false) {
            if ($request->wantsJson()) {
                return response()->json(['error' => true, 'mensaje' => $resultado['mensaje']], 409);
            }
            return back()->with('error', $resultado['mensaje']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'movimiento' => $resultado,
                'monto_actual' => $this->service->obtenerMontoActual($id),
            ]);
        }

        return redirect()->route('caja.dia')->with('mensaje', 'Movimiento registrado correctamente.');
    }

    public function cerrar(Request $request, int $id)
    {
        $datos = $request->validate([
            'monto_final' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $resultado = $this->service->cerrarCaja(
            $id,
            self::ID_USUARIO_PROVISORIO,
            (float) $datos['monto_final'],
            $datos['observaciones'] ?? null
        );

        if ($resultado === null) {
            abort(404, 'Caja no encontrada');
        }

        if ($resultado['error'] ?? false) {
            return back()->with('error', $resultado['mensaje']);
        }

        return redirect()
            ->route('caja.cierres.show', $id)
            ->with('mensaje', 'Caja cerrada correctamente.');
    }

    public function cierresIndex(Request $request)
    {
        $idUsuario = $request->query('id_usuario');
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        $cierres = $this->service->obtenerHistorialCierres(
            $idUsuario !== null ? (int) $idUsuario : null,
            $desde ?: null,
            $hasta ?: null
        );

        return view('caja.cierres', [
            'cierres' => $cierres,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }

    public function cierresShow(int $id)
    {
        $cierre = $this->service->obtenerDetalleCierre($id);

        if ($cierre === null) {
            abort(404, 'Cierre no encontrado');
        }

        return view('caja.cierre-detalle', ['cierre' => $cierre]);
    }
}