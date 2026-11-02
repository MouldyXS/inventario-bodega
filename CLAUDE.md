# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Proyecto

Sistema de inventario para una sola bodega (Ecuador): productos, presentaciones (botella, caja…), lotes con caducidad, movimientos y alertas. Es una aplicación web privada que usan un administrador (PC de casa) y un encargado (tablet, por Tailscale).

**La especificación es [docs/Requerimientos.txt](docs/Requerimientos.txt) (v1.1) y manda sobre cualquier suposición.** Las secciones se citan como `§5.6`, `RF-03`, `Caso 21`, etc. También se citan en los comentarios del código.

Forma de trabajo acordada con el usuario:
- Se avanza por las **etapas de §11**. Cada etapa se planifica, se aprueba explícitamente, se implementa, se verifica con pruebas y se cierra antes de pasar a la siguiente.
- Al cerrar una etapa, se actualiza la tabla de **§13** (decisiones confirmadas y pendientes) en `docs/Requerimientos.txt` y se copia el archivo a una carpeta de documentos fuera del repositorio.
- No se amplía el alcance ni se reabren decisiones ya cerradas en §13. Todo se escribe en español: interfaz, mensajes, nombres de dominio y mensajes de commit.
- Se hace un commit por cada bloque verificado (en `main`, sin remoto).

Estado: las Etapas 1 a 8 están cerradas (incluye Usuarios, `inventario:cerrar-sesiones`, copias cifradas locales con `scripts/respaldo.ps1` y estado en JSON leído por la app, prueba de restauración, pausa de Windows Update; ver `docs/OPERACION.md` y `docs/RESTAURACION.md`). La Etapa 9 (carga inicial y validación) está en curso: `CargaInicialService` y la pantalla /carga-inicial (CSV, solo administrador) están hechas; falta el conteo y la carga reales del cliente, y cerrar la etapa. Pendiente de la Etapa 7: probar con un lector de códigos real cuando se compre. El rediseño de la interfaz (8 etapas) está cerrado; para activar las imágenes de producto en producción faltan la migración y habilitar `gd`/`exif` en `php.ini` (ver `docs/OPERACION.md`).

## Entorno (Windows 10 Enterprise LTSC 2021)

- **Apache 2.4** (servicio `Apache2.4`, `C:\Apache24`) sirve directamente `C:/inventario/public` en `http://127.0.0.1:8080`, con PHP como módulo. Los cambios en PHP o Blade se ven al instante. No se usa `php artisan serve`.
- **PHP 8.5 Thread Safe** en `C:\php`, con `C:\php\php.ini`. La CLI y Apache comparten ese `php.ini`.
- **MySQL 8.4** (servicio `MySQL84`). Cliente: `"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -h 127.0.0.1 -u root --skip-password`.
- **Cuentas de MySQL (§6.5):** la aplicación usa `inventario_app` (historial solo `SELECT` e `INSERT`), las migraciones `inventario_migra` y las pruebas `inventario_test`. Tras crear tablas nuevas hay que volver a ejecutar `database/sql/02_permisos_app.sql` como `root`, y comprobar con `php artisan inventario:verificar-permisos`. `root` sigue sin contraseña (solo local).
- **Acceso remoto:** `tailscale serve` publica `https://<equipo>.<tailnet>.ts.net` hacia Apache en 127.0.0.1:8080. `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con esa dirección y `SESSION_SECURE_COOKIE=true`; `bootstrap/app.php` confía en el proxy 127.0.0.1. Se administra desde la dirección `ts.net`, no desde `localhost`.
- Las contraseñas reales nunca pasan por Claude: las escribe el usuario en `.env` o en las preguntas de `usuarios:crear`.

## Comandos

```bash
php artisan test                                   # suite completa (MySQL, base inventario_test)
php artisan test --filter=AutenticacionTest        # una clase
php artisan test --filter=test_un_usuario_activo_inicia_sesion   # una prueba
php artisan test --testsuite=Unit                  # sin base de datos
vendor/bin/pint                                    # formato (pint --test solo comprueba)
npm run build                                      # recompilar CSS/JS (Bootstrap vía Vite) tras tocar resources/css o resources/js
php artisan migrate --database=migraciones         # migrar producción (conexión con la cuenta de DDL, §6.5)
php artisan usuarios:crear                         # crear usuarios (interactivo; pide la contraseña sin mostrarla)
php artisan inventario:imagenes-huerfanas          # imágenes de productos sin fila que las use (--borrar para borrarlas)
```

**Imágenes con GD/EXIF en desarrollo sin tocar `php.ini`** (lo comparten consola y Apache de producción): `php artisan test` lanza PHPUnit en un proceso hijo que **no** recibe las opciones `-d`, así que las pruebas de imágenes (que se omiten sin GD) se ejecutan con:

```bash
php -d extension=gd -d extension=exif vendor/phpunit/phpunit/phpunit
```

Servidor temporal con la base de pruebas y las extensiones (desde `public/`, porque el enrutador de Laravel toma `getcwd()` como carpeta pública; `artisan serve` tampoco reenvía `-d`):

```bash
cd public && APP_ENV=testing php -d extension=gd -d extension=exif -d upload_max_filesize=10M -d post_max_size=12M -S 127.0.0.1:8099 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Después de cambiar `.env` o algo en `config/`, conviene ejecutar `php artisan config:clear`.

