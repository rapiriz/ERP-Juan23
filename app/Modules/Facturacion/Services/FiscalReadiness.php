<?php

namespace App\Modules\Facturacion\Services;

class FiscalReadiness
{
    public function status(): array
    {
        $privateFile = function ($path) {
            if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
                return false;
            }
            $absolute = str_replace('\\', '/', realpath($path));
            $public = str_replace('\\', '/', realpath(public_path()));
            return ! str_starts_with(strtolower($absolute), strtolower(rtrim($public, '/')).'/');
        };
        $cuit = (string) config('fiscal.cuit');
        return ['emision_habilitada' => false, 'modo_actual' => 'comprobantes_internos',
            'entorno_preparado' => config('fiscal.environment'), 'cuenta_fiscal_configurada' => (bool) preg_match('/^[0-9]{11}$/D', $cuit),
            'punto_venta_configurado' => filter_var(config('fiscal.point_of_sale'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99999]]) !== false,
            'certificado_privado_presente' => $privateFile(config('fiscal.certificate_path')),
            'clave_privada_presente' => $privateFile(config('fiscal.private_key_path')),
            'adaptador_arca_implementado' => false,
            'pendientes' => ['Autorizaciones y configuración del emisor', 'Adaptador WSAA/WSFE y recuperación idempotente ante respuestas inciertas',
                'Ensayos de homologación y revisión del tratamiento impositivo', 'Habilitación de producción y conciliación de autorizaciones reales']];
    }
}
