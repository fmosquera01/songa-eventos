<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Evento.php';

$db = Database::connection();

/*
|--------------------------------------------------------------------------
| OBTENER EVENTO ID
|--------------------------------------------------------------------------
*/

$eventoId = (int)(
    $_GET['evento_id']
    ?? $_GET['id']
    ?? $_POST['evento_id']
    ?? 0
);

if ($eventoId <= 0) {
    die('Evento no válido.');
}


/*
|--------------------------------------------------------------------------
| OBTENER EVENTO
|--------------------------------------------------------------------------
*/

$evento = obtenerEvento($eventoId);

if (!$evento) {
    die('Evento no encontrado.');
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$mensaje = '';
$error = '';


/*
|--------------------------------------------------------------------------
| GUARDAR CONFIGURACIÓN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * IMPORTANTE:
     * Esta comprobación se realiza nuevamente justo antes
     * de modificar la base de datos.
     *
     * De esta forma, aunque alguien deje abierta la página
     * y posteriormente el evento sea finalizado, no podrá
     * guardar cambios.
     */

    $evento = exigirEventoModificable($eventoId);


    $nombre = trim(
        (string)(
            $_POST['nombre']
            ?? ''
        )
    );

    $descripcion = trim(
        (string)(
            $_POST['descripcion']
            ?? ''
        )
    );

    $tipo = trim(
        (string)(
            $_POST['tipo']
            ?? ''
        )
    );

    $fechaEvento = trim(
        (string)(
            $_POST['fecha_evento']
            ?? ''
        )
    );

    $horaInicio = trim(
        (string)(
            $_POST['hora_inicio']
            ?? ''
        )
    );

    $horaFin = trim(
        (string)(
            $_POST['hora_fin']
            ?? ''
        )
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDACIONES
    |--------------------------------------------------------------------------
    */

    if ($nombre === '') {

        $error =
            'El nombre del evento es obligatorio.';

    } elseif ($fechaEvento === '') {

        $error =
            'La fecha del evento es obligatoria.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                UPDATE eventos
                SET
                    nombre = :nombre,
                    descripcion = :descripcion,
                    tipo = :tipo,
                    fecha_evento = :fecha_evento,
                    hora_inicio = :hora_inicio,
                    hora_fin = :hora_fin
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([

                ':nombre' =>
                    $nombre,

                ':descripcion' =>
                    $descripcion !== ''
                        ? $descripcion
                        : null,

                ':tipo' =>
                    $tipo !== ''
                        ? $tipo
                        : null,

                ':fecha_evento' =>
                    $fechaEvento,

                ':hora_inicio' =>
                    $horaInicio !== ''
                        ? $horaInicio
                        : null,

                ':hora_fin' =>
                    $horaFin !== ''
                        ? $horaFin
                        : null,

                ':id' =>
                    $eventoId
            ]);


            $mensaje =
                'Configuración guardada correctamente.';


            /*
            |--------------------------------------------------------------------------
            | RECARGAR EVENTO
            |--------------------------------------------------------------------------
            */

            $evento =
                obtenerEvento(
                    $eventoId
                );


        } catch (Throwable $e) {

            $error =
                'No se pudo guardar la configuración: ' .
                $e->getMessage();
        }
    }
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
Configuración -
<?= htmlspecialchars(
    (string)$evento['nombre']
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

    background: #f3f4f6;

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

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;
}


.navbar h1 {

    margin: 0;

    font-size: 20px;
}


.navbar small {

    opacity: .75;
}


/*
|--------------------------------------------------------------------------
| CONTENEDOR
|--------------------------------------------------------------------------
*/

.container {

    width:
        min(900px, 94vw);

    margin:
        30px auto;
}


/*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

.card {

    background: white;

    border-radius: 12px;

    padding: 28px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.08);
}


/*
|--------------------------------------------------------------------------
| TITULO
|--------------------------------------------------------------------------
*/

.titulo {

    margin-bottom: 25px;

    padding-bottom: 18px;

    border-bottom:
        1px solid #e5e7eb;
}


.titulo h2 {

    margin:
        0 0 6px;

    font-size: 24px;
}


.titulo p {

    margin: 0;

    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| FORMULARIO
|--------------------------------------------------------------------------
*/

.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
}


.campo {

    display: flex;

    flex-direction: column;

    gap: 7px;
}


.campo.full {

    grid-column:
        1 / -1;
}


.campo label {

    font-weight: 700;

    font-size: 14px;

    color: #374151;
}


.campo input,
.campo textarea,
.campo select {

    width: 100%;

    padding:
        12px 13px;

    border:
        1px solid #d1d5db;

    border-radius: 8px;

    font-size: 15px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    outline: none;
}


.campo input:focus,
.campo textarea:focus,
.campo select:focus {

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.12);
}


