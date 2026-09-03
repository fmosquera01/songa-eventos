<?php
declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($uri === '/login.php' || $uri === '/logout.php') {
    return;
}

if (str_starts_with($uri, '/admin/')) {
    exigirAdmin();

    $eventoId = (int)($_POST['evento_id'] ?? $_GET['evento_id'] ?? $_GET['id'] ?? 0);

    if ($eventoId > 0) {
        require_once __DIR__ . '/Database.php';

        try {
            $dbGuard = Database::connection();
            $stmtGuard = $dbGuard->prepare("SELECT estado FROM eventos WHERE id = :id LIMIT 1");
            $stmtGuard->execute([':id' => $eventoId]);
            $estadoGuard = strtoupper(trim((string)$stmtGuard->fetchColumn()));

            if ($estadoGuard === 'FINALIZADO') {
                $rutaRelativa = ltrim(substr($uri, strlen('/admin/')), '/');
                $accion = (string)($_POST['action'] ?? $_GET['action'] ?? '');

                if (str_starts_with($rutaRelativa, 'importar/')) {
                    http_response_code(403);
                    exit('Evento finalizado: la importación está bloqueada.');
                }

                if ($rutaRelativa === 'sorteo_api.php' && $accion !== 'bootstrap') {
                    http_response_code(403);
                    exit('Evento finalizado: el sorteo está en modo consulta.');
                }

                if (in_array($rutaRelativa, ['sorteo_crear.php', 'sorteo_premio.php', 'sorteo_resultado.php'], true)) {
                    http_response_code(403);
                    exit('Evento finalizado: no se pueden modificar premios ni sorteos.');
                }
            }
        } catch (Throwable $e) {
            // Las páginas ejecutan sus propias validaciones de base de datos.
        }
    }

    return;
}

if (str_starts_with($uri, '/operador/')) {
    exigirLogin();
    return;
}
