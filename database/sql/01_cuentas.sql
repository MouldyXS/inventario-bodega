-- Requerimientos §6.5: bases de datos y cuentas de MySQL.
-- Ejecutar UNA vez como root (por ejemplo, desde MySQL Workbench).
-- Antes de ejecutar, sustituye cada CAMBIAR_... por una contraseña robusta
-- y NO guardes este archivo con las contraseñas reales.
-- Las contraseñas de inventario_app, inventario_migra e inventario_test van
-- luego en .env / .env.testing; la de inventario_backup, en el archivo de
-- opciones protegido de las copias (Etapa 8).

CREATE DATABASE IF NOT EXISTS inventario
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS inventario_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Migraciones (DDL sobre la base de producción). Solo se usa al migrar.
CREATE USER IF NOT EXISTS 'inventario_migra'@'127.0.0.1' IDENTIFIED BY 'CAMBIAR_MIGRA';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
    ON inventario.* TO 'inventario_migra'@'127.0.0.1';

-- Aplicación en ejecución. Sus privilegios se conceden tabla por tabla en
-- 02_permisos_app.sql, después de cada migración que cree tablas.
CREATE USER IF NOT EXISTS 'inventario_app'@'127.0.0.1' IDENTIFIED BY 'CAMBIAR_APP';

-- Copias de seguridad (mysqldump --single-transaction --no-tablespaces).
CREATE USER IF NOT EXISTS 'inventario_backup'@'127.0.0.1' IDENTIFIED BY 'CAMBIAR_BACKUP';
GRANT SELECT, SHOW VIEW, TRIGGER ON inventario.* TO 'inventario_backup'@'127.0.0.1';

-- Pruebas automatizadas: todos los privilegios, solo sobre inventario_test.
CREATE USER IF NOT EXISTS 'inventario_test'@'127.0.0.1' IDENTIFIED BY 'CAMBIAR_TEST';
GRANT ALL PRIVILEGES ON inventario_test.* TO 'inventario_test'@'127.0.0.1';
