<?php
namespace App\Cobros\Repositories;
use App\Cobros\Models\Cobro;
use App\Cobros\Models\CobroVenta;
use App\Caja\Models\CajaMovimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Collection;
class CobroRepository
{
    /**
     * Inserta un nuevo cobro en la tabla COBRO.
     */
    public function crear(array $datos): Cobro
    {
        return Cobro::create([
            'id_cliente' => $datos['id_cliente'],
            'id_usuario' => $datos['id_usuario'],
            'fecha' => $datos['fecha'] ?? now(),
            'monto_total' => $datos['monto_total'],
            'medio_pago' => $datos['medio_pago'] ?? Cobro::MEDIO_EFECTIVO,
            'comprobante_nro' => $datos['comprobante_nro'] ?? null,
            'estado' => Cobro::ESTADO_REGISTRADO,
            'observaciones' => $datos['observaciones'] ?? null,
        ]);
    }
    /**
     * Busca un cobro por su ID con sus relaciones.
     */
    public function buscarPorId(int $idCobro): ?Cobro
    {
        return Cobro::with(['ventasAsociadas', 'movimientoCaja'])->find($idCobro);
    }
    /**
     * Asocia una venta/factura saldada al cobro en la tabla COBRO_VENTA.
     */
    public function asociarVenta(int $idCobro, int $idVenta, float $montoAplicado): CobroVenta
    {
        return CobroVenta::create([
            'id_cobro' => $idCobro,
            'id_venta' => $idVenta,
            'monto_aplicado' => $montoAplicado,
        ]);
    }
    /**
     * Obtiene las ventas no pagadas de un cliente desde la tabla VENTA.
     */
    public function obtenerVentasPendientesCliente(int $idCliente): array
    {
        if (!Schema::hasTable('VENTA')) {
            return [];
        }
        return DB::table('VENTA')
            ->where('id_cliente', $idCliente)
            ->where('pagado', false)
            ->orderBy('fecha', 'asc')
            ->get()
            ->toArray();
    }
    /**
     * Actualiza el estado de una venta a pagada.
     */
    public function marcarVentaComoPagada(int $idVenta): void
    {
        if (!Schema::hasTable('VENTA')) {
            return;
        }
        DB::table('VENTA')
            ->where('id_venta', $idVenta)
            ->update(['pagado' => true]);
    }
    /**
     * Registra un ingreso de efectivo en la tabla CAJA_MOVIMIENTO asociado a la caja abierta.
     */
    public function crearMovimientoCaja(array $datos): CajaMovimiento
    {
        $idUsuario = (int) ($datos['id_usuario'] ?? 1);
        $caja = \App\Caja\Models\Caja::where('id_usuario', $idUsuario)
            ->where('estado', \App\Caja\Models\Caja::ESTADO_ABIERTA)
            ->first();

        if ($caja === null) {
            $caja = \App\Caja\Models\Caja::where('estado', \App\Caja\Models\Caja::ESTADO_ABIERTA)
                ->orderByDesc('fecha_hora_apertura')
                ->first();
        }

        return CajaMovimiento::create([
            'id_caja' => $caja ? $caja->id_caja : null,
            'id_cobro' => $datos['id_cobro'] ?? null,
            'fecha' => now()->toDateString(),
            'fecha_hora_carga' => now(),
            'monto' => $datos['monto'],
            'tipo' => CajaMovimiento::TIPO_COBRO,
            'concepto' => $datos['concepto'] ?? 'Cobro a cliente',
            'id_usuario' => $caja ? $caja->id_usuario : $idUsuario,
        ]);
    }
    /**
     * Valida si un cliente existe y está activo en la tabla CLIENTE.
     */
    public function clienteEstaActivo(int $idCliente): bool
    {
        if (!Schema::hasTable('CLIENTE')) {
            return $idCliente > 0;
        }
        return DB::table('CLIENTE')
            ->where('id_cliente', $idCliente)
            ->where('estado', 'activo')
            ->exists();
    }
}