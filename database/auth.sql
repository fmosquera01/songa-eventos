-- Ejecutar una sola vez sobre la base songa_eventos.
-- Agrega los campos necesarios para autenticacion segura.

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS usuario_login VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS rol VARCHAR(20) NOT NULL DEFAULT 'OPERADOR';

CREATE UNIQUE INDEX IF NOT EXISTS uk_usuarios_login
    ON usuarios (usuario_login);

-- Ejemplos de roles permitidos: ADMIN / OPERADOR
-- No se almacenan contrasenas en texto plano.
