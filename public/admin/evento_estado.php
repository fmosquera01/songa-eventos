<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';

$db = Database::connection();


/*
|--------------------------------------------------------------------------
| RECIBIR DATOS
|--------------------------------------------------------------------------
*/

$eventoId =
    isset($_GET['id'])
        ? (int)$_GET['id']
        : 0;

$nuevoEstado =
    isset($_GET['estado'])
        ? strtoupper(
            trim(
                (string)$_GET['estado']
            )
        )
        : '';


/*
|--------------------------------------------------------------------------
| VALIDAR EVENTO
|--------------------------------------------------------------------------
*/

if ($eventoId <= 0) {

    header(
        'Location: eventos.php?error=' .
        urlencode(
            'Evento no válido.'
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDAR ESTADO
|--------------------------------------------------------------------------
*/

$estadosPermitidos = [

    'ACTIVO',
    'FINALIZADO',
    'CANCELADO'

];


if (
    !in_array(
        $nuevoEstado,
        $estadosPermitidos,
        true
    )
) {

    header(
        'Location: eventos.php?error=' .
        urlencode(
            'Estado no permitido.'
        )
    );

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | BUSCAR EVENTO
    |--------------------------------------------------------------------------
    */

    $stmt =
        $db->prepare("
            SELECT
                id,
                nombre,
                estado
            FROM eventos
            WHERE id = :id
            LIMIT 1
        ");

    $stmt->execute([
        ':id' => $eventoId
    ]);

    $evento =
        $stmt->fetch();


    if (!$evento) {

        throw new Exception(
            'El evento no existe.'
        );
    }


    $estadoActual =
        strtoupper(
            trim(
                (string)$evento['estado']
            )
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDAR TRANSICIONES
    |--------------------------------------------------------------------------
    */

    if (
        $nuevoEstado === 'ACTIVO' &&
        $estadoActual !== 'BORRADOR'
    ) {

        throw new Exception(
            'Solo se puede activar un evento que se encuentre en estado BORRADOR.'
        );
    }


    if (
        $nuevoEstado === 'FINALIZADO' &&
        $estadoActual !== 'ACTIVO'
    ) {

        throw new Exception(
            'Solo se puede finalizar un evento que se encuentre ACTIVO.'
        );
    }


    if (
        $nuevoEstado === 'CANCELADO' &&
        (
            $estadoActual === 'FINALIZADO' ||
            $estadoActual === 'CANCELADO'
        )
    ) {

        throw new Exception(
            'No se puede cancelar un evento FINALIZADO o CANCELADO.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR ESTADO
    |--------------------------------------------------------------------------
    */

    $stmt =
        $db->prepare("
            UPDATE eventos
            SET
                estado = :estado,
                actualizado_en = NOW()
            WHERE id = :id
        ");

    $stmt->execute([

        ':estado' =>
            $nuevoEstado,

        ':id' =>
            $eventoId

    ]);


    /*
    |--------------------------------------------------------------------------
    | COMPROBAR ACTUALIZACIÓN
    |--------------------------------------------------------------------------
    */

    $stmt =
        $db->prepare("
            SELECT
                estado
            FROM eventos
            WHERE id = :id
            LIMIT 1
        ");

    $stmt->execute([
        ':id' => $eventoId
    ]);

    $verificacion =
        $stmt->fetch();


    if (
        !$verificacion ||
        $verificacion['estado'] !== $nuevoEstado
    ) {

        throw new Exception(
            'No se pudo actualizar el estado del evento.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MENSAJE
    |--------------------------------------------------------------------------
    */

    if (
        $nuevoEstado === 'ACTIVO'
    ) {

        $mensaje =
            'El evento "' .
            $evento['nombre'] .
            '" fue activado correctamente.';

    } elseif (
        $nuevoEstado === 'FINALIZADO'
    ) {

        $mensaje =
            'El evento "' .
            $evento['nombre'] .
            '" fue finalizado correctamente.';

    } else {

        $mensaje =
            'El evento "' .
            $evento['nombre'] .
            '" fue actualizado correctamente.';
    }


    /*
    |--------------------------------------------------------------------------
    | REGRESAR
    |--------------------------------------------------------------------------
    */

    header(
        'Location: eventos.php?ok=' .
        urlencode(
            $mensaje
        )
    );

    exit;


} catch (
    Throwable $e
) {

    header(
        'Location: eventos.php?error=' .
        urlencode(
            $e->getMessage()
        )
    );

    exit;
}