<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';

$eventoId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$eventoId) {
    die('Evento no válido.');
}

$db = Database::connection();

$stmt = $db->prepare("
    SELECT
        e.*,
        u.nombre AS creador
    FROM eventos e
    INNER JOIN usuarios u
        ON u.id = e.creado_por
    WHERE e.id = :id
");

$stmt->execute([
    ':id' => $eventoId
]);

$evento = $stmt->fetch();

if (!$evento) {
    die('Evento no encontrado.');
}


/*
|--------------------------------------------------------------------------
| COLABORADORES
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM evento_colaboradores
    WHERE evento_id = :evento_id
");

$stmt->execute([
    ':evento_id' => $eventoId
]);

$totalColaboradores =
    (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| ASISTENCIAS
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT COUNT(DISTINCT colaborador_id)
    FROM registros
    WHERE evento_id = :evento_id
      AND tipo_registro = 'ASISTENCIA'
");

$stmt->execute([
    ':evento_id' => $eventoId
]);

$totalAsistencias =
    (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| PREMIOS
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM sorteo_premios sp
    INNER JOIN sorteos s
        ON s.id = sp.sorteo_id
    WHERE s.evento_id = :evento_id
");

$stmt->execute([
    ':evento_id' => $eventoId
]);

$totalSorteos =
    (int)$stmt->fetchColumn();

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
<?= htmlspecialchars($evento['nombre']) ?>
- Songa Event Control
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background:
        #f4f6f9;

    color:
        #1f2937;
}

.navbar {

    background:
        #1f2937;

    color:
        white;

    padding:
        16px 30px;
}

.navbar h1 {

    margin: 0;

    font-size:
        20px;
}

.container {

    max-width:
        1200px;

    margin:
        30px auto;

    padding:
        0 20px;
}

.volver {

    display:
        inline-block;

    margin-bottom:
        20px;

    color:
        #2563eb;

    text-decoration:
        none;
}

.header {

    background:
        white;

    border-radius:
        10px;

    padding:
        25px;

    margin-bottom:
        20px;
}

.header-top {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        flex-start;

    gap:
        20px;
}

.header h2 {

    margin:
        0 0 10px;

    font-size:
        28px;
}

.descripcion {

    color:
        #6b7280;
}

.estado {

    display:
        inline-block;

    padding:
        7px 12px;

    border-radius:
        20px;

    font-size:
        13px;

    font-weight:
        bold;
}

.BORRADOR {

    background:
        #e5e7eb;

    color:
        #374151;
}

.ACTIVO {

    background:
        #dcfce7;

    color:
        #166534;
}

.FINALIZADO {

    background:
        #dbeafe;

    color:
        #1e40af;
}

.CANCELADO {

    background:
        #fee2e2;

    color:
        #991b1b;
}

.datos {

    display:
        grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(
                180px,
                1fr
            )
        );

    gap:
        15px;

    margin-top:
        25px;
}

.dato {

    background:
        #f9fafb;

    padding:
        15px;

    border-radius:
        8px;
}

.dato-label {

    font-size:
        12px;

    color:
        #6b7280;

    margin-bottom:
        5px;
}

.dato-valor {

    font-weight:
        bold;
}

.estadisticas {

    display:
        grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(
                200px,
                1fr
            )
        );

    gap:
        15px;

    margin-bottom:
        20px;
}

.stat {

    background:
        white;

    border-radius:
        10px;

    padding:
        20px;
}

.stat-numero {

    font-size:
        32px;

    font-weight:
        bold;
}

.stat-label {

    color:
        #6b7280;

    margin-top:
        5px;
}

.menu {

    background:
        white;

    border-radius:
        10px;

    padding:
        25px;
}

.menu h3 {

    margin-top:
        0;
}

.acciones {

    display:
        grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(
                220px,
                1fr
            )
        );

    gap:
        15px;
}

.accion {

    border:
        1px solid #e5e7eb;

    border-radius:
        8px;

    padding:
        20px;

    text-decoration:
        none;

    color:
        #1f2937;

    transition:
        .2s;
}

.accion:hover {

    border-color:
        #2563eb;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.08);

    transform:
        translateY(-2px);
}

.accion-titulo {

    font-size:
        18px;

    font-weight:
        bold;

    margin-bottom:
        7px;
}

.accion-descripcion {

    font-size:
        14px;

    color:
        #6b7280;
}

</style>

</head>


<body>


<div class="navbar">

    <h1>
        Songa Event Control
    </h1>

</div>


<div class="container">


<a
    href="eventos.php"
    class="volver"
>
    ← Volver a eventos
</a>


<!-- ========================================================= -->
<!-- INFORMACIÓN DEL EVENTO -->
<!-- ========================================================= -->

<div class="header">


    <div class="header-top">


        <div>

            <h2>

                <?= htmlspecialchars(
                    $evento['nombre']
                ) ?>

            </h2>


            <?php if (
                !empty(
                    $evento['descripcion']
                )
            ): ?>

                <div class="descripcion">

                    <?= nl2br(
                        htmlspecialchars(
                            $evento[
                                'descripcion'
                            ]
                        )
                    ) ?>

                </div>

            <?php endif; ?>

        </div>


        <span
            class="estado <?= htmlspecialchars(
                $evento['estado']
            ) ?>"
        >

            <?= htmlspecialchars(
                $evento['estado']
            ) ?>

        </span>


    </div>


    <div class="datos">


        <div class="dato">

            <div class="dato-label">
                Tipo de evento
            </div>

            <div class="dato-valor">

                <?= htmlspecialchars(
                    $evento['tipo']
                ) ?>

            </div>

        </div>


        <div class="dato">

            <div class="dato-label">
                Fecha
            </div>

            <div class="dato-valor">

                <?= htmlspecialchars(
                    (string)
                    $evento[
                        'fecha_evento'
                    ]
                ) ?>

            </div>

        </div>


        <div class="dato">

            <div class="dato-label">
                Horario
            </div>

            <div class="dato-valor">

                <?= htmlspecialchars(
                    (string)
                    $evento[
                        'hora_inicio'
                    ]
                ) ?>

                -

                <?= htmlspecialchars(
                    (string)
                    $evento[
                        'hora_fin'
                    ]
                ) ?>

            </div>

        </div>


        <div class="dato">

            <div class="dato-label">
                Creado por
            </div>

            <div class="dato-valor">

                <?= htmlspecialchars(
                    $evento['creador']
                ) ?>

            </div>

        </div>


    </div>


</div>


<!-- ========================================================= -->
<!-- ESTADÍSTICAS -->
<!-- ========================================================= -->

<div class="estadisticas">


    <div class="stat">

        <div class="stat-numero">

            <?= $totalColaboradores ?>

        </div>

        <div class="stat-label">

            Colaboradores cargados

        </div>

    </div>


    <div class="stat">

        <div class="stat-numero">

            <?= $totalAsistencias ?>

        </div>

        <div class="stat-label">

            Asistentes registrados

        </div>

    </div>


    <div class="stat">

        <div class="stat-numero">

            <?= $totalSorteos ?>

        </div>

        <div class="stat-label">

            Premios registrados

        </div>

    </div>


</div>


<!-- ========================================================= -->
<!-- MENÚ -->
<!-- ========================================================= -->

<div class="menu">


    <h3>
        Administración del evento
    </h3>


    <div class="acciones">


        <!-- IMPORTAR -->

        <a
            href="importar/index.php?evento_id=<?= $eventoId ?>"
            class="accion"
        >

            <div class="accion-titulo">

                📥 Importar colaboradores

            </div>

            <div class="accion-descripcion">

                Cargar o reemplazar el listado
                de colaboradores correspondiente
                a este evento.

            </div>

        </a>


        <!-- COLABORADORES -->

        <a
            href="colaboradores.php?evento_id=<?= $eventoId ?>"
            class="accion"
        >

            <div class="accion-titulo">

                👥 Colaboradores

            </div>

            <div class="accion-descripcion">

                Consultar el listado de colaboradores
                cargados en este evento.

            </div>

        </a>


        <!-- REGISTRAR ASISTENCIA -->

        <a
            href="../operador/registro.php?evento_id=<?= $eventoId ?>"
            class="accion"
        >

            <div class="accion-titulo">

                📷 Registrar asistencia

            </div>

            <div class="accion-descripcion">

                Escanear código, ingresar
                cédula o código manualmente.

            </div>

        </a>


        <!-- VER ASISTENCIA -->

        <a
            href="asistencia.php?evento_id=<?= $eventoId ?>"
            class="accion"
        >

            <div class="accion-titulo">

                📋 Ver asistencia

            </div>

            <div class="accion-descripcion">

                Consultar quiénes ingresaron
                al evento.

            </div>

        </a>


        <!-- ================================================= -->
        <!-- SORTEO -->
        <!-- ================================================= -->

        <a
            href="sorteo.php?evento_id=<?= $eventoId ?>"
            class="accion"
        >

            <div class="accion-titulo">

                🎁 Sorteo

            </div>

            <div class="accion-descripcion">

                Realizar sorteos utilizando
                únicamente los asistentes
                del evento.

            </div>

        </a>


        <!-- CONFIGURACIÓN -->

        <a
            href="configuracion.php?evento_id=<?= $eventoId ?>"
            class="accion"
        >

            <div class="accion-titulo">

                ⚙️ Configuración

            </div>

            <div class="accion-descripcion">

                Configurar reglas y campos
                específicos del evento.

            </div>

        </a>


    </div>


</div>


</div>


</body>

</html>