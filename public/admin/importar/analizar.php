<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


/*
|--------------------------------------------------------------------------
| Validar evento
|--------------------------------------------------------------------------
*/

$eventoId = filter_input(
    INPUT_POST,
    'evento_id',
    FILTER_VALIDATE_INT
);

if (!$eventoId) {
    die('Evento no válido.');
}


/*
|--------------------------------------------------------------------------
| Validar archivo
|--------------------------------------------------------------------------
*/

if (!isset($_FILES['archivo'])) {
    die('No se recibió ningún archivo.');
}

$archivo = $_FILES['archivo'];

if ($archivo['error'] !== UPLOAD_ERR_OK) {
    die(
        'Error al subir el archivo. Código: ' .
        $archivo['error']
    );
}


/*
|--------------------------------------------------------------------------
| Validar extensión
|--------------------------------------------------------------------------
*/

$extension = strtolower(
    pathinfo(
        $archivo['name'],
        PATHINFO_EXTENSION
    )
);

$permitidas = [
    'xlsx',
    'xls',
    'csv'
];

if (!in_array($extension, $permitidas, true)) {
    die(
        'Formato no permitido. Utilice XLSX, XLS o CSV.'
    );
}


/*
|--------------------------------------------------------------------------
| Limpiar importación anterior
|--------------------------------------------------------------------------
*/

if (
    isset(
        $_SESSION['importacion']
    )
) {

    $anterior =
        $_SESSION['importacion'];

    if (
        isset(
            $anterior['archivo_temporal']
        ) &&
        file_exists(
            $anterior['archivo_temporal']
        )
    ) {

        @unlink(
            $anterior['archivo_temporal']
        );
    }

    unset(
        $_SESSION['importacion']
    );
}


/*
|--------------------------------------------------------------------------
| Directorio temporal
|--------------------------------------------------------------------------
*/

$directorio =
    __DIR__ .
    '/../../../storage/importaciones';

if (!is_dir($directorio)) {

    mkdir(
        $directorio,
        0777,
        true
    );
}


/*
|--------------------------------------------------------------------------
| Guardar archivo
|--------------------------------------------------------------------------
*/

$nombreTemporal =
    uniqid(
        'import_',
        true
    ) .
    '.' .
    $extension;

$rutaTemporal =
    $directorio .
    '/' .
    $nombreTemporal;

if (
    !move_uploaded_file(
        $archivo['tmp_name'],
        $rutaTemporal
    )
) {

    die(
        'No se pudo guardar el archivo temporal.'
    );
}


/*
|--------------------------------------------------------------------------
| Leer Excel
|--------------------------------------------------------------------------
*/

