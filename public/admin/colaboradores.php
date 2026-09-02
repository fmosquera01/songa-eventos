<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';


/*
|--------------------------------------------------------------------------
| EVENTO
|--------------------------------------------------------------------------
*/

$eventoId = filter_input(
    INPUT_GET,
    'evento_id',
    FILTER_VALIDATE_INT
);

if (!$eventoId) {
    die('Evento no válido.');
}


/*
|--------------------------------------------------------------------------
| BASE DE DATOS
|--------------------------------------------------------------------------
*/

$db = Database::connection();


/*
|--------------------------------------------------------------------------
| OBTENER EVENTO
|--------------------------------------------------------------------------
*/

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
| TOTAL COLABORADORES
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

$totalColaboradores = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL ASISTENTES
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

$totalAsistentes = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

$buscar = trim(
    (string)($_GET['buscar'] ?? '')
);


/*
|--------------------------------------------------------------------------
| PAGINACIÓN
|--------------------------------------------------------------------------
*/

$porPagina = 50;

$pagina = filter_input(
    INPUT_GET,
    'pagina',
    FILTER_VALIDATE_INT
);

if (!$pagina || $pagina < 1) {
    $pagina = 1;
}


/*
|--------------------------------------------------------------------------
| CONDICIONES
|--------------------------------------------------------------------------
*/

$where = "
    WHERE ec.evento_id = :evento_id
";

$params = [
    ':evento_id' => $eventoId
];


if ($buscar !== '') {

    $where .= "
        AND (
            ec.cod LIKE :buscar
            OR ec.cedula LIKE :buscar
            OR ec.apellidos_nombres LIKE :buscar
            OR ec.area LIKE :buscar
            OR ec.empresa LIKE :buscar
        )
    ";

    $params[':buscar'] = '%' . $buscar . '%';
}


/*
|--------------------------------------------------------------------------
| TOTAL RESULTADOS
|--------------------------------------------------------------------------
*/

$sqlTotal = "
    SELECT COUNT(*)
    FROM evento_colaboradores ec
    $where
";

$stmt = $db->prepare($sqlTotal);

$stmt->execute($params);

$totalResultados = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| CÁLCULO PAGINACIÓN
|--------------------------------------------------------------------------
*/

$totalPaginas = max(
    1,
    (int)ceil(
        $totalResultados / $porPagina
    )
);


if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}


$offset =
    ($pagina - 1) *
    $porPagina;


/*
|--------------------------------------------------------------------------
| CONSULTAR COLABORADORES
|--------------------------------------------------------------------------
|
| EXISTS permite saber si ya tiene asistencia
| sin multiplicar los registros por cada asistencia.
|
*/

$sql = "
    SELECT
        ec.id,
        ec.cod,
        ec.cedula,
        ec.apellidos_nombres,
        ec.area,
        ec.empresa,
        ec.estado,
        ec.fila_excel,

        CASE
            WHEN EXISTS (
                SELECT 1
                FROM registros r
                WHERE r.evento_id = ec.evento_id
                  AND r.colaborador_id = ec.id
                  AND r.tipo_registro = 'ASISTENCIA'
            )
            THEN 1
            ELSE 0
        END AS asistio

    FROM evento_colaboradores ec

    $where

    ORDER BY
        ec.apellidos_nombres ASC

    LIMIT :limit
    OFFSET :offset
";


$stmt = $db->prepare($sql);


foreach ($params as $key => $value) {

    $stmt->bindValue(
        $key,
        $value,
        PDO::PARAM_STR
    );
}

$stmt->bindValue(
    ':limit',
    $porPagina,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);


$stmt->execute();

$colaboradores =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| FUNCIÓN URL
|--------------------------------------------------------------------------
*/

function urlPagina(
    int $pagina,
    int $eventoId,
    string $buscar
): string {

    $params = [
        'evento_id' => $eventoId,
        'pagina' => $pagina
    ];

    if ($buscar !== '') {
        $params['buscar'] = $buscar;
    }

    return '?' .
        http_build_query($params);
}


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

Colaboradores -
<?= htmlspecialchars(
    $evento['nombre']
) ?>

</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f6f9;

    color: #1f2937;
}


