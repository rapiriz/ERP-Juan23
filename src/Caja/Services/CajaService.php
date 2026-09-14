<?php

namespace App\Caja\Services;

use App\Caja\Models\Caja;
use App\Caja\Models\CajaMovimiento;
use App\Caja\Repositories\CajaRepository;
use Illuminate\Support\Carbon;

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

    public function obtenerCajasDeFecha(string $fecha): array
    {
        return $this->repositorio->listarPorFecha($fecha)->toArray();
    }

    public function obtenerCajaDeUsuarioYFecha(int $idUsuario, string $fecha): ?array
    {
        $caja = $this->repositorio->buscarPorUsuarioYFecha($idUsuario, $fecha);

        return $caja?->toArray();
    }

    /**
     * Monto inicial + saldo neto de movimientos (ingresos - egresos).
     * Devuelve null si la caja no existe.
     */
    public function obtenerMontoActual(int $idCaja): ?float
    {
        $caja = $this->repositorio->buscarPorId($idCaja);

        if ($caja === null) {
            return null;
        }

        return (float) $caja->monto_inicial + $this->repositorio->calcularSaldoMovimientos($idCaja);
    }

    public function cerrarCaja(int $idCaja, int $idUsuarioSesion, float $montoFinal, ?string $observaciones = null): ?array
    {
        $caja = $this->repositorio->buscarPorId($idCaja);

        if ($caja === null) {
            return null;
        }

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
            return [
                'error' => true,
                'mensaje' => 'No se pueden registrar movimientos en una caja cerrada. Los movimientos pendientes se cargan en la caja del día siguiente.',
            ];
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