<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/EventoEstado.php';

exigirAdmin();

$db = Database::connection();

$eventoId = isset($_POST['evento_id']) ? (int)$_POST['evento_id'] : 0;
$sorteoId = isset($_POST['sorteo_id']) ? (int)$_POST['sorteo_id'] : 0;
$nombre = trim((string)($_POST['nombre'] ?? ''));

if ($eventoId <= 0 || $sorteoId <= 0) die('Datos no válidos.');
if ($nombre === '') die('Debe indicar el nombre del premio.');

EventoEstado::exigirModificable($db, $eventoId);

$stmt = $db->prepare("SELECT id, evento_id FROM sorteos WHERE id = :id AND evento_id = :evento_id LIMIT 1");
$stmt->execute([':id' => $sorteoId, ':evento_id' => $eventoId]);
$sorteo = $stmt->fetch();

if (!$sorteo) die('Sorteo no encontrado.');

$stmt = $db->prepare("SELECT COALESCE(MAX(posicion), 0) + 1 FROM sorteo_premios WHERE sorteo_id = :sorteo_id");
$stmt->execute([':sorteo_id' => $sorteoId]);
$posicion = (int)$stmt->fetchColumn();

$stmt = $db->prepare("INSERT INTO sorteo_premios (sorteo_id, nombre, posicion) VALUES (:sorteo_id, :nombre, :posicion)");
$stmt->execute([
    ':sorteo_id' => $sorteoId,
    ':nombre' => $nombre,
    ':posicion' => $posicion
]);

$stmt = $db->prepare("
    UPDATE sorteos
    SET cantidad_ganadores = (
        SELECT COUNT(*) FROM sorteo_premios WHERE sorteo_id = :sorteo_id
    )
    WHERE id = :id
");
$stmt->execute([':sorteo_id' => $sorteoId, ':id' => $sorteoId]);

header('Location: sorteo.php?evento_id=' . $eventoId . '&ok=' . urlencode('Premio agregado correctamente.'));
exit;