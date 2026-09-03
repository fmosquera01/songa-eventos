<?php

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($uri === '/login.php' || $uri === '/logout.php') {
    return;
}

if (str_starts_with($uri, '/admin/')) {
    exigirAdmin();

    // Los eventos FINALIZADOS quedan en modo consulta.
    // Este bloqueo es adicional a las validaciones de cada módulo.
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

                // La importación no tiene ningún acceso operativo en FINALIZADO.
                if (str_starts_with($rutaRelativa, 'importar/')) {
                    http_response_code(403);
                    exit('Evento finalizado: la importación está bloqueada.');
                }

                // El API del sorteo conserva bootstrap/consultas, pero bloquea toda acción que modifique datos.
                if ($rutaRelativa === 'sorteo_api.php' && $accion !== 'bootstrap') {
                    http_response_code(403);
                    exit('Evento finalizado: el sorteo está en modo consulta.');
                }

                if (in_array($rutaRelativa, ['sorteo_crear.php', 'sorteo_premio.php'], true)) {
                    http_response_code(403);
                    exit('Evento finalizado: no se pueden modificar premios ni sorteos.');
                }
            }
        } catch (Throwable $e) {
            // El guard no debe ocultar errores normales de autenticación/ruteo.
            // Las páginas ejecutarán sus propias validaciones de base de datos.
        }
    }

    return;
}

if (str_starts_with($uri, '/operador/')) {
    exigirLogin();
    return;
}
