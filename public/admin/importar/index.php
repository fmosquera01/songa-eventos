<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Auth.php';
require_once __DIR__ . '/../../../app/Database.php';

exigirAdmin();

$eventoId = filter_input(INPUT_GET, 'evento_id', FILTER_VALIDATE_INT);
if (!$eventoId) die('Evento no válido.');

$db = Database::connection();
$stmt = $db->prepare('SELECT id, nombre, estado FROM eventos WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $eventoId]);
$evento = $stmt->fetch();
if (!$evento) die('Evento no encontrado.');
if (!in_array($evento['estado'], ['BORRADOR', 'ACTIVO'], true)) {
    http_response_code(403);
    die('El evento no permite importar colaboradores porque está en estado ' . htmlspecialchars($evento['estado']) . '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Importar colaboradores</title><style>
body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9}.container{max-width:800px;margin:50px auto;padding:20px}.card{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08)}h1{margin-top:0}.evento{background:#f3f4f6;padding:15px;border-radius:7px;margin-bottom:25px}.campo{margin-bottom:20px}label{display:block;font-weight:bold;margin-bottom:8px}input[type=file]{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:6px}button{background:#2563eb;color:white;border:0;padding:12px 20px;border-radius:6px;cursor:pointer;font-size:15px}.volver{display:inline-block;margin-left:10px;background:#6b7280;color:white;padding:12px 20px;border-radius:6px;text-decoration:none}.info{background:#eff6ff;border-left:4px solid #2563eb;padding:15px;margin-bottom:20px}
</style></head>
<body><div class="container"><div class="card"><h1>Importar colaboradores</h1><div class="evento"><strong>Evento:</strong> <?= htmlspecialchars($evento['nombre']) ?><br><strong>Estado:</strong> <?= htmlspecialchars($evento['estado']) ?></div><div class="info">El archivo debe contener la primera fila con los nombres de las columnas.<br><br>Formatos permitidos: <strong>XLSX, XLS, CSV</strong></div><form action="analizar.php" method="POST" enctype="multipart/form-data"><input type="hidden" name="evento_id" value="<?= $eventoId ?>"><div class="campo"><label>Archivo Excel</label><input type="file" name="archivo" accept=".xlsx,.xls,.csv" required></div><button type="submit">Analizar archivo</button><a href="../evento.php?id=<?= $eventoId ?>" class="volver">Cancelar</a></form></div></div></body></html>