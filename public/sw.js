/**
 * Nabung Tracking - minimal service worker.
 *
 * Caches the static app shell (built CSS/JS, icons, fonts) so the app opens
 * read-only when offline. Dynamic data (Livewire updates, API/POST) always
 * goes to the network and is never cached.
 *
 * Bump CACHE when you want every client to drop the old shell.
 */
const CACHE = 'nabung-shell-v1';

const PRECACHE = [
    '/offline.html',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

// Which static assets to cache-first at runtime (hashed Vite filenames, etc).
function isStaticAsset(url) {
    return (
        url.origin === self.location.origin &&
        (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/'))
    ) || url.origin === 'https://fonts.bunny.net';
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return; // never touch POST / Livewire updates
    }

    const url = new URL(request.url);

    // App-shell assets: cache-first, fill the cache on first hit.
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Page navigations: network-first, fall back to a cached copy, then offline.html.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached || caches.match('/offline.html')))
        );
        return;
    }

    // Everything else: straight to network (no offline guarantee).
});
