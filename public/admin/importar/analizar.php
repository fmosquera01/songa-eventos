<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Auth.php';
require_once __DIR__ . '/../../../app/Database.php';

exigirAdmin();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$eventoId = filter_input(INPUT_POST, 'evento_id', FILTER_VALIDATE_INT);
if (!$eventoId) die('Evento no válido.');

$archivo = $_FILES['archivo'] ?? null;
if (!$archivo) die('No se recibió ningún archivo.');
if ($archivo['error'] !== UPLOAD_ERR_OK) die('Error al subir el archivo. Código: ' . $archivo['error']);

$db = Database::connection();
$stmt = $db->prepare('SELECT id, nombre, estado FROM eventos WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $eventoId]);
$evento = $stmt->fetch();
if (!$evento) die('Evento no encontrado.');
if (!in_array($evento['estado'], ['BORRADOR', 'ACTIVO'], true)) {
    http_response_code(403);
    die('El evento no permite importar colaboradores porque está en estado ' . htmlspecialchars($evento['estado']) . '.');
}

$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) die('Formato no permitido. Utilice XLSX, XLS o CSV.');

if (isset($_SESSION['importacion'])) {
    $anterior = $_SESSION['importacion'];
    if (!empty($anterior['archivo_temporal']) && file_exists($anterior['archivo_temporal'])) @unlink($anterior['archivo_temporal']);
    unset($_SESSION['importacion']);
}

$directorio = __DIR__ . '/../../../storage/importaciones';
if (!is_dir($directorio) && !mkdir($directorio, 0777, true) && !is_dir($directorio)) die('No se pudo crear el directorio temporal.');

$rutaTemporal = $directorio . '/' . uniqid('import_', true) . '.' . $extension;
if (!move_uploaded_file($archivo['tmp_name'], $rutaTemporal)) die('No se pudo guardar el archivo temporal.');

try {
    $reader = IOFactory::createReaderForFile($rutaTemporal);
    $reader->setReadDataOnly(true);
    $hojas = $reader->listWorksheetNames($rutaTemporal);
    if (empty($hojas)) throw new Exception('El archivo no contiene hojas.');
    $reader->setLoadSheetsOnly([$hojas[0]]);
    $spreadsheet = $reader->load($rutaTemporal);
    $sheet = $spreadsheet->getActiveSheet();
    $highestColumn = $sheet->getHighestDataColumn();
    $columnCount = Coordinate::columnIndexFromString($highestColumn);

    $headers = [];
    for ($column = 1; $column <= $columnCount; $column++) {
        $letra = Coordinate::stringFromColumnIndex($column);
        $headers[] = trim((string)$sheet->getCell($letra . '1')->getFormattedValue());
    }
    while (!empty($headers) && trim((string)end($headers)) === '') array_pop($headers);
    $columnCount = count($headers);
    if ($columnCount === 0) throw new Exception('No se encontraron encabezados en la primera fila.');

    $highestRow = $sheet->getHighestDataRow();
    while ($highestRow > 1) {
        $tieneDatos = false;
        for ($column = 1; $column <= $columnCount; $column++) {
            $letra = Coordinate::stringFromColumnIndex($column);
            if (trim((string)$sheet->getCell($letra . $highestRow)->getFormattedValue()) !== '') { $tieneDatos = true; break; }
        }
        if ($tieneDatos) break;
        $highestRow--;
    }
    $totalFilas = max(0, $highestRow - 1);

    $preview = [];
    $ultimaFilaPreview = min($highestRow, 11);
    for ($row = 2; $row <= $ultimaFilaPreview; $row++) {
        $fila = [];
        $tieneDatos = false;
        for ($column = 1; $column <= $columnCount; $column++) {
            $letra = Coordinate::stringFromColumnIndex($column);
            $valor = trim((string)$sheet->getCell($letra . $row)->getFormattedValue());
            if ($valor !== '') $tieneDatos = true;
            $fila[] = $valor;
        }
        if ($tieneDatos) $preview[] = $fila;
    }

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet, $sheet, $reader);

    $_SESSION['importacion'] = [
        'evento_id' => $eventoId,
        'archivo_temporal' => $rutaTemporal,
        'archivo_original' => $archivo['name'],
        'headers' => $headers,
        'preview' => $preview,
        'total_filas' => $totalFilas,
        'columnas' => $columnCount
    ];
} catch (Throwable $e) {
    if (file_exists($rutaTemporal)) @unlink($rutaTemporal);
    http_response_code(500);
    die('Error leyendo el archivo: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Analizar archivo</title>
<style>
body{margin:0;padding:30px;background:#f3f4f6;font-family:Arial,sans-serif}.container{max-width:1100px;margin:auto}.card{background:white;padding:25px;margin-bottom:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08)}.info{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px}.info-item{background:#f8fafc;padding:15px;border-radius:8px}.numero{font-size:24px;font-weight:bold;margin-top:8px}.table-container{overflow:auto}table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:9px;border-bottom:1px solid #ddd;text-align:left;white-space:nowrap}th{background:#f1f5f9}.btn{display:inline-block;padding:12px 20px;border-radius:7px;text-decoration:none;border:0;cursor:pointer}.primary{background:#2563eb;color:white}.secondary{background:#6b7280;color:white}
</style>
</head>
<body>
<div class="container">
<div class="card"><h1>Analizar archivo</h1><div class="info">
<div class="info-item">Archivo<div class="numero"><?= htmlspecialchars($archivo['name']) ?></div></div>
<div class="info-item">Columnas<div class="numero"><?= $columnCount ?></div></div>
<div class="info-item">Registros<div class="numero"><?= $totalFilas ?></div></div>
<div class="info-item">Hoja<div class="numero"><?= htmlspecialchars($hojas[0]) ?></div></div>
</div></div>
<div class="card"><h2>Columnas encontradas</h2><div class="table-container"><table><thead><tr><th>#</th><th>Nombre</th></tr></thead><tbody>
<?php foreach ($headers as $index => $header): ?><tr><td><?= $index + 1 ?></td><td><?= $header !== '' ? htmlspecialchars($header) : '<em>Vacío</em>' ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="card"><h2>Vista previa</h2><div class="table-container"><table><thead><tr><?php foreach ($headers as $header): ?><th><?= htmlspecialchars($header) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($preview as $fila): ?><tr><?php foreach ($fila as $valor): ?><td><?= htmlspecialchars($valor) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="card"><a href="../evento.php?id=<?= $eventoId ?>" class="btn secondary">Cancelar</a><form method="POST" action="mapear.php" style="display:inline"><input type="hidden" name="evento_id" value="<?= $eventoId ?>"><button type="submit" class="btn primary">Continuar con el mapeo →</button></form></div>
</div>
</body>
</html>