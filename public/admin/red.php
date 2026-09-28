<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
exigirAdmin();

/*
|--------------------------------------------------------------------------
| CACHE
|--------------------------------------------------------------------------
*/

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');


/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN WI-FI
|--------------------------------------------------------------------------
|
| CAMBIA ESTOS VALORES POR LOS DE TU RED.
|
| WIFI_SECURITY:
|   WPA    = WPA/WPA2/WPA3
|   WEP    = WEP
|   nopass = red abierta
|
*/

const WIFI_SSID      = 'EVENTOS';
const WIFI_PASSWORD  = 'CLARO1D7F';
const WIFI_SECURITY  = 'WPA';


/*
|--------------------------------------------------------------------------
| URLs LOCALES
|--------------------------------------------------------------------------
*/

const URL_CA = 'https://192.168.1.2/songa-eventos-ca.crt';

const URL_APK = 'https://192.168.1.2/Songa-Asistencia.apk';

const URL_MOVIL = 'https://songa-eventos.local/movil';


/*
|--------------------------------------------------------------------------
| QR CODE
|--------------------------------------------------------------------------
|
| Generador QR completamente local.
|
| No utiliza:
| - Composer
| - Internet
| - CDN
| - librerías externas
| - API externa
|
| Se utiliza QR versión 5 - nivel L.
|
| Versión 5:
| 37 x 37 módulos
| Capacidad L: 108 bytes de datos
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Multiplicación GF(256)
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Potencia GF(256)
|--------------------------------------------------------------------------
*/

function qrGfPow(int $x, int $power): int
{
    $resultado = 1;

    while ($power > 0) {

        if (($power & 1) !== 0) {
            $resultado = qrGfMul($resultado, $x);
        }

        $x = qrGfMul($x, $x);
        $power >>= 1;
    }

    return $resultado;
}


/*
|--------------------------------------------------------------------------
| Generador Reed-Solomon
|--------------------------------------------------------------------------
|
| Se genera dinámicamente para evitar depender de una librería.
|
*/

function qrGenerarPolinomio(int $ecCount): array
{
    $polinomio = [1];

    for ($i = 0; $i < $ecCount; $i++) {

        $nuevo = array_fill(
            0,
            count($polinomio) + 1,
            0
        );

        $raiz = qrGfPow(
            2,
            $i
        );

        foreach ($polinomio as $j => $coeficiente) {

            $nuevo[$j] ^= $coeficiente;

            $nuevo[$j + 1] ^= qrGfMul(
                $coeficiente,
                $raiz
            );
        }

        $polinomio = $nuevo;
    }

    return $polinomio;
}


/*
|--------------------------------------------------------------------------
| Reed-Solomon
|--------------------------------------------------------------------------
*/

function qrReedSolomon(
    array $data,
    int $ecCount
): array {

    $generator = qrGenerarPolinomio(
        $ecCount
    );

    $mensaje = array_merge(
        $data,
        array_fill(
            0,
            $ecCount,
            0
        )
    );

    $dataCount = count($data);

    for (
        $i = 0;
        $i < $dataCount;
        $i++
    ) {

        $coeficiente = $mensaje[$i];

        if ($coeficiente === 0) {
            continue;
        }

        for (
            $j = 0;
            $j < count($generator);
            $j++
        ) {

            $mensaje[$i + $j] ^=
                qrGfMul(
                    $generator[$j],
                    $coeficiente
                );
        }
    }

    return array_slice(
        $mensaje,
        -$ecCount
    );
}


/*
|--------------------------------------------------------------------------
| Agregar bits
|--------------------------------------------------------------------------
*/

function qrPonerBits(
    array &$bits,
    int $valor,
    int $cantidad
): void {

    for (
        $i = $cantidad - 1;
        $i >= 0;
        $i--
    ) {

        $bits[] =
            (($valor >> $i) & 1);
    }
}


/*
|--------------------------------------------------------------------------
| BCH para información de formato
|--------------------------------------------------------------------------
*/

