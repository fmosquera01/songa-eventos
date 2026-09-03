<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/EventoEstado.php';
exigirAdmin();
$eventoId=(int)($_POST['evento_id']??0);$id=(int)($_POST['id']??0);
if($eventoId<=0||$id<=0)die('Datos inválidos.');
$db=Database::connection();EventoEstado::exigirModificable($db,$eventoId);
$st=$db->prepare("SELECT COUNT(*) FROM registros WHERE evento_id=:evento_id AND colaborador_id=:id");$st->execute([':evento_id'=>$eventoId,':id'=>$id]);
if((int)$st->fetchColumn()>0)die('No se puede eliminar: el colaborador ya tiene registros de asistencia.');
$st=$db->prepare("DELETE FROM evento_colaboradores WHERE id=:id AND evento_id=:evento_id");$st->execute([':id'=>$id,':evento_id'=>$eventoId]);
header('Location: colaboradores.php?evento_id='.$eventoId);exit;