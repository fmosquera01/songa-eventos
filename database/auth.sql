-- Inicializacion de autenticacion para songa_eventos.
-- Ejecutar una sola vez despues de crear las tablas.
--
-- Usuario inicial:
--   Usuario: admin
--   Clave temporal: Admin123
-- IMPORTANTE: cambiar esta clave despues del primer inicio de sesion.

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS usuario_login VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS rol VARCHAR(20) NOT NULL DEFAULT 'OPERADOR';

CREATE UNIQUE INDEX IF NOT EXISTS uk_usuarios_login
    ON usuarios (usuario_login);

-- Si la instalacion ya creo la fila admin con usuario_login vacio,
-- la convertimos en el administrador inicial.
UPDATE usuarios
SET
    usuario_login = 'admin',
    password_hash = '$2y$12$6ekxlkcq.tFPGTvHZoT2KuyNOMvm9Akztm41ehSrTPVmTg4CnPw/a',
    rol = 'ADMIN',
    activo = 1
WHERE usuario = 'admin'
  AND (usuario_login IS NULL OR usuario_login = '');

-- Para instalaciones nuevas, crear el administrador inicial.
INSERT INTO usuarios
    (usuario, nombre, usuario_login, password_hash, rol, activo)
SELECT
    'admin',
    'Administrador',
    'admin',
    '$2y$12$6ekxlkcq.tFPGTvHZoT2KuyNOMvm9Akztm41ehSrTPVmTg4CnPw/a',
    'ADMIN',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE usuario_login = 'admin'
);
