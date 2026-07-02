const STATIC_CACHE = 'sems-static-v1';
const STATIC_ASSETS = [
    '/sems/manifest.json',
    '/sems/icons/icon-192.png',
    '/sems/icons/icon-512.png',
    '/sems/pwa-register.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== STATIC_CACHE).map((key) => caches.delete(key)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    const isStaticAsset = STATIC_ASSETS.some((asset) => url.pathname === asset) ||
        url.pathname.startsWith('/sems/icons/');

    if (isStaticAsset) {
        // Cache-first: these files never change dashboard/business data.
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request))
        );
        return;
    }

    // Network-first for everything else (all .php pages: dashboard, ledgers, etc.)
    // Never serve stale business data from cache.
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
