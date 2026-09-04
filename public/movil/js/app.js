(() => {
  const input = document.getElementById('identificador');
  const form = document.getElementById('formAsistencia');
  const result = document.getElementById('resultado');
  const video = document.getElementById('video');
  const cameraMessage = document.getElementById('cameraMessage');
  const frame = document.querySelector('.scan-frame');
  const btnCamera = document.getElementById('btnCamera');
  const btnInstall = document.getElementById('btnInstall');
  const asistentes = document.getElementById('asistentes');
  const ultimaHora = document.getElementById('ultimaHora');
  const connectionDot = document.getElementById('connectionDot');
  let stream = null;
  let scanning = false;
  let detector = null;
  let processing = false;
  let deferredInstallPrompt = null;
  let lastScanned = '';
  let lastScannedAt = 0;

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
    if (!window.isSecureContext) {
      cameraMessage.textContent = 'La cámara requiere HTTPS. Abre esta aplicación usando https://';
      return;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
      cameraMessage.textContent = 'El navegador no expone la cámara. Verifica que estés usando Chrome/Edge actualizado y HTTPS.';
      return;
    }
    if (!('BarcodeDetector' in window)) {
      cameraMessage.textContent = 'Este navegador no tiene BarcodeDetector. Usa Chrome actualizado o el ingreso manual.';
      return;
    }
    try {
      let formats = ['code_128','code_39','code_93','ean_13','ean_8','upc_a','upc_e','qr_code','itf','codabar'];
      if (typeof BarcodeDetector.getSupportedFormats === 'function') {
        const supported = await BarcodeDetector.getSupportedFormats();
        formats = formats.filter(f => supported.includes(f));
      }
      detector = formats.length ? new BarcodeDetector({formats}) : new BarcodeDetector();
    } catch (_) {
      try { detector = new BarcodeDetector(); }
      catch (_) { cameraMessage.textContent = 'No se pudo inicializar el lector de códigos en este navegador.'; return; }
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
      if (error && error.name === 'NotAllowedError') cameraMessage.textContent = 'Permiso de cámara denegado. En Chrome: candado → Cámara → Permitir y vuelve a cargar.';
      else if (error && error.name === 'NotFoundError') cameraMessage.textContent = 'No se encontró una cámara disponible.';
      else if (error && error.name === 'NotReadableError') cameraMessage.textContent = 'La cámara está siendo usada por otra aplicación.';
      else cameraMessage.textContent = `No se pudo activar la cámara (${error?.name || 'error'}).`;
    }
  }

  async function scanLoop() {
    if (!scanning || !detector || video.readyState < 2) { if (scanning) requestAnimationFrame(scanLoop); return; }
    try {
      const codes = await detector.detect(video);
      if (codes.length && !processing) {
        const value = (codes[0].rawValue || '').trim();
        const now = Date.now();
        if (value && !(value === lastScanned && now - lastScannedAt < 1500)) {
          lastScanned = value;
          lastScannedAt = now;
          await registrar(value);
          await new Promise(r => setTimeout(r, 300));
        }
      }
    } catch (_) {}
    if (scanning) requestAnimationFrame(scanLoop);
  }

  btnCamera.addEventListener('click', startCamera);

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    deferredInstallPrompt = event;
    if (btnInstall) btnInstall.hidden = false;
  });

  if (btnInstall) {
    btnInstall.addEventListener('click', async () => {
      if (!deferredInstallPrompt) return;
      deferredInstallPrompt.prompt();
      await deferredInstallPrompt.userChoice;
      deferredInstallPrompt = null;
      btnInstall.hidden = true;
    });
  }

  window.addEventListener('appinstalled', () => {
    if (btnInstall) btnInstall.hidden = true;
  });
  window.addEventListener('online', () => connectionDot.classList.remove('offline'));
  window.addEventListener('offline', () => connectionDot.classList.add('offline'));
  document.addEventListener('visibilitychange', () => { if (document.hidden && stream) stream.getTracks().forEach(track => track.stop()); });

  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
  input.focus();
})();
