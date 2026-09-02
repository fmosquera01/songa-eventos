<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../app/Database.php';

$db = Database::connection();

$sorteoId = isset($_GET['sorteo_id'])
    ? (int)$_GET['sorteo_id']
    : 0;

if ($sorteoId <= 0) {
    die('Sorteo no válido.');
}

/*
|--------------------------------------------------------------------------
| OBTENER PREMIO
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        s.id,
        s.evento_id,
        s.nombre AS premio,
        e.nombre AS evento,
        e.estado AS estado_evento

    FROM sorteos s

    INNER JOIN eventos e
        ON e.id = s.evento_id

    WHERE s.id = :id
");

$stmt->execute([
    ':id' => $sorteoId
]);

$sorteo = $stmt->fetch();

if (!$sorteo) {
    die('Premio no encontrado.');
}

$eventoId = (int)$sorteo['evento_id'];

/*
|--------------------------------------------------------------------------
| OBTENER ASISTENTES
|--------------------------------------------------------------------------
|
| SOLO personas que tienen asistencia registrada.
|
| Se excluyen:
|
| 1. Ganadores válidos de premios anteriores.
|
| 2. Ganadores válidos del mismo premio.
|
| 3. Personas marcadas NO_PRESENTE para este premio.
|
*/

$sql = "
    SELECT DISTINCT

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

    WHERE ec.evento_id = :evento_id

    AND r.tipo_registro = 'ASISTENCIA'

    AND ec.id NOT IN
    (
        SELECT sg.colaborador_id

        FROM sorteo_ganadores sg

        INNER JOIN sorteos s2
            ON s2.id = sg.sorteo_id

        WHERE s2.evento_id = :evento_id_2

        AND sg.estado = 'GANADOR'
    )

    AND ec.id NOT IN
    (
        SELECT sg2.colaborador_id

        FROM sorteo_ganadores sg2

        WHERE sg2.sorteo_id = :sorteo_id

        AND sg2.estado = 'NO_PRESENTE'
    )

    ORDER BY ec.apellidos_nombres
";

$stmt = $db->prepare($sql);

$stmt->execute([
    ':evento_id' => $eventoId,
    ':evento_id_2' => $eventoId,
    ':sorteo_id' => $sorteoId
]);

$participantes = $stmt->fetchAll();

$totalParticipantes = count($participantes);

/*
|--------------------------------------------------------------------------
| GANADOR ACTUAL
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT

        sg.id,
        sg.colaborador_id,
        sg.posicion,
        sg.estado,

        ec.cod,
        ec.cedula,
        ec.apellidos_nombres,
        ec.area,
        ec.empresa

    FROM sorteo_ganadores sg

    INNER JOIN evento_colaboradores ec
        ON ec.id = sg.colaborador_id

    WHERE sg.sorteo_id = :sorteo_id

    AND sg.estado = 'GANADOR'

    ORDER BY sg.id DESC

    LIMIT 1
");

$stmt->execute([
    ':sorteo_id' => $sorteoId
]);

$ganadorActual = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| DATOS PARA JAVASCRIPT
|--------------------------------------------------------------------------
*/

$participantesJs = [];