## Pruebas

- `phpunit.xml` fuerza `DB_CONNECTION=mysql` y `DB_DATABASE=inventario_test`. Las credenciales están en `.env.testing`, que no está en Git. **Nunca se usa SQLite**: las restricciones `CHECK`, las columnas generadas, `FOR UPDATE` y el aislamiento dependen de MySQL.
- `tests/TestCase.php` aborta el proceso (`exit(1)`) antes de tocar la base si la conexión no es `mysql` o si la base no termina en `_test` (Caso 28). También llama a `withoutVite()`.
- Las pruebas normales usan `RefreshDatabase`. Las de concurrencia (§12) **no**, porque los otros procesos no verían los datos de la transacción:
  - usan `DatabaseTruncation` y además truncan en `tearDown()`, para no dejar datos a las clases con `RefreshDatabase`;
  - usan el trait `LanzaProcesos`, que lanza procesos PHP independientes con `Process::pool` sobre `tests/Concurrencia/trabajador.php` (entradas, salidas y reversiones). El trabajador arranca Laravel con `APP_ENV=testing`, comprueba la base con `TestCase::asegurarBaseDePruebas()` y espera a una marca de tiempo común para que los procesos coincidan;
  - conviene comprobar que la prueba detecta el problema quitando un momento la protección (bloqueo o manejo del 1062) y viendo que falla.
- Para fijar el reloj: `travelTo(...)` o `freezeTime()` sin argumentos. `freezeTime(callback)` solo congela durante el callback.
- Para revisar pantallas con datos sin tocar producción: cambiar temporalmente `DB_DATABASE` a `inventario_test` en `.env`, ejecutar `migrate:fresh` y cargar datos con `tinker`, y restaurar `.env` al terminar. La siguiente ejecución de la suite limpia esa base.

## Arquitectura y reglas transversales

**Existencias derivadas de un libro inmutable (§5.4–§5.7).**
- No hay tabla de saldos. Las existencias se calculan sumando `detalles_movimiento.cantidad` por par (lote, presentación), o `cantidad_base` por lote.
- `movimientos` y `detalles_movimiento` nunca se actualizan ni se borran. Las correcciones son movimientos nuevos (ajuste o reversión).
- `App\Services\InventarioService` es el único que escribe movimientos (`registrarEntrada`, `registrarSalida`, `registrarConversion`, `registrarAjuste`, `revertir`) y también corrige lotes (`corregirLote`). Todas las operaciones pasan por el mismo núcleo (`ejecutar`, `bloquearLotes`, `validarSinNegativos`, `guardar`), según §5.6:
  - token de idempotencia: mismo usuario y tipo = reenvío (`ResultadoMovimiento::$reenvio`); otro uso = error;
  - lotes obtenidos o creados dentro de la operación;
  - bloqueo `FOR UPDATE` solo sobre `lotes`, en orden de id, y lectura `FOR SHARE` del producto y las presentaciones;
  - validación de negativos por par (lote, presentación), sumando las variaciones de la operación y comparándolas con la existencia leída después del bloqueo;
  - clasificación de los errores 1062 según el índice que los provoca (`esIndice()`; por eso los índices únicos tienen nombre explícito en las migraciones).