function qrBchTypeInfo(int $data): int
{
    $generator = 0x537;
    $mask = 0x5412;

    $value = $data << 10;

    while ($value !== 0) {

        $gradoValor =
            strlen(decbin($value)) - 1;

        $gradoGenerator =
            strlen(decbin($generator)) - 1;

        if ($gradoValor < $gradoGenerator) {
            break;
        }

        $value ^=
            $generator <<
            ($gradoValor - $gradoGenerator);
    }

    return (
        (($data << 10) | $value)
        ^ $mask
    );
}


/*
|--------------------------------------------------------------------------
| Escape para QR Wi-Fi
|--------------------------------------------------------------------------
*/

function qrWifiEscape(string $texto): string
{
    return str_replace(
        [
            '\\',
            ';',
            ',',
            ':',
            '"'
        ],
        [
            '\\\\',
            '\;',
            '\,',
            '\:',
            '\"'
        ],
        $texto
    );
}


/*
|--------------------------------------------------------------------------
| Crear matriz QR versión 5-L
|--------------------------------------------------------------------------
*/

function qrCrearMatriz(string $texto): array
{
    /*
     * QR versión 5-L
     *
     * 37 x 37 módulos
     * 108 bytes de datos
     * 26 bytes de corrección
     */

    $version = 5;

    $n = 37;

    $dataCapacity = 108;

    $ecCount = 26;

    $datos = array_values(
        unpack(
            'C*',
            $texto
        )
    );

    if (count($datos) > $dataCapacity) {

        throw new RuntimeException(
            'El contenido es demasiado largo para el QR.'
        );
    }


    /*
     * Construcción de bits.
     */

    $bits = [];

    /*
     * Modo Byte.
     */

    qrPonerBits(
        $bits,
        0b0100,
        4
    );

    /*
     * Longitud.
     *
     * Para versión 5 en modo Byte:
     * 8 bits.
     */

    qrPonerBits(
        $bits,
        count($datos),
        8
    );


    /*
     * Datos.
     */

    foreach ($datos as $byte) {

        qrPonerBits(
            $bits,
            $byte,
            8
        );
    }


    /*
     * Capacidad:
     * 108 bytes = 864 bits.
     */

    $capacidadBits =
        $dataCapacity * 8;


    /*
     * Terminador.
     */

    $faltan =
        $capacidadBits -
        count($bits);

    if ($faltan > 0) {

        qrPonerBits(
            $bits,
            0,
            min(
                4,
                $faltan
            )
        );
    }


    /*
     * Alinear a byte.
     */

    while (
        count($bits) % 8 !== 0
    ) {

        $bits[] = 0;
    }


    /*
     * Codewords.
     */

    $codewords = [];

    for (
        $i = 0;
        $i < count($bits);
        $i += 8
    ) {

        $valor = 0;

        for (
            $j = 0;
            $j < 8;
            $j++
        ) {

            $valor =
                ($valor << 1)
                | $bits[$i + $j];
        }

        $codewords[] = $valor;
    }


    /*
     * Relleno estándar QR.
     */

    $rellenos = [
        0xEC,
        0x11
    ];

    $indiceRelleno = 0;

    while (
        count($codewords)
        < $dataCapacity
    ) {

        $codewords[] =
            $rellenos[$indiceRelleno];

        $indiceRelleno ^= 1;
    }


    /*
     * Corrección de errores.
     */

    $ec = qrReedSolomon(
        $codewords,
        $ecCount
    );

    $codewords = array_merge(
        $codewords,
        $ec
    );


    /*
     * Convertir nuevamente a bits.
     */

    $bits = [];

    foreach (
        $codewords as $byte
    ) {

        qrPonerBits(
            $bits,
            $byte,
            8
        );
    }


    /*
     * Matriz.
     */

    $matriz = array_fill(
        0,
        $n,
        array_fill(
            0,
            $n,
            null
        )
    );


    /*
     |--------------------------------------------------------------------------
     | Finder patterns
     |--------------------------------------------------------------------------
     */

    $ponerFinder =
        static function (
            array &$m,
            int $fila,
            int $columna
        ): void {

            $n = count($m);

            for (
                $dr = -1;
                $dr <= 7;
                $dr++
            ) {

                for (
                    $dc = -1;
                    $dc <= 7;
                    $dc++
                ) {

                    $r =
                        $fila + $dr;

                    $c =
                        $columna + $dc;

                    if (
                        $r < 0 ||
                        $r >= $n ||
                        $c < 0 ||
                        $c >= $n
                    ) {
                        continue;
                    }

                    $m[$r][$c] = (

                        (
                            $dr >= 0 &&
                            $dr <= 6 &&
                            (
                                $dc === 0 ||
                                $dc === 6
                            )
                        )

                        ||

                        (
                            $dc >= 0 &&
                            $dc <= 6 &&
                            (
                                $dr === 0 ||
                                $dr === 6
                            )
                        )

                        ||

                        (
                            $dr >= 2 &&
                            $dr <= 4 &&
                            $dc >= 2 &&
                            $dc <= 4
                        )
                    );
                }
            }
        };


    /*
     * Esquinas.
     */

    $ponerFinder(
        $matriz,
        0,
        0
    );

    $ponerFinder(
        $matriz,
        $n - 7,
        0
    );

    $ponerFinder(
        $matriz,
        0,
        $n - 7
    );


    /*
     |--------------------------------------------------------------------------
     | Alignment pattern
     |--------------------------------------------------------------------------
     |
     | Versión 5:
     | posiciones 6 y 30.
     |
     | Solo se coloca en 30,30 porque los otros
     | puntos de combinación están ocupados por
     | los finder patterns.
     |
     */

    $centros = [
        6,
        30
    ];

    foreach (
        $centros as $filaCentro
    ) {

        foreach (
            $centros as $columnaCentro
        ) {

            /*
             * No colocar sobre los finder.
             */

            if (
                (
                    $filaCentro === 6 &&
                    $columnaCentro === 6
                )
                ||
                (
                    $filaCentro === 6 &&
                    $columnaCentro === 30
                )
                ||
                (
                    $filaCentro === 30 &&
                    $columnaCentro === 6
                )
            ) {
                continue;
            }

            for (
                $dr = -2;
                $dr <= 2;
                $dr++
            ) {

                for (
                    $dc = -2;
                    $dc <= 2;
                    $dc++
                ) {

                    $r =
                        $filaCentro +
                        $dr;

                    $c =
                        $columnaCentro +
                        $dc;

                    $matriz[$r][$c] =
                        max(
                            abs($dr),
                            abs($dc)
                        ) !== 1;
                }
            }
        }
    }


    /*
     |--------------------------------------------------------------------------
     | Timing patterns
     |--------------------------------------------------------------------------
     */

    for (
        $i = 8;
        $i < $n - 8;
        $i++
    ) {

        if (
            $matriz[$i][6] === null
        ) {

            $matriz[$i][6] =
                ($i % 2 === 0);
        }

        if (
            $matriz[6][$i] === null
        ) {

            $matriz[6][$i] =
                ($i % 2 === 0);
        }
    }


    /*
     |--------------------------------------------------------------------------
     | Format information
     |--------------------------------------------------------------------------
     |
     | Nivel L = 01
     | Máscara 0 = 000
     |
     | data = 001000
     |
     */

    $formato = qrBchTypeInfo(
        (1 << 3) | 0
    );


    /*
     * Primer copia del formato.
     */

    for (
        $i = 0;
        $i < 15;
        $i++
    ) {

        $bit =
            (($formato >> $i) & 1) === 1;

        if ($i < 6) {

            $fila = $i;
            $columna = 8;

        } elseif ($i < 8) {

            $fila = $i + 1;
            $columna = 8;

        } else {

            $fila =
                $n - 15 + $i;

            $columna = 8;
        }

        $matriz[$fila][$columna] =
            $bit;
    }


    /*
     * Segunda copia del formato.
     */

    for (
        $i = 0;
        $i < 15;
        $i++
    ) {

        $bit =
            (($formato >> $i) & 1) === 1;

        if ($i < 8) {

            $fila = 8;
            $columna =
                $n - $i - 1;

        } elseif ($i < 9) {

            $fila = 8;
            $columna =
                15 - $i;

        } else {

            $fila = 8;
            $columna =
                15 - $i - 1;
        }

        $matriz[$fila][$columna] =
            $bit;
    }


    /*
     * Dark module obligatorio.
     */

    $matriz[$n - 8][8] = true;


    /*
     |--------------------------------------------------------------------------
     | Colocación de datos
     |--------------------------------------------------------------------------
     */

    $fila = $n - 1;

    $direccion = -1;

    $indiceBit = 0;


    /*
     * 0 = máscara 0.
     *
     * mask = (row + column) % 2 == 0
     */

    for (
        $columna = $n - 1;
        $columna > 0;
        $columna -= 2
    ) {

        /*
         * Saltar columna 6.
         */

        if ($columna === 6) {
            $columna--;
        }


        while (true) {

            for (
                $dc = 0;
                $dc < 2;
                $dc++
            ) {

                $c =
                    $columna - $dc;

                if (
                    $matriz[$fila][$c] === null
                ) {

                    $bit =
                        $indiceBit <
                        count($bits)
                            ? $bits[$indiceBit]
                            : 0;

                    $enmascarado =
                        (
                            (
                                $fila +
                                $c
                            ) % 2 === 0
                        );

                    if ($enmascarado) {
                        $bit ^= 1;
                    }

                    $matriz[$fila][$c] =
                        ($bit === 1);

                    $indiceBit++;
                }
            }


            $fila += $direccion;


            if (
                $fila < 0 ||
                $fila >= $n
            ) {

                $fila -= $direccion;

                $direccion =
                    -$direccion;

                break;
            }
        }
    }


    return $matriz;
}


