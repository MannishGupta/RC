const VERSION = new URL(self.location).searchParams.get('v') || 'dev';
const SHELL = `shell-${VERSION}`;
const ASSETS = `assets-${VERSION}`;
self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(SHELL).then((c) => c.addAll(['/offline.php']).catch(() => {})).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== SHELL && k !== ASSETS).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(fetch(req).catch(async () => (await caches.match('/offline.php')) || new Response('<h1>Offline</h1>', { status: 503, headers: { 'Content-Type': 'text/html' } })));
    return;
  }
  if (url.origin !== self.location.origin) return;
  if (url.searchParams.has('action') || url.searchParams.has('ajax') || url.pathname.startsWith('/tools/') || url.pathname.startsWith('/vehicle-tags/')) return;
  const isStatic = /\.(css|js|woff2?|ttf|png|jpe?g|webp|gif|svg|ico)$/i.test(url.pathname) || url.pathname.startsWith('/assets/');
  if (!isStatic) return;
  event.respondWith(caches.open(ASSETS).then(async (cache) => {
    const hit = await cache.match(req);
    const net = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
    return hit || net;
  }));
});
self.addEventListener('message', (event) => {
  if (event.data === 'clearAll') caches.keys().then((keys) => Promise.all(keys.map((k) => caches.delete(k))));
});
