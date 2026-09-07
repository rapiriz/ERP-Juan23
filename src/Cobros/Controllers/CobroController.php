<?php

namespace App\Cobros\Controllers;

use App\Cobros\Services\CobroService;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\Response;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class CobroController extends Controller
{
    public function __construct(private CobroService $service)
    {
    }

    /**
     * POST /api/v1/cobros
     * Registra un nuevo cobro a cliente.
     */
    public function store(Request $request): void
    {
        $datos = $request->validate([
            'id_cliente' => ['required', 'integer'],
            'id_usuario' => ['required', 'integer'],
            'monto_total' => ['required', 'numeric', 'gt:0'],
            'medio_pago' => ['required', 'string', 'in:efectivo,transferencia,cheque'],
            'comprobante_nro' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string'],
        ]);

        try {
            $resultado = $this->service->registrarCobro($datos);
            Response::json($resultado, 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            Response::error("Error interno al procesar el cobro: " . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/cobros/clientes/{idCliente}/saldo
     * Consulta el saldo pendiente de un cliente antes de cobrar.
     */
    public function saldo(int $idCliente): void
    {
        $saldo = $this->service->obtenerSaldoPendiente($idCliente);
        Response::json([
            'id_cliente' => $idCliente,
            'saldo_pendiente' => $saldo,
        ]);
    }
}
