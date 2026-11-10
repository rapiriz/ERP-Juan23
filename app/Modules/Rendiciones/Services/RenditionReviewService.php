<?php

namespace App\Modules\Rendiciones\Services;

use App\Modules\Login\Models\User;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Rendiciones\Models\RendicionCobro;
use App\Modules\Shared\Services\G2OperationAudit;
use Illuminate\Support\Facades\DB;

class RenditionReviewService
{
    public function __construct(private CollectionSettlementService $settlements) {}

    public function review(int $id, array $data): array
    {
        $actor = User::findOrFail($data['id_usuario_validador']);
        abort_unless($actor->rol->value === 'administrativo' && $actor->estado->value === 'activo', 403);
        abort_unless(in_array($data['accion'] ?? null, ['aprobar', 'rechazar'], true), 422, 'Acción de revisión inválida.');

        return DB::transaction(function () use ($id, $data, $actor) {
            $rendition = Rendicion::lockForUpdate()->findOrFail($id);
            $items = RendicionCobro::where('id_rendicion', $id)->orderBy('id_cliente')->orderBy('id_rendicion_cobro')->lockForUpdate()->get();
            abort_if($rendition->estado === 'aprobada' && $items->contains('estado_revision', 'pendiente'), 409, 'Rendición histórica aprobada sin certificación individual: revisar su aplicación antes de volver a liquidar.');
            $ids = isset($data['cobros_aprobados']) ? array_map('intval', $data['cobros_aprobados']) : $items->pluck('id_rendicion_cobro')->all();
            abort_if(array_diff($ids, $items->pluck('id_rendicion_cobro')->all()), 422, 'La selección incluye cobros de otra rendición.');
            if ($data['accion'] === 'aprobar') {
                $difference = DB::table('g2_rendicion_diferencias')->where('id_rendicion', $id)->first();
                abort_if($difference && ($difference->diferencia_pendiente || (int) round($difference->total_rendido * 100) !== (int) round($items->sum('monto') * 100)), 409, 'La diferencia está pendiente o cambiaron los cobros: recalculá y resolvé antes de aprobar.');
                abort_if($items->isNotEmpty() && ! $ids, 422, 'Seleccioná al menos un cobro.');
                abort_if($rendition->estado === 'rechazada', 409, 'La rendición está rechazada.');
                foreach ($items->whereIn('id_rendicion_cobro', $ids) as $item) {
                    if ($item->estado_revision === 'aplicado') {
                        continue;
                    }
                    abort_unless($item->estado_revision === 'pendiente', 409, 'Un cobro seleccionado ya fue rechazado o anulado.');
                    $this->settlements->apply($item, $actor);
                }
            } else {
                abort_if(mb_strlen(trim($data['motivo_rechazo'] ?? '')) < 3, 422, 'Indicá el motivo de rechazo.');
                foreach ($items->where('estado_revision', 'pendiente') as $item) {
                    $item->forceFill(['estado_revision' => 'rechazado', 'revisado_por' => $actor->id, 'fecha_revision' => now(), 'nota_revision' => $data['motivo_rechazo']])->save();
                    G2OperationAudit::record('rendiciones', $id, 'cobro_rechazado', $actor->id, ['id_cobro' => $item->id_rendicion_cobro, 'motivo' => $data['motivo_rechazo']]);
                }
            }
            $current = RendicionCobro::where('id_rendicion', $id)->get();
            $pending = $current->where('estado_revision', 'pendiente')->count();
            $applied = $current->where('estado_revision', 'aplicado')->count();
            $state = $pending ? 'pendiente' : (($applied || ($items->isEmpty() && $data['accion'] === 'aprobar')) ? 'aprobada' : 'rechazada');
            $rendition->forceFill(['estado' => $state, 'id_usuario_validador' => $actor->id, 'fecha_validacion' => now(),
                'motivo_rechazo' => $data['accion'] === 'rechazar' ? $data['motivo_rechazo'] : null])->save();
            G2OperationAudit::record('rendiciones', $id, 'revision', $actor->id, ['accion' => $data['accion'], 'seleccion' => $ids, 'estado' => $state, 'pendientes' => $pending, 'aplicados' => $applied]);

            return ['id_rendicion' => $id, 'estado_actual' => $state, 'revision_parcial' => $pending > 0, 'cobros_pendientes' => $pending, 'cobros_aplicados' => $applied,
                'id_usuario_validador' => $actor->id, 'fecha_validacion' => $rendition->fecha_validacion, 'motivo_rechazo' => $rendition->motivo_rechazo];
        });
    }
}
