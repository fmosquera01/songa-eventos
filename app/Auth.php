<?php

declare(strict_types=1);

/*
 * Algunas páginas antiguas del sistema todavía llaman directamente a
 * session_start(). Auth.php inicia la sesión cuando corresponde y silencia
 * únicamente el Notice generado por una segunda llamada dentro de la misma
 * petición. No se ocultan otros errores de PHP.
 */
set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        if (
            $severity === E_NOTICE
            && str_contains(
                $message,
                'session_start(): Ignoring session_start() because a session is already active'
            )
        ) {
            return true;
        }

        return false;
    }
);

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
