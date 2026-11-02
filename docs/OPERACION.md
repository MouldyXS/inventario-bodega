# Operación del sistema

Guía corta para el administrador. Detalle de diseño en `Requerimientos.txt`; copias en `RESTAURACION.md`. El uso diario de las pantallas (recibir, retirar, ajustes, usuarios) está en `GUIA_DE_USO.md`.

## Cómo está montado

| Pieza | Dónde |
|---|---|
| Aplicación | `C:\inventario`, servida por Apache (servicio `Apache2.4`) en `127.0.0.1:8080` |
| Base de datos | MySQL 8.4 (servicio `MySQL84`) en `127.0.0.1:3306`; la aplicación entra con `inventario_app` |
| Acceso | `tailscale serve` publica `https://<equipo>.<tailnet>.ts.net` solo dentro de la red Tailscale (sin Funnel) |
| Copias | `D:\Respaldos\Inventario`, dos veces al día |
| Servicios | Apache, MySQL y Tailscale arrancan solos con Windows, sin que nadie inicie sesión |

Entra siempre por la dirección `ts.net`, también desde este PC, no por `localhost`.

## Comprobaciones rápidas (PowerShell)

```
Get-Service Apache2.4, MySQL84, Tailscale
tailscale serve status
cd C:\inventario ; php artisan inventario:verificar-permisos
Get-PSDrive C        # espacio libre en C:
```

## Tema claro, oscuro y automático

El botón **Tema** (en la barra lateral, o en «Más» en la tablet) alterna entre automático, claro y oscuro. En **automático** sigue al sistema (la tablet), también si cambia mientras la aplicación está abierta; en claro u oscuro se queda fijo. La preferencia se guarda en ese navegador. En la barra lateral del PC, el botón junto al nombre la contrae a solo iconos.

## Si se pierde o roban la tablet

1. **Retírala de Tailscale:** consola de Tailscale (login.tailscale.com) > *Machines* > la tablet > *Remove*. Sin eso, la tablet sigue dentro de la red privada.
2. **Cierra sus sesiones** en el PC:
   ```
   cd C:\inventario
   php artisan inventario:cerrar-sesiones            # todas las sesiones
   php artisan inventario:cerrar-sesiones correo@x   # solo las de un usuario
   ```
3. En **Usuarios**, cambia la contraseña del usuario que la usaba (se cierran sus sesiones) o desactívalo.

## Si el PC está apagado o no hay Internet

La tablet no puede operar. Anota los movimientos en papel (producto, lote, caducidad, cantidad, fecha y hora, motivo) y regístralos después en la aplicación con la **fecha real** en que ocurrieron: el formulario admite fechas pasadas, nunca futuras.

## Tailscale

- **Caducidad de la clave del PC.** Por defecto las claves de los dispositivos caducan a los 180 días y el PC **dejaría de estar disponible sin aviso**. En la consola: *Machines* > el PC > menú *...* > **Disable key expiry**. En la tablet puedes dejarla activada (es una protección), sabiendo que tendrás que volver a iniciar sesión en Tailscale cada 180 días.
- **Revisión periódica:** en *Machines* solo deben aparecer tus dispositivos.
- **Sin sesión de usuario:** en Windows, Tailscale debe funcionar como servicio ("Run unattended"). Se comprueba con el reinicio de prueba.

## Reinicio de prueba (una vez)

1. Reinicia el PC y **no inicies sesión**.
2. A los 3 o 4 minutos, desde la tablet, abre `https://<equipo>.<tailnet>.ts.net` e inicia sesión en la aplicación.
3. Si no responde: inicia sesión en Windows y ejecuta las comprobaciones rápidas.

## Carga inicial de existencias (una vez)

1. Antes de empezar, crea en el catálogo todos los productos y presentaciones (cajas, paquetes) que vas a contar.
2. En la aplicación, como administrador: **Carga inicial > Descargar plantilla**. Trae una fila por cada presentación activa.
3. Cuenta en la bodega. Anota por cada presentación y lote: cantidad, código de lote (opcional) y caducidad (obligatoria si el producto la controla). Si un producto tiene varios lotes, repite su fila.
4. Completa el CSV en Excel (puedes guardarlo como CSV) y súbelo en **Revisar archivo**. Si hay errores, se listan por número de fila y no se guarda nada; corrige y vuelve a subir.
5. Revisa la tabla y pulsa **Confirmar y registrar**. Todo el archivo se registra junto o no se registra nada, con motivo «Carga inicial».
6. Compara con el físico en **Inventario**. Para corregir diferencias usa **Ajustar** en el producto: muestra lo registrado, tú escribes lo contado y queda como ajuste con motivo «Conteo físico».
7. Haz una copia nueva (`Start-ScheduledTask -TaskName InventarioRespaldo`) y repite la prueba de restauración.

## Imágenes de productos

