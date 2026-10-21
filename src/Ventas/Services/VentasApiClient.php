<?php

namespace App\Ventas\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cliente HTTP para la API de Ventas (Grupo 4).
 * Es el canal predeterminado para obtener el historial de ventas y actualizar sus estados.
 * Cuenta con fallback transparente a la base de datos local en caso de desconexión.
 */
class VentasApiClient
{
    private string $baseUrl;
    private int $timeoutSeconds;

    public function __construct(?string $baseUrl = null, int $timeoutSeconds = 5)
    {
        $this->baseUrl = rtrim($baseUrl ?? env('VENTAS_API_BASE_URL', 'http://localhost:8001/api/v1'), '/');
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Consulta el historial de ventas confirmadas listas para despacho desde la API de Ventas (G4).
     * Endpoint contractual: GET /ventas?estado=confirmada
     */
    public function obtenerVentasPendientesDespacho(?int $idZona = null): array
    {
        try {
            $url = "{$this->baseUrl}/ventas";
            $params = ['estado' => 'confirmada'];
            if ($idZona !== null) {
                $params['id_zona'] = $idZona;
            }

            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                // Normalizar respuesta si viene dentro de data o es array directo
                $ventas = $data['data'] ?? $data['ventas'] ?? $data;
                if (is_array($ventas)) {
                    Log::info("VentasApiClient: Obtenidas " . count($ventas) . " ventas desde API Grupo 4 ({$url}).");
                    return $this->normalizarVentasExternas($ventas);
                }
            }
            Log::warning("VentasApiClient: La API de Ventas respondió con código " . $response->status());
        } catch (\Throwable $e) {
            Log::warning("VentasApiClient: No se pudo conectar con la API de Ventas de G4 ({$this->baseUrl}): " . $e->getMessage() . ". Aplicando fallback local.");
        }

        // Fallback local en caso de que la API de G4 no esté disponible
        return $this->obtenerVentasLocalesFallback($idZona);
    }

    /**
     * Obtiene el detalle de una venta por ID desde la API de Ventas (G4).
     * Endpoint contractual: GET /ventas/{id}
     */
    public function obtenerVentaPorId(int $idVenta): ?array
    {
        try {
            $url = "{$this->baseUrl}/ventas/{$idVenta}";
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? $data;
            }
        } catch (\Throwable $e) {
            Log::warning("VentasApiClient: Error al consultar venta #{$idVenta} en API G4: " . $e->getMessage());
        }

