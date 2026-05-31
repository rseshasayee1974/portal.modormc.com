const CACHE_NAME = 'modormc-pwa-v1';
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
                    if (cacheName !== CACHE_NAME) {
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

    const url = new URL(request.url);

    // Only handle http/https requests (ignore chrome-extension://, etc.)
    if (!url.protocol.startsWith('http')) return;

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
                    // Fail silently for static assets offline
                });
            })
        );
        return;
    }

    // 2. Navigation / Page Requests (HTML shell) -> Network-First, fall back to cached shell
    if (request.mode === 'navigate' || url.pathname === '/orders/batches') {
        event.respondWith(
            fetch(request).then((networkResponse) => {
                // Update cached page shell with the latest network response
                const responseToCache = networkResponse.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put('/orders/batches', responseToCache);
                });
                return networkResponse;
            }).catch(() => {
                console.log('[Service Worker] Offline fallback serving cached batches page');
                return caches.match('/orders/batches');
            })
        );
        return;
    }

    // Default request behavior
    event.respondWith(
        caches.match(request).then((response) => {
            return response || fetch(request);
        })
    );
});
