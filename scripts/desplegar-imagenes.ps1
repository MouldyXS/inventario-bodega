<#
.SYNOPSIS
    Activa en producción las imágenes de productos: php.ini, migración y reinicio de Apache.

.DESCRIPTION
    Ejecutar en PowerShell COMO ADMINISTRADOR, una vez:

        powershell -ExecutionPolicy Bypass -File C:\inventario\scripts\desplegar-imagenes.ps1

    Para ver qué haría sin cambiar nada (no hace falta ser administrador):

        powershell -ExecutionPolicy Bypass -File C:\inventario\scripts\desplegar-imagenes.ps1 -SoloComprobar

    Pasos:
      1. Lee C:\php\php.ini y muestra el estado actual (GD, WebP, EXIF, subida máxima, formulario, memoria).
      2. Guarda una copia (php.ini.antes-imagenes) y deja activas extension=gd y extension=exif,
         upload_max_filesize = 10M y post_max_size = 12M. memory_limit no se toca.
      3. Comprueba con el propio php.exe que todo quedó bien; si no, restaura la copia y se detiene.
      4. Aplica la migración pendiente (productos.imagen) con la cuenta inventario_migra.
      5. Reinicia Apache y comprueba que responde.
      6. Ejecuta php artisan inventario:verificar-permisos.
    Después hay que comprobar en la aplicación (Configuración > Imágenes de productos): ver docs/OPERACION.md.

    Es idempotente: si ya está todo, no cambia nada. Compatible con Windows PowerShell 5.1.

    -OmitirMigracion y -OmitirApache sirven para probar el script contra una copia de php.ini (-PhpIni).
#>
[CmdletBinding()]
param(
    [string]$PhpIni = 'C:\php\php.ini',
    [string]$Php = 'C:\php\php.exe',
    [string]$Proyecto = 'C:\inventario',
    [string]$Servicio = 'Apache2.4',
    [string]$UrlComprobacion = 'http://127.0.0.1:8080/up',
    [switch]$SoloComprobar,
    [switch]$OmitirMigracion,
    [switch]$OmitirApache
)

$ErrorActionPreference = 'Stop'
$latin1 = [Text.Encoding]::GetEncoding(28591)   # php.ini puede traer caracteres que no son UTF-8: se conserva byte a byte.
$copia = "$PhpIni.antes-imagenes"

function Ejecutar-Php([string]$codigo) {
    $temporal = [IO.Path]::GetTempFileName() + '.php'
    try {
        [IO.File]::WriteAllText($temporal, "<?php $codigo", (New-Object Text.UTF8Encoding($false)))
        # Los avisos de PHP (p. ej. una extensión que no carga) van a stderr: no deben detener el script.
        $anterior = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        try { $salida = & $Php -c $PhpIni $temporal 2>&1 } finally { $ErrorActionPreference = $anterior }
        return ($salida | ForEach-Object { "$_" } | Out-String).Trim()
    } finally { Remove-Item -LiteralPath $temporal -ErrorAction SilentlyContinue }
}

function Leer-Estado {
    $json = Ejecutar-Php 'echo json_encode(["gd" => extension_loaded("gd"), "webp" => function_exists("imagewebp") && (gd_info()["WebP Support"] ?? false), "exif" => function_exists("exif_read_data"), "subida" => ini_get("upload_max_filesize"), "post" => ini_get("post_max_size"), "memoria" => ini_get("memory_limit")]);'
    # La salida puede traer avisos de PHP antes del JSON: se toma solo el objeto.
    $objeto = [regex]::Match($json, '\{"gd".*\}')
    if (-not $objeto.Success) { throw "No se pudo leer el estado de PHP con $PhpIni. Salida: $json" }
    return $objeto.Value | ConvertFrom-Json
}

function Bytes([string]$valor) {
    $n = [int64]($valor -replace '[^\d]', '')
    switch (($valor.Trim().ToUpper()[-1])) { 'G' { return $n * 1GB } 'M' { return $n * 1MB } 'K' { return $n * 1KB } default { return $n } }
}

function Mostrar-Estado($e, [string]$titulo) {
    Write-Host $titulo -ForegroundColor Cyan
    Write-Host ('  GD: {0}   WebP: {1}   EXIF: {2}' -f $e.gd, $e.webp, $e.exif)
    Write-Host ('  Subida máxima: {0}   Formulario: {1}   Memoria: {2}' -f $e.subida, $e.post, $e.memoria)
}

function Esta-Listo($e) {
    return ($e.gd -and $e.webp -and $e.exif -and (Bytes $e.subida) -ge 10MB -and (Bytes $e.post) -ge 12MB)
}

if (-not (Test-Path -LiteralPath $PhpIni)) { throw "No existe $PhpIni." }
if (-not (Test-Path -LiteralPath $Php)) { throw "No existe $Php." }

$antes = Leer-Estado
Mostrar-Estado $antes 'Estado actual de PHP'

if (Esta-Listo $antes) {
    Write-Host 'php.ini ya está listo para las imágenes.' -ForegroundColor Green
} elseif ($SoloComprobar) {
    Write-Host 'Falta: extension=gd, extension=exif, upload_max_filesize = 10M y post_max_size = 12M. Ejecuta el script sin -SoloComprobar.' -ForegroundColor Yellow
}

