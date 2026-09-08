<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
exigirAdmin();

// Evita que el navegador conserve una IP/QR anterior si el DHCP cambió la dirección.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function ejecutarIpconfig(): string
{
    return shell_exec('ipconfig /all 2>&1') ?? '';
}

function detectarInterfaces(string $salida): array
{
    $interfaces = [];
    $bloques = preg_split('/(?=^\s*(?:Adaptador|Adapter)\s+)/mi', $salida);

    foreach ($bloques as $bloque) {
        if (trim($bloque) === '') {
            continue;
        }

        $nombre = '';
        if (preg_match('/^\s*(?:Adaptador|Adapter)\s+(.*?):\s*$/mi', $bloque, $m)) {
            $nombre = trim($m[1]);
        }

        $ip = null;
        if (preg_match('/IPv4[^:]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i', $bloque, $m)) {
            $ip = $m[1];
        }

        $esWifi = (bool) preg_match(
            '/wi[\s-]?fi|wifi|inal[aá]mbrica|wireless/i',
            $nombre . ' ' . $bloque
        );

        $esIpValida = $ip !== null
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && !str_starts_with($ip, '169.254.');

        $interfaces[] = [
            'nombre' => $nombre,
            'ip' => $ip,
            'wifi' => $esWifi,
            'activo' => $esIpValida,
        ];
    }

    return $interfaces;
}

function qrGfMul(int $x, int $y): int
{
    $resultado = 0;

    while ($y > 0) {
        if (($y & 1) !== 0) {
            $resultado ^= $x;
        }

        $y >>= 1;
        $x <<= 1;

        if (($x & 0x100) !== 0) {
            $x ^= 0x11D;
        }
    }

    return $resultado & 0xFF;
}

function qrBchTypeInfo(int $data): int
{
    $generator = 0x537;
    $mask = 0x5412;
    $value = $data << 10;

    while (($value === 0 ? 0 : strlen(decbin($value))) - strlen(decbin($generator)) >= 0) {
        $shift = strlen(decbin($value)) - strlen(decbin($generator));
        $value ^= $generator << $shift;
    }

    return (($data << 10) | $value) ^ $mask;
}

function qrReedSolomon(array $data, int $ecCount = 10): array
{
    // Polinomio generador para QR versión 2, nivel de corrección L (10 bytes EC).
    $generator = [1, 216, 194, 159, 111, 199, 94, 95, 113, 157, 193];
    $mensaje = array_merge($data, array_fill(0, $ecCount, 0));

    for ($i = 0, $dataCount = count($data); $i < $dataCount; $i++) {
        $coeficiente = $mensaje[$i];

        if ($coeficiente === 0) {
            continue;
        }

        for ($j = 0; $j < count($generator); $j++) {
            $mensaje[$i + $j] ^= qrGfMul($generator[$j], $coeficiente);
        }
    }

    return array_slice($mensaje, -$ecCount);
}

function qrPonerBits(array &$bits, int $valor, int $cantidad): void
{
    for ($i = $cantidad - 1; $i >= 0; $i--) {
        $bits[] = (($valor >> $i) & 1);
    }
}

