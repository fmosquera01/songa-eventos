<?php

/**
 * Detectar interfaces de red activas en Windows
 * y encontrar específicamente la interfaz Wi-Fi.
 */

function ejecutarIpconfig(): string
{
    return shell_exec('ipconfig /all 2>&1') ?? '';
}

function detectarInterfaces(string $salida): array
{
    $interfaces = [];

    // Divide ipconfig en bloques por cada adaptador
    $bloques = preg_split(
        '/(?=^\s*(?:Adaptador|Adapter)\s+)/mi',
        $salida
    );

    foreach ($bloques as $bloque) {

        if (trim($bloque) === '') {
            continue;
        }

        $nombre = '';

        // Windows en español:
        // "Adaptador de LAN inalámbrica Wi-Fi:"
        //
        // Windows en inglés:
        // "Wireless LAN adapter Wi-Fi:"
        if (preg_match(
            '/^\s*(?:Adaptador|Adapter)\s+(.*?):\s*$/mi',
            $bloque,
            $m
        )) {
            $nombre = trim($m[1]);
        }

        // Buscar IPv4
        $ip = null;

        if (preg_match(
            '/IPv4[^:]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i',
            $bloque,
            $m
        )) {
            $ip = $m[1];
        }

        // Buscar máscara
        $mascara = null;

        if (preg_match(
            '/(?:Máscara de subred|Subnet Mask)[^:]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i',
            $bloque,
            $m
        )) {
            $mascara = $m[1];
        }

        // Buscar gateway
        $gateway = null;

        if (preg_match(
            '/(?:Puerta de enlace predeterminada|Default Gateway)[^:]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i',
            $bloque,
            $m
        )) {
            $gateway = $m[1];
        }

        // Detectar Wi-Fi por nombre del adaptador
        $esWifi = preg_match(
            '/wi[\s-]?fi|wifi|inal[aá]mbrica|wireless/i',
            $nombre . ' ' . $bloque
        );

        $interfaces[] = [
            'nombre'   => $nombre,
            'ip'       => $ip,
            'mascara'  => $mascara,
            'gateway'  => $gateway,
            'wifi'     => (bool)$esWifi,
            'activo'   => $ip !== null
        ];
    }

    return $interfaces;
}

$salida = ejecutarIpconfig();
$interfaces = detectarInterfaces($salida);

// Buscar Wi-Fi activa
$wifi = null;

foreach ($interfaces as $interfaz) {

    if (
        $interfaz['wifi'] &&
        $interfaz['activo'] &&
        filter_var($interfaz['ip'], FILTER_VALIDATE_IP)
    ) {
        $wifi = $interfaz;
        break;
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Red - Songa Eventos</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 30px;
        }

        .contenedor {
            max-width: 800px;
            margin: auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,.08);
        }

        h1 {
            margin-top: 0;
        }

        .ok {
            color: #198754;
            font-weight: bold;
        }

        .error {
            color: #dc3545;
            font-weight: bold;
        }

        .dato {
            margin: 10px 0;
        }

        .ip {
            font-size: 28px;
            font-weight: bold;
            margin: 15px 0;
        }

        .url {
            background: #f1f3f5;
            padding: 15px;
            border-radius: 8px;
            font-size: 20px;
            word-break: break-all;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
    </style>
</head>

<body>

<div class="contenedor">

    <div class="card">

        <h1>🌐 Red Songa Eventos</h1>

        <?php if ($wifi): ?>

            <p class="ok">
                ✓ Interfaz Wi-Fi detectada
            </p>

            <div class="dato">
                <strong>Adaptador:</strong>
                <?= htmlspecialchars($wifi['nombre']) ?>
            </div>

            <div class="dato">
                <strong>IP:</strong>
            </div>

            <div class="ip">
                <?= htmlspecialchars($wifi['ip']) ?>
            </div>

            

            <hr>

            <h3>📱 Acceso desde PDA / celular</h3>

            <div class="url">
                https://<?= htmlspecialchars($wifi['ip']) ?>/movil
            </div>

            <p>
                Conecta el PDA o celular a la misma red Wi-Fi
                del TCL y utiliza esta dirección.
            </p>

        <?php else: ?>

            <p class="error">
                ✗ No se encontró una interfaz Wi-Fi activa.
            </p>

            <p>
                Verifica que el equipo esté conectado al Wi-Fi
                del TCL LINKZONE.
            </p>

        <?php endif; ?>

    </div>






    </div>

</div>

</body>
</html>