<?php

declare(strict_types=1);

namespace App\Support;

use App\Modules\Login\Enums\UserStatus;
use App\Modules\Login\Models\User;
use Illuminate\Http\Request;

/** Identidad autenticada para auditoría, sin aprovisionamiento durante la solicitud. */
final class UsuarioActual
{
    public const ATTRIBUTO = 'usuario_autenticado_id';

    public static function id(?Request $request = null): int
    {

        $request ??= request();

        if ($request->attributes->get('api_service') === true) {

            $id = filter_var(config('services.internal_api.user_id'), FILTER_VALIDATE_INT, [

                'options' => ['min_range' => 1],

            ]);

            abort_unless($id !== false, 503, 'Configure el usuario de auditoría del servicio interno.');

            $user = User::query()->whereKey($id)->where('estado', UserStatus::ACTIVO->value)->first();

            abort_unless($user, 503, 'El usuario de auditoría del servicio interno debe existir y estar activo.');

            return (int) $user->id;

        }

        $user = $request->user();

        abort_unless($user instanceof User && $user->estado === UserStatus::ACTIVO && $user->id > 0,

            401, 'No autorizado.');

        return (int) $user->id;

    }

}
