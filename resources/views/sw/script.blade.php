// jeffersongoncalves.dev.br service worker — generated from blade so the
// VERSION constant follows whatever AppVersion::current() reports, which
// bumps on every `release-X.Y.Z` tag. A new VERSION forces a new cache
// name on activate and tears down the old caches.
const VERSION = '{{ $version }}';
const CACHE_NAME = `jg-pwa-v${VERSION}`;
const OFFLINE_URL = '/offline';

// Pre-cache the bare minimum needed to answer a navigation request while
// offline. Both `/` and `/?source=pwa` (the manifest's `start_url`) are
// seeded so the standalone PWA launches into a working page even on a
// cold cache + no network.
const PRECACHE_URLS = [OFFLINE_URL, '/', '/?source=pwa'];

self.addEventListener('install', (event) => {
    // skipWaiting lets the newly installed SW take over without forcing
    // the user to close every tab first. Pairs with `clients.claim()`
    // below — together they make releases pick up on next navigation.
    self.skipWaiting();

    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS)),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const names = await caches.keys();
            await Promise.all(
                names
                    .filter((name) => name.startsWith('jg-pwa-') && name !== CACHE_NAME)
                    .map((name) => caches.delete(name)),
            );
            await self.clients.claim();

            // Tell every controlled page which version just activated. The
            // client decides whether to surface an update toast — it can't
            // be decided here because the SW doesn't know if this is the
            // first install (no old version) or a real upgrade.
            const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            for (const client of clients) {
                client.postMessage({ type: 'pwa-updated', version: VERSION });
            }
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only meddle with GETs from the same origin. Anything else (POST,
    // cross-origin, etc) goes straight to the network — caching it would
    // either be wrong (POST is not idempotent) or pointless.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Routes that must always hit the network. Admin / Filament panels
    // rely on Livewire WS + auth, caching them would serve stale HTML to
    // logged-in users. The SW file itself + manifest must always be live
    // so updates aren't held back by their own cached copy.
    const passthroughPrefixes = ['/admin', '/app', '/livewire', '/api', '/horizon', '/telescope', '/pulse'];
    const passthroughExact = ['/sw.js', '/manifest.json'];

    if (passthroughExact.includes(url.pathname)) return;
    if (passthroughPrefixes.some((prefix) => url.pathname === prefix || url.pathname.startsWith(`${prefix}/`))) return;

    // Hashed Vite outputs in /build are immutable (content-hashed names),
    // so cache-first is correct and there is no risk of serving stale
    // assets — the HTML changes its references on the next release.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // HTML navigations: network-first so users always see fresh content
    // when online, with cached fallback (and finally /offline) when not.
    if (request.mode === 'navigate' || request.destination === 'document') {
        event.respondWith(networkFirst(request));
        return;
    }

    // Everything else (images, fonts, manifest pieces) — stale-while-
    // revalidate so the page renders instantly off cache while a fresh
    // copy is fetched in the background.
    event.respondWith(staleWhileRevalidate(request));
});

async function cacheFirst(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const fresh = await fetch(request);

    if (fresh && fresh.ok) {
        cache.put(request, fresh.clone());
    }

    return fresh;
}

async function networkFirst(request) {
    const cache = await caches.open(CACHE_NAME);

    try {
        const fresh = await fetch(request);

        if (fresh && fresh.ok) {
            cache.put(request, fresh.clone());
        }

        return fresh;
    } catch (error) {
        const cached = await cache.match(request);

        if (cached) {
            return cached;
        }

        const offline = await cache.match(OFFLINE_URL);

        return (
            offline ||
            new Response('Offline', {
                status: 503,
                statusText: 'Service Unavailable',
                headers: { 'Content-Type': 'text/plain' },
            })
        );
    }
}

// Web Push handler. Payloads ship as JSON `{title, body, url, tag}` so
// `JSON.parse` covers the entire shape; legacy plain-text payloads are
// rendered as the body. The notification is shown via `showNotification`
// inside `waitUntil` so the SW stays alive until the OS finishes rendering.
self.addEventListener('push', (event) => {
    let payload = { title: 'Jefferson Gonçalves', body: '', url: '/' };

    if (event.data) {
        try {
            payload = Object.assign(payload, event.data.json());
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body || '',
            icon: '{{ $pushIcon }}',
            badge: '{{ $pushIcon }}',
            tag: payload.tag || 'jg-push',
            data: { url: payload.url || '/' },
        }),
    );
});

// Focus an existing tab on click instead of opening a new one. Falls back
// to `clients.openWindow` only when no tab is open.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        (async () => {
            const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            for (const client of all) {
                if ('focus' in client) {
                    client.navigate(target);
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(target);
            }
        })(),
    );
});

async function staleWhileRevalidate(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request)
        .then((response) => {
            if (response && response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        })
        .catch(() => cached);

    return cached || fetchPromise;
}
