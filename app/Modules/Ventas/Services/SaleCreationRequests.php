<?php

namespace App\Modules\Ventas\Services;

use Illuminate\Support\Facades\DB;

/** Se reserva y completa en la misma transacción que venta, deuda y stock. */
class SaleCreationRequests
{
    public function replay(string $key, int $actor, array $payload): ?array
    {
        $existing = DB::table('g4_sale_requests')->where('request_key', $key)->first();

        return $existing ? $this->verify($existing, $actor, $payload) : null;
    }

    public function claim(string $key, int $actor, array $payload): ?array
    {
        unset($payload['request_key']);
        $hash = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));
        $inserted = DB::table('g4_sale_requests')->insertOrIgnore([
            'request_key' => $key, 'actor_id' => $actor, 'payload_hash' => $hash,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($inserted) {
            return null;
        }
        $existing = DB::table('g4_sale_requests')->where('request_key', $key)->lockForUpdate()->firstOrFail();

        return $this->verify($existing, $actor, $payload);
    }

    private function verify(object $existing, int $actor, array $payload): array
    {
        unset($payload['request_key']);
        $hash = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));
        abort_unless((int) $existing->actor_id === $actor && hash_equals($existing->payload_hash, $hash),
            409, 'La referencia ya se utilizó para otra venta o con otros datos.');
        abort_unless($existing->respuesta, 409, 'La solicitud está en proceso. Reintentá con la misma referencia.');

        return json_decode($existing->respuesta, true, flags: JSON_THROW_ON_ERROR);
    }

    public function complete(string $key, array $response): void
    {
        DB::table('g4_sale_requests')->where('request_key', $key)
            ->update(['respuesta' => json_encode($response, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    private function canonical(array $payload): array
    {
        if (! array_is_list($payload)) {
            ksort($payload);
        }
        foreach ($payload as &$value) {
            if (is_array($value)) {
                $value = $this->canonical($value);
            }
        }

        return $payload;
    }
}
