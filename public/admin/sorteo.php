<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Database.php';

$db = Database::connection();

$eventoId = (int)(
    $_GET['evento_id']
    ?? $_GET['id']
    ?? 0
);

if ($eventoId <= 0) {
    die('Evento no válido.');
}

$stmt = $db->prepare("
    SELECT
        id,
        nombre,
        descripcion,
        tipo,
        fecha_evento,
        estado
    FROM eventos
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $eventoId
]);

$evento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evento) {
    die('Evento no encontrado.');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sorteo - <?= htmlspecialchars($evento['nombre']) ?></title>

<style>
/* ============================================================
   BASE & PALETA
============================================================ */
* {
    box-sizing: border-box;
}

html, body {
    margin: 0;
    padding: 0;
    min-height: 100%;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: radial-gradient(circle at 50% 0%, #d1e4ff 0%, #b2d0fd 50%, #8eb7f7 100%);
    color: #1a1a1a;
    min-height: 100vh;
}

/* ============================================================
   BARRA SUPERIOR
============================================================ */
.topbar {
    height: 74px;
    padding: 12px 24px;
    background: rgba(255, 255, 255, 0.85);
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    backdrop-filter: blur(12px);
}

.topbar-left {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.topbar h1 {
    margin: 0;
    font-size: 21px;
    font-weight: 800;
    color: #2c3e50;
}

.topbar small {
    color: #64748b;
    font-size: 13px;
}

/* ============================================================
   BOTONES
============================================================ */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 10px;
    padding: 10px 15px;
    text-decoration: none;
    cursor: pointer;
    font-weight: 700;
    transition: transform .15s ease, filter .15s ease, opacity .15s ease;
}

.btn:hover {
    transform: translateY(-1px);
    filter: brightness(1.05);
}

.btn:disabled {
    opacity: .45;
    cursor: not-allowed;
    transform: none;
}

.btn-gray {
    background: #e2e8f0;
    color: #334155;
    border: 1px solid #cbd5e1;
}

.btn-primary {
    background: linear-gradient(135deg, #5b68bb, #3ea0ed);
    color: white;
}

.btn-green {
    background: linear-gradient(135deg, #22c55e, #15803d);
    color: white;
}

.btn-red {
    background: linear-gradient(135deg, #ef4444, #b91c1c);
    color: white;
}

/* ============================================================
   LAYOUT & PANELES
============================================================ */
.layout {
    width: min(1450px, 97vw);
    margin: 18px auto;
    display: grid;
    grid-template-columns: 320px minmax(500px, 1fr);
    gap: 18px;
}

.panel {
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 18px;
    padding: 18px;
    backdrop-filter: blur(14px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
}

.panel h2 {
    margin: 0 0 14px;
    font-size: 18px;
    color: #1e293b;
}

/* ============================================================
   PREMIOS & GANADORES
============================================================ */
.premio-form {
    display: flex;
    gap: 8px;
    margin-bottom: 18px;
}

.premio-form input {
    min-width: 0;
    flex: 1;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    outline: none;
    font-size: 14px;
}

.premio-form input:focus {
    border-color: #5b68bb;
    box-shadow: 0 0 0 3px rgba(91, 104, 187, 0.18);
}

.premios {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.premio {
    padding: 12px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    transition: .2s ease;
}

.premio.activo {
    border-color: #5b68bb;
    background: #f0f3ff;
    box-shadow: inset 0 0 0 1px #5b68bb;
}

.premio-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.premio-nombre {
    font-weight: 800;
    color: #1e293b;
}

.premio-estado {
    font-size: 9px;
    padding: 4px 7px;
    border-radius: 20px;
    background: #e2e8f0;
    color: #475569;
}

.premio button {
    margin-top: 10px;
    width: 100%;
}

.winners {
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid #cbd5e1;
}

.winner-row {
    background: #ffffff;
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 7px;
    border-left: 4px solid #5b68bb;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.winner-row strong {
    display: block;
    color: #1e293b;
}

.winner-row span {
    display: block;
    font-size: 13px;
    color: #475569;
}

.winner-row small {
    display: block;
    margin-top: 4px;
    color: #94a3b8;
}

/* ============================================================
   ESCENARIO Y CONTENEDOR DE RULETA
============================================================ */
.stage {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
}

.prize-title {
    text-align: center;
    width: 100%;
    min-height: 72px;
}

.prize-title .label {
    text-transform: uppercase;
    letter-spacing: 3px;
    font-size: 11px;
    color: #64748b;
    font-weight: 700;
}

.prize-title h2 {
    margin: 7px 0 0;
    font-size: clamp(22px, 3vw, 32px);
    font-weight: 900;
    color: #0f172a;
}

.wheel-box {
    width: min(75vh, 600px, 68vw);
    height: min(75vh, 600px, 68vw);
    position: relative;
    margin: 10px auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

#wheel {
    width: 100%;
    height: 100%;
    display: block;
    border-radius: 50%;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

/* Puntero Derecha Estilo AppSorteos */
.pointer {
    position: absolute;
    top: 50%;
    right: -25px;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-top: 15px solid transparent;
    border-bottom: 15px solid transparent;
    border-right: 40px solid #000000;
    z-index: 20;
    filter: drop-shadow(-2px 4px 6px rgba(0,0,0,0.3));
	border-radius: 50%;
}

.pointer::after {
    content: "";
    position: absolute;
    top: -5px;
    right: -34px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ffffff;
    border: 0px solid #000000;
}

/* Botón Centro */
.center-spin {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: clamp(90px, 16%, 120px);
    height: clamp(90px, 16%, 120px);
    border-radius: 50%;
    border: 6px solid #ffffff;
    background: radial-gradient(circle, #ffffff 0%, #f1f5f9 100%);
    color: #1e293b;
    font-size: clamp(12px, 1.4vw, 16px);
    font-weight: 900;
    cursor: pointer;
    z-index: 15;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    transition: transform .15s ease, filter .15s ease;
}

.center-spin:hover:not(:disabled) {
    transform: translate(-50%, -50%) scale(1.06);
}

.center-spin:disabled {
    cursor: not-allowed;
    opacity: .6;
}

.status {
    min-height: 23px;
    text-align: center;
    color: #475569;
    font-size: 14px;
    margin-top: 6px;
    font-weight: 600;
}

/* ============================================================
   MODAL GANADOR
============================================================ */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 100;
    padding: 20px;
    backdrop-filter: blur(4px);
}

.modal {
    width: min(500px, 96vw);
    background: #ffffff;
    color: #1e293b;
    border-radius: 24px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: popup .3s ease-out;
}

@keyframes popup {
    from { transform: scale(.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.modal .trophy { font-size: 56px; margin-bottom: 8px; }
.modal h2 { margin: 4px 0 8px; font-size: 24px; color: #0f172a; }
.modal .winner-name { font-size: clamp(22px, 4vw, 30px); font-weight: 900; color: #5b68bb; margin: 12px 0; }
.modal .data { color: #475569; line-height: 1.6; font-size: 14px; }
.modal-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 24px; }
.modal-actions button { padding: 14px 10px; font-size: 14px; }

@media (max-width: 900px) {
    .layout { grid-template-columns: 1fr; }
    .stage { order: 1; }
    .layout > aside { order: 2; }
    .pointer {
        top: -20px;
        right: 50%;
        transform: translateX(50%) rotate(90deg);
    }
}
</style>
</head>

<body>

<div class="topbar">
    <div class="topbar-left">
        <h1>🎉 Sorteo del evento</h1>
        <small><?= htmlspecialchars($evento['nombre']) ?></small>
    </div>
    <a href="evento.php?id=<?= $eventoId ?>" class="btn btn-gray">← Volver al evento</a>
</div>

<div class="layout">
    <aside class="panel">
        <h2>🏆 Premios</h2>
        <div class="premio-form">
            <input type="text" id="nombrePremio" placeholder="Ej.: Canasta navideña" maxlength="200">
            <button type="button" class="btn btn-primary" id="btnCrearPremio">+</button>
        </div>
        <div id="premios" class="premios"></div>

        <div class="winners">
            <h2>🏆 Ganadores</h2>
            <div id="ganadores"></div>
        </div>
    </aside>

    <main class="panel stage">
        <div class="prize-title">
            <div class="label">PREMIO EN SORTEO</div>
            <h2 id="premioActual">Seleccione un premio</h2>
        </div>

        <div class="wheel-box">
            <canvas id="wheel"></canvas>
            <div class="pointer"></div>
            <button type="button" class="center-spin" id="btnGirar" disabled>
                <span>GIRAR</span>
            </button>
        </div>

        <div id="status" class="status">Seleccione un premio para comenzar.</div>
    </main>
</div>

<div id="modalGanador" class="modal-backdrop">
    <div class="modal">
        <div class="trophy">🏆</div>
        <h2>¡Tenemos un ganador!</h2>
        <div id="modalNombre" class="winner-name"></div>
        <div id="modalDatos" class="data"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-green" id="btnPresente">✓ ESTÁ PRESENTE</button>
            <button type="button" class="btn btn-red" id="btnNoPresente">✕ NO ESTÁ PRESENTE</button>
        </div>
    </div>
</div>

<script>
const EVENTO_ID = <?= $eventoId ?>;
const API = 'sorteo_api.php';

let premios = [];
let ganadores = [];
let premioActual = null;
let candidatos = [];
let candidatoSeleccionado = null;
let girando = false;
let anguloActual = 0;

const canvas = document.getElementById('wheel');
const ctx = canvas.getContext('2d');

const premiosEl = document.getElementById('premios');
const ganadoresEl = document.getElementById('ganadores');
const premioActualEl = document.getElementById('premioActual');
const btnGirar = document.getElementById('btnGirar');
const statusEl = document.getElementById('status');
const modal = document.getElementById('modalGanador');
const modalNombre = document.getElementById('modalNombre');
const modalDatos = document.getElementById('modalDatos');
const btnPresente = document.getElementById('btnPresente');
const btnNoPresente = document.getElementById('btnNoPresente');

// Paleta AppSorteos
const colores = [
    '#5b68bb', '#a746b9', '#e33f76', '#eb594f', 
    '#f7a126', '#f7c246', '#ceda53', '#3ea0ed'
];

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

async function api(action, data = {}) {
    const body = new URLSearchParams();
    body.append('action', action);
    body.append('evento_id', EVENTO_ID);
    Object.entries(data).forEach(([key, value]) => body.append(key, value));

    const response = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body
    });
    const json = await response.json();
    if (!json.ok) throw new Error(json.message || 'Error inesperado.');
    return json;
}

async function cargar() {
    try {
        const data = await api('bootstrap');
        premios = data.premios || [];
        ganadores = data.ganadores || [];
        renderPremios();
        renderGanadores();
        dibujarRuleta([]);
    } catch (error) {
        statusEl.textContent = 'Error: ' + error.message;
    }
}

function renderPremios() {
    if (!premios.length) {
        premiosEl.innerHTML = '<div style="opacity:.65;">Todavía no hay premios creados.</div>';
        return;
    }

    premiosEl.innerHTML = premios.map(premio => {
        const activo = premioActual && Number(premioActual.id) === Number(premio.id);
        let estado = premio.estado === 'COMPLETADO' ? '✓ COMPLETADO' : (premio.estado === 'EN_PROCESO' ? 'EN PROCESO' : 'PENDIENTE');
        const disabled = premio.estado === 'COMPLETADO' ? 'disabled' : '';
        const textoBoton = premio.estado === 'COMPLETADO' ? '✓ Ganador registrado' : '🎡 Sortear este premio';

        return `
            <div class="premio ${activo ? 'activo' : ''}">
                <div class="premio-top">
                    <div class="premio-nombre">${escapeHtml(premio.nombre)}</div>
                    <div class="premio-estado">${estado}</div>
                </div>
                <button class="btn btn-primary" onclick="seleccionarPremio(${Number(premio.id)})" ${disabled}>
                    ${textoBoton}
                </button>
            </div>
        `;
    }).join('');
}

function renderGanadores() {
    if (!ganadores.length) {
        ganadoresEl.innerHTML = '<div style="opacity:.65;">Aún no hay ganadores.</div>';
        return;
    }

    ganadoresEl.innerHTML = ganadores.map(g => `
        <div class="winner-row">
            <strong>${escapeHtml(g.premio)}</strong>
            <span>${escapeHtml(g.apellidos_nombres)}</span>
            <small>COD: ${escapeHtml(g.cod)}${g.cedula ? ' · Cédula: ' + escapeHtml(g.cedula) : ''}</small>
        </div>
    `).join('');
}

async function seleccionarPremio(id) {
    const premio = premios.find(p => Number(p.id) === Number(id));
    if (!premio) return;

    premioActual = premio;
    premioActualEl.textContent = premio.nombre;
    renderPremios();

    btnGirar.disabled = false;
    statusEl.textContent = 'Listo. Presione GIRAR en el centro de la ruleta.';
    
    // Cargar visualmente candidatos previa extracción
    try {
        const data = await api('candidatos', { premio_id: premioActual.id });
        candidatos = data.candidatos || [];
        anguloActual = 0;
        dibujarRuleta(candidatos);
    } catch(e) {
        dibujarRuleta([]);
    }
}

async function girar() {
    if (girando || !premioActual) return;

    try {
        girando = true;
        btnGirar.disabled = true;
        statusEl.textContent = 'Obteniendo participante...';

        const data = await api('candidatos', { premio_id: premioActual.id });
        candidatos = data.candidatos || [];

        if (!candidatos.length) {
            throw new Error('No quedan colaboradores disponibles para este premio.');
        }

        candidatoSeleccionado = data.ganador;
        const indice = Number(data.indice_ganador);

        statusEl.textContent = '🎡 ¡La ruleta está girando!';
        await animarHastaIndice(indice, candidatos.length);

        mostrarGanador(candidatoSeleccionado);

    } catch (error) {
        statusEl.textContent = 'Error: ' + error.message;
        btnGirar.disabled = false;
        girando = false;
    }
}

function prepararCanvas() {
    const size = canvas.clientWidth || 500;
    const ratio = window.devicePixelRatio || 1;
    canvas.width = size * ratio;
    canvas.height = size * ratio;
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    return size;
}

function dibujarRuleta(items) {
    const size = prepararCanvas();
    const cx = size / 2;
    const cy = size / 2;
    const radius = size / 2 - 10;

    ctx.clearRect(0, 0, size, size);

    if (!items.length) {
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fillStyle = '#e2e8f0';
        ctx.fill();
        ctx.lineWidth = 8;
        ctx.strokeStyle = '#ffffff';
        ctx.stroke();
        return;
    }

    const n = items.length;
    const slice = (Math.PI * 2) / n;

    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(anguloActual);
    ctx.translate(-cx, -cy);

    for (let i = 0; i < n; i++) {
        const start = i * slice;
        const end = start + slice;

        // Dibujar Segmento
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, radius, start, end);
        ctx.closePath();

        ctx.fillStyle = colores[i % colores.length];
        ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#ffffff';
        ctx.stroke();

        // Renderizado del Texto
        ctx.save();
        const mid = start + slice / 2;
        ctx.translate(cx, cy);
        ctx.rotate(mid);
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#ffffff';
        
        let fontSize = n <= 20 ? 15 : (n <= 50 ? 12 : 9);
        ctx.font = `bold ${fontSize}px sans-serif`;

        let nombre = items[i].apellidos_nombres || '';
        if (nombre.length > 20) nombre = nombre.substring(0, 18) + '…';

        ctx.fillText(nombre, radius - 18, 0);
        ctx.restore();
    }

    ctx.restore();

    // Borde exterior blanco
    ctx.beginPath();
    ctx.arc(cx, cy, radius, 0, Math.PI * 2);
    ctx.lineWidth = 10;
    ctx.strokeStyle = '#ffffff';
    ctx.stroke();
}

function animarHastaIndice(indice, cantidad) {
    return new Promise(resolve => {
        const slice = (Math.PI * 2) / cantidad;
        
        // Puntero en 0 radianes (Derecha / 3:00)
        const centroSegmento = (indice + 0.5) * slice;
        const objetivoBase = -centroSegmento;

        const vueltas = (6 + Math.floor(Math.random() * 3)) * Math.PI * 2;
        const inicio = anguloActual;
        const final = inicio + vueltas + (objetivoBase - (inicio % (Math.PI * 2)));

        const duracion = 5000;
        const inicioTiempo = performance.now();

        function easeOutCubic(t) {
            return 1 - Math.pow(1 - t, 3);
        }

        function frame(ahora) {
            const progreso = Math.min(1, (ahora - inicioTiempo) / duracion);
            const ease = easeOutCubic(progreso);

            anguloActual = inicio + (final - inicio) * ease;
            dibujarRuleta(candidatos);

            if (progreso < 1) {
                requestAnimationFrame(frame);
            } else {
                anguloActual = final;
                dibujarRuleta(candidatos);
                resolve();
            }
        }

        requestAnimationFrame(frame);
    });
}

function mostrarGanador(colaborador) {
    modalNombre.textContent = colaborador.apellidos_nombres;
    modalDatos.innerHTML = `
        <strong>COD:</strong> ${escapeHtml(colaborador.cod)}<br>
        ${colaborador.cedula ? `<strong>Cédula:</strong> ${escapeHtml(colaborador.cedula)}<br>` : ''}
        ${colaborador.area ? `<strong>Área:</strong> ${escapeHtml(colaborador.area)}<br>` : ''}
        ${colaborador.empresa ? `<strong>Empresa:</strong> ${escapeHtml(colaborador.empresa)}` : ''}
    `;

    modal.style.display = 'flex';
    btnPresente.disabled = false;
    btnNoPresente.disabled = false;
}

async function resolverGanador(presente) {
    if (!candidatoSeleccionado || !premioActual) return;

    btnPresente.disabled = true;
    btnNoPresente.disabled = true;

    try {
        await api('resolver', {
            premio_id: premioActual.id,
            colaborador_id: candidatoSeleccionado.id,
            presente: presente ? 1 : 0
        });

        if (presente) {
            modal.style.display = 'none';
            statusEl.textContent = '🏆 Ganador registrado: ' + candidatoSeleccionado.apellidos_nombres;

            const premioEncontrado = premios.find(p => Number(p.id) === Number(premioActual.id));
            if (premioEncontrado) premioEncontrado.estado = 'COMPLETADO';

            ganadores.push({
                premio_id: premioActual.id,
                premio: premioActual.nombre,
                colaborador_id: candidatoSeleccionado.id,
                cod: candidatoSeleccionado.cod,
                cedula: candidatoSeleccionado.cedula,
                apellidos_nombres: candidatoSeleccionado.apellidos_nombres
            });

            renderPremios();
            renderGanadores();

            btnGirar.disabled = true;
            candidatoSeleccionado = null;
            girando = false;
            return;
        }

        modal.style.display = 'none';
        statusEl.textContent = 'El colaborador no está presente. Volviendo a girar...';
        candidatoSeleccionado = null;
        girando = false;

        setTimeout(() => girar(), 1000);

    } catch (error) {
        modal.style.display = 'none';
        statusEl.textContent = 'Error: ' + error.message;
        btnGirar.disabled = false;
        girando = false;
    }
}

document.getElementById('btnCrearPremio').addEventListener('click', async () => {
    const input = document.getElementById('nombrePremio');
    const nombre = input.value.trim();
    if (!nombre) {
        alert('Ingrese el nombre del premio.');
        input.focus();
        return;
    }

    try {
        const data = await api('crear_premio', { nombre });
        input.value = '';
        const carga = await api('bootstrap');
        premios = carga.premios || [];
        ganadores = carga.ganadores || [];
        renderPremios();
        renderGanadores();
        await seleccionarPremio(data.premio_id);
    } catch (error) {
        alert(error.message);
    }
});

btnGirar.addEventListener('click', girar);
btnPresente.addEventListener('click', () => resolverGanador(true));
btnNoPresente.addEventListener('click', () => resolverGanador(false));
window.addEventListener('resize', () => dibujarRuleta(candidatos));

document.getElementById('nombrePremio').addEventListener('keydown', event => {
    if (event.key === 'Enter') document.getElementById('btnCrearPremio').click();
});

cargar();
</script>

</body>
</html>