foreach ($participantes as $participante) {

    $participantesJs[] = [

        'id' =>
            (int)$participante['id'],

        'nombre' =>
            $participante['apellidos_nombres'],

        'cod' =>
            $participante['cod'],

        'cedula' =>
            $participante['cedula']
    ];
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
Ruleta - <?= htmlspecialchars($sorteo['premio']) ?>
</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {

    margin: 0;

    padding: 0;

    width: 100%;

    min-height: 100%;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        radial-gradient(
            circle at center,
            #1f2937 0%,
            #111827 70%,
            #030712 100%
        );

    color:
        white;
}

body {

    min-height:
        100vh;

    overflow-x:
        hidden;
}

.top {

    width:
        100%;

    padding:
        15px 20px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        20px;
}

.top h1 {

    margin:
        0;

    font-size:
        clamp(20px, 3vw, 34px);
}

.top .premio {

    color:
        #facc15;

    font-weight:
        bold;
}

.btn {

    border:
        none;

    border-radius:
        8px;

    padding:
        11px 18px;

    cursor:
        pointer;

    font-weight:
        bold;

    text-decoration:
        none;

    display:
        inline-block;
}

.btn-back {

    background:
        #374151;

    color:
        white;
}

.main {

    width:
        100%;

    display:
        flex;

    flex-direction:
        column;

    align-items:
        center;

    justify-content:
        center;

    padding:
        5px 15px 30px;
}

.ruleta-wrapper {

    position:
        relative;

    width:
        min(82vw, 760px);

    height:
        min(82vw, 760px);

    max-width:
        760px;

    max-height:
        760px;

    aspect-ratio:
        1 / 1;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;
}

#wheel {

    width:
        100%;

    height:
        100%;

    border-radius:
        50%;

    display:
        block;

    box-shadow:
        0 0 0 8px rgba(255,255,255,.08),
        0 0 50px rgba(0,0,0,.6);
}

.pointer {

    position:
        absolute;

    top:
        -3px;

    left:
        50%;

    transform:
        translateX(-50%);

    width:
        0;

    height:
        0;

    border-left:
        20px solid transparent;

    border-right:
        20px solid transparent;

    border-top:
        45px solid #facc15;

    z-index:
        10;

    filter:
        drop-shadow(
            0 4px 5px rgba(0,0,0,.5)
        );
}

.center {

    position:
        absolute;

    width:
        100px;

    height:
        100px;

    border-radius:
        50%;

    background:
        radial-gradient(
            circle,
            #fff 0%,
            #e5e7eb 55%,
            #9ca3af 100%
        );

    border:
        8px solid #374151;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    z-index:
        8;

    box-shadow:
        0 4px 15px rgba(0,0,0,.5);
}

.center span {

    color:
        #111827;

    font-size:
        14px;

    font-weight:
        bold;

    text-align:
        center;
}

.control {

    margin-top:
        20px;

    text-align:
        center;
}

.btn-spin {

    background:
        linear-gradient(
            135deg,
            #f59e0b,
            #ef4444
        );

    color:
        white;

    font-size:
        22px;

    padding:
        16px 50px;

    border-radius:
        50px;

    box-shadow:
        0 6px 20px rgba(0,0,0,.35);

    transition:
        transform .15s;
}

.btn-spin:hover {

    transform:
        scale(1.04);
}

.btn-spin:disabled {

    opacity:
        .5;

    cursor:
        not-allowed;

    transform:
        none;
}

.contador {

    margin-top:
        10px;

    color:
        #d1d5db;

    font-size:
        14px;
}

.resultado {

    position:
        fixed;

    inset:
        0;

    background:
        rgba(3,7,18,.88);

    display:
        none;

    align-items:
        center;

    justify-content:
        center;

    z-index:
        100;

    padding:
        20px;
}

.resultado-card {

    width:
        min(700px, 95vw);

    background:
        linear-gradient(
            145deg,
            #ffffff,
            #f3f4f6
        );

    color:
        #111827;

    border-radius:
        20px;

    padding:
        40px;

    text-align:
        center;

    box-shadow:
        0 20px 70px rgba(0,0,0,.6);

    animation:
        aparecer .5s ease;
}

@keyframes aparecer {

    from {

        opacity:
            0;

        transform:
            scale(.7);

    }

    to {

        opacity:
            1;

        transform:
            scale(1);

    }
}

.resultado-card .trofeo {

    font-size:
        80px;
}

.resultado-card h2 {

    margin:
        5px 0;

    color:
        #6b7280;

    font-size:
        20px;
}

.resultado-card .nombre {

    font-size:
        clamp(28px, 6vw, 52px);

    font-weight:
        900;

    margin:
        20px 0;

    text-transform:
        uppercase;
}

.resultado-card .datos {

    color:
        #4b5563;

    margin-bottom:
        25px;
}

