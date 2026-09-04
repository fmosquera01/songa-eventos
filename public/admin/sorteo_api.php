<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';

exigirAdmin();

header('Content-Type: application/json; charset=utf-8');

function respuesta(bool $ok, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function eventoExiste(PDO $db, int $eventoId): array
{
    $stmt = $db->prepare("SELECT id, nombre, estado FROM eventos WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $eventoId]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$evento) {
        throw new RuntimeException('Evento no encontrado.');
    }
    return $evento;
}

function obtenerSorteo(PDO $db, int $eventoId, int $usuarioId, bool $crear = false): ?array
{
    $stmt = $db->prepare("SELECT id, evento_id, estado FROM sorteos WHERE evento_id = :evento_id LIMIT 1");
    $stmt->execute([':evento_id' => $eventoId]);
    $sorteo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sorteo || !$crear) {
        return $sorteo ?: null;
    }

    $stmt = $db->prepare("INSERT INTO sorteos (evento_id, creado_por, estado) VALUES (:evento_id, :creado_por, 'ACTIVO')");
    $stmt->execute([':evento_id' => $eventoId, ':creado_por' => $usuarioId]);

    return [
        'id' => (int)$db->lastInsertId(),
        'evento_id' => $eventoId,
        'estado' => 'ACTIVO'
    ];
}

