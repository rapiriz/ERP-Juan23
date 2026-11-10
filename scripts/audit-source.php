<?php

/** Control portátil de rutas PSR-4, migraciones repetidas y conexiones peligrosas de tests. */
$root = dirname(__DIR__);
$failures = [];
$canonicalTables = [];
foreach (require $root.'/config/schema_tables.php' as $table) {
    $canonicalTables[strtolower($table)] = $table;
}
foreach (['app', 'tests'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if ($directory === 'app' && preg_match_all('/(?:exists|unique):([A-Za-z_][A-Za-z0-9_]*)/', $source, $rules)) {
            foreach ($rules[1] as $table) {
                $expected = $canonicalTables[strtolower($table)] ?? $table;
                if ($table !== $expected) {
                    $failures[] = 'Validador SQL/case: '.$file->getPathname().' '.$table.' -> '.$expected;
                }
            }
        }
        if ($directory === 'tests' && preg_match('/new\s+\\?PDO\s*\(/', $source)) {
            $failures[] = 'PDO directo en tests: '.$file->getPathname();
        }
        if (preg_match('/namespace\s+([^;]+);/', $source, $ns) && preg_match('/^(?:abstract |final )?(?:class|trait|interface|enum)\s+(\w+)/m', $source, $class)) {
            $prefix = $directory === 'app' ? 'App\\' : 'Tests\\';
            $expected = $root.'/'.$directory.'/'.str_replace('\\', '/', substr(trim($ns[1]).'\\'.$class[1], strlen($prefix))).'.php';
            if (str_replace('\\', '/', $file->getPathname()) !== str_replace('\\', '/', $expected)) {
                $failures[] = 'PSR-4/case: '.$expected;
            }
        }
    }
}
$names = [];
foreach (array_merge(glob($root.'/database/migrations/*.php'), glob($root.'/app/Modules/*/database/migrations/*.php')) as $file) {
    $name = basename($file);
    if (isset($names[$name])) {
        $failures[] = 'Migración duplicada: '.$name;
    } $names[$name] = $file;
}
echo json_encode(['errors' => $failures, 'migration_files' => count($names)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($failures ? 1 : 0);
