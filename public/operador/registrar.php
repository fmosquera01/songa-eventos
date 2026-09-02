<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../app/Database.php';


/*
|--------------------------------------------------------------------------
| RESPUESTA JSON
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/json; charset=utf-8'
);


/*
|--------------------------------------------------------------------------
| FUNCIÓN DE RESPUESTA
|--------------------------------------------------------------------------
*/

function respuesta(
    array $datos,
    int $codigo = 200
): never {

    http_response_code(
        $codigo
    );

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| MÉTODO
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    respuesta([
        'estado' => 'ERROR',
        'titulo' => 'Solicitud no válida',
        'mensaje' =>
            'La solicitud debe realizarse mediante POST.'
    ], 405);
}


/*
|--------------------------------------------------------------------------
| DATOS
|--------------------------------------------------------------------------
*/

$eventoId =
    (int)(
        $_POST['evento_id']
        ?? 0
    );


$identificador =
    trim(
        (string)(
            $_POST['identificador']
            ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| VALIDAR
|--------------------------------------------------------------------------
*/

if (
    $eventoId <= 0
) {

    respuesta([
        'estado' => 'ERROR',
        'titulo' => 'Evento no válido',
        'mensaje' =>
            'No se recibió un evento válido.'
    ], 400);
}


if (
    $identificador === ''
) {

    respuesta([
        'estado' => 'ERROR',
        'titulo' => 'Dato vacío',
        'mensaje' =>
            'Ingrese un código o cédula.'
    ], 400);
}


/*
|--------------------------------------------------------------------------
| BASE DE DATOS
|--------------------------------------------------------------------------
*/

$db =
    Database::connection();


try {

    /*
    |--------------------------------------------------------------------------
    | VALIDAR EVENTO
    |--------------------------------------------------------------------------
    */

    $stmt =
        $db->prepare("
            SELECT
                id,
                nombre,
                estado
            FROM eventos
            WHERE id = :evento_id
            LIMIT 1
        ");

    $stmt->execute([
        ':evento_id' =>
            $eventoId
    ]);

    $evento =
        $stmt->fetch();


    if (!$evento) {

        respuesta([
            'estado' => 'ERROR',
            'titulo' =>
                'Evento no encontrado',
            'mensaje' =>
                'El evento no existe.'
        ], 404);
    }


    /*
    |--------------------------------------------------------------------------
    | EVENTO ACTIVO
    |--------------------------------------------------------------------------
    */

    if (
        $evento['estado'] !==
        'ACTIVO'
    ) {

        respuesta([
            'estado' => 'ERROR',
            'titulo' =>
                'Evento no activo',
            'mensaje' =>
                'Este evento no está habilitado para registrar asistencia.'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | BUSCAR COLABORADOR
    |--------------------------------------------------------------------------
    |
    | Primero buscamos por COD.
    | Si no existe, buscamos por CÉDULA.
    |
    */

    $stmt =
        $db->prepare("
            SELECT
                id,
                evento_id,
                cod,
                cedula,
                apellidos_nombres,
                area,
                empresa,
                estado
            FROM evento_colaboradores
            WHERE evento_id = :evento_id
              AND (
                    cod = :identificador_cod
                    OR cedula = :identificador_cedula
                  )
            LIMIT 1
        ");

    $stmt->execute([

        ':evento_id' =>
            $eventoId,

        ':identificador_cod' =>
            $identificador,

        ':identificador_cedula' =>
            $identificador

    ]);


    $colaborador =
        $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | NO ENCONTRADO
    |--------------------------------------------------------------------------
    */

    if (!$colaborador) {

        respuesta([

            'estado' =>
                'ERROR',

            'titulo' =>
                'NO AUTORIZADO',

            'mensaje' =>
                'La persona no se encuentra en el listado de este evento.'

        ]);
    }


    $colaboradorId =
        (int)$colaborador['id'];


    /*
    |--------------------------------------------------------------------------
    | VALIDAR ESTADO DEL COLABORADOR
    |--------------------------------------------------------------------------
    |
    | Si el evento tiene validar_estado = 1,
    | verificamos el campo estado.
    |
    */

    $stmt =
        $db->prepare("
            SELECT
                validar_estado
            FROM eventos
            WHERE id = :evento_id
            LIMIT 1
        ");

    $stmt->execute([
        ':evento_id' =>
            $eventoId
    ]);

    $validarEstado =
        (bool)$stmt->fetchColumn();


    if (
        $validarEstado &&
        $colaborador['estado'] !== null &&
        trim(
            (string)$colaborador['estado']
        ) !== ''
    ) {

        $estado =
            mb_strtoupper(
                trim(
                    (string)$colaborador['estado']
                ),
                'UTF-8'
            );


        /*
        |--------------------------------------------------------------------------
        | ESTADOS QUE CONSIDERAMOS INACTIVOS
        |--------------------------------------------------------------------------
        */

        $estadosInactivos = [

            'INACTIVO',

            'INACTIVA',

            'BAJA',

            'CESADO',

            'CESADA',

            'RETIRADO',

            'RETIRADA',

            'NO ACTIVO',

            'NO ACTIVA'

        ];


        if (
            in_array(
                $estado,
                $estadosInactivos,
                true
            )
        ) {

            respuesta([

                'estado' =>
                    'ERROR',

                'titulo' =>
                    'COLABORADOR INACTIVO',

                'mensaje' =>
                    'El colaborador figura como inactivo en el listado.',

                'colaborador' => [

                    'cod' =>
                        $colaborador['cod'],

                    'cedula' =>
                        $colaborador['cedula'],

                    'apellidos_nombres' =>
                        $colaborador['apellidos_nombres'],

                    'area' =>
                        $colaborador['area']

                ]

            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | BUSCAR ASISTENCIA PREVIA
    |--------------------------------------------------------------------------
    */

    $stmt =
        $db->prepare("
            SELECT
                id,
                fecha_hora,
                metodo
            FROM registros
            WHERE evento_id = :evento_id
              AND colaborador_id = :colaborador_id
              AND tipo_registro = 'ASISTENCIA'
            ORDER BY fecha_hora ASC
            LIMIT 1
        ");

    $stmt->execute([

        ':evento_id' =>
            $eventoId,

        ':colaborador_id' =>
            $colaboradorId

    ]);


    $registroAnterior =
        $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | YA INGRESÓ
    |--------------------------------------------------------------------------
    */

    if ($registroAnterior) {

        $fecha =
            $registroAnterior['fecha_hora'];


        $timestamp =
            strtotime(
                $fecha
            );


        $hora =
            $timestamp
                ? date(
                    'd/m/Y H:i:s',
                    $timestamp
                )
                : $fecha;


        respuesta([

            'estado' =>
                'DUPLICADO',

            'titulo' =>
                'YA REGISTRÓ SU INGRESO',

            'mensaje' =>
                'Este colaborador ya tiene registrada su asistencia.',

            'hora' =>
                $hora,

            'colaborador' => [

                'cod' =>
                    $colaborador['cod'],

                'cedula' =>
                    $colaborador['cedula'],

                'apellidos_nombres' =>
                    $colaborador['apellidos_nombres'],

                'area' =>
                    $colaborador['area'],

                'empresa' =>
                    $colaborador['empresa']

            ]

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DETERMINAR MÉTODO
    |--------------------------------------------------------------------------
    */

    $metodo = 'MANUAL';


    if (
        $identificador ===
        (string)$colaborador['cod']
    ) {

        $metodo =
            'CODIGO';

    } elseif (
        $colaborador['cedula'] !== null &&
        $identificador ===
        (string)$colaborador['cedula']
    ) {

        $metodo =
            'CEDULA';
    }


    /*
    |--------------------------------------------------------------------------
    | USUARIO DEL SISTEMA
    |--------------------------------------------------------------------------
    */

    $usuarioId =
        isset(
            $_SESSION['usuario_id']
        )
            ? (int)$_SESSION['usuario_id']
            : 0;


    /*
    |--------------------------------------------------------------------------
    | IMPORTANTE
    |--------------------------------------------------------------------------
    |
    | Si todavía no hemos implementado
    | el login, usamos el primer usuario
    | activo como operador temporal.
    |
    */

    if (
        $usuarioId <= 0
    ) {

        $stmt =
            $db->query("
                SELECT id
                FROM usuarios
                WHERE activo = 1
                ORDER BY id ASC
                LIMIT 1
            ");

        $usuarioId =
            (int)$stmt->fetchColumn();
    }


    if (
        $usuarioId <= 0
    ) {

        respuesta([

            'estado' =>
                'ERROR',

            'titulo' =>
                'Usuario no configurado',

            'mensaje' =>
                'No existe un usuario activo para registrar la asistencia.'

        ], 500);
    }


    /*
    |--------------------------------------------------------------------------
    | DISPOSITIVO
    |--------------------------------------------------------------------------
    */

    $dispositivo =
        trim(
            (string)(
                $_SERVER['HTTP_USER_AGENT']
                ?? ''
            )
        );


    if (
        strlen($dispositivo) > 150
    ) {

        $dispositivo =
            substr(
                $dispositivo,
                0,
                150
            );
    }


    /*
    |--------------------------------------------------------------------------
    | IP
    |--------------------------------------------------------------------------
    */

    $ip =
        $_SERVER['REMOTE_ADDR']
        ?? null;


    /*
    |--------------------------------------------------------------------------
    | INSERTAR ASISTENCIA
    |--------------------------------------------------------------------------
    */

    $stmt =
        $db->prepare("
            INSERT INTO registros
            (
                evento_id,
                colaborador_id,
                tipo_registro,
                fecha_hora,
                usuario_id,
                metodo,
                dispositivo,
                ip,
                observacion
            )
            VALUES
            (
                :evento_id,
                :colaborador_id,
                'ASISTENCIA',
                NOW(),
                :usuario_id,
                :metodo,
                :dispositivo,
                :ip,
                NULL
            )
        ");


    $stmt->execute([

        ':evento_id' =>
            $eventoId,

        ':colaborador_id' =>
            $colaboradorId,

        ':usuario_id' =>
            $usuarioId,

        ':metodo' =>
            $metodo,

        ':dispositivo' =>
            $dispositivo,

        ':ip' =>
            $ip

    ]);


    /*
    |--------------------------------------------------------------------------
    | HORA
    |--------------------------------------------------------------------------
    */

    $hora =
        date(
            'd/m/Y H:i:s'
        );


    /*
    |--------------------------------------------------------------------------
    | ESTADÍSTICAS ACTUALIZADAS
    |--------------------------------------------------------------------------
    */

    $stmt =
        $db->prepare("
            SELECT COUNT(*)
            FROM evento_colaboradores
            WHERE evento_id = :evento_id
        ");

    $stmt->execute([
        ':evento_id' =>
            $eventoId
    ]);

    $total =
        (int)$stmt->fetchColumn();


    $stmt =
        $db->prepare("
            SELECT COUNT(DISTINCT colaborador_id)
            FROM registros
            WHERE evento_id = :evento_id
              AND tipo_registro = 'ASISTENCIA'
        ");

    $stmt->execute([
        ':evento_id' =>
            $eventoId
    ]);

    $asistentes =
        (int)$stmt->fetchColumn();


    $pendientes =
        max(
            0,
            $total -
            $asistentes
        );


    $porcentaje =
        $total > 0
            ? round(
                ($asistentes / $total) * 100,
                1
            )
            : 0;


    /*
    |--------------------------------------------------------------------------
    | RESPUESTA
    |--------------------------------------------------------------------------
    */

    respuesta([

        'estado' =>
            'OK',

        'titulo' =>
            'INGRESO AUTORIZADO',

        'mensaje' =>
            'La asistencia fue registrada correctamente.',

        'hora' =>
            $hora,

        'colaborador' => [

            'cod' =>
                $colaborador['cod'],

            'cedula' =>
                $colaborador['cedula'],

            'apellidos_nombres' =>
                $colaborador['apellidos_nombres'],

            'area' =>
                $colaborador['area'],

            'empresa' =>
                $colaborador['empresa']

        ],

        'estadisticas' => [

            'asistentes' =>
                number_format(
                    $asistentes
                ),

            'pendientes' =>
                number_format(
                    $pendientes
                ),

            'porcentaje' =>
                $porcentaje

        ]

    ]);


} catch (
    Throwable $e
) {

    respuesta([

        'estado' =>
            'ERROR',

        'titulo' =>
            'ERROR DEL SERVIDOR',

        'mensaje' =>
            $e->getMessage()

    ], 500);
}