/*
|--------------------------------------------------------------------------
| Convertir QR a SVG
|--------------------------------------------------------------------------
*/

function qrSvg(
    string $texto,
    int $escala = 8,
    int $margen = 4
): string {

    $matriz =
        qrCrearMatriz($texto);

    $n =
        count($matriz);

    $total =
        $n +
        ($margen * 2);

    $rectangulos = [];


    for (
        $fila = 0;
        $fila < $n;
        $fila++
    ) {

        for (
            $columna = 0;
            $columna < $n;
            $columna++
        ) {

            if (
                $matriz[$fila][$columna]
                === true
            ) {

                $x =
                    $columna +
                    $margen;

                $y =
                    $fila +
                    $margen;

                $rectangulos[] =
                    '<rect x="' .
                    $x .
                    '" y="' .
                    $y .
                    '" width="1" height="1"/>';
            }
        }
    }


    $svg =
        '<svg ' .
        'xmlns="http://www.w3.org/2000/svg" ' .
        'viewBox="0 0 ' .
        $total .
        ' ' .
        $total .
        '" ' .
        'width="' .
        ($total * $escala) .
        '" ' .
        'height="' .
        ($total * $escala) .
        '" ' .
        'shape-rendering="crispEdges" ' .
        'role="img" ' .
        'aria-label="Código QR">';


    $svg .=
        '<rect ' .
        'width="100%" ' .
        'height="100%" ' .
        'fill="#ffffff"/>';


    $svg .=
        '<g fill="#000000">' .
        implode(
            '',
            $rectangulos
        ) .
        '</g>';


    $svg .=
        '</svg>';


    return $svg;
}


