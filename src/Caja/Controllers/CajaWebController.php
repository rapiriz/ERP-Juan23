<?php

namespace App\Caja\Controllers;

use App\Caja\Services\CajaService;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Controller para las VISTAS (Blade) del modulo Caja.
 * Distinto de CajaController (API con Sanctum) - usa el mismo CajaService,
 * pero via sesion web normal, sin token.
 *
 * TODO: reemplazar el id_usuario fijo por el usuario real de sesion cuando
 * el Login (G1) este integrado. Mismo criterio provisorio que se uso en
 * ConciliacionWebController.
 */
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

        return view('caja.dia', ['caja' => $caja]);
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
            abort(404, 'Caja no encontrada');
        }

        if ($resultado['error'] ?? false) {
            return back()->with('error', $resultado['mensaje']);
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