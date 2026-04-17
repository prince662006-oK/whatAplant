/**
 * sw.js — Service Worker WhatAPlant
 * Gère le cache offline et les notifications push
 */

const VERSION_CACHE = 'whataplan-v1.2';

// Fichiers à mettre en cache pour le mode hors ligne
const FICHIERS_CACHE = [
    '/projet/index.php',
    '/projet/connexion.php',
    '/projet/accueil.php',
    '/projet/chat.php',
    '/projet/scan.php',
    '/projet/manifest.json',
    '/projet/icons/icon-192.png',
    '/projet/icons/icon-512.png',
];

// ── Installation : mise en cache des fichiers essentiels ──
self.addEventListener('install', event => {
    console.log('[SW] Installation WhatAPlant v1.2');
    event.waitUntil(
        caches.open(VERSION_CACHE).then(cache => {
            console.log('[SW] Mise en cache des fichiers essentiels');
            return cache.addAll(FICHIERS_CACHE).catch(err => {
                console.warn('[SW] Certains fichiers non cachés:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// ── Activation : supprimer les anciens caches ──
self.addEventListener('activate', event => {
    console.log('[SW] Activation');
    event.waitUntil(
        caches.keys().then(cles => {
            return Promise.all(
                cles.filter(cle => cle !== VERSION_CACHE)
                    .map(cle => {
                        console.log('[SW] Suppression ancien cache:', cle);
                        return caches.delete(cle);
                    })
            );
        }).then(() => self.clients.claim())
    );
});

// ── Fetch : stratégie Cache puis Réseau ──
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Ne pas cacher les requêtes API, images dynamiques et POST
    if (
        event.request.method === 'POST' ||
        url.pathname.includes('api_chat.php') ||
        url.pathname.includes('img_proxy.php') ||
        url.pathname.includes('uploads/')
    ) {
        // Réseau uniquement pour les requêtes dynamiques
        event.respondWith(
            fetch(event.request).catch(() => {
                return new Response(
                    JSON.stringify({ error: 'Pas de connexion internet. Vérifiez votre réseau.' }),
                    { headers: { 'Content-Type': 'application/json' } }
                );
            })
        );
        return;
    }

    // Stratégie : Cache d'abord, puis réseau (pour les pages)
    event.respondWith(
        caches.match(event.request).then(reponseCache => {
            if (reponseCache) {
                // Mettre à jour en arrière-plan
                fetch(event.request).then(reponseReseau => {
                    if (reponseReseau && reponseReseau.status === 200) {
                        caches.open(VERSION_CACHE).then(cache => {
                            cache.put(event.request, reponseReseau.clone());
                        });
                    }
                }).catch(() => {});
                return reponseCache;
            }

            // Pas en cache → réseau
            return fetch(event.request).then(reponseReseau => {
                if (!reponseReseau || reponseReseau.status !== 200) return reponseReseau;
                const cloneReponse = reponseReseau.clone();
                caches.open(VERSION_CACHE).then(cache => {
                    cache.put(event.request, cloneReponse);
                });
                return reponseReseau;
            }).catch(() => {
                // Page hors ligne
                return caches.match('/projet/accueil.php').then(page => {
                    return page || new Response(
                        `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width">
                        <title>WhatAPlant — Hors ligne</title>
                        <style>
                            body{font-family:sans-serif;background:#0d5c3a;color:white;
                            display:flex;flex-direction:column;align-items:center;
                            justify-content:center;height:100vh;gap:16px;padding:24px;text-align:center;}
                            h1{font-size:28px;} p{opacity:.8;line-height:1.6;}
                            button{background:#34d399;border:none;color:#0d5c3a;padding:14px 28px;
                            border-radius:50px;font-size:16px;font-weight:700;cursor:pointer;margin-top:8px;}
                        </style></head>
                        <body>
                            <div style="font-size:64px">🌿</div>
                            <h1>WhatAPlant</h1>
                            <p>Vous êtes hors ligne.<br>Vérifiez votre connexion internet<br>pour utiliser l'application.</p>
                            <button onclick="window.location.reload()">🔄 Réessayer</button>
                        </body></html>`,
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                    );
                });
            });
        })
    );
});

// ── Message du client (mise à jour manuelle) ──
self.addEventListener('message', event => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    if (event.data === 'CLEAR_CACHE') {
        caches.delete(VERSION_CACHE).then(() => {
            event.ports[0].postMessage('Cache supprimé');
        });
    }
});