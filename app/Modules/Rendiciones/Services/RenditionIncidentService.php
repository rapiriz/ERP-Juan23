<?php

namespace App\Modules\Rendiciones\Services;

use App\Modules\ConciliacionBancaria\Services\BankMoney;
use App\Modules\Entregas\Integraciones\VentasApiClient;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Shared\Auth\G2Identity;
use App\Modules\Shared\Services\G2OperationAudit;
use Illuminate\Support\Facades\DB;

class RenditionIncidentService
{
    public function __construct(private VentasApiClient $sales) {}

    private function authorize(Rendicion $rendition): int
    {
        $actor = G2Identity::id();
        abort_unless($actor && (G2Identity::role() === 'admin' || (int) $rendition->id_repartidor === $actor), 403);
        abort_unless($rendition->estaPendiente(), 409, 'La rendición ya fue revisada.');

        return $actor;
    }

    public function returned(int $id, array $data): array
    {
        validator($data, ['id_pedido' => 'required|integer|min:1', 'motivo' => 'required|string|min:3|max:500',
            'observaciones' => 'nullable|string|max:2000'])->validate();

        return DB::transaction(function () use ($id, $data) {
            $rendition = Rendicion::lockForUpdate()->findOrFail($id);
            $actor = $this->authorize($rendition);
            abort_unless($rendition->id_entrega, 422, 'La devolución requiere una entrega asociada a la rendición.');
            $delivery = DB::table('entrega')->where('id_entrega', $rendition->id_entrega)->lockForUpdate()->firstOrFail();
            abort_unless((int) $delivery->id_repartidor === (int) $rendition->id_repartidor, 422, 'La entrega corresponde a otro repartidor.');
            $item = DB::table('entrega_pedido')->where('id_entrega', $delivery->id_entrega)
                ->where('id_venta', $data['id_pedido'])->lockForUpdate()->first();
            abort_unless($item, 422, 'El pedido no pertenece a esta entrega.');
            $existing = DB::table('g2_rendicion_devoluciones')->where('id_entrega', $delivery->id_entrega)->where('id_pedido', $data['id_pedido'])->first();
            if ($existing) {
                abort_unless((int) $existing->id_rendicion === $id && $existing->motivo === trim($data['motivo'])
                    && ($existing->observaciones ?? '') === trim($data['observaciones'] ?? ''), 409, 'El pedido ya tiene otra devolución registrada.');

                return [...(array) $existing, 'id_devolucion' => $existing->id, 'repetido' => true, 'estado_entrega' => 'no_entregada'];
            }
            abort_unless(in_array($delivery->estado, ['en_preparacion', 'en_camino'], true)
                && $item->estado_pedido_entrega === 'en_preparacion', 409, 'El pedido ya fue entregado o cerrado.');
            abort_if(DB::table('RENDICION_COBRO')->where('id_rendicion', $id)->where('id_factura', $data['id_pedido'])->exists(),
                409, 'El pedido tiene un cobro declarado; revisalo antes de registrar que no fue entregado.');
            $sale = $this->sales->obtenerVentaPorId((int) $data['id_pedido']);
            abort_unless($sale && ! empty($sale['id_cliente']) && $sale['estado'] === 'preparada', 422, 'Ventas no confirma un pedido preparado válido.');
            $record = ['id_rendicion' => $id, 'id_entrega' => $delivery->id_entrega, 'id_pedido' => (int) $data['id_pedido'],
                'id_cliente' => $sale['id_cliente'], 'usuario_id' => $actor, 'motivo' => trim($data['motivo']),
                'observaciones' => trim($data['observaciones'] ?? ''), 'created_at' => now()];
            $incident = DB::table('g2_rendicion_devoluciones')->insertGetId($record);
            DB::table('entrega_pedido')->where('id_entrega_pedido', $item->id_entrega_pedido)
                ->update(['estado_pedido_entrega' => 'no_entregada', 'fecha_actualizacion' => now()]);
            DB::table('remito')->where('id_entrega', $delivery->id_entrega)->where('id_venta', $data['id_pedido'])->where('estado', '!=', 'anulado')
                ->update(['estado' => 'no_entregado']);
            $pending = DB::table('entrega_pedido')->where('id_entrega', $delivery->id_entrega)->where('estado_pedido_entrega', 'en_preparacion')->exists();
            DB::table('entrega')->where('id_entrega', $delivery->id_entrega)->update(['estado' => $pending ? $delivery->estado : 'finalizada',
                'fecha_modificacion' => now(), 'id_usuario_modificacion' => $actor]);
            G2OperationAudit::record('rendiciones', $id, 'devolucion_registrada', $actor, ['id_devolucion' => $incident, ...$record]);

            return ['id_devolucion' => $incident, ...$record, 'estado_entrega' => 'no_entregada', 'repetido' => false, 'stock_modificado' => false];
        });
    }

