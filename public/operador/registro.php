<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../app/Database.php';

$db = Database::connection();

/*
|--------------------------------------------------------------------------
| OBTENER EVENTO ACTIVO
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        id,
        nombre,
        descripcion,
        tipo,
        fecha_evento,
        estado
    FROM eventos
    WHERE estado = 'ACTIVO'
    ORDER BY fecha_evento DESC, id DESC
    LIMIT 1
");

$evento = $stmt->fetch();

if (!$evento) {
    die('No existe actualmente un evento activo.');
}

$eventoId = (int)$evento['id'];

/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM evento_colaboradores
    WHERE evento_id = :evento_id
");

$stmt->execute([
    ':evento_id' => $eventoId
]);

$totalColaboradores =
    (int)$stmt->fetchColumn();


$stmt = $db->prepare("
    SELECT COUNT(DISTINCT colaborador_id)
    FROM registros
    WHERE evento_id = :evento_id
      AND tipo_registro = 'ASISTENCIA'
");

$stmt->execute([
    ':evento_id' => $eventoId
]);

$totalAsistentes =
    (int)$stmt->fetchColumn();


$pendientes =
    max(
        0,
        $totalColaboradores -
        $totalAsistentes
    );


$porcentaje =
    $totalColaboradores > 0
        ? round(
            ($totalAsistentes / $totalColaboradores) * 100,
            1
        )
        : 0;

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

    color: #0f172a;
}

.header {

    background: #0f172a;

    color: white;

    padding: 20px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;
}

.header h1 {

    margin: 0;

    font-size: 24px;
}

.header small {

    display: block;

    margin-top: 5px;

    color: #cbd5e1;
}

.contenedor {

    max-width: 1100px;

    margin: 30px auto;

    padding: 0 20px;
}

.estadisticas {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}

.stat {

    background: white;

    border-radius: 12px;

    padding: 20px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.06);
}

.stat .numero {

    font-size: 32px;

    font-weight: bold;

    margin-top: 8px;
}

.stat .titulo {

    color: #64748b;

    font-size: 14px;
}

.principal {

    background: white;

    border-radius: 16px;

    padding: 40px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.08);

    text-align: center;
}

.principal h2 {

    margin-top: 0;

    font-size: 28px;
}

.descripcion {

    color: #64748b;

    margin-bottom: 30px;
}

.formulario {

    max-width: 650px;

    margin: auto;
}

.input {

    width: 100%;

    height: 70px;

    border: 3px solid #cbd5e1;

    border-radius: 12px;

    padding: 0 20px;

    font-size: 30px;

    text-align: center;

    outline: none;
}

.input:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,.12);
}

.botones {

    display: flex;

    gap: 10px;

    margin-top: 15px;
}

.btn {

    border: none;

    border-radius: 10px;

    padding: 15px 25px;

    font-size: 18px;

    cursor: pointer;

    flex: 1;
}

.btn-buscar {

    background: #2563eb;

    color: white;
}

.btn-limpiar {

    background: #e2e8f0;

    color: #0f172a;
}

.resultado {

    margin-top: 30px;

    display: none;

    border-radius: 14px;

    padding: 25px;

    text-align: center;
}

.resultado.exito {

    display: block;

    background: #dcfce7;

    border: 2px solid #22c55e;

    color: #166534;
}

.resultado.error {

    display: block;

    background: #fee2e2;

    border: 2px solid #ef4444;

    color: #991b1b;
}

.resultado.duplicado {

    display: block;

    background: #fef3c7;

    border: 2px solid #f59e0b;

    color: #92400e;
}

.resultado .icono {

    font-size: 55px;

    margin-bottom: 10px;
}

.resultado .nombre {

    font-size: 28px;

    font-weight: bold;

    margin: 10px 0;
}

.resultado .detalle {

    font-size: 17px;

    margin-top: 8px;
}

.cargando {

    display: none;

    margin-top: 20px;

    color: #64748b;

    font-size: 18px;
}

.progreso {

    margin-top: 20px;

    height: 12px;

    background: #e2e8f0;

    border-radius: 20px;

    overflow: hidden;
}

.progreso-barra {

    height: 100%;

    background: #22c55e;

    width: <?= $porcentaje ?>%;
}

@media (max-width: 800px) {

    .estadisticas {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .principal {

        padding: 25px 15px;
    }

    .header {

        padding: 15px;

        display: block;
    }
}

@media (max-width: 500px) {

    .estadisticas {

        grid-template-columns:
            1fr 1fr;
    }

    .stat .numero {

        font-size: 25px;
    }

    .input {

        height: 60px;

        font-size: 24px;
    }

    .botones {

        flex-direction: column;
    }
}

</style>

</head>

<body>

<div class="header">

<div>

<h1>
<?= htmlspecialchars($evento['nombre']) ?>
</h1>

<small>
Control de asistencia
</small>

</div>

<div>

<small>
Evento #<?= $eventoId ?>
</small>

</div>

</div>


<div class="contenedor">


<div class="estadisticas">

<div class="stat">

<div class="titulo">
LISTADO
</div>

<div
    class="numero"
    id="totalColaboradores"
>
<?= number_format($totalColaboradores) ?>
</div>

</div>


<div class="stat">

<div class="titulo">
ASISTENTES
</div>

<div
    class="numero"
    id="totalAsistentes"
>
<?= number_format($totalAsistentes) ?>
</div>

</div>


<div class="stat">

<div class="titulo">
PENDIENTES
</div>

<div
    class="numero"
    id="totalPendientes"
>
<?= number_format($pendientes) ?>
</div>

</div>


<div class="stat">

<div class="titulo">
ASISTENCIA
</div>

<div
    class="numero"
    id="porcentaje"
>
<?= $porcentaje ?>%
</div>

</div>

</div>


<div class="principal">

<h2>
Escanee la credencial
</h2>

<div class="descripcion">

Puede escanear el código del colaborador
o ingresar manualmente el código o cédula.

</div>


<div class="formulario">

<form
    id="formAsistencia"
    autocomplete="off"
>

<input
    type="text"
    id="identificador"
    name="identificador"
    class="input"
    placeholder="Código o cédula"
    inputmode="numeric"
    autofocus
>

<div class="botones">

<button
    type="submit"
    class="btn btn-buscar"
>
REGISTRAR INGRESO
</button>

<button
    type="button"
    class="btn btn-limpiar"
    id="btnLimpiar"
>
LIMPIAR
</button>

</div>

</form>


<div
    class="cargando"
    id="cargando"
>
Verificando...
</div>


<div
    id="resultado"
    class="resultado"
>
</div>


<div class="progreso">

<div
    class="progreso-barra"
    id="progresoBarra"
    style="width: <?= $porcentaje ?>%"
></div>

</div>

</div>

</div>

</div>


<script>

const formulario =
    document.getElementById(
        'formAsistencia'
    );

const input =
    document.getElementById(
        'identificador'
    );

const resultado =
    document.getElementById(
        'resultado'
    );

const cargando =
    document.getElementById(
        'cargando'
    );

const btnLimpiar =
    document.getElementById(
        'btnLimpiar'
    );


/*
|--------------------------------------------------------------------------
| MANTENER FOCO
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'load',
    function () {

        input.focus();

    }
);


/*
|--------------------------------------------------------------------------
| LIMPIAR
|--------------------------------------------------------------------------
*/

