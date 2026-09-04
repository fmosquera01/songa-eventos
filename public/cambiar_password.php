<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';

exigirLogin();

$db = Database::connection();
$error = '';
$ok = '';

$usuarioId = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = (string)($_POST['password_actual'] ?? '');
    $nueva = (string)($_POST['password_nueva'] ?? '');
    $confirmacion = (string)($_POST['password_confirmacion'] ?? '');

    try {
        if ($actual === '' || $nueva === '' || $confirmacion === '') {
            throw new RuntimeException('Todos los campos son obligatorios.');
        }

        if (strlen($nueva) < 8) {
            throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
        }

        if ($nueva !== $confirmacion) {
            throw new RuntimeException('Las contraseñas nuevas no coinciden.');
        }

        $stmt = $db->prepare('SELECT password_hash FROM usuarios WHERE id = :id AND activo = 1 LIMIT 1');
        $stmt->execute([':id' => $usuarioId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($actual, (string)$hash)) {
            throw new RuntimeException('La contraseña actual es incorrecta.');
        }

        if (password_verify($nueva, (string)$hash)) {
            throw new RuntimeException('La nueva contraseña debe ser diferente de la actual.');
        }

        $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);

        $stmt = $db->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
        $stmt->execute([
            ':hash' => $nuevoHash,
            ':id' => $usuarioId
        ]);

        $ok = 'Contraseña actualizada correctamente.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$volver = usuarioEsAdmin() ? '/admin/eventos.php' : '/operador/registro.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cambiar contraseña - Songa Event Control</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a}.card{width:min(440px,92vw);background:#fff;border-radius:16px;padding:32px;box-shadow:0 8px 30px rgba(0,0,0,.10)}h1{margin:0 0 8px}.sub{color:#64748b;margin:0 0 24px}.campo{margin-bottom:16px}.campo label{display:block;font-weight:bold;margin-bottom:7px}.campo input{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:8px;font-size:16px}.btn{width:100%;padding:13px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:bold;cursor:pointer}.link{display:block;text-align:center;margin-top:18px;color:#2563eb;text-decoration:none}.msg{padding:12px;border-radius:8px;margin-bottom:18px}.error{background:#fee2e2;color:#991b1b}.ok{background:#dcfce7;color:#166534}
</style>
</head>
<body>
<div class="card">
    <h1>Cambiar contraseña</h1>
    <p class="sub">Usuario: <?= htmlspecialchars((string)($_SESSION['usuario_login'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

    <?php if ($error !== ''): ?><div class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($ok !== ''): ?><div class="msg ok"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="campo">
            <label for="password_actual">Contraseña actual</label>
            <input id="password_actual" name="password_actual" type="password" required autofocus>
        </div>
        <div class="campo">
            <label for="password_nueva">Nueva contraseña</label>
            <input id="password_nueva" name="password_nueva" type="password" minlength="8" required>
        </div>
        <div class="campo">
            <label for="password_confirmacion">Confirmar nueva contraseña</label>
            <input id="password_confirmacion" name="password_confirmacion" type="password" minlength="8" required>
        </div>
        <button class="btn" type="submit">Cambiar contraseña</button>
    </form>

    <a class="link" href="<?= htmlspecialchars($volver, ENT_QUOTES, 'UTF-8') ?>">← Volver al sistema</a>
</div>
</body>
</html>
