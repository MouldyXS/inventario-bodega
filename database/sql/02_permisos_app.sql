-- Requerimientos §6.5: privilegios de la cuenta inventario_app, tabla por tabla.
-- Ejecutar como root DESPUÉS de cada migración que cree tablas.
-- Es idempotente: puede ejecutarse tantas veces como haga falta.
-- No se concede nada sobre inventario.* a propósito: un privilegio a nivel de
-- base no puede retirarse después para una tabla concreta.

-- Etapa 1 -----------------------------------------------------------------
-- users: sin DELETE (los usuarios se desactivan).
GRANT SELECT, INSERT, UPDATE ON inventario.users TO 'inventario_app'@'127.0.0.1';

-- Tablas del framework: sesiones, caché y restablecimiento de contraseñas.
GRANT SELECT, INSERT, UPDATE, DELETE ON inventario.sessions              TO 'inventario_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON inventario.cache                 TO 'inventario_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON inventario.cache_locks           TO 'inventario_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON inventario.password_reset_tokens TO 'inventario_app'@'127.0.0.1';

-- Etapa 2: catálogo --------------------------------------------------------
-- Sin DELETE: categorías, productos y presentaciones se desactivan.
GRANT SELECT, INSERT, UPDATE ON inventario.categorias     TO 'inventario_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON inventario.productos      TO 'inventario_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON inventario.presentaciones TO 'inventario_app'@'127.0.0.1';
-- Los códigos de barras sí pueden quitarse.
GRANT SELECT, INSERT, UPDATE, DELETE ON inventario.codigos_barras TO 'inventario_app'@'127.0.0.1';

-- Etapa 3: lotes e historial -----------------------------------------------
-- lotes: UPDATE es necesario para la corrección de datos (RF-04) y para
-- SELECT ... FOR UPDATE (§5.6). Sin DELETE.
GRANT SELECT, INSERT, UPDATE ON inventario.lotes TO 'inventario_app'@'127.0.0.1';
-- Historial de solo inserción (RF-09, Caso 26).
GRANT SELECT, INSERT ON inventario.movimientos         TO 'inventario_app'@'127.0.0.1';
GRANT SELECT, INSERT ON inventario.detalles_movimiento TO 'inventario_app'@'127.0.0.1';

-- Etapa 5: configuración ---------------------------------------------------
GRANT SELECT, INSERT, UPDATE ON inventario.configuracion TO 'inventario_app'@'127.0.0.1';
