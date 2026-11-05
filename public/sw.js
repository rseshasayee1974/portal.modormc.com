const CACHE_NAME = 'modormc-pwa-v3';
const ASSETS_TO_CACHE = [
    '/orders/batches',
    '/assets/modormc_favicon.png',
    '/assets/modormc_logo_v1.0.png',
    '/favicon.ico'
];

// Install Service Worker and cache critical shell URLs
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[Service Worker] Pre-caching HTML shell and assets');
            return cache.addAll(ASSETS_TO_CACHE);
        }).then(() => self.skipWaiting())
    );
});

// Activate and remove old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName.startsWith('modormc-pwa-') && cacheName !== CACHE_NAME) {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch events interceptor
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only handle GET requests
    if (request.method !== 'GET') return;
    // Browser cache-only probes cannot reliably be replayed with fetch().
    if (request.cache === 'only-if-cached') return;

    const url = new URL(request.url);

    // Only handle http/https requests (ignore chrome-extension://, etc.)
    if (!url.protocol.startsWith('http')) return;

    // Ignore cross-origin requests (e.g., local weighbridge API at localhost:8089, external CDNs)
    if (url.origin !== self.location.origin) return;

    // Inertia and API responses depend on the current user and active plant.
    // Leave them to the browser instead of substituting cached pages or data.
    if (request.headers.has('X-Inertia') || request.headers.get('Accept')?.includes('application/json')) return;

    // Live export status must go straight to the server, without cached fallbacks.
    if (url.pathname.startsWith('/reports/export-status/')) return;

    // 1. Static Assets (Compiled Vite bundles, fonts, images) -> Cache-First
    if (url.pathname.includes('/build/assets/') || url.pathname.includes('/assets/') || request.destination === 'font') {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) return cachedResponse;

                return fetch(request).then((networkResponse) => {
                    if (!networkResponse || networkResponse.status !== 200) {
                        return networkResponse;
                    }
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                    return networkResponse;
                }).catch(() => {
                    return Response.error();
                });
            })
        );
        return;
    }

    // 2. Navigation / Page Requests (HTML shell) -> Network-First, fall back to cached shell
    if (request.mode === 'navigate' && url.pathname === '/orders/batches') {
        event.respondWith(
            fetch(request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200 && !networkResponse.redirected
                    && networkResponse.headers.get('Content-Type')?.includes('text/html')) {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put('/orders/batches', responseToCache);
                    });
                }
                return networkResponse;
            }).catch(async () => {
                console.log('[Service Worker] Offline fallback serving cached batches page');
                const cached = await caches.match('/orders/batches');
                return cached || Response.error();
            })
        );
        return;
    }

    // All other requests (including products and other page navigations) use
    // normal browser networking; a cached batches page is not a valid fallback.
});
