<?php

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($uri === '/login.php' || $uri === '/logout.php') {
    return;
}

if (str_starts_with($uri, '/admin/')) {
    exigirAdmin();
    return;
}

if (str_starts_with($uri, '/operador/')) {
    exigirLogin();
    return;
}
