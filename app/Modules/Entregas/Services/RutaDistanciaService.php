<?php

declare(strict_types=1);

namespace App\Modules\Entregas\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RutaDistanciaService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
    private const USER_AGENT = 'ERP-Juan23/1.0 (entregas-g2)';
    private const TABLE = 'g2_cliente_coordenadas';

    private bool $hizoConsultaExterna = false;

    public function normalizarDireccion(string $direccion, string $localidad = ''): string
    {
        $dir = trim($direccion);
        $loc = trim($localidad);

        // Quitar pisos, departamentos, entre calles y aclaraciones entre paréntesis
        $dir = preg_replace('/\([^)]*\)/u', '', $dir) ?? $dir;
        $dir = preg_replace('/\b(piso|dpto\.?|depto\.?|departamento|oficina|local)\s*[a-z0-9°º\-]+/iu', '', $dir) ?? $dir;
        $dir = preg_replace('/\bentre\s+.+$/iu', '', $dir) ?? $dir;

        // Expandir abreviaturas comunes para mejorar la tasa de acierto en OSM
        $reemplazos = [
            '/\bav\.?\b/iu' => 'Avenida',
            '/\bavda\.?\b/iu' => 'Avenida',
            '/\bgral\.?\b/iu' => 'General',
            '/\bbv\.?\b/iu' => 'Boulevard',
            '/\bpje\.?\b/iu' => 'Pasaje',
            '/\bsarmiento\b/iu' => 'Sarmiento',
        ];
        foreach ($reemplazos as $patron => $reemplazo) {
            $dir = preg_replace($patron, $reemplazo, $dir) ?? $dir;
        }

        $dir = trim(preg_replace('/\s+/u', ' ', $dir) ?? $dir, " ,\t\n\r\0\x0B");

        $partes = array_filter([$dir, $loc], fn (string $v): bool => $v !== '');
        $completo = implode(', ', $partes);

        if ($completo !== '' && stripos($completo, 'argentina') === false) {
            if (stripos($completo, 'buenos aires') === false) {
                $completo .= ', Provincia de Buenos Aires';
            }
            $completo .= ', Argentina';
        }

        return mb_substr($completo, 0, 255);
    }

    /**
     * Ordena los pedidos por proximidad encadenada (vecino más cercano)
     * partiendo desde la dirección origen de la distribuidora.
     *
     * @param  list<array<string, mixed>>  $pedidos
     * @return array{pedidos: list<array<string, mixed>>, geocodificados: int, sin_coordenadas: int, ordenado_por_distancia: bool}
     */
    public function ordenarPedidosPorDistancia(array $pedidos, string $origenDireccion): array
    {
        if (count($pedidos) <= 1) {
            return [
                'pedidos' => array_values($pedidos),
                'geocodificados' => count($pedidos),
                'sin_coordenadas' => 0,
                'ordenado_por_distancia' => false,
            ];
        }

        $origenCoords = $this->obtenerCoordenadas(0, $origenDireccion, '');
        if ($origenCoords === null) {
            return [
                'pedidos' => array_values($pedidos),
                'geocodificados' => 0,
                'sin_coordenadas' => count($pedidos),
                'ordenado_por_distancia' => false,
            ];
        }

        $conCoords = [];
        $sinCoords = [];

        foreach ($pedidos as $pedido) {
            $idCliente = (int) ($pedido['id_cliente'] ?? 0);
            $direccion = (string) ($pedido['direccion'] ?? '');
            $localidad = (string) ($pedido['localidad'] ?? '');

            $coords = ($idCliente > 0 && $direccion !== '')
                ? $this->obtenerCoordenadas($idCliente, $direccion, $localidad)
                : null;

            if ($coords !== null) {
                $conCoords[] = ['pedido' => $pedido, 'coords' => $coords];
            } else {
                $sinCoords[] = $pedido;
            }
        }

        $ordenados = [];
        $actualLat = $origenCoords['lat'];
        $actualLng = $origenCoords['lng'];
        $pendientes = $conCoords;

        while (count($pendientes) > 0) {
            $mejorIdx = 0;
            $mejorDist = INF;

            foreach ($pendientes as $idx => $item) {
                $d = $this->distanciaKm($actualLat, $actualLng, $item['coords']['lat'], $item['coords']['lng']);
                if ($d < $mejorDist) {
                    $mejorDist = $d;
                    $mejorIdx = $idx;
                }
            }

            $elegido = $pendientes[$mejorIdx];
            $pedidoElegido = $elegido['pedido'];
            $pedidoElegido['distancia_tramo_km'] = round($mejorDist, 2);
            $ordenados[] = $pedidoElegido;

            $actualLat = $elegido['coords']['lat'];
            $actualLng = $elegido['coords']['lng'];
            array_splice($pendientes, $mejorIdx, 1);
        }

        foreach ($sinCoords as $p) {
            $p['distancia_tramo_km'] = null;
            $ordenados[] = $p;
        }

        return [
            'pedidos' => $ordenados,
            'geocodificados' => count($conCoords),
            'sin_coordenadas' => count($sinCoords),
            'ordenado_por_distancia' => count($conCoords) > 0,
        ];
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function obtenerCoordenadas(int $idCliente, string $direccion, string $localidad): ?array
    {
        $normalizada = $this->normalizarDireccion($direccion, $localidad);
        if ($normalizada === '') {
            return null;
        }

        $tieneCache = Schema::hasTable(self::TABLE);
        if ($tieneCache) {
            $cache = DB::table(self::TABLE)->where('id_cliente', $idCliente)->first();
            if ($cache && $cache->direccion_normalizada === $normalizada && $cache->lat !== null && $cache->lng !== null) {
                return ['lat' => (float) $cache->lat, 'lng' => (float) $cache->lng];
            }
        }

        $coords = $this->consultarNominatim($normalizada);

        // Fallback: si no encontró con altura exacta, intentar solo calle + localidad
        if ($coords === null && $localidad !== '') {
            $soloCalle = preg_replace('/\s+\d{1,6}\b/u', '', trim($direccion)) ?? $direccion;
            $fallbackQuery = $this->normalizarDireccion($soloCalle, $localidad);
            if ($fallbackQuery !== '' && $fallbackQuery !== $normalizada) {
                $coords = $this->consultarNominatim($fallbackQuery);
            }
        }

        if ($tieneCache && $coords !== null) {
            DB::table(self::TABLE)->updateOrInsert(
                ['id_cliente' => $idCliente],
                [
                    'direccion_normalizada' => $normalizada,
                    'lat' => $coords['lat'],
                    'lng' => $coords['lng'],
                    'fuente' => 'nominatim',
                    'updated_at' => now(),
                ]
            );
        }

        return $coords;
    }

    public function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierra = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $radioTierra * asin(min(1.0, sqrt($a)));
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function consultarNominatim(string $query): ?array
    {
        if ($this->hizoConsultaExterna) {
            usleep(1050000);
        }
        $this->hizoConsultaExterna = true;

        try {
            $request = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(5);
            if (app()->isLocal()) {
                $request = $request->withoutVerifying();
            }

            $response = $request->get(self::NOMINATIM_URL, [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'ar',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            if (! is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
                return null;
            }

            return [
                'lat' => (float) $data[0]['lat'],
                'lng' => (float) $data[0]['lon'],
            ];
        } catch (\Throwable $e) {
            Log::warning('G2 - Nominatim geocoding error: ' . $e->getMessage());

            return null;
        }
    }
}
