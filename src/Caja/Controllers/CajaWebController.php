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
            return back()->with('error', $resultado['mensaje']);
        }

        return redirect()->route('caja.dia')->with('mensaje', 'Caja abierta correctamente.');
    }

    /**
     * Si la request pide JSON (fetch con header Accept: application/json),
     * responde con el movimiento creado y el monto actual recalculado, sin
     * redirigir — así el frontend actualiza la pantalla sin recargar.
     * Si no, se comporta como antes (redirect clásico, fallback sin JS).
     */
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
        $cierres = $this->service->obtenerHistorialCierres($idUsuario !== null ? (int) $idUsuario : null);

        return view('caja.cierres', ['cierres' => $cierres]);
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