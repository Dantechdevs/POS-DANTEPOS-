/* DantePOS offline mode: keeps the Offline POS page available when the server cannot be reached.
   Sales data lives in IndexedDB (managed by the page), not in this cache. */
const CACHE = 'dantepos-offline-v1';
const PAGE_PATH = new URL('offline-pos', self.registration.scope).pathname;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET' || url.origin !== self.location.origin) return;
  if (req.mode !== 'navigate' || url.pathname !== PAGE_PATH) return;

  event.respondWith((async () => {
    try {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 5000);
      const net = await fetch(req, { signal: controller.signal });
      clearTimeout(timer);

      if (net.status >= 500) throw new Error('server error');

      // Only keep real page copies, never a redirect to the login screen.
      if (net.ok && !net.redirected) {
        const cache = await caches.open(CACHE);
        await cache.put(PAGE_PATH, net.clone());
      }
      return net;
    } catch (err) {
      const cached = await caches.match(PAGE_PATH);
      if (cached) return cached;
      return new Response(
        '<h1>Offline</h1><p>Open the Offline POS page once while online so it can be saved on this device.</p>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
      );
    }
  })());
});
