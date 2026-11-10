<?php

namespace App\Modules\NotificacionesTransferencia\Services;

use App\Modules\Clientes\Models\Cliente;
use App\Modules\Login\Models\User;
use App\Modules\NotificacionesTransferencia\Models\TransferNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransferNotificationService
{
    public function register(array $data, UploadedFile $file, User $actor): TransferNotification
    {
        abort_unless($actor->rol->value === 'administrativo' && $actor->estado->value === 'activo', 403);
        $hash = hash_file('sha256', $file->getRealPath());
        $stored = null;
        try {
            return DB::transaction(function () use ($data, $file, $actor, $hash, &$stored) {
                Cliente::query()->whereKey($data['cliente_id'])->lockForUpdate()->firstOrFail();
                $existing = TransferNotification::where('request_key', $data['request_key'])->first();
                if ($existing) {
                    abort_unless((int) $existing->cliente_id === (int) $data['cliente_id'] && (int) $existing->registrado_por === (int) $actor->id
                        && $existing->sha256 === $hash && $existing->fecha->toDateString() === $data['fecha']
                        && (int) round($existing->monto * 100) === (int) round($data['monto'] * 100) && $existing->referencia === $data['referencia'], 409, 'La solicitud ya corresponde a otro aviso.');

                    return $existing;
                }
                $stored = $file->store('transferencias', 'local');
                if (! is_string($stored) || $stored === '') {
                    throw ValidationException::withMessages(['comprobante' => 'No pudimos guardar el comprobante. Intentá nuevamente.']);
                }
                $notification = TransferNotification::create([
                    ...collect($data)->only(['request_key', 'cliente_id', 'fecha', 'monto', 'referencia'])->all(),
                    'estado' => 'pendiente', 'registrado_por' => $actor->id, 'archivo' => $stored,
                    'nombre_archivo' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'mime' => $file->getMimeType(), 'bytes' => $file->getSize(), 'sha256' => $hash,
                ]);
                $this->audit($notification, $actor, 'registrada', ['estado' => 'pendiente', 'monto' => $notification->monto, 'sha256' => $hash]);

                return $notification;
            });
        } catch (Throwable $error) {
            if (is_string($stored) && $stored !== '') {
                Storage::disk('local')->delete($stored);
            }
            throw $error;
        }
    }

    public function review(TransferNotification $notification, User $actor, array $data): void
    {
        abort_unless($actor->rol->value === 'administrativo' && $actor->estado->value === 'activo', 403);
        DB::transaction(function () use ($notification, $actor, $data): void {
            $notification = TransferNotification::whereKey($notification->id)->lockForUpdate()->firstOrFail();
            if ($notification->estado === $data['estado']) {
                return;
            }
            abort_unless($notification->estado === 'pendiente', 409, 'El aviso ya tiene una revisión registrada.');
            $notification->update(['estado' => $data['estado'], 'revisado_por' => $actor->id, 'nota_revision' => $data['nota']]);
            $this->audit($notification, $actor, $data['estado'], ['estado_anterior' => 'pendiente', 'nota' => $data['nota']]);
        });
    }

    private function audit(TransferNotification $notification, User $actor, string $event, array $data): void
    {
        DB::table('g1_transferencia_eventos')->insert(['transferencia_id' => $notification->id, 'usuario_id' => $actor->id,
            'evento' => $event, 'datos' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
