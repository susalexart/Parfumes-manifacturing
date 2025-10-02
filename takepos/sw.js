/**
 * Service Worker for Modern TakePos
 * Provides offline functionality and caching
 */

const CACHE_NAME = 'takepos-modern-v1';
const urlsToCache = [
    '/takepos/modern-index.php',
    '/takepos/modern-phone.php',
    '/takepos/css/modern-pos.css',
    '/takepos/css/modern-mobile.css',
    '/takepos/js/modern-pos.js',
    '/takepos/js/modern-mobile.js',
    '/takepos/js/jquery.colorbox-min.js',
    '/takepos/genimg/empty.png'
];

// Install event - cache resources
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
    );
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // Return cached version or fetch from network
                if (response) {
                    return response;
                }
                
                return fetch(event.request).then((response) => {
                    // Check if we received a valid response
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }
                    
                    // Clone the response
                    const responseToCache = response.clone();
                    
                    caches.open(CACHE_NAME)
                        .then((cache) => {
                            cache.put(event.request, responseToCache);
                        });
                    
                    return response;
                });
            })
            .catch(() => {
                // Return offline page for navigation requests
                if (event.request.destination === 'document') {
                    return caches.match('/takepos/offline.html');
                }
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Background sync for offline actions
self.addEventListener('sync', (event) => {
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

function doBackgroundSync() {
    // Sync offline actions when connection is restored
    return new Promise((resolve) => {
        // Implementation for syncing offline data
        console.log('Background sync triggered');
        resolve();
    });
}

// Push notifications (if needed)
self.addEventListener('push', (event) => {
    const options = {
        body: event.data ? event.data.text() : 'New notification',
        icon: '/takepos/img/icon-192x192.png',
        badge: '/takepos/img/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'Open TakePos',
                icon: '/takepos/img/checkmark.png'
            },
            {
                action: 'close',
                title: 'Close notification',
                icon: '/takepos/img/xmark.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('TakePos', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/takepos/modern-index.php')
        );
    }
});
