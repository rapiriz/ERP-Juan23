<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SchemaInventory extends Command
{
    protected $signature = 'erp:inventario-esquema {--json} {--historicas : Sólo tablas sin contrato canónico, sin migrarlas}';

    protected $description = 'Inventaría tablas, columnas y relaciones sin cambiar estructura ni datos';

    public function handle(): int
    {
        $schema = DB::connection()->getSchemaBuilder();
        $inventory = [];
        $tables = DB::getDriverName() === 'sqlite' ? $schema->getTables() : $schema->getTables(DB::connection()->getDatabaseName());
        foreach ($tables as $table) {
            $name = $table['name'];
            $inventory[$name] = [
                'registros' => DB::table($name)->count(), 'columnas' => $schema->getColumns($name), 'claves_foraneas' => $schema->getForeignKeys($name)];
        }
        if ($this->option('historicas')) {
            $inventory = app(\App\Modules\Shared\Services\HistoricalSchemaReview::class)->inventory();
        }
        if ($this->option('json')) {
            $this->line(json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            foreach ($inventory as $name => $data) {
                $this->line($name.': '.$data['registros'].' registros; '.count($data['columnas']).' columnas');
            }
        }

        return self::SUCCESS;
    }
}