/*
|--------------------------------------------------------------------------
| PREPARAR QR WI-FI
|--------------------------------------------------------------------------
*/

$wifiSsid =
    qrWifiEscape(
        WIFI_SSID
    );

$wifiPassword =
    qrWifiEscape(
        WIFI_PASSWORD
    );


$wifiQr =
    'WIFI:T:' .
    WIFI_SECURITY .
    ';S:' .
    $wifiSsid .
    ';P:' .
    $wifiPassword .
    ';;';


/*
|--------------------------------------------------------------------------
| Generar los cuatro QR
|--------------------------------------------------------------------------
*/

$qrWifi = '';
$qrCa = '';
$qrApk = '';
$qrMovil = '';

$erroresQr = [];


/*
 * QR 1 - Wi-Fi
 */

try {

    $qrWifi =
        qrSvg(
            $wifiQr
        );

} catch (Throwable $e) {

    $erroresQr[] =
        'Wi-Fi: ' .
        $e->getMessage();
}


/*
 * QR 2 - CA
 */

try {

    $qrCa =
        qrSvg(
            URL_CA
        );

} catch (Throwable $e) {

    $erroresQr[] =
        'CA: ' .
        $e->getMessage();
}


/*
 * QR 3 - APK
 */

try {

    $qrApk =
        qrSvg(
            URL_APK
        );

} catch (Throwable $e) {

    $erroresQr[] =
        'APK: ' .
        $e->getMessage();
}


