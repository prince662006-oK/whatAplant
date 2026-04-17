/**
 * sw.js — Service Worker WhatAPlant (Version Racine)
 */

const VERSION_CACHE = 'whataplan-v1.4';   // Incrémente à chaque mise à jour

const FICHIERS_CACHE = [
    '/',
    '/index.php',
    '/accueil.php',
    '/chat.php',
    '/scan.php',
    '/connexion.php',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png'
    // Ajoute ici tes fichiers CSS, JS statiques si nécessaire
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
                cles.filter(cle => cle !== VERSION_CACHE).map(cle => caches.delete(cle))
            );
        }).then(() => self.clients.claim())
    );
});

// ── Fetch ──
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Ignorer les requêtes POST, API dynamiques et uploads
    if (
        event.request.method !== 'GET' ||
        url.pathname.includes('api_chat.php') ||
        url.pathname.includes('img_proxy.php') ||
        url.pathname.includes('uploads/')
    ) {
        event.respondWith(fetch(event.request).catch(() => 
            new Response(JSON.stringify({ error: 'Hors ligne' }), { 
                headers: { 'Content-Type': 'application/json' } 
            })
        ));
        return;
    }

    // Stratégie Cache First + mise à jour en arrière-plan
    event.respondWith(
        caches.match(event.request).then(reponseCache => {
            const fetchPromise = fetch(event.request).then(reponseReseau => {
                if (reponseReseau && reponseReseau.status === 200) {
                    caches.open(VERSION_CACHE).then(cache => {
                        cache.put(event.request, reponseReseau.clone());
                    });
                }
                return reponseReseau;
            }).catch(() => null);

            return reponseCache || fetchPromise.then(res => res || 
                caches.match('/index.php').then(page => 
                    page || new Response(
                        `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
                        <title>WhatAPlant — Hors ligne</title>
                        <style>
                            body{font-family:sans-serif;background:#0d5c3a;color:white;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;text-align:center;padding:20px;}
                            h1{margin:10px 0;} button{padding:12px 28px;margin-top:20px;border:none;border-radius:50px;background:#34d399;color:#0d5c3a;font-weight:bold;font-size:16px;}
                        </style></head>
                        <body>
                            <div style="font-size:80px">🌿</div>
                            <h1>WhatAPlant</h1>
                            <p>Vous êtes hors ligne.<br>Vérifiez votre connexion internet.</p>
                            <button onclick="window.location.reload()">🔄 Réessayer</button>
                        </body></html>`,
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                    )
                )
            );
        })
    );
});

// Messages pour mise à jour
self.addEventListener('message', event => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
    if (event.data === 'CLEAR_CACHE') {
        caches.delete(VERSION_CACHE).then(() => event.ports[0].postMessage('Cache supprimé'));
    }
});
