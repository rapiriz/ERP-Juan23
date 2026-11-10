param([switch]$Remove)
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$sha = [System.Security.Cryptography.SHA256]::Create()
$suffix = ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($projectRoot)))).Replace('-', '').Substring(0, 10)
$taskName = 'ERP-Juan23-' + $suffix
if ($Remove) { Unregister-ScheduledTask -TaskName $taskName -Confirm:$false; Write-Output ('Tarea retirada: ' + $taskName); exit }
if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) { Write-Output ('La tarea ya existe: ' + $taskName); exit }
$wrapper = Join-Path $PSScriptRoot 'scheduler-run.ps1'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $wrapper + '"') -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10)
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings | Select-Object TaskName,State
Write-Output 'Se ejecuta cada minuto mientras este usuario tenga sesión iniciada. Producción requiere cron/systemd o cuenta de servicio.'
