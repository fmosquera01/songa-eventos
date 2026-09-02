<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../app/Database.php';

$db = Database::connection();

/*
|--------------------------------------------------------------------------
| EVENTO
|--------------------------------------------------------------------------
*/

$eventoId = isset($_GET['evento_id'])
    ? (int) $_GET['evento_id']
    : 0;

if ($eventoId <= 0) {
    die('Evento no válido.');
}


/*
|--------------------------------------------------------------------------
| OBTENER EVENTO
|--------------------------------------------------------------------------
*/

$stmtEvento = $db->prepare("
    SELECT
        id,
        nombre,
        descripcion,
        tipo,
        fecha_evento,
        hora_inicio,
        hora_fin,
        estado
    FROM eventos
    WHERE id = :id
");

$stmtEvento->execute([
    ':id' => $eventoId
]);

$evento = $stmtEvento->fetch(PDO::FETCH_ASSOC);

if (!$evento) {
    die('El evento no existe.');
}


/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$buscar = trim($_GET['buscar'] ?? '');
$empresa = trim($_GET['empresa'] ?? '');
$area = trim($_GET['area'] ?? '');
$metodo = trim($_GET['metodo'] ?? '');


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

/*
 * Total de colaboradores registrados en el listado
 */
$stmtTotal = $db->prepare("
    SELECT COUNT(*)
    FROM evento_colaboradores
    WHERE evento_id = :evento_id
");

$stmtTotal->execute([
    ':evento_id' => $eventoId
]);

$totalColaboradores = (int) $stmtTotal->fetchColumn();


/*
 * Total de asistencias únicas
 *
 * Se utiliza DISTINCT colaborador_id porque la tabla
 * puede eventualmente tener más de un registro.
 */
$stmtAsistentes = $db->prepare("
    SELECT COUNT(DISTINCT colaborador_id)
    FROM registros
    WHERE evento_id = :evento_id
      AND tipo_registro = 'ASISTENCIA'
");

$stmtAsistentes->execute([
    ':evento_id' => $eventoId
]);

$totalAsistentes = (int) $stmtAsistentes->fetchColumn();


/*
 * Pendientes
 */
$totalPendientes =
    max(
        0,
        $totalColaboradores - $totalAsistentes
    );


/*
 * Porcentaje
 */
$porcentaje =
    $totalColaboradores > 0
        ? round(
            ($totalAsistentes / $totalColaboradores) * 100,
            1
        )
        : 0;


/*
|--------------------------------------------------------------------------
| LISTA DE EMPRESAS
|--------------------------------------------------------------------------
*/

$stmtEmpresas = $db->prepare("
    SELECT DISTINCT empresa
    FROM evento_colaboradores
    WHERE evento_id = :evento_id
      AND empresa IS NOT NULL
      AND TRIM(empresa) <> ''
    ORDER BY empresa
");

$stmtEmpresas->execute([
    ':evento_id' => $eventoId
]);

$empresas = $stmtEmpresas->fetchAll(PDO::FETCH_COLUMN);


/*
|--------------------------------------------------------------------------
| LISTA DE ÁREAS
|--------------------------------------------------------------------------
*/

$stmtAreas = $db->prepare("
    SELECT DISTINCT area
    FROM evento_colaboradores
    WHERE evento_id = :evento_id
      AND area IS NOT NULL
      AND TRIM(area) <> ''
    ORDER BY area
");

$stmtAreas->execute([
    ':evento_id' => $eventoId
]);

$areas = $stmtAreas->fetchAll(PDO::FETCH_COLUMN);


/*
|--------------------------------------------------------------------------
| CONSULTA DE ASISTENCIAS
|--------------------------------------------------------------------------
*/

$where = [
    'r.evento_id = :evento_id',
    "r.tipo_registro = 'ASISTENCIA'"
];

$params = [
    ':evento_id' => $eventoId
];


/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

if ($buscar !== '') {

    $where[] = "
        (
            c.cod LIKE :buscar
            OR c.cedula LIKE :buscar
            OR c.apellidos_nombres LIKE :buscar
            OR c.area LIKE :buscar
            OR c.empresa LIKE :buscar
        )
    ";

    $params[':buscar'] = '%' . $buscar . '%';
}


/*
|--------------------------------------------------------------------------
| FILTRO EMPRESA
|--------------------------------------------------------------------------
*/

if ($empresa !== '') {

    $where[] = 'c.empresa = :empresa';

    $params[':empresa'] = $empresa;
}


/*
|--------------------------------------------------------------------------
| FILTRO ÁREA
|--------------------------------------------------------------------------
*/

if ($area !== '') {

    $where[] = 'c.area = :area';

    $params[':area'] = $area;
}


/*
|--------------------------------------------------------------------------
| FILTRO MÉTODO
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $metodo,
        ['CODIGO', 'CEDULA', 'MANUAL'],
        true
    )
) {

    $where[] = 'r.metodo = :metodo';

    $params[':metodo'] = $metodo;
}


/*
|--------------------------------------------------------------------------
| PAGINACIÓN
|--------------------------------------------------------------------------
*/

$porPagina = 50;

$pagina = isset($_GET['pagina'])
    ? max(1, (int) $_GET['pagina'])
    : 1;


/*
 * Contar resultados
 */
$sqlCount = "
    SELECT COUNT(*)
    FROM registros r
    INNER JOIN evento_colaboradores c
        ON c.id = r.colaborador_id
    WHERE " . implode(' AND ', $where);

$stmtCount = $db->prepare($sqlCount);
$stmtCount->execute($params);

$totalResultados = (int) $stmtCount->fetchColumn();

$totalPaginas =
    max(
        1,
        (int) ceil(
            $totalResultados / $porPagina
        )
    );


if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}


$offset =
    ($pagina - 1) * $porPagina;


/*
|--------------------------------------------------------------------------
| OBTENER ASISTENCIAS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        r.id,
        r.fecha_hora,
        r.metodo,
        r.dispositivo,
        r.ip,
        r.observacion,

        c.id AS colaborador_id,
        c.cod,
        c.cedula,
        c.apellidos_nombres,
        c.area,
        c.empresa,
        c.estado,

        u.id AS usuario_id,
        u.usuario,
        u.nombre AS usuario_nombre

    FROM registros r

    INNER JOIN evento_colaboradores c
        ON c.id = r.colaborador_id

    LEFT JOIN usuarios u
        ON u.id = r.usuario_id

    WHERE " . implode(' AND ', $where) . "

    ORDER BY
        r.fecha_hora DESC,
        r.id DESC

    LIMIT :limit
    OFFSET :offset
";


$stmt = $db->prepare($sql);

foreach ($params as $key => $value) {

    $stmt->bindValue(
        $key,
        $value
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

$asistencias =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FUNCIÓN PARA CONSERVAR FILTROS EN PAGINACIÓN
|--------------------------------------------------------------------------
*/

function urlPagina(
    int $pagina,
    int $eventoId,
    string $buscar,
    string $empresa,
    string $area,
    string $metodo
): string {

    $params = [
        'evento_id' => $eventoId,
        'pagina' => $pagina
    ];

    if ($buscar !== '') {
        $params['buscar'] = $buscar;
    }

    if ($empresa !== '') {
        $params['empresa'] = $empresa;
    }

    if ($area !== '') {
        $params['area'] = $area;
    }

    if ($metodo !== '') {
        $params['metodo'] = $metodo;
    }

    return '?' . http_build_query($params);
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Asistencia - <?= htmlspecialchars($evento['nombre']) ?>
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #f1f5f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #1e293b;
}


.container {

    max-width: 1500px;

    margin: auto;

    padding: 25px;
}


/*
|--------------------------------------------------------------------------
| ENCABEZADO
|--------------------------------------------------------------------------
*/

.header {

    background: white;

    border-radius: 12px;

    padding: 25px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.06);

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;
}


.header h1 {

    margin: 0 0 8px 0;

    font-size: 26px;
}


.header p {

    margin: 4px 0;

    color: #64748b;
}


.botones {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


.btn {

    display: inline-block;

    padding: 11px 16px;

    border-radius: 7px;

    text-decoration: none;

    border: none;

    cursor: pointer;

    font-size: 14px;
}


.btn-primary {

    background: #2563eb;

    color: white;
}


.btn-secondary {

    background: #475569;

    color: white;
}


.btn-green {

    background: #16a34a;

    color: white;
}


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 20px;
}


.stat {

    background: white;

    border-radius: 12px;

    padding: 22px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.05);
}


.stat-title {

    color: #64748b;

    font-size: 14px;

    margin-bottom: 8px;
}


.stat-number {

    font-size: 32px;

    font-weight: bold;
}


.stat-sub {

    margin-top: 5px;

    color: #64748b;

    font-size: 13px;
}


.progress {

    margin-top: 12px;

    width: 100%;

    height: 8px;

    background: #e2e8f0;

    border-radius: 20px;

    overflow: hidden;
}


.progress-bar {

    height: 100%;

    background: #16a34a;

    width: <?= $porcentaje ?>%;
}


/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

.card {

    background: white;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.05);
}


.card-title {

    font-size: 18px;

    font-weight: bold;

    margin-bottom: 15px;
}


.filters {

    display: grid;

    grid-template-columns:
        2fr
        1fr
        1fr
        1fr
        auto;

    gap: 10px;

    align-items: end;
}


.field label {

    display: block;

    font-size: 13px;

    font-weight: bold;

    margin-bottom: 6px;

    color: #475569;
}


.field input,
.field select {

    width: 100%;

    height: 40px;

    padding: 0 10px;

    border: 1px solid #cbd5e1;

    border-radius: 6px;

    background: white;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| TABLA
|--------------------------------------------------------------------------
*/

.table-wrapper {

    overflow-x: auto;

    border: 1px solid #e2e8f0;

    border-radius: 8px;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1100px;
}


thead {

    background: #f8fafc;
}


th {

    text-align: left;

    padding: 12px;

    border-bottom: 1px solid #e2e8f0;

    font-size: 13px;

    white-space: nowrap;
}


td {

    padding: 11px 12px;

    border-bottom: 1px solid #f1f5f9;

    font-size: 13px;

    vertical-align: middle;
}


tbody tr:hover {

    background: #f8fafc;
}


.nombre {

    font-weight: bold;
}


.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;
}


.badge-codigo {

    background: #dbeafe;

    color: #1d4ed8;
}


.badge-cedula {

    background: #dcfce7;

    color: #15803d;
}


.badge-manual {

    background: #fef3c7;

    color: #92400e;
}


.vacio {

    padding: 50px;

    text-align: center;

    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| PAGINACIÓN
|--------------------------------------------------------------------------
*/

.pagination {

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 6px;

    margin-top: 20px;

    flex-wrap: wrap;
}


.pagination a {

    display: inline-block;

    min-width: 36px;

    padding: 9px 11px;

    text-align: center;

    text-decoration: none;

    border-radius: 6px;

    background: #e2e8f0;

    color: #334155;

    font-size: 13px;
}


.pagination a:hover {

    background: #cbd5e1;
}


.pagination .actual {

    background: #2563eb;

    color: white;
}


.info {

    color: #64748b;

    font-size: 13px;

    margin-top: 12px;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 1000px
) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .filters {

        grid-template-columns:
            1fr 1fr;
    }

}


@media (
    max-width: 650px
) {

    .container {

        padding: 12px;
    }

    .header {

        flex-direction: column;

        align-items: flex-start;
    }

    .stats {

        grid-template-columns:
            1fr;
    }

    .filters {

        grid-template-columns:
            1fr;
    }

}

</style>

</head>


<body>


<div class="container">


<!--
|--------------------------------------------------------------------------
| ENCABEZADO
|--------------------------------------------------------------------------
-->

<div class="header">

    <div>

        <h1>
            👥 Asistencia
        </h1>

        <p>
            Evento:
            <strong>
                <?= htmlspecialchars(
                    $evento['nombre']
                ) ?>
            </strong>
        </p>

        <?php if (
            !empty($evento['fecha_evento'])
        ): ?>

            <p>
                Fecha:
                <?= htmlspecialchars(
                    $evento['fecha_evento']
                ) ?>
            </p>

        <?php endif; ?>

    </div>


    <div class="botones">

        <a
            class="btn btn-primary"
            href="../operador/registro.php?evento_id=<?= $eventoId ?>"
        >
            📷 Registrar asistencia
        </a>

        <a
            class="btn btn-secondary"
            href="evento.php?id=<?= $eventoId ?>"
        >
            ← Volver al evento
        </a>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
-->

<div class="stats">


    <div class="stat">

        <div class="stat-title">
            Colaboradores en el listado
        </div>

        <div class="stat-number">
            <?= number_format(
                $totalColaboradores
            ) ?>
        </div>

        <div class="stat-sub">
            Personas habilitadas para el evento
        </div>

    </div>


    <div class="stat">

        <div class="stat-title">
            Asistentes
        </div>

        <div class="stat-number">
            <?= number_format(
                $totalAsistentes
            ) ?>
        </div>

        <div class="stat-sub">
            Colaboradores que ya ingresaron
        </div>

    </div>


    <div class="stat">

        <div class="stat-title">
            Pendientes
        </div>

        <div class="stat-number">
            <?= number_format(
                $totalPendientes
            ) ?>
        </div>

        <div class="stat-sub">
            Todavía no registrados
        </div>

    </div>


    <div class="stat">

        <div class="stat-title">
            Porcentaje de asistencia
        </div>

        <div class="stat-number">
            <?= $porcentaje ?>%
        </div>

        <div class="progress">

            <div class="progress-bar"></div>

        </div>

    </div>


</div>


<!--
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
-->

<div class="card">

    <div class="card-title">
        🔎 Buscar asistencia
    </div>


    <form
        method="get"
        action=""
    >

        <input
            type="hidden"
            name="evento_id"
            value="<?= $eventoId ?>"
        >


        <div class="filters">


            <div class="field">

                <label>
                    Buscar
                </label>

                <input
                    type="text"
                    name="buscar"
                    value="<?= htmlspecialchars(
                        $buscar
                    ) ?>"
                    placeholder="COD, cédula, nombre, área..."
                >

            </div>


            <div class="field">

                <label>
                    Empresa
                </label>

                <select name="empresa">

                    <option value="">
                        Todas
                    </option>

                    <?php foreach (
                        $empresas as $item
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $item
                            ) ?>"
                            <?= $empresa === $item
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $item
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="field">

                <label>
                    Área
                </label>

                <select name="area">

                    <option value="">
                        Todas
                    </option>

                    <?php foreach (
                        $areas as $item
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $item
                            ) ?>"
                            <?= $area === $item
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $item
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="field">

                <label>
                    Método
                </label>

                <select name="metodo">

                    <option value="">
                        Todos
                    </option>

                    <option
                        value="CODIGO"
                        <?= $metodo === 'CODIGO'
                            ? 'selected'
                            : '' ?>
                    >
                        Código
                    </option>

                    <option
                        value="CEDULA"
                        <?= $metodo === 'CEDULA'
                            ? 'selected'
                            : '' ?>
                    >
                        Cédula
                    </option>

                    <option
                        value="MANUAL"
                        <?= $metodo === 'MANUAL'
                            ? 'selected'
                            : '' ?>
                    >
                        Manual
                    </option>

                </select>

            </div>


            <div>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Buscar
                </button>

            </div>


        </div>


    </form>

