<?php

namespace App\Modules\Shared\Services;

use App\Modules\Clientes\Models\Cliente;
use App\Modules\Ventas\Contracts\ClienteGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio desacoplado para consultar Clientes desde el Grupo 2.
 * Consulta prioritariamente la tabla 'clientes' de la base de datos (G1),
 * y si aún no está poblada o falla, recurre a la API / Gateway de clientes del sistema (G4/ClienteGateway).
 */
class ClienteService
{
    /**
     * Busca clientes activos por término (nombre, razón social o DNI/CUIT)
     * Responde con la estructura normalizada que necesita el frontend de G2.
     */
    public static function buscarClientes(string $termino = '', int $limite = 20): array
    {
        $termino = trim($termino);

        try {
            // 1. Intentamos consultar la tabla 'clientes' (G1)
            $query = DB::table('clientes')
                ->select(['id', 'nombre', 'apellido_razon_social', 'dni_cuit', 'direccion', 'localidad', 'zona_id', 'estado'])
                ->where(function ($w) {
                    $w->where('estado', 'activo')
                      ->orWhereNull('estado')
                      ->orWhere('estado', '');
                });

            if ($termino !== '') {
                $query->where(function ($sub) use ($termino) {
                    $pattern = '%' . $termino . '%';
                    $sub->where('nombre', 'like', $pattern)
                        ->orWhere('apellido_razon_social', 'like', $pattern)
                        ->orWhere('dni_cuit', 'like', $pattern);
                    
                    if (is_numeric($termino)) {
                        $sub->orWhere('id', (int) $termino);
                    }
                });
            }

            $clientesG1 = $query->orderBy('apellido_razon_social')
                ->orderBy('nombre')
                ->limit($limite)
                ->get();

            if ($clientesG1->isNotEmpty()) {
                return $clientesG1->map(function ($c) {
                    $razon = trim((string) ($c->apellido_razon_social ?? ''));
                    $nombre = trim((string) ($c->nombre ?? ''));
                    $nombreCompleto = trim("{$razon} {$nombre}");
                    if (empty($nombreCompleto)) {
                        $nombreCompleto = $nombre ?: ($razon ?: "Cliente #{$c->id}");
                    }

                    return [
                        'id' => (int) $c->id,
                        'nombre' => $nombreCompleto,
                        'cuit' => $c->dni_cuit ?? '',
                        'direccion' => $c->direccion ?? '',
                        'localidad' => $c->localidad ?? '',
                        'etiqueta' => "#{$c->id} - {$nombreCompleto}" . (!empty($c->dni_cuit) ? " ({$c->dni_cuit})" : '')
                    ];
                })->all();
            }
        } catch (\Throwable $e) {
            Log::warning("G2 - ClienteService: Error al consultar tabla clientes: " . $e->getMessage());
        }

        // 2. Si la tabla clientes está vacía o no responde, consultamos a través del ClienteGateway oficial
        return self::buscarEnGateway($termino, $limite);
    }

    /**
     * Obtiene los datos básicos de un cliente por su ID.
     */
    public static function obtenerCliente(int $idCliente): ?array
    {
        try {
            $c = DB::table('clientes')->where('id', $idCliente)->first();
            if ($c) {
                $razon = trim((string) ($c->apellido_razon_social ?? ''));
                $nombre = trim((string) ($c->nombre ?? ''));
                $nombreCompleto = trim("{$razon} {$nombre}") ?: ($nombre ?: $razon);

                return [
                    'id' => (int) $c->id,
                    'nombre' => $nombreCompleto,
                    'cuit' => $c->dni_cuit ?? '',
                    'direccion' => $c->direccion ?? '',
                    'localidad' => $c->localidad ?? '',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("G2 - ClienteService: Error al obtener cliente #{$idCliente} en tabla: " . $e->getMessage());
        }

        // Fallback a ClienteGateway
        try {
            if (app()->bound(ClienteGateway::class)) {
                $gateway = app(ClienteGateway::class);
                $c = $gateway->buscar($idCliente);
                if ($c) {
                    return [
                        'id' => (int) $c['id'],
                        'nombre' => $c['nombre'] ?? "Cliente #{$idCliente}",
                        'cuit' => $c['cuit'] ?? '',
                        'direccion' => $c['domicilio'] ?? ($c['direccion'] ?? ''),
                        'localidad' => $c['localidad'] ?? '',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("G2 - ClienteService: Error al obtener cliente #{$idCliente} en gateway: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Búsqueda en el ClienteGateway (API/Mock oficial de la aplicación)
     */
    private static function buscarEnGateway(string $termino, int $limite): array
    {
        try {
            if (!app()->bound(ClienteGateway::class)) {
                return [];
            }

            $gateway = app(ClienteGateway::class);
            $todos = $gateway->todos();

            $terminoLower = mb_strtolower($termino);
            $resultados = [];

            foreach ($todos as $c) {
                $id = (int) ($c['id'] ?? 0);
                $nombre = (string) ($c['nombre'] ?? '');
                $cuit = (string) ($c['cuit'] ?? '');
                $direccion = (string) ($c['domicilio'] ?? ($c['direccion'] ?? ''));
                $localidad = (string) ($c['localidad'] ?? '');

                $coincide = empty($terminoLower)
                    || str_contains(mb_strtolower($nombre), $terminoLower)
                    || str_contains(mb_strtolower($cuit), $terminoLower)
                    || (is_numeric($termino) && $id === (int) $termino);

                if ($coincide) {
                    $resultados[] = [
                        'id' => $id,
                        'nombre' => $nombre,
                        'cuit' => $cuit,
                        'direccion' => $direccion,
                        'localidad' => $localidad,
                        'etiqueta' => "#{$id} - {$nombre}" . (!empty($cuit) ? " ({$cuit})" : '')
                    ];

                    if (count($resultados) >= $limite) {
                        break;
                    }
                }
            }

            return $resultados;
        } catch (\Throwable $e) {
            Log::warning("G2 - ClienteService: Error al buscar en ClienteGateway: " . $e->getMessage());
            return [];
        }
    }
}


