-- Ejecutar una sola vez sobre songa_eventos.
-- Esta regla impide que existan dos eventos ACTIVO al mismo tiempo.

DROP TRIGGER IF EXISTS trg_eventos_unico_activo;

DELIMITER $$

CREATE TRIGGER trg_eventos_unico_activo
BEFORE UPDATE ON eventos
FOR EACH ROW
BEGIN
    IF NEW.estado = 'ACTIVO' AND OLD.estado <> 'ACTIVO' THEN
        IF EXISTS (
            SELECT 1
            FROM eventos
            WHERE estado = 'ACTIVO'
              AND id <> OLD.id
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'No se puede activar el evento: ya existe otro evento ACTIVO.';
        END IF;
    END IF;
END$$

DELIMITER ;

-- Verificación recomendada después de instalar la regla:
-- SELECT id, nombre, estado FROM eventos WHERE estado = 'ACTIVO';
-- Debe devolver como máximo un registro.
