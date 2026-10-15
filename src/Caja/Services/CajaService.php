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

    /**
     * CAJ-01: una sola caja abierta por usuario. Si ya tiene una (de hoy o
     * de días anteriores) se bloquea la apertura y se avisa, indicando si
     * está vencida, para que la cierre antes de abrir otra.
     */
    public function abrirCaja(int $idUsuarioSesion, string $rol, float $montoInicial, ?string $observaciones = null): array
    {
        $ahora = Carbon::now();

        $cajaAbierta = $this->repositorio->buscarCualquierAbiertaDeUsuario($idUsuarioSesion);

        if ($cajaAbierta !== null) {
            $vencida = $cajaAbierta->fecha_hora_vencimiento !== null
                && $ahora->greaterThan($cajaAbierta->fecha_hora_vencimiento);

            return [
                'error' => true,
                'mensaje' => 'Tenés una caja abierta desde el '
                    . $cajaAbierta->fecha_hora_apertura->format('d/m/Y H:i')
                    . ($vencida ? ' que ya venció' : '')
                    . '. Cerrala antes de abrir otra.',
                'id_caja_pendiente' => $cajaAbierta->id_caja,
                'vencida' => $vencida,
            ];
        }

        if ($montoInicial < 0) {
            return ['error' => true, 'mensaje' => 'El monto inicial no puede ser negativo.'];
        }

        $caja = $this->repositorio->crear([
            'id_usuario' => $idUsuarioSesion,
            'fecha' => $ahora->toDateString(),
            'monto_inicial' => $montoInicial,
            'estado' => Caja::ESTADO_ABIERTA,
            'fecha_hora_apertura' => $ahora,
            'fecha_hora_vencimiento' => $this->calcularVencimiento($rol, $ahora),
            'observaciones' => $observaciones,
        ]);

        return $caja->toArray();
    }

    /**
     * Repartidor: 72 h. Resto (Diego/admin): 7 días o fin de mes, lo que
     * ocurra primero.
     */
    private function calcularVencimiento(string $rol, Carbon $apertura): Carbon
    {
        if ($rol === 'repartidor') {
            return $apertura->copy()->addHours(72);
        }

        return $apertura->copy()->addDays(7)->min($apertura->copy()->endOfMonth());
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

    public function listarCajas(?string $estado, ?int $idUsuario): array
    {
        return $this->repositorio->listarFiltradas($estado, $idUsuario)->toArray();
    }

    public function obtenerCaja(int $idCaja): ?array
    {
        return $this->repositorio->buscarPorId($idCaja)?->toArray();
    }

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

    public function obtenerHistorialCierres(?int $idUsuario = null, ?string $desde = null, ?string $hasta = null): array
    {
        return $this->repositorio->listarCierres($idUsuario, $desde, $hasta)->toArray();
    }

    public function obtenerDetalleCierre(int $idCaja): ?array
    {
        return $this->repositorio->buscarCierrePorId($idCaja)?->toArray();
    }

    public function registrarMovimiento(
        int $idCaja,
        int $idUsuarioSesion,
        string $rol,
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
                'mensaje' => 'No se pueden registrar movimientos en una caja cerrada. Abrí una caja nueva para cargarlos.',
            ];
        }

        if (!in_array($tipo, CajaMovimiento::tiposPermitidosPara($rol), true)) {
            return ['error' => true, 'mensaje' => 'Tipo de movimiento no permitido para tu rol.'];
        }

        if ($monto <= 0) {
            return ['error' => true, 'mensaje' => 'El monto debe ser mayor a cero.'];
        }

        $ahora = Carbon::now();

        $movimiento = $this->repositorio->crearMovimiento([
            'id_caja' => $idCaja,
            'id_usuario' => $idUsuarioSesion,
            'fecha' => $ahora->toDateString(),
            'fecha_hora_carga' => $ahora,
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