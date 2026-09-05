/*
 * Service worker de Focusyn.
 *
 * Portée volontairement étroite pour cette étape : rendre l'application
 * installable et survivre à une coupure réseau, sans prétendre à un
 * fonctionnement hors ligne complet — l'écriture hors ligne exigerait une file
 * de synchronisation et une résolution de conflits, écartées à la conception.
 *
 * Deux stratégies :
 *   - navigation : réseau d'abord, page « hors ligne » en secours ;
 *   - /assets/*  : cache d'abord, car AssetMapper versionne chaque fichier par
 *                  un condensat — une URL donnée ne change jamais de contenu.
 */

const VERSION = 'v1';
const SHELL_CACHE = `focusyn-shell-${VERSION}`;
const ASSET_CACHE = `focusyn-assets-${VERSION}`;
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(SHELL_CACHE);
            await cache.add(new Request(OFFLINE_URL, { cache: 'reload' }));
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(
                keys
                    .filter((key) => key !== SHELL_CACHE && key !== ASSET_CACHE)
                    .map((key) => caches.delete(key)),
            );
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Ne jamais s'interposer devant les outils de développement.
    if (url.pathname.startsWith('/_')) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            (async () => {
                try {
                    return await fetch(request);
                } catch {
                    const offline = await caches.match(OFFLINE_URL);

                    return offline ?? Response.error();
                }
            })(),
        );

        return;
    }

    if (url.pathname.startsWith('/assets/')) {
        event.respondWith(
            (async () => {
                const cached = await caches.match(request);

                if (cached) {
                    return cached;
                }

                const response = await fetch(request);

                if (response.ok) {
                    const cache = await caches.open(ASSET_CACHE);
                    await cache.put(request, response.clone());
                }

                return response;
            })(),
        );
    }
});
