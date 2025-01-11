const CACHE_NAME = 'shashety-v1';
const ASSETS = [
    '/',
    '/index.html',
    '/service/css/styles.css',
    '/service/js/scripts.js',
    '/service/img/logo.png',
    '/service/img/sun.png',
    '/service/img/moon.png',
    '/service/img/mbc_icon.png',
    '/service/img/sports_icon.png',
    '/service/img/entertainment_icon.png',
    '/service/img/movies_icon.png',
    '/service/img/kids_icon.png',
    '/service/img/osn_icon.png',
    '/service/img/other.png',
    '/service/img/icon1.png',
    '/service/img/icon2.png',
    '/service/img/icon3.png',
    '/service/animation/Naruto.mp4',
];

// تثبيت Service Worker
self.addEventListener('install', (event) => {
    console.log('Service Worker: Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Service Worker: Caching files...');
                return cache.addAll(ASSETS);
            })
            .then(() => {
                console.log('Service Worker: Installed successfully.');
                self.skipWaiting();
            })
            .catch((err) => {
                console.error('Service Worker: Installation failed.', err);
            })
    );
});

// تفعيل Service Worker
self.addEventListener('activate', (event) => {
    console.log('Service Worker: Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        console.log('Service Worker: Deleting old cache...', cache);
                        return caches.delete(cache);
                    }
                })
            );
        })
        .then(() => {
            console.log('Service Worker: Activated successfully.');
            self.clients.claim();
        })
        .catch((err) => {
            console.error('Service Worker: Activation failed.', err);
        })
    );
});

// استرجاع الملفات من الذاكرة المؤقتة أو الإنترنت
self.addEventListener('fetch', (event) => {
    console.log('Service Worker: Fetching...', event.request.url);
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                if (response) {
                    console.log('Service Worker: Found in cache.', event.request.url);
                    return response;
                }
                console.log('Service Worker: Fetching from network.', event.request.url);
                return fetch(event.request);
            })
            .catch((err) => {
                console.error('Service Worker: Fetch failed.', err);
            })
    );
});
