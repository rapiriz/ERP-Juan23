<?php

namespace App\Modules\Productos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CategoryHierarchy
{
    public function requireParent(?int $id, ?int $parent): void
    {
        $seen = $id === null ? [] : [$id];
        while ($parent !== null) {
            if (in_array($parent, $seen, true) || count($seen) >= 8) {
                throw ValidationException::withMessages(['id_categoria_padre' => 'La categoría no puede formar un ciclo ni superar ocho niveles.']);
            }
            $seen[] = $parent;
            $row = DB::table('categoria')->where('id_categoria', $parent)->first();
            if (! $row) {
                throw ValidationException::withMessages(['id_categoria_padre' => 'La categoría superior no existe.']);
            }
            $parent = $row->id_categoria_padre === null ? null : (int) $row->id_categoria_padre;
        }
    }

    public function path(?int $id, ?array $records = null): array
    {
        if ($id === null || ! Schema::hasColumn('categoria', 'id_categoria_padre')) {
            return [];
        }
        $records ??= DB::table('categoria')->get()->keyBy('id_categoria')->all();
        $path = [];
        while ($id !== null && isset($records[$id]) && count($path) < 8 && ! isset($path[$id])) {
            $row = $records[$id];
            $path[$id] = ['id' => (string) $id, 'nombre' => $row->nombre];
            $id = $row->id_categoria_padre === null ? null : (int) $row->id_categoria_padre;
        }
        return array_values(array_reverse($path, true));
    }
}
