// ============================================================
// Service Worker — SIMK PT Pesta Hijau Abadi
// ============================================================

const CACHE_NAME = 'simk-pha-v1';
const OFFLINE_URL = '/pwa-hr/offline.html';

const PRECACHE = [
    '/pwa-hr/',
    '/pwa-hr/login.php',
    '/pwa-hr/offline.html',
    '/pwa-hr/assets/css/app.css',
    '/pwa-hr/assets/js/app.js',
    '/pwa-hr/manifest.json',
];

// Install
self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE_NAME).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

// Activate
self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// Fetch — Network first, cache fallback
self.addEventListener('fetch', e => {
    if (e.request.method !== 'GET') return;
    if (e.request.url.includes('/api/')) return; // API tidak dicache

    e.respondWith(
        fetch(e.request)
            .then(res => {
                const clone = res.clone();
                caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
                return res;
            })
            .catch(() => caches.match(e.request).then(cached => cached || caches.match(OFFLINE_URL)))
    );
});

// Push Notification
self.addEventListener('push', e => {
    const data = e.data ? e.data.json() : { title: 'SIMK PHA', body: 'Ada notifikasi baru' };
    e.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/pwa-hr/assets/icons/icon-192.png',
            badge: '/pwa-hr/assets/icons/icon-72.png',
            data: { url: data.url || '/pwa-hr/' },
            vibrate: [200, 100, 200],
            tag: 'simk-notif'
        })
    );
});

self.addEventListener('notificationclick', e => {
    e.notification.close();
    e.waitUntil(clients.openWindow(e.notification.data.url));
});
