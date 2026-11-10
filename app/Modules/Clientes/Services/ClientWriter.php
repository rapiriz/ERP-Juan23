<?php

namespace App\Modules\Clientes\Services;

use App\Modules\Clientes\Enums\ClientStatus;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Models\Zona;
use Illuminate\Support\Facades\DB;

class ClientWriter
{
    public function save(array $data, ?Cliente $client = null, ?int $creator = null): Cliente
    {
        return DB::transaction(function () use ($data, $client, $creator): Cliente {
            if ($client !== null) {
                $client = Cliente::whereKey($client->id)->lockForUpdate()->firstOrFail();
            }
            $previousLimit = $client?->limite_credito;
            if (isset($data['zona'])) {
                $name = $data['zona'];
                $zone = Zona::whereRaw('LOWER(nombre) = ?', [mb_strtolower($name)])->first()
                    ?? Zona::firstOrCreate(['nombre' => $name]);
                $data['zona_id'] = $zone->id;
            }
            unset($data['zona']);
            if ($client === null) {
                $client = new Cliente;
                $client->estado = ClientStatus::ACTIVO;
                $client->creado_por = $creator;
            }
            $client->fill($data)->save();
            if (array_key_exists('limite_credito', $data) && $previousLimit !== $client->limite_credito) {
                $actor = auth()->id() ?? $creator;
                abort_unless($actor, 401);
                DB::table('g4_credit_events')->insert(['cliente_id' => $client->id, 'usuario_id' => $actor,
                    'evento' => 'limite_modificado', 'datos' => json_encode(['anterior' => $previousLimit, 'nuevo' => $client->limite_credito], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }

            return $client->refresh();
        });
    }
}
