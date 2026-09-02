<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';

$db = Database::connection();

$eventoId =
    isset($_POST['evento_id'])
        ? (int)$_POST['evento_id']
        : 0;

$premioId =
    isset($_POST['premio_id'])
        ? (int)$_POST['premio_id']
        : 0;

$colaboradorId =
    isset($_POST['colaborador_id'])
        ? (int)$_POST['colaborador_id']
        : 0;

$accion =
    strtoupper(
        trim(
            (string)(
                $_POST['accion']
                ?? ''
            )
        )
    );


if (
    $eventoId <= 0 ||
    $premioId <= 0 ||
    $colaboradorId <= 0
) {

    die('Datos inválidos.');
}


if (
    !in_array(
        $accion,
        [
            'CONFIRMAR',
            'RECHAZAR'
        ],
        true
    )
) {

    die('Acción no válida.');
}


try {

    $db->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | OBTENER PREMIO
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT
            p.id,
            p.sorteo_id,
            p.estado,
            p.nombre,
            s.evento_id
        FROM sorteo_premios p
        INNER JOIN sorteos s
            ON s.id = p.sorteo_id
        WHERE p.id = :premio_id
          AND s.evento_id = :evento_id
        FOR UPDATE
    ");

    $stmt->execute([

        ':premio_id' =>
            $premioId,

        ':evento_id' =>
            $eventoId

    ]);

    $premio =
        $stmt->fetch();


    if (!$premio) {

        throw new Exception(
            'Premio no encontrado.'
        );
    }


    if (
        $premio['estado'] === 'SORTEADO'
    ) {

        throw new Exception(
            'Este premio ya tiene ganador.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFICAR QUE EL COLABORADOR ASISTIÓ
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT
            ec.id,
            ec.cod,
            ec.cedula,
            ec.apellidos_nombres,
            ec.area,
            ec.empresa
        FROM evento_colaboradores ec
        INNER JOIN registros r
            ON r.colaborador_id = ec.id
            AND r.evento_id = ec.evento_id
        WHERE ec.id = :colaborador_id
          AND ec.evento_id = :evento_id
          AND r.tipo_registro = 'ASISTENCIA'
        LIMIT 1
    ");

    $stmt->execute([

        ':colaborador_id' =>
            $colaboradorId,

        ':evento_id' =>
            $eventoId

    ]);

    $colaborador =
        $stmt->fetch();


    if (!$colaborador) {

        throw new Exception(
            'El colaborador seleccionado no tiene asistencia registrada en este evento.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RECHAZAR GANADOR
    |--------------------------------------------------------------------------
    */

    if (
        $accion === 'RECHAZAR'
    ) {

        $stmt = $db->prepare("
            INSERT INTO sorteo_intentos
            (
                premio_id,
                colaborador_id,
                resultado
            )
            VALUES
            (
                :premio_id,
                :colaborador_id,
                'RECHAZADO'
            )
            ON DUPLICATE KEY UPDATE
                resultado = 'RECHAZADO'
        ");

        $stmt->execute([

            ':premio_id' =>
                $premioId,

            ':colaborador_id' =>
                $colaboradorId

        ]);


        $db->commit();


        header(
            'Location: sorteo_ruleta.php?evento_id=' .
            $eventoId .
            '&premio_id=' .
            $premioId .
            '&error=' .
            urlencode(
                'El ganador fue descartado. Puede volver a sortear.'
            )
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | CONFIRMAR GANADOR
    |--------------------------------------------------------------------------
    */

    /*
     * Verificar que no haya ganado otro premio
     * del mismo evento.
     */

    $stmt = $db->prepare("
        SELECT
            p.id,
            p.nombre
        FROM sorteo_premios p
        INNER JOIN sorteos s
            ON s.id = p.sorteo_id
        WHERE s.evento_id = :evento_id
          AND p.estado = 'SORTEADO'
          AND p.ganador_colaborador_id = :colaborador_id
        LIMIT 1
    ");

    $stmt->execute([

        ':evento_id' =>
            $eventoId,

        ':colaborador_id' =>
            $colaboradorId

    ]);

    $ganadorAnterior =
        $stmt->fetch();


    if ($ganadorAnterior) {

        throw new Exception(
            'Este colaborador ya ganó el premio "' .
            $ganadorAnterior['nombre'] .
            '" en este evento.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GUARDAR GANADOR
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        UPDATE sorteo_premios
        SET
            ganador_colaborador_id = :colaborador_id,
            estado = 'SORTEADO',
            sorteado_en = NOW()
        WHERE id = :premio_id
          AND estado = 'PENDIENTE'
    ");

    $stmt->execute([

        ':colaborador_id' =>
            $colaboradorId,

        ':premio_id' =>
            $premioId

    ]);


    if (
        $stmt->rowCount() !== 1
    ) {

        throw new Exception(
            'No se pudo guardar el ganador.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REGISTRAR INTENTO GANADOR
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        INSERT INTO sorteo_intentos
        (
            premio_id,
            colaborador_id,
            resultado
        )
        VALUES
        (
            :premio_id,
            :colaborador_id,
            'GANADOR'
        )
        ON DUPLICATE KEY UPDATE
            resultado = 'GANADOR'
    ");

    $stmt->execute([

        ':premio_id' =>
            $premioId,

        ':colaborador_id' =>
            $colaboradorId

    ]);


    /*
    |--------------------------------------------------------------------------
    | MANTENER cantidad_ganadores ACTUALIZADA
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        UPDATE sorteos
        SET cantidad_ganadores = (
            SELECT COUNT(*)
            FROM sorteo_premios
            WHERE sorteo_id = :sorteo_id
              AND estado = 'SORTEADO'
        )
        WHERE id = :id
    ");

    $stmt->execute([

        ':sorteo_id' =>
            $premio['sorteo_id'],

        ':id' =>
            $premio['sorteo_id']

    ]);


    $db->commit();


    header(
        'Location: sorteo.php?evento_id=' .
        $eventoId .
        '&ok=' .
        urlencode(
            'Ganador confirmado correctamente.'
        )
    );

    exit;


} catch (
    Throwable $e
) {

    if (
        $db->inTransaction()
    ) {

        $db->rollBack();
    }


    header(
        'Location: sorteo.php?evento_id=' .
        $eventoId .
        '&error=' .
        urlencode(
            $e->getMessage()
        )
    );

    exit;
}