<?php

namespace App\Modules\Entregas\Services;

use App\Modules\Entregas\Integraciones\VentasApiClient;
use App\Modules\Entregas\Models\Remito;
use App\Modules\Entregas\Repositories\RemitoRepository;
use App\Modules\Login\Models\User;
use App\Modules\Rendiciones\Services\RendicionService;
use App\Modules\Shared\Services\G2OperationAudit;
use App\Modules\Ventas\Services\DeliverySaleService;
use Illuminate\Support\Facades\DB;

class DeliveryPlanningService
{
    public function __construct(private VentasApiClient $sales, private RemitoRepository $receipts, private DeliverySaleService $owner) {}

    private function actor(int $id): User
    {
        $actor = User::findOrFail($id);
        abort_unless($actor->estado->value === 'activo' && in_array($actor->rol->value, ['administrativo', 'repartidor'], true), 403);
        abort_unless(config('g2.transport') === 'internal', 503, 'La edición atómica de entregas requiere la integración local; no se enviarán cambios remotos parciales.');

        return $actor;
    }

    public function create(int $driver, array $ids, string $date, int $actor, ?string $note): array
    {
        abort_unless($this->actor($actor)->rol->value === 'administrativo', 403);

        return DB::transaction(function () use ($driver, $ids, $date, $actor, $note) {
            DB::table('usuarios')->orderBy('id')->lockForUpdate()->first();
            $user = User::findOrFail($driver);
            abort_unless($user->rol->value === 'repartidor' && $user->estado->value === 'activo', 422, 'Repartidor no disponible.');
            abort_unless($ids, 422, 'Incluí al menos un pedido.');
            $id = DB::table('entrega')->insertGetId(['id_repartidor' => $driver, 'fecha_salida' => $date, 'estado' => 'en_preparacion', 'observaciones' => $note, 'id_usuario_creacion' => $actor, 'fecha_creacion' => now()]);
            $result = $this->update($id, $ids, $actor);

            return [...$result, 'id_entrega' => $id, 'mensaje' => 'Entrega creada con remitos y pedidos reservados.'];
        });
    }

    public function update(int $id, array $desired, int $actor): array
    {
        $user = $this->actor($actor);
        $desired = array_map('intval', $desired);
        sort($desired);
        abort_if(count($desired) !== count(array_unique($desired)) || count($desired) > 100 || count(array_filter($desired, fn ($v) => $v <= 0)), 422, 'Listado de pedidos inválido.');

        return DB::transaction(function () use ($id, $desired, $actor, $user) {
            DB::table('usuarios')->orderBy('id')->lockForUpdate()->first();
            $delivery = DB::table('entrega')->where('id_entrega', $id)->lockForUpdate()->firstOrFail();
            abort_unless($user->rol->value === 'administrativo' || (int) $delivery->id_repartidor === $actor, 403);
            abort_unless($delivery->estado === 'en_preparacion', 409, 'Sólo se modifican entregas en preparación.');
            $current = DB::table('entrega_pedido')->where('id_entrega', $id)->lockForUpdate()->pluck('id_venta')->map(fn ($v) => (int) $v)->all();
            $add = array_values(array_diff($desired, $current));
            $remove = array_values(array_diff($current, $desired));
            $zone = $delivery->id_zona;
            if (! $zone && $current) {
                $existing = $this->sales->obtenerVentaPorId($current[0]);
                $zone = $existing['id_zona'] ?? null;
            }
            $orders = [];
            foreach ($add as $saleId) {
                abort_if(DB::table('entrega_pedido as ep')->join('entrega as e', 'e.id_entrega', '=', 'ep.id_entrega')->where('ep.id_venta', $saleId)->whereIn('e.estado', ['en_preparacion', 'en_camino'])->exists(), 409, 'Un pedido ya pertenece a otra entrega.');
                $sale = $this->sales->obtenerVentaPorId($saleId);
                abort_unless($sale && $sale['estado'] === 'confirmada' && ! empty($sale['id_zona']), 422, 'El pedido debe estar confirmado y tener zona.');
                $zone ??= $sale['id_zona'];
                abort_unless((int) $zone === (int) $sale['id_zona'], 422, 'Los pedidos deben pertenecer a la misma zona.');
                $orders[$saleId] = $sale;
            }
            foreach ($remove as $saleId) {
                $this->owner->change($saleId, 'liberar', $actor);
                DB::table('remito')->where('id_entrega', $id)->where('id_venta', $saleId)->update(['estado' => 'anulado']);
                DB::table('entrega_pedido')->where('id_entrega', $id)->where('id_venta', $saleId)->delete();
                DB::table('g2_entrega_reservas')->where('id_venta', $saleId)->where('id_entrega', $id)->delete();
            }
            foreach ($orders as $saleId => $sale) {
                $this->owner->change($saleId, 'reservar', $actor);
                // Releer bajo el bloqueo de Ventas: la selección previa pudo cambiar.
                $sale = $this->sales->obtenerVentaPorId($saleId);
                abort_unless($sale && (int) ($sale['id_zona'] ?? 0) === (int) $zone, 409, 'El pedido cambió de zona durante la planificación.');
                DB::table('g2_entrega_reservas')->insert(['id_venta' => $saleId, 'id_entrega' => $id]);
                DB::table('entrega_pedido')->insert(['id_entrega' => $id, 'id_venta' => $saleId, 'estado_pedido_entrega' => 'en_preparacion', 'fecha_actualizacion' => now()]);
                $number = $this->receipts->generarSiguienteNumeroRemito(1);
                $receipt = $this->receipts->guardarRemito(new Remito($number, $id, $saleId, 'emitido', 'Generado desde planificación de entrega.'));
                abort_unless($sale['items'], 422, 'El pedido no tiene artículos.');
                $this->receipts->guardarDetalleRemito($receipt, $sale['items']);
            }
            if ($add || $remove) {
                DB::table('entrega')->where('id_entrega', $id)->update(['id_zona' => $zone, 'fecha_modificacion' => now(), 'id_usuario_modificacion' => $actor]);
                G2OperationAudit::record('entregas', $id, 'pedidos_modificados', $actor, ['agregados' => $add, 'quitados' => $remove, 'zona_id' => $zone]);
            }

            return ['id_entrega' => $id, 'pedidos_ids' => $desired, 'agregados' => $add, 'quitados' => $remove, 'id_zona' => $zone];
        });
    }