try {

    $reader =
        IOFactory::createReaderForFile(
            $rutaTemporal
        );

    $reader->setReadDataOnly(
        true
    );


    /*
    |--------------------------------------------------------------------------
    | Hojas
    |--------------------------------------------------------------------------
    */

    $hojas =
        $reader->listWorksheetNames(
            $rutaTemporal
        );

    if (empty($hojas)) {

        throw new Exception(
            'El archivo no contiene hojas.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Primera hoja
    |--------------------------------------------------------------------------
    */

    $reader->setLoadSheetsOnly(
        [$hojas[0]]
    );


    $spreadsheet =
        $reader->load(
            $rutaTemporal
        );


    $sheet =
        $spreadsheet->getActiveSheet();


    /*
    |--------------------------------------------------------------------------
    | Última columna REAL
    |--------------------------------------------------------------------------
    */

    $highestColumn =
        $sheet->getHighestDataColumn();


    $columnCount =
        Coordinate::columnIndexFromString(
            $highestColumn
        );


    /*
    |--------------------------------------------------------------------------
    | Leer encabezados
    |--------------------------------------------------------------------------
    */

    $headers = [];


    for (
        $column = 1;
        $column <= $columnCount;
        $column++
    ) {

        $letra =
            Coordinate::stringFromColumnIndex(
                $column
            );


        $valor =
            trim(
                (string)$sheet
                    ->getCell(
                        $letra . '1'
                    )
                    ->getFormattedValue()
            );


        $headers[] =
            $valor;
    }


    /*
    |--------------------------------------------------------------------------
    | Eliminar columnas vacías del final
    |--------------------------------------------------------------------------
    */

    while (
        !empty($headers) &&
        trim(
            (string)end($headers)
        ) === ''
    ) {

        array_pop(
            $headers
        );
    }


    $columnCount =
        count($headers);


    if ($columnCount === 0) {

        throw new Exception(
            'No se encontraron encabezados en la primera fila.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Última fila con datos
    |--------------------------------------------------------------------------
    */

    $highestRow =
        $sheet->getHighestDataRow();


    while (
        $highestRow > 1
    ) {

        $tieneDatos = false;


        for (
            $column = 1;
            $column <= $columnCount;
            $column++
        ) {

            $letra =
                Coordinate::stringFromColumnIndex(
                    $column
                );


            $valor =
                trim(
                    (string)$sheet
                        ->getCell(
                            $letra . $highestRow
                        )
                        ->getFormattedValue()
                );


            if ($valor !== '') {

                $tieneDatos =
                    true;

                break;
            }
        }


        if ($tieneDatos) {
            break;
        }


        $highestRow--;
    }


    $totalFilas =
        max(
            0,
            $highestRow - 1
        );


    /*
    |--------------------------------------------------------------------------
    | Vista previa
    |--------------------------------------------------------------------------
    */

    $preview = [];


    $ultimaFilaPreview =
        min(
            $highestRow,
            11
        );


    for (
        $row = 2;
        $row <= $ultimaFilaPreview;
        $row++
    ) {

        $fila = [];

        $tieneDatos = false;


        for (
            $column = 1;
            $column <= $columnCount;
            $column++
        ) {

            $letra =
                Coordinate::stringFromColumnIndex(
                    $column
                );


            $valor =
                trim(
                    (string)$sheet
                        ->getCell(
                            $letra . $row
                        )
                        ->getFormattedValue()
                );


            if ($valor !== '') {

                $tieneDatos =
                    true;
            }


            $fila[] =
                $valor;
        }


        if ($tieneDatos) {

            $preview[] =
                $fila;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Liberar memoria
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
    | GUARDAR NUEVA IMPORTACIÓN EN SESIÓN
    |--------------------------------------------------------------------------
    */



    $_SESSION['importacion'] = [

        'evento_id' =>
            $eventoId,

        'archivo_temporal' =>
            $rutaTemporal,

        'archivo_original' =>
            $archivo['name'],

        'headers' =>
            $headers,

        'preview' =>
            $preview,

        'total_filas' =>
            $totalFilas,

        'columnas' =>
            $columnCount
    ];


} catch (
    Throwable $e
) {

    if (
        file_exists(
            $rutaTemporal
        )
    ) {

        @unlink(
            $rutaTemporal
        );
    }


    http_response_code(
        500
    );


    die(
        'Error leyendo el archivo: ' .
        htmlspecialchars(
            $e->getMessage()
        )
    );
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<title>
Analizar archivo
</title>

<style>

body {
    margin:0;
    padding:30px;
    background:#f3f4f6;
    font-family:Arial,sans-serif;
}

.container {
    max-width:1100px;
    margin:auto;
}

.card {
    background:white;
    padding:25px;
    margin-bottom:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
}

.info {
    display:grid;
    grid-template-columns:
        repeat(auto-fit,minmax(180px,1fr));
    gap:15px;
}

.info-item {
    background:#f8fafc;
    padding:15px;
    border-radius:8px;
}

.numero {
    font-size:24px;
    font-weight:bold;
    margin-top:8px;
}

.table-container {
    overflow:auto;
}

table {
    width:100%;
    border-collapse:collapse;
    font-size:14px;
}

th,
td {
    padding:9px;
    border-bottom:1px solid #ddd;
    text-align:left;
    white-space:nowrap;
}

th {
    background:#f1f5f9;
}

.btn {
    display:inline-block;
    padding:12px 20px;
    border-radius:7px;
    text-decoration:none;
    border:0;
    cursor:pointer;
}

.primary {
    background:#2563eb;
    color:white;
}

.secondary {
    background:#6b7280;
    color:white;
}

</style>

</head>

<body>

<div class="container">


<div class="card">

<h1>
Analizar archivo
</h1>


<div class="info">


<div class="info-item">

Archivo

<div class="numero">

<?= htmlspecialchars(
    $archivo['name']
) ?>

</div>

</div>


<div class="info-item">

Columnas

<div class="numero">

<?= $columnCount ?>

</div>

</div>


<div class="info-item">

Registros

<div class="numero">

<?= $totalFilas ?>

</div>

</div>


<div class="info-item">

Hoja

<div class="numero">

<?= htmlspecialchars(
    $hojas[0]
) ?>

</div>

</div>


</div>

</div>


<div class="card">

<h2>
Columnas encontradas
</h2>


<div class="table-container">

<table>

<thead>

<tr>

<th>
#
</th>

<th>
Nombre
</th>

</tr>

</thead>


<tbody>

<?php foreach (
    $headers as $index => $header
): ?>

<tr>

<td>
<?= $index + 1 ?>
</td>

<td>

<?php if (
    $header !== ''
): ?>

<?= htmlspecialchars(
    $header
) ?>

<?php else: ?>

<em>
Vacío
</em>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>


<div class="card">

<h2>
Vista previa
</h2>


<div class="table-container">

<table>

<thead>

<tr>

<?php foreach (
    $headers as $header
): ?>

<th>

<?= htmlspecialchars(
    $header
) ?>

</th>

<?php endforeach; ?>

</tr>

</thead>


<tbody>

<?php foreach (
    $preview as $fila
): ?>

<tr>

<?php foreach (
    $fila as $valor
): ?>

<td>

<?= htmlspecialchars(
    $valor
) ?>

</td>

<?php endforeach; ?>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>


<div class="card">

<a
    href="../evento.php?id=<?= $eventoId ?>"
    class="btn secondary"
>
Cancelar
</a>


<form
    method="POST"
    action="mapear.php"
    style="display:inline"
>

<input
    type="hidden"
    name="evento_id"
    value="<?= $eventoId ?>"
>


<button
    type="submit"
    class="btn primary"
>
Continuar con el mapeo →
</button>

</form>

</div>


</div>

</body>

</html>