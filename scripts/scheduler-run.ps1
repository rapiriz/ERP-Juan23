$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$phpExecutable = if ($env:ERP_PHP) { $env:ERP_PHP } else { 'C:\xampp\php\php.exe' }
$logFolder = Join-Path $projectRoot 'storage\logs'
$log = Join-Path $logFolder 'scheduler.log'
Set-Location -LiteralPath $projectRoot
& $phpExecutable (Join-Path $projectRoot 'artisan') schedule:run --no-interaction *>> $log
exit $LASTEXITCODE
