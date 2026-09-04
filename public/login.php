<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';

if (usuarioAutenticado()) {
    header('Location: ' . (usuarioEsAdmin() ? '/admin/eventos.php' : '/operador/registro.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($usuario === '' || $password === '') {
        $error = 'Ingrese usuario y contraseña.';
    } else {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                SELECT id, nombre, usuario, usuario_login, password_hash, rol, activo
                FROM usuarios
                WHERE usuario_login = :usuario_login OR usuario = :usuario
                LIMIT 1
            ");
            $stmt->execute([
                ':usuario_login' => $usuario,
                ':usuario' => $usuario,
            ]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$registro ||
                !(int)$registro['activo'] ||
                empty($registro['password_hash']) ||
                !password_verify($password, (string)$registro['password_hash'])
            ) {
                $error = 'Usuario o contraseña incorrectos.';
            } else {
                $rol = strtoupper(trim((string)$registro['rol']));

                if (!in_array($rol, ['ADMIN', 'OPERADOR'], true)) {
                    $error = 'El usuario no tiene un rol válido.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['usuario_id'] = (int)$registro['id'];
                    $_SESSION['usuario_nombre'] = (string)$registro['nombre'];
                    $_SESSION['usuario_login'] = (string)($registro['usuario_login'] ?: $registro['usuario']);
                    $_SESSION['usuario_rol'] = $rol;

                    header('Location: ' . ($rol === 'ADMIN' ? '/admin/eventos.php' : '/operador/registro.php'));
                    exit;
                }
            }
        } catch (Throwable $e) {
            error_log('Songa Event Control - Error de inicio de sesión: ' . $e->getMessage());
            $error = 'No se pudo iniciar sesión. Verifique la configuración de la base de datos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión - Songa Event Control</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a}.card{width:min(420px,92vw);background:#fff;border-radius:16px;padding:35px;box-shadow:0 8px 30px rgba(0,0,0,.10)}h1{margin:0 0 8px;font-size:26px}p{color:#64748b;margin:0 0 25px}.campo{margin-bottom:18px}.campo label{display:block;font-weight:bold;margin-bottom:7px}.campo input{width:100%;padding:13px;border:1px solid #cbd5e1;border-radius:8px;font-size:16px}.btn{width:100%;padding:13px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-size:16px;font-weight:bold;cursor:pointer}.error{margin-bottom:18px;padding:12px;border-radius:8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca}.marca{font-size:13px;color:#94a3b8;margin-top:22px;text-align:center}</style>
</head>
<body>
<div class="card">
    <h1>Songa Event Control</h1>
    <p>Ingrese sus credenciales para continuar.</p>
    <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="POST" autocomplete="off">
        <div class="campo">
            <label for="usuario">Usuario</label>
            <input id="usuario" name="usuario" type="text" maxlength="100" required autofocus>
        </div>
        <div class="campo">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" required>
        </div>
        <button class="btn" type="submit">Iniciar sesión</button>
    </form>
    <div class="marca">Acceso controlado por rol</div>
</div>
</body>
</html>
