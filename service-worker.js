const CACHE_NAME = 'shimal-events-v1';
const STATIC_CACHE = 'static-v1';
const DYNAMIC_CACHE = 'dynamic-v1';

// الملفات التي سيتم تخزينها مؤقتاً عند التثبيت
const STATIC_ASSETS = [
    '/activity/',
    '/activity/index.php',
    '/activity/offline.html',
    '/activity/assets/css/mobile-responsive.css',
    '/activity/assets/css/mobile-advanced.css'
    // ملاحظة: Tailwind و Font Awesome من CDN، لا نحفظهم في cache
];

// تثبيت Service Worker
self.addEventListener('install', event => {
    console.log('[SW] Installing Service Worker...', event);
    event.waitUntil(
        caches.open(STATIC_CACHE).then(cache => {
            console.log('[SW] Caching static assets');
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// تفعيل Service Worker
self.addEventListener('activate', event => {
    console.log('[SW] Activating Service Worker...', event);
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys
                    .filter(key => key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
                    .map(key => caches.delete(key))
            );
        })
    );
    return self.clients.claim();
});

// استراتيجية Cache-First للملفات الثابتة
// Network-First للبيانات الديناميكية
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // تجاهل الطلبات غير HTTP/HTTPS
    if (!request.url.startsWith('http')) {
        return;
    }

    // استراتيجية حسب نوع الملف
    if (request.url.includes('/activity/includes/') ||
        request.url.includes('.css') ||
        request.url.includes('.js') ||
        request.url.includes('.woff') ||
        request.url.includes('.png') ||
        request.url.includes('.jpg')) {
        // Cache-First للملفات الثابتة
        event.respondWith(
            caches.match(request).then(response => {
                return response || fetch(request).then(fetchRes => {
                    return caches.open(STATIC_CACHE).then(cache => {
                        cache.put(request, fetchRes.clone());
                        return fetchRes;
                    });
                });
            }).catch(() => {
                // Fallback للصفحات
                if (request.destination === 'document') {
                    return caches.match('/activity/offline.html');
                }
            })
        );
    } else {
        // Network-First للبيانات الديناميكية
        event.respondWith(
            fetch(request).then(fetchRes => {
                return caches.open(DYNAMIC_CACHE).then(cache => {
                    cache.put(request, fetchRes.clone());
                    return fetchRes;
                });
            }).catch(() => {
                return caches.match(request);
            })
        );
    }
});

// التعامل مع الإشعارات (للمستقبل)
self.addEventListener('push', event => {
    console.log('[SW] Push received', event);
    const data = event.data ? event.data.json() : {};
    const title = data.title || 'إشعار جديد';
    const options = {
        body: data.body || 'لديك إشعار جديد',
        icon: '/activity/assets/icons/icon-192x192.png',
        badge: '/activity/assets/icons/icon-72x72.png',
        dir: 'rtl',
        lang: 'ar',
        vibrate: [200, 100, 200],
        data: data.data || {}
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// التعامل مع نقر الإشعار
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification click', event);
    event.notification.close();

    event.waitUntil(
        clients.openWindow(event.notification.data.url || '/activity/')
    );
});

// مزامنة في الخلفية (Background Sync)
self.addEventListener('sync', event => {
    console.log('[SW] Background sync', event);
    if (event.tag === 'sync-bookings') {
        event.waitUntil(syncBookings());
    }
});

// دالة مزامنة الحجوزات المعلقة
async function syncBookings() {
    // يمكن تطويرها لاحقاً لمزامنة الطلبات المحفوظة محلياً
    console.log('[SW] Syncing bookings...');
}
