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

    if ($uri === '/operador/registro.php') {
        ob_start(static function (string $html): string {
            $boton = <<<'HTML'
<a href="/logout.php" style="position:fixed;top:16px;right:16px;z-index:9999;background:#dc2626;color:#fff;text-decoration:none;padding:11px 18px;border-radius:9px;font-weight:bold;font-family:Arial,Helvetica,sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.20);">Cerrar sesión</a>
HTML;

            if (stripos($html, '</body>') !== false) {
                return str_ireplace('</body>', $boton . "\n</body>", $html);
            }

            return $html . $boton;
        });
    }

    return;
}
