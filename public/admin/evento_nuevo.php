<?php

declare(strict_types=1);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Nuevo evento</title>

    <style>

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        h1 {
            margin-top: 0;
        }

        .campo {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 15px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .acciones {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        button,
        a {
            padding: 11px 18px;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #2563eb;
            color: white;
        }

        .cancelar {
            background: #6b7280;
            color: white;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>
            Nuevo evento
        </h1>

        <form
            method="POST"
            action="evento_guardar.php"
        >

            <div class="campo">

                <label>
                    Nombre del evento *
                </label>

                <input
                    type="text"
                    name="nombre"
                    maxlength="200"
                    required
                    placeholder="Ej: Navidad 2026"
                >

            </div>

            <div class="campo">

                <label>
                    Tipo de evento *
                </label>

                <select
                    name="tipo"
                    required
                >

                    <option value="">
                        Seleccione...
                    </option>

                    <option value="ASISTENCIA">
                        Asistencia
                    </option>

                    <option value="ENTREGA">
                        Entrega
                    </option>

                    <option value="SORTEO">
                        Sorteo
                    </option>

                    <option value="CONTROL">
                        Control
                    </option>

                </select>

            </div>

            <div class="campo">

                <label>
                    Fecha del evento
                </label>

                <input
                    type="date"
                    name="fecha_evento"
                >

            </div>

            <div class="campo">

                <label>
                    Hora de inicio
                </label>

                <input
                    type="time"
                    name="hora_inicio"
                >

            </div>

            <div class="campo">

                <label>
                    Hora de finalización
                </label>

                <input
                    type="time"
                    name="hora_fin"
                >

            </div>

            <div class="campo">

                <label>
                    Descripción
                </label>

                <textarea
                    name="descripcion"
                    placeholder="Descripción del evento..."
                ></textarea>

            </div>

            <div class="campo">

                <label>

                    <input
                        type="checkbox"
                        name="validar_estado"
                        value="1"
                        checked
                        style="width:auto;"
                    >

                    Validar estado del colaborador

                </label>

            </div>

            <div class="campo">

                <label>

                    <input
                        type="checkbox"
                        name="permitir_duplicado"
                        value="1"
                        style="width:auto;"
                    >

                    Permitir registros duplicados

                </label>

            </div>

            <div class="acciones">

                <button type="submit">
                    Crear evento
                </button>

                <a
                    href="eventos.php"
                    class="cancelar"
                >
                    Cancelar
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>