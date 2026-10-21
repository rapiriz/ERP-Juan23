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
     * El vendedor no maneja caja: no puede abrirla.
     */
    public function abrirCaja(int $idUsuarioSesion, string $rol, float $montoInicial, ?string $observaciones = null): array
    {
        if (!Caja::rolManejaCaja($rol)) {
            return ['error' => true, 'mensaje' => 'Tu rol no maneja caja.'];
        }

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
     * Repartidor: 72 h. Admin (Diego): 7 días o fin de mes, lo que
     * ocurra primero.
     */
    private function calcularVencimiento(string $rol, Carbon $apertura): Carbon
    {
        if ($rol === Caja::ROL_REPARTIDOR) {
            return $apertura->copy()->addHours(72);
        }

        return $apertura->copy()->addDays(7)->min($apertura->copy()->endOfMonth());
    }

    public function obtenerCajasDeFecha(string $fecha): array
    {
        return $this->repositorio->listarPorFecha($fecha)->toArray();
    }

    /**
     * Lista (puede haber varias cajas del mismo usuario el mismo día).
     */
    public function obtenerCajasDeUsuarioYFecha(int $idUsuario, string $fecha): array
    {
        return $this->repositorio->listarPorUsuarioYFecha($idUsuario, $fecha)->toArray();
    }

    public function obtenerCajaAbiertaDeUsuario(int $idUsuario): ?array
    {
        return $this->repositorio->buscarCualquierAbiertaDeUsuario($idUsuario)?->toArray();
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

        return round((float) $caja->monto_inicial + $this->repositorio->calcularSaldoMovimientos($idCaja), 2);
    }

    /**
     * Cierra la caja. Solo la cierra su dueño (repartidor o Diego), sin
     * aprobación. Guarda el monto esperado y la diferencia (final - esperado).
     */
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

        $saldoEsperado = round((float) $caja->monto_inicial + $this->repositorio->calcularSaldoMovimientos($idCaja), 2);
        $diferencia = round($montoFinal - $saldoEsperado, 2);

        $caja->monto_final = $montoFinal;
        $caja->monto_esperado = $saldoEsperado;
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

    /**
     * Los movimientos con la caja vencida NO se bloquean (el repartidor
     * puede estar en ruta). $idCobro es opcional: lo usa Cobros para
     * dejar vinculado el movimiento con el cobro que lo generó.
     */
    public function registrarMovimiento(
        int $idCaja,
        int $idUsuarioSesion,
        string $rol,
        string $tipo,
        string $concepto,
        float $monto,
        ?int $idCobro = null
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
            'id_cobro' => $idCobro,
            'id_usuario' => $idUsuarioSesion,
            'fecha' => $ahora->toDateString(),
            'fecha_hora_carga' => $ahora,
            'tipo' => $tipo,
            'concepto' => $concepto,
            'monto' => round($monto, 2),
        ]);

        return $movimiento->toArray();
    }

    /**
     * Cobro en efectivo: entra a la caja abierta del usuario que cobra,
     * como movimiento de tipo 'cobro'. Si el usuario específico no tiene caja abierta,
     * busca cualquier caja abierta activa en el sistema (ej. caja de tesorería/admin).
     */
    public function registrarCobroEnEfectivo(
        int $idUsuario,
        string $rol,
        float $monto,
        string $concepto,
        ?int $idCobro = null
    ): ?array {
        $caja = $this->repositorio->buscarCualquierAbiertaDeUsuario($idUsuario);

        // Fallback resiliente: buscar cualquier caja abierta activa en el sistema
        if ($caja === null) {
            $caja = Caja::where('estado', Caja::ESTADO_ABIERTA)->orderByDesc('fecha_hora_apertura')->first();
            if ($caja !== null) {
                $idUsuario = $caja->id_usuario;
                $rol = Caja::ROL_ADMIN;
            }
        }

        if ($caja === null) {
            return ['error' => true, 'mensaje' => 'No hay una caja abierta activa en el sistema. Abrila antes de cobrar en efectivo.'];
        }

        return $this->registrarMovimiento(
            $caja->id_caja,
            $idUsuario,
            $rol,
            CajaMovimiento::TIPO_COBRO,
            $concepto,
            $monto,
            $idCobro
        );
    }

    public function obtenerMovimiento(int $idMovimiento): ?array
    {
        return $this->repositorio->buscarMovimientoPorId($idMovimiento)?->toArray();
    }

    /**
     * Solo se puede modificar un movimiento de una caja abierta. Guarda el
     * monto anterior, quién lo cambió y cuándo (auditoría).
     */
    public function modificarMovimiento(int $idMovimiento, string $concepto, float $monto, int $idUsuarioSesion): ?array
    {
        $movimiento = $this->repositorio->buscarMovimientoPorId($idMovimiento);

        if ($movimiento === null) {
            return null;
        }

        if ($monto <= 0) {
            return ['error' => true, 'mensaje' => 'El monto debe ser mayor a cero.'];
        }

        if ($movimiento->id_caja !== null) {
            $caja = $this->repositorio->buscarPorId((int) $movimiento->id_caja);

            if ($caja !== null && !$caja->estaAbierta()) {
                return ['error' => true, 'mensaje' => 'No se puede modificar un movimiento de una caja cerrada.'];
            }
        }

        $movimiento->monto_anterior = $movimiento->monto;
        $movimiento->concepto = $concepto;
        $movimiento->monto = round($monto, 2);
        $movimiento->id_usuario_modificacion = $idUsuarioSesion;
        $movimiento->fecha_hora_modificacion = Carbon::now();

        return $this->repositorio->guardarMovimiento($movimiento)->toArray();
    }
}