- `CatalogoService` bloquea con `FOR UPDATE` el producto o la presentación cuando aplica reglas que dependen del historial (Casos 19 y 20). Así no se cruza con la lectura compartida de las entradas.
- `App\Services\ExistenciasService` concentra las consultas de existencias y la clasificación de caducidad (`estado()`, `diasRestantes()`, `consultaCaducidad()`, `resumenCaducidad()`, con el enum `EstadoCaducidad`). "Hoy" viene de `hoy()` (PHP, `America/Guayaquil`) y se pasa como parámetro a SQL; nunca `CURDATE()`.
- El periodo de alerta (RF-08) se lee siempre con `ConfiguracionService::diasAlertaCaducidad()`; no se fija en el código.
- §5.7 dice qué garantiza la base de datos y qué garantiza el servicio. Hay que respetar esa división.

**Base de datos.**
- Dos conexiones en `config/database.php`:
  - `mysql`, la de ejecución, con `isolation_level = READ COMMITTED` y `timezone = '-05:00'` (MySQL en Windows no tiene tablas de zonas horarias con nombre);
  - `migraciones`, para la cuenta con DDL.
- La zona de la aplicación es `America/Guayaquil`. La fecha de "hoy" para caducidades la calcula PHP y no `CURDATE()`.
- **Convenciones de §5.1:**
  - identificadores y claves foráneas `BIGINT UNSIGNED`, todas las FK con `RESTRICT`;
  - **cantidades, factores y stock mínimo como enteros con signo** con `CHECK` (un operando `UNSIGNED` rompe `cantidad * factor` con valores negativos);
  - `DECIMAL` sin `UNSIGNED`;
  - los `CHECK` se crean con `DB::statement` en la migración;
  - las columnas generadas (`->storedAs()`) nunca se asignan desde Eloquent;
  - claves foráneas compuestas `(x_id, producto_id)` para que un lote y una presentación pertenezcan al mismo producto.
- **Al crear tablas, actualizar `database/sql/02_permisos_app.sql`.** Los privilegios de `inventario_app` se conceden tabla por tabla; el historial solo admite `SELECT` e `INSERT`.

**Nombres.**
- El dominio está en español (`productos`, `presentaciones`, `lotes`…). La tabla de usuarios conserva el nombre estándar `users`.
- Laravel pluraliza en inglés, así que cada modelo de dominio declara `$table` explícitamente.
- Se sigue el estilo de Laravel 13 con atributos: `#[Fillable]`, `#[Hidden]`, `#[Signature]`, `#[Description]`.

**Autenticación y permisos (§2.3).**
- Roles base en el enum `App\Enums\Rol` (`admin`, `encargado`).
- Tres permisos especiales como booleanos en `users`: `puede_gestionar_catalogo`, `puede_ajustar_inventario` y `puede_revertir`.
- Se comprueban **en el servidor** con los Gates `gestionar-catalogo`, `ajustar-inventario`, `revertir` y `administrar` (este último, solo para el administrador), definidos en `AppServiceProvider`. El administrador tiene implícitamente los tres permisos.
- Las operaciones protegidas se comprueban dos veces: en la ruta (middleware `can:`) y dentro de `InventarioService` (`Gate::forUser(...)->authorize(...)` en ajustes, reversiones y corrección de lotes).
- `rol`, los permisos y `activo` no están en `#[Fillable]` a propósito: se asignan como atributos explícitos.
- `AsegurarUsuarioActivo`, añadido al grupo `web` en `bootstrap/app.php`, expulsa en la siguiente petición a un usuario desactivado.
- El inicio de sesión es propio (`Auth\LoginController`, con límite de intentos). No hay registro público ni recuperación de contraseña por correo.

