<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;


/*
|--------------------------------------------------------------------------
| FILTRO DE LECTURA POR FILAS
|--------------------------------------------------------------------------
*/

class RowReadFilter implements IReadFilter
{
    private int $startRow = 1;
    private int $endRow = 1;

    public function setRows(
        int $startRow,
        int $endRow
    ): void {
        $this->startRow = $startRow;
        $this->endRow = $endRow;
    }

    public function readCell(
        $columnAddress,
        $row,
        $worksheetName = ''
    ): bool {
        return (
            $row >= $this->startRow &&
            $row <= $this->endRow
        );
    }
}


/*
|--------------------------------------------------------------------------
| VALIDAR MÉTODO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Solicitud no válida.');
}


/*
|--------------------------------------------------------------------------
| IMPORTACIÓN EN SESIÓN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['importacion']) ||
    !is_array($_SESSION['importacion'])
) {
    die('No existe una importación pendiente.');
}


/*
|--------------------------------------------------------------------------
| GUARDAR MAPEO RECIBIDO DESDE mapear.php
|--------------------------------------------------------------------------
*/

$_SESSION['importacion']['mapa'] = [

    'cod' =>
        $_POST['cod'] ?? '',

    'cedula' =>
        $_POST['cedula'] ?? '',

    'apellidos_nombres' =>
        $_POST['apellidos_nombres'] ?? '',

    'area' =>
        $_POST['area'] ?? '',

    'empresa' =>
        $_POST['empresa'] ?? '',

    'estado' =>
        $_POST['estado'] ?? ''
];


$_SESSION['importacion']['adicionales'] =
    isset($_POST['adicionales'])
        && is_array($_POST['adicionales'])
        ? $_POST['adicionales']
        : [];


/*
|--------------------------------------------------------------------------
| RECUPERAR IMPORTACIÓN
|--------------------------------------------------------------------------
*/

$importacion =
    $_SESSION['importacion'];


$eventoId =
    (int)(
        $importacion['evento_id']
        ?? 0
    );


if (!$eventoId) {
    die('Evento no válido.');
}


/*
|--------------------------------------------------------------------------
| ARCHIVO
|--------------------------------------------------------------------------
*/

$archivo =
    $importacion['archivo_temporal']
    ?? '';


if (
    $archivo === '' ||
    !file_exists($archivo)
) {
    die('El archivo temporal no existe.');
}


/*
|--------------------------------------------------------------------------
| MAPA
|--------------------------------------------------------------------------
*/

$mapa =
    $importacion['mapa']
    ?? [];


$adicionales =
    $importacion['adicionales']
    ?? [];


$headers =
    $importacion['headers']
    ?? [];


/*
|--------------------------------------------------------------------------
| VALIDACIONES
|--------------------------------------------------------------------------
*/

if (
    !isset($mapa['cod']) ||
    $mapa['cod'] === ''
) {
    die('No se ha definido la columna COD.');
}


if (
    !isset($mapa['apellidos_nombres']) ||
    $mapa['apellidos_nombres'] === ''
) {
    die(
        'No se ha definido la columna APELLIDOS Y NOMBRES.'
    );
}


/*
|--------------------------------------------------------------------------
| CONVERTIR ÍNDICES
|--------------------------------------------------------------------------
*/

$mapa['cod'] =
    (int)$mapa['cod'];


$mapa['apellidos_nombres'] =
    (int)$mapa['apellidos_nombres'];


if (
    $mapa['cedula'] !== ''
) {
    $mapa['cedula'] =
        (int)$mapa['cedula'];
}


if (
    $mapa['area'] !== ''
) {
    $mapa['area'] =
        (int)$mapa['area'];
}


if (
    $mapa['empresa'] !== ''
) {
    $mapa['empresa'] =
        (int)$mapa['empresa'];
}


