<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function usuarioAutenticado(): bool
{
    return isset($_SESSION['usuario_id'], $_SESSION['usuario_rol'])
        && (int)$_SESSION['usuario_id'] > 0
        && in_array($_SESSION['usuario_rol'], ['ADMIN', 'OPERADOR'], true);
}

function usuarioEsAdmin(): bool
{
    return usuarioAutenticado() && $_SESSION['usuario_rol'] === 'ADMIN';
}

function usuarioEsOperador(): bool
{
    return usuarioAutenticado() && $_SESSION['usuario_rol'] === 'OPERADOR';
}

function exigirLogin(): void
{
    if (!usuarioAutenticado()) {
        header('Location: /login.php');
        exit;
    }
}

function exigirAdmin(): void
{
    exigirLogin();

    if (!usuarioEsAdmin()) {
        http_response_code(403);
        exit('Acceso denegado. Se requiere rol ADMIN.');
    }
}

function exigirAdminOOperador(): void
{
    exigirLogin();
}

function cerrarSesion(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
