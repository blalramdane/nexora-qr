const CACHE_NAME = 'nexora-qr-shell-v2';
const OFFLINE_PAGE = '/offline.html';
const STATIC_ASSETS = ['/', '/offline.html'];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
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
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Never cache authenticated pages, Inertia data, orders, customer details, or API responses.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                const offlinePage = await caches.match(OFFLINE_PAGE);
                return offlinePage || new Response('NEXORA QR needs an internet connection for live data.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store' },
                });
            }),
        );
        return;
    }

    // Cache static assets (CSS/JS/images/fonts) with stale-while-revalidate
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/storage/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const fetchPromise = fetch(request).then((response) => {
                    if (response && response.status === 200) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                }).catch(() => cached);
                return cached || fetchPromise;
            }),
        );
    }
});
