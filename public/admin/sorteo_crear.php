<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/EventoEstado.php';

exigirAdmin();

$db = Database::connection();

$eventoId = isset($_POST['evento_id']) ? (int)$_POST['evento_id'] : 0;
$nombre = trim((string)($_POST['nombre'] ?? ''));

if ($eventoId <= 0) die('Evento no válido.');
if ($nombre === '') die('Debe indicar el nombre del sorteo.');

$evento = EventoEstado::exigirModificable($db, $eventoId);

$stmt = $db->prepare("SELECT id FROM sorteos WHERE evento_id = :evento_id LIMIT 1");
$stmt->execute([':evento_id' => $eventoId]);
$existente = $stmt->fetch();

if ($existente) {
    header('Location: sorteo.php?evento_id=' . $eventoId . '&error=' . urlencode('Este evento ya tiene un sorteo creado.'));
    exit;
}

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);

if ($usuarioId <= 0) die('Sesión de usuario no válida.');

$stmt = $db->prepare("SELECT id FROM usuarios WHERE id = :id AND activo = 1 LIMIT 1");
$stmt->execute([':id' => $usuarioId]);
if (!$stmt->fetchColumn()) die('El usuario actual no está activo.');

$stmt = $db->prepare("
    INSERT INTO sorteos
    (evento_id, nombre, cantidad_ganadores, solo_asistentes, creado_por)
    VALUES (:evento_id, :nombre, 0, 1, :creado_por)
");
$stmt->execute([
    ':evento_id' => $eventoId,
    ':nombre' => $nombre,
    ':creado_por' => $usuarioId
]);

header('Location: sorteo.php?evento_id=' . $eventoId . '&ok=' . urlencode('Sorteo creado correctamente.'));
exit;