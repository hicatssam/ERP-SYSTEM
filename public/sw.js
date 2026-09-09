const CACHE_NAME = 'dahab-pwa-shell-v3';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(name => name !== CACHE_NAME)
                    .map(name => caches.delete(name))
            );
        })
    );

    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const request = event.request;

    if (request.method !== 'GET' || request.mode === 'navigate') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (
        url.pathname.startsWith('/api/')
        || url.pathname.startsWith('/login')
        || url.pathname.startsWith('/logout')
    ) {
        return;
    }

    const cacheableDestination = [
        'style',
        'script',
        'image',
        'font',
        'manifest'
    ].includes(request.destination);

    const isPwaAsset = url.pathname.startsWith('/pwa/');

    if (! cacheableDestination && ! isPwaAsset) {
        return;
    }

    event.respondWith(
        caches.match(request).then(cachedResponse => {
            const networkRequest = fetch(request).then(response => {
                if (response && response.ok) {
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(request, response.clone());
                    });
                }

                return response;
            });

            return cachedResponse || networkRequest;
        })
    );
});