/*
|--------------------------------------------------------------------------
| NAVBAR
|--------------------------------------------------------------------------
*/

.navbar {

    background: #1f2937;

    color: white;

    padding:
        16px 30px;
}


.navbar h1 {

    margin: 0;

    font-size: 20px;
}


/*
|--------------------------------------------------------------------------
| CONTENEDOR
|--------------------------------------------------------------------------
*/

.container {

    max-width: 1400px;

    margin:
        30px auto;

    padding:
        0 20px;
}


/*
|--------------------------------------------------------------------------
| VOLVER
|--------------------------------------------------------------------------
*/

.volver {

    display: inline-block;

    margin-bottom: 20px;

    color: #2563eb;

    text-decoration: none;

    font-weight: bold;
}


.volver:hover {

    text-decoration: underline;
}


/*
|--------------------------------------------------------------------------
| CABECERA
|--------------------------------------------------------------------------
*/

.header {

    background: white;

    border-radius: 10px;

    padding: 25px;

    margin-bottom: 20px;
}


.header-top {

    display: flex;

    justify-content:
        space-between;

    align-items:
        flex-start;

    gap: 20px;
}


.header h2 {

    margin:
        0 0 8px;

    font-size: 28px;
}


.descripcion {

    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| ESTADO
|--------------------------------------------------------------------------
*/

.estado {

    display: inline-block;

    padding:
        7px 12px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;
}


.BORRADOR {

    background: #e5e7eb;

    color: #374151;
}


.ACTIVO {

    background: #dcfce7;

    color: #166534;
}


.FINALIZADO {

    background: #dbeafe;

    color: #1e40af;
}


.CANCELADO {

    background: #fee2e2;

    color: #991b1b;
}


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(200px, 1fr)
        );

    gap: 15px;

    margin-bottom: 20px;
}


.stat {

    background: white;

    border-radius: 10px;

    padding: 20px;
}


.stat-numero {

    font-size: 32px;

    font-weight: bold;
}


.stat-label {

    color: #6b7280;

    margin-top: 5px;
}


/*
|--------------------------------------------------------------------------
| PANEL
|--------------------------------------------------------------------------
*/

.panel {

    background: white;

    border-radius: 10px;

    padding: 25px;

    margin-bottom: 20px;
}


/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