.resultado-acciones {

    display:
        flex;

    justify-content:
        center;

    gap:
        10px;

    flex-wrap:
        wrap;
}

.btn-confirmar {

    background:
        #16a34a;

    color:
        white;
}

.btn-no-presente {

    background:
        #dc2626;

    color:
        white;
}

.btn-cerrar {

    background:
        #6b7280;

    color:
        white;
}

.sin-participantes {

    text-align:
        center;

    background:
        #7f1d1d;

    border-radius:
        12px;

    padding:
        25px;

    max-width:
        700px;

    margin:
        30px auto;
}

@media(max-width:600px) {

    .ruleta-wrapper {

        width:
            94vw;

        height:
            94vw;
    }

    .center {

        width:
            70px;

        height:
            70px;
    }

    .center span {

        font-size:
            10px;
    }

    .btn-spin {

        font-size:
            18px;

        padding:
            14px 35px;
    }

    .resultado-card {

        padding:
            25px 15px;
    }
}

</style>

</head>

<body>

<div class="top">

    <h1>

        🎁

        <?= htmlspecialchars(
            $sorteo['evento']
        ) ?>

        —

        <span class="premio">

            <?= htmlspecialchars(
                $sorteo['premio']
            ) ?>

        </span>

    </h1>

    <a
        class="btn btn-back"
        href="sorteo.php?evento_id=<?= $eventoId ?>"
    >
        ← Volver
    </a>

</div>


<?php if ($totalParticipantes <= 0): ?>

    <div class="sin-participantes">

        <h2>
            No existen participantes disponibles.
        </h2>

        <p>
            El sorteo solamente utiliza colaboradores
            que registraron asistencia en este evento.
        </p>

        <p>
            Los ganadores anteriores tampoco participan.
        </p>

    </div>

<?php else: ?>

<div class="main">

    <div class="ruleta-wrapper">

        <div class="pointer"></div>

        <canvas
            id="wheel"
        ></canvas>

        <div class="center">

            <span>
                ¡SUERTE!
            </span>

        </div>

    </div>


    <div class="control">

        <button
            id="btnSpin"
            class="btn btn-spin"
            onclick="girar()"
        >
            🎡 GIRAR RULETA
        </button>

        <div class="contador">

            Participantes disponibles:

            <strong id="contador">
                <?= $totalParticipantes ?>
            </strong>

        </div>

    </div>

</div>


<div
    id="resultado"
    class="resultado"
>

    <div class="resultado-card">

        <div class="trofeo">
            🏆
        </div>

        <h2>
            ¡TENEMOS GANADOR!
        </h2>

        <div
            id="ganadorNombre"
            class="nombre"
        >
        </div>

        <div
            id="ganadorDatos"
            class="datos"
        >
        </div>

        <div class="resultado-acciones">

            <button
                class="btn btn-confirmar"
                onclick="confirmarGanador()"
            >
                🏆 Confirmar ganador
            </button>

            <button
                class="btn btn-no-presente"
                onclick="noEstaPresente()"
            >
                ❌ Ya se fue / No está
            </button>

            <button
                class="btn btn-cerrar"
                onclick="cerrarResultado()"
            >
                Ver después
            </button>

        </div>

    </div>

</div>

<?php endif; ?>


<script>

const participantes =
    <?= json_encode(
        $participantesJs,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) ?>;

const sorteoId =
    <?= $sorteoId ?>;

const eventoId =
    <?= $eventoId ?>;

const canvas =
    document.getElementById('wheel');

const ctx =
    canvas.getContext('2d');

let rotation = 0;

let girando = false;

let ganadorActual = null;

const colores = [

    '#2563eb',
    '#dc2626',
    '#16a34a',
    '#d97706',
    '#7c3aed',
    '#0891b2',
    '#db2777',
    '#65a30d',
    '#ea580c',
    '#4f46e5',
    '#0f766e',
    '#be123c'
];


/*
|--------------------------------------------------------------------------
| CANVAS
|--------------------------------------------------------------------------
*/

