<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';

$db = Database::connection();
$bloqueado = false;
$error = '';
$ok = false;

$stmt = $db->query("SELECT COUNT(*) FROM usuarios WHERE password_hash IS NOT NULL AND password_hash <> ''");
if ((int)$stmt->fetchColumn() > 0) {
    $bloqueado = true;
}

if (!$bloqueado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmacion = (string)($_POST['confirmacion'] ?? '');

    try {
        if ($nombre === '' || $usuario === '' || $password === '') {
            throw new RuntimeException('Todos los campos son obligatorios.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
        }
        if ($password !== $confirmacion) {
            throw new RuntimeException('Las contraseñas no coinciden.');
        }

        $stmt = $db->prepare("SELECT id FROM usuarios WHERE usuario_login = :usuario LIMIT 1");
        $stmt->execute([':usuario' => $usuario]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('Ese usuario ya existe.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO usuarios (nombre, usuario_login, password_hash, rol, activo) VALUES (:nombre,:usuario,:hash,'ADMIN',1)");
        $stmt->execute([':nombre'=>$nombre, ':usuario'=>$usuario, ':hash'=>$hash]);
        $ok = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configuración inicial</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-family:Arial}.card{width:min(440px,92vw);background:#fff;padding:30px;border-radius:15px;box-shadow:0 8px 30px #0002}h1{margin-top:0}.campo{margin:15px 0}.campo label{display:block;font-weight:bold;margin-bottom:6px}.campo input{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:7px;box-sizing:border-box}.btn{width:100%;padding:13px;border:0;border-radius:8px;background:#2563eb;color:white;font-weight:bold;cursor:pointer}.error{padding:12px;background:#fee2e2;color:#991b1b;border-radius:8px}.ok{padding:15px;background:#dcfce7;color:#166534;border-radius:8px}.nota{font-size:13px;color:#64748b;line-height:1.5}</style>
</head>
<body><div class="card">
<h1>Configuración inicial</h1>
<?php if($bloqueado): ?>
<div class="error">La configuración inicial ya fue realizada.</div><p><a href="login.php">Ir al inicio de sesión</a></p>
<?php elseif($ok): ?>
<div class="ok"><strong>Administrador creado correctamente.</strong><br>Ya puede iniciar sesión.</div><p><a href="login.php">Ir al inicio de sesión</a></p>
<?php else: ?>
<p>Este paso crea el primer usuario ADMIN del sistema.</p>
<?php if($error!==''): ?><div class="error"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
<form method="post" autocomplete="off">
<div class="campo"><label>Nombre</label><input name="nombre" maxlength="150" required></div>
<div class="campo"><label>Usuario</label><input name="usuario" maxlength="100" required></div>
<div class="campo"><label>Contraseña</label><input name="password" type="password" minlength="8" required></div>
<div class="campo"><label>Confirmar contraseña</label><input name="confirmacion" type="password" minlength="8" required></div>
<button class="btn">Crear administrador</button>
</form>
<p class="nota">Por seguridad, esta página deja de funcionar automáticamente cuando ya existe un usuario con contraseña configurada.</p>
<?php endif; ?>
</div></body></html>
