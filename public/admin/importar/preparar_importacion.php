<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../../app/Database.php';

$eventoId = filter_input(
    INPUT_POST,
    'evento_id',
    FILTER_VALIDATE_INT
);

if (!$eventoId) {
    die('Evento no válido.');
}

if (
    !isset($_SESSION['importacion'])
) {
    die(
        'No existe una importación pendiente.'
    );
}

$importacion =
    $_SESSION['importacion'];

if (
    (int)$importacion['evento_id'] !==
    (int)$eventoId
) {
    die(
        'La importación no corresponde al evento.'
    );
}

$mapa =
    $_POST['mapa'] ?? [];

$adicionales =
    $_POST['adicionales'] ?? [];

$db =
    Database::connection();


/*
|--------------------------------------------------------------------------
| Obtener evento
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        id,
        nombre,
        estado
    FROM eventos
    WHERE id = :id
");

$stmt->execute([
    ':id' => $eventoId
]);

$evento =
    $stmt->fetch();

if (!$evento) {
    die('Evento no encontrado.');
}


/*
|--------------------------------------------------------------------------
| Validar campos obligatorios
|--------------------------------------------------------------------------
*/

$obligatorios = [

    'cod' =>
        'Código',

    'apellidos_nombres' =>
        'Apellidos y nombres'
];

$errores = [];

foreach (
    $obligatorios as $campo => $nombre
) {

    if (
        !isset($mapa[$campo]) ||
        $mapa[$campo] === ''
    ) {

        $errores[] =
            "Debe seleccionar la columna para: {$nombre}";
    }
}

if (!empty($errores)) {

    echo '<h2>Errores de validación</h2>';

    echo '<ul>';

    foreach ($errores as $error) {

        echo '<li>' .
            htmlspecialchars($error) .
            '</li>';
    }

    echo '</ul>';

    echo '<p>';

    echo '<a href="mapear.php?evento_id=' .
        $eventoId .
        '">';

    echo '← Volver al mapeo';

    echo '</a>';

    echo '</p>';

    exit;
}


/*
|--------------------------------------------------------------------------
| Validar índices
|--------------------------------------------------------------------------
*/

$totalColumnas =
    count(
        $importacion['headers']
    );

foreach (
    $mapa as $campo => $indice
) {

    if ($indice === '') {
        continue;
    }

    $indice = (int)$indice;

    if (
        $indice < 0 ||
        $indice >= $totalColumnas
    ) {

        die(
            'Una de las columnas seleccionadas no es válida.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Guardar configuración temporal
|--------------------------------------------------------------------------
*/

$_SESSION['importacion']['mapa'] =
    $mapa;

$_SESSION['importacion']['adicionales'] =
    array_map(
        'intval',
        $adicionales
    );

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Confirmar importación
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow:
                0 2px 8px rgba(0,0,0,.06);
        }

        h1,
        h2 {
            margin-top: 0;
        }

        .evento {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 15px;
        }

        .fila {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .campo {
            font-weight: bold;
        }

        .valor {
            color: #374151;
            text-align: right;
        }

        .ok {
            color: #15803d;
            font-weight: bold;
        }

        .warning {
            color: #b45309;
            font-weight: bold;
        }

        .acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        button,
        a {
            padding: 12px 20px;
            border-radius: 7px;
            border: none;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .primary {
            background: #16a34a;
            color: white;
        }

        .secondary {
            background: #6b7280;
            color: white;
        }

        ul {
            line-height: 1.8;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>
            Confirmar importación
        </h1>

        <div class="evento">

            <strong>
                Evento:
            </strong>

            <?= htmlspecialchars(
                $evento['nombre']
            ) ?>

            <br>

            <strong>
                Estado:
            </strong>

            <?= htmlspecialchars(
                $evento['estado']
            ) ?>

        </div>

    </div>


    <div class="card">

        <h2>
            Archivo
        </h2>

        <div class="fila">

            <span class="campo">
                Archivo
            </span>

            <span class="valor">

                <?= htmlspecialchars(
                    $importacion['archivo_original']
                ) ?>

            </span>

        </div>

        <div class="fila">

            <span class="campo">
                Registros
            </span>

            <span class="valor">

                <?= (int)$importacion['total_filas'] ?>

            </span>

        </div>

    </div>


    <div class="card">

        <h2>
            Campos estándar
        </h2>

        <?php

        $labels = [

            'cod' =>
                'Código',

            'cedula' =>
                'Cédula',

            'apellidos_nombres' =>
                'Apellidos y nombres',

            'area' =>
                'Área',

            'empresa' =>
                'Empresa',

            'estado' =>
                'Estado'
        ];

        ?>

        <?php foreach (
            $labels as $campo => $label
        ): ?>

            <?php

            $indice =
                $mapa[$campo] ?? '';

            ?>

            <div class="fila">

                <span class="campo">

                    <?= htmlspecialchars(
                        $label
                    ) ?>

                </span>

                <span class="valor">

                <?php if (
                    $indice === ''
                ): ?>

                    <span class="warning">
                        No seleccionado
                    </span>

                <?php else: ?>

                    <span class="ok">

                        ✓ Columna
                        <?= (int)$indice + 1 ?>

                        -

                        <?= htmlspecialchars(
                            $importacion['headers']
                            [$indice]
                        ) ?>

                    </span>

                <?php endif; ?>

                </span>

            </div>

        <?php endforeach; ?>

    </div>


    <div class="card">

        <h2>
            Campos adicionales
        </h2>

        <?php if (
            empty($adicionales)
        ): ?>

            <p>
                No se seleccionaron campos adicionales.
            </p>

        <?php else: ?>

            <ul>

            <?php foreach (
                $adicionales as $indice
            ): ?>

                <li>

                    <strong>
                        <?= htmlspecialchars(
                            $importacion['headers']
                            [$indice]
                        ) ?>
                    </strong>

                    — columna
                    <?= $indice + 1 ?>

                </li>

            <?php endforeach; ?>

            </ul>

        <?php endif; ?>

    </div>


    <div class="card">

        <p>

            <strong>
                Importante:
            </strong>

            Al confirmar, los colaboradores serán
            asociados exclusivamente a este evento.
            Los registros históricos de otros eventos
            no serán modificados.

        </p>

        <div class="acciones">

            <a
                href="mapear.php?evento_id=<?= $eventoId ?>"
                class="secondary"
            >
                ← Volver al mapeo
            </a>

            <form
                method="POST"
                action="importar.php"
                style="display:inline;"
            >

                <input
                    type="hidden"
                    name="evento_id"
                    value="<?= $eventoId ?>"
                >

                <button
                    type="submit"
                    class="primary"
                >
                    ✓ Confirmar importación
                </button>

            </form>

        </div>

    </div>

</div>

</body>

</html>