if (
    $mapa['estado'] !== ''
) {
    $mapa['estado'] =
        (int)$mapa['estado'];
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
            'Evento no encontrado.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PREPARAR LECTOR
    |--------------------------------------------------------------------------
    */

    $reader =
        IOFactory::createReaderForFile(
            $archivo
        );

    $reader->setReadDataOnly(true);


    /*
    |--------------------------------------------------------------------------
    | HOJAS
    |--------------------------------------------------------------------------
    */

    $hojas =
        $reader->listWorksheetNames(
            $archivo
        );


    if (empty($hojas)) {
        throw new Exception(
            'El archivo no contiene hojas.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | HOJA
    |--------------------------------------------------------------------------
    */

    $sheetName =
        $importacion['hoja']
        ?? $hojas[0];


    if (
        !in_array(
            $sheetName,
            $hojas,
            true
        )
    ) {
        $sheetName =
            $hojas[0];
    }


    $reader->setLoadSheetsOnly([
        $sheetName
    ]);


    /*
    |--------------------------------------------------------------------------
    | OBTENER ÚLTIMA FILA
    |--------------------------------------------------------------------------
    |
    | Solo cargamos temporalmente el archivo
    | para conocer la última fila.
    |
    */

    $tempSpreadsheet =
        $reader->load(
            $archivo
        );


    $tempSheet =
        $tempSpreadsheet
            ->getActiveSheet();


    $highestRow =
        $tempSheet
            ->getHighestDataRow();


    $highestColumn =
        $tempSheet
            ->getHighestDataColumn();


    $tempSpreadsheet
        ->disconnectWorksheets();


    unset(
        $tempSpreadsheet,
        $tempSheet
    );


    /*
    |--------------------------------------------------------------------------
    | SI NO HAY REGISTROS
    |--------------------------------------------------------------------------
    */

    if ($highestRow < 2) {
        throw new Exception(
            'El archivo no contiene registros.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TRANSACCIÓN
    |--------------------------------------------------------------------------
    */

    $db->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | CREAR CAMPOS ADICIONALES
    |--------------------------------------------------------------------------
    */

    $campoIds = [];


    $stmtCampo =
        $db->prepare("
            INSERT INTO evento_campos
            (
                evento_id,
                nombre_original,
                nombre_campo,
                tipo_dato,
                es_requerido,
                es_visible,
                es_busqueda,
                orden
            )
            VALUES
            (
                :evento_id,
                :nombre_original,
                :nombre_campo,
                'TEXTO',
                0,
                1,
                0,
                :orden
            )
        ");


    $nombresUsados = [];


    foreach (
        $adicionales as $indice
    ) {

        $indice =
            (int)$indice;


        if (
            !isset(
                $headers[$indice]
            )
        ) {
            continue;
        }


        $nombreOriginal =
            trim(
                (string)$headers[$indice]
            );


        if (
            $nombreOriginal === ''
        ) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | CREAR NOMBRE INTERNO
        |--------------------------------------------------------------------------
        */

        $nombreCampo =
            mb_strtolower(
                $nombreOriginal,
                'UTF-8'
            );


        $convertido =
            iconv(
                'UTF-8',
                'ASCII//TRANSLIT//IGNORE',
                $nombreCampo
            );


        if ($convertido !== false) {
            $nombreCampo =
                $convertido;
        }


        $nombreCampo =
            preg_replace(
                '/[^a-zA-Z0-9]+/',
                '_',
                $nombreCampo
            );


        $nombreCampo =
            trim(
                $nombreCampo,
                '_'
            );


        if (
            $nombreCampo === ''
        ) {

            $nombreCampo =
                'campo_' .
                ($indice + 1);
        }


        $base =
            $nombreCampo;


        $contador = 2;


        while (
            in_array(
                $nombreCampo,
                $nombresUsados,
                true
            )
        ) {

            $nombreCampo =
                $base .
                '_' .
                $contador;

            $contador++;
        }


        $nombresUsados[] =
            $nombreCampo;


        /*
        |--------------------------------------------------------------------------
        | INSERTAR CAMPO
        |--------------------------------------------------------------------------
        */

        $stmtCampo->execute([

            ':evento_id' =>
                $eventoId,

            ':nombre_original' =>
                $nombreOriginal,

            ':nombre_campo' =>
                $nombreCampo,

            ':orden' =>
                count($campoIds) + 1

        ]);


        $campoIds[] = [

            'id' =>
                (int)$db->lastInsertId(),

            'indice' =>
                $indice
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | INSERT COLABORADOR
    |--------------------------------------------------------------------------
    */

    $stmtColaborador =
        $db->prepare("
            INSERT INTO evento_colaboradores
            (
                evento_id,
                cod,
                cedula,
                apellidos_nombres,
                area,
                empresa,
                estado,
                fila_excel
            )
            VALUES
            (
                :evento_id,
                :cod,
                :cedula,
                :apellidos_nombres,
                :area,
                :empresa,
                :estado,
                :fila_excel
            )
        ");


    /*
    |--------------------------------------------------------------------------
    | INSERT CAMPOS ADICIONALES
    |--------------------------------------------------------------------------
    */

    $stmtValor =
        $db->prepare("
            INSERT INTO colaborador_campos
            (
                colaborador_id,
                campo_id,
                valor_texto
            )
            VALUES
            (
                :colaborador_id,
                :campo_id,
                :valor_texto
            )
        ");


    /*
    |--------------------------------------------------------------------------
    | FILTRO
    |--------------------------------------------------------------------------
    */

    $filter =
        new RowReadFilter();


    /*
    |--------------------------------------------------------------------------
    | CONTADORES
    |--------------------------------------------------------------------------
    */

    $importados = 0;

    $duplicados = 0;

    $vacios = 0;


    /*
    |--------------------------------------------------------------------------
    | DUPLICADOS
    |--------------------------------------------------------------------------
    */

    $codigosProcesados = [];


    /*
    |--------------------------------------------------------------------------
    | PROCESAR POR BLOQUES
    |--------------------------------------------------------------------------
    */

    $blockSize = 500;


    for (
        $inicio = 2;
        $inicio <= $highestRow;
        $inicio += $blockSize
    ) {

        $fin =
            min(
                $inicio +
                $blockSize -
                1,
                $highestRow
            );


        /*
        |--------------------------------------------------------------------------
        | NUEVO LECTOR
        |--------------------------------------------------------------------------
        */

        $reader =
            IOFactory::createReaderForFile(
                $archivo
            );


        $reader->setReadDataOnly(
            true
        );


        $reader->setLoadSheetsOnly([
            $sheetName
        ]);


        $filter->setRows(
            $inicio,
            $fin
        );


        $reader->setReadFilter(
            $filter
        );


        /*
        |--------------------------------------------------------------------------
        | CARGAR BLOQUE
        |--------------------------------------------------------------------------
        */

        $spreadsheet =
            $reader->load(
                $archivo
            );


        $sheet =
            $spreadsheet
                ->getActiveSheet();


        /*
        |--------------------------------------------------------------------------
        | RECORRER BLOQUE
        |--------------------------------------------------------------------------
        */

        for (
            $row = $inicio;
            $row <= $fin;
            $row++
        ) {

            $fila = [];


            /*
            |--------------------------------------------------------------------------
            | LEER COLUMNAS
            |--------------------------------------------------------------------------
            */

            $columnCount =
                count($headers);


            for (
                $column = 1;
                $column <= $columnCount;
                $column++
            ) {

                $letra =
                    Coordinate::stringFromColumnIndex(
                        $column
                    );


                $celda =
                    $sheet->getCell(
                        $letra . $row
                    );


                $valor =
                    $celda->getFormattedValue();


                if (
                    $valor === null
                ) {
                    $valor = '';
                }


                $fila[] =
                    trim(
                        (string)$valor
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | FILA VACÍA
            |--------------------------------------------------------------------------
            */

            $tieneDatos = false;


            foreach (
                $fila as $valor
            ) {

                if (
                    $valor !== ''
                ) {

                    $tieneDatos = true;

                    break;
                }
            }


            if (!$tieneDatos) {

                $vacios++;

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | COD
            |--------------------------------------------------------------------------
            */

            $cod =
                trim(
                    (string)(
                        $fila[
                            $mapa['cod']
                        ]
                        ?? ''
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | NOMBRE
            |--------------------------------------------------------------------------
            */

            $nombre =
                trim(
                    (string)(
                        $fila[
                            $mapa[
                                'apellidos_nombres'
                            ]
                        ]
                        ?? ''
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | VALIDACIÓN
            |--------------------------------------------------------------------------
            */

            if (
                $cod === '' ||
                $nombre === ''
            ) {

                $vacios++;

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | DUPLICADO EN EL MISMO EXCEL
            |--------------------------------------------------------------------------
            */

            if (
                isset(
                    $codigosProcesados[$cod]
                )
            ) {

                $duplicados++;

                continue;
            }


            $codigosProcesados[$cod] =
                true;


            /*
            |--------------------------------------------------------------------------
            | CÉDULA
            |--------------------------------------------------------------------------
            */

            $cedula = null;


            if (
                $mapa['cedula'] !== ''
            ) {

                $cedula =
                    trim(
                        (string)(
                            $fila[
                                $mapa['cedula']
                            ]
                            ?? ''
                        )
                    );


                if (
                    $cedula === ''
                ) {
                    $cedula = null;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | AREA
            |--------------------------------------------------------------------------
            */

            $area = null;


            if (
                $mapa['area'] !== ''
            ) {

                $area =
                    trim(
                        (string)(
                            $fila[
                                $mapa['area']
                            ]
                            ?? ''
                        )
                    );


                if (
                    $area === ''
                ) {
                    $area = null;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */

            $empresa = null;


            if (
                $mapa['empresa'] !== ''
            ) {

                $empresa =
                    trim(
                        (string)(
                            $fila[
                                $mapa['empresa']
                            ]
                            ?? ''
                        )
                    );


                if (
                    $empresa === ''
                ) {
                    $empresa = null;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | ESTADO
            |--------------------------------------------------------------------------
            */

            $estado = null;


            if (
                $mapa['estado'] !== ''
            ) {

                $estado =
                    trim(
                        (string)(
                            $fila[
                                $mapa['estado']
                            ]
                            ?? ''
                        )
                    );


                if (
                    $estado === ''
                ) {
                    $estado = null;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | INSERTAR COLABORADOR
            |--------------------------------------------------------------------------
            */

            try {

                $stmtColaborador->execute([

                    ':evento_id' =>
                        $eventoId,

                    ':cod' =>
                        $cod,

                    ':cedula' =>
                        $cedula,

                    ':apellidos_nombres' =>
                        $nombre,

                    ':area' =>
                        $area,

                    ':empresa' =>
                        $empresa,

                    ':estado' =>
                        $estado,

                    ':fila_excel' =>
                        $row

                ]);

            } catch (
                PDOException $e
            ) {

                /*
                |--------------------------------------------------------------------------
                | DUPLICADO
                |--------------------------------------------------------------------------
                */

                if (
                    str_contains(
                        strtolower(
                            $e->getMessage()
                        ),
                        'duplicate'
                    )
                ) {

                    $duplicados++;

                    continue;
                }


                throw $e;
            }


            /*
            |--------------------------------------------------------------------------
            | ID COLABORADOR
            |--------------------------------------------------------------------------
            */

            $colaboradorId =
                (int)$db->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | CAMPOS ADICIONALES
            |--------------------------------------------------------------------------
            */

            foreach (
                $campoIds as $campo
            ) {

                $valor =
                    $fila[
                        $campo['indice']
                    ]
                    ?? '';


                $valor =
                    trim(
                        (string)$valor
                    );


                /*
                |----------------------------------------------------------------------
                | No guardar valores vacíos
                |----------------------------------------------------------------------
                */

                if (
                    $valor === ''
                ) {
                    continue;
                }


                $stmtValor->execute([

                    ':colaborador_id' =>
                        $colaboradorId,

                    ':campo_id' =>
                        $campo['id'],

                    ':valor_texto' =>
                        $valor

                ]);
            }


            $importados++;
        }


        /*
        |--------------------------------------------------------------------------
        | LIBERAR BLOQUE
        |--------------------------------------------------------------------------
        */

        $spreadsheet
            ->disconnectWorksheets();


        unset(
            $spreadsheet,
            $sheet,
            $reader
        );


        /*
        |--------------------------------------------------------------------------
        | LIMPIAR MEMORIA
        |--------------------------------------------------------------------------
        */

        gc_collect_cycles();
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $db->commit();


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR ARCHIVO TEMPORAL
    |--------------------------------------------------------------------------
    */

    if (
        file_exists($archivo)
    ) {

        unlink($archivo);
    }


    /*
    |--------------------------------------------------------------------------
    | LIMPIAR SESIÓN
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION['importacion']
    );


} catch (
    Throwable $e
) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if (
        isset($db) &&
        $db->inTransaction()
    ) {

        $db->rollBack();
    }


    http_response_code(500);


    echo '<!DOCTYPE html>';

    echo '<html lang="es">';

    echo '<head>';

    echo '<meta charset="UTF-8">';

    echo '<title>Error de importación</title>';

    echo '</head>';

    echo '<body style="
        font-family:Arial;
        padding:40px;
        background:#f3f4f6;
    ">';

    echo '<div style="
        max-width:800px;
        margin:auto;
        background:white;
        padding:30px;
        border-radius:10px;
    ">';

    echo '<h2 style="color:#dc2626;">
        Error durante la importación
    </h2>';


    echo '<p>';

    echo htmlspecialchars(
        $e->getMessage()
    );

    echo '</p>';


    echo '<p>';

    echo '<strong>Archivo:</strong> ';

    echo htmlspecialchars(
        basename($archivo)
    );

    echo '</p>';


    echo '<p>';

    echo '<strong>Fila aproximada:</strong> ';

    echo htmlspecialchars(
        (string)($row ?? '-')
    );

    echo '</p>';


    echo '<p>';

    echo '<a href="javascript:history.back()">
        ← Volver
    </a>';

    echo '</p>';


    echo '</div>';

    echo '</body>';

    echo '</html>';

    exit;
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<title>
Importación completada
</title>

<style>

body {

    margin:0;

    padding:40px;

    background:#f3f4f6;

    font-family:Arial,sans-serif;
}

.card {

    max-width:650px;

    margin:auto;

    background:white;

    padding:40px;

    border-radius:12px;

    text-align:center;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,.08);
}

.ok {

    font-size:60px;

    color:#16a34a;
}

.numero {

    font-size:42px;

    font-weight:bold;

    margin:20px;
}

.detalle {

    background:#f8fafc;

    padding:15px;

    margin:15px 0;

    border-radius:8px;
}

.btn {

    display:inline-block;

    margin-top:20px;

    padding:12px 22px;

    background:#2563eb;

    color:white;

    text-decoration:none;

    border-radius:7px;
}

</style>

</head>

<body>

<div class="card">

<div class="ok">
✓
</div>

<h1>
Importación completada
</h1>

<div class="numero">
<?= number_format($importados) ?>
</div>

<p>
colaboradores importados
</p>


<?php if ($duplicados > 0): ?>

<div class="detalle">

<strong>
<?= number_format($duplicados) ?>
</strong>

registros duplicados omitidos.

</div>

<?php endif; ?>


<?php if ($vacios > 0): ?>

<div class="detalle">

<strong>
<?= number_format($vacios) ?>
</strong>

filas vacías o incompletas ignoradas.

</div>

<?php endif; ?>


<div class="detalle">

Evento:

<br><br>

<strong>

<?= htmlspecialchars(
    $evento['nombre']
) ?>

</strong>

</div>


<a
    class="btn"
    href="../evento.php?id=<?= $eventoId ?>"
>
Volver al evento
</a>

</div>

</body>

</html>