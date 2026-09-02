<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';

try {
    $db = Database::connection();

    $stmt = $db->query("
        SELECT
            id,
            nombre,
            tipo,
            fecha_evento,
            estado
        FROM eventos
        ORDER BY id DESC
    ");

    $eventos = $stmt->fetchAll();

} catch (Throwable $e) {
    die(
        'Error de conexión: ' .
        htmlspecialchars($e->getMessage())
    );
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

    <title>Songa Event Control</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f1f3f5;
        }

        .vacio {
            padding: 30px;
            text-align: center;
            color: #777;
        }
    </style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>Songa Event Control</h1>

        <p>
            Sistema de control de eventos
        </p>

    </div>

    <div class="card">

        <h2>Eventos</h2>

        <?php if (empty($eventos)): ?>

            <div class="vacio">
                No existen eventos registrados.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Evento</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($eventos as $evento): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                (string) $evento['id']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $evento['nombre']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $evento['tipo']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                (string) $evento['fecha_evento']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $evento['estado']
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>
</html>