<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
exigirAdmin();
$eventoId=(int)($_GET['evento_id']??0);if($eventoId<=0)die('Evento no válido.');
$db=Database::connection();$st=$db->prepare("SELECT nombre FROM eventos WHERE id=:id LIMIT 1");$st->execute([':id'=>$eventoId]);$evento=$st->fetchColumn();if(!$evento)die('Evento no encontrado.');
$st=$db->prepare("SELECT cod,cedula,apellidos_nombres,area,empresa,estado FROM evento_colaboradores WHERE evento_id=:evento_id ORDER BY apellidos_nombres");$st->execute([':evento_id'=>$eventoId]);
$filename='colaboradores_evento_'.$eventoId.'_'.date('Ymd_His').'.xls';header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');echo "\xEF\xBB\xBF";echo '<table border="1"><tr><th>Código</th><th>Cédula</th><th>Apellidos y nombres</th><th>Área</th><th>Empresa</th><th>Estado</th></tr>';while($r=$st->fetch(PDO::FETCH_ASSOC)){echo '<tr>';foreach($r as $v)echo '<td>'.htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8').'</td>';echo '</tr>';}echo '</table>';