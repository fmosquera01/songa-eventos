<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';

exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: eventos.php');
    exit;
}

try {
    $db = Database::connection();

    $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = :id AND activo = 1 LIMIT 1");
    $stmt->execute([':id' => $usuarioId]);
    if (!(int)$stmt->fetchColumn()) {
        throw new RuntimeException('La sesión no corresponde a un usuario activo.');
    }

    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $tipo = trim((string)($_POST['tipo'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $fechaEvento = !empty($_POST['fecha_evento']) ? (string)$_POST['fecha_evento'] : null;
    $horaInicio = !empty($_POST['hora_inicio']) ? (string)$_POST['hora_inicio'] : null;
    $horaFin = !empty($_POST['hora_fin']) ? (string)$_POST['hora_fin'] : null;
    $validarEstado = isset($_POST['validar_estado']) ? 1 : 0;
    $permitirDuplicado = isset($_POST['permitir_duplicado']) ? 1 : 0;

    if ($nombre === '') {
        throw new RuntimeException('El nombre del evento es obligatorio.');
    }
    if ($tipo === '') {
        throw new RuntimeException('Debe seleccionar el tipo de evento.');
    }

    $stmt = $db->prepare("INSERT INTO eventos
        (nombre, descripcion, tipo, fecha_evento, hora_inicio, hora_fin, estado, creado_por, validar_estado, permitir_duplicado)
        VALUES (:nombre, :descripcion, :tipo, :fecha_evento, :hora_inicio, :hora_fin, 'BORRADOR', :creado_por, :validar_estado, :permitir_duplicado)");

    $stmt->execute([
        ':nombre' => $nombre,
        ':descripcion' => $descripcion !== '' ? $descripcion : null,
        ':tipo' => $tipo,
        ':fecha_evento' => $fechaEvento,
        ':hora_inicio' => $horaInicio,
        ':hora_fin' => $horaFin,
        ':creado_por' => $usuarioId,
        ':validar_estado' => $validarEstado,
        ':permitir_duplicado' => $permitirDuplicado
    ]);

    header('Location: evento.php?id=' . (int)$db->lastInsertId());
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="evento_nuevo.php">Volver</a></p>';
}
