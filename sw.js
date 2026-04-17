

const VERSION_CACHE = 'whataplan-v2.3';
const FICHIERS_CACHE = [
    '/',
    '/index.php',
    '/accueil.php',
    '/chat.php',
    '/scan.php',
    '/connexion.php',
    'https://lienwh.vercel.app/manifest.json',     
    '/icons/icon-192.png',
    '/icons/icon-512.png'
];

// ── Installation ──
self.addEventListener('install', event => {
    console.log(`[SW] Installation ${VERSION_CACHE}`);
    event.waitUntil(
        caches.open(VERSION_CACHE).then(cache => {
            console.log('[SW] Mise en cache des fichiers essentiels');
            return cache.addAll(FICHIERS_CACHE);
        }).then(() => self.skipWaiting())
    );
});

// ── Activation ──
self.addEventListener('activate', event => {
    console.log('[SW] Activation');
    event.waitUntil(
        caches.keys().then(cles => {
            return Promise.all(
                cles.filter(cle => cle !== VERSION_CACHE)
                    .map(cle => caches.delete(cle))
            );
        }).then(() => self.clients.claim())
    );
});

// ── Fetch - Version CORRIGÉE (sans erreur clone) ──
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Ne pas intercepter les requêtes dynamiques
    if (
        event.request.method !== 'GET' ||
        url.pathname.includes('api_chat.php') ||
        url.pathname.includes('img_proxy.php') ||
        url.pathname.includes('uploads/')
    ) {
        event.respondWith(
            fetch(event.request).catch(() => 
                new Response(JSON.stringify({ error: 'Hors ligne' }), {
                    headers: { 'Content-Type': 'application/json' }
                })
            )
        );
        return;
    }

    event.respondWith(
        caches.match(event.request).then(cachedResponse => {

            // 1. Si on a la réponse en cache → on la retourne tout de suite
            if (cachedResponse) {
                // Mise à jour en arrière-plan (silencieuse)
                fetch(event.request).then(networkResponse => {
                    if (networkResponse && networkResponse.status === 200) {
                        caches.open(VERSION_CACHE).then(cache => {
                            cache.put(event.request, networkResponse.clone());   // clone ici est safe
                        });
                    }
                }).catch(() => {});

                return cachedResponse;
            }

            // 2. Pas en cache → on va sur le réseau
            return fetch(event.request).then(networkResponse => {
                if (!networkResponse || networkResponse.status !== 200) {
                    return networkResponse;
                }

                // Clone AVANT de mettre en cache (point critique corrigé)
                const responseClone = networkResponse.clone();

                caches.open(VERSION_CACHE).then(cache => {
                    cache.put(event.request, responseClone);
                });

                return networkResponse;
            }).catch(() => {
                // Page hors ligne
                return caches.match('/index.php').then(page => {
                    return page || new Response(
                        `<!DOCTYPE html>
                        <html lang="fr">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1">
                            <title>WhatAPlant — Hors ligne</title>
                            <style>
                                body {font-family:sans-serif; background:#0d5c3a; color:white; 
                                      display:flex; flex-direction:column; align-items:center; 
                                      justify-content:center; height:100vh; text-align:center; padding:20px;}
                                h1 {margin:10px 0;}
                                button {padding:14px 32px; margin-top:20px; border:none; 
                                        border-radius:50px; background:#34d399; color:#0d5c3a; 
                                        font-weight:bold; font-size:16px; cursor:pointer;}
                            </style>
                        </head>
                        <body>
                            <div style="font-size:80px">🌿</div>
                            <h1>WhatAPlant</h1>
                            <p>Vous êtes hors ligne.<br>Vérifiez votre connexion internet.</p>
                            <button onclick="window.location.reload()">🔄 Réessayer</button>
                        </body>
                        </html>`,
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                    );
                });
            });
        })
    );
});

// ── Messages ──
self.addEventListener('message', event => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
    if (event.data === 'CLEAR_CACHE') {
        caches.delete(VERSION_CACHE).then(() => event.ports[0].postMessage('Cache supprimé'));
    }
});
