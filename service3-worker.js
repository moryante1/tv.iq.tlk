const CACHE_NAME = 'my-site-cache-v1';
const urlsToCache = [
  '/',
  '/animation/inx3.mp4',
  '/audio/track3.mp3',
  '/icon/v1.png',

  'https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap'
];

// تثبيت Service Worker وتخزين الموارد
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(urlsToCache);
      })
  );
});

// تقديم الموارد من الكاش
self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        if (response) {
          return response;
        }
        return fetch(event.request);
      })
  );
});