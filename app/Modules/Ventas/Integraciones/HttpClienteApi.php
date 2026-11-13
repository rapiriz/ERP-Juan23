<?php

namespace App\Modules\Ventas\Integraciones;

use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Exceptions\IntegracionNoDisponibleException;
use App\Services\Integrations\InternalApiClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Clientes desde /api/v1/clientes: transporte interno o HTTP autenticado. */
class HttpClienteApi implements ClienteGateway
{
    private ?array $cache = null;

    /** Clientes ya buscados en este pedido (null = no existe): se pide cada id una sola vez. */
    private array $buscados = [];

    public function todos(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $clientes = [];
            $page = 1;

            do {
                $respuesta = $this->cliente()
                    ->get('/api/v1/clientes', ['estado' => 'activo', 'per_page' => 100, 'page' => $page])
                    ->throw()
                    ->json();

                $clientes = array_merge($clientes, $respuesta['data'] ?? []);
                $lastPage = (int) ($respuesta['meta']['last_page'] ?? 1);
                $page++;
            } while ($page <= $lastPage);
        } catch (Throwable $e) {
            throw new IntegracionNoDisponibleException('clientes', $e);
        }

        return $this->cache = array_values(array_map(
            fn (array $cliente) => $this->normalizar($cliente),
            $clientes
        ));
    }

    public function buscar(int $id): ?array
    {
        if (array_key_exists($id, $this->buscados)) {
            return $this->buscados[$id];
        }

        return $this->buscados[$id] = $this->pedir($id);
    }

    private function pedir(int $id): ?array
    {
        try {
            $respuesta = $this->cliente()->get("/api/v1/clientes/{$id}");
        } catch (Throwable $e) {
            throw new IntegracionNoDisponibleException('clientes', $e);
        }

        if ($respuesta->notFound()) {
            return null;
        }

        if ($respuesta->failed()) {
            throw new IntegracionNoDisponibleException('clientes', $respuesta->toException());
        }

        return $this->normalizar($respuesta->json('data') ?? $respuesta->json());
    }

    private function normalizar(array $cliente): array
    {
        return [
            'id' => (int) ($cliente['id'] ?? 0),
            'nombre' => trim((string) (($cliente['nombre'] ?? '').' '.($cliente['apellido_razon_social'] ?? ''))),
            'cuit' => (string) ($cliente['dni_cuit'] ?? ''),
            'condicion' => (string) ($cliente['condicion_iva'] ?? ''),
            'domicilio' => (string) ($cliente['direccion'] ?? ''),
            'localidad' => (string) ($cliente['localidad'] ?? ''),
            'zona_id' => isset($cliente['zona_id']) ? (int) $cliente['zona_id'] : null,
            'tipo_cliente' => (string) ($cliente['tipo_cliente'] ?? ''),
            'movimientos' => [],
        ];
    }

    private function cliente(): PendingRequest|InternalApiClient
    {
        if (config('ventas.integracion') === 'internal') {
            return new InternalApiClient(config('services.internal_api.token'));
        }

        $cliente = Http::baseUrl(rtrim((string) config('ventas.servicios.clientes.url'), '/'))
            ->timeout((int) config('ventas.timeout'))
            ->acceptJson();

        $token = config('services.internal_api.token');

        return is_string($token) && $token !== '' ? $cliente->withToken($token) : $cliente;
    }
}
