<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../app/Database.php';

$db = Database::connection();

$eventoId = isset($_POST['evento_id'])
    ? (int)$_POST['evento_id']
    : 0;

$nombre = trim(
    (string)($_POST['nombre'] ?? '')
);

if ($eventoId <= 0) {

    die('Evento no válido.');
}

if ($nombre === '') {

    die('Debe indicar el nombre del sorteo.');
}


/*
|--------------------------------------------------------------------------
| VERIFICAR EVENTO
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        id,
        nombre,
        estado
    FROM eventos
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $eventoId
]);

$evento = $stmt->fetch();

if (!$evento) {

    die('Evento no encontrado.');
}


/*
|--------------------------------------------------------------------------
| SOLO UN SORTEO POR EVENTO
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT id
    FROM sorteos
    WHERE evento_id = :evento_id
    LIMIT 1
");

$stmt->execute([
    ':evento_id' => $eventoId
]);

$existente = $stmt->fetch();

if ($existente) {

    header(
        'Location: sorteo.php?evento_id=' .
        $eventoId .
        '&error=' .
        urlencode(
            'Este evento ya tiene un sorteo creado.'
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| USUARIO
|--------------------------------------------------------------------------
*/

$usuarioId =
    (int)(
        $_SESSION['usuario_id']
        ??
        $_SESSION['user_id']
        ??
        0
    );

if ($usuarioId <= 0) {

    /*
     * Si todavía no está implementado el login,
     * usamos el primer usuario activo.
     */

    $stmt = $db->query("
        SELECT id
        FROM usuarios
        WHERE activo = 1
        ORDER BY id ASC
        LIMIT 1
    ");

    $usuarioId =
        (int)$stmt->fetchColumn();
}


if ($usuarioId <= 0) {

    die(
        'No existe un usuario válido para crear el sorteo.'
    );
}


/*
|--------------------------------------------------------------------------
| CREAR
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    INSERT INTO sorteos
    (
        evento_id,
        nombre,
        cantidad_ganadores,
        solo_asistentes,
        creado_por
    )
    VALUES
    (
        :evento_id,
        :nombre,
        0,
        1,
        :creado_por
    )
");

$stmt->execute([

    ':evento_id' =>
        $eventoId,

    ':nombre' =>
        $nombre,

    ':creado_por' =>
        $usuarioId

]);


header(
    'Location: sorteo.php?evento_id=' .
    $eventoId .
    '&ok=' .
    urlencode(
        'Sorteo creado correctamente.'
    )
);

exit;