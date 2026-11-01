<#
.SYNOPSIS
    Copia de seguridad del inventario (Requerimientos RNF-05).

.DESCRIPTION
    Lo ejecuta el Programador de tareas como SYSTEM (tarea "InventarioRespaldo"), dos veces al día.
    1. Vuelca la base con mysqldump --single-transaction --no-tablespaces con la cuenta inventario_backup.
    2. Comprueba que el volcado terminó ("Dump completed").
    3. Comprime y cifra (7-Zip, AES-256, nombres incluidos) el volcado y, aparte, .env, httpd.conf y my.ini.
    4. Comprueba que cada archivo cifrado se puede abrir con la clave.
    5. Copia los archivos a cada destino y comprueba el tamaño.
    6. Borra copias y registros de Apache con más de N días.
    7. Escribe el estado en un JSON que la aplicación muestra en Configuración (decisión K3).

    No contiene contraseñas: se leen de $Secretos (permisos solo para SYSTEM y Administradores), que crea
    instalar-respaldo.ps1. Para añadir destinos (otro disco, la nube) basta con añadirlos a -Destinos.
    Compatible con Windows PowerShell 5.1.
#>
[CmdletBinding()]
param(
    [string]$Secretos = 'C:\ProgramData\InventarioRespaldo',
    [string[]]$Destinos = @('D:\Respaldos\Inventario'),
    [string]$Base = 'inventario',
    [string]$EstadoJson = 'C:\inventario\storage\app\respaldo-estado.json',
    [int]$DiasRetencion = 30,
    [string]$CarpetaRegistrosApache = 'C:\Apache24\logs',
    # Archivos y carpetas que van cifrados en config_….7z; la carpeta de imágenes de productos se guarda como productos\{id}\….
    [string[]]$ArchivosConfig = @('C:\inventario\.env', 'C:\Apache24\conf\httpd.conf', 'C:\ProgramData\MySQL\MySQL Server 8.4\my.ini', 'C:\inventario\storage\app\private\productos'),
    [string]$MysqlDump = 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqldump.exe',
    [string]$SieteZip = 'C:\Program Files\7-Zip\7z.exe',
    [string]$Git = 'C:\Program Files\Git\cmd\git.exe',
    [string]$Repositorio = 'C:\inventario'
)

$ErrorActionPreference = 'Stop'
$utf8SinBom = New-Object System.Text.UTF8Encoding($false)
$registro = Join-Path $Secretos 'respaldo.log'
$trabajo = Join-Path $Secretos 'tmp'

function Escribir-Registro([string]$mensaje) {
    $linea = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $mensaje
    if ((Test-Path -LiteralPath $registro) -and ((Get-Item -LiteralPath $registro).Length -gt 1MB)) {
        Move-Item -LiteralPath $registro -Destination "$registro.old" -Force
    }
    [System.IO.File]::AppendAllText($registro, $linea + "`r`n", $utf8SinBom)
    Write-Host $linea
}

function Leer-EstadoAnterior {
    if (-not (Test-Path -LiteralPath $EstadoJson)) { return $null }
    try {
        $texto = (Get-Content -LiteralPath $EstadoJson -Raw).TrimStart([char]0xFEFF)
        return ($texto | ConvertFrom-Json)
    } catch { return $null }
}

function Escribir-Estado([bool]$ok, [string]$archivo, [long]$bytes, [string]$mensajeError) {
    $ahora = (Get-Date).ToString('o')
    $anterior = Leer-EstadoAnterior
    $ultimoOk = $null
    if ($ok) { $ultimoOk = $ahora } elseif ($anterior -ne $null) { $ultimoOk = $anterior.ultimo_ok }

    $estado = [ordered]@{
        ultimo_intento = $ahora
        ok             = $ok
        ultimo_ok      = $ultimoOk
        archivo        = $archivo
        bytes          = $bytes
        error          = $mensajeError
    }
    $carpeta = Split-Path -Parent $EstadoJson
    if (-not (Test-Path -LiteralPath $carpeta)) { New-Item -ItemType Directory -Path $carpeta -Force | Out-Null }
    # Sin BOM: la aplicación lee el JSON con PHP.
    [System.IO.File]::WriteAllText($EstadoJson, ($estado | ConvertTo-Json), $utf8SinBom)
}

function Ejecutar-Nativo([string]$exe, [string[]]$argumentos, [string]$descripcion) {
    & $exe @argumentos | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "$descripcion falló (código $LASTEXITCODE)." }
}

$exito = $false
$nombreArchivo = ''
$tamano = 0

