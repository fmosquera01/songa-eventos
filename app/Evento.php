<?php

declare(strict_types=1);

/**
 * Funciones relacionadas con el estado de los eventos.
 */

require_once __DIR__ . '/Database.php';


/**
 * Obtiene un evento por ID.
 */
function obtenerEvento(int $eventoId): ?array
{
    if ($eventoId <= 0) {
        return null;
    }

    $db = Database::connection();

    $stmt = $db->prepare("
        SELECT
            id,
            nombre,
            descripcion,
            tipo,
            fecha_evento,
            hora_inicio,
            hora_fin,
            estado,
            creado_en,
            creado_por
        FROM eventos
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $eventoId
    ]);

    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    return $evento ?: null;
}


/**
 * Comprueba si un evento existe.
 */
function eventoExiste(int $eventoId): bool
{
    return obtenerEvento($eventoId) !== null;
}


/**
 * Comprueba si el evento está finalizado.
 *
 * Un evento FINALIZADO es completamente
 * de solo lectura.
 */
function eventoFinalizado(int $eventoId): bool
{
    $evento = obtenerEvento($eventoId);

    return $evento !== null
        && strtoupper(
            (string)$evento['estado']
        ) === 'FINALIZADO';
}


/**
 * Comprueba si un evento está activo.
 */
function eventoActivo(int $eventoId): bool
{
    $evento = obtenerEvento($eventoId);

    return $evento !== null
        && strtoupper(
            (string)$evento['estado']
        ) === 'ACTIVO';
}


/**
 * Comprueba si el evento está en borrador.
 */
function eventoBorrador(int $eventoId): bool
{
    $evento = obtenerEvento($eventoId);

    return $evento !== null
        && strtoupper(
            (string)$evento['estado']
        ) === 'BORRADOR';
}


/**
 * Detiene cualquier operación de modificación
 * cuando el evento está FINALIZADO.
 *
 * Se utiliza en scripts que modifican
 * información del evento.
 */
function exigirEventoModificable(int $eventoId): array
{
    $evento = obtenerEvento($eventoId);

    if (!$evento) {

        http_response_code(404);

        die('Evento no encontrado.');
    }

    $estado = strtoupper(
        trim(
            (string)$evento['estado']
        )
    );

    if ($estado === 'FINALIZADO') {

        http_response_code(403);

        ?>
        <!DOCTYPE html>
        <html lang="es">

        <head>

            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1"
            >

            <title>Evento finalizado</title>

            <style>

                * {
                    box-sizing: border-box;
                }

                body {

                    margin: 0;

                    min-height: 100vh;

                    display: flex;

                    align-items: center;

                    justify-content: center;

                    padding: 20px;

                    font-family:
                        Arial,
                        Helvetica,
                        sans-serif;

                    background: #f3f4f6;

                    color: #1f2937;
                }

                .card {

                    width:
                        min(520px, 94vw);

                    background: white;

                    border-radius: 16px;

                    padding: 40px;

                    text-align: center;

                    box-shadow:
                        0 10px 35px
                        rgba(0,0,0,.12);
                }

                .icon {

                    width: 75px;

                    height: 75px;

                    margin: 0 auto 20px;

                    border-radius: 50%;

                    display: flex;

                    align-items: center;

                    justify-content: center;

                    background: #dbeafe;

                    color: #1d4ed8;

                    font-size: 36px;
                }

                h1 {

                    margin:
                        0 0 10px;

                    font-size: 26px;
                }

                p {

                    margin:
                        8px 0;

                    color: #6b7280;

                    line-height: 1.5;
                }

                .evento {

                    margin:
                        20px 0;

                    padding: 14px;

                    background: #f9fafb;

                    border-radius: 9px;

                    font-weight: bold;
                }

                .btn {

                    display: inline-block;

                    margin-top: 15px;

                    padding:
                        12px 20px;

                    border-radius: 8px;

                    background: #2563eb;

                    color: white;

                    text-decoration: none;

                    font-weight: bold;
                }

                .btn:hover {

                    background: #1d4ed8;
                }

            </style>

        </head>

        <body>

            <div class="card">

                <div class="icon">
                    🔒
                </div>

                <h1>
                    Evento finalizado
                </h1>

                <p>
                    Este evento se encuentra finalizado
                    y ya no puede ser modificado.
                </p>

                <div class="evento">

                    <?= htmlspecialchars(
                        (string)$evento['nombre']
                    ) ?>

                </div>

                <p>
                    El evento está disponible únicamente
                    para consultas.
                </p>

                <a
                    href="evento.php?id=<?= (int)$eventoId ?>"
                    class="btn"
                >
                    ← Volver al evento
                </a>

            </div>

        </body>

        </html>
        <?php

        exit;
    }

    return $evento;
}


/**
 * Determina si una determinada función
 * debe estar disponible según el estado.
 */
function puedeModificarEvento(int $eventoId): bool
{
    $evento = obtenerEvento($eventoId);

    if (!$evento) {
        return false;
    }

    return strtoupper(
        trim(
            (string)$evento['estado']
        )
    ) !== 'FINALIZADO';
}


/**
 * Determina si el evento solamente
 * debe mostrarse en modo consulta.
 */
function soloConsultaEvento(int $eventoId): bool
{
    return eventoFinalizado($eventoId);
}