.campo textarea {

    min-height: 110px;

    resize: vertical;
}


/*
|--------------------------------------------------------------------------
| ESTADO
|--------------------------------------------------------------------------
*/

.estado-box {

    margin-top: 25px;

    padding: 15px;

    background:
        #f9fafb;

    border:
        1px solid #e5e7eb;

    border-radius: 9px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}


.estado-label {

    font-size: 13px;

    color: #6b7280;
}


.estado {

    display: inline-block;

    padding:
        6px 11px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}


.estado.BORRADOR {

    background:
        #e5e7eb;

    color:
        #374151;
}


.estado.ACTIVO {

    background:
        #dcfce7;

    color:
        #166534;
}


.estado.FINALIZADO {

    background:
        #dbeafe;

    color:
        #1e40af;
}


.estado.CANCELADO {

    background:
        #fee2e2;

    color:
        #991b1b;
}


/*
|--------------------------------------------------------------------------
| BOTONES
|--------------------------------------------------------------------------
*/

.botones {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 10px;

    margin-top: 28px;

    padding-top: 20px;

    border-top:
        1px solid #e5e7eb;
}


.btn {

    display: inline-block;

    padding:
        11px 18px;

    border: 0;

    border-radius: 8px;

    text-decoration: none;

    cursor: pointer;

    font-weight: 700;

    font-size: 14px;
}


.btn-primary {

    background:
        #2563eb;

    color: white;
}


.btn-primary:hover {

    background:
        #1d4ed8;
}


.btn-secondary {

    background:
        #6b7280;

    color: white;
}


.btn-secondary:hover {

    background:
        #4b5563;
}


/*
|--------------------------------------------------------------------------
| MENSAJES
|--------------------------------------------------------------------------
*/

.mensaje {

    padding:
        13px 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-weight: 600;
}


.mensaje.ok {

    background:
        #dcfce7;

    color:
        #166534;

    border:
        1px solid #bbf7d0;
}


.mensaje.error {

    background:
        #fee2e2;

    color:
        #991b1b;

    border:
        1px solid #fecaca;
}


/*
|--------------------------------------------------------------------------
| INFORMACIÓN
|--------------------------------------------------------------------------
*/

.info {

    margin-top: 20px;

    padding:
        15px;

    border-radius: 8px;

    background:
        #eff6ff;

    border:
        1px solid #bfdbfe;

    color:
        #1e40af;

    font-size: 13px;

    line-height: 1.5;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .form-grid {

        grid-template-columns:
            1fr;
    }


    .campo.full {

        grid-column:
            auto;
    }


    .botones {

        flex-direction:
            column-reverse;

        align-items:
            stretch;
    }


    .btn {

        text-align:
            center;

        width:
            100%;
    }


    .estado-box {

        flex-direction:
            column;

        align-items:
            flex-start;
    }

}

</style>

</head>


<body>


<div class="navbar">

    <div>

        <h1>
            ⚙️ Configuración del evento
        </h1>

        <small>
            <?= htmlspecialchars(
                (string)$evento['nombre']
            ) ?>
        </small>

    </div>


    <a
        href="evento.php?id=<?= $eventoId ?>"
        class="btn btn-secondary"
    >
        ← Volver
    </a>

</div>


<div class="container">


<div class="card">


<div class="titulo">

    <h2>
        Datos del evento
    </h2>

    <p>
        Modifique la información general del evento.
    </p>