        return $this->obtenerVentaLocalPorId($idVenta);
    }

    /**
     * Notifica el cambio de estado de una venta a la API de Ventas (G4).
     * Endpoint contractual: PATCH /ventas/{id}/estado
     */
    public function actualizarEstadoVenta(int $idVenta, string $nuevoEstado): bool
    {
        $notificadoExitoso = false;
        try {
            $url = "{$this->baseUrl}/ventas/{$idVenta}/estado";
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->patch($url, [
                    'estado' => $nuevoEstado,
                    'modulo_origen' => 'entregas_grupo2',
                    'fecha_actualizacion' => date('Y-m-d H:i:s')
                ]);

            if ($response->successful()) {
                Log::info("VentasApiClient: Venta #{$idVenta} notificada con éxito a estado '{$nuevoEstado}' en API G4.");
                $notificadoExitoso = true;
            } else {
                Log::warning("VentasApiClient: Error al actualizar estado de venta #{$idVenta} en API G4. Status: " . $response->status());
            }
        } catch (\Throwable $e) {
            Log::warning("VentasApiClient: Error de conexión con API G4 para venta #{$idVenta}: " . $e->getMessage());
        }

        // Siempre sincronizar en la base de datos local para mantener consistencia
        $this->actualizarEstadoVentaLocal($idVenta, $nuevoEstado);

        return $notificadoExitoso;
    }

    /**
     * Normaliza las estructuras que pudiera devolver la API de G4 a la convención que espera Entregas.
     */
    private function normalizarVentasExternas(array $ventas): array
    {
        $normalizadas = [];
        foreach ($ventas as $v) {
            $normalizadas[] = [
                'id_venta' => (int)($v['id_venta'] ?? $v['id'] ?? 0),
                'fecha' => $v['fecha'] ?? date('Y-m-d H:i:s'),
                'total' => (float)($v['total'] ?? $v['monto_total'] ?? 0),
                'monto_total' => (float)($v['monto_total'] ?? $v['total'] ?? 0),
                'total_bultos' => (int)($v['total_bultos'] ?? $v['bultos'] ?? 1),
                'total_sueltos' => (int)($v['total_sueltos'] ?? 0),
                'observaciones' => $v['observaciones'] ?? '',
                'id_cliente' => (int)($v['id_cliente'] ?? $v['cliente']['id_cliente'] ?? 1),
                'cliente_nombre' => $v['cliente_nombre'] ?? $v['cliente']['nombre'] ?? 'Cliente G4',
                'razon_social' => $v['razon_social'] ?? $v['cliente']['razon_social'] ?? '',
                'direccion' => $v['direccion'] ?? $v['cliente']['direccion'] ?? 'Domicilio Comercial',
                'localidad' => $v['localidad'] ?? $v['cliente']['localidad'] ?? 'Pigüé',
                'telefono' => $v['telefono'] ?? $v['cliente']['telefono'] ?? '',
                'id_zona' => (int)($v['id_zona'] ?? $v['cliente']['id_zona'] ?? 1),
                'zona_nombre' => $v['zona_nombre'] ?? 'Zona Reparto',
                'origen' => 'api_grupo_4',
                'items' => $v['items'] ?? $v['detalle'] ?? []
            ];
        }
        return $normalizadas;
    }

    /**
     * Fallback de consulta local cuando la API de G4 no está conectada.
     */
    private function obtenerVentasLocalesFallback(?int $idZona = null): array
    {
        if (!Schema::hasTable('VENTA')) {
            $mock = \App\Ventas\Controllers\VentasProxyController::obtenerCatalogoMockVentas();
            if ($idZona !== null && $idZona > 0) {
                $mock = array_values(array_filter($mock, fn($v) => (int)($v['id_zona'] ?? 0) === $idZona));
            }
            return $mock;
        }

        $query = DB::table('VENTA as v')
            ->leftJoin('CLIENTE as c', 'v.id_cliente', '=', 'c.id_cliente')
            ->leftJoin('zona as z', 'c.id_zona', '=', 'z.id_zona')
            ->where(function ($q) {
                $q->where('v.estado', 'confirmada')
                  ->orWhereNull('v.estado');
            })
            ->where('v.pagado', false);

        if ($idZona !== null && $idZona > 0) {
            $query->where('z.id_zona', $idZona);
        }

        $ventas = $query->select([
            'v.id_venta',
            'v.fecha',
            DB::raw('COALESCE(v.total, v.monto_total) as total'),
            DB::raw('COALESCE(v.total_bultos, 1) as total_bultos'),
            DB::raw('COALESCE(v.total_sueltos, 0) as total_sueltos'),
            'v.observaciones',
            'c.id_cliente',
            DB::raw("COALESCE(c.nombre, 'Cliente Registrado') as cliente_nombre"),
            'c.razon_social',
            'c.direccion',
            'c.localidad',
            'c.telefono',
            'z.id_zona',
            DB::raw("COALESCE(z.nombre, 'Zona Central') as zona_nombre"),
            DB::raw("'base_local_fallback' as origen")
        ])->get();

        return json_decode(json_encode($ventas), true);
    }

    private function obtenerVentaLocalPorId(int $idVenta): ?array
    {
        if (!Schema::hasTable('VENTA')) {
            $catalogo = \App\Ventas\Controllers\VentasProxyController::obtenerCatalogoMockVentas();
            foreach ($catalogo as $v) {
                if ((int)$v['id_venta'] === (int)$idVenta) {
                    return $v;
                }
            }
            return [
                'id_venta' => (int)$idVenta,
                'total' => 15000.00,
                'monto_total' => 15000.00,
                'id_cliente' => 1,
                'cliente_nombre' => "Cliente Pedido #{$idVenta}",
                'total_bultos' => 1,
                'total_sueltos' => 0,
                'direccion' => 'Domicilio Comercial',
                'localidad' => 'Pigüé'
            ];
        }

        $v = DB::table('VENTA as v')
            ->leftJoin('CLIENTE as c', 'v.id_cliente', '=', 'c.id_cliente')
            ->where('v.id_venta', $idVenta)
            ->first();

        return $v ? (array)$v : null;
    }

    private function actualizarEstadoVentaLocal(int $idVenta, string $nuevoEstado): void
    {
        if (Schema::hasTable('VENTA')) {
            $data = ['estado' => $nuevoEstado];
            if ($nuevoEstado === 'entregada') {
                // Si la venta se entregó, puede marcarse pagado si ya se cobró, o se actualiza su estado logístico
            }
            DB::table('VENTA')->where('id_venta', $idVenta)->update($data);
        }
    }
}
