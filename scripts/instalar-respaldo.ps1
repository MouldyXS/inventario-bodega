<#
.SYNOPSIS
    Instala las copias de seguridad programadas del inventario (Requerimientos RNF-05).

.DESCRIPTION
    Ejecutar UNA vez en una ventana de PowerShell abierta COMO ADMINISTRADOR:

        powershell -ExecutionPolicy Bypass -File C:\inventario\scripts\instalar-respaldo.ps1

    Pregunta dos contraseñas sin mostrarlas (no pasan por ningún otro programa):
      - la de la cuenta MySQL inventario_backup;
      - la de cifrado de las copias. GUÁRDALA FUERA DE ESTE PC: sin ella las copias no se pueden abrir.

    Crea C:\ProgramData\InventarioRespaldo con permisos solo para SYSTEM y Administradores, la carpeta de
    destino, y la tarea programada "InventarioRespaldo" (13:00 y 23:30, como SYSTEM, aunque nadie haya
    iniciado sesión; si el PC estaba apagado a la hora, se ejecuta al encenderse). Después ejecuta una
    primera copia y muestra el resultado.
#>
[CmdletBinding()]
param(
    [string]$Secretos = 'C:\ProgramData\InventarioRespaldo',
    [string]$Destino = 'D:\Respaldos\Inventario',
    [string]$Script = 'C:\inventario\scripts\respaldo.ps1',
    [string]$Estado = 'C:\inventario\storage\app\respaldo-estado.json',
    [switch]$SinPrimeraCopia
)

$ErrorActionPreference = 'Stop'
$utf8SinBom = New-Object System.Text.UTF8Encoding($false)

$administrador = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $administrador) {
    Write-Host 'Abre PowerShell como administrador (clic derecho > Ejecutar como administrador) y vuelve a ejecutar este script.' -ForegroundColor Red
    exit 1
}
foreach ($ruta in @($Script, 'C:\Program Files\7-Zip\7z.exe', 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqldump.exe')) {
    if (-not (Test-Path -LiteralPath $ruta)) { Write-Host "No existe $ruta" -ForegroundColor Red; exit 1 }
}
if (-not (Test-Path -LiteralPath (Split-Path -Qualifier $Destino))) {
    Write-Host "La unidad $(Split-Path -Qualifier $Destino) no existe." -ForegroundColor Red
    exit 1
}

function Leer-Secreto([string]$pregunta) {
    $segura = Read-Host -Prompt $pregunta -AsSecureString
    $puntero = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($segura)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($puntero) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($puntero) }
}

Write-Host ''
$claveMysql = Leer-Secreto 'Contraseña de la cuenta MySQL inventario_backup'
$clave1 = Leer-Secreto 'Contraseña de CIFRADO de las copias (guárdala fuera de este PC)'
$clave2 = Leer-Secreto 'Repite la contraseña de cifrado'
if ([string]::IsNullOrEmpty($claveMysql) -or [string]::IsNullOrEmpty($clave1)) { Write-Host 'Las contraseñas no pueden estar vacías.' -ForegroundColor Red; exit 1 }
if ($clave1 -ne $clave2) { Write-Host 'Las contraseñas de cifrado no coinciden.' -ForegroundColor Red; exit 1 }
if ($clave1.Length -lt 12) { Write-Host 'La contraseña de cifrado debe tener al menos 12 caracteres.' -ForegroundColor Red; exit 1 }
if ($clave1 -match '["^%!\s]') { Write-Host 'La contraseña de cifrado no puede llevar espacios ni los caracteres " ^ % ! (dan problemas al pasarla a 7-Zip).' -ForegroundColor Red; exit 1 }

# Carpeta de secretos: solo SYSTEM y Administradores (por SID, válido en cualquier idioma de Windows).
if (-not (Test-Path -LiteralPath $Secretos)) { New-Item -ItemType Directory -Path $Secretos | Out-Null }
& icacls $Secretos /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'No se pudieron fijar los permisos de la carpeta de secretos.' }

# Archivo de opciones de mysqldump (la contraseña nunca va en la línea de comandos).
$escapada = $claveMysql.Replace('\', '\\').Replace('"', '\"')
$cnf = "[client]`r`nuser=inventario_backup`r`nhost=127.0.0.1`r`npassword=`"$escapada`"`r`n"
[System.IO.File]::WriteAllText((Join-Path $Secretos 'mysql-backup.cnf'), $cnf, $utf8SinBom)
[System.IO.File]::WriteAllText((Join-Path $Secretos 'clave-cifrado.txt'), $clave1, $utf8SinBom)

# Destino: SYSTEM y Administradores con control total; tu usuario puede leer y copiar.
if (-not (Test-Path -LiteralPath $Destino)) { New-Item -ItemType Directory -Path $Destino -Force | Out-Null }
& icacls $Destino /grant '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' "$($env:USERNAME):(OI)(CI)M" | Out-Null

# Tarea programada.
$accion = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$Script`""
$disparadores = @((New-ScheduledTaskTrigger -Daily -At '13:00'), (New-ScheduledTaskTrigger -Daily -At '23:30'))
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$ajustes = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Hours 1) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName 'InventarioRespaldo' -Action $accion -Trigger $disparadores -Principal $principal -Settings $ajustes `
    -Description 'Copia de seguridad cifrada del inventario (RNF-05). Ver C:\inventario\scripts\respaldo.ps1' -Force | Out-Null

Write-Host ''
Write-Host 'Instalado: tarea "InventarioRespaldo" a las 13:00 y 23:30.' -ForegroundColor Green
Write-Host "Secretos: $Secretos   Copias: $Destino"

if (-not $SinPrimeraCopia) {
    Write-Host 'Ejecutando la primera copia...'
    if (Test-Path -LiteralPath $Estado) { Remove-Item -LiteralPath $Estado -Force }
    Start-ScheduledTask -TaskName 'InventarioRespaldo'
    $limite = (Get-Date).AddMinutes(3)
    while ((Get-Date) -lt $limite -and -not (Test-Path -LiteralPath $Estado)) { Start-Sleep -Seconds 2 }
    Start-Sleep -Seconds 1
    $registro = Join-Path $Secretos 'respaldo.log'
    if (Test-Path -LiteralPath $registro) { Get-Content -LiteralPath $registro -Tail 8 }
    if (Test-Path -LiteralPath $Estado) {
        $resultado = (Get-Content -LiteralPath $Estado -Raw).TrimStart([char]0xFEFF) | ConvertFrom-Json
        if ($resultado.ok) { Write-Host 'Primera copia correcta.' -ForegroundColor Green }
        else { Write-Host "La primera copia falló: $($resultado.error)" -ForegroundColor Red; exit 1 }
    } else {
        Write-Host 'No se obtuvo resultado en 3 minutos. Revisa el registro anterior.' -ForegroundColor Yellow
        exit 1
    }
}
