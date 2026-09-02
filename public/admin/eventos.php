<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';

$db = Database::connection();

/*
|--------------------------------------------------------------------------
| OBTENER EVENTOS
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        e.id,
        e.nombre,
        e.descripcion,
        e.tipo,
        e.fecha_evento,
        e.hora_inicio,
        e.hora_fin,
        e.estado,
        e.creado_en,
        u.nombre AS creador
    FROM eventos e
    INNER JOIN usuarios u
        ON u.id = e.creado_por
    ORDER BY e.id DESC
");

$eventos = $stmt->fetchAll();

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
        Eventos - Songa Event Control
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #212529;
        }

        .navbar {
            background: #1f2937;
            color: white;
            padding: 16px 30px;
        }

        .navbar h1 {
            margin: 0;
            font-size: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 6px;
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
            background: #6b7280;
            color: white;
        }

        .btn-success {
            background: #16a34a;
            color: white;
        }

        .btn-warning {
            background: #d97706;
            color: white;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background: #f9fafb;
        }

        .estado {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
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

        .acciones {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .vacio {
            text-align: center;
            padding: 50px;
            color: #6b7280;
        }

        .mensaje {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .error {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
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

    <div class="header">

        <h2>
            Eventos
        </h2>

        <a
            href="evento_nuevo.php"
            class="btn btn-primary"
        >
            + Nuevo evento
        </a>

    </div>


    <?php if (isset($_GET['ok'])): ?>

        <div class="mensaje">

            <?= htmlspecialchars(
                (string)$_GET['ok']
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['error'])): ?>

        <div class="error">

            <?= htmlspecialchars(
                (string)$_GET['error']
            ) ?>

        </div>

    <?php endif; ?>


    <div class="card">

        <?php if (empty($eventos)): ?>

            <div class="vacio">

                No existen eventos registrados.

                <br><br>

                <a
                    href="evento_nuevo.php"
                    class="btn btn-primary"
                >
                    Crear primer evento
                </a>

            </div>

        <?php else: ?>

            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                Evento
                            </th>

                            <th>
                                Tipo
                            </th>

                            <th>
                                Fecha
                            </th>

                            <th>
                                Horario
                            </th>

                            <th>
                                Estado
                            </th>

                            <th>
                                Acciones
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($eventos as $evento): ?>

                        <tr>

                            <td>

                                <?= (int)$evento['id'] ?>

                            </td>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $evento['nombre']
                                    ) ?>

                                </strong>


                                <?php if (
                                    !empty(
                                        $evento['descripcion']
                                    )
                                ): ?>

                                    <br>

                                    <small>

                                        <?= htmlspecialchars(
                                            $evento['descripcion']
                                        ) ?>

                                    </small>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $evento['tipo']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    (string)$evento['fecha_evento']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    (string)$evento['hora_inicio']
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    (string)$evento['hora_fin']
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="estado <?= htmlspecialchars(
                                        $evento['estado']
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $evento['estado']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <div class="acciones">

                                    <!-- ABRIR -->

                                    <a
                                        href="evento.php?id=<?= (int)$evento['id'] ?>"
                                        class="btn btn-secondary"
                                    >
                                        Abrir
                                    </a>


                                    <!-- ACTIVAR -->

                                    <?php if (
                                        $evento['estado'] === 'BORRADOR'
                                    ): ?>

                                        <a
                                            href="evento_estado.php?id=<?= (int)$evento['id'] ?>&estado=ACTIVO"
                                            class="btn btn-success"
                                            onclick="return confirm('¿Está seguro de activar este evento?');"
                                        >
                                            Activar
                                        </a>

                                    <?php endif; ?>


                                    <!-- FINALIZAR -->

                                    <?php if (
                                        $evento['estado'] === 'ACTIVO'
                                    ): ?>

                                        <a
                                            href="evento_estado.php?id=<?= (int)$evento['id'] ?>&estado=FINALIZADO"
                                            class="btn btn-warning"
                                            onclick="return confirm('¿Está seguro de finalizar este evento? Después no se podrán registrar nuevas asistencias.');"
                                        >
                                            Finalizar
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>