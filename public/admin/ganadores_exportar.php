<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
exigirAdmin();
$eventoId=(int)($_GET['evento_id']??0);if($eventoId<=0)die('Evento no válido.');
$db=Database::connection();
$st=$db->prepare("SELECT nombre FROM eventos WHERE id=:id LIMIT 1");$st->execute([':id'=>$eventoId]);$evento=$st->fetchColumn();if(!$evento)die('Evento no encontrado.');
$st=$db->prepare("SELECT sp.posicion,sp.nombre AS premio,sg.fecha_hora,c.cod,c.cedula,c.apellidos_nombres,c.area,c.empresa,c.estado,u.usuario,u.nombre AS usuario_nombre FROM sorteo_ganadores sg INNER JOIN sorteos s ON s.id=sg.sorteo_id INNER JOIN sorteo_premios sp ON sp.id=sg.premio_id INNER JOIN evento_colaboradores c ON c.id=sg.colaborador_id LEFT JOIN usuarios u ON u.id=sg.usuario_id WHERE s.evento_id=:evento_id ORDER BY sp.posicion,sg.id");$st->execute([':evento_id'=>$eventoId]);
$filename='ganadores_evento_'.$eventoId.'_'.date('Ymd_His').'.xls';header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');echo "\xEF\xBB\xBF";
echo '<table border="1"><tr><th>Posición</th><th>Premio</th><th>Fecha y hora</th><th>Código</th><th>Cédula</th><th>Apellidos y nombres</th><th>Área</th><th>Empresa</th><th>Estado</th><th>Usuario</th><th>Nombre usuario</th></tr>';
while($r=$st->fetch(PDO::FETCH_ASSOC)){echo '<tr>';foreach($r as $v)echo '<td>'.htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8').'</td>';echo '</tr>';}
echo '</table>';