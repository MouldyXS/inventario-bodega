<#
.SYNOPSIS
    Prueba una copia de seguridad restaurándola en una base temporal (Requerimientos Caso 10).

.DESCRIPTION
    1. Toma la copia más reciente de D:\Respaldos\Inventario (o la indicada con -Archivo) y la descifra
       con la contraseña de cifrado que escribes (sin mostrarla).
    2. La carga en una base temporal "inventario_restauracion" (la base original NO se toca).
    3. Calcula una huella de la base original y de la restaurada: número de filas de cada tabla,
       existencia por lote y presentación, existencia total en unidades base y los movimientos.
    4. Muestra CORRECTO si coinciden o las diferencias, y borra la base temporal.

    Ejecútalo justo después de una copia (Start-ScheduledTask InventarioRespaldo): si se registraron
    movimientos entre la copia y la prueba, habrá diferencias esperables. Usa mysql con la cuenta root
    local (sin contraseña, como está hoy); si le pones contraseña, indica un archivo de opciones con
    -OpcionesMysql.

    Compatible con Windows PowerShell 5.1.
#>
[CmdletBinding()]
param(
    [string]$Destino = 'D:\Respaldos\Inventario',
    [string]$Archivo = '',
    [string]$BaseOriginal = 'inventario',
    [string]$BaseTemporal = 'inventario_restauracion',
    [string]$OpcionesMysql = '',
    [string]$ArchivoClave = '',
    [switch]$ConservarBase,
    [string]$Mysql = 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe',
    [string]$SieteZip = 'C:\Program Files\7-Zip\7z.exe'
)

$ErrorActionPreference = 'Stop'
$utf8SinBom = New-Object System.Text.UTF8Encoding($false)
$trabajo = Join-Path $env:TEMP ('inventario-restauracion-' + [guid]::NewGuid().ToString('N'))
$baseCreada = $false

function Invocar-Mysql([string[]]$argumentos) {
    $comunes = @()
    if ($OpcionesMysql) { $comunes += "--defaults-extra-file=$OpcionesMysql" } else { $comunes += @('-h', '127.0.0.1', '-u', 'root') }
    $comunes += '--default-character-set=utf8mb4'
    $salida = & $Mysql @comunes @argumentos
    if ($LASTEXITCODE -ne 0) { throw "mysql falló (código $LASTEXITCODE)." }
    return $salida
}

# Consulta de la huella (idéntica para las dos bases). Cada fila: nombre<TAB>valor.
$consultaHuella = @'
SET SESSION group_concat_max_len = 1073741824;
SELECT 'categorias', COUNT(*) FROM categorias
UNION ALL SELECT 'productos', COUNT(*) FROM productos
UNION ALL SELECT 'presentaciones', COUNT(*) FROM presentaciones
UNION ALL SELECT 'codigos_barras', COUNT(*) FROM codigos_barras
UNION ALL SELECT 'lotes', COUNT(*) FROM lotes
UNION ALL SELECT 'movimientos', COUNT(*) FROM movimientos
UNION ALL SELECT 'detalles_movimiento', COUNT(*) FROM detalles_movimiento
UNION ALL SELECT 'usuarios', COUNT(*) FROM users
UNION ALL SELECT 'existencia total (unidades base)', IFNULL(SUM(cantidad_base), 0) FROM detalles_movimiento
UNION ALL SELECT 'existencias lote y present. (huella)', MD5(IFNULL(GROUP_CONCAT(CONCAT(lote_id, ':', presentacion_id, ':', c) ORDER BY lote_id, presentacion_id SEPARATOR ';'), ''))
  FROM (SELECT lote_id, presentacion_id, SUM(cantidad) AS c FROM detalles_movimiento GROUP BY lote_id, presentacion_id HAVING SUM(cantidad) <> 0) AS e
UNION ALL SELECT 'movimientos (huella)', MD5(IFNULL(GROUP_CONCAT(CONCAT(id, '|', tipo, '|', fecha, '|', usuario_id, '|', token) ORDER BY id SEPARATOR ';'), ''))
  FROM movimientos
