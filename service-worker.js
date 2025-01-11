// اسم ذاكرة التخزين المؤقت
const CACHE_NAME = 'shashety-v1';

// الملفات التي سيتم تخزينها في الذاكرة المؤقتة
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

// تثبيت Service Worker وتخزين الملفات في الذاكرة المؤقتة
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll(ASSETS); // تخزين الملفات في الذاكرة المؤقتة
            })
            .then(() => self.skipWaiting()) // تخطي الانتظار والبدء فورًا
    );
});

// تفعيل Service Worker
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache); // حذف الذواكر القديمة
                    }
                })
            );
        })
    );
});

// استرجاع الملفات من الذاكرة المؤقتة أو الإنترنت
self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request) // البحث عن الطلب في الذاكرة المؤقتة
            .then((response) => {
                if (response) {
                    return response; // إرجاع الملف من الذاكرة المؤقتة إذا كان موجودًا
                }
                return fetch(event.request); // إجراء الطلب من الإنترنت إذا لم يكن موجودًا
            })
    );
});