# Copias de seguridad y restauración

Requerimientos RNF-05 y Caso 10. Todo lo de esta página se ejecuta en el PC del servidor.

## Qué se copia, dónde y cuándo

| Dato | Valor |
|---|---|
| Cuándo | Todos los días a las 13:00 y a las 23:30 (tarea programada `InventarioRespaldo`). Si el PC estaba apagado, se hace al encenderlo |
| Dónde | `D:\Respaldos\Inventario` (otro disco físico distinto de C:) |
| Retención | 30 días; lo anterior se borra solo |
| `inventario_AAAAMMDD_HHMM.7z` | Volcado de la base de datos `inventario` |
| `config_AAAAMMDD_HHMM.7z` | `.env`, `httpd.conf`, `my.ini` y el **código del proyecto** (`codigo_….bundle`, un repositorio de Git) |
| Cifrado | 7-Zip, AES-256, también los nombres de los archivos |

**La contraseña de cifrado no está en ningún lugar recuperable de este PC.** Guárdala fuera de él (un gestor de contraseñas, o impresa en un lugar seguro). **Sin ella, las copias no se pueden abrir ni restaurar.**

**Qué cubre y qué no.** Las copias están en el mismo equipo. Cubren un fallo del disco C:, un error de software, datos dañados o un borrado accidental. **No** cubren robo, incendio, un fallo eléctrico grave ni la pérdida del PC entero. La copia en la nube o en un disco externo está aplazada (§13 de la especificación); cuando se configure, solo hay que añadir el destino a la lista `-Destinos` de `scripts\respaldo.ps1`.

## Ver que las copias se están haciendo

- En la aplicación: **Configuración** muestra la última copia correcta, y el **Inicio** avisa al administrador si pasaron más de 36 horas.
- Registro: `C:\ProgramData\InventarioRespaldo\respaldo.log` (solo lo lee un administrador).
- Hacer una copia ahora, en PowerShell como administrador:
  ```
  Start-ScheduledTask -TaskName InventarioRespaldo
  ```

## Probar una copia (Caso 10)

Conviene hacerlo al instalar las copias, una vez al mes y después de cambios importantes. No toca la base real: restaura en una base temporal, compara y la borra.

1. Haz una copia nueva (comando anterior) y espera un minuto.
2. En PowerShell, sin necesidad de administrador:
   ```
   powershell -ExecutionPolicy Bypass -File C:\inventario\scripts\probar-restauracion.ps1
   ```
3. Escribe la contraseña de cifrado. El script descifra la copia más reciente, la carga en `inventario_restauracion` y compara con la base real: filas de cada tabla, existencia total, existencia por lote y presentación, movimientos y costos.
4. Debe terminar con **CORRECTO**. Si se registraron movimientos después de la copia, saldrán diferencias esperables: repite la prueba justo después de una copia nueva.

## Restaurar la base de datos (el PC está bien, los datos no)

1. Haz una copia de lo que quede, por si acaso: `Start-ScheduledTask -TaskName InventarioRespaldo`.
2. Descifra la copia elegida en una carpeta temporal:
   ```
   & "C:\Program Files\7-Zip\7z.exe" x -pCONTRASEÑA -oC:\Temp\restauracion D:\Respaldos\Inventario\inventario_AAAAMMDD_HHMM.7z
   ```
3. Detén la aplicación para que nadie registre nada: `Stop-Service Apache2.4` (administrador).
4. Recrea la base y carga el volcado (cuenta `root`, local):
   ```
   & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -h 127.0.0.1 -u root -e "DROP DATABASE inventario; CREATE DATABASE inventario CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -h 127.0.0.1 -u root --default-character-set=utf8mb4 inventario -e "SOURCE C:/Temp/restauracion/inventario_AAAAMMDD_HHMM.sql"
   ```
5. Vuelve a ejecutar `database\sql\02_permisos_app.sql` como `root` (en Workbench) y comprueba:
   ```
   cd C:\inventario
   php artisan inventario:verificar-permisos
   ```
6. Arranca la aplicación (`Start-Service Apache2.4`), entra y revisa Inventario e Historial.
7. Borra la carpeta temporal `C:\Temp\restauracion`: contiene el volcado sin cifrar.

## Reconstruir el servidor (el disco C: o el PC se perdió, D: sigue sano)

1. Instala el software de §6.1 de la especificación: Windows, PHP 8.5 Thread Safe, Apache 2.4, MySQL 8.4 (servicio `MySQL84`), Composer, Git, Node, 7-Zip y Tailscale. La dirección `ts.net` puede cambiar si el PC tiene otro nombre.
2. Descifra `config_….7z` más reciente. Contiene `codigo_….bundle`, `.env`, `httpd.conf` y `my.ini`.
3. Recupera el código: `git clone codigo_….bundle C:\inventario`. Después, en esa carpeta: `composer install` y `npm ci` y `npm run build`.
4. Copia el `.env` a `C:\inventario\.env`, y `httpd.conf` y `my.ini` a sus carpetas (revisa que las rutas sigan siendo válidas).
5. En MySQL: ejecuta `database\sql\01_cuentas.sql` con **las mismas contraseñas** que lleva el `.env` (o cámbialas en el `.env`), restaura el volcado como arriba y ejecuta `02_permisos_app.sql`.
6. Arranca MySQL y Apache, y publica con `tailscale serve --bg http://127.0.0.1:8080` (actualiza `APP_URL` si la dirección cambió).
7. Vuelve a instalar las copias: `scripts\instalar-respaldo.ps1`.
8. Comprueba con `php artisan inventario:verificar-permisos` y `php artisan test`.
