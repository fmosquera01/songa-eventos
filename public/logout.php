<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

$destino = (($_GET['redirect'] ?? '') === 'movil') ? '/movil/' : '/login.php';

cerrarSesion();

header('Location: ' . $destino);
exit;
