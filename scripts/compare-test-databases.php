<?php

/** Compara restauración QA sin imprimir datos ni leer .env. */
$source = getenv('ERP_G3_G4_TEST_DATABASE');
$target = getenv('ERP_RESTORE_TEST_DATABASE');
foreach ([$source, $target] as $name) {
    if (! is_string($name) || ! preg_match('/^erp_(qa|test)_[a-z0-9_]+$/D', $name)) throw new RuntimeException('Sólo bases QA explícitas.');
}
if ($source === $target) throw new RuntimeException('Las bases deben ser distintas.');
$pdo = new PDO('mysql:host=127.0.0.1;port=3315;charset=utf8mb4', getenv('ERP_TEST_DB_USERNAME') ?: 'root', getenv('ERP_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = function ($database) use ($pdo) {
    $q = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
    $q->execute([$database]);return $q->fetchAll(PDO::FETCH_COLUMN);
};
$left = $tables($source);$right = $tables($target);
if (! $left || $left !== $right) throw new RuntimeException('No coinciden las tablas restauradas.');
$rows = 0;
foreach ($left as $table) {
    $hashes = function ($db) use ($pdo, $table, &$rows) {
        $quoted = str_replace('`','``',$table);
        $data = $pdo->query('SELECT * FROM `'.$db.'`.`'.$quoted.'`')->fetchAll(PDO::FETCH_ASSOC);
        $hashes = array_map(fn ($row) => hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)), $data);
        sort($hashes);return $hashes;
    };
    $a = $hashes($source);$b = $hashes($target);$rows += count($a);
    if ($a !== $b) throw new RuntimeException('Difieren las filas de '.$table);
}
echo json_encode(['tables' => count($left), 'rows' => $rows, 'differences' => 0], JSON_THROW_ON_ERROR).PHP_EOL;