UNION ALL SELECT 'costos y factores (huella)', MD5(IFNULL(GROUP_CONCAT(CONCAT(id, '|', cantidad, '|', factor, '|', IFNULL(costo_unitario, '')) ORDER BY id SEPARATOR ';'), ''))
  FROM detalles_movimiento;
'@

try {
    # 1. Copia a probar.
    if (-not $Archivo) {
        $candidata = Get-ChildItem -LiteralPath $Destino -File -ErrorAction Stop |
            Where-Object { $_.Name -match '^inventario_\d{8}_\d{4}\.7z$' } |
            Sort-Object Name -Descending | Select-Object -First 1
        if (-not $candidata) { throw "No hay copias inventario_AAAAMMDD_HHMM.7z en $Destino." }
        $Archivo = $candidata.FullName
    }
    if (-not (Test-Path -LiteralPath $Archivo)) { throw "No existe $Archivo." }
    Write-Host "Copia a probar: $Archivo"

    if ($ArchivoClave) {
        $clave = (Get-Content -LiteralPath $ArchivoClave -Raw).Trim()
    } else {
        $segura = Read-Host -Prompt 'Contraseña de cifrado de las copias' -AsSecureString
        $puntero = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($segura)
        try { $clave = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($puntero) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($puntero) }
    }

    # 2. Descifrado y carga en la base temporal.
    New-Item -ItemType Directory -Path $trabajo | Out-Null
    & $SieteZip x "-p$clave" "-o$trabajo" -y $Archivo | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo descifrar la copia: ¿contraseña incorrecta o archivo dañado?' }
    $sql = Get-ChildItem -LiteralPath $trabajo -Filter '*.sql' | Select-Object -First 1
    if (-not $sql) { throw 'La copia no contiene un volcado .sql.' }
    Write-Host ('Volcado descifrado: {0} ({1:N0} bytes)' -f $sql.Name, $sql.Length)

    Invocar-Mysql @('-e', "DROP DATABASE IF EXISTS ``$BaseTemporal``; CREATE DATABASE ``$BaseTemporal`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;") | Out-Null
    $baseCreada = $true
    $rutaSql = $sql.FullName.Replace('\', '/')
    Invocar-Mysql @($BaseTemporal, '-e', "SOURCE $rutaSql") | Out-Null
    Write-Host "Cargado en la base temporal $BaseTemporal."

    # 3. Huellas.
    $original = @(Invocar-Mysql @('-N', '-B', $BaseOriginal, '-e', $consultaHuella))
    $restaurada = @(Invocar-Mysql @('-N', '-B', $BaseTemporal, '-e', $consultaHuella))

    # 4. Comparación.
    $diferencias = 0
    $filas = @()
    for ($i = 0; $i -lt $original.Count; $i++) {
        $o = $original[$i] -split "`t"
        $r = ($restaurada[$i] -split "`t")
        $igual = ($original[$i] -eq $restaurada[$i])
        if (-not $igual) { $diferencias++ }
        $filas += [pscustomobject]@{ Concepto = $o[0]; Original = $o[1]; Restaurada = $r[1]; Resultado = $(if ($igual) { 'igual' } else { 'DIFERENTE' }) }
    }
    $filas | Format-Table -AutoSize | Out-String | Write-Host

    if ($diferencias -eq 0) {
        Write-Host 'CORRECTO: la copia restaurada coincide con la base original (Caso 10).' -ForegroundColor Green
        $codigo = 0
    } else {
        Write-Host "HAY $diferencias DIFERENCIAS. Si se registraron movimientos después de la copia, son esperables; repite la prueba justo después de una copia nueva." -ForegroundColor Yellow
        $codigo = 2
    }
} catch {
    Write-Host ('ERROR: ' + $_.Exception.Message) -ForegroundColor Red
    $codigo = 1
} finally {
    if ($baseCreada -and -not $ConservarBase) {
        try { Invocar-Mysql @('-e', "DROP DATABASE IF EXISTS ``$BaseTemporal``;") | Out-Null; Write-Host "Base temporal $BaseTemporal eliminada." } catch { Write-Host "No se pudo borrar $BaseTemporal." -ForegroundColor Yellow }
    }
    if (Test-Path -LiteralPath $trabajo) { Remove-Item -LiteralPath $trabajo -Recurse -Force -ErrorAction SilentlyContinue }
}
exit $codigo
