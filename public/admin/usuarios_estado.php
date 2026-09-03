<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
exigirAdmin();
$id=(int)($_GET['id']??0);
$activo=isset($_GET['activo']) && (int)$_GET['activo']===1 ? 1 : 0;
if($id<=0){header('Location: usuarios.php?error='.urlencode('Usuario no válido.'));exit;}
try{
 $db=Database::connection();
 if($id===(int)$_SESSION['usuario_id'] && $activo===0) throw new RuntimeException('No puede desactivar su propio usuario.');
 $stmt=$db->prepare("UPDATE usuarios SET activo=:activo WHERE id=:id LIMIT 1");
 $stmt->execute([':activo'=>$activo,':id'=>$id]);
 header('Location: usuarios.php?ok='.urlencode('Estado del usuario actualizado.'));exit;
}catch(Throwable $e){header('Location: usuarios.php?error='.urlencode($e->getMessage()));exit;}
