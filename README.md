# Inventario Bodega

Sistema de inventario privado para una bodega pequeña en Ecuador, pensado para usarse desde una tablet. Registra qué entra, qué sale y qué hay, con control de lotes y caducidad.

## El problema

Una bodega de productos de consumo masivo (arroz, aceite, conservas, lácteos, limpieza) tiene dificultades que una hoja de cálculo no resuelve bien:

- El mismo producto se maneja en **cajas y en unidades sueltas**, y abrir una caja cambia las dos cantidades a la vez.
- Los productos perecederos llegan en **lotes con distinta caducidad**, y hay que sacar primero lo que vence antes.
- **Varias personas** registran movimientos, a veces al mismo tiempo, y un toque doble en la tablet no debe duplicar una salida.
- Los errores (un stock negativo, un registro borrado) deben ser imposibles o, al menos, quedar a la vista.

## Cómo está pensado

- **El historial es la verdad.** No existe una tabla de saldos: la existencia de cada lote y presentación se calcula siempre a partir de los movimientos, que nunca se editan ni se borran. Un error se corrige con otro movimiento (ajuste o reversión), y todo queda registrado.
- **Producto, presentación y lote.** Un producto tiene presentaciones con un factor (caja x12 = 12 botellas) y sus unidades se reparten en lotes con su caducidad. Una caja y sus botellas son la misma existencia vista en distinta unidad.
- **Un solo punto de escritura.** Un único servicio registra los movimientos, con token de idempotencia, transacción, bloqueo ordenado de filas y la regla de que ningún lote queda en negativo. Las pruebas de concurrencia lo ejercitan con procesos PHP independientes.
- **La base también protege.** La cuenta de MySQL de la aplicación solo puede leer e insertar en el historial, y el resto de reglas se comprueban con restricciones en la propia base.
- **Privado por diseño.** Funciona en un PC de la casa o la bodega: MySQL y Apache escuchan solo en local y el acceso desde la tablet es por Tailscale, sin exponer nada a Internet.
- **Operable por una sola persona.** Copias cifradas automáticas con prueba de restauración, avisos en pantalla si falla una copia, y una guía de operación para los imprevistos (tablet perdida, reinicio, caída de Internet).

## Qué incluye

- Catálogo con categorías, productos, presentaciones y códigos de barras.
- Entradas, salidas (con sugerencia del lote que vence primero), conversiones de caja a unidades, ajustes por conteo físico y reversiones.
- Panel de inicio, inventario, historial, y alertas de lotes vencidos, próximos a caducar y bajo el mínimo.
- Usuarios con rol de administrador o encargado y permisos especiales.
- Carga inicial de existencias por CSV, con revisión previa y registro todo o nada.
- Interfaz táctil: botones grandes, accesos rápidos y formularios cómodos en tablet.

## Estado del proyecto

Se construye por etapas, con una especificación como autoridad (`docs/Requerimientos.txt`).

- **Hecho (etapas 1 a 8):** catálogo, movimientos, existencias, caducidad, panel, interfaz de tablet, acceso por Tailscale, usuarios, endurecimiento y copias de seguridad.
- **Interfaz rediseñada:** barra lateral en PC y barra inferior en tablet y móvil, tema claro, oscuro y automático, tablas que se apilan como tarjetas, cantidades con botones − / + e imagen opcional de producto. Contraste AA y desbordes auditados en 25 pantallas.
- **En curso (etapa 9):** carga inicial y validación contra el conteo físico. La herramienta está lista y probada; falta cargar los datos reales de la bodega.
- **Pendiente u opcional:** probar con un lector de códigos de barras, y copias en la nube o en disco externo.

## Tecnología

Laravel 13 y PHP 8.5, MySQL 8.4, plantillas Blade con Bootstrap, Apache 2.4, Tailscale. Pruebas con PHPUnit contra una base MySQL independiente.

## Documentación

- [`docs/Requerimientos.txt`](docs/Requerimientos.txt): especificación completa y decisiones de diseño.
- [`docs/OPERACION.md`](docs/OPERACION.md): operación diaria y procedimientos.
- [`docs/RESTAURACION.md`](docs/RESTAURACION.md): copias de seguridad y restauración.
- [`CLAUDE.md`](CLAUDE.md): guía del proyecto para trabajar con asistentes de código.

## Para desarrollarlo

```bash
composer install
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

Crea las cuentas de MySQL con `database/sql/01_cuentas.sql` (cambia las contraseñas de ejemplo), pon los datos de conexión en `.env`, y ejecuta `php artisan migrate`, `database/sql/02_permisos_app.sql` y `php artisan usuarios:crear` para el primer administrador.

Para probar sin datos reales: `php artisan db:seed --class=SimulacroCanastaSeeder` (productos de ejemplo de la canasta básica). Las pruebas: `php artisan test`; solo corren contra una base cuyo nombre termine en `_test`.

## Licencia

Código bajo licencia MIT (ver el archivo [LICENSE](LICENSE)).
