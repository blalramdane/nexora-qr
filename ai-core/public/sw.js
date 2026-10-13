const CACHE_NAME = 'nexora-qr-shell-v1';
const OFFLINE_PAGE = '/offline.html';

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.add(OFFLINE_PAGE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(key => key.startsWith('nexora-qr-shell-') && key !== CACHE_NAME).map(key => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET' || request.mode !== 'navigate') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Never cache authenticated pages, Inertia data, orders, customer details, or API responses.
    // Online navigation uses the server; network failure receives only a static offline message.
    event.respondWith(
        fetch(request).catch(async () => {
            const offlinePage = await caches.match(OFFLINE_PAGE);
            return offlinePage || new Response('NEXORA QR needs an internet connection for live data.', {
                status: 503,
                headers: { 'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store' },
            });
        }),
    );
});