.busqueda {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


.busqueda input {

    flex: 1;

    min-width: 280px;

    padding:
        12px 14px;

    border:
        1px solid #d1d5db;

    border-radius: 7px;

    font-size: 15px;
}


.btn {

    display: inline-block;

    border: 0;

    padding:
        12px 18px;

    border-radius: 7px;

    text-decoration: none;

    cursor: pointer;

    font-size: 14px;

    font-weight: bold;
}


.btn-primary {

    background: #2563eb;

    color: white;
}


.btn-secondary {

    background: #6b7280;

    color: white;
}


.btn:hover {

    opacity: .9;
}


/*
|--------------------------------------------------------------------------
| TABLA
|--------------------------------------------------------------------------
*/

.tabla-container {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse:
        collapse;

    min-width: 950px;
}


thead {

    background: #f8fafc;
}


th {

    text-align: left;

    padding: 13px 10px;

    border-bottom:
        2px solid #e5e7eb;

    font-size: 13px;

    color: #374151;

    white-space: nowrap;
}


td {

    padding: 12px 10px;

    border-bottom:
        1px solid #e5e7eb;

    font-size: 14px;
}


tbody tr:hover {

    background: #f8fafc;
}


/*
|--------------------------------------------------------------------------
| ESTADO ASISTENCIA
|--------------------------------------------------------------------------
*/

.asistencia-si {

    display: inline-block;

    background: #dcfce7;

    color: #166534;

    padding:
        5px 9px;

    border-radius: 15px;

    font-size: 12px;

    font-weight: bold;
}


.asistencia-no {

    display: inline-block;

    background: #f3f4f6;

    color: #6b7280;

    padding:
        5px 9px;

    border-radius: 15px;

    font-size: 12px;
}


/*
|--------------------------------------------------------------------------
| ESTADO COLABORADOR
|--------------------------------------------------------------------------
*/

.estado-colaborador {

    color: #374151;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| PAGINACIÓN
|--------------------------------------------------------------------------
*/

.paginacion {

    display: flex;

    justify-content:
        center;

    align-items:
        center;

    gap: 6px;

    flex-wrap: wrap;

    margin-top: 25px;
}


.paginacion a,
.paginacion span {

    display: inline-flex;

    align-items:
        center;

    justify-content:
        center;

    min-width: 38px;

    height: 38px;

    padding:
        0 10px;

    border:
        1px solid #d1d5db;

    border-radius: 6px;

    text-decoration: none;

    color: #374151;

    background: white;

    font-size: 14px;
}


.paginacion a:hover {

    background: #f3f4f6;
}


.paginacion .actual {

    background: #2563eb;

    color: white;

    border-color:
        #2563eb;

    font-weight: bold;
}


.paginacion .disabled {

    color: #9ca3af;

    background: #f9fafb;
}


/*
|--------------------------------------------------------------------------
| SIN RESULTADOS
|--------------------------------------------------------------------------
*/

.sin-resultados {

    text-align: center;

    padding: 50px 20px;

    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 700px
) {

    .container {

        margin-top: 15px;

        padding:
            0 10px;
    }


    .header {

        padding: 18px;
    }


    .header-top {

        flex-direction:
            column;
    }


    .panel {

        padding: 15px;
    }


    .busqueda input {

        min-width: 100%;
    }


    .busqueda .btn {

        width: 100%;
    }

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


<!-- VOLVER -->

<a
    href="evento.php?id=<?= $eventoId ?>"
    class="volver"
>
    ← Volver al evento
</a>


<!-- INFORMACIÓN EVENTO -->

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
                            $evento['descripcion']
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

</div>


<!-- ESTADÍSTICAS -->

<div class="estadisticas">


    <div class="stat">

        <div class="stat-numero">

            <?= number_format(
                $totalColaboradores
            ) ?>

        </div>

        <div class="stat-label">

            Colaboradores cargados

        </div>

    </div>


    <div class="stat">

        <div class="stat-numero">

            <?= number_format(
                $totalAsistentes
            ) ?>

        </div>

        <div class="stat-label">

            Asistentes

        </div>

    </div>


    <div class="stat">

        <div class="stat-numero">

            <?= number_format(
                max(
                    0,
                    $totalColaboradores -
                    $totalAsistentes
                )
            ) ?>

        </div>

        <div class="stat-label">

            Pendientes

        </div>

    </div>


</div>


<!-- BÚSQUEDA -->

<div class="panel">

    <h3>

        Buscar colaborador

    </h3>


    <form
        method="get"
        action="colaboradores.php"
        class="busqueda"
    >

        <input
            type="hidden"
            name="evento_id"
            value="<?= $eventoId ?>"
        >


        <input
            type="text"
            name="buscar"
            value="<?= htmlspecialchars(
                $buscar
            ) ?>"
            placeholder="COD, cédula, nombre, área o empresa..."
            autocomplete="off"
        >


        <button
            type="submit"
            class="btn btn-primary"
        >
            Buscar
        </button>


        <?php if (
            $buscar !== ''
        ): ?>

            <a
                href="colaboradores.php?evento_id=<?= $eventoId ?>"
                class="btn btn-secondary"
            >
                Limpiar
            </a>

        <?php endif; ?>

    </form>

</div>


<!-- LISTADO -->

<div class="panel">


    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:15px;
            flex-wrap:wrap;
            margin-bottom:15px;
        "
    >

        <h3
            style="margin:0;"
        >

            Colaboradores

        </h3>


        <div
            style="
                color:#6b7280;
                font-size:14px;
            "
        >

            Mostrando

            <strong>

                <?= number_format(
                    $totalResultados
                ) ?>

            </strong>

            resultado(s)

        </div>

    </div>


    <?php if (
        empty($colaboradores)
    ): ?>


        <div class="sin-resultados">

            <?php if (
                $buscar !== ''
            ): ?>

                No se encontraron
                colaboradores con ese criterio.

            <?php else: ?>

                Este evento todavía
                no tiene colaboradores cargados.

            <?php endif; ?>

        </div>


    <?php else: ?>


        <div class="tabla-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            COD
                        </th>

                        <th>
                            CÉDULA
                        </th>

                        <th>
                            APELLIDOS Y NOMBRES
                        </th>

                        <th>
                            ÁREA
                        </th>

                        <th>
                            EMPRESA
                        </th>

                        <th>
                            ESTADO
                        </th>

                        <th>
                            ASISTENCIA
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $colaboradores
                    as $indice => $colaborador
                ): ?>


                    <tr>

                        <td>

                            <?= $offset +
                                $indice +
                                1 ?>

                        </td>


                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    (string)$colaborador['cod']
                                ) ?>

                            </strong>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                (string)(
                                    $colaborador['cedula']
                                    ?? ''
                                )
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $colaborador[
                                    'apellidos_nombres'
                                ]
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                (string)(
                                    $colaborador['area']
                                    ?? ''
                                )
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                (string)(
                                    $colaborador['empresa']
                                    ?? ''
                                )
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="estado-colaborador"
                            >

                                <?= htmlspecialchars(
                                    (string)(
                                        $colaborador['estado']
                                        ?? ''
                                    )
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?php if (
                                (int)$colaborador['asistio'] === 1
                            ): ?>

                                <span
                                    class="asistencia-si"
                                >
                                    ✓ ASISTIÓ
                                </span>

                            <?php else: ?>

                                <span
                                    class="asistencia-no"
                                >
                                    PENDIENTE
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>

        </div>


        <!-- PAGINACIÓN -->

        <?php if (
            $totalPaginas > 1
        ): ?>

            <div class="paginacion">


                <?php if (
                    $pagina > 1
                ): ?>

                    <a
                        href="<?= htmlspecialchars(
                            urlPagina(
                                $pagina - 1,
                                $eventoId,
                                $buscar
                            )
                        ) ?>"
                    >
                        ←
                    </a>

                <?php else: ?>

                    <span
                        class="disabled"
                    >
                        ←
                    </span>

                <?php endif; ?>


                <?php

                /*
                 * Mostrar un rango razonable
                 * de páginas.
                 */

                $inicioPagina =
                    max(
                        1,
                        $pagina - 2
                    );

                $finPagina =
                    min(
                        $totalPaginas,
                        $pagina + 2
                    );

                ?>


                <?php if (
                    $inicioPagina > 1
                ): ?>

                    <a
                        href="<?= htmlspecialchars(
                            urlPagina(
                                1,
                                $eventoId,
                                $buscar
                            )
                        ) ?>"
                    >
                        1
                    </a>


                    <?php if (
                        $inicioPagina > 2
                    ): ?>

                        <span>
                            ...
                        </span>

                    <?php endif; ?>

                <?php endif; ?>


                <?php for (
                    $p = $inicioPagina;
                    $p <= $finPagina;
                    $p++
                ): ?>


                    <?php if (
                        $p === $pagina
                    ): ?>

                        <span
                            class="actual"
                        >
                            <?= $p ?>
                        </span>

                    <?php else: ?>

                        <a
                            href="<?= htmlspecialchars(
                                urlPagina(
                                    $p,
                                    $eventoId,
                                    $buscar
                                )
                            ) ?>"
                        >
                            <?= $p ?>
                        </a>

                    <?php endif; ?>


                <?php endfor; ?>


                <?php if (
                    $finPagina < $totalPaginas
                ): ?>


                    <?php if (
                        $finPagina <
                        $totalPaginas - 1
                    ): ?>

                        <span>
                            ...
                        </span>

                    <?php endif; ?>


                    <a
                        href="<?= htmlspecialchars(
                            urlPagina(
                                $totalPaginas,
                                $eventoId,
                                $buscar
                            )
                        ) ?>"
                    >
                        <?= $totalPaginas ?>
                    </a>

                <?php endif; ?>


                <?php if (
                    $pagina < $totalPaginas
                ): ?>

                    <a
                        href="<?= htmlspecialchars(
                            urlPagina(
                                $pagina + 1,
                                $eventoId,
                                $buscar
                            )
                        ) ?>"
                    >
                        →
                    </a>

                <?php else: ?>

                    <span
                        class="disabled"
                    >
                        →
                    </span>

                <?php endif; ?>


            </div>

        <?php endif; ?>


    <?php endif; ?>


</div>


</div>


</body>

</html>