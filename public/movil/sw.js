const CACHE = 'songa-asistencia-v2';

const APP_SHELL = [
  './css/app.css',
  './js/app.js',
  './manifest.json',
  './icon.svg',
  './icon-192.png',
  './icon-512.png'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE)
      .then(cache => cache.addAll(APP_SHELL))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys
          .filter(key => key !== CACHE)
          .map(key => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // Nunca cachear autenticación, navegación dinámica ni registro de asistencia.
  if (
    url.origin !== self.location.origin ||
    url.pathname.endsWith('/registrar.php') ||
    url.pathname.endsWith('/login.php') ||
    url.pathname.endsWith('/logout.php') ||
    url.pathname.endsWith('/movil/') ||
    url.pathname.endsWith('/movil/index.php')
  ) {
    return;
  }

  // Los recursos estáticos de la PWA se sirven desde caché cuando existen.
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;

      return fetch(event.request).then(response => {
        if (!response || response.status !== 200 || response.type === 'opaque') {
          return response;
        }

        const copy = response.clone();
        caches.open(CACHE).then(cache => cache.put(event.request, copy));
        return response;
      });
    })
  );
});