function candidatosDisponibles(PDO $db, int $eventoId, int $sorteoId): array
{
    $stmt = $db->prepare("
        SELECT DISTINCT ec.id, ec.cod, ec.cedula, ec.apellidos_nombres, ec.area, ec.empresa
        FROM evento_colaboradores ec
        INNER JOIN registros r
            ON r.colaborador_id = ec.id
           AND r.evento_id = ec.evento_id
        WHERE ec.evento_id = :evento_id
          AND r.tipo_registro = 'ASISTENCIA'
          AND NOT EXISTS (
              SELECT 1 FROM sorteo_ganadores sg
              WHERE sg.sorteo_id = :sorteo_ganadores
                AND sg.colaborador_id = ec.id
          )
          AND NOT EXISTS (
              SELECT 1 FROM sorteo_intentos si
              WHERE si.sorteo_id = :sorteo_intentos
                AND si.colaborador_id = ec.id
                AND si.resultado = 'NO_PRESENTE'
          )
        ORDER BY ec.apellidos_nombres
    ");
    $stmt->execute([
        ':evento_id' => $eventoId,
        ':sorteo_ganadores' => $sorteoId,
        ':sorteo_intentos' => $sorteoId
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $db = Database::connection();

    $action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
    $eventoId = (int)($_POST['evento_id'] ?? $_GET['evento_id'] ?? 0);

    if ($eventoId <= 0) {
        respuesta(false, ['message' => 'Evento no válido.'], 400);
    }

    $evento = eventoExiste($db, $eventoId);
    $usuarioId = (int)$_SESSION['usuario_id'];

    /* Un evento FINALIZADO es solamente de consulta. */
    if ($evento['estado'] === 'FINALIZADO' && $action !== 'bootstrap') {
        respuesta(false, ['message' => 'El evento está FINALIZADO. El sorteo es de solo lectura.'], 409);
    }

    /*
     * Bootstrap nunca crea datos. Esto es importante para que abrir un evento
     * finalizado no modifique la base de datos.
     */
    $sorteo = obtenerSorteo($db, $eventoId, $usuarioId, $action !== 'bootstrap');

    if (!$sorteo) {
        if ($action === 'bootstrap') {
            respuesta(true, [
                'evento' => $evento,
                'sorteo' => null,
                'premios' => [],
                'ganadores' => [],
                'cantidad_disponibles' => 0
            ]);
        }
        respuesta(false, ['message' => 'No existe un sorteo para este evento.'], 404);
    }

    $sorteoId = (int)$sorteo['id'];

    if ($sorteo['estado'] === 'FINALIZADO' && $action !== 'bootstrap') {
        respuesta(false, ['message' => 'El sorteo está FINALIZADO y no admite modificaciones.'], 409);
    }

    if ($action === 'bootstrap') {
        $stmt = $db->prepare("SELECT id, nombre, posicion, estado, creado_en FROM sorteo_premios WHERE sorteo_id = :sorteo_id ORDER BY posicion, id");
        $stmt->execute([':sorteo_id' => $sorteoId]);
        $premios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT sg.id, sg.premio_id, sp.nombre AS premio,
                   ec.id AS colaborador_id, ec.cod, ec.cedula,
                   ec.apellidos_nombres, ec.area, ec.empresa, sg.fecha_hora
            FROM sorteo_ganadores sg
            INNER JOIN sorteo_premios sp ON sp.id = sg.premio_id
            INNER JOIN evento_colaboradores ec ON ec.id = sg.colaborador_id
            WHERE sg.sorteo_id = :sorteo_id
            ORDER BY sg.id
        ");
        $stmt->execute([':sorteo_id' => $sorteoId]);
        $ganadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $candidatos = candidatosDisponibles($db, $eventoId, $sorteoId);

        respuesta(true, [
            'evento' => $evento,
            'sorteo' => $sorteo,
            'premios' => $premios,
            'ganadores' => $ganadores,
            'cantidad_disponibles' => count($candidatos)
        ]);
    }

    if ($action === 'crear_premio') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        if ($nombre === '') {
            respuesta(false, ['message' => 'Ingrese el nombre del premio.'], 400);
        }

        $stmt = $db->prepare("SELECT COALESCE(MAX(posicion), 0) + 1 FROM sorteo_premios WHERE sorteo_id = :sorteo_id");
        $stmt->execute([':sorteo_id' => $sorteoId]);
        $posicion = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("INSERT INTO sorteo_premios (sorteo_id, nombre, posicion, estado) VALUES (:sorteo_id, :nombre, :posicion, 'PENDIENTE')");
        $stmt->execute([
            ':sorteo_id' => $sorteoId,
            ':nombre' => $nombre,
            ':posicion' => $posicion
        ]);

        respuesta(true, [
            'premio_id' => (int)$db->lastInsertId(),
            'message' => 'Premio creado correctamente.'
        ]);
    }

    if ($action === 'candidatos') {
        $premioId = (int)($_POST['premio_id'] ?? 0);
        if ($premioId <= 0) {
            respuesta(false, ['message' => 'Premio no válido.'], 400);
        }

        $stmt = $db->prepare("SELECT id, nombre, estado FROM sorteo_premios WHERE id = :id AND sorteo_id = :sorteo_id LIMIT 1");
        $stmt->execute([':id' => $premioId, ':sorteo_id' => $sorteoId]);
        $premio = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$premio) {
            respuesta(false, ['message' => 'Premio no encontrado.'], 404);
        }
        if ($premio['estado'] === 'COMPLETADO') {
            respuesta(false, ['message' => 'Este premio ya fue completado.'], 409);
        }

        $candidatos = candidatosDisponibles($db, $eventoId, $sorteoId);
        if (!$candidatos) {
            respuesta(false, ['message' => 'No quedan colaboradores disponibles para este sorteo.'], 409);
        }

        $indiceGanador = random_int(0, count($candidatos) - 1);
        $ganador = $candidatos[$indiceGanador];

        $stmt = $db->prepare("UPDATE sorteo_premios SET estado = 'EN_PROCESO' WHERE id = :id AND estado = 'PENDIENTE'");
        $stmt->execute([':id' => $premioId]);

        respuesta(true, [
            'premio' => $premio,
            'candidatos' => $candidatos,
            'indice_ganador' => $indiceGanador,
            'ganador' => $ganador
        ]);
    }

    if ($action === 'resolver') {
        $premioId = (int)($_POST['premio_id'] ?? 0);
        $colaboradorId = (int)($_POST['colaborador_id'] ?? 0);
        $presente = (int)($_POST['presente'] ?? 0);

        if ($premioId <= 0 || $colaboradorId <= 0) {
            respuesta(false, ['message' => 'Datos del ganador no válidos.'], 400);
        }

        $stmt = $db->prepare("SELECT id, nombre, estado FROM sorteo_premios WHERE id = :id AND sorteo_id = :sorteo_id LIMIT 1");
        $stmt->execute([':id' => $premioId, ':sorteo_id' => $sorteoId]);
        $premio = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$premio) {
            respuesta(false, ['message' => 'Premio no encontrado.'], 404);
        }
        if ($premio['estado'] === 'COMPLETADO') {
            respuesta(false, ['message' => 'Este premio ya fue completado.'], 409);
        }

        $stmt = $db->prepare("SELECT id, cod, cedula, apellidos_nombres, area, empresa FROM evento_colaboradores WHERE id = :id AND evento_id = :evento_id LIMIT 1");
        $stmt->execute([':id' => $colaboradorId, ':evento_id' => $eventoId]);
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$colaborador) {
            respuesta(false, ['message' => 'Colaborador no encontrado.'], 404);
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM registros WHERE evento_id = :evento_id AND colaborador_id = :colaborador_id AND tipo_registro = 'ASISTENCIA'");
        $stmt->execute([':evento_id' => $eventoId, ':colaborador_id' => $colaboradorId]);
        if ((int)$stmt->fetchColumn() === 0) {
            respuesta(false, ['message' => 'El colaborador no tiene asistencia registrada.'], 409);
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM sorteo_ganadores WHERE sorteo_id = :sorteo_id AND colaborador_id = :colaborador_id");
        $stmt->execute([':sorteo_id' => $sorteoId, ':colaborador_id' => $colaboradorId]);
        if ((int)$stmt->fetchColumn() > 0) {
            respuesta(false, ['message' => 'Este colaborador ya ganó un premio en este evento.'], 409);
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM sorteo_intentos WHERE sorteo_id = :sorteo_id AND colaborador_id = :colaborador_id AND resultado = 'NO_PRESENTE'");
        $stmt->execute([':sorteo_id' => $sorteoId, ':colaborador_id' => $colaboradorId]);
        if ((int)$stmt->fetchColumn() > 0) {
            respuesta(false, ['message' => 'Este colaborador ya fue marcado como no presente.'], 409);
        }

        $db->beginTransaction();
        try {
            if ($presente === 1) {
                $stmt = $db->prepare("INSERT INTO sorteo_intentos (sorteo_id, premio_id, colaborador_id, resultado, usuario_id) VALUES (:sorteo_id, :premio_id, :colaborador_id, 'GANADOR', :usuario_id)");
                $stmt->execute([
                    ':sorteo_id' => $sorteoId,
                    ':premio_id' => $premioId,
                    ':colaborador_id' => $colaboradorId,
                    ':usuario_id' => $usuarioId
                ]);

                $stmt = $db->prepare("INSERT INTO sorteo_ganadores (sorteo_id, premio_id, colaborador_id, usuario_id) VALUES (:sorteo_id, :premio_id, :colaborador_id, :usuario_id)");
                $stmt->execute([
                    ':sorteo_id' => $sorteoId,
                    ':premio_id' => $premioId,
                    ':colaborador_id' => $colaboradorId,
                    ':usuario_id' => $usuarioId
                ]);

                $stmt = $db->prepare("UPDATE sorteo_premios SET estado = 'COMPLETADO' WHERE id = :id AND estado <> 'COMPLETADO'");
                $stmt->execute([':id' => $premioId]);

                $db->commit();
                respuesta(true, [
                    'resultado' => 'GANADOR',
                    'message' => 'Ganador registrado correctamente.',
                    'colaborador' => $colaborador,
                    'premio' => $premio
                ]);
            }

            $stmt = $db->prepare("INSERT INTO sorteo_intentos (sorteo_id, premio_id, colaborador_id, resultado, usuario_id) VALUES (:sorteo_id, :premio_id, :colaborador_id, 'NO_PRESENTE', :usuario_id)");
            $stmt->execute([
                ':sorteo_id' => $sorteoId,
                ':premio_id' => $premioId,
                ':colaborador_id' => $colaboradorId,
                ':usuario_id' => $usuarioId
            ]);

            $db->commit();
            respuesta(true, [
                'resultado' => 'NO_PRESENTE',
                'message' => 'Colaborador excluido. Se realizará otro giro.',
                'colaborador' => $colaborador,
                'premio' => $premio
            ]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    if ($action === 'finalizar') {
        $stmt = $db->prepare("UPDATE sorteos SET estado = 'FINALIZADO' WHERE id = :id AND estado <> 'FINALIZADO'");
        $stmt->execute([':id' => $sorteoId]);
        respuesta(true, ['message' => 'Sorteo finalizado.']);
    }

    respuesta(false, ['message' => 'Acción no reconocida.'], 400);
} catch (Throwable $e) {
    respuesta(false, ['message' => $e->getMessage()], 500);
}
