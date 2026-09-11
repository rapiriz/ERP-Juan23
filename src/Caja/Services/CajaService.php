<?php

namespace App\Caja\Services;

use App\Caja\Models\Caja;
use App\Caja\Models\CajaMovimiento;
use App\Caja\Repositories\CajaRepository;
use Illuminate\Support\Carbon;

/**
 * Reglas de negocio confirmadas con el equipo (ver 06-diseno-modulo-caja.md):
 * - Una caja por usuario por día.
 * - Cada usuario gestiona ÚNICAMENTE su propia caja (sin excepción de admin).
 *
 * Los errores de negocio se devuelven como array ['error' => true, 'mensaje' => ...]
 * en vez de excepciones, siguiendo el mismo patrón que ConciliacionService (ver
 * conciliarAutomatico/conciliarManual/cerrarPeriodo), para que el Controller
 * los traduzca a Response::error() de la misma forma en todos los módulos.
 */
class CajaService
{
    public function __construct(private CajaRepository $repositorio)
    {
    }

    public function abrirCaja(int $idUsuarioSesion, float $montoInicial, ?string $observaciones = null): array
    {
        $hoy = Carbon::now()->toDateString();

        if ($this->repositorio->buscarAbiertaDeUsuario($idUsuarioSesion, $hoy) !== null) {
            return ['error' => true, 'mensaje' => 'Ya existe una caja abierta hoy para este usuario.'];
        }

        if ($montoInicial < 0) {
            return ['error' => true, 'mensaje' => 'El monto inicial no puede ser negativo.'];
        }

        $caja = $this->repositorio->crear([
            'id_usuario' => $idUsuarioSesion,
            'fecha' => $hoy,
            'monto_inicial' => $montoInicial,
            'estado' => Caja::ESTADO_ABIERTA,
            'fecha_hora_apertura' => Carbon::now(),
            'observaciones' => $observaciones,
        ]);

        return $caja->toArray();
    }

    /** Vista admin: todas las cajas (de todo el personal) de una fecha. */
    public function obtenerCajasDeFecha(string $fecha): array
    {
        return $this->repositorio->listarPorFecha($fecha)->toArray();
    }

    public function obtenerCajaDeUsuarioYFecha(int $idUsuario, string $fecha): ?array
    {
        $caja = $this->repositorio->buscarPorUsuarioYFecha($idUsuario, $fecha);

        return $caja?->toArray();
    }

    public function cerrarCaja(int $idCaja, int $idUsuarioSesion, float $montoFinal, ?string $observaciones = null): ?array
    {
        $caja = $this->repositorio->buscarPorId($idCaja);

        if ($caja === null) {
            return null;
        }

        // Regla confirmada por el equipo: cada usuario gestiona únicamente su
        // propia caja. Un admin NO puede cerrar la caja de otro usuario.
        if (!$caja->perteneceA($idUsuarioSesion)) {
            return ['error' => true, 'mensaje' => 'No podés cerrar la caja de otro usuario.'];
        }

        if (!$caja->estaAbierta()) {
            return ['error' => true, 'mensaje' => 'La caja ya está cerrada.'];
        }

        $saldoMovimientos = $this->repositorio->calcularSaldoMovimientos($idCaja);
        $saldoEsperado = (float) $caja->monto_inicial + $saldoMovimientos;
        $diferencia = $montoFinal - $saldoEsperado;

        $caja->monto_final = $montoFinal;
        $caja->diferencia = $diferencia;
        $caja->estado = Caja::ESTADO_CERRADA;
        $caja->id_usuario_cierre = $idUsuarioSesion;
        $caja->fecha_hora_cierre = Carbon::now();

        if ($observaciones !== null) {
            $caja->observaciones = $observaciones;
        }

        return $this->repositorio->guardar($caja)->toArray();
    }

    public function obtenerHistorialCierres(?int $idUsuario = null): array
    {
        return $this->repositorio->listarCierres($idUsuario)->toArray();
    }

    public function obtenerDetalleCierre(int $idCaja): ?array
    {
        return $this->repositorio->buscarCierrePorId($idCaja)?->toArray();
    }

    /**
     * Registrar movimiento manual (ingreso/egreso) — HU CAJ-02.
     *
     * NOTA (no confirmado por el equipo): asumo que solo el dueño de la caja
     * puede cargar movimientos manuales en ella. Un movimiento tipo 'cobro'
     * probablemente lo va a generar el módulo de Cobros vía integración
     * interna, no un usuario tipeando un form — ese caso puede necesitar otro
     * camino más adelante. TODO: confirmar con el equipo.
     */
    public function registrarMovimiento(
        int $idCaja,
        int $idUsuarioSesion,
        string $tipo,
        string $concepto,
        float $monto
    ): ?array {
        $caja = $this->repositorio->buscarPorId($idCaja);

        if ($caja === null) {
            return null;
        }

        if (!$caja->perteneceA($idUsuarioSesion)) {
            return ['error' => true, 'mensaje' => 'No podés registrar movimientos en la caja de otro usuario.'];
        }

        if (!$caja->estaAbierta()) {
            return ['error' => true, 'mensaje' => 'No se pueden registrar movimientos en una caja cerrada.'];
        }

        if (!in_array($tipo, CajaMovimiento::TIPOS_VALIDOS, true)) {
            return ['error' => true, 'mensaje' => 'Tipo de movimiento inválido.'];
        }

        if ($monto <= 0) {
            return ['error' => true, 'mensaje' => 'El monto debe ser mayor a cero.'];
        }

        $movimiento = $this->repositorio->crearMovimiento([
            'id_caja' => $idCaja,
            'id_usuario' => $idUsuarioSesion,
            'fecha' => Carbon::now()->toDateString(),
            'tipo' => $tipo,
            'concepto' => $concepto,
            'monto' => $monto,
        ]);

        return $movimiento->toArray();
    }

    public function obtenerMovimiento(int $idMovimiento): ?array
    {
        return $this->repositorio->buscarMovimientoPorId($idMovimiento)?->toArray();
    }

    /**
     * Modificar movimiento manual — "solo admin" según el endpoint original.
     * La verificación de rol admin se hace en el Controller, no acá.
     */
    public function modificarMovimiento(int $idMovimiento, string $concepto, float $monto): ?array
    {
        $movimiento = $this->repositorio->buscarMovimientoPorId($idMovimiento);

        if ($movimiento === null) {
            return null;
        }

        if ($monto <= 0) {
            return ['error' => true, 'mensaje' => 'El monto debe ser mayor a cero.'];
        }

        $movimiento->concepto = $concepto;
        $movimiento->monto = $monto;

        return $this->repositorio->guardarMovimiento($movimiento)->toArray();
    }
}
