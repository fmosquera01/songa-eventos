(() => {
  const input = document.getElementById('identificador');
  const form = document.getElementById('formAsistencia');
  const result = document.getElementById('resultado');
  const video = document.getElementById('video');
  const cameraWrap = document.getElementById('cameraWrap');
  const cameraMessage = document.getElementById('cameraMessage');
  const frame = document.querySelector('.scan-frame');
  const btnCamera = document.getElementById('btnCamera');
  const asistentes = document.getElementById('asistentes');
  const ultimaHora = document.getElementById('ultimaHora');
  const connectionDot = document.getElementById('connectionDot');
  let stream = null;
  let scanning = false;
  let detector = null;
  let processing = false;

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function feedback(type) {
    if (navigator.vibrate) navigator.vibrate(type === 'ok' ? 120 : type === 'dup' ? [80,60,80] : [180,80,180]);
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.frequency.value = type === 'ok' ? 880 : type === 'dup' ? 520 : 220;
      gain.gain.setValueAtTime(0.08, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.14);
      osc.start(); osc.stop(ctx.currentTime + 0.15);
    } catch (_) {}
  }

  function showResult(data) {
    const type = data.estado === 'OK' ? 'ok' : data.estado === 'DUPLICADO' ? 'dup' : 'err';
    const icon = type === 'ok' ? '✓' : type === 'dup' ? '!' : '×';
    const c = data.colaborador || {};
    result.className = `result ${type}`;
    result.innerHTML = `<div class="icon">${icon}</div><div class="title">${escapeHtml(data.titulo || '')}</div>${c.apellidos_nombres ? `<div class="name">${escapeHtml(c.apellidos_nombres)}</div>` : ''}${c.empresa ? `<div class="detail">${escapeHtml(c.empresa)}</div>` : ''}${c.area ? `<div class="detail">${escapeHtml(c.area)}</div>` : ''}${data.hora ? `<div class="detail">${escapeHtml(data.hora)}</div>` : ''}${data.mensaje ? `<div class="detail">${escapeHtml(data.mensaje)}</div>` : ''}`;
    feedback(type);
    if (data.estadisticas) asistentes.textContent = data.estadisticas.asistentes;
    if (data.hora) ultimaHora.textContent = data.hora.split(' ')[1] || data.hora;
  }

  async function registrar(valor) {
    if (processing || !valor.trim()) return;
    processing = true;
    input.value = '';
    try {
      const body = new URLSearchParams({ identificador: valor.trim() });
      const response = await fetch(window.PWA_CONFIG.registrarUrl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body, credentials:'same-origin', cache:'no-store' });
      const data = await response.json();
      showResult(data);
      connectionDot.classList.remove('offline');
    } catch (error) {
      showResult({estado:'ERROR', titulo:'ERROR DE CONEXIÓN', mensaje:'No se pudo comunicar con el servidor.'});
      connectionDot.classList.add('offline');
    } finally {
      processing = false;
      input.focus();
    }
  }

  form.addEventListener('submit', e => { e.preventDefault(); registrar(input.value); });

  async function startCamera() {
    if (!navigator.mediaDevices?.getUserMedia) {
      cameraMessage.textContent = 'Este navegador no permite acceder a la cámara.';
      return;
    }
    if (!('BarcodeDetector' in window)) {
      cameraMessage.textContent = 'El lector de códigos de este navegador no está disponible. Usa el campo manual o Chrome actualizado.';
      return;
    }
    try {
      detector = new BarcodeDetector({formats:['code_128','code_39','code_93','ean_13','ean_8','upc_a','upc_e','qr_code','itf','codabar']});
    } catch (_) {
      detector = new BarcodeDetector();
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});
      video.srcObject = stream;
      await video.play();
      video.style.display = 'block';
      frame.style.display = 'block';
      cameraMessage.style.display = 'none';
      btnCamera.textContent = 'CÁMARA ACTIVA';
      btnCamera.disabled = true;
      scanning = true;
      scanLoop();
    } catch (error) {
      cameraMessage.textContent = 'No se pudo activar la cámara. Revisa el permiso del navegador.';
    }
  }

  async function scanLoop() {
    if (!scanning || !detector || video.readyState < 2) { if (scanning) requestAnimationFrame(scanLoop); return; }
    try {
      const codes = await detector.detect(video);
      if (codes.length && !processing) {
        const value = (codes[0].rawValue || '').trim();
        if (value) {
          await registrar(value);
          await new Promise(r => setTimeout(r, 450));
        }
      }
    } catch (_) {}
    if (scanning) requestAnimationFrame(scanLoop);
  }

  btnCamera.addEventListener('click', startCamera);
  window.addEventListener('online', () => connectionDot.classList.remove('offline'));
  window.addEventListener('offline', () => connectionDot.classList.add('offline'));

  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
  input.focus();
})();