</div>


<?php if ($mensaje !== ''): ?>

    <div class="mensaje ok">

        ✓

        <?= htmlspecialchars(
            $mensaje
        ) ?>

    </div>

<?php endif; ?>


<?php if ($error !== ''): ?>

    <div class="mensaje error">

        ⚠️

        <?= htmlspecialchars(
            $error
        ) ?>

    </div>

<?php endif; ?>


<form
    method="POST"
    action="configuracion.php"
>


<input
    type="hidden"
    name="evento_id"
    value="<?= $eventoId ?>"
>


<div class="form-grid">


    <!-- NOMBRE -->

    <div class="campo full">

        <label for="nombre">
            Nombre del evento *
        </label>

        <input
            type="text"
            id="nombre"
            name="nombre"
            maxlength="200"
            required
            value="<?= htmlspecialchars(
                (string)$evento['nombre']
            ) ?>"
        >

    </div>


    <!-- DESCRIPCIÓN -->

    <div class="campo full">

        <label for="descripcion">
            Descripción
        </label>

        <textarea
            id="descripcion"
            name="descripcion"
            maxlength="1000"
        ><?= htmlspecialchars(
            (string)(
                $evento['descripcion']
                ?? ''
            )
        ) ?></textarea>

    </div>


    <!-- TIPO -->

    <div class="campo">

        <label for="tipo">
            Tipo de evento
        </label>

        <input
            type="text"
            id="tipo"
            name="tipo"
            maxlength="100"
            value="<?= htmlspecialchars(
                (string)(
                    $evento['tipo']
                    ?? ''
                )
            ) ?>"
        >

    </div>


    <!-- FECHA -->

    <div class="campo">

        <label for="fecha_evento">
            Fecha del evento *
        </label>

        <input
            type="date"
            id="fecha_evento"
            name="fecha_evento"
            required
            value="<?= htmlspecialchars(
                (string)(
                    $evento['fecha_evento']
                    ?? ''
                )
            ) ?>"
        >

    </div>


    <!-- HORA INICIO -->

    <div class="campo">

        <label for="hora_inicio">
            Hora de inicio
        </label>

        <input
            type="time"
            id="hora_inicio"
            name="hora_inicio"
            value="<?= htmlspecialchars(
                substr(
                    (string)(
                        $evento['hora_inicio']
                        ?? ''
                    ),
                    0,
                    5
                )
            ) ?>"
        >

    </div>


    <!-- HORA FIN -->

    <div class="campo">

        <label for="hora_fin">
            Hora de finalización
        </label>

        <input
            type="time"
            id="hora_fin"
            name="hora_fin"
            value="<?= htmlspecialchars(
                substr(
                    (string)(
                        $evento['hora_fin']
                        ?? ''
                    ),
                    0,
                    5
                )
            ) ?>"
        >

    </div>


</div>


<!-- ESTADO -->

<div class="estado-box">

    <div>

        <strong>
            Estado actual
        </strong>

        <div class="estado-label">

            <?php if (
                strtoupper(
                    (string)$evento['estado']
                ) === 'FINALIZADO'
            ): ?>

                Este evento es de solo consulta.

            <?php else: ?>

                El estado se administra desde la pantalla
                de Eventos.

            <?php endif; ?>

        </div>

    </div>


    <span
        class="estado <?= htmlspecialchars(
            (string)$evento['estado']
        ) ?>"
    >

        <?= htmlspecialchars(
            (string)$evento['estado']
        ) ?>

    </span>

</div>


<div class="botones">

    <a
        href="evento.php?id=<?= $eventoId ?>"
        class="btn btn-secondary"
    >
        Cancelar
    </a>


    <button
        type="submit"
        class="btn btn-primary"
    >
        💾 Guardar configuración
    </button>

</div>


</form>


<div class="info">

    <strong>Importante:</strong><br>

    Cuando un evento sea marcado como
    <strong>FINALIZADO</strong>, todos sus datos
    quedarán protegidos contra modificaciones.
    El evento podrá utilizarse únicamente para consultas
    y estadísticas.

</div>


</div>


</div>


</body>

</html>