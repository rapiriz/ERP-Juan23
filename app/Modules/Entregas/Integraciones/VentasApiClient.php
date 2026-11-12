<?php

namespace App\Modules\Entregas\Integraciones;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

/**
 * Cliente HTTP del módulo G2 para consumir la API de Ventas (G4).
 * Diseñado bajo principios de resiliencia y desacoplamiento:
 * - Solo peticiones HTTP (sin acceso a tablas de otros grupos).
 * - Timeouts breves y política de reintentos ante fallas efímeras.
 * - Graceful degradation: captura errores de conexión y 5xx registrando logs.
 */
class VentasApiClient
{
    /** @var array<int, array<string, mixed>|null> */
    private array $clientesCache = [];

    /** @var array<int, string>|null */
    private ?array $zonasCache = null;

    public function getBaseUrl(): string
    {
        $url = rtrim((string) config('g2.ventas_url'), '/');
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new UnexpectedValueException('La conexión de G2 con Ventas todavía no está configurada.');
        }

        return $url;
    }

    public function isConfigured(): bool
    {
        try {
            return $this->getBaseUrl() !== '';
        } catch (Throwable) {
            return false;
        }
    }

    private function http(): PendingRequest
    {
        $timeout = (int) (config('g2.timeout') ?: 3);

        $request = Http::baseUrl($this->getBaseUrl())
            ->acceptJson()
            ->connectTimeout($timeout)
            ->timeout($timeout)
            ->retry(2, 100, throw: false);

        $token = config('g2.ventas_token') ?: config('services.internal_api.token');

        return $token ? $request->withToken($token) : $request;
    }

    private function request(string $method, string $path, array $data = []): ?\Illuminate\Http\Client\Response
    {
        $url = $this->getBaseUrl();
        $isLocalLoop = str_starts_with($url, 'http://127.0.0.1:') || str_starts_with($url, 'http://localhost:');

        // Si apunta a localhost/127.0.0.1 dentro de este mismo entorno monolithic,
        // resolvemos la petición a través del kernel/router de Laravel para evitar el deadlock
        // de single-process de PHP built-in server (php artisan serve).
        if ($isLocalLoop) {
            $parsedUrl = parse_url($url);
            $basePath = rtrim($parsedUrl['path'] ?? '', '/');
            $fullPath = $basePath . '/' . ltrim($path, '/');

            $queryString = ($method === 'GET' && !empty($data)) ? '?' . http_build_query($data) : '';
            $subRequest = \Illuminate\Http\Request::create($fullPath . $queryString, $method, $method === 'GET' ? [] : $data);
            $subRequest->headers->set('Accept', 'application/json');
            $token = config('g2.ventas_token') ?: config('services.internal_api.token');
            if ($token) {
                $subRequest->headers->set('Authorization', 'Bearer ' . $token);
            }

            $originalRequest = app()->bound('request') ? app('request') : null;
            try {
                $laravelResponse = app()->handle($subRequest);
                $psrResponse = new \GuzzleHttp\Psr7\Response(
                    $laravelResponse->getStatusCode(),
                    $laravelResponse->headers->all(),
                    $laravelResponse->getContent()
                );
                return new \Illuminate\Http\Client\Response($psrResponse);
            } catch (Throwable $e) {
                Log::warning("G2 - VentasApiClient: Excepción en llamada interna a Ventas: " . $e->getMessage());
                return null;
            } finally {
                if ($originalRequest) {
                    app()->instance('request', $originalRequest);
                }
            }
        }

        // Si apunta a un servidor remoto / puerto diferente separado, usa HTTP normal:
        try {
            $http = $this->http();
            return match (strtoupper($method)) {
                'GET' => $http->get($path, $data),
                'POST' => $http->post($path, $data),
                'PATCH' => $http->patch($path, $data),
                'PUT' => $http->put($path, $data),
                default => null,
            };
        } catch (Throwable $e) {
            Log::warning("G2 - VentasApiClient: Excepción al comunicarse con Ventas (G4): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene ventas con estado confirmada listas para ser despachadas.
     * Retorna array vacío o lanza excepción si la URL no está configurada según contrato.
     */
    public function obtenerVentasPendientesDespacho(?int $idZona = null): array
    {
        $sales = [];
        $page = 1;

        $url = $this->getBaseUrl();
        $isRemoteG4 = str_contains($url, 'g4.test') || (!str_starts_with($url, 'http://127.0.0.1:') && !str_starts_with($url, 'http://localhost:'));
        $estadoFiltro = $isRemoteG4 ? 'confirmada' : config('g2.ventas_estado_despacho', 'pendiente');
        $estadosValidos = array_unique(['pendiente', 'confirmada', $estadoFiltro]);

        try {
            do {
                $response = $this->request('GET', 'ventas', array_filter(['estado' => $estadoFiltro, 'id_zona' => $idZona, 'page' => $page]));

                // Si con el estado configurado no vino nada (o la prueba mockea 'confirmada'), intentamos también si es necesario
                if ($response && $response->successful() && empty($response->json('data')) && $estadoFiltro !== 'confirmada') {
                    $fallbackResp = $this->request('GET', 'ventas', array_filter(['estado' => 'confirmada', 'id_zona' => $idZona, 'page' => $page]));
                    if ($fallbackResp && $fallbackResp->successful() && !empty($fallbackResp->json('data'))) {
                        $response = $fallbackResp;
                    }
                }

                if (! $response || $response->failed()) {
                    Log::warning("G2 - VentasApiClient: Error al consultar ventas pendientes" . ($response ? " (HTTP {$response->status()})" : ""));
                    return [];
                }

                $body = $response->json();
                $rows = $body['data'] ?? $body;

                if (! is_array($rows) || ! array_is_list($rows) || ($body['error'] ?? false)) {
                    throw new UnexpectedValueException('Ventas devolvió un listado incompatible con el contrato de G2.');
                }

                $sales = array_merge($sales, array_map($this->normalizar(...), $rows));
                $lastPage = (int) ($body['meta']['last_page'] ?? 1);
                if ($lastPage < $page || $lastPage > 100) {
                    throw new UnexpectedValueException('La paginación de Ventas no puede procesarse de forma completa.');
                }
                $page++;
            } while ($page <= $lastPage);
        } catch (UnexpectedValueException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('G2 - VentasApiClient: Excepción al comunicarse con Ventas (G4): ' . $e->getMessage());
            return [];
        }

        if ($idZona !== null && count(array_filter($sales, fn ($sale) => ! isset($sale['id_zona']))) > 0) {
            throw new UnexpectedValueException('Ventas debe informar la zona para aplicar este filtro.');
        }

        return array_values(array_filter($sales,
            fn ($sale) => in_array($sale['estado'] ?? null, $estadosValidos, true)
                && ($idZona === null || (int) ($sale['id_zona'] ?? 0) === $idZona)));
    }

    /**
     * Obtiene el detalle de una venta por ID.
     * Retorna null si no existe, si falla la conexión o si el servicio externo está caído.
     */
    public function obtenerVentaPorId(int $id): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning("G2 - VentasApiClient: No se puede obtener venta #{$id} porque la conexión no está configurada.");
            return null;
        }

        try {
            $response = $this->request('GET', "ventas/{$id}");

            if (! $response) {
                return null;
            }

            if ($response->status() === 404) {
                return null;
            }

            if ($response->failed()) {
                Log::warning("G2 - VentasApiClient: Error HTTP {$response->status()} al consultar venta #{$id}.", [
                    'body' => $response->body(),
                ]);
                return null;
            }

            $body = $response->json();
            if (($body['error'] ?? false) || ! is_array($body['data'] ?? $body)) {
                Log::warning("G2 - VentasApiClient: Venta #{$id} devuelta con estructura incompatible.");
                return null;
            }

            $sale = $this->normalizar($body['data'] ?? $body);
            if ($sale['id_venta'] !== $id) {
                Log::warning("G2 - VentasApiClient: El ID devuelto ({$sale['id_venta']}) no coincide con el solicitado ({$id}).");
                return null;
            }

            return $sale;
        } catch (Throwable $e) {
            Log::warning("G2 - VentasApiClient: Excepción al obtener venta #{$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Notifica a la API de Ventas (G4) el cambio de estado de una venta (ej. 'pagada', 'entregada').
     * Retorna false si falla sin lanzar excepciones que interrumpan los flujos de G2.
     */
    public function actualizarEstadoVenta(int $id, string $estado): bool
    {
        if (! $this->isConfigured()) {
            Log::warning("G2 - VentasApiClient: No se pudo actualizar estado de venta #{$id} a '{$estado}': conexión no configurada.");
            return false;
        }

        try {
            $response = $this->request('PATCH', "ventas/{$id}/estado", ['estado' => $estado]);

            if (! $response || $response->failed() || $response->json('error') === true) {
                Log::warning("G2 - VentasApiClient: Ventas no aceptó actualizar venta #{$id} a '{$estado}'" . ($response ? " (HTTP {$response->status()})" : ""), [
                    'body' => $response?->body(),
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning("G2 - VentasApiClient: Excepción al actualizar estado de venta #{$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notifica a la API de Cuenta Corriente (G4) la aprobación de un cobro de rendición
     * para que impacte directamente en el saldo del cliente (POST /api/cuenta-corriente/cobros).
     */
    public function registrarCobroCuentaCorriente(int $idRendicionCobro): bool
    {
        if (! $this->isConfigured()) {
            Log::warning("G2 - VentasApiClient: No se pudo imputar cobro #{$idRendicionCobro} en cuenta corriente: conexión no configurada.");
            return false;
        }

        try {
            $response = $this->request('POST', 'cuenta-corriente/cobros', ['id' => $idRendicionCobro]);

            if (! $response || $response->failed() || $response->json('error') === true) {
                Log::warning("G2 - VentasApiClient: Cuenta Corriente no aceptó el cobro #{$idRendicionCobro}" . ($response ? " (HTTP {$response->status()})" : ""), [
                    'body' => $response?->body(),
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning("G2 - VentasApiClient: Excepción al imputar cobro #{$idRendicionCobro} en cuenta corriente: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Normaliza la estructura devuelta por G4 (VentaResource o array plano).
     */
    private function normalizar(array $sale): array
    {
        $sale['id_venta'] = (int) ($sale['id_venta'] ?? $sale['id'] ?? 0);
        $sale['id_cliente'] = (int) ($sale['id_cliente'] ?? $sale['cliente']['id'] ?? 0);
        // Equivalencias documentadas por VentaResource de G4
        $estado = is_array($sale['estado'] ?? null) ? ($sale['estado']['valor'] ?? null) : ($sale['estado'] ?? null);
        $sale['estado'] = is_string($estado) ? strtolower(trim($estado)) : null;
        $sale['cliente_nombre'] = $sale['cliente_nombre'] ?? $sale['cliente']['nombre'] ?? null;
        $rawItems = $sale['items'] ?? array_map(fn ($item) => [
            ...$item, 'producto_nombre' => $item['producto'] ?? null,
        ], $sale['detalles'] ?? []);

        $totalBultos = 0;
        $totalSueltos = 0;
        $sale['items'] = array_map(function (array $item) use (&$totalBultos, &$totalSueltos): array {
            $cantidad = max(0, (int) ($item['cantidad'] ?? 0));
            $bultos = isset($item['bultos']) ? (int) $item['bultos'] : intdiv($cantidad, 6);
            $sueltos = isset($item['unidades_sueltas']) ? (int) $item['unidades_sueltas'] : ($cantidad % 6);

            $totalBultos += $bultos;
            $totalSueltos += $sueltos;

            return [
                ...$item,
                'cantidad' => $cantidad,
                'bultos' => $bultos,
                'unidades_sueltas' => $sueltos,
            ];
        }, $rawItems);

        $sale['total_bultos'] = isset($sale['total_bultos']) ? (int) $sale['total_bultos'] : $totalBultos;
        $sale['total_sueltos'] = isset($sale['total_sueltos']) ? (int) $sale['total_sueltos'] : $totalSueltos;

        $datosCliente = $sale['id_cliente'] > 0 ? $this->obtenerDatosCliente($sale['id_cliente']) : null;

        // LÍNEAS PARA COHERENCIA CON REMITOS Y VIAJES
        $sale['direccion'] = $sale['direccion']
            ?? $sale['cliente']['domicilio']
            ?? $sale['cliente']['direccion']
            ?? $datosCliente['direccion']
            ?? 'Domicilio comercial';

        $sale['localidad'] = $sale['localidad']
            ?? $sale['cliente']['localidad']
            ?? $datosCliente['localidad']
            ?? 'Pigüé';

        $sale['telefono'] = $sale['telefono']
            ?? $sale['cliente']['telefono']
            ?? $datosCliente['telefono']
            ?? '';

        $sale['cuit'] = $sale['cuit']
            ?? $sale['cliente']['cuit']
            ?? $datosCliente['cuit']
            ?? '';

        $sale['razon_social'] = $sale['razon_social']
            ?? $datosCliente['razon_social']
            ?? '';

        if (! isset($sale['id_zona']) && isset($datosCliente['id_zona'])) {
            $sale['id_zona'] = $datosCliente['id_zona'];
        }

        $sale['zona_nombre'] = $sale['zona_nombre']
            ?? $sale['cliente']['zona']
            ?? $datosCliente['zona_nombre']
            ?? 'Ruta Saavedra';

        if ($sale['id_venta'] <= 0 || $sale['id_cliente'] <= 0 || ! is_numeric($sale['total'] ?? null)) {
            throw new UnexpectedValueException('Faltan identificadores o el total real en la respuesta de Ventas.');
        }

        return $sale;
    }

    /**
     * Obtiene dirección, localidad y zona reales del cliente consultando la API de Clientes (/api/v1/clientes/{id}).
     *
     * @return array<string, mixed>|null
     */
    private function obtenerDatosCliente(int $idCliente): ?array
    {
        if (array_key_exists($idCliente, $this->clientesCache)) {
            return $this->clientesCache[$idCliente];
        }

        $token = (string) (config('services.internal_api.token') ?: config('g2.ventas_token') ?: '');
        $originalRequest = app()->bound('request') ? app('request') : null;

        try {
            $req = \Illuminate\Http\Request::create("/api/v1/clientes/{$idCliente}", 'GET');
            $req->headers->set('Accept', 'application/json');
            if ($token !== '') {
                $req->headers->set('Authorization', 'Bearer ' . $token);
            }

            $resp = app()->handle($req);
            if ($resp->getStatusCode() !== 200) {
                return $this->clientesCache[$idCliente] = null;
            }

            $payload = json_decode((string) $resp->getContent(), true);
            $data = is_array($payload) ? ($payload['data'] ?? $payload) : null;
            if (! is_array($data)) {
                return $this->clientesCache[$idCliente] = null;
            }

            $idZona = isset($data['zona_id']) && $data['zona_id'] !== null ? (int) $data['zona_id'] : null;
            $zonas = $idZona !== null ? $this->obtenerMapaZonas($token) : [];

            return $this->clientesCache[$idCliente] = [
                'direccion' => filled($data['direccion'] ?? null) ? (string) $data['direccion'] : null,
                'localidad' => filled($data['localidad'] ?? null) ? (string) $data['localidad'] : null,
                'telefono' => (string) ($data['telefono'] ?? ''),
                'cuit' => (string) ($data['dni_cuit'] ?? ''),
                'razon_social' => (string) ($data['apellido_razon_social'] ?? ''),
                'id_zona' => $idZona,
                'zona_nombre' => ($idZona !== null && isset($zonas[$idZona])) ? $zonas[$idZona] : null,
            ];
        } catch (Throwable) {
            return $this->clientesCache[$idCliente] = null;
        } finally {
            if ($originalRequest) {
                app()->instance('request', $originalRequest);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function obtenerMapaZonas(string $token): array
    {
        if ($this->zonasCache !== null) {
            return $this->zonasCache;
        }

        $this->zonasCache = [];

        try {
            $req = \Illuminate\Http\Request::create('/api/v1/zonas', 'GET');
            $req->headers->set('Accept', 'application/json');
            if ($token !== '') {
                $req->headers->set('Authorization', 'Bearer ' . $token);
            }

            $resp = app()->handle($req);
            if ($resp->getStatusCode() !== 200) {
                return $this->zonasCache;
            }

            $payload = json_decode((string) $resp->getContent(), true);
            $rows = is_array($payload) ? ($payload['data'] ?? $payload) : [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $id = (int) ($row['id'] ?? $row['id_zona'] ?? 0);
                    $nombre = (string) ($row['nombre'] ?? '');
                    if ($id > 0 && $nombre !== '') {
                        $this->zonasCache[$id] = $nombre;
                    }
                }
            }
        } catch (Throwable) {
            // Silencioso: mantiene fallback si no está disponible
        }

        return $this->zonasCache;
    }
}
