<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../app/Database.php';

function respuesta(bool $ok, array $data = [], int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        array_merge(['ok' => $ok], $data),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

function usuarioActual(PDO $db): int
{
    $posibles = [
        $_SESSION['usuario_id'] ?? null,
        $_SESSION['user_id'] ?? null,
        $_SESSION['id_usuario'] ?? null,
        $_SESSION['usuario']['id'] ?? null,
    ];

    foreach ($posibles as $id) {
        if (is_numeric($id) && (int)$id > 0) {
            return (int)$id;
        }
    }

    // Si la aplicación todavía no tiene autenticación conectada,
    // usamos el primer usuario activo existente.
    $stmt = $db->query("
        SELECT id
        FROM usuarios
        WHERE activo = 1
        ORDER BY id
        LIMIT 1
    ");

    $id = $stmt->fetchColumn();

    if (!$id) {
        throw new RuntimeException(
            'No existe un usuario activo en la tabla usuarios.'
        );
    }

    return (int)$id;
}

function eventoExiste(PDO $db, int $eventoId): array
{
    $stmt = $db->prepare("
        SELECT id, nombre, estado
        FROM eventos
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $eventoId]);

    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        throw new RuntimeException('Evento no encontrado.');
    }

    return $evento;
}

function obtenerSorteo(PDO $db, int $eventoId, int $usuarioId): array
{
    $stmt = $db->prepare("
        SELECT id, evento_id, estado
        FROM sorteos
        WHERE evento_id = :evento_id
        LIMIT 1
    ");

    $stmt->execute([':evento_id' => $eventoId]);

    $sorteo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sorteo) {
        return $sorteo;
    }

    $stmt = $db->prepare("
        INSERT INTO sorteos
        (
            evento_id,
            creado_por,
            estado
        )
        VALUES
        (
            :evento_id,
            :creado_por,
            'ACTIVO'
        )
    ");

    $stmt->execute([
        ':evento_id' => $eventoId,
        ':creado_por' => $usuarioId
    ]);

    return [
        'id' => (int)$db->lastInsertId(),
        'evento_id' => $eventoId,
        'estado' => 'ACTIVO'
    ];
}

function candidatosDisponibles(
    PDO $db,
    int $eventoId,
    int $sorteoId,
    int $premioId
): array {
    /*
     * Candidato válido:
     * 1. Tiene asistencia registrada.
     * 2. No ha ganado antes en el evento.
     * 3. No fue marcado como NO_PRESENTE.
     */

    $stmt = $db->prepare("
        SELECT DISTINCT
            ec.id,
            ec.cod,
            ec.cedula,
            ec.apellidos_nombres,
            ec.area,
            ec.empresa
        FROM evento_colaboradores ec
        INNER JOIN registros r
            ON r.colaborador_id = ec.id
           AND r.evento_id = ec.evento_id

        WHERE ec.evento_id = :evento_id

          AND NOT EXISTS (
              SELECT 1
              FROM sorteo_ganadores sg
              WHERE sg.sorteo_id = :sorteo_ganadores
                AND sg.colaborador_id = ec.id
          )

          AND NOT EXISTS (
              SELECT 1
              FROM sorteo_intentos si
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

    $action = $_POST['action']
        ?? $_GET['action']
        ?? '';

    $eventoId = (int)(
        $_POST['evento_id']
        ?? $_GET['evento_id']
        ?? 0
    );

    if ($eventoId <= 0) {
        respuesta(false, [
            'message' => 'Evento no válido.'
        ], 400);
    }

    $evento = eventoExiste($db, $eventoId);
    $usuarioId = usuarioActual($db);
    $sorteo = obtenerSorteo(
        $db,
        $eventoId,
        $usuarioId
    );

    $sorteoId = (int)$sorteo['id'];

    /*
     * ============================================================
     * BOOTSTRAP
     * ============================================================
     */

    if ($action === 'bootstrap') {

        $stmt = $db->prepare("
            SELECT
                id,
                nombre,
                posicion,
                estado,
                creado_en
            FROM sorteo_premios
            WHERE sorteo_id = :sorteo_id
            ORDER BY posicion, id
        ");

        $stmt->execute([
            ':sorteo_id' => $sorteoId
        ]);

        $premios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT
                sg.id,
                sg.premio_id,
                sp.nombre AS premio,
                ec.id AS colaborador_id,
                ec.cod,
                ec.cedula,
                ec.apellidos_nombres,
                ec.area,
                ec.empresa,
                sg.fecha_hora
            FROM sorteo_ganadores sg
            INNER JOIN sorteo_premios sp
                ON sp.id = sg.premio_id
            INNER JOIN evento_colaboradores ec
                ON ec.id = sg.colaborador_id
            WHERE sg.sorteo_id = :sorteo_id
            ORDER BY sg.id
        ");

        $stmt->execute([
            ':sorteo_id' => $sorteoId
        ]);

        $ganadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $candidatos = candidatosDisponibles(
            $db,
            $eventoId,
            $sorteoId,
            0
        );

        respuesta(true, [
            'evento' => $evento,
            'sorteo' => $sorteo,
            'premios' => $premios,
            'ganadores' => $ganadores,
            'cantidad_disponibles' => count($candidatos)
        ]);
    }

    /*
     * ============================================================
     * CREAR PREMIO
     * ============================================================
     */

    if ($action === 'crear_premio') {

        $nombre = trim(
            (string)($_POST['nombre'] ?? '')
        );

        if ($nombre === '') {
            respuesta(false, [
                'message' => 'Ingrese el nombre del premio.'
            ], 400);
        }

        $stmt = $db->prepare("
            SELECT COALESCE(MAX(posicion), 0) + 1
            FROM sorteo_premios
            WHERE sorteo_id = :sorteo_id
        ");

        $stmt->execute([
            ':sorteo_id' => $sorteoId
        ]);

        $posicion = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("
            INSERT INTO sorteo_premios
            (
                sorteo_id,
                nombre,
                posicion,
                estado
            )
            VALUES
            (
                :sorteo_id,
                :nombre,
                :posicion,
                'PENDIENTE'
            )
        ");

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

    /*
     * ============================================================
     * CANDIDATOS / PREPARAR RULETA
     * ============================================================
     */

    if ($action === 'candidatos') {

        $premioId = (int)(
            $_POST['premio_id']
            ?? 0
        );

        if ($premioId <= 0) {
            respuesta(false, [
                'message' => 'Premio no válido.'
            ], 400);
        }

        $stmt = $db->prepare("
            SELECT id, nombre, estado
            FROM sorteo_premios
            WHERE id = :id
              AND sorteo_id = :sorteo_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $premioId,
            ':sorteo_id' => $sorteoId
        ]);

        $premio = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$premio) {
            respuesta(false, [
                'message' => 'Premio no encontrado.'
            ], 404);
        }

        $candidatos = candidatosDisponibles(
            $db,
            $eventoId,
            $sorteoId,
            $premioId
        );

        if (!$candidatos) {
            respuesta(false, [
                'message' =>
                    'No quedan colaboradores disponibles para este sorteo.'
            ], 409);
        }

        /*
         * La selección aleatoria se hace en el servidor.
         * El navegador solo anima la rueda hasta ese índice.
         */
        $indiceGanador = random_int(
            0,
            count($candidatos) - 1
        );

        $ganador = $candidatos[$indiceGanador];

        $stmt = $db->prepare("
            UPDATE sorteo_premios
            SET estado = 'EN_PROCESO'
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $premioId
        ]);

        respuesta(true, [
            'premio' => $premio,
            'candidatos' => $candidatos,
            'indice_ganador' => $indiceGanador,
            'ganador' => $ganador
        ]);
    }

    /*
     * ============================================================
     * RESOLVER GANADOR
     * ============================================================
     */

    if ($action === 'resolver') {

        $premioId = (int)(
            $_POST['premio_id']
            ?? 0
        );

        $colaboradorId = (int)(
            $_POST['colaborador_id']
            ?? 0
        );

        $presente = (int)(
            $_POST['presente']
            ?? 0
        );

        if ($premioId <= 0 || $colaboradorId <= 0) {
            respuesta(false, [
                'message' => 'Datos del ganador no válidos.'
            ], 400);
        }

        $stmt = $db->prepare("
            SELECT id, nombre, estado
            FROM sorteo_premios
            WHERE id = :id
              AND sorteo_id = :sorteo_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $premioId,
            ':sorteo_id' => $sorteoId
        ]);

        $premio = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$premio) {
            respuesta(false, [
                'message' => 'Premio no encontrado.'
            ], 404);
        }

        $stmt = $db->prepare("
            SELECT
                id,
                cod,
                cedula,
                apellidos_nombres,
                area,
                empresa
            FROM evento_colaboradores
            WHERE id = :id
              AND evento_id = :evento_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $colaboradorId,
            ':evento_id' => $eventoId
        ]);

        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$colaborador) {
            respuesta(false, [
                'message' => 'Colaborador no encontrado.'
            ], 404);
        }

        /*
         * Verificamos nuevamente la elegibilidad antes de guardar.
         */

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM registros
            WHERE evento_id = :evento_id
              AND colaborador_id = :colaborador_id
        ");

        $stmt->execute([
            ':evento_id' => $eventoId,
            ':colaborador_id' => $colaboradorId
        ]);

        if ((int)$stmt->fetchColumn() === 0) {
            respuesta(false, [
                'message' =>
                    'El colaborador no tiene asistencia registrada.'
            ], 409);
        }

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM sorteo_ganadores
            WHERE sorteo_id = :sorteo_id
              AND colaborador_id = :colaborador_id
        ");

        $stmt->execute([
            ':sorteo_id' => $sorteoId,
            ':colaborador_id' => $colaboradorId
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            respuesta(false, [
                'message' =>
                    'Este colaborador ya ganó un premio en este evento.'
            ], 409);
        }

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM sorteo_intentos
            WHERE sorteo_id = :sorteo_id
              AND colaborador_id = :colaborador_id
              AND resultado = 'NO_PRESENTE'
        ");

        $stmt->execute([
            ':sorteo_id' => $sorteoId,
            ':colaborador_id' => $colaboradorId
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            respuesta(false, [
                'message' =>
                    'Este colaborador ya fue marcado como no presente.'
            ], 409);
        }

        $db->beginTransaction();

        try {

            if ($presente === 1) {

                $stmt = $db->prepare("
                    INSERT INTO sorteo_intentos
                    (
                        sorteo_id,
                        premio_id,
                        colaborador_id,
                        resultado,
                        usuario_id
                    )
                    VALUES
                    (
                        :sorteo_id,
                        :premio_id,
                        :colaborador_id,
                        'GANADOR',
                        :usuario_id
                    )
                ");

                $stmt->execute([
                    ':sorteo_id' => $sorteoId,
                    ':premio_id' => $premioId,
                    ':colaborador_id' => $colaboradorId,
                    ':usuario_id' => $usuarioId
                ]);

                $stmt = $db->prepare("
                    INSERT INTO sorteo_ganadores
                    (
                        sorteo_id,
                        premio_id,
                        colaborador_id,
                        usuario_id
                    )
                    VALUES
                    (
                        :sorteo_id,
                        :premio_id,
                        :colaborador_id,
                        :usuario_id
                    )
                ");

                $stmt->execute([
                    ':sorteo_id' => $sorteoId,
                    ':premio_id' => $premioId,
                    ':colaborador_id' => $colaboradorId,
                    ':usuario_id' => $usuarioId
                ]);

                $stmt = $db->prepare("
                    UPDATE sorteo_premios
                    SET estado = 'COMPLETADO'
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':id' => $premioId
                ]);

                $db->commit();

                respuesta(true, [
                    'resultado' => 'GANADOR',
                    'message' => 'Ganador registrado correctamente.',
                    'colaborador' => $colaborador,
                    'premio' => $premio
                ]);
            }

            /*
             * NO PRESENTE:
             * Se registra y queda excluido automáticamente.
             * El frontend cerrará el popup y volverá a girar.
             */

            $stmt = $db->prepare("
                INSERT INTO sorteo_intentos
                (
                    sorteo_id,
                    premio_id,
                    colaborador_id,
                    resultado,
                    usuario_id
                )
                VALUES
                (
                    :sorteo_id,
                    :premio_id,
                    :colaborador_id,
                    'NO_PRESENTE',
                    :usuario_id
                )
            ");

            $stmt->execute([
                ':sorteo_id' => $sorteoId,
                ':premio_id' => $premioId,
                ':colaborador_id' => $colaboradorId,
                ':usuario_id' => $usuarioId
            ]);

            $db->commit();

            respuesta(true, [
                'resultado' => 'NO_PRESENTE',
                'message' =>
                    'Colaborador excluido. Se realizará otro giro.',
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

    /*
     * ============================================================
     * FINALIZAR SORTEO
     * ============================================================
     */

    if ($action === 'finalizar') {

        $stmt = $db->prepare("
            UPDATE sorteos
            SET estado = 'FINALIZADO'
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $sorteoId
        ]);

        respuesta(true, [
            'message' => 'Sorteo finalizado.'
        ]);
    }

    respuesta(false, [
        'message' => 'Acción no reconocida.'
    ], 400);

} catch (Throwable $e) {

    respuesta(false, [
        'message' => $e->getMessage()
    ], 500);
}