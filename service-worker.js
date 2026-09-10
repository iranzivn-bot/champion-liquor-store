'use strict';

var CACHE_NAME = 'champion-store-v1';
var urlsToCache = [
  '/champion-liquor-store/',
  '/champion-liquor-store/index.php',
  '/champion-liquor-store/pages/shop.php',
  '/champion-liquor-store/assets/css/design-system.css',
  '/champion-liquor-store/assets/css/style.css',
  '/champion-liquor-store/assets/js/script.js',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
];

// Install: cache core assets
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll(urlsToCache);
    }).then(function() {
      return self.skipWaiting();
    })
  );
});

// Activate: clean old caches
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(name) {
          if (name !== CACHE_NAME) {
            return caches.delete(name);
          }
        })
      );
    }).then(function() {
      return self.clients.claim();
    })
  );
});

// Fetch: network-first, fallback to cache, offline fallback page
self.addEventListener('fetch', function(event) {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;

  // Skip chrome-extension, analytics, etc
  if (event.request.url.indexOf('chrome-extension') !== -1) return;

  event.respondWith(
    fetch(event.request)
      .then(function(response) {
        // Cache successful responses
        if (response.status === 200) {
          var responseClone = response.clone();
          caches.open(CACHE_NAME).then(function(cache) {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      })
      .catch(function() {
        // Network failed - try cache
        return caches.match(event.request).then(function(cached) {
          if (cached) return cached;
          // If it's a page navigation, show offline page
          if (event.request.mode === 'navigate') {
            return caches.match('/champion-liquor-store/offline.php');
          }
          return new Response('Offline', { status: 503 });
        });
      })
  );
});