function limpiar() {

    input.value = '';

    resultado.className =
        'resultado';

    resultado.innerHTML = '';

    cargando.style.display =
        'none';

    input.focus();
}


btnLimpiar.addEventListener(
    'click',
    limpiar
);


/*
|--------------------------------------------------------------------------
| REGISTRAR
|--------------------------------------------------------------------------
*/

formulario.addEventListener(
    'submit',
    async function (e) {

        e.preventDefault();


        const identificador =
            input.value.trim();


        if (
            identificador === ''
        ) {

            input.focus();

            return;
        }


        cargando.style.display =
            'block';


        resultado.className =
            'resultado';


        resultado.innerHTML =
            '';


        try {

            const datos =
                new URLSearchParams();


            datos.append(
                'evento_id',
                '<?= $eventoId ?>'
            );


            datos.append(
                'identificador',
                identificador
            );


            const respuesta =
                await fetch(
                    'registrar.php',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/x-www-form-urlencoded'
                        },

                        body:
                            datos.toString()
                    }
                );


            const json =
                await respuesta.json();


            cargando.style.display =
                'none';


            mostrarResultado(
                json
            );


            actualizarEstadisticas(
                json
            );


            /*
            |--------------------------------------------------------------------------
            | LIMPIAR AUTOMÁTICAMENTE
            |--------------------------------------------------------------------------
            */

            input.value = '';

            input.focus();


        } catch (error) {

            cargando.style.display =
                'none';


            resultado.className =
                'resultado error';


            resultado.innerHTML = `

                <div class="icono">
                    ❌
                </div>

                <div class="nombre">
                    ERROR DE CONEXIÓN
                </div>

                <div class="detalle">
                    No se pudo comunicar con el servidor.
                </div>

            `;

            input.focus();
        }

    }
);


