<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../app/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: eventos.php');
    exit;
}

try {

    $db = Database::connection();

    /*
     * Por ahora utilizamos el usuario ID 1,
     * que creamos durante la prueba.
     *
     * Después esto vendrá del sistema de login.
     */
    $usuarioId = 1;

    $nombre = trim(
        $_POST['nombre'] ?? ''
    );

    $tipo = trim(
        $_POST['tipo'] ?? ''
    );

    $descripcion = trim(
        $_POST['descripcion'] ?? ''
    );

    $fechaEvento =
        !empty($_POST['fecha_evento'])
            ? $_POST['fecha_evento']
            : null;

    $horaInicio =
        !empty($_POST['hora_inicio'])
            ? $_POST['hora_inicio']
            : null;

    $horaFin =
        !empty($_POST['hora_fin'])
            ? $_POST['hora_fin']
            : null;

    $validarEstado =
        isset($_POST['validar_estado'])
            ? 1
            : 0;

    $permitirDuplicado =
        isset($_POST['permitir_duplicado'])
            ? 1
            : 0;

    if ($nombre === '') {
        throw new RuntimeException(
            'El nombre del evento es obligatorio.'
        );
    }

    if ($tipo === '') {
        throw new RuntimeException(
            'Debe seleccionar el tipo de evento.'
        );
    }

    $stmt = $db->prepare("
        INSERT INTO eventos
        (
            nombre,
            descripcion,
            tipo,
            fecha_evento,
            hora_inicio,
            hora_fin,
            estado,
            creado_por,
            validar_estado,
            permitir_duplicado
        )
        VALUES
        (
            :nombre,
            :descripcion,
            :tipo,
            :fecha_evento,
            :hora_inicio,
            :hora_fin,
            'BORRADOR',
            :creado_por,
            :validar_estado,
            :permitir_duplicado
        )
    ");

    $stmt->execute([
        ':nombre' => $nombre,
        ':descripcion' => $descripcion ?: null,
        ':tipo' => $tipo,
        ':fecha_evento' => $fechaEvento,
        ':hora_inicio' => $horaInicio,
        ':hora_fin' => $horaFin,
        ':creado_por' => $usuarioId,
        ':validar_estado' => $validarEstado,
        ':permitir_duplicado' => $permitirDuplicado
    ]);

    $eventoId = (int)$db->lastInsertId();

    header(
        'Location: evento.php?id=' . $eventoId
    );

    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo '<h1>Error</h1>';

    echo '<p>';

    echo htmlspecialchars(
        $e->getMessage()
    );

    echo '</p>';

    echo '<p>';

    echo '<a href="evento_nuevo.php">Volver</a>';

    echo '</p>';
}