**Interfaz.** Blade + Bootstrap 5.3 (sin más dependencias), con un layout en `resources/views/layouts/app.blade.php`. Debe poder usarse cómodamente en una tablet. Convenciones del rediseño:
- **Tokens y temas** en `resources/css/app.css`: variables de Bootstrap redefinidas en `:root` y `[data-bs-theme=dark]`. El tema (`claro`, `oscuro`, `automatico`) lo fija `data-bs-theme` en `<html>`: un script en el `<head>` del layout evita el destello y `resources/js/app.js` escucha el cambio del sistema. No uses colores fijos (`bg-white`, `text-bg-light`, `table-danger` sin tokens): usa `bg-body`, `bg-body-secondary` y los tokens `--estado-*`.
- **Navegación:** `layouts/_menu.blade.php` es la única definición del menú (barra lateral ≥ 1200 px y hoja «Más» en tablet y móvil); la barra inferior y la superior están en el layout. Iconos: sprite en `compartido/iconos.blade.php` (`<svg class="icono"><use href="#i-…"/></svg>`).
- **Parciales y componentes:** `compartido/estado` (píldora con icono y texto: nunca solo color), `compartido/miniatura` (imagen `-t` o marcador), `compartido/vacio` (estado vacío con «Quitar filtros»), `compartido/cantidad` (− / +; mismo `<input>`), `compartido/motivo` (radios `name="motivo"`), `compartido/campo-clave` (Mostrar/Ocultar) y `<x-cabecera-pagina>` / `<x-cabecera-producto>`.
- **Tablas apiladas:** clase `tabla-apilable` con `role` explícitos, `<caption class="visually-hidden">`, `<th scope="col">` y `data-etiqueta` en cada `<td>`; bajo 1024 px pasan a tarjeta solo con CSS. La primera celda y la de acciones llevan `data-etiqueta=""`. La cabecera se oculta solo visualmente.
- **Formularios:** campos en una tarjeta y botones en `<div class="d-flex gap-2 barra-acciones">` (fija sobre la barra inferior en tablet). Filtros: la búsqueda siempre visible y el resto en `.filtros-extra` tras el botón «Filtros».
- **Accesibilidad:** enlace «Saltar al contenido», foco visible de 3 px, bordes de campos ≥ 3:1 y contraste AA de texto en ambos temas. No añadas `opacity` a texto ni colores de Bootstrap sin comprobar el contraste.
- **Cómo se audita** (sin dependencias): con el servidor temporal de arriba y la sesión iniciada, un script en el navegador carga cada pantalla en un `<iframe>` de 390, 820, 1180 y 1280 px, con `localStorage.tema` en `claro` y `oscuro`, calcula la razón de contraste de cada texto visible (4,5:1; 3:1 si es grande) y detecta desbordes (`documentElement.scrollWidth > clientWidth`). Debe dar cero fallos antes de cerrar un cambio visual. Las pruebas de PHP solo comprueban el marcado.
- **Selector de productos** (paso 1 de Recibir y Retirar): `compartido/buscar-producto.blade.php` imprime la lista completa de productos activos (tope 500, `BuscaProducto`) y `resources/js/app.js` (`[data-selector-producto]`) la filtra al escribir con `data-busqueda` (sin tildes ni mayúsculas, como la colación). Sin JavaScript queda el GET `?q=`; un código de barras exacto se resuelve en el servidor. No añadas reglas de negocio aquí: solo cambia cómo se elige el producto.
- **Guía de uso:** `docs/GUIA_DE_USO.md` copia los rótulos reales de las pantallas. Si cambias un botón, un nombre de pantalla o un permiso, actualiza la guía (y sus capturas en `docs/img/guia/`, que deben usar solo datos ficticios).
