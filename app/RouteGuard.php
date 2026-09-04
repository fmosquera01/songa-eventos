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

                if ($rutaRelativa === 'sorteo_api.php') {
                    if ($accion !== 'bootstrap') {
                        http_response_code(403);
                        exit('Evento finalizado: el sorteo está en modo consulta.');
                    }

                    // bootstrap debe ser realmente de consulta: el API crea el sorteo
                    // si no existe, por lo que en un evento FINALIZADO solo se permite
                    // consultar un sorteo que ya existía antes de finalizar el evento.
                    $stmtSorteo = $dbGuard->prepare("SELECT id FROM sorteos WHERE evento_id = :evento_id LIMIT 1");
                    $stmtSorteo->execute([':evento_id' => $eventoId]);
                    if (!$stmtSorteo->fetchColumn()) {
                        http_response_code(403);
                        exit('Evento finalizado: no se puede crear un sorteo nuevo.');
                    }
                }

                if (in_array($rutaRelativa, ['sorteo_crear.php', 'sorteo_premio.php', 'sorteo_resultado.php'], true)) {
                    http_response_code(403);
                    exit('Evento finalizado: no se pueden modificar premios ni sorteos.');
                }

                // En un evento FINALIZADO no se muestra el acceso a registrar asistencia.
                // Ese enlace lleva al módulo que trabaja con el evento ACTIVO.
                if ($rutaRelativa === 'asistencia.php') {
                    ob_start(static function (string $html): string {
                        $patron = '~<a\b[^>]*href=["\'][^"\']*\.\./operador/registro\.php(?:\?[^"\']*)?["\'][^>]*>.*?</a>~is';
                        return (string)preg_replace($patron, '', $html);
                    });
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
