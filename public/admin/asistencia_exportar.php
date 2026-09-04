<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
exigirAdmin();
$eventoId=(int)($_GET['evento_id']??0);if($eventoId<=0)die('Evento no válido.');
$db=Database::connection();
$st=$db->prepare("SELECT nombre FROM eventos WHERE id=:id LIMIT 1");$st->execute([':id'=>$eventoId]);$evento=$st->fetchColumn();if(!$evento)die('Evento no encontrado.');
$st=$db->prepare("SELECT r.fecha_hora,r.metodo,r.dispositivo,r.ip,r.observacion,c.cod,c.cedula,c.apellidos_nombres,c.area,c.empresa,c.estado,u.usuario,u.nombre AS usuario_nombre FROM registros r INNER JOIN evento_colaboradores c ON c.id=r.colaborador_id LEFT JOIN usuarios u ON u.id=r.usuario_id WHERE r.evento_id=:evento_id AND r.tipo_registro='ASISTENCIA' ORDER BY r.fecha_hora ASC,r.id ASC");$st->execute([':evento_id'=>$eventoId]);
$filename='asistencia_evento_'.$eventoId.'_'.date('Ymd_His').'.xls';header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');echo "\xEF\xBB\xBF";
echo '<table border="1"><tr><th>Fecha y hora</th><th>Método</th><th>Dispositivo</th><th>IP</th><th>Código</th><th>Cédula</th><th>Apellidos y nombres</th><th>Área</th><th>Empresa</th><th>Estado</th><th>Usuario</th><th>Nombre usuario</th><th>Observación</th></tr>';
while($r=$st->fetch(PDO::FETCH_ASSOC)){echo '<tr>';foreach($r as $v)echo '<td>'.htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8').'</td>';echo '</tr>';}
echo '</table>';