    public function difference(int $id, array $data): array
    {
        validator($data, ['total_esperado' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2',
            'motivo' => 'nullable|string|max:500', 'observaciones' => 'nullable|string|max:2000'])->validate();

        return DB::transaction(function () use ($id, $data) {
            $rendition = Rendicion::lockForUpdate()->findOrFail($id);
            $actor = $this->authorize($rendition);
            $items = DB::table('RENDICION_COBRO')->where('id_rendicion', $id)->lockForUpdate()->get();
            $ids = $rendition->id_entrega
                ? DB::table('entrega_pedido')->where('id_entrega', $rendition->id_entrega)->where('estado_pedido_entrega', 'entregado')->pluck('id_venta')
                : $items->pluck('id_factura')->unique();
            if ($rendition->id_entrega) {
                $previouslyReviewed = DB::table('RENDICION_COBRO as c')->join('RENDICION as r', 'r.id_rendicion', '=', 'c.id_rendicion')
                    ->where('r.id_entrega', $rendition->id_entrega)->where('r.id_rendicion', '!=', $id)
                    ->where('r.estado', '!=', 'pendiente')->pluck('c.id_factura');
                $ids = $ids->diff($previouslyReviewed);
            }
            abort_if($ids->isEmpty(), 422, 'No hay comprobantes entregados para calcular un total esperado.');
            $expected = 0;
            foreach ($ids->sort() as $saleId) {
                $sale = $this->sales->obtenerVentaPorId((int) $saleId);
                abort_unless($sale && ! in_array($sale['estado'], ['cancelada', 'anulada', 'pendiente'], true), 422, 'No se pudo verificar un comprobante de la rendición.');
                $expected += BankMoney::cents($sale['total']);
            }
            abort_if($expected > 999999999999, 422, 'El total supera el rango admitido.');
            abort_if(isset($data['total_esperado']) && BankMoney::cents($data['total_esperado']) !== $expected,
                422, 'El total esperado debe coincidir con los comprobantes reales asociados.');
            $declared = $items->sum(fn ($item) => BankMoney::cents($item->monto));
            abort_if($declared < 0 || $declared > 999999999999, 422, 'El total declarado supera el rango admitido.');
            $difference = $declared - $expected;
            abort_if($difference !== 0 && mb_strlen(trim($data['motivo'] ?? '')) < 3, 422, 'Indicá el motivo del faltante o sobrante.');
            $record = ['id_rendicion' => $id, 'total_esperado' => $expected / 100, 'total_rendido' => $declared / 100,
                'diferencia' => $difference / 100, 'tipo_diferencia' => $difference < 0 ? 'Faltante' : ($difference > 0 ? 'Sobrante' : 'Exacto'),
                'diferencia_pendiente' => $difference !== 0, 'motivo' => trim($data['motivo'] ?? ''), 'observaciones' => trim($data['observaciones'] ?? ''),
                'usuario_id' => $actor, 'resuelto_por' => null, 'resuelto_at' => null, 'resolucion' => null, 'updated_at' => now()];
            $existing = DB::table('g2_rendicion_diferencias')->where('id_rendicion', $id)->first();
            if ($existing) {
                DB::table('g2_rendicion_diferencias')->where('id', $existing->id)->update($record);
                $incident = $existing->id;
            } else {
                $incident = DB::table('g2_rendicion_diferencias')->insertGetId([...$record, 'created_at' => now()]);
            }
            $rendition->update(['total_rendido' => $declared / 100]);
            G2OperationAudit::record('rendiciones', $id, 'diferencia_registrada', $actor, ['anterior' => $existing, ...$record]);

            return ['id_diferencia' => $incident, ...$record];
        });
    }

    public function resolve(int $id, int $incident, string $reason): array
    {
        abort_unless(G2Identity::role() === 'admin', 403);
        abort_if(mb_strlen(trim($reason)) < 3 || mb_strlen($reason) > 2000, 422, 'Indicá una resolución válida.');

        return DB::transaction(function () use ($id, $incident, $reason) {
            $rendition = Rendicion::lockForUpdate()->findOrFail($id);
            $actor = $this->authorize($rendition);
            $difference = DB::table('g2_rendicion_diferencias')->where('id', $incident)->where('id_rendicion', $id)->lockForUpdate()->firstOrFail();
            if (! $difference->diferencia_pendiente) {
                return ['id_diferencia' => $incident, 'repetido' => true];
            }
            DB::table('g2_rendicion_diferencias')->where('id', $incident)->update(['diferencia_pendiente' => false,
                'resuelto_por' => $actor, 'resolucion' => trim($reason), 'resuelto_at' => now(), 'updated_at' => now()]);
            G2OperationAudit::record('rendiciones', $id, 'diferencia_resuelta', $actor, ['id_diferencia' => $incident, 'motivo' => $reason]);

            return ['id_diferencia' => $incident, 'repetido' => false, 'diferencia_pendiente' => false];
        });
    }
}