/*
 * QR 4 - Sitio móvil
 */

try {

    $qrMovil =
        qrSvg(
            URL_MOVIL
        );

} catch (Throwable $e) {

    $erroresQr[] =
        'Sitio móvil: ' .
        $e->getMessage();
}

?>
<!doctype html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title>Red - Songa Eventos</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
            padding: 30px;
        }


        .contenedor {
            max-width: 1200px;
            margin: auto;
        }


        .volver {
            display: inline-block;
            margin-bottom: 18px;
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }


        .card {
            background: #fff;
            border-radius: 14px;
            padding: 28px;
            box-shadow:
                0 3px 15px
                rgba(0,0,0,.08);
        }


        h1 {
            margin:
                0 0 8px;
        }


        .subtitulo {
            color: #6b7280;
            margin:
                0 0 28px;
        }


        .grid {
            display: grid;
            grid-template-columns:
                1fr 1fr;
            gap: 25px;
        }


        .qr-card {
            border:
                1px solid #dbe3ec;

            border-radius: 14px;

            padding: 25px;

            background: #fff;

            text-align: center;
        }


        .numero {
            display: inline-flex;

            width: 40px;
            height: 40px;

            border-radius: 50%;

            align-items: center;
            justify-content: center;

            background: #2563eb;

            color: #fff;

            font-size: 20px;

            font-weight: bold;

            margin-bottom: 8px;
        }


        .qr-card h2 {
            margin:
                5px 0 8px;

            font-size: 21px;
        }


        .descripcion {
            color: #6b7280;

            min-height: 44px;

            margin-bottom: 15px;

            line-height: 1.4;
        }


        .qrbox {
            display: inline-flex;

            background: #fff;

            padding: 18px;

            border:
                1px solid #dbe3ec;

            border-radius: 12px;

            align-items: center;
            justify-content: center;
        }


        .qrbox svg {
            width: 270px;
            height: 270px;
            display: block;
        }


        .dato {
            margin-top: 15px;

            padding: 12px;

            background: #f4f6f8;

            border-radius: 8px;

            font-size: 14px;

            word-break: break-all;

            line-height: 1.5;
        }


        .url {
            color: #1769d1;
            font-weight: bold;
        }


        .wifi-dato {
            margin:
                4px 0;
        }


        .aviso {
            color: #856404;

            background: #fff3cd;

            border:
                1px solid #ffecb5;

            border-radius: 8px;

            padding: 12px;

            max-width: 300px;
        }


        .errores {
            margin-top: 25px;

            padding: 15px;

            background: #f8d7da;

            border:
                1px solid #f5c2c7;

            color: #842029;

            border-radius: 8px;
        }


        .errores ul {
            margin-bottom: 0;
        }


        .nota {
            margin-top: 25px;

            font-size: 13px;

            color: #6b7280;

            line-height: 1.5;
        }


        @media (max-width: 850px) {

            body {
                padding: 15px;
            }


            .grid {
                grid-template-columns: 1fr;
            }


            .qrbox svg {
                width: 250px;
                height: 250px;
            }

        }

    </style>

</head>


<body>

