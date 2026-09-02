<?php

declare(strict_types=1);

session_start();

$importacion = $_SESSION['importacion'] ?? null;

if (!$importacion) {
    die('No existe una importación activa.');
}

$eventoId = (int)($importacion['evento_id'] ?? 0);

if (!$eventoId) {
    die('Evento no válido.');
}

$headers = $importacion['headers'] ?? [];

if (!is_array($headers) || empty($headers)) {
    die('No se encontraron columnas.');
}


/*
|--------------------------------------------------------------------------
| NORMALIZAR TEXTO
|--------------------------------------------------------------------------
*/

function normalizarTexto(string $texto): string
{
    $texto = trim($texto);

    $texto = mb_strtolower($texto, 'UTF-8');

    $texto = strtr($texto, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n'
    ]);

    return preg_replace('/\s+/', ' ', $texto);
}


/*
|--------------------------------------------------------------------------
| DETECTAR COLUMNA
|--------------------------------------------------------------------------
*/

function detectarColumna(
    array $headers,
    array $posibles,
    array $excluir = []
): ?int {

    foreach ($headers as $indice => $header) {

        if (in_array($indice, $excluir, true)) {
            continue;
        }

        $actual = normalizarTexto((string)$header);

        foreach ($posibles as $posible) {

            if (
                $actual === normalizarTexto($posible)
            ) {
                return $indice;
            }
        }
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| DETECCIÓN
|--------------------------------------------------------------------------
*/

$cod = detectarColumna(
    $headers,
    [
        'COD',
        'Código',
        'Codigo',
        'Código empleado',
        'Codigo empleado'
    ]
);

$cedula = detectarColumna(
    $headers,
    [
        'CEDULA',
        'Cédula',
        'Cedula',
        'Documento',
        'Identificación',
        'Identificacion'
    ]
);

$nombre = detectarColumna(
    $headers,
    [
        'APELLIDOS Y NOMBRES',
        'Apellidos y nombres',
        'Nombre completo',
        'Colaborador',
        'Empleado'
    ]
);

$area = detectarColumna(
    $headers,
    [
        'AREA',
        'Área',
        'Area',
        'Departamento'
    ]
);

$empresa = detectarColumna(
    $headers,
    [
        'EMPRESA',
        'Empresa'
    ]
);

$estado = detectarColumna(
    $headers,
    [
        'ESTADO',
        'Estado',
        'Status',
        'Estatus'
    ]
);


/*
|--------------------------------------------------------------------------
| CAMPOS ESTÁNDAR
|--------------------------------------------------------------------------
*/

$estandar = array_filter([
    $cod,
    $cedula,
    $nombre,
    $area,
    $empresa,
    $estado
], fn($v) => $v !== null);


/*
|--------------------------------------------------------------------------
| COLUMNAS ADICIONALES
|--------------------------------------------------------------------------
*/

$adicionales = [];

foreach ($headers as $indice => $header) {

    if (in_array($indice, $estandar, true)) {
        continue;
    }

    if (trim((string)$header) === '') {
        continue;
    }

    $adicionales[] = [
        'indice' => $indice,
        'numero' => $indice + 1,
        'nombre' => $header
    ];
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<title>Mapear columnas</title>

<style>

* {
    box-sizing:border-box;
}

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
    border-radius:10px;
    padding:25px;
    margin-bottom:20px;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
}

h1,h2 {
    margin-top:0;
}

.info {
    background:#eff6ff;
    border:1px solid #bfdbfe;
    padding:15px;
    border-radius:8px;
    margin-bottom:20px;
}

.grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
    gap:15px;
}

.campo {
    border:1px solid #ddd;
    border-radius:8px;
    padding:15px;
}

.campo label {
    display:block;
    font-weight:bold;
    margin-bottom:8px;
}

select {
    width:100%;
    padding:10px;
    border:1px solid #ccc;
    border-radius:6px;
    background:white;
}

.adicional {
    padding:10px;
    border-bottom:1px solid #eee;
}

.btn {
    display:inline-block;
    padding:12px 20px;
    border:0;
    border-radius:7px;
    cursor:pointer;
    text-decoration:none;
    font-size:15px;
}

.primary {
    background:#2563eb;
    color:white;
}

.secondary {
    background:#6b7280;
    color:white;
}

.obligatorio {
    color:#dc2626;
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<h1>
Mapear columnas
</h1>

<div class="info">

<strong>Archivo:</strong>
<?= htmlspecialchars(
    $importacion['archivo_original'] ?? ''
) ?>

<br>

<strong>Columnas:</strong>
<?= count($headers) ?>

<br>

<strong>Registros:</strong>
<?= (int)($importacion['total_filas'] ?? 0) ?>

</div>

</div>


<form
    method="POST"
    action="importar.php"
>


<div class="card">

<h2>
Datos estándar
</h2>

<p>
Estas columnas forman parte de la información principal del colaborador.
</p>


<div class="grid">


<!-- COD -->

<div class="campo">

<label>
COD <span class="obligatorio">*</span>
</label>

<select name="cod" required>

<option value="">
-- Seleccionar --
</option>

<?php foreach ($headers as $i => $header): ?>

<option
    value="<?= $i ?>"
    <?= $cod === $i ? 'selected' : '' ?>
>

<?= $i + 1 ?> -
<?= htmlspecialchars((string)$header) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- CEDULA -->

<div class="campo">

<label>
Cédula
</label>

<select name="cedula">

<option value="">
-- No seleccionar --
</option>

<?php foreach ($headers as $i => $header): ?>

<option
    value="<?= $i ?>"
    <?= $cedula === $i ? 'selected' : '' ?>
>

<?= $i + 1 ?> -
<?= htmlspecialchars((string)$header) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- NOMBRE -->

<div class="campo">

<label>
Apellidos y nombres <span class="obligatorio">*</span>
</label>

<select name="apellidos_nombres" required>

<option value="">
-- Seleccionar --
</option>

<?php foreach ($headers as $i => $header): ?>

<option
    value="<?= $i ?>"
    <?= $nombre === $i ? 'selected' : '' ?>
>

<?= $i + 1 ?> -
<?= htmlspecialchars((string)$header) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- AREA -->

<div class="campo">

<label>
Área
</label>

<select name="area">

<option value="">
-- No seleccionar --
</option>

<?php foreach ($headers as $i => $header): ?>

<option
    value="<?= $i ?>"
    <?= $area === $i ? 'selected' : '' ?>
>

<?= $i + 1 ?> -
<?= htmlspecialchars((string)$header) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- EMPRESA -->

<div class="campo">

<label>
Empresa
</label>

<select name="empresa">

<option value="">
-- No seleccionar --
</option>

<?php foreach ($headers as $i => $header): ?>

<option
    value="<?= $i ?>"
    <?= $empresa === $i ? 'selected' : '' ?>
>

<?= $i + 1 ?> -
<?= htmlspecialchars((string)$header) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- ESTADO -->

<div class="campo">

<label>
Estado
</label>

<select name="estado">

<option value="">
-- No seleccionar --
</option>

<?php foreach ($headers as $i => $header): ?>

<option
    value="<?= $i ?>"
    <?= $estado === $i ? 'selected' : '' ?>
>

<?= $i + 1 ?> -
<?= htmlspecialchars((string)$header) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

</div>


<div class="card">

<h2>
Campos adicionales
</h2>

<p>
Seleccione las columnas adicionales que desea conservar.
</p>

<?php if (empty($adicionales)): ?>

<p>
No hay campos adicionales.
</p>

<?php else: ?>

<?php foreach ($adicionales as $campo): ?>

<div class="adicional">

<label>

<input
    type="checkbox"
    name="adicionales[]"
    value="<?= $campo['indice'] ?>"
    checked
>

<?= $campo['numero'] ?> -
<?= htmlspecialchars((string)$campo['nombre']) ?>

</label>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>


<div class="card">

<a
    href="../evento.php?id=<?= $eventoId ?>"
    class="btn secondary"
>
Cancelar
</a>

<button
    type="submit"
    class="btn primary"
>
Importar colaboradores →
</button>

</div>

</form>

</div>

</body>

</html>