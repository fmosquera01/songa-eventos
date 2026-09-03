<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/EventoEstado.php';
exigirAdmin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Método no permitido.');}
$eventoId=(int)($_POST['evento_id']??0);
$db=Database::connection();
EventoEstado::exigirModificable($db,$eventoId);
$cod=trim((string)($_POST['cod']??''));$cedula=trim((string)($_POST['cedula']??''));$nombre=trim((string)($_POST['apellidos_nombres']??''));$area=trim((string)($_POST['area']??''));$empresa=trim((string)($_POST['empresa']??''));$estado=trim((string)($_POST['estado']??'ACTIVO'));
if($cod===''||$nombre==='')die('Código y nombre son obligatorios.');
try{$st=$db->prepare("INSERT INTO evento_colaboradores (evento_id,cod,cedula,apellidos_nombres,area,empresa,estado,fila_excel) VALUES (:evento_id,:cod,:cedula,:nombre,:area,:empresa,:estado,NULL)");$st->execute([':evento_id'=>$eventoId,':cod'=>$cod,':cedula'=>$cedula,':nombre'=>$nombre,':area'=>$area,':empresa'=>$empresa,':estado'=>$estado]);header('Location: colaboradores.php?evento_id='.$eventoId);exit;}catch(Throwable $e){http_response_code(400);exit('No se pudo guardar el colaborador: '.htmlspecialchars($e->getMessage()));}