function qrCrearMatriz(string $texto): array
{
    // La URL de esta página cabe en QR versión 2-L: 34 bytes de datos.
    $datos = array_values(unpack('C*', $texto));

    if (count($datos) > 34) {
        throw new RuntimeException('La URL es demasiado larga para el QR local.');
    }

    $bits = [];
    qrPonerBits($bits, 0b0100, 4);       // Modo Byte.
    qrPonerBits($bits, count($datos), 8); // Longitud para versión 2.

    foreach ($datos as $byte) {
        qrPonerBits($bits, $byte, 8);
    }

    // Terminador y relleno hasta completar 34 bytes de datos.
    $capacidadBits = 34 * 8;
    $faltan = $capacidadBits - count($bits);
    if ($faltan > 0) {
        qrPonerBits($bits, 0, min(4, $faltan));
    }

    while ((count($bits) % 8) !== 0) {
        $bits[] = 0;
    }

    $codewords = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $valor = 0;
        for ($j = 0; $j < 8; $j++) {
            $valor = ($valor << 1) | $bits[$i + $j];
        }
        $codewords[] = $valor;
    }

    $rellenos = [0xEC, 0x11];
    $indiceRelleno = 0;
    while (count($codewords) < 34) {
        $codewords[] = $rellenos[$indiceRelleno];
        $indiceRelleno ^= 1;
    }

    $codewords = array_merge($codewords, qrReedSolomon($codewords, 10));

    $bits = [];
    foreach ($codewords as $byte) {
        qrPonerBits($bits, $byte, 8);
    }

    $n = 25; // QR versión 2 = 25 x 25 módulos.
    $matriz = array_fill(0, $n, array_fill(0, $n, null));

    $ponerFinder = static function (array &$m, int $fila, int $columna): void {
        $n = count($m);

        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $r = $fila + $dr;
                $c = $columna + $dc;

                if ($r < 0 || $r >= $n || $c < 0 || $c >= $n) {
                    continue;
                }

                $m[$r][$c] = (
                    ($dr >= 0 && $dr <= 6 && ($dc === 0 || $dc === 6))
                    || ($dc >= 0 && $dc <= 6 && ($dr === 0 || $dr === 6))
                    || ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4)
                );
            }
        }
    };

    $ponerFinder($matriz, 0, 0);
    $ponerFinder($matriz, $n - 7, 0);
    $ponerFinder($matriz, 0, $n - 7);

    // Patrón de alineación único de la versión 2: centro (18,18).
    for ($dr = -2; $dr <= 2; $dr++) {
        for ($dc = -2; $dc <= 2; $dc++) {
            if ($matriz[18 + $dr][18 + $dc] === null) {
                $matriz[18 + $dr][18 + $dc] = max(abs($dr), abs($dc)) !== 1;
            }
        }
    }

    // Patrones de sincronización.
    for ($i = 8; $i < $n - 8; $i++) {
        if ($matriz[$i][6] === null) {
            $matriz[$i][6] = ($i % 2 === 0);
        }
        if ($matriz[6][$i] === null) {
            $matriz[6][$i] = ($i % 2 === 0);
        }
    }

    // Nivel L + máscara 0.
    $formato = qrBchTypeInfo((1 << 3) | 0);

    for ($i = 0; $i < 15; $i++) {
        $bit = (($formato >> $i) & 1) === 1;

        if ($i < 6) {
            $fila = $i;
            $columna = 8;
        } elseif ($i < 8) {
            $fila = $i + 1;
            $columna = 8;
        } else {
            $fila = $n - 15 + $i;
            $columna = 8;
        }

        $matriz[$fila][$columna] = $bit;
    }

    for ($i = 0; $i < 15; $i++) {
        $bit = (($formato >> $i) & 1) === 1;

        if ($i < 8) {
            $fila = 8;
            $columna = $n - $i - 1;
        } elseif ($i < 9) {
            $fila = 8;
            $columna = 15 - $i;
        } else {
            $fila = 8;
            $columna = 15 - $i - 1;
        }

        $matriz[$fila][$columna] = $bit;
    }

    $matriz[$n - 8][8] = true;

    // Colocación de los bits de datos en zigzag. Máscara 0: (fila + columna) par.
    $fila = $n - 1;
    $direccion = -1;
    $indiceBit = 0;

    for ($columna = $n - 1; $columna > 0; $columna -= 2) {
        if ($columna === 6) {
            $columna--;
        }

        while (true) {
            for ($dc = 0; $dc < 2; $dc++) {
                $c = $columna - $dc;

                if ($matriz[$fila][$c] === null) {
                    $bit = $indiceBit < count($bits) ? $bits[$indiceBit] : 0;
                    $enmascarado = (($fila + $c) % 2 === 0);
                    $matriz[$fila][$c] = (($bit ^ ($enmascarado ? 1 : 0)) === 1);
                    $indiceBit++;
                }
            }

            $fila += $direccion;

            if ($fila < 0 || $fila >= $n) {
                $fila -= $direccion;
                $direccion = -$direccion;
                break;
            }
        }
    }

    return $matriz;
}

