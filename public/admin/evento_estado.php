<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/EventoEstado.php';
exigirAdmin();
$db=Database::connection();
$eventoId=isset($_GET['id'])?(int)$_GET['id']:0;
$nuevoEstado=isset($_GET['estado'])?strtoupper(trim((string)$_GET['estado'])):'';
if($eventoId<=0||!in_array($nuevoEstado,['ACTIVO','FINALIZADO','CANCELADO'],true)){header('Location: eventos.php?error='.urlencode('Solicitud no válida.'));exit;}
try{
 $evento=EventoEstado::exigirEvento($db,$eventoId);
 $actual=strtoupper(trim((string)$evento['estado']));
 if($nuevoEstado==='ACTIVO'&&$actual!=='BORRADOR') throw new RuntimeException('Solo se puede activar un evento BORRADOR.');
 if($nuevoEstado==='FINALIZADO'&&$actual!=='ACTIVO') throw new RuntimeException('Solo se puede finalizar un evento ACTIVO.');
 if($nuevoEstado==='CANCELADO'&&in_array($actual,['FINALIZADO','CANCELADO'],true)) throw new RuntimeException('No se puede cancelar un evento FINALIZADO o CANCELADO.');
 EventoEstado::cambiar($db,$eventoId,$nuevoEstado);
 $mensaje='El evento "'.$evento['nombre'].'" fue actualizado correctamente.';
 header('Location: eventos.php?ok='.urlencode($mensaje));exit;
}catch(Throwable $e){header('Location: eventos.php?error='.urlencode($e->getMessage()));exit;}
