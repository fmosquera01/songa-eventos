<?php
// Endpoint reservado para el generador QR local.
// Se completará con la librería QR antes de producción.
http_response_code(503);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Generador QR no disponible.';