function qrSvg(string $texto, int $escala = 12, int $margen = 4): string
{
    $matriz = qrCrearMatriz($texto);
    $n = count($matriz);
    $total = $n + ($margen * 2);
    $rectangulos = [];

    for ($fila = 0; $fila < $n; $fila++) {
        for ($columna = 0; $columna < $n; $columna++) {
            if ($matriz[$fila][$columna] === true) {
                $x = $columna + $margen;
                $y = $fila + $margen;
                $rectangulos[] = '<rect x="' . $x . '" y="' . $y . '" width="1" height="1"/>';
            }
        }
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $total . '" width="' . ($total * $escala) . '" height="' . ($total * $escala) . '" shape-rendering="crispEdges" role="img" aria-label="Código QR">';
    $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';
    $svg .= '<g fill="#000000">' . implode('', $rectangulos) . '</g>';
    $svg .= '</svg>';

    return $svg;
}

$wifi = null;

foreach (detectarInterfaces(ejecutarIpconfig()) as $interfaz) {
    if ($interfaz['wifi'] && $interfaz['activo']) {
        $wifi = $interfaz;
        break;
    }
}

$ipWifi = $wifi['ip'] ?? null;
$urlMovil = $ipWifi ? 'https://' . $ipWifi . '/movil' : '';
$qrSvg = '';
$qrError = '';

if ($urlMovil !== '') {
    try {
        $qrSvg = qrSvg($urlMovil);
    } catch (Throwable $e) {
        $qrError = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Red - Songa Eventos</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Arial,sans-serif;background:#f4f6f8;color:#1f2937;padding:30px}
        .contenedor{max-width:1050px;margin:auto}
        .volver{display:inline-block;margin-bottom:18px;color:#2563eb;text-decoration:none;font-weight:bold}
        .card{background:#fff;border-radius:14px;padding:28px;box-shadow:0 3px 15px rgba(0,0,0,.08)}
        h1{margin:0 0 22px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:35px;align-items:center}
        .ok{color:#198754;font-size:20px;font-weight:bold}
        .dato{margin:16px 0;font-size:17px}
        .ip{font-size:36px;font-weight:bold;color:#1769d1;margin:8px 0 24px}
        .url{background:#eef5ff;padding:16px;border-radius:9px;font-size:19px;word-break:break-all;color:#1769d1;font-weight:bold}
        .qr{text-align:center}
        .qrbox{display:inline-flex;background:#fff;padding:20px;border:1px solid #dbe3ec;border-radius:12px;min-width:340px;min-height:340px;align-items:center;justify-content:center}
        .qrbox svg{width:300px;height:300px;display:block}
        .aviso{color:#856404;background:#fff3cd;border:1px solid #ffecb5;border-radius:8px;padding:12px;max-width:420px}
        .error{color:#dc3545;font-weight:bold;font-size:18px}
        .nota{font-size:13px;color:#6b7280;margin-top:12px}
        .actualizar{display:inline-block;margin-top:15px;padding:10px 15px;background:#2563eb;color:#fff;text-decoration:none;border-radius:7px;font-weight:bold}
        @media(max-width:750px){body{padding:15px}.grid{grid-template-columns:1fr}.qrbox{min-width:280px;min-height:280px}.qrbox svg{width:250px;height:250px}}
    </style>
</head>
<body>
<div class="contenedor">
    <a href="eventos.php" class="volver">← Volver al evento</a>

    <div class="card">
        <h1>🌐 Red Songa Eventos</h1>

        <?php if ($wifi): ?>
            <div class="grid">
                <div>
                    <p class="ok">✓ Interfaz Wi-Fi detectada</p>

                    <div class="dato">
                        <strong>Adaptador:</strong>
                        <?= htmlspecialchars($wifi['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="dato"><strong>IP asignada por DHCP:</strong></div>
                    <div class="ip"><?= htmlspecialchars($ipWifi, ENT_QUOTES, 'UTF-8') ?></div>

                    <h3>📱 Acceso desde PDA / celular</h3>
                    <div class="url"><?= htmlspecialchars($urlMovil, ENT_QUOTES, 'UTF-8') ?></div>

                    <p>Conecta el PDA o celular a la misma red Wi-Fi y escanea el código QR.</p>
                    <p class="nota">El QR se genera localmente en PHP usando la IP Wi-Fi detectada en este momento. No utiliza Internet ni ningún servicio externo.</p>
                    <a class="actualizar" href="red.php?actualizar=<?= time() ?>">↻ Volver a detectar IP / actualizar QR</a>
                </div>

                <div class="qr">
                    <h2>📷 Escanea para acceder</h2>
                    <div class="qrbox">
                        <?php if ($qrSvg !== ''): ?>
                            <?= $qrSvg ?>
                        <?php elseif ($qrError !== ''): ?>
                            <div class="aviso">No se pudo generar el QR local: <?= htmlspecialchars($qrError, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php else: ?>
                            <div class="aviso">No se pudo generar el QR porque no hay una IP Wi-Fi válida detectada.</div>
                        <?php endif; ?>
                    </div>
                    <p>El código contiene exactamente:</p>
                    <strong><?= htmlspecialchars($urlMovil, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            </div>
        <?php else: ?>
            <p class="error">✗ No se encontró una interfaz Wi-Fi activa.</p>
            <p>Verifica que el equipo esté conectado al Wi-Fi.</p>
            <a class="actualizar" href="red.php?actualizar=<?= time() ?>">↻ Volver a detectar</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
