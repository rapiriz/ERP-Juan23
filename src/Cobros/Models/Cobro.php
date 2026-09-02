<?php

namespace Cobros\Models;

use InvalidArgumentException;

/**
 * Clase Cobro — Modelo de Dominio
 * 
 * Representa la entidad de un Cobro en el sistema ERP Distribuidora.
 * Contiene los atributos del cobro y encuadra las reglas de validación básicas
 * de dominio (montos positivos, medios de pago válidos, identificadores requeridos).
 */
class Cobro
{
    public const MEDIO_EFECTIVO = 'efectivo';
    public const MEDIO_TRANSFERENCIA = 'transferencia';
    public const MEDIO_CHEQUE = 'cheque';

    public const ESTADO_REGISTRADO = 'registrado';
    public const ESTADO_ANULADO = 'anulado';

    private ?int $idCobro;
    private int $idCliente;
    private int $idUsuario;
    private ?string $fecha;
    private float $montoTotal;
    private string $medioPago;
    private ?string $comprobanteNro;
    private string $estado;
    private ?string $observaciones;
    private ?string $motivoAnulacion;
    private ?string $fechaAnulacion;
    private ?int $idUsuarioAnulacion;

    /**
     * @var array Lista de ítems/facturas asociadas al cobro.
     * Cada ítem es un array: ['id_venta' => int, 'monto_aplicado' => float]
     */
    private array $ventasAsociadas;

    public function __construct(
        int $idCliente,
        int $idUsuario,
        float $montoTotal,
        string $medioPago = self::MEDIO_EFECTIVO,
        ?string $comprobanteNro = null,
        ?string $observaciones = null,
        array $ventasAsociadas = [],
        ?int $idCobro = null,
        ?string $fecha = null,
        string $estado = self::ESTADO_REGISTRADO,
        ?string $motivoAnulacion = null,
        ?string $fechaAnulacion = null,
        ?int $idUsuarioAnulacion = null
    ) {
        $this->setIdCliente($idCliente);
        $this->setIdUsuario($idUsuario);
        $this->setMontoTotal($montoTotal);
        $this->setMedioPago($medioPago);
        $this->setEstado($estado);

        $this->idCobro = $idCobro;
        $this->fecha = $fecha;
        $this->comprobanteNro = $comprobanteNro;
        $this->observaciones = $observaciones;
        $this->ventasAsociadas = $ventasAsociadas;
        $this->motivoAnulacion = $motivoAnulacion;
        $this->fechaAnulacion = $fechaAnulacion;
        $this->idUsuarioAnulacion = $idUsuarioAnulacion;
    }

    // --- GETTERS Y SETTERS CON VALIDACIÓN DE DOMINIO ---

    public function getIdCobro(): ?int
    {
        return $this->idCobro;
    }

    public function setIdCobro(?int $idCobro): void
    {
        $this->idCobro = $idCobro;
    }

    public function getIdCliente(): int
    {
        return $this->idCliente;
    }

    public function setIdCliente(int $idCliente): void
    {
        if ($idCliente <= 0) {
            throw new InvalidArgumentException("El ID de cliente debe ser un entero positivo.");
        }
        $this->idCliente = $idCliente;
    }

    public function getIdUsuario(): int
    {
        return $this->idUsuario;
    }

    public function setIdUsuario(int $idUsuario): void
    {
        if ($idUsuario <= 0) {
            throw new InvalidArgumentException("El ID de usuario debe ser un entero positivo.");
        }
        $this->idUsuario = $idUsuario;
    }

    public function getFecha(): ?string
    {
        return $this->fecha;
    }

    public function setFecha(?string $fecha): void
    {
        $this->fecha = $fecha;
    }

    public function getMontoTotal(): float
    {
        return $this->montoTotal;
    }

    public function setMontoTotal(float $montoTotal): void
    {
        if ($montoTotal <= 0) {
            throw new InvalidArgumentException("El monto total del cobro debe ser mayor a cero.");
        }
        $this->montoTotal = round($montoTotal, 2);
    }

