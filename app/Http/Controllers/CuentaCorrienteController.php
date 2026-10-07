<?php

namespace App\Http\Controllers;

use App\Services\MockClienteApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CuentaCorrienteController extends Controller
{
    public function index(MockClienteApi $clientes)
    {
        $listado = $clientes->todos();
        $ids = array_column($listado, 'id');
        $persistidos = DB::table('cuenta_corriente_movimientos')
            ->whereIn('cliente_id', $ids)
            ->orderBy('created_at')
            ->get();

        foreach ($listado as &$cliente) {
            $movimientos = $persistidos->where('cliente_id', $cliente['id'])
                ->map(fn ($movimiento) => [
                    'fecha' => $movimiento->created_at,
                    'descripcion' => $movimiento->descripcion,
                    'monto' => (float) $movimiento->importe,
                    'venta_id' => $movimiento->venta_id,
                ])
                ->all();

            $cliente['movimientos'] = array_merge($cliente['movimientos'], $movimientos);
        }
        unset($cliente);

        return view('saldo', ['clientes' => $listado]);
    }

    public function pagar(Request $request, int $cliente, MockClienteApi $clientes): JsonResponse
    {
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ]);
        $clienteApi = $clientes->buscar($cliente);

        if ($clienteApi === null) {
            return response()->json(['message' => 'El cliente no existe.'], 404);
        }

        return DB::transaction(function () use ($cliente, $clienteApi, $datos): JsonResponse {
            $movimientos = DB::table('cuenta_corriente_movimientos')
                ->where('cliente_id', $cliente)
                ->lockForUpdate()
                ->get();

            $saldo = array_sum(array_column($clienteApi['movimientos'], 'monto'))
                + (float) $movimientos->sum('importe');
            $monto = round((float) $datos['monto'], 2);

            if ($saldo >= 0 || $monto > abs($saldo)) {
                throw ValidationException::withMessages([
                    'monto' => ['El pago no puede superar la deuda pendiente del cliente.'],
                ]);
            }

            $id = DB::table('cuenta_corriente_movimientos')->insertGetId([
                'cliente_id' => $cliente,
                'venta_id' => null,
                'tipo' => 'pago',
                'descripcion' => 'Pago parcial de cuenta',
                'importe' => $monto,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'movimiento' => [
                    'fecha' => now()->toDateString(),
                    'descripcion' => 'Pago parcial de cuenta',
                    'monto' => $monto,
                    'venta_id' => null,
                ],
                'saldo' => round($saldo + $monto, 2),
                'referencia' => $id,
            ], 201);
        });
    }
}
