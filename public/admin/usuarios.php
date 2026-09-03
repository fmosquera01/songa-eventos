<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';
require_once __DIR__ . '/../../app/Auth.php';
exigirAdmin();

$db = Database::connection();
$mensaje = trim((string)($_GET['ok'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));

$stmt = $db->query("SELECT id, nombre, usuario_login, rol, activo, creado_en FROM usuarios ORDER BY id ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuarios - Songa Event Control</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937}.navbar{background:#1f2937;color:#fff;padding:16px 30px;display:flex;justify-content:space-between;align-items:center}.navbar h1{margin:0;font-size:20px}.container{max-width:1100px;margin:30px auto;padding:0 20px}.card{background:#fff;border-radius:10px;padding:22px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.08)}h2{margin-top:0}.form-grid{display:grid;grid-template-columns:2fr 1.5fr 1fr 1fr;gap:12px}.campo label{display:block;font-size:13px;font-weight:bold;margin-bottom:6px}.campo input,.campo select{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:7px}.btn{display:inline-block;padding:10px 15px;border:0;border-radius:7px;text-decoration:none;cursor:pointer;font-weight:bold}.primary{background:#2563eb;color:#fff}.secondary{background:#6b7280;color:#fff}.danger{background:#dc2626;color:#fff}.acciones{display:flex;gap:6px;align-items:end}.mensaje{padding:12px;border-radius:7px;margin-bottom:15px;background:#dcfce7;color:#166534}.error{padding:12px;border-radius:7px;margin-bottom:15px;background:#fee2e2;color:#991b1b}table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #e5e7eb;text-align:left}.badge{padding:4px 8px;border-radius:15px;font-size:12px;font-weight:bold}.admin{background:#dbeafe;color:#1e40af}.operador{background:#e5e7eb;color:#374151}.activo{background:#dcfce7;color:#166534}.inactivo{background:#fee2e2;color:#991b1b}@media(max-width:800px){.form-grid{grid-template-columns:1fr}.acciones{align-items:stretch}}
</style>
</head>
<body>
<div class="navbar"><h1>👤 Administración de usuarios</h1><a class="btn secondary" href="eventos.php">← Eventos</a></div>
<div class="container">
<div class="card">
<h2>Crear usuario</h2>
<?php if($mensaje!==''): ?><div class="mensaje">✓ <?=htmlspecialchars($mensaje,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
<?php if($error!==''): ?><div class="error">⚠️ <?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
<form method="post" action="usuarios_guardar.php">
<div class="form-grid">
<div class="campo"><label>Nombre</label><input name="nombre" maxlength="150" required></div>
<div class="campo"><label>Usuario</label><input name="usuario_login" maxlength="100" required autocomplete="off"></div>
<div class="campo"><label>Rol</label><select name="rol"><option value="OPERADOR">OPERADOR</option><option value="ADMIN">ADMIN</option></select></div>
<div class="campo"><label>Contraseña</label><input name="password" type="password" minlength="8" required autocomplete="new-password"></div>
</div>
<br><button class="btn primary" type="submit">Crear usuario</button>
</form>
</div>
<div class="card"><h2>Usuarios registrados</h2><div style="overflow-x:auto"><table><thead><tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($usuarios as $u): ?><tr><td><?= (int)$u['id'] ?></td><td><?=htmlspecialchars((string)$u['nombre'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars((string)$u['usuario_login'],ENT_QUOTES,'UTF-8')?></td><td><span class="badge <?=strtolower((string)$u['rol'])?>"><?=htmlspecialchars((string)$u['rol'])?></span></td><td><span class="badge <?=$u['activo']?'activo':'inactivo'?>"><?=$u['activo']?'ACTIVO':'INACTIVO'?></span></td><td><a class="btn <?=$u['activo']?'danger':'primary'?>" href="usuarios_estado.php?id=<?= (int)$u['id'] ?>&activo=<?=$u['activo']?'0':'1'?>" onclick="return confirm('¿Confirmar cambio de estado?')"><?=$u['activo']?'Desactivar':'Activar'?></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</div>
</body></html>
