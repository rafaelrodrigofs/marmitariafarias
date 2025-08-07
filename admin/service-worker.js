self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('push', (event) => {
    const options = {
        body: event.data ? event.data.text() : 'Nova notificação!',
        icon: '/img/android-chrome-192x192.png',
        badge: '/img/android-chrome-192x192.png',
        vibrate: [200, 100, 200],
        tag: 'marmitariafarias-notification',
        renotify: true,
        sound: '/notification-sound.mp3'
    };
    
    event.waitUntil(
        self.registration.showNotification('Marmitaria Farias', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        clients.openWindow('/admin/views/dashboard.php')
    );
}); 