function ajustarCanvas() {

    const rect =
        canvas.getBoundingClientRect();

    const size =
        Math.min(
            rect.width,
            rect.height
        );

    const dpr =
        window.devicePixelRatio || 1;

    canvas.width =
        size * dpr;

    canvas.height =
        size * dpr;

    ctx.setTransform(
        dpr,
        0,
        0,
        dpr,
        0,
        0
    );

    dibujarRuleta(
        rotation
    );
}


/*
|--------------------------------------------------------------------------
| DIBUJAR RULETA
|--------------------------------------------------------------------------
*/

function dibujarRuleta(
    angulo
) {

    const rect =
        canvas.getBoundingClientRect();

    const size =
        Math.min(
            rect.width,
            rect.height
        );

    const cx =
        size / 2;

    const cy =
        size / 2;

    const radius =
        size / 2 - 8;

    ctx.clearRect(
        0,
        0,
        size,
        size
    );

    if (
        participantes.length === 0
    ) {
        return;
    }

    const cantidad =
        participantes.length;

    const sector =
        (Math.PI * 2) /
        cantidad;

    ctx.save();

    ctx.translate(
        cx,
        cy
    );

    ctx.rotate(
        angulo
    );

    for (
        let i = 0;
        i < cantidad;
        i++
    ) {

        const inicio =
            i * sector;

        const fin =
            inicio + sector;

        /*
        |--------------------------------------------------------------------------
        | SECTOR
        |--------------------------------------------------------------------------
        */

        ctx.beginPath();

        ctx.moveTo(
            0,
            0
        );

        ctx.arc(
            0,
            0,
            radius,
            inicio,
            fin
        );

        ctx.closePath();

        ctx.fillStyle =
            colores[
                i % colores.length
            ];

        ctx.fill();

        ctx.lineWidth =
            2;

        ctx.strokeStyle =
            'rgba(255,255,255,.8)';

        ctx.stroke();


        /*
        |--------------------------------------------------------------------------
        | NOMBRE
        |--------------------------------------------------------------------------
        |
        | El texto nace desde el centro
        | y se dirige hacia el borde.
        |
        */

        const mitad =
            inicio +
            sector / 2;

        ctx.save();

        ctx.rotate(
            mitad
        );

        /*
        |--------------------------------------------------------------------------
        | TEXTO RADIAL
        |--------------------------------------------------------------------------
        */

        ctx.translate(
            radius * 0.18,
            0
        );

        ctx.rotate(
            Math.PI / 2
        );

        let nombre =
            participantes[i].nombre
            || '';

        /*
        |--------------------------------------------------------------------------
        | Cuando existen muchísimos
        | participantes, reducimos
        | el tamaño del texto.
        |--------------------------------------------------------------------------
        */

        let fontSize;

        if (cantidad > 250) {

            fontSize = 7;

        } else if (cantidad > 150) {

            fontSize = 8;

        } else if (cantidad > 80) {

            fontSize = 9;

        } else if (cantidad > 40) {

            fontSize = 11;

        } else {

            fontSize = 13;
        }

        ctx.font =
            'bold ' +
            fontSize +
            'px Arial';

        ctx.fillStyle =
            '#ffffff';

        ctx.textAlign =
            'left';

        ctx.textBaseline =
            'middle';

        /*
        |--------------------------------------------------------------------------
        | Si el nombre es demasiado largo
        |--------------------------------------------------------------------------
        */

        const maxWidth =
            radius * 0.72;

        while (
            ctx.measureText(nombre).width >
            maxWidth &&
            nombre.length > 12
        ) {

            nombre =
                nombre.substring(
                    0,
                    nombre.length - 1
                );
        }

        if (
            nombre !==
            participantes[i].nombre
        ) {

            nombre +=
                '…';
        }

        ctx.fillText(
            nombre,
            0,
            0
        );

        ctx.restore();

    }

    ctx.restore();


    /*
    |--------------------------------------------------------------------------
    | BORDE
    |--------------------------------------------------------------------------
    */

    ctx.beginPath();

    ctx.arc(
        cx,
        cy,
        radius,
        0,
        Math.PI * 2
    );

    ctx.lineWidth =
        8;

    ctx.strokeStyle =
        '#facc15';

    ctx.stroke();

}