<div class="contenedor">


    <a
        href="eventos.php"
        class="volver"
    >
        ← Volver al evento
    </a>


    <div class="card">


        <h1>
            🌐 Red Songa Eventos
        </h1>


        <p class="subtitulo">
            Configuración y accesos rápidos para PDA y celulares.
        </p>


        <div class="grid">


            <!-- ==================================================
                 QR 1 - WIFI
                 ================================================== -->

            <div class="qr-card">

                <div class="numero">
                    1
                </div>


                <h2>
                    📶 Conectarse al Wi-Fi
                </h2>


                <p class="descripcion">
                    Escanea este código para configurar
                    automáticamente la red Wi-Fi. Debe cambiar el DNS  192.168.1                                                                                                                                                             .2 y 8.8.8.8
                </p>


                <div class="qrbox">

                    <?php if ($qrWifi !== ''): ?>

                        <?= $qrWifi ?>

                    <?php else: ?>

                        <div class="aviso">
                            No se pudo generar el QR Wi-Fi.
                        </div>

                    <?php endif; ?>

                </div>


                <div class="dato">

                    <div class="wifi-dato">

                        <strong>
                            Red:
                        </strong>

                        <?= htmlspecialchars(
                            WIFI_SSID,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>


                    <div class="wifi-dato">

                        <strong>
                            Seguridad:
                        </strong>

                        <?= htmlspecialchars(
                            WIFI_SECURITY,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- ==================================================
                 QR 2 - CA
                 ================================================== -->

            <div class="qr-card">

                <div class="numero">
                    2
                </div>


                <h2>
                    🔐 Descargar certificado CA
                </h2>


                <p class="descripcion">
                    Escanea para descargar el certificado
                    CA del servidor Songa Eventos.
                </p>


                <div class="qrbox">

                    <?php if ($qrCa !== ''): ?>

                        <?= $qrCa ?>

                    <?php else: ?>

                        <div class="aviso">
                            No se pudo generar el QR del CA.
                        </div>

                    <?php endif; ?>

                </div>


                <div class="dato url">

                    <?= htmlspecialchars(
                        URL_CA,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>


            <!-- ==================================================
                 QR 3 - APK
                 ================================================== -->

            <div class="qr-card">

                <div class="numero">
                    3
                </div>


                <h2>
                    📱 Descargar aplicación
                </h2>


                <p class="descripcion">
                    Escanea para descargar
                    Songa-Asistencia.apk.
                </p>


                <div class="qrbox">

                    <?php if ($qrApk !== ''): ?>

                        <?= $qrApk ?>

                    <?php else: ?>

                        <div class="aviso">
                            No se pudo generar el QR del APK.
                        </div>

                    <?php endif; ?>

                </div>


                <div class="dato url">

                    <?= htmlspecialchars(
                        URL_APK,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>


            <!-- ==================================================
                 QR 4 - SITIO MOVIL
                 ================================================== -->

            <div class="qr-card">

                <div class="numero">
                    4
                </div>


                <h2>
                    🌐 Abrir Songa Eventos
                </h2>


                <p class="descripcion">
                    Escanea para abrir directamente
                    la aplicación web móvil.
                </p>


                <div class="qrbox">

                    <?php if ($qrMovil !== ''): ?>

                        <?= $qrMovil ?>

                    <?php else: ?>

                        <div class="aviso">
                            No se pudo generar el QR del sitio.
                        </div>

                    <?php endif; ?>

                </div>


                <div class="dato url">

                    <?= htmlspecialchars(
                        URL_MOVIL,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>


        </div>


        <?php if (!empty($erroresQr)): ?>

            <div class="errores">

                <strong>
                    Errores al generar los QR:
                </strong>


                <ul>

                    <?php foreach (
                        $erroresQr as $error
                    ): ?>

                        <li>

                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <p class="nota">

            Los cuatro códigos QR se generan directamente
            en este servidor mediante PHP.
            No se requiere conexión a Internet,
            Composer ni ninguna dependencia externa.

        </p>


    </div>

</div>

</body>

</html>