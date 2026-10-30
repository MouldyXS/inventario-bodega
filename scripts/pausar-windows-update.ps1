<#
.SYNOPSIS
    Pausa indefinidamente las actualizaciones automáticas de Windows (o las reanuda con -Reanudar).

.DESCRIPTION
    Ejecutar en PowerShell COMO ADMINISTRADOR:

        powershell -ExecutionPolicy Bypass -File C:\inventario\scripts\pausar-windows-update.ps1
        powershell -ExecutionPolicy Bypass -File C:\inventario\scripts\pausar-windows-update.ps1 -Reanudar

    Aplica, mediante directivas del registro (válidas en Windows 10/11 Pro, Enterprise y LTSC):
      - desactiva la descarga e instalación automáticas (NoAutoUpdate);
      - impide reinicios automáticos con sesión iniciada;
      - marca la pausa de Configuración > Windows Update hasta 2099.
    No desactiva el servicio de Windows Update: las actualizaciones manuales siguen funcionando
    (Configuración > Windows Update > Buscar actualizaciones).
    Compatible con Windows PowerShell 5.1.
#>
[CmdletBinding()]
param([switch]$Reanudar)

$ErrorActionPreference = 'Stop'

$administrador = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $administrador) {
    Write-Host 'Abre PowerShell como administrador y vuelve a ejecutar este script.' -ForegroundColor Red
    exit 1
}

$directivas = 'HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU'
$ux = 'HKLM:\SOFTWARE\Microsoft\WindowsUpdate\UX\Settings'
$clavesPausa = 'PauseUpdatesStartTime', 'PauseUpdatesExpiryTime', 'PauseFeatureUpdatesStartTime', 'PauseFeatureUpdatesEndTime',
    'PauseQualityUpdatesStartTime', 'PauseQualityUpdatesEndTime'

if ($Reanudar) {
    foreach ($nombre in 'NoAutoUpdate', 'NoAutoRebootWithLoggedOnUsers') {
        Remove-ItemProperty -Path $directivas -Name $nombre -ErrorAction SilentlyContinue
    }
    foreach ($nombre in $clavesPausa) { Remove-ItemProperty -Path $ux -Name $nombre -ErrorAction SilentlyContinue }
    Write-Host 'Actualizaciones automáticas reanudadas.' -ForegroundColor Green
    exit 0
}

if (-not (Test-Path $directivas)) { New-Item -Path $directivas -Force | Out-Null }
Set-ItemProperty -Path $directivas -Name 'NoAutoUpdate' -Value 1 -Type DWord
Set-ItemProperty -Path $directivas -Name 'NoAutoRebootWithLoggedOnUsers' -Value 1 -Type DWord

if (-not (Test-Path $ux)) { New-Item -Path $ux -Force | Out-Null }
$inicio = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
$fin = '2099-12-31T00:00:00Z'
Set-ItemProperty -Path $ux -Name 'PauseUpdatesStartTime' -Value $inicio
Set-ItemProperty -Path $ux -Name 'PauseUpdatesExpiryTime' -Value $fin
Set-ItemProperty -Path $ux -Name 'PauseFeatureUpdatesStartTime' -Value $inicio
Set-ItemProperty -Path $ux -Name 'PauseFeatureUpdatesEndTime' -Value $fin
Set-ItemProperty -Path $ux -Name 'PauseQualityUpdatesStartTime' -Value $inicio
Set-ItemProperty -Path $ux -Name 'PauseQualityUpdatesEndTime' -Value $fin

Write-Host 'Actualizaciones automáticas pausadas indefinidamente.' -ForegroundColor Green
Write-Host 'Para actualizar a mano: Configuración > Windows Update > Buscar actualizaciones.'
Write-Host 'Para reanudarlas: este script con -Reanudar.'
