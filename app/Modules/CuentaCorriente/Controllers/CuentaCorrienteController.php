<?php

namespace App\Modules\CuentaCorriente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CuentaCorriente\Services\AccountSummary;
use App\Modules\CuentaCorriente\Services\ClientPaymentService;
use App\Modules\CuentaCorriente\Services\FinancialReadService;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Rendiciones\Models\RendicionCobro;
use App\Modules\Ventas\Contracts\ClienteGateway;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CuentaCorrienteController extends Controller
{
    // Marca en referencia_externa el cobro de rendición ya aplicado a la cuenta.
    private const PREFIJO_COBRO = 'rendicion_cobro:';

    public function index(ClienteGateway $clientes)
    {
        return view('saldos.saldo', ['clientes' => $this->accounts($clientes)]);
    }

    private function accounts(ClienteGateway $clientes): array
    {
        $listado = $clientes->todos();
        $ids = array_column($listado, 'id');
        $persistidos = DB::table('cuenta_corriente_movimientos')
            ->whereIn('cliente_id', $ids)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $cobrosPendientes = $this->cobrosSinAplicar($ids);

        foreach ($listado as &$cliente) {
            $movimientos = $persistidos->where('cliente_id', $cliente['id'])
                ->map(fn ($movimiento) => [
                    'tipo' => $movimiento->tipo,
                    'fecha' => $movimiento->created_at,
                    'descripcion' => $movimiento->descripcion,
                    'monto' => (float) $movimiento->importe,
                    'venta_id' => $movimiento->venta_id,
                    'referencia_externa' => str_starts_with((string) $movimiento->referencia_externa, self::PREFIJO_COBRO)
                        ? 'Rendición'
                        : $movimiento->referencia_externa,
                ])
                ->all();

            $historical = config('ventas.integracion') === 'mock' ? []
                : app(FinancialReadService::class)->account((int) $cliente['id'])['historical_entries'];
            $cliente['movimientos'] = array_merge($cliente['movimientos'], $movimientos, $historical);
            $cliente['cobros_rendicion'] = $cobrosPendientes->where('id_cliente', $cliente['id'])
                ->map(fn (RendicionCobro $cobro) => [
                    'id' => $cobro->id_rendicion_cobro,
                    'id_rendicion' => $cobro->id_rendicion,
                    'id_venta' => $cobro->id_factura,
                    'monto' => (float) $cobro->monto,
                    'medio_pago' => $cobro->medio_pago,
                ])
                ->values()
                ->all();
        }
        unset($cliente);

        return $listado;
    }

    public function exportPdf(Request $request, ClienteGateway $clientes)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:150'],
            'modoFecha' => ['nullable', 'in:todas,especifica,periodo'], 'fecha' => ['nullable', 'date_format:Y-m-d'],
            'fechaDesde' => ['nullable', 'date_format:Y-m-d'], 'fechaHasta' => ['nullable', 'date_format:Y-m-d', ...($request->filled('fechaDesde') ? ['after_or_equal:fechaDesde'] : [])]]);
        if (($filters['modoFecha'] ?? '') === 'especifica') {
            $request->validate(['fecha' => ['required', 'date_format:Y-m-d']]);
        }
        $rows = [];
        $summary = [];
        foreach ($this->accounts($clientes) as $client) {
            $search = mb_strtolower(trim($filters['search'] ?? ''), 'UTF-8');
            if ($search !== '' && ! str_contains(mb_strtolower($client['nombre'].' '.$client['cuit'], 'UTF-8'), $search)) {
                continue;
            }
            $movements = collect($client['movimientos'])->filter(function ($movement) use ($filters) {
                $date = substr((string) $movement['fecha'], 0, 10);

                return match ($filters['modoFecha'] ?? 'todas') {
                    'especifica' => $date === ($filters['fecha'] ?? ''),
                    'periodo' => (! ($filters['fechaDesde'] ?? '') || $date >= $filters['fechaDesde'])
                        && (! ($filters['fechaHasta'] ?? '') || $date <= $filters['fechaHasta']),
                    default => true,
                };
            })->sortBy('fecha')->values();
            $hasDates = ($filters['modoFecha'] ?? '') === 'especifica'
                || (($filters['modoFecha'] ?? '') === 'periodo' && (($filters['fechaDesde'] ?? '') || ($filters['fechaHasta'] ?? '')));
            if ($hasDates && $movements->isEmpty()) {
                continue;
            }
            $balance = 0;
            foreach ($movements as $movement) {
                $balance += (int) round($movement['monto'] * 100);
                $rows[] = [$client['nombre'], $client['cuit'], substr((string) $movement['fecha'], 0, 10),
                    $movement['descripcion'], $movement['referencia_externa'] ?? '',
                    number_format($movement['monto'], 2, ',', '.'), number_format($balance / 100, 2, ',', '.')];
            }
            $totals = AccountSummary::calculate($client['movimientos'], $filters);
            $summary[] = $client['nombre'].': pagos netos $ '.number_format($totals['pagos_netos'], 2, ',', '.').' · deuda al corte $ '.number_format($totals['deuda_al_corte'], 2, ',', '.').' · deuda actual $ '.number_format($totals['deuda_actual'], 2, ',', '.');
        }
        $pdf = Pdf::loadView('saldos.pdf', ['rows' => $rows, 'summary' => $summary, 'filters' => $filters])
            ->setPaper('a4', 'landscape');

        return $pdf->download('saldos-y-pagos-'.today()->format('Y-m-d').'.pdf');
    }

    public function registrarMovimiento(Request $request, int $cliente, ClienteGateway $clientes): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:pago,nota_credito'],
            'request_key' => ['nullable', 'uuid'],
            'datos_medio_pago' => ['nullable', 'array'],
            'datos_medio_pago.numero_cheque' => ['required_if:metodo_pago,Cheque', 'nullable', 'string', 'max:50'],
            'datos_medio_pago.banco_emisor' => ['required_if:metodo_pago,Cheque', 'nullable', 'string', 'max:100'],
            'datos_medio_pago.fecha_cobro' => ['required_if:metodo_pago,Cheque', 'nullable', 'date_format:Y-m-d'],
            'metodo_pago' => [
                'nullable',
                'required_if:tipo,pago',
                'in:Tarjeta de débito,Tarjeta de crédito,Efectivo,Cheque,Transferencia,QR',
            ],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
        ]);
        $clienteApi = $clientes->buscar($cliente);

        if ($clienteApi === null) {
            return response()->json(['message' => 'El cliente no existe.'], 404);
        }

        if ($datos['tipo'] === 'pago') {
            $method = match ($datos['metodo_pago']) {
                'Efectivo' => 'efectivo', 'Transferencia' => 'transferencia', 'Cheque' => 'cheque', default => 'otro',
            };
            $result = app(ClientPaymentService::class)->register($cliente,
                ['request_key' => $datos['request_key'] ?? (string) Str::uuid(),
                    'monto' => $datos['monto'], 'medio_pago' => $method, 'comprobante' => $datos['metodo_pago'], 'datos_medio_pago' => $datos['datos_medio_pago'] ?? []], $request->user());
            $payment = DB::table('cuenta_corriente_pagos')->where('id', $result['pago_id'])->firstOrFail();
            $entry = DB::table('cuenta_corriente_movimientos')->where('id', $payment->movimiento_cuenta_id)->firstOrFail();

            return response()->json(['movimiento' => $result['repetido'] ? null : ['fecha' => $entry->created_at,
                'descripcion' => $entry->descripcion, 'monto' => (float) $entry->importe, 'venta_id' => null,
                'referencia_externa' => $datos['metodo_pago']], 'saldo' => app(FinancialReadService::class)->account($cliente)['balance']['saldo_contable'],
                'referencia' => $payment->movimiento_cuenta_id, 'repetido' => $result['repetido']], $result['repetido'] ? 200 : 201);
        }

        return DB::transaction(function () use ($cliente, $clienteApi, $datos): JsonResponse {
            DB::table('clientes')->where('id', $cliente)->lockForUpdate()->firstOrFail();
            $movimientos = DB::table('cuenta_corriente_movimientos')
                ->where('cliente_id', $cliente)
                ->lockForUpdate()
                ->get();

            $saldo = config('ventas.integracion') === 'mock'
                ? array_sum(array_column($clienteApi['movimientos'], 'monto')) + (float) $movimientos->sum('importe')
                : app(FinancialReadService::class)->account($cliente)['balance']['saldo_contable'];
            $monto = round((float) $datos['monto'], 2);
            $tipo = $datos['tipo'];

            if ($tipo === 'pago' && ($saldo >= 0 || $monto > abs($saldo))) {
                throw ValidationException::withMessages([
                    'monto' => ['El pago no puede superar la deuda pendiente del cliente.'],
                ]);
            }

            $descripcion = $tipo === 'pago' ? 'Pago de cuenta' : 'Nota de crédito';
            $fecha = now();
            $id = DB::table('cuenta_corriente_movimientos')->insertGetId([
                'cliente_id' => $cliente,
                'venta_id' => null,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'importe' => $monto,
                'referencia_externa' => $datos['metodo_pago'] ?? null,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ]);

            return response()->json([
                'movimiento' => [
                    'fecha' => $fecha->toDateTimeString(),
                    'descripcion' => $descripcion,
                    'monto' => $monto,
                    'venta_id' => null,
                    'referencia_externa' => $datos['metodo_pago'] ?? null,
                ],
                'saldo' => round($saldo + $monto, 2),
                'referencia' => $id,
            ], 201);
        });
    }

    public function registrarCobro(Request $request): JsonResponse
    {
        $id = (int) $request->validate(['id' => ['required', 'integer', 'min:1']])['id'];

        $item = RendicionCobro::query()->findOrFail($id);
        if ($item->estado_revision === 'aplicado' && $item->id_cobro_aplicado) {
            return response()->json(['mensaje' => 'El cobro ya fue aplicado por Rendiciones.', 'repetido' => true]);
        }
        abort(409, 'Revisá y aplicá este cobro desde Rendiciones y Cobros. No se acredita directamente al libro.');
    }

    /**
     * Cobros de rendiciones aprobadas que todavía no impactaron en la cuenta.
     */
    private function cobrosSinAplicar(array $idsClientes): Collection
    {
        $aplicados = $this->cobrosAplicados();

        return RendicionCobro::query()
            ->whereIn('id_cliente', $idsClientes)
            ->whereIn('id_rendicion', Rendicion::query()->where('estado', 'aprobada')->select('id_rendicion'))
            ->orderBy('id_rendicion_cobro')
            ->get()
            ->reject(fn (RendicionCobro $cobro) => $aplicados->contains($cobro->id_rendicion_cobro));
    }

    private function cobrosAplicados(): Collection
    {
        return DB::table('cuenta_corriente_movimientos')
            ->where('referencia_externa', 'like', self::PREFIJO_COBRO.'%')
            ->pluck('referencia_externa')
            ->map(fn (string $referencia) => (int) substr($referencia, strlen(self::PREFIJO_COBRO)));
    }
}
