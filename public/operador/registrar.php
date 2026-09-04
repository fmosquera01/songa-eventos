<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';

exigirAdminOOperador();

header('Content-Type: application/json; charset=utf-8');

function respuesta(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta(['estado' => 'ERROR', 'titulo' => 'Solicitud no válida', 'mensaje' => 'La solicitud debe realizarse mediante POST.'], 405);
}

$identificador = trim((string)($_POST['identificador'] ?? ''));

// Algunos lectores de tarjetas envían el código con un prefijo "$".
// La base de datos almacena únicamente el código, por lo que se elimina
// uno o varios "$" iniciales antes de realizar la búsqueda.
$identificador = ltrim($identificador, '$');
$identificador = trim($identificador);

if ($identificador === '') {
    respuesta(['estado' => 'ERROR', 'titulo' => 'Dato vacío', 'mensaje' => 'Ingrese un código o cédula.'], 400);
}

$db = Database::connection();

try {
    $stmt = $db->query("SELECT id, nombre, estado, validar_estado FROM eventos WHERE estado = 'ACTIVO' ORDER BY id ASC");
    $eventosActivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($eventosActivos) === 0) {
        respuesta(['estado' => 'ERROR', 'titulo' => 'SIN EVENTO ACTIVO', 'mensaje' => 'No existe actualmente un evento activo para registrar asistencia.'], 409);
    }
    if (count($eventosActivos) > 1) {
        respuesta(['estado' => 'ERROR', 'titulo' => 'CONFIGURACIÓN INVÁLIDA', 'mensaje' => 'Existe más de un evento ACTIVO. Debe quedar solamente uno activo.'], 409);
    }

    $evento = $eventosActivos[0];
    $eventoId = (int)$evento['id'];

    $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = :id AND activo = 1 LIMIT 1");
    $stmt->execute([':id' => $usuarioId]);
    $usuarioId = (int)$stmt->fetchColumn();

    if ($usuarioId <= 0) {
        respuesta(['estado' => 'ERROR', 'titulo' => 'SESIÓN NO VÁLIDA', 'mensaje' => 'El usuario de la sesión no existe o está inactivo.'], 401);
    }

    $stmt = $db->prepare("SELECT id, evento_id, cod, cedula, apellidos_nombres, area, empresa, estado FROM evento_colaboradores WHERE evento_id = :evento_id AND (cod = :identificador_cod OR cedula = :identificador_cedula) LIMIT 1");
    $stmt->execute([':evento_id' => $eventoId, ':identificador_cod' => $identificador, ':identificador_cedula' => $identificador]);
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$colaborador) {
        respuesta(['estado' => 'ERROR', 'titulo' => 'NO AUTORIZADO', 'mensaje' => 'La persona no se encuentra en el listado de este evento.']);
    }

    $colaboradorId = (int)$colaborador['id'];

    if ((bool)$evento['validar_estado'] && $colaborador['estado'] !== null && trim((string)$colaborador['estado']) !== '') {
        $estado = mb_strtoupper(trim((string)$colaborador['estado']), 'UTF-8');
        $estadosInactivos = ['INACTIVO', 'INACTIVA', 'BAJA', 'CESADO', 'CESADA', 'RETIRADO', 'RETIRADA', 'NO ACTIVO', 'NO ACTIVA'];
        if (in_array($estado, $estadosInactivos, true)) {
            respuesta(['estado' => 'ERROR', 'titulo' => 'COLABORADOR INACTIVO', 'mensaje' => 'El colaborador figura como inactivo en el listado.', 'colaborador' => ['cod' => $colaborador['cod'], 'cedula' => $colaborador['cedula'], 'apellidos_nombres' => $colaborador['apellidos_nombres'], 'area' => $colaborador['area']]]);
        }
    }

    $stmt = $db->prepare("SELECT id, fecha_hora, metodo FROM registros WHERE evento_id = :evento_id AND colaborador_id = :colaborador_id AND tipo_registro = 'ASISTENCIA' ORDER BY fecha_hora ASC LIMIT 1");
    $stmt->execute([':evento_id' => $eventoId, ':colaborador_id' => $colaboradorId]);
    $registroAnterior = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registroAnterior) {
        $fecha = (string)$registroAnterior['fecha_hora'];
        $timestamp = strtotime($fecha);
        $hora = $timestamp ? date('d/m/Y H:i:s', $timestamp) : $fecha;
        respuesta(['estado' => 'DUPLICADO', 'titulo' => 'YA REGISTRÓ SU INGRESO', 'mensaje' => 'Este colaborador ya tiene registrada su asistencia.', 'hora' => $hora, 'colaborador' => ['cod' => $colaborador['cod'], 'cedula' => $colaborador['cedula'], 'apellidos_nombres' => $colaborador['apellidos_nombres'], 'area' => $colaborador['area'], 'empresa' => $colaborador['empresa']]]);
    }

    $metodo = 'MANUAL';
    if ($identificador === (string)$colaborador['cod']) {
        $metodo = 'CODIGO';
    } elseif ($colaborador['cedula'] !== null && $identificador === (string)$colaborador['cedula']) {
        $metodo = 'CEDULA';
    }

    $dispositivo = substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 150);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $db->prepare("INSERT INTO registros (evento_id, colaborador_id, tipo_registro, fecha_hora, usuario_id, metodo, dispositivo, ip, observacion) VALUES (:evento_id, :colaborador_id, 'ASISTENCIA', NOW(), :usuario_id, :metodo, :dispositivo, :ip, NULL)");
    $stmt->execute([':evento_id' => $eventoId, ':colaborador_id' => $colaboradorId, ':usuario_id' => $usuarioId, ':metodo' => $metodo, ':dispositivo' => $dispositivo, ':ip' => $ip]);

    $stmt = $db->prepare("SELECT COUNT(*) FROM evento_colaboradores WHERE evento_id = :evento_id");
    $stmt->execute([':evento_id' => $eventoId]);
    $total = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(DISTINCT colaborador_id) FROM registros WHERE evento_id = :evento_id AND tipo_registro = 'ASISTENCIA'");
    $stmt->execute([':evento_id' => $eventoId]);
    $asistentes = (int)$stmt->fetchColumn();
    $porcentaje = $total > 0 ? round(($asistentes / $total) * 100, 1) : 0;

    respuesta(['estado' => 'OK', 'titulo' => 'INGRESO REGISTRADO', 'mensaje' => 'La asistencia fue registrada correctamente.', 'hora' => date('d/m/Y H:i:s'), 'evento' => ['id' => $eventoId, 'nombre' => $evento['nombre']], 'colaborador' => ['cod' => $colaborador['cod'], 'cedula' => $colaborador['cedula'], 'apellidos_nombres' => $colaborador['apellidos_nombres'], 'area' => $colaborador['area'], 'empresa' => $colaborador['empresa']], 'estadisticas' => ['total' => $total, 'asistentes' => $asistentes, 'pendientes' => max(0, $total - $asistentes), 'porcentaje' => $porcentaje]]);
} catch (Throwable $e) {
    respuesta(['estado' => 'ERROR', 'titulo' => 'ERROR DEL SERVIDOR', 'mensaje' => $e->getMessage()], 500);
}
