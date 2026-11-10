<?php

namespace App\Modules\Shared\Services;

use Illuminate\Support\Facades\DB;

/** Correspondencia estructural de sólo lectura; no certifica equivalencia de registros. */
class HistoricalSchemaReview
{
    public function inventory(): array
    {
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $tables = $connection->getDriverName() === 'sqlite' ? $schema->getTables() : $schema->getTables($connection->getDatabaseName());
        $known = array_map('strtolower', [...config('schema_tables'), 'migrations']);
        $result = [];
        foreach ($tables as $table) {
            $name = $table['name'];
            if (in_array(strtolower($name), $known, true)) continue;
            $target = match (true) {
                str_contains($name, 'credito_config') => 'clientes.limite_credito / g4_credit_events',
                str_contains($name, 'nota_credito'), str_contains($name, 'credito_venta') => 'g4_notas_credito / cuenta_corriente_movimientos',
                str_contains($name, 'devolucion') => 'g4_devoluciones / g4_devolucion_detalles',
                str_contains($name, 'integration_operation') => 'g3_stock_requests / g4_stock_operations',
                str_contains($name, 'secuencia') => 'Numeración vigente: revisar unicidad y comprobantes históricos',
                default => 'Sin equivalencia confirmada; conservar y verificar referencias',
            };
            $result[$name] = ['registros' => DB::table($name)->count(), 'columnas' => array_column($schema->getColumns($name), 'name'),
                'claves_foraneas' => $schema->getForeignKeys($name), 'destino_candidato' => $target,
                'migracion_automatica' => false, 'puede_eliminarse' => false,
                'requiere' => 'Documento/origen, cliente y venta verificables; moneda, signo y unidades; ausencia de efecto duplicado; ensayo en copia y aprobación por registro.'];
        }
        ksort($result);

        return $result;
    }
}