</div>


<!--
|--------------------------------------------------------------------------
| TABLA
|--------------------------------------------------------------------------
-->

<div class="card">

    <div class="card-title">

        📋 Asistentes registrados

    </div>


    <?php if (
        empty($asistencias)
    ): ?>

        <div class="vacio">

            <div style="font-size:45px;">
                📭
            </div>

            <h3>
                No hay registros
            </h3>

            <p>
                No se encontraron asistencias
                con los filtros seleccionados.
            </p>

        </div>

    <?php else: ?>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Fecha / Hora
                        </th>

                        <th>
                            COD
                        </th>

                        <th>
                            Cédula
                        </th>

                        <th>
                            Apellidos y nombres
                        </th>

                        <th>
                            Área
                        </th>

                        <th>
                            Empresa
                        </th>

                        <th>
                            Método
                        </th>

                        <th>
                            Registrado por
                        </th>

                        <th>
                            Dispositivo
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php
                    $numero =
                        $offset + 1;
                    ?>


                    <?php foreach (
                        $asistencias as $registro
                    ): ?>


                        <tr>


                            <td>
                                <?= $numero++ ?>
                            </td>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $registro['fecha_hora']
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $registro['cod']
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $registro['cedula']
                                    ?? ''
                                ) ?>

                            </td>


                            <td class="nombre">

                                <?= htmlspecialchars(
                                    $registro[
                                        'apellidos_nombres'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $registro['area']
                                    ?? ''
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $registro['empresa']
                                    ?? ''
                                ) ?>

                            </td>


                            <td>


                                <?php
                                $claseMetodo =
                                    match (
                                        $registro['metodo']
                                    ) {

                                        'CODIGO'
                                            => 'badge-codigo',

                                        'CEDULA'
                                            => 'badge-cedula',

                                        default
                                            => 'badge-manual'
                                    };
                                ?>


                                <span
                                    class="badge <?= $claseMetodo ?>"
                                >

                                    <?= htmlspecialchars(
                                        $registro['metodo']
                                    ) ?>

                                </span>


                            </td>


                            <td>

                                <?php if (
                                    !empty(
                                        $registro[
                                            'usuario_nombre'
                                        ]
                                    )
                                ): ?>

                                    <?= htmlspecialchars(
                                        $registro[
                                            'usuario_nombre'
                                        ]
                                    ) ?>

                                <?php elseif (
                                    !empty(
                                        $registro['usuario']
                                    )
                                ): ?>

                                    <?= htmlspecialchars(
                                        $registro['usuario']
                                    ) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $registro[
                                        'dispositivo'
                                    ]
                                    ?? ''
                                ) ?>


                                <?php if (
                                    !empty(
                                        $registro['ip']
                                    )
                                ): ?>

                                    <br>

                                    <small
                                        style="
                                            color:#64748b;
                                        "
                                    >

                                        <?= htmlspecialchars(
                                            $registro['ip']
                                        ) ?>

                                    </small>

                                <?php endif; ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>

            </table>

        </div>


        <div class="info">

            Mostrando

            <strong>
                <?= $totalResultados > 0
                    ? $offset + 1
                    : 0 ?>
            </strong>

            a

            <strong>
                <?= min(
                    $offset + $porPagina,
                    $totalResultados
                ) ?>
            </strong>

            de

            <strong>
                <?= number_format(
                    $totalResultados
                ) ?>
            </strong>

            registros.

        </div>


        <!--
        |--------------------------------------------------------------------------
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        -->

        <?php if (
            $totalPaginas > 1
        ): ?>

            <div class="pagination">


                <?php if (
                    $pagina > 1
                ): ?>

                    <a
                        href="<?= htmlspecialchars(
                            urlPagina(
                                $pagina - 1,
                                $eventoId,
                                $buscar,
                                $empresa,
                                $area,
                                $metodo
                            )
                        ) ?>"
                    >
                        ←
                    </a>

                <?php endif; ?>


                <?php

                $inicio =
                    max(
                        1,
                        $pagina - 3
                    );

                $fin =
                    min(
                        $totalPaginas,
                        $pagina + 3
                    );

                ?>


                <?php for (
                    $i = $inicio;
                    $i <= $fin;
                    $i++
                ): ?>


                    <a
                        href="<?= htmlspecialchars(
                            urlPagina(
                                $i,
                                $eventoId,
                                $buscar,
                                $empresa,
                                $area,
                                $metodo
                            )
                        ) ?>"
                        class="<?= $i === $pagina
                            ? 'actual'
                            : '' ?>"
                    >

                        <?= $i ?>

                    </a>


                <?php endfor; ?>


                <?php if (
                    $pagina < $totalPaginas
                ): ?>

                    <a
                        href="<?= htmlspecialchars(
                            urlPagina(
                                $pagina + 1,
                                $eventoId,
                                $buscar,
                                $empresa,
                                $area,
                                $metodo
                            )
                        ) ?>"
                    >
                        →
                    </a>

                <?php endif; ?>


            </div>

        <?php endif; ?>


    <?php endif; ?>


</div>


</div>


</body>

</html>