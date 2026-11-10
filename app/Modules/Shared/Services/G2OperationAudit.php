<?php

namespace App\Modules\Shared\Services;

use Illuminate\Support\Facades\DB;

class G2OperationAudit
{
    public static function record(string $domain, int|string $id, string $event, int $actor, array $data): void
    {
        DB::table('g2_operacion_eventos')->insert(['dominio' => $domain, 'registro' => (string) $id,
            'evento' => $event, 'usuario_id' => $actor, 'datos' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
