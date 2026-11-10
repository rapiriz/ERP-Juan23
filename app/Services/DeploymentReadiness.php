<?php

namespace App\Services;

class DeploymentReadiness
{
    public function checks(bool $production = false): array
    {
        $smtp = config('mail.mailers.smtp');
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $checks = [
            'app_key' => filled(config('app.key')),
            'comprobantes_privados' => realpath(config('filesystems.disks.local.root')) !== false
                && ! str_starts_with(realpath(config('filesystems.disks.local.root')), realpath(public_path()).DIRECTORY_SEPARATOR),
            'scheduler_reciente' => $this->recentHeartbeat(),
        ];
        if ($production) {
            $checks += [
                'entorno_production' => app()->environment('production'),
                'debug_desactivado' => config('app.debug') === false,
                'url_https' => parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https',
                'cookies_seguras' => config('session.secure') === true,
                'cookies_http_only' => config('session.http_only') === true,
                'same_site' => in_array(config('session.same_site'), ['lax', 'strict'], true),
                'smtp_configurado' => config('mail.default') === 'smtp' && filled($smtp['host'] ?? null)
                    && ! in_array($smtp['host'], ['127.0.0.1', 'localhost', 'mailpit'], true)
                    && filled($smtp['username'] ?? null) && filled($smtp['password'] ?? null)
                    && filled(config('mail.from.address')) && ! str_ends_with(config('mail.from.address'), '@example.com'),
                'smtp_tls' => ($smtp['scheme'] ?? '') === 'smtps' || (int) ($smtp['port'] ?? 0) === 587,
                'host_publico' => $host && ! in_array($host, ['localhost', '127.0.0.1'], true),
                'ventas_reales' => config('ventas.integracion') !== 'mock',
            ];
        }

        return $checks;
    }

    private function recentHeartbeat(): bool
    {
        $file = storage_path('app/private/operations/scheduler.json');
        $data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
        $stamp = (int) ($data['timestamp'] ?? 0);

        return $stamp > 0 && $stamp <= now()->timestamp && now()->timestamp - $stamp <= 180;
    }
}
