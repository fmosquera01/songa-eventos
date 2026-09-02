<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';

exigirAdmin();

$eventoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$eventoId) {
    die('Evento no válido.');
}

$db = Database::connection();

$stmt = $db->prepare("
    SELECT e.*, u.nombre AS creador
    FROM eventos e
    INNER JOIN usuarios u ON u.id = e.creado_por
    WHERE e.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $eventoId]);
$evento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evento) {
    die('Evento no encontrado.');
}

$estado = strtoupper(trim((string)$evento['estado']));
$finalizado = $estado === 'FINALIZADO';

$stmt = $db->prepare("SELECT COUNT(*) FROM evento_colaboradores WHERE evento_id = :evento_id");
$stmt->execute([':evento_id' => $eventoId]);
$totalColaboradores = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(DISTINCT colaborador_id) FROM registros WHERE evento_id = :evento_id AND tipo_registro = 'ASISTENCIA'");
$stmt->execute([':evento_id' => $eventoId]);
$totalAsistencias = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM sorteo_premios sp INNER JOIN sorteos s ON s.id = sp.sorteo_id WHERE s.evento_id = :evento_id");
$stmt->execute([':evento_id' => $eventoId]);
$totalSorteos = (int)$stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars((string)$evento['nombre'], ENT_QUOTES, 'UTF-8') ?> - Songa Event Control</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937}.navbar{background:#1f2937;color:white;padding:16px 30px}.navbar h1{margin:0;font-size:20px}.container{max-width:1200px;margin:30px auto;padding:0 20px}.volver{display:inline-block;margin-bottom:20px;color:#2563eb;text-decoration:none}.header,.menu,.stat{background:white;border-radius:10px}.header{padding:25px;margin-bottom:20px}.header-top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px}.header h2{margin:0 0 10px;font-size:28px}.descripcion{color:#6b7280}.estado{display:inline-block;padding:7px 12px;border-radius:20px;font-size:13px;font-weight:bold}.BORRADOR{background:#e5e7eb;color:#374151}.ACTIVO{background:#dcfce7;color:#166534}.FINALIZADO{background:#dbeafe;color:#1e40af}.CANCELADO{background:#fee2e2;color:#991b1b}.datos{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-top:25px}.dato{background:#f9fafb;padding:15px;border-radius:8px}.dato-label{font-size:12px;color:#6b7280;margin-bottom:5px}.dato-valor{font-weight:bold}.estadisticas{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px}.stat{padding:20px}.stat-numero{font-size:32px;font-weight:bold}.stat-label{color:#6b7280;margin-top:5px}.menu{padding:25px}.menu h3{margin-top:0}.aviso{margin-bottom:20px;padding:14px 16px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:8px;font-weight:600}.acciones{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px}.accion{border:1px solid #e5e7eb;border-radius:8px;padding:20px;text-decoration:none;color:#1f2937;transition:.2s}.accion:hover{border-color:#2563eb;box-shadow:0 2px 8px rgba(0,0,0,.08);transform:translateY(-2px)}.accion-titulo{font-size:18px;font-weight:bold;margin-bottom:7px}.accion-descripcion{font-size:14px;color:#6b7280}.solo-lectura{background:#f8fafc;border-color:#cbd5e1}.bloqueado{opacity:.6;cursor:not-allowed}.bloqueado:hover{transform:none;box-shadow:none;border-color:#e5e7eb}@media(max-width:700px){.header-top{flex-direction:column}}
</style>
</head>
<body>
<div class="navbar"><h1>Songa Event Control</h1></div>
<div class="container">
<a href="eventos.php" class="volver">← Volver a eventos</a>
<div class="header">
<div class="header-top">
<div>
<h2><?= htmlspecialchars((string)$evento['nombre'], ENT_QUOTES, 'UTF-8') ?></h2>
<?php if (!empty($evento['descripcion'])): ?><div class="descripcion"><?= nl2br(htmlspecialchars((string)$evento['descripcion'], ENT_QUOTES, 'UTF-8')) ?></div><?php endif; ?>
</div>
<span class="estado <?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?></span>
</div>
<div class="datos">
<div class="dato"><div class="dato-label">Tipo de evento</div><div class="dato-valor"><?= htmlspecialchars((string)$evento['tipo'], ENT_QUOTES, 'UTF-8') ?></div></div>
<div class="dato"><div class="dato-label">Fecha</div><div class="dato-valor"><?= htmlspecialchars((string)$evento['fecha_evento'], ENT_QUOTES, 'UTF-8') ?></div></div>
<div class="dato"><div class="dato-label">Horario</div><div class="dato-valor"><?= htmlspecialchars((string)$evento['hora_inicio'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string)$evento['hora_fin'], ENT_QUOTES, 'UTF-8') ?></div></div>
<div class="dato"><div class="dato-label">Creado por</div><div class="dato-valor"><?= htmlspecialchars((string)$evento['creador'], ENT_QUOTES, 'UTF-8') ?></div></div>
</div>
</div>
<div class="estadisticas">
<div class="stat"><div class="stat-numero"><?= $totalColaboradores ?></div><div class="stat-label">Colaboradores cargados</div></div>
<div class="stat"><div class="stat-numero"><?= $totalAsistencias ?></div><div class="stat-label">Asistentes registrados</div></div>
<div class="stat"><div class="stat-numero"><?= $totalSorteos ?></div><div class="stat-label">Premios registrados</div></div>
</div>
<div class="menu">
<h3><?= $finalizado ? 'Consulta del evento finalizado' : 'Administración del evento' ?></h3>
<?php if ($finalizado): ?><div class="aviso">🔒 Este evento está FINALIZADO. No se permite ninguna modificación. Las opciones disponibles son únicamente de consulta y exportación.</div><?php endif; ?>
<div class="acciones">
<a href="colaboradores.php?evento_id=<?= $eventoId ?>" class="accion solo-lectura"><div class="accion-titulo">👥 Colaboradores</div><div class="accion-descripcion">Consultar el listado de colaboradores y exportarlo a Excel.</div></a>
<a href="asistencia.php?evento_id=<?= $eventoId ?>" class="accion solo-lectura"><div class="accion-titulo">📋 Ver asistencia</div><div class="accion-descripcion">Consultar las asistencias registradas y exportarlas a Excel.</div></a>
<a href="sorteo.php?evento_id=<?= $eventoId ?>" class="accion <?= $finalizado ? 'solo-lectura' : '' ?>"><div class="accion-titulo">🎁 Sorteo</div><div class="accion-descripcion"><?= $finalizado ? 'Consultar premios y ganadores del evento.' : 'Administrar premios y realizar sorteos.' ?></div></a>
<?php if (!$finalizado): ?>
<a href="importar/index.php?evento_id=<?= $eventoId ?>" class="accion"><div class="accion-titulo">📥 Importar colaboradores</div><div class="accion-descripcion">Cargar o reemplazar el listado de colaboradores.</div></a>
<a href="../operador/registro.php" class="accion"><div class="accion-titulo">📷 Registrar asistencia</div><div class="accion-descripcion">Registrar asistencia mediante código o cédula.</div></a>
<a href="configuracion.php?evento_id=<?= $eventoId ?>" class="accion"><div class="accion-titulo">⚙️ Configuración</div><div class="accion-descripcion">Modificar la información y configuración del evento.</div></a>
<?php endif; ?>
</div>
</div>
</div>
</body>
</html>