/*
|--------------------------------------------------------------------------
| MOSTRAR RESULTADO
|--------------------------------------------------------------------------
*/

function mostrarResultado(
    datos
) {

    let clase =
        'resultado error';


    let icono =
        '❌';


    if (
        datos.estado ===
        'OK'
    ) {

        clase =
            'resultado exito';

        icono =
            '✅';

    } else if (
        datos.estado ===
        'DUPLICADO'
    ) {

        clase =
            'resultado duplicado';

        icono =
            '⚠️';
    }


    let html = `

        <div class="icono">
            ${icono}
        </div>

        <div class="nombre">
            ${escapeHtml(
                datos.titulo ?? ''
            )}
        </div>

    `;


    if (
        datos.colaborador
    ) {

        html += `

            <div class="detalle">

                <strong>
                    ${escapeHtml(
                        datos.colaborador.apellidos_nombres
                    )}
                </strong>

            </div>

        `;


        if (
            datos.colaborador.cod
        ) {

            html += `

                <div class="detalle">
                    Código:
                    ${escapeHtml(
                        datos.colaborador.cod
                    )}
                </div>

            `;
        }


        if (
            datos.colaborador.cedula
        ) {

            html += `

                <div class="detalle">
                    Cédula:
                    ${escapeHtml(
                        datos.colaborador.cedula
                    )}
                </div>

            `;
        }


        if (
            datos.colaborador.area
        ) {

            html += `

                <div class="detalle">
                    Área:
                    ${escapeHtml(
                        datos.colaborador.area
                    )}
                </div>

            `;
        }


        if (
            datos.hora
        ) {

            html += `

                <div class="detalle">
                    Hora:
                    <strong>
                        ${escapeHtml(
                            datos.hora
                        )}
                    </strong>
                </div>

            `;
        }
    }


    if (
        datos.mensaje
    ) {

        html += `

            <div class="detalle">

                ${escapeHtml(
                    datos.mensaje
                )}

            </div>

        `;
    }


    resultado.className =
        clase;

    resultado.innerHTML =
        html;
}


/*
|--------------------------------------------------------------------------
| ACTUALIZAR ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

function actualizarEstadisticas(
    datos
) {

    if (
        !datos.estadisticas
    ) {
        return;
    }


    document.getElementById(
        'totalAsistentes'
    ).textContent =
        datos.estadisticas.asistentes;


    document.getElementById(
        'totalPendientes'
    ).textContent =
        datos.estadisticas.pendientes;


    document.getElementById(
        'porcentaje'
    ).textContent =
        datos.estadisticas.porcentaje +
        '%';


    document.getElementById(
        'progresoBarra'
    ).style.width =
        datos.estadisticas.porcentaje +
        '%';
}


/*
|--------------------------------------------------------------------------
| ESCAPAR HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(
    texto
) {

    const div =
        document.createElement(
            'div'
        );

    div.textContent =
        texto ?? '';

    return div.innerHTML;
}


/*
|--------------------------------------------------------------------------
| TECLADO / ESCÁNER
|--------------------------------------------------------------------------
|
| La mayoría de lectores funcionan
| como teclado y envían ENTER.
|
*/

document.addEventListener(
    'click',
    function (e) {

        if (
            !e.target.closest(
                'button'
            )
        ) {

            input.focus();

        }

    }
);

</script>

</body>

</html>