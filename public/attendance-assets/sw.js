/* =========================================================================
   LAIJAU EMPLOYEE ATTENDANCE PWA — SERVICE WORKER
   ========================================================================= */

const CACHE_NAME = 'laijau-attendance-v3';
const OFFLINE_URL = '/attendance/offline';

const PRECACHE_ASSETS = [
    '/attendance/offline',
    '/attendance/manifest.webmanifest',
    '/attendance-assets/icons/icon-192.png',
    '/attendance-assets/icons/icon-512.png',
];

// Install: Cache offline assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// Activate: Purge obsolete caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    return caches.delete(key);
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: Network first with offline fallback for navigation requests
self.addEventListener('fetch', (event) => {
    // Only handle GET requests within scope
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    // Skip API, non-HTTP, and third-party requests
    if (url.pathname.startsWith('/attendance/api/')) {
        return;
    }

    // Static assets: cache first, then network
    if (url.pathname.startsWith('/attendance-assets/')) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                return cached || fetch(event.request).then((response) => {
                    if (response.status === 200) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Navigation requests (HTML pages): Network first, then offline fallback
    if (event.request.mode === 'navigate' || event.request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(event.request).catch(async () => {
                const cache = await caches.open(CACHE_NAME);
                const cachedOffline = await cache.match(OFFLINE_URL);
                return cachedOffline || new Response('Offline - Internet connection required to verify attendance.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain' },
                });
            })
        );
    }
});
