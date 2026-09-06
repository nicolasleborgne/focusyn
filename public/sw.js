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

/*
 * Notifications poussées.
 *
 * La charge arrive chiffrée avec les clés de cet appareil : le service de
 * notification l'a transportée sans pouvoir la lire. Si elle manque ou n'est
 * pas du JSON, on affiche quand même quelque chose — une notification vide vaut
 * mieux qu'un rappel perdu.
 */
self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = {};
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Focusyn', {
            body: payload.body || '',
            // Le même rappel poussé deux fois remplace la première notification
            // au lieu de s'empiler.
            tag: payload.tag || 'focusyn-reminder',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            data: { url: payload.url || '/' },
        }),
    );
});

/*
 * Un clic ramène dans l'onglet déjà ouvert plutôt que d'en ouvrir un de plus.
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = new URL(event.notification.data?.url || '/', self.location.origin);

    event.waitUntil(
        (async () => {
            const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

            for (const client of clients) {
                if (new URL(client.url).origin === target.origin && 'focus' in client) {
                    await client.focus();
                    return client.navigate ? client.navigate(target.href) : undefined;
                }
            }

            return self.clients.openWindow(target.href);
        })(),
    );
});