try {
    foreach ($ruta in @($MysqlDump, $SieteZip)) {
        if (-not (Test-Path -LiteralPath $ruta)) { throw "No existe $ruta." }
    }
    $opcionesMysql = Join-Path $Secretos 'mysql-backup.cnf'
    $archivoClave = Join-Path $Secretos 'clave-cifrado.txt'
    foreach ($ruta in @($opcionesMysql, $archivoClave)) {
        if (-not (Test-Path -LiteralPath $ruta)) { throw "Falta ${ruta}: ejecuta instalar-respaldo.ps1." }
    }
    $clave = (Get-Content -LiteralPath $archivoClave -Raw).Trim()
    if ([string]::IsNullOrEmpty($clave)) { throw 'La clave de cifrado está vacía.' }

    if (Test-Path -LiteralPath $trabajo) { Remove-Item -LiteralPath $trabajo -Recurse -Force }
    New-Item -ItemType Directory -Path $trabajo | Out-Null

    $marca = Get-Date -Format 'yyyyMMdd_HHmm'
    Escribir-Registro "Inicio de la copia $marca"

    # 1-2. Volcado y comprobación de que terminó.
    $volcado = Join-Path $trabajo "${Base}_$marca.sql"
    Ejecutar-Nativo $MysqlDump @("--defaults-extra-file=$opcionesMysql", '--single-transaction', '--no-tablespaces', '--triggers',
        '--default-character-set=utf8mb4', "--result-file=$volcado", $Base) 'mysqldump'
    if (-not (Test-Path -LiteralPath $volcado) -or (Get-Item -LiteralPath $volcado).Length -eq 0) { throw 'El volcado está vacío.' }
    if (((Get-Content -LiteralPath $volcado -Tail 3) -join "`n") -notmatch 'Dump completed') { throw 'El volcado no terminó ("Dump completed" ausente).' }

    # 3-4. Cifrado y comprobación de la clave.
    $nombreArchivo = "${Base}_$marca.7z"
    $archivoBase = Join-Path $trabajo $nombreArchivo
    Ejecutar-Nativo $SieteZip @('a', '-t7z', '-mx=5', "-p$clave", '-mhe=on', $archivoBase, $volcado) '7-Zip (base de datos)'
    Ejecutar-Nativo $SieteZip @('t', "-p$clave", $archivoBase) 'Comprobación del archivo de la base de datos'

    $archivoConfig = Join-Path $trabajo "config_$marca.7z"
    $existentes = @($ArchivosConfig | Where-Object { Test-Path -LiteralPath $_ })
    foreach ($faltante in @($ArchivosConfig | Where-Object { -not (Test-Path -LiteralPath $_) })) { Escribir-Registro "Aviso: no existe $faltante" }

    # El repositorio de código solo existe en este PC: se incluye como paquete de Git (sin .env ni vendor).
    # No es fatal: un fallo de Git no debe impedir la copia de la base de datos.
    $paquete = Join-Path $trabajo "codigo_$marca.bundle"
    try {
        if (-not (Test-Path -LiteralPath $Git)) { throw "No existe $Git." }
        & $Git -c "safe.directory=$($Repositorio.Replace('\', '/'))" -C $Repositorio bundle create $paquete --all 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $paquete)) { throw "git bundle falló (código $LASTEXITCODE)." }
        $existentes += $paquete
    } catch {
        Escribir-Registro ('Aviso: no se incluyó el código del proyecto: ' + $_.Exception.Message)
    }

    if ($existentes.Count -eq 0) { throw 'Ningún archivo de configuración para copiar.' }
    Ejecutar-Nativo $SieteZip (@('a', '-t7z', '-mx=5', "-p$clave", '-mhe=on', $archivoConfig) + $existentes) '7-Zip (configuración)'
    Ejecutar-Nativo $SieteZip @('t', "-p$clave", $archivoConfig) 'Comprobación del archivo de configuración'

    $tamano = (Get-Item -LiteralPath $archivoBase).Length

    # 5. Destinos.
    foreach ($destino in $Destinos) {
        if (-not (Test-Path -LiteralPath $destino)) { New-Item -ItemType Directory -Path $destino -Force | Out-Null }
        foreach ($origen in @($archivoBase, $archivoConfig)) {
            $copia = Join-Path $destino (Split-Path -Leaf $origen)
            Copy-Item -LiteralPath $origen -Destination $copia -Force
            if ((Get-Item -LiteralPath $copia).Length -ne (Get-Item -LiteralPath $origen).Length) { throw "La copia en $destino no coincide en tamaño." }
        }
        Escribir-Registro "Copiado a $destino ($nombreArchivo, $tamano bytes)"

        # 6. Retención.
        $limite = (Get-Date).AddDays(-$DiasRetencion)
        $viejos = @(Get-ChildItem -LiteralPath $destino -File | Where-Object { $_.Name -match '^(inventario|config)_\d{8}_\d{4}\.7z$' -and $_.LastWriteTime -lt $limite })
        foreach ($viejo in $viejos) { Remove-Item -LiteralPath $viejo.FullName -Force }
        if ($viejos.Count -gt 0) { Escribir-Registro "Borradas $($viejos.Count) copias de más de $DiasRetencion días en $destino" }
    }

    # Registros de Apache rotados (access-AAAAMMDD.log), de más de N días.
    if (Test-Path -LiteralPath $CarpetaRegistrosApache) {
        $limite = (Get-Date).AddDays(-$DiasRetencion)
        Get-ChildItem -LiteralPath $CarpetaRegistrosApache -File |
            Where-Object { $_.Name -match '-\d{8}\.log$' -and $_.LastWriteTime -lt $limite } |
            Remove-Item -Force
    }

    $exito = $true
    Escribir-Registro 'Copia terminada correctamente'
} catch {
    Escribir-Registro ('ERROR: ' + $_.Exception.Message)
    try { Escribir-Estado $false $nombreArchivo 0 $_.Exception.Message } catch { }
} finally {
    if (Test-Path -LiteralPath $trabajo) { Remove-Item -LiteralPath $trabajo -Recurse -Force -ErrorAction SilentlyContinue }
}

if ($exito) {
    Escribir-Estado $true $nombreArchivo $tamano ''
    exit 0
}
exit 1
