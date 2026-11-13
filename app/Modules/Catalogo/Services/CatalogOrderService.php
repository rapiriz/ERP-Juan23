<?php

namespace App\Modules\Catalogo\Services;

use App\Modules\Login\Models\User;
use App\Modules\Ventas\Controllers\VentaController;
use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Contracts\ProductoGateway;
use App\Modules\Ventas\Contracts\StockGateway;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Solicitudes públicas: no reservan, no venden ni acreditan deuda automáticamente. */
class CatalogOrderService
{
    public function submit(array $input): array
    {
        $data = validator($input, ['request_key' => 'required|uuid', 'receipt_token' => 'required|string|regex:/^[a-f0-9]{64}$/D',
            'nombre' => 'required|string|min:3|max:160', 'email' => 'required|email:rfc|max:160',
            'telefono' => ['required','string','max:30','regex:/^\+?[0-9 ()-]{7,30}$/D'],
            'observaciones' => 'nullable|string|max:2000', 'acepta_contacto' => 'required|accepted', 'website' => 'nullable|string|max:0',
            'items' => 'required|array|min:1|max:50', 'items.*.id' => 'required|integer|min:1|distinct',
            'items.*.cantidad' => 'required|integer|min:1|max:999'])->validate();
        $items = collect($data['items'])->sortBy('id')->map(fn ($i) => ['id' => (int) $i['id'], 'cantidad' => (int) $i['cantidad']])->values()->all();
        $contact = ['nombre' => trim($data['nombre']), 'email' => mb_strtolower(trim($data['email'])),
            'telefono' => trim($data['telefono']), 'observaciones' => trim($data['observaciones'] ?? '')];
        $hash = hash('sha256', json_encode([$contact, $items], JSON_THROW_ON_ERROR));
        $receipt = hash('sha256', $data['receipt_token']);
        $existing = DB::table('g1_catalog_orders')->where('request_key', $data['request_key'])->first();
        if ($existing) {
            abort_unless(hash_equals($existing->payload_hash, $hash) && hash_equals($existing->receipt_hash, $receipt), 409, 'La referencia ya pertenece a otra solicitud.');
            return ['repetido' => true, 'receipt_url' => route('catalogo.orders.receipt', $data['receipt_token'])];
        }
        $rows = [];
        $total = 0;
        $unpriced = false;
        foreach ($items as $item) {
            $product = app(CatalogoGateway::class)->find($item['id']);
            $cents = $product->precio === null ? null : BigDecimal::of($product->precio)->multipliedBy(100)->toInt();
            $unpriced = $unpriced || $cents === null;
            $total += ($cents ?? 0) * $item['cantidad'];
            abort_if($total > 999999999999, 422, 'El total supera el máximo admitido. Contactá al distribuidor.');
            $rows[] = ['producto_id' => $product->id, 'codigo' => $product->codigo, 'nombre' => $product->nombre,
                'cantidad' => $item['cantidad'], 'precio_referencia' => $product->precio];
        }
        // El bloqueo de la cuenta técnica serializa el alta y la clave única resuelve reenvíos simultáneos.
        DB::transaction(function () use ($data, $contact, $rows, $hash, $receipt, $unpriced, $total) {
            DB::table('usuarios')->orderBy('id')->lockForUpdate()->firstOrFail();
            $existing = DB::table('g1_catalog_orders')->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash) && hash_equals($existing->receipt_hash, $receipt), 409);
                return;
            }
            $id = DB::table('g1_catalog_orders')->insertGetId([...$contact, 'request_key' => $data['request_key'],
                'sale_request_key' => (string) Str::uuid(), 'payload_hash' => $hash, 'receipt_hash' => $receipt,
                'total_referencia' => $unpriced ? null : $total / 100, 'estado' => 'solicitado', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($rows as $row) {
                DB::table('g1_catalog_order_items')->insert(['order_id' => $id, ...$row]);
            }
            $this->event($id, null, null, 'solicitado', null);
        }, 3);
        return ['repetido' => false, 'receipt_url' => route('catalogo.orders.receipt', $data['receipt_token'])];
    }

    public function review(int $id, array $input, User $actor): array
    {
        abort_unless($actor->rol->value === 'administrativo' && $actor->estado->value === 'activo', 403);
        $data = validator($input, ['accion' => 'required|in:revisar,rechazar,convertir', 'cliente_id' => 'required_if:accion,convertir|nullable|integer|min:1',
            'lista' => 'required_if:accion,convertir|nullable|in:mayorista,minorista',
            'confirmar_revision' => ($input['accion'] ?? '') === 'convertir' ? 'required|accepted' : 'nullable|boolean',
            'nota' => 'required_if:accion,rechazar|nullable|string|max:2000'])->validate();
        return DB::transaction(function () use ($id, $data, $actor) {
            $order = DB::table('g1_catalog_orders')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($order->estado === 'convertido') {
                abort_unless($data['accion'] === 'convertir' && (int) $order->cliente_id === (int) $data['cliente_id'], 409, 'La solicitud ya fue convertida.');
                return ['estado' => 'convertido', 'venta_id' => $order->venta_id, 'repetido' => true];
            }
            abort_if($order->estado === 'rechazado', 409, 'La solicitud ya fue rechazada.');
            $update = ['revisado_por' => $actor->id, 'nota_revision' => $data['nota'] ?? null, 'updated_at' => now()];
            if ($data['accion'] !== 'convertir') {
                $update['estado'] = $data['accion'] === 'rechazar' ? 'rechazado' : 'en_revision';
                DB::table('g1_catalog_orders')->where('id', $id)->update($update);
                $this->event($id, $actor->id, $order->estado, $update['estado'], $data['nota'] ?? null);
                return ['estado' => $update['estado']];
            }
            $client = DB::table('clientes')->where('id', $data['cliente_id'])->lockForUpdate()->firstOrFail();
            abort_unless($client->estado === 'activo', 422, 'El cliente debe estar activo.');
            $items = DB::table('g1_catalog_order_items')->where('order_id', $id)->orderBy('producto_id')->get();
            $request = Request::create('/api/ventas', 'POST', ['request_key' => $order->sale_request_key,
                'cliente_id' => (int) $data['cliente_id'], 'lista' => $data['lista'], 'observaciones' => 'Pedido catálogo #'.$id,
                'aplicar_precio_mayorista' => $data['lista'] === 'mayorista',
                'items' => $items->map(fn ($i) => ['id' => $i->producto_id, 'cantidad' => $i->cantidad, 'descuento' => 0])->all()]);
            $request->headers->set('Accept', 'application/json');
            $request->setUserResolver(fn () => $actor);
            $response = app(VentaController::class)->store($request, app(ClienteGateway::class), app(ProductoGateway::class), app(StockGateway::class));
            $result = $response->getData(true);
            abort_unless($response->getStatusCode() < 300, 422, $result['message'] ?? 'No se pudo crear la venta.');
            $saleId = $result['venta_id'] ?? $result['id_venta'] ?? $result['data']['id_venta'] ?? $result['venta']['id_venta'] ?? null;
            abort_unless($saleId, 500, 'El contrato de Ventas no devolvió un identificador.');
            DB::table('g1_catalog_orders')->where('id', $id)->update([...$update, 'estado' => 'convertido', 'cliente_id' => $client->id, 'venta_id' => $saleId]);
            $this->event($id, $actor->id, $order->estado, 'convertido', 'Venta #'.$saleId.'. '.($data['nota'] ?? ''));
            return ['estado' => 'convertido', 'venta_id' => $saleId, 'repetido' => false];
        }, 3);
    }

    private function event(int $id, ?int $actor, ?string $before, string $after, ?string $note): void
    {
        DB::table('g1_catalog_order_events')->insert(['order_id' => $id, 'usuario_id' => $actor,
            'estado_anterior' => $before, 'estado_nuevo' => $after, 'nota' => $note, 'created_at' => now()]);
    }
}
