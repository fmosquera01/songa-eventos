<?php

declare(strict_types=1);

/*
 * Inicialización local del administrador.
 *
 * EJECUCIÓN DESDE LA RAÍZ DEL PROYECTO:
 *   php database/crear_admin_inicial.php
 *
 * Crea/completa el usuario admin con:
 *   Usuario: admin
 *   Contraseña temporal: Admin123
 *
 * Para una instalación existente donde se desconoce la contraseña actual:
 *   php database/crear_admin_inicial.php --reset
 *
 * Este archivo NO está dentro de public/ y por tanto no es accesible desde el navegador.
 */

require_once __DIR__ . '/../app/Database.php';

$db = Database::connection();
$reset = in_array('--reset', $argv ?? [], true);

$usuario = 'admin';
$nombre = 'Administrador';
$login = 'admin';
$password = 'Admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare('SELECT id, password_hash FROM usuarios WHERE usuario = :usuario LIMIT 1');
$stmt->execute([':usuario' => $usuario]);
$existente = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existente) {
    if ($reset || empty($existente['password_hash'])) {
        $stmt = $db->prepare('UPDATE usuarios SET nombre = :nombre, usuario_login = :login, password_hash = :hash, rol = \'ADMIN\', activo = 1 WHERE id = :id');
        $stmt->execute([
            ':nombre' => $nombre,
            ':login' => $login,
            ':hash' => $hash,
            ':id' => (int)$existente['id']
        ]);
        echo "Administrador inicial actualizado.\n";
    } else {
        $stmt = $db->prepare('UPDATE usuarios SET usuario_login = :login, rol = \'ADMIN\', activo = 1 WHERE id = :id');
        $stmt->execute([
            ':login' => $login,
            ':id' => (int)$existente['id']
        ]);
        echo "El administrador ya tenía contraseña. Solo se normalizó el usuario de acceso.\n";
        echo "Si necesita restablecerla a Admin123, ejecute nuevamente con --reset.\n";
    }
} else {
    $stmt = $db->prepare('INSERT INTO usuarios (usuario, nombre, usuario_login, password_hash, rol, activo) VALUES (:usuario, :nombre, :login, :hash, \'ADMIN\', 1)');
    $stmt->execute([
        ':usuario' => $usuario,
        ':nombre' => $nombre,
        ':login' => $login,
        ':hash' => $hash
    ]);
    echo "Administrador inicial creado.\n";
}

echo "Usuario: admin\n";
echo "Contraseña temporal: Admin123\n";
echo "IMPORTANTE: cambiar la contraseña después del primer ingreso.\n";
