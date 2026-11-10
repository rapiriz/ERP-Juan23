<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SchemaCaseGuard
{
    /** Detecta conflictos antes de migrar; no renombra ni borra tablas. */
    public function conflicts(): array
    {
        $db = DB::connection();
        if (! in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
            return [];
        }
        $mode = (int) array_values((array) $db->selectOne('SELECT @@lower_case_table_names AS value'))[0];
        if ($mode !== 0) {
            return [];
        }
        $expected = array_combine(array_map('strtolower', config('schema_tables')), config('schema_tables'));
        $conflicts = [];
        foreach ($db->select('SHOW FULL TABLES WHERE Table_type="BASE TABLE"') as $row) {
            $name = array_values((array) $row)[0];
            $canonical = $expected[strtolower($name)] ?? $name;
            if ($canonical !== $name) {
                $conflicts[] = ['actual' => $name, 'expected' => $canonical];
            }
        }

        return $conflicts;
    }
}
