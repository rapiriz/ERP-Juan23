<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'erp:backup {directory : Directorio privado fuera de public}';

    protected $description = 'Genera un respaldo SQL sin exponer credenciales en argumentos';

    public function handle(): int
    {
        $directory = (string) $this->argument('directory');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
            $this->error('No se pudo crear el directorio.');

            return self::FAILURE;
        }
        $path = realpath($directory);
        $public = realpath(public_path());
        if ($path === $public || str_starts_with($path, $public.DIRECTORY_SEPARATOR)) {
            $this->error('No guardar respaldos dentro de public.');

            return self::FAILURE;
        }
        $db = DB::connection();
        if (! in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('Este respaldo requiere MySQL/MariaDB.');

            return self::FAILURE;
        }
        $file = $path.'/erp-'.now()->format('Ymd-His').'-'.Str::random(6).'.sql';
        $binary = getenv('ERP_MYSQLDUMP') ?: (is_file('C:/xampp/mysql/bin/mysqldump.exe') ? 'C:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump');
        $config = $db->getConfig();
        $process = new Process([$binary, '-h', $config['host'], '-P', (string) $config['port'], '-u', $config['username'],
            '--single-transaction', '--routines', '--triggers', '--hex-blob', '--result-file='.$file, $config['database']], base_path(), ['MYSQL_PWD' => $config['password'] ?? '']);
        $process->setTimeout(300);
        $process->run();
        if (! $process->isSuccessful()) {
            @unlink($file);
            $this->error('Falló mysqldump. Revisar permisos/conexión; no se generó un respaldo válido.');

            return self::FAILURE;
        }
        chmod($file, 0600);
        file_put_contents($file.'.json', json_encode(['database' => $config['database'], 'created_at' => now()->toIso8601String(), 'sha256' => hash_file('sha256', $file)], JSON_THROW_ON_ERROR));
        chmod($file.'.json', 0600);
        $this->info('Respaldo: '.$file);
        $this->comment('Respaldar también .env/APP_KEY y storage/app/private. Cifrar y guardar fuera del servidor; el SQL contiene datos personales.');

        return self::SUCCESS;
    }
}
