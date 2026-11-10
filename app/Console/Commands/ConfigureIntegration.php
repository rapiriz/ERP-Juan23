<?php

namespace App\Console\Commands;

use App\Modules\Login\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ConfigureIntegration extends Command
{
    protected $signature = 'erp:configure {--admin : Crear un administrativo mediante preguntas interactivas}';

    protected $description = 'Configura la identidad de servicio local y, opcionalmente, el primer administrativo';

    public function handle(): int
    {
        $path = $this->laravel->environmentFilePath();
        if (! is_file($path) || ! is_readable($path) || ! is_writable($path)) {
            $this->error('El archivo .env debe existir y permitir lectura y escritura.');

            return self::FAILURE;
        }
        $user = User::query()->firstOrCreate(['usuario' => 'servicio.integracion'], [
            'nombre' => 'Servicio de integración ERP', 'rol' => 'contador', 'estado' => 'activo',
            'password_hash' => Hash::make(bin2hex(random_bytes(48))),
        ]);
        if ($user->estado->value !== 'activo' || $user->rol->value !== 'contador') {
            $this->error('La cuenta servicio.integracion existente necesita revisión de permisos y estado.');

            return self::FAILURE;
        }

        $text = file_get_contents($path);
        $token = config('services.internal_api.token') ?: bin2hex(random_bytes(32));
        foreach (['INTERNAL_API_TOKEN' => $token, 'INTERNAL_API_USER_ID' => $user->id,
            'ERP_API_TRANSPORT' => 'internal', 'VENTAS_INTEGRACION' => 'internal',
            'CATALOG_TRANSPORT' => 'internal', 'SALES_TRANSPORT' => 'internal',
            'BILLING_TRANSPORT' => 'internal', 'BALANCE_TRANSPORT' => 'internal',
            'G1_RENDICIONES_TRANSPORT' => 'internal', 'G2_API_TRANSPORT' => 'internal',
            'CASH_TRANSPORT' => 'internal',
            'BILLING_PAYMENTS_PATH' => '/api/v1/finanzas/cobros',
            'BILLING_INVOICES_PATH' => '/api/v1/finanzas/facturas/pendientes',
            'BALANCE_CLIENT_PATH' => '/api/v1/finanzas/clientes/{id}/saldo',
            'BALANCE_DEBTORS_PATH' => '/api/v1/finanzas/clientes/deudores'] as $key => $value) {
            $line = $key.'='.$value;
            $text = preg_match('/^'.preg_quote($key, '/').'=/m', $text)
                ? preg_replace_callback('/^'.preg_quote($key, '/').'=.*$/m', fn () => $line, $text)
                : rtrim($text).PHP_EOL.$line.PHP_EOL;
        }
        if (file_put_contents($path, $text, LOCK_EX) === false) {
            $this->error('No se pudo guardar la configuración local.');

            return self::FAILURE;
        }
        $this->callSilent('config:clear');

        if ($this->option('admin')) {
            $username = $this->ask('Nombre de usuario administrativo');
            $validation = Validator::make(['usuario' => $username], ['usuario' => ['required', 'string', 'max:50']]);
            if ($validation->fails()) {
                $this->error($validation->errors()->first());

                return self::FAILURE;
            }
            if (User::query()->where('usuario', $username)->exists()) {
                $this->error('Ese usuario ya existe. Sus credenciales se conservan.');

                return self::FAILURE;
            }
            $password = $this->secret('Contraseña nueva, mínimo 12 caracteres');
            $validation = Validator::make(['password' => $password], ['password' => ['required', 'string', 'min:12']]);
            if ($validation->fails()) {
                $this->error($validation->errors()->first());

                return self::FAILURE;
            }
            User::query()->create(['usuario' => $username, 'nombre' => $username,
                'password_hash' => Hash::make($password), 'rol' => 'administrativo', 'estado' => 'activo']);
        }
        $this->info('Integración local configurada. Cuenta técnica #'.$user->id.'.');

        return self::SUCCESS;
    }
}