El administrador puede añadir, cambiar o quitar una imagen en la ficha de cada producto; el resto de usuarios solo la ve. Se guardan en `C:\inventario\storage\app\private\productos` (privadas: solo se ven con sesión iniciada) y van en las copias (`config_….7z`).

**Activarlas en producción (una vez, recomendado):** abre PowerShell **como administrador** y ejecuta

```
powershell -ExecutionPolicy Bypass -File C:\inventario\scripts\desplegar-imagenes.ps1
```

El script guarda una copia (`php.ini.antes-imagenes`), deja activas las cuatro directivas de abajo, comprueba con el propio `php.exe` que quedaron bien (si no, restaura la copia y se detiene), aplica la migración `productos.imagen` con la cuenta `inventario_migra`, reinicia Apache y ejecuta `inventario:verificar-permisos`. Es idempotente. Con `-SoloComprobar` (no hace falta ser administrador) muestra qué falta sin cambiar nada. Hazlo en una hora tranquila: Apache se reinicia unos segundos.

Después comprueba en la aplicación, como administrador: **Configuración > Imágenes de productos** debe decir Sí / Sí / Sí y 10 MB / 12 MB; sube una foto desde la ficha de un producto (idealmente una hecha con la tablet) y comprueba que se ve bien orientada. Haz una copia (`Start-ScheduledTask -TaskName InventarioRespaldo`) y, cuando termine, ejecuta `scripts\probar-restauracion.ps1`: debe decir CORRECTO e «Imágenes: 1 correctas, 0 ausentes».

**Volver atrás** (si algo no va bien): en PowerShell como administrador, `Copy-Item C:\php\php.ini.antes-imagenes C:\php\php.ini -Force` y `Restart-Service Apache2.4`. La aplicación sigue funcionando sin imágenes. La columna `imagen` puede quedarse (no estorba); si de verdad hay que quitarla: `php artisan migrate:rollback --database=migraciones --step=1 --force`.

**Qué hace el script, a mano** (por si prefieres editar tú; `C:\php\php.ini` lo comparten la consola y Apache). Como administrador, edita el archivo y deja estas líneas así (sin `;` delante):

```
extension=gd
extension=exif
upload_max_filesize = 10M
post_max_size = 12M
```

- **gd**: genera la miniatura y la versión media en WebP (viene con PHP, no se instala nada).
- **exif**: corrige la orientación de las fotos de la cámara de la tablet; sin ella pueden verse giradas.
- **10M / 12M**: la imagen admite hasta 8 MB; el formulario necesita algo más de margen.
- `memory_limit` se deja en 128M: la aplicación calcula cuántos megapíxeles puede procesar con esa memoria (hasta 16 MP en JPEG y WebP; menos en PNG, que necesita casi el doble) y rechaza antes las fotos más grandes con un mensaje.

Después reinicia Apache (`Restart-Service Apache2.4`) y comprueba en **Configuración > Imágenes de productos** que todo dice «Sí» y 10 MB / 12 MB (eso es lo que ve Apache). `php artisan inventario:verificar-permisos` muestra lo mismo para la consola.

**Al cerrar sesión** la aplicación pide al navegador que borre su caché de este sitio (`Clear-Site-Data`): así otra persona en la misma tablet no ve imágenes guardadas. Borra toda la caché del sitio (también estilos y scripts, que se vuelven a descargar al entrar), no las cookies ni la preferencia de tema.

**Imágenes huérfanas** (por ejemplo, tras restaurar una base más antigua): `php artisan inventario:imagenes-huerfanas` las lista y `--borrar` las borra.

## Windows Update

Las actualizaciones automáticas están pausadas indefinidamente; se instalan a mano (*Configuración > Windows Update > Buscar actualizaciones*). Para aplicar la pausa en un PC nuevo, o deshacerla, en PowerShell como administrador:

```
powershell -ExecutionPolicy Bypass -File C:\inventario\scripts\pausar-windows-update.ps1
powershell -ExecutionPolicy Bypass -File C:\inventario\scripts\pausar-windows-update.ps1 -Reanudar
```

## Espacio en C:

C: guarda el sistema, MySQL y la aplicación. Los registros se limitan solos: Laravel conserva 14 días y los registros de Apache se rotan por día y la tarea de copias borra los de más de 30 días. Si C: baja de 5 GB libres, libera espacio.

## Mantenimiento

- **Tablas nuevas:** después de cada migración que cree tablas, ejecuta `database\sql\02_permisos_app.sql` como `root` y `php artisan inventario:verificar-permisos`.
- **Instalar o reinstalar las copias** (PowerShell como administrador, una vez): `powershell -ExecutionPolicy Bypass -File C:\inventario\scripts\instalar-respaldo.ps1`. Te pide la contraseña de `inventario_backup` y la de cifrado.
- **Reiniciar Apache** (administrador): `Restart-Service Apache2.4`. Necesario tras cambiar `httpd.conf`.
