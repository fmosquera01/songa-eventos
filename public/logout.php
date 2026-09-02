<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

cerrarSesion();

header('Location: /login.php');
exit;