    public function getMedioPago(): string
    {
        return $this->medioPago;
    }

    public function setMedioPago(string $medioPago): void
    {
        $mediosValidos = [self::MEDIO_EFECTIVO, self::MEDIO_TRANSFERENCIA, self::MEDIO_CHEQUE];
        if (!in_array($medioPago, $mediosValidos, true)) {
            throw new InvalidArgumentException("Medio de pago inválido. Valores permitidos: " . implode(', ', $mediosValidos));
        }
        $this->medioPago = $medioPago;
    }

    public function getComprobanteNro(): ?string
    {
        return $this->comprobanteNro;
    }

    public function setComprobanteNro(?string $comprobanteNro): void
    {
        $this->comprobanteNro = $comprobanteNro;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): void
    {
        $estadosValidos = [self::ESTADO_REGISTRADO, self::ESTADO_ANULADO];
        if (!in_array($estado, $estadosValidos, true)) {
            throw new InvalidArgumentException("Estado de cobro inválido. Valores permitidos: " . implode(', ', $estadosValidos));
        }
        $this->estado = $estado;
    }

    public function getObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function setObservaciones(?string $observaciones): void
    {
        $this->observaciones = $observaciones;
    }

    public function getVentasAsociadas(): array
    {
        return $this->ventasAsociadas;
    }

    public function setVentasAsociadas(array $ventasAsociadas): void
    {
        $this->ventasAsociadas = $ventasAsociadas;
    }

    public function getMotivoAnulacion(): ?string
    {
        return $this->motivoAnulacion;
    }

    public function setMotivoAnulacion(?string $motivoAnulacion): void
    {
        $this->motivoAnulacion = $motivoAnulacion;
    }

    public function getFechaAnulacion(): ?string
    {
        return $this->fechaAnulacion;
    }

    public function setFechaAnulacion(?string $fechaAnulacion): void
    {
        $this->fechaAnulacion = $fechaAnulacion;
    }

    public function getIdUsuarioAnulacion(): ?int
    {
        return $this->idUsuarioAnulacion;
    }

    public function setIdUsuarioAnulacion(?int $idUsuarioAnulacion): void
    {
        $this->idUsuarioAnulacion = $idUsuarioAnulacion;
    }

    // --- MÉTODOS DE SERIALIZACIÓN ---

    /**
     * Convierte la entidad a un arreglo asociativo (útil para respuestas JSON).
     */
    public function toArray(): array
    {
        return [
            'id_cobro' => $this->idCobro,
            'id_cliente' => $this->idCliente,
            'id_usuario' => $this->idUsuario,
            'fecha' => $this->fecha,
            'monto_total' => $this->montoTotal,
            'medio_pago' => $this->medioPago,
            'comprobante_nro' => $this->comprobanteNro,
            'estado' => $this->estado,
            'observaciones' => $this->observaciones,
            'ventas_asociadas' => $this->ventasAsociadas,
            'motivo_anulacion' => $this->motivoAnulacion,
            'fecha_anulacion' => $this->fechaAnulacion,
            'id_usuario_anulacion' => $this->idUsuarioAnulacion,
        ];
    }

    /**
     * Instancia un objeto Cobro a partir de un arreglo de datos (útil para mapear consultas SQL).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id_cliente'] ?? 0),
            (int) ($data['id_usuario'] ?? 0),
            (float) ($data['monto_total'] ?? 0),
            $data['medio_pago'] ?? self::MEDIO_EFECTIVO,
            $data['comprobante_nro'] ?? null,
            $data['observaciones'] ?? null,
            $data['ventas_asociadas'] ?? [],
            isset($data['id_cobro']) ? (int) $data['id_cobro'] : null,
            $data['fecha'] ?? null,
            $data['estado'] ?? self::ESTADO_REGISTRADO,
            $data['motivo_anulacion'] ?? null,
            $data['fecha_anulacion'] ?? null,
            isset($data['id_usuario_anulacion']) ? (int) $data['id_usuario_anulacion'] : null
        );
    }
}