/*
|--------------------------------------------------------------------------
| ANIMACIÓN
|--------------------------------------------------------------------------
*/

function animar(
    desde,
    hasta,
    duracion,
    callback
) {

    const inicio =
        performance.now();

    function frame(
        ahora
    ) {

        const progreso =
            Math.min(
                (ahora - inicio) /
                duracion,
                1
            );

        /*
        |--------------------------------------------------------------------------
        | Easing fuerte para sensación
        | de aceleración/desaceleración.
        |--------------------------------------------------------------------------
        */

        const ease =
            1 -
            Math.pow(
                1 - progreso,
                4
            );

        rotation =
            desde +
            (hasta - desde) *
            ease;

        dibujarRuleta(
            rotation
        );

        if (
            progreso < 1
        ) {

            requestAnimationFrame(
                frame
            );

        } else {

            callback();
        }
    }

    requestAnimationFrame(
        frame
    );
}


/*
|--------------------------------------------------------------------------
| GIRAR
|--------------------------------------------------------------------------
*/

async function girar() {

    if (girando) {
        return;
    }

    if (
        participantes.length === 0
    ) {

        alert(
            'No existen participantes disponibles.'
        );

        return;
    }

    girando = true;

    document
        .getElementById(
            'btnSpin'
        )
        .disabled = true;


    try {

        /*
        |--------------------------------------------------------------------------
        | Preguntamos al servidor
        | quién debe ganar.
        |--------------------------------------------------------------------------
        */

        const respuesta =
            await fetch(
                'sorteo_api.php',
                {

                    method:
                        'POST',

                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded'
                    },

                    body:
                        new URLSearchParams({

                            accion:
                                'seleccionar',

                            sorteo_id:
                                sorteoId

                        })
                }
            );


        const datos =
            await respuesta.json();


        if (
            !datos.ok
        ) {

            throw new Error(
                datos.mensaje
                ||
                'No se pudo realizar el sorteo.'
            );
        }


        ganadorActual =
            datos.ganador;


        const indice =
            participantes.findIndex(
                p =>
                    Number(p.id) ===
                    Number(
                        ganadorActual.id
                    )
            );


        if (
            indice < 0
        ) {

            throw new Error(
                'El ganador no existe en la ruleta.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Cada sector tiene el mismo tamaño.
        |--------------------------------------------------------------------------
        */

        const sector =
            (
                Math.PI * 2
            ) /
            participantes.length;


        /*
        |--------------------------------------------------------------------------
        | Queremos que el sector ganador
        | termine debajo del puntero superior.
        |--------------------------------------------------------------------------
        */

        const anguloCentro =
            (
                indice * sector
            ) +
            (
                sector / 2
            );


        const objetivo =
            (
                Math.PI * 1.5
            ) -
            anguloCentro;


        const vueltas =
            7 +
            Math.floor(
                Math.random() * 3
            );


        let destino =
            rotation +
            (
                Math.PI * 2 *
                vueltas
            );


        const normalizado =
            objetivo -
            (
                destino %
                (Math.PI * 2)
            );


        destino +=
            normalizado;


        const duracion =
            6500;


        const desde =
            rotation;


        animar(
            desde,
            destino,
            duracion,
            () => {

                rotation =
                    destino;

                mostrarResultado(
                    ganadorActual
                );

                girando =
                    false;
            }
        );


    } catch (
        error
    ) {

        alert(
            error.message
        );

        girando =
            false;

        document
            .getElementById(
                'btnSpin'
            )
            .disabled =
            false;
    }

}


/*
|--------------------------------------------------------------------------
| MOSTRAR GANADOR
|--------------------------------------------------------------------------
*/

function mostrarResultado(
    ganador
) {

    document
        .getElementById(
            'ganadorNombre'
        )
        .textContent =
        ganador.nombre;


    document
        .getElementById(
            'ganadorDatos'
        )
        .innerHTML =
        `
            <strong>Código:</strong>
            ${escapeHtml(
                ganador.cod || ''
            )}
            &nbsp;&nbsp;

            <strong>Cédula:</strong>
            ${escapeHtml(
                ganador.cedula || ''
            )}
            <br><br>

            ${escapeHtml(
                ganador.area || ''
            )}
        `;


    document
        .getElementById(
            'resultado'
        )
        .style.display =
        'flex';
}


/*
|--------------------------------------------------------------------------
| CONFIRMAR GANADOR
|--------------------------------------------------------------------------
*/

async function confirmarGanador() {

    if (!ganadorActual) {
        return;
    }

    const respuesta =
        await fetch(
            'sorteo_api.php',
            {

                method:
                    'POST',

                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },

                body:
                    new URLSearchParams({

                        accion:
                            'confirmar',

                        sorteo_id:
                            sorteoId,

                        colaborador_id:
                            ganadorActual.id

                    })
            }
        );


    const datos =
        await respuesta.json();


    if (
        !datos.ok
    ) {

        alert(
            datos.mensaje
            ||
            'No se pudo confirmar.'
        );

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | El ganador queda confirmado.
    |--------------------------------------------------------------------------
    */

    window.location.href =
        'sorteo.php?evento_id=' +
        eventoId;
}


/*
|--------------------------------------------------------------------------
| NO ESTÁ PRESENTE
|--------------------------------------------------------------------------
*/

async function noEstaPresente() {

    if (!ganadorActual) {
        return;
    }

    const confirmar =
        confirm(
            '¿Confirmas que el colaborador ya se retiró o no está presente?'
        );

    if (!confirmar) {
        return;
    }


    const respuesta =
        await fetch(
            'sorteo_api.php',
            {

                method:
                    'POST',

                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },

                body:
                    new URLSearchParams({

                        accion:
                            'no_presente',

                        sorteo_id:
                            sorteoId,

                        colaborador_id:
                            ganadorActual.id

                    })
            }
        );


    const datos =
        await respuesta.json();


    if (
        !datos.ok
    ) {

        alert(
            datos.mensaje
            ||
            'No se pudo registrar.'
        );

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Se quita de la lista actual.
    |--------------------------------------------------------------------------
    */

    const indice =
        participantes.findIndex(
            p =>
                Number(p.id) ===
                Number(
                    ganadorActual.id
                )
        );


    if (
        indice >= 0
    ) {

        participantes.splice(
            indice,
            1
        );
    }


    ganadorActual =
        null;


    document
        .getElementById(
            'resultado'
        )
        .style.display =
        'none';


    document
        .getElementById(
            'contador'
        )
        .textContent =
        participantes.length;


    dibujarRuleta(
        rotation
    );


    document
        .getElementById(
            'btnSpin'
        )
        .disabled =
        false;


    if (
        participantes.length === 0
    ) {

        alert(
            'Ya no quedan participantes disponibles para este premio.'
        );

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Vuelve a girar automáticamente
    | para el MISMO premio.
    |--------------------------------------------------------------------------
    */

    setTimeout(
        () => {

            girar();

        },
        700
    );
}


/*
|--------------------------------------------------------------------------
| CERRAR RESULTADO
|--------------------------------------------------------------------------
*/

function cerrarResultado() {

    document
        .getElementById(
            'resultado'
        )
        .style.display =
        'none';

    document
        .getElementById(
            'btnSpin'
        )
        .disabled =
        false;
}


/*
|--------------------------------------------------------------------------
| ESCAPAR HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(
    texto
) {

    return String(texto || '')
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}


/*
|--------------------------------------------------------------------------
| INICIO
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'resize',
    ajustarCanvas
);

ajustarCanvas();

</script>

</body>

</html>