if ($SoloComprobar) {
    if (-not $OmitirMigracion) {
        Push-Location $Proyecto
        try { & $Php artisan migrate:status --database=migraciones 2>&1 | Select-Object -Last 4 } finally { Pop-Location }
    }
    if (-not $OmitirApache) { Write-Host ("Apache ({0}): {1}" -f $Servicio, (Get-Service $Servicio).Status) }
    exit $(if (Esta-Listo $antes) { 0 } else { 2 })
}

if (-not $OmitirApache) {
    $administrador = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $administrador) {
        Write-Host 'Abre PowerShell como administrador y vuelve a ejecutar este script (o usa -SoloComprobar).' -ForegroundColor Red
        exit 1
    }
}

# 1-2. php.ini: copia y cambios. Cada directiva se activa donde ya está (comentada o no); si falta, se añade al final.
if (-not (Test-Path -LiteralPath $copia)) {
    Copy-Item -LiteralPath $PhpIni -Destination $copia
    Write-Host "Copia de seguridad: $copia"
}

$texto = [IO.File]::ReadAllText($PhpIni, $latin1)
$cambios = @()

function Fijar([string]$etiqueta, [string]$patron, [string]$linea) {
    # Si ya hay una línea activa con ese valor, no se toca; si hay otra activa, se reemplaza; si solo hay comentadas, se activa la primera.
    $activa = [regex]::Match($script:texto, "(?m)^[ \t]*$patron[^\r\n]*")
    if ($activa.Success) {
        if ($activa.Value.Trim() -ne $linea) {
            $script:texto = $script:texto.Remove($activa.Index, $activa.Length).Insert($activa.Index, $linea)
            $script:cambios += $etiqueta
        }
        return
    }
    $comentada = [regex]::Match($script:texto, "(?m)^[ \t]*;[ \t]*$patron[^\r\n]*")
    if ($comentada.Success) {
        $script:texto = $script:texto.Remove($comentada.Index, $comentada.Length).Insert($comentada.Index, $linea)
    } else {
        $script:texto = $script:texto.TrimEnd() + "`r`n$linea`r`n"
    }
    $script:cambios += $etiqueta
}

Fijar 'extension=gd' 'extension\s*=\s*gd\b' 'extension=gd'
Fijar 'extension=exif' 'extension\s*=\s*exif\b' 'extension=exif'
Fijar 'upload_max_filesize = 10M' 'upload_max_filesize\s*=' 'upload_max_filesize = 10M'
Fijar 'post_max_size = 12M' 'post_max_size\s*=' 'post_max_size = 12M'

if ($cambios.Count -gt 0) {
    [IO.File]::WriteAllText($PhpIni, $texto, $latin1)
    Write-Host ('php.ini actualizado: ' + ($cambios -join ', ')) -ForegroundColor Green
}

# 3. Comprobación con el propio PHP; si algo falla se vuelve atrás.
$despues = Leer-Estado
Mostrar-Estado $despues 'Estado de PHP tras los cambios'
if (-not (Esta-Listo $despues)) {
    Copy-Item -LiteralPath $copia -Destination $PhpIni -Force
    throw "PHP no quedó listo (¿faltan php_gd.dll o php_exif.dll en la carpeta ext?). Se restauró $PhpIni."
}

# 4. Migración (productos.imagen) con la cuenta de DDL.
if (-not $OmitirMigracion) {
    Push-Location $Proyecto
    try {
        Write-Host 'Migración:' -ForegroundColor Cyan
        & $Php artisan migrate --database=migraciones --force
        if ($LASTEXITCODE -ne 0) { throw "La migración falló (código $LASTEXITCODE). php.ini ya está actualizado; la aplicación sigue funcionando sin imágenes." }
    } finally { Pop-Location }
}

# 5. Apache: lee php.ini solo al arrancar.
if (-not $OmitirApache) {
    Restart-Service -Name $Servicio -ErrorAction Stop
    Start-Sleep -Seconds 4
    $estado = (Get-Service $Servicio).Status
    try { $codigo = (Invoke-WebRequest -Uri $UrlComprobacion -UseBasicParsing -TimeoutSec 15).StatusCode } catch { $codigo = 'sin respuesta' }
    Write-Host "Apache: $estado; $UrlComprobacion responde: $codigo"
    if ($estado -ne 'Running' -or $codigo -ne 200) {
        Write-Host "Apache no respondió bien. Para volver atrás: Copy-Item '$copia' '$PhpIni' -Force; Restart-Service $Servicio" -ForegroundColor Red
        exit 1
    }
}

# 6. Comprobación general de la aplicación (consola).
if (-not $OmitirMigracion) {
    Push-Location $Proyecto
    try { & $Php artisan inventario:verificar-permisos } finally { Pop-Location }
}

Write-Host ''
Write-Host 'Listo. Falta comprobarlo en la aplicación: Configuración > Imágenes de productos debe decir Sí / Sí / Sí y 10 MB / 12 MB;' -ForegroundColor Green
Write-Host 'después sube una foto desde la ficha de un producto. Para volver atrás: ver docs/OPERACION.md.'
exit 0
