/**
 * sw.js — WhatAPlant PWA Service Worker v3
 * Hors ligne : vraie page sans rediriger vers le navigateur
 */
const CACHE_NOM      = 'whataaplant-v3';
const CACHE_STATIQUE = 'whataaplant-static-v3';

const PAGES_CORE = ['/', '/index.php', '/connexion.php', '/manifest.json',
    '/icons/icon-192.png', '/icons/icon-512.png'];

const PAGE_OFFLINE = `<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0d5c3a">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>WhatAPlant</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,sans-serif;background:linear-gradient(160deg,#0d5c3a,#1b8a5e 60%,#34d399);
min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;align-items:center;
justify-content:center;color:white;padding:32px 24px;text-align:center;gap:20px}
.logo{font-size:80px;animation:bal 3s ease-in-out infinite}
@keyframes bal{0%,100%{transform:rotate(-5deg)}50%{transform:rotate(5deg)}}
h1{font-size:28px;font-weight:800}
.sous{font-size:14px;opacity:.85;max-width:300px;line-height:1.6}
.carte{background:rgba(255,255,255,.15);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.25);
border-radius:20px;padding:24px;max-width:340px;width:100%}
.carte h2{font-size:16px;font-weight:700;margin-bottom:12px}
.carte p{font-size:13px;opacity:.85;line-height:1.7}
.btn{background:white;color:#0d5c3a;border:none;padding:16px 32px;border-radius:50px;
font-size:16px;font-weight:800;cursor:pointer;font-family:inherit;width:100%;max-width:280px;transition:transform .2s}
.btn:active{transform:scale(.97)}
.dot{display:inline-block;width:8px;height:8px;background:#fbbf24;border-radius:50%;
margin-right:6px;animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
.status{font-size:12px;opacity:.7;margin-top:-8px}
</style></head><body>
<div class="logo">&#127807;</div>
<h1>WhatAPlant</h1>
<p class="sous">L'IA botanique pour l'Afrique</p>
<div class="carte">
  <h2>&#128225; Pas de connexion internet</h2>
  <p>Connectez-vous pour identifier vos plantes, analyser vos cultures et trouver des remèdes naturels africains.</p>
</div>
<button class="btn" onclick="retry()">&#128260; Réessayer</button>
<p class="status" id="st"><span class="dot"></span>En attente de connexion...</p>
<script>
function retry(){
  var b=document.querySelector('.btn');
  b.textContent='⏳ Vérification...'; b.disabled=true;
  fetch('/',{method:'HEAD',cache:'no-cache',signal:AbortSignal.timeout(5000)})
    .then(function(){window.location.href='/';})
    .catch(function(){b.textContent='🔄 Réessayer';b.disabled=false;
      document.getElementById('st').innerHTML='<span class="dot"></span>Toujours hors ligne...';});
}
setInterval(function(){
  fetch('/',{method:'HEAD',cache:'no-cache',signal:AbortSignal.timeout(3000)})
    .then(function(){window.location.href='/';}).catch(function(){});
},10000);
window.addEventListener('online',function(){setTimeout(function(){window.location.href='/';},500);});
</script></body></html>`;

self.addEventListener('install', e => {
    e.waitUntil(
        Promise.all([
            caches.open(CACHE_STATIQUE).then(c => c.addAll(PAGES_CORE).catch(()=>{})),
            caches.open(CACHE_NOM).then(c => c.put('/__offline__',
                new Response(PAGE_OFFLINE, {headers:{'Content-Type':'text/html;charset=utf-8'}})))
        ]).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k!==CACHE_NOM && k!==CACHE_STATIQUE).map(k=>caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET') return;
    if (url.pathname.includes('api_chat.php')) return;
    if (url.pathname.includes('/uploads/')) return;
    if (!url.hostname.includes(self.location.hostname) &&
        !url.hostname.includes('railway.app') &&
        !url.hostname.includes('localhost')) return;

    e.respondWith(
        fetch(e.request).then(rep => {
            if (rep && rep.status===200) {
                const clone = rep.clone();
                const isPHP = url.pathname.endsWith('.php') &&
                    !['index.php','connexion.php'].some(p=>url.pathname.endsWith(p));
                if (!isPHP) caches.open(CACHE_NOM).then(c=>c.put(e.request,clone));
            }
            return rep;
        }).catch(async () => {
            const cached = await caches.match(e.request);
            if (cached) return cached;
            const accept = e.request.headers.get('Accept')||'';
            if (accept.includes('text/html')) return caches.match('/__offline__');
            return new Response('',{status:503});
        })
    );
});
