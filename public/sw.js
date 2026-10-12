/*
 * Drive-Thru Plus guest service worker.
 * - Built assets and fonts: cache first (their file names are content-hashed).
 * - Pages: always from the network (menus, prices and orders must be live);
 *   when offline, a calm bilingual offline page instead of a browser error.
 * - Never caches pages, API calls or anything personal.
 */
const VERSION = 'dtp-v2';
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll([OFFLINE])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/fonts/')) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(VERSION).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
    }
});

/*
 * Web push: order updates for guests, alerts for staff. The payload is
 * { title, body, url, tag }; tapping opens (or focuses) that page on this site.
 */
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: event.data?.text() ?? '' }; }

    event.waitUntil(self.registration.showNotification(data.title || 'Drive-Thru Plus', {
        body: data.body || '',
        tag: data.tag || undefined,
        renotify: Boolean(data.tag),
        icon: data.icon || '/favicon.svg',
        badge: '/favicon.svg',
        vibrate: [200, 100, 200],
        data: { url: data.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/', self.location.origin);
    if (target.origin !== self.location.origin) return;

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const open = windows.find((w) => w.url === target.href);
        return open ? open.focus() : self.clients.openWindow(target.href);
    }));
});
