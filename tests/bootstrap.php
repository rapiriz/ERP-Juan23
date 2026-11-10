<?php

require __DIR__.'/../vendor/autoload.php';

$appKey = 'base64:'.base64_encode(random_bytes(32));

$_ENV['APP_KEY'] = $appKey;

$_SERVER['APP_KEY'] = $appKey;

putenv('APP_KEY='.$appKey);

// Nunca cargar configuración cacheada del entorno habitual antes de registrar la guarda de SQL.
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/erp-test-config-'.bin2hex(random_bytes(8)).'.php'] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key.'='.$value);
}
