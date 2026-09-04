<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';

exigirAdminOOperador();

$db = Database::connection();
$stmt = $db->query("SELECT id, nombre, fecha_evento FROM eventos WHERE estado = 'ACTIVO' ORDER BY id ASC LIMIT 1");
$evento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evento) {
    http_response_code(409);
    ?>
    <!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sin evento activo</title><style>body{font-family:Arial;background:#f1f5f9;display:grid;place-items:center;min-height:100vh;margin:0}.card{background:#fff;padding:30px;border-radius:18px;text-align:center;max-width:420px;box-shadow:0 8px 30px #0001}h1{font-size:24px}</style></head><body><div class="card"><h1>Sin evento activo</h1><p>No existe actualmente un evento ACTIVO para registrar asistencia.</p></div></body></html>
    <?php
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="css/app.css">
<title>Control de asistencia</title>
</head>
<body>
<header class="topbar">
  <div><div class="brand">Songa</div><div class="event-name"><?= htmlspecialchars((string)$evento['nombre']) ?></div></div>
  <div class="top-actions"><div class="status-dot" id="connectionDot" title="Conectado"></div><button class="install-btn" id="btnInstall" type="button" hidden>INSTALAR</button></div>
</header>

<main class="app">
  <section class="stats">
    <div><span>Asistentes</span><strong id="asistentes">—</strong></div>
    <div><span>Último registro</span><strong id="ultimaHora">—</strong></div>
  </section>

  <section class="scanner-card">
    <div class="scanner-title">Escanear credencial</div>
    <div class="camera-wrap" id="cameraWrap">
      <video id="video" playsinline muted></video>
      <div class="scan-frame"><i></i></div>
      <div class="camera-message" id="cameraMessage">Pulsa «Activar cámara»</div>
    </div>
    <button class="primary" id="btnCamera">ACTIVAR CÁMARA</button>

    <div class="or"><span>o ingresar manualmente</span></div>
    <form id="formAsistencia" autocomplete="off">
      <input id="identificador" type="text" inputmode="numeric" placeholder="Código o cédula" autofocus>
      <button class="secondary" type="submit">REGISTRAR</button>
    </form>
  </section>

  <section id="resultado" class="result hidden" aria-live="polite"></section>

  <div class="hint">Después de cada registro queda listo para el siguiente escaneo.</div>
</main>

<script>
window.PWA_CONFIG = {
  registrarUrl: '../operador/registrar.php'
};
</script>
<script src="js/app.js"></script>
</body>
</html>
