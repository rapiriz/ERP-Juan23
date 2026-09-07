<?php
namespace App\Cobros\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Caja\Models\CajaMovimiento;
use InvalidArgumentException;
/**
 * Modelo Eloquent: Cobro
 * Tabla: COBRO
 */
class Cobro extends Model
{
    // Constantes de Medios de Pago
    public const MEDIO_EFECTIVO = 'efectivo';
    public const MEDIO_TRANSFERENCIA = 'transferencia';
    public const MEDIO_CHEQUE = 'cheque';
    // Constantes de Estados
    public const ESTADO_REGISTRADO = 'registrado';
    public const ESTADO_ANULADO = 'anulado';
    protected $table = 'COBRO';
    protected $primaryKey = 'id_cobro';
    public $timestamps = false;
    protected $fillable = [
        'id_cliente',
        'id_usuario',
        'fecha',
        'monto_total',
        'medio_pago',
        'comprobante_nro',
        'estado',
        'observaciones',
        'motivo_anulacion',
        'fecha_anulacion',
        'id_usuario_anulacion',
    ];
    protected $casts = [
        'id_cobro' => 'integer',
        'id_cliente' => 'integer',
        'id_usuario' => 'integer',
        'monto_total' => 'float',
        'fecha' => 'datetime',
        'fecha_anulacion' => 'datetime',
        'id_usuario_anulacion' => 'integer',
    ];
    // --- RELACIONES ELOQUENT ---
    /**
     * Ventas/facturas asociadas a este cobro mediante la tabla intermedia COBRO_VENTA.
     */
    public function ventasAsociadas(): HasMany
    {
        return $this->hasMany(CobroVenta::class, 'id_cobro', 'id_cobro');
    }
    /**
     * Movimiento de caja asociado (en caso de cobros en efectivo).
     */
    public function movimientoCaja(): HasOne
    {
        return $this->hasOne(CajaMovimiento::class, 'id_cobro', 'id_cobro');
    }
    // --- MÉTODOS DE DOMINIO Y REGLAS DE NEGOCIO ---
    public function esEfectivo(): bool
    {
        return $this->medio_pago === self::MEDIO_EFECTIVO;
    }
    public function estaAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }
    /**
     * Valida y aplica la anulación sobre la entidad.
     */
    public function anular(string $motivo, int $idUsuario): void
    {
        if ($this->estaAnulado()) {
            throw new InvalidArgumentException("El cobro ya se encuentra anulado.");
        }
        if (empty(trim($motivo))) {
            throw new InvalidArgumentException("El motivo de anulación es obligatorio.");
        }
        $this->estado = self::ESTADO_ANULADO;
        $this->motivo_anulacion = $motivo;
        $this->fecha_anulacion = now();
        $this->id_usuario_anulacion = $idUsuario;
    }
}