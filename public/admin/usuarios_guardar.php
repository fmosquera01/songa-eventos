<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
exigirAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: usuarios.php'); exit; }
try {
    $nombre=trim((string)($_POST['nombre']??''));
    $login=trim((string)($_POST['usuario_login']??''));
    $password=(string)($_POST['password']??'');
    $rol=strtoupper(trim((string)($_POST['rol']??'OPERADOR')));
    if($nombre===''||$login===''||$password==='') throw new RuntimeException('Todos los campos son obligatorios.');
    if(strlen($password)<8) throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
    if(!in_array($rol,['ADMIN','OPERADOR'],true)) throw new RuntimeException('Rol no válido.');
    $db=Database::connection();
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $stmt=$db->prepare("INSERT INTO usuarios (usuario, nombre, usuario_login, password_hash, rol, activo) VALUES (:usuario,:nombre,:login,:hash,:rol,1)");
    $stmt->execute([':usuario'=>$login,':nombre'=>$nombre,':login'=>$login,':hash'=>$hash,':rol'=>$rol]);
    header('Location: usuarios.php?ok='.urlencode('Usuario creado correctamente.')); exit;
} catch(Throwable $e) { header('Location: usuarios.php?error='.urlencode($e->getMessage())); exit; }
