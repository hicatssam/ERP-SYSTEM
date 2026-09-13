const CACHE_NAME = 'dahab-pwa-shell-v4';

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
        (async () => {
            const cached = await caches.match(request);

            // Cache hit: return it immediately and refresh the cache
            // quietly in the background. The background refresh runs
            // completely independently of what we already returned, so it
            // can never race with — or corrupt — the response the page is
            // using.
            if (cached) {
                fetch(request)
                    .then(response => {
                        if (response && response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE_NAME)
                                .then(cache => cache.put(request, copy))
                                .catch(() => {});
                        }
                    })
                    .catch(() => {});

                return cached;
            }

            // Cache miss: fetch once, clone immediately (before the
            // response body can be read anywhere else), cache the clone,
            // and return the original. Any caching failure is swallowed
            // so it never breaks the actual page/image/font load.
            try {
                const response = await fetch(request);

                if (response && response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME)
                        .then(cache => cache.put(request, copy))
                        .catch(() => {});
                }

                return response;
            } catch (error) {
                return cached || Response.error();
            }
        })()
    );
});