    public function deliver(int $id, int $saleId, int $actor, ?string $note, ?float $amount = null, string $method = 'efectivo'): array
    {
        $user = $this->actor($actor);

        abort_unless(in_array($method, ['efectivo', 'transferencia', 'cheque'], true), 422, 'Medio de pago inválido.');
        abort_if($amount !== null && (! is_finite($amount) || $amount < 0 || $amount > 99999999.99 || abs($amount * 100 - round($amount * 100)) > 0.0001), 422, 'Importe cobrado inválido.');

        return DB::transaction(function () use ($id, $saleId, $actor, $user, $note, $amount, $method) {
            $delivery = DB::table('entrega')->where('id_entrega', $id)->lockForUpdate()->firstOrFail();
            abort_unless($user->rol->value === 'administrativo' || (int) $delivery->id_repartidor === $actor, 403);
            $item = DB::table('entrega_pedido')->where('id_entrega', $id)->where('id_venta', $saleId)->lockForUpdate()->firstOrFail();
            if ($item->estado_pedido_entrega === 'entregado') {
                abort_if($amount !== null && ((int) round($amount * 100) !== (int) round($item->monto_cobrado * 100) || $method !== $item->medio_pago), 409, 'La entrega ya fue confirmada con otro cobro. Registrá el saldo desde Rendiciones.');
                return ['id_entrega' => $id, 'id_venta' => $saleId, 'repetido' => true, 'mensaje' => 'El pedido ya estaba confirmado; se conservó una sola operación.'];
            }
            abort_unless(in_array($delivery->estado, ['en_preparacion', 'en_camino'], true) && $item->estado_pedido_entrega === 'en_preparacion', 409, 'La entrega o el pedido no admite confirmaciones.');
            $service = app(RendicionService::class);
            $available = $service->availableForDeclaration($saleId);
            $amount ??= $available;
            abort_if((int) round($amount * 100) > (int) round($available * 100), 422, 'El cobro supera el saldo disponible o ya declarado del pedido.');
            $this->owner->change($saleId, 'entregar', $actor);
            DB::table('entrega_pedido')->where('id_entrega_pedido', $item->id_entrega_pedido)->update(['estado_pedido_entrega' => 'entregado', 'fecha_actualizacion' => now(), 'monto_cobrado' => $amount, 'medio_pago' => $method]);
            DB::table('remito')->where('id_entrega', $id)->where('id_venta', $saleId)->where('estado', '!=', 'anulado')->update(['estado' => 'entregado_conforme']);
            $pending = DB::table('entrega_pedido')->where('id_entrega', $id)->where('estado_pedido_entrega', 'en_preparacion')->exists();
            DB::table('entrega')->where('id_entrega', $id)->update(['estado' => $pending ? 'en_camino' : 'finalizada', 'fecha_modificacion' => now(), 'id_usuario_modificacion' => $actor]);
            $rendition = app(RendicionService::class)->precargarOActualizarRendicionDesdeEntrega($id);
            G2OperationAudit::record('entregas', $id, 'pedido_entregado', $actor, ['venta_id' => $saleId, 'observaciones' => $note, 'monto_cobrado' => $amount, 'medio_pago' => $method, 'rendicion' => $rendition]);

            return ['id_entrega' => $id, 'id_venta' => $saleId, 'estado_pedido' => 'entregado', 'monto_cobrado' => $amount, 'medio_pago' => $method, 'rendicion_precargada' => $rendition !== null, 'mensaje' => $rendition ? 'Pedido entregado y rendición preparada.' : 'Pedido entregado sin cobros nuevos para rendir.'];
        }, 3);
    }
}
