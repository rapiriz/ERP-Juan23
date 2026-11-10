<?php

namespace App\Modules\Catalogo\Services;

use App\Services\Integrations\InternalApiClient;
use Illuminate\Http\Client\Response;

/** Adaptador del contrato público de productos utilizado por el catálogo. */
class InternalCatalogApi
{
    public function get(string $path, array $query = []): Response
    {

        abort_unless(preg_match('#^/api/(?:v1/)?productos(?:/[1-9][0-9]*)?$#D', $path),

            503, 'Ruta de catálogo interno no permitida.');

        return app(InternalApiClient::class)->get($path, $query, config('services.catalog.token'));

    }

}
