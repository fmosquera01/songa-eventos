<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';

exigirAdmin();

$eventoId = (int)($_GET['evento_id'] ?? 0);

if ($eventoId <= 0) {
    die('Evento no válido.');
}

$db = Database::connection();

$stmtEvento = $db->prepare('
    SELECT nombre
    FROM eventos
    WHERE id = :id
    LIMIT 1
');

$stmtEvento->execute([
    ':id' => $eventoId
]);

$evento = $stmtEvento->fetchColumn();

if (!$evento) {
    die('Evento no encontrado.');
}

$stmt = $db->prepare("
    SELECT
        r.fecha_hora,
        c.cod,
        c.cedula,
        c.apellidos_nombres,
        c.area,
        c.empresa,
        r.metodo,
        u.nombre AS usuario_nombre,
        u.usuario,
        r.dispositivo
    FROM registros r
    INNER JOIN evento_colaboradores c
        ON c.id = r.colaborador_id
    LEFT JOIN usuarios u
        ON u.id = r.usuario_id
    WHERE r.evento_id = :evento_id
      AND r.tipo_registro = 'ASISTENCIA'
    ORDER BY
        r.fecha_hora ASC,
        r.id ASC
");

$stmt->execute([
    ':evento_id' => $eventoId
]);

$filename =
    'asistencia_evento_' .
    $eventoId . '_' .
    date('Ymd_His') .
    '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "\xEF\xBB\xBF";

echo '<table border="1">';
echo '<tr>';
echo '<th>#</th>';
echo '<th>Fecha / Hora</th>';
echo '<th>COD</th>';
echo '<th>Cédula</th>';
echo '<th>Apellidos y nombres</th>';
echo '<th>Área</th>';
echo '<th>Empresa</th>';
echo '<th>Método</th>';
echo '<th>Registrado por</th>';
echo '<th>Dispositivo</th>';
echo '</tr>';

$numero = 1;

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<tr>';

    echo '<td>' . $numero++ . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['fecha_hora'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['cod'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['apellidos_nombres'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['area'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['empresa'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['metodo'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';

    $registradoPor =
        !empty($r['usuario_nombre'])
            ? (string)$r['usuario_nombre']
            : (string)($r['usuario'] ?? '');

    echo '<td>' . htmlspecialchars($registradoPor, ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)($r['dispositivo'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';

    echo '</tr>';
}

echo '</table>';
