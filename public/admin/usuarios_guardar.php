<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

try {
    $id = (int)($_POST['id'] ?? 0);

    $db = Database::connection();

    // Cambio de contraseña de un usuario existente.
    if ($id > 0) {
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($password === '' || $passwordConfirm === '') {
            throw new RuntimeException('Debe ingresar y confirmar la nueva contraseña.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
        }

        if ($password !== $passwordConfirm) {
            throw new RuntimeException('La nueva contraseña y su confirmación no coinciden.');
        }

        $stmt = $db->prepare("SELECT usuario_login FROM usuarios WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            throw new RuntimeException('Usuario no encontrado.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($hash === false) {
            throw new RuntimeException('No fue posible generar la contraseña.');
        }

        $stmt = $db->prepare("
            UPDATE usuarios
            SET password_hash = :hash
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':hash' => $hash,
            ':id' => $id
        ]);

        header('Location: usuarios.php?ok=' . urlencode('Contraseña actualizada correctamente para ' . $usuario['usuario_login'] . '.'));
        exit;
    }

    // Creación de usuario.
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $login = trim((string)($_POST['usuario_login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $rol = strtoupper(trim((string)($_POST['rol'] ?? 'OPERADOR')));

    if ($nombre === '' || $login === '' || $password === '') {
        throw new RuntimeException('Todos los campos son obligatorios.');
    }

    if (strlen($password) < 8) {
        throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
    }

    if (!in_array($rol, ['ADMIN', 'OPERADOR'], true)) {
        throw new RuntimeException('Rol no válido.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($hash === false) {
        throw new RuntimeException('No fue posible generar la contraseña.');
    }

    $stmt = $db->prepare("
        INSERT INTO usuarios
            (usuario, nombre, usuario_login, password_hash, rol, activo)
        VALUES
            (:usuario, :nombre, :login, :hash, :rol, 1)
    ");

    $stmt->execute([
        ':usuario' => $login,
        ':nombre' => $nombre,
        ':login' => $login,
        ':hash' => $hash,
        ':rol' => $rol
    ]);

    header('Location: usuarios.php?ok=' . urlencode('Usuario creado correctamente.'));
    exit;

} catch (Throwable $e) {
    header('Location: usuarios.php?error=' . urlencode($e->getMessage()));
    exit;
}
