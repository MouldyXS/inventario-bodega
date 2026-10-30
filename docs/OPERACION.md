# Operación del sistema

Guía corta para el administrador. Detalle de diseño en `Requerimientos.txt`; copias en `RESTAURACION.md`.

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
