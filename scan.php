<?php
/**
 * scan.php — Scanner universel WhatAPlant
 * Accessible depuis : accueil, profil, n'importe quelle page
 * Redirige vers chat.php avec l'image scannée prête à analyser
 */
require_once 'connect_db.php';
requireLogin();

$nom_user = htmlspecialchars($_SESSION['nom']);
$retour   = $_GET['retour'] ?? 'accueil.php'; // page de retour
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d5c3a">
    <title>Scanner — WhatAPlant</title>
    <link rel="manifest" href="manifest.json">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --vert: #1b8a5e;
            --vert-f: #0d5c3a;
            --vert-c: #34d399;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #000;
            height: 100dvh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            color: white;
        }

        /* ── En-tête ── */
        .entete {
            position: fixed;
            top: 0; left: 0; right: 0;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            background: linear-gradient(to bottom, rgba(0,0,0,.7), transparent);
            z-index: 100;
        }
        .btn-retour {
            background: rgba(255,255,255,.15);
            border: none; color: white;
            width: 40px; height: 40px;
            border-radius: 50%; font-size: 18px;
            cursor: pointer; display:flex; align-items:center; justify-content:center;
            backdrop-filter: blur(4px);
            transition: background .2s;
        }
        .btn-retour:hover { background: rgba(255,255,255,.3); }
        .entete-titre { font-size: 18px; font-weight: 700; }

        /* ── Flux vidéo ── */
        #flux-video {
            width: 100vw;
            height: 100dvh;
            object-fit: cover;
            display: block;
        }

        /* ── Overlay scanner ── */
        .overlay-scan {
            position: fixed; inset: 0;
            z-index: 50;
            pointer-events: none;
        }
        /* Cadre de scan */
        .cadre-scan {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: min(280px, 75vw);
            height: min(280px, 75vw);
        }
        /* Coins du cadre */
        .coin {
            position: absolute;
            width: 30px; height: 30px;
            border-color: var(--vert-c);
            border-style: solid;
        }
        .coin.tg { top:0; left:0;  border-width:3px 0 0 3px; border-radius:8px 0 0 0; }
        .coin.td { top:0; right:0; border-width:3px 3px 0 0; border-radius:0 8px 0 0; }
        .coin.bg { bottom:0; left:0;  border-width:0 0 3px 3px; border-radius:0 0 0 8px; }
        .coin.bd { bottom:0; right:0; border-width:0 3px 3px 0; border-radius:0 0 8px 0; }
        /* Ligne de scan animée */
        .ligne-scan {
            position: absolute;
            left: 4px; right: 4px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--vert-c), transparent);
            box-shadow: 0 0 8px var(--vert-c);
            animation: scan-ligne 2s ease-in-out infinite;
            top: 0;
        }
        @keyframes scan-ligne {
            0%   { top: 4px; opacity:1; }
            50%  { top: calc(100% - 6px); opacity:1; }
            100% { top: 4px; opacity:1; }
        }
        /* Zone sombre autour du cadre */
        .masque {
            position: absolute; inset: 0;
            background: rgba(0,0,0,.55);
            -webkit-mask: url(#masque-scan);
            mask: url(#masque-scan);
        }

        /* ── Bas de page ── */
        .bas-page {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            padding: 24px 24px 40px;
            background: linear-gradient(transparent, rgba(0,0,0,.85));
            z-index: 100;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        .texte-guide {
            font-size: 14px;
            color: rgba(255,255,255,.8);
            text-align: center;
            line-height: 1.5;
        }
        .btns-action {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        /* Bouton galerie */
        .btn-galerie {
            background: rgba(255,255,255,.15);
            border: 1.5px solid rgba(255,255,255,.4);
            color: white; padding: 12px 20px;
            border-radius: 50px; font-size: 14px; font-weight: 600;
            cursor: pointer; backdrop-filter: blur(4px);
            display: flex; align-items: center; gap: 8px;
            transition: background .2s;
        }
        .btn-galerie:hover { background: rgba(255,255,255,.25); }
        /* Bouton capture */
        .btn-capture {
            width: 70px; height: 70px;
            border-radius: 50%;
            background: white;
            border: 4px solid rgba(255,255,255,.5);
            outline: 3px solid var(--vert-c);
            cursor: pointer;
            transition: transform .1s, box-shadow .2s;
            box-shadow: 0 0 20px rgba(52,211,153,.4);
        }
        .btn-capture:active { transform: scale(0.94); }
        .btn-capture.flash { background: var(--vert-c); }
        /* Bouton galerie droite */
        .btn-torch {
            background: rgba(255,255,255,.15);
            border: 1.5px solid rgba(255,255,255,.4);
            color: white; padding: 12px 16px;
            border-radius: 50px; font-size: 20px;
            cursor: pointer; backdrop-filter: blur(4px);
            transition: background .2s;
        }
        .btn-torch.on { background: rgba(255,220,0,.3); border-color: #ffd700; }

        /* ── Aperçu avant envoi ── */
        #apercu-wrap {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.9);
            z-index: 200;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
            padding: 24px;
        }
        #apercu-img {
            max-width: 90vw;
            max-height: 60vh;
            border-radius: 16px;
            border: 2px solid var(--vert-c);
        }
        .apercu-titre {
            font-size: 18px; font-weight: 700;
            color: white; text-align: center;
        }
        .apercu-sous {
            font-size: 13px; color: rgba(255,255,255,.6);
            text-align: center;
        }
        .apercu-btns {
            display: flex; gap: 14px; margin-top: 8px;
        }
        .btn-annuler {
            padding: 14px 28px;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.3);
            color: white; border-radius: 50px;
            font-size: 15px; font-weight: 600; cursor: pointer;
        }
        .btn-analyser {
            padding: 14px 28px;
            background: var(--vert);
            border: none; color: white;
            border-radius: 50px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 16px rgba(27,138,94,.4);
        }
        .btn-analyser:hover { background: var(--vert-f); }

        /* Entrée fichier cachée */
        #entree-galerie { display: none; }

        /* Message erreur caméra */
        #erreur-camera {
            display: none;
            position: fixed; inset: 0;
            background: #000;
            z-index: 300;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 24px;
            text-align: center;
        }
        .erreur-icon { font-size: 56px; }
        .erreur-titre { font-size: 20px; font-weight: 700; }
        .erreur-desc  { font-size: 14px; color: rgba(255,255,255,.6); line-height: 1.6; }
        .btn-galerie-erreur {
            padding: 14px 28px;
            background: var(--vert);
            border: none; color: white;
            border-radius: 50px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            margin-top: 8px;
        }
    </style>
</head>
<body>

<!-- En-tête -->
<div class="entete">
    <button class="btn-retour" onclick="retourner()">←</button>
    <span class="entete-titre">📷 Scanner une plante</span>
</div>

<!-- Flux caméra -->
<video id="flux-video" autoplay playsinline muted></video>
<canvas id="canvas-capture" style="display:none;"></canvas>

<!-- Overlay de scan -->
<div class="overlay-scan">
    <!-- Masque SVG pour découper la zone de scan -->
    <svg width="0" height="0" style="position:absolute">
        <defs>
            <mask id="masque-scan">
                <rect width="100%" height="100%" fill="white"/>
                <rect id="rect-masque" fill="black" rx="16"/>
            </mask>
        </defs>
    </svg>
    <div class="masque" id="masque-div"></div>
    <!-- Cadre de scan -->
    <div class="cadre-scan" id="cadre-scan">
        <div class="coin tg"></div>
        <div class="coin td"></div>
        <div class="coin bg"></div>
        <div class="coin bd"></div>
        <div class="ligne-scan"></div>
    </div>
</div>

<!-- Bas de page -->
<div class="bas-page">
    <div class="texte-guide">
        📸 Placez la plante dans le cadre<br>et appuyez sur le bouton
    </div>
    <div class="btns-action">
        <button class="btn-galerie" onclick="document.getElementById('entree-galerie').click()">
            🖼️ Galerie
        </button>
        <button class="btn-capture" id="btn-capture" onclick="capturer()"></button>
        <button class="btn-torch" id="btn-torch" onclick="basculerTorche()">🔦</button>
    </div>
</div>

<!-- Entrée galerie cachée -->
<input type="file" id="entree-galerie" accept="image/*" onchange="choisirGalerie(event)">

<!-- Aperçu avant envoi -->
<div id="apercu-wrap">
    <div class="apercu-titre">🔍 Analyser cette image ?</div>
    <img id="apercu-img" src="" alt="Aperçu">
    <div class="apercu-sous">L'IA va identifier la plante et faire un diagnostic complet</div>
    <div class="apercu-btns">
        <button class="btn-annuler" onclick="annulerApercu()">↩ Reprendre</button>
        <button class="btn-analyser" onclick="analyser()">🌿 Analyser</button>
    </div>
</div>

<!-- Erreur caméra -->
<div id="erreur-camera">
    <div class="erreur-icon">📵</div>
    <div class="erreur-titre">Caméra inaccessible</div>
    <div class="erreur-desc">
        Autorisez l'accès à la caméra dans les paramètres de votre navigateur,<br>
        ou utilisez la galerie pour choisir une photo existante.
    </div>
    <button class="btn-galerie-erreur" onclick="document.getElementById('entree-galerie').click()">
        🖼️ Choisir depuis la galerie
    </button>
    <button class="btn-galerie-erreur" style="background:rgba(255,255,255,.1);margin-top:8px;" onclick="retourner()">
        ← Retour
    </button>
</div>

<script>
let fluxCamera  = null;
let torchActive = false;
let fichierEnAttente = null;

// Page de retour
const pageRetour = '<?= htmlspecialchars($retour) ?>';

function retourner() {
    arreterCamera();
    window.location.href = pageRetour;
}

// ── Démarrer la caméra ──
async function demarrerCamera() {
    try {
        fluxCamera = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width:{ideal:1920}, height:{ideal:1080} },
            audio: false
        });
        document.getElementById('flux-video').srcObject = fluxCamera;
        positionnerCadre();
    } catch(e) {
        console.error(e);
        try {
            fluxCamera = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            document.getElementById('flux-video').srcObject = fluxCamera;
            positionnerCadre();
        } catch(e2) {
            document.getElementById('erreur-camera').style.display = 'flex';
        }
    }
}

function arreterCamera() {
    if (fluxCamera) {
        fluxCamera.getTracks().forEach(t => t.stop());
        fluxCamera = null;
    }
}

// ── Positionner le masque SVG ──
function positionnerCadre() {
    setTimeout(() => {
        const cadre = document.getElementById('cadre-scan');
        const rect  = cadre.getBoundingClientRect();
        const rm    = document.getElementById('rect-masque');
        rm.setAttribute('x', rect.left);
        rm.setAttribute('y', rect.top);
        rm.setAttribute('width', rect.width);
        rm.setAttribute('height', rect.height);
    }, 100);
}

// ── Capturer la photo ──
function capturer() {
    const video  = document.getElementById('flux-video');
    const canvas = document.getElementById('canvas-capture');
    canvas.width  = video.videoWidth  || 1280;
    canvas.height = video.videoHeight || 720;
    canvas.getContext('2d').drawImage(video, 0, 0);

    // Flash visuel
    const btn = document.getElementById('btn-capture');
    btn.classList.add('flash');
    setTimeout(() => btn.classList.remove('flash'), 200);

    canvas.toBlob(blob => {
        fichierEnAttente = new File([blob], 'scan_'+Date.now()+'.jpg', {type:'image/jpeg'});
        afficherApercu(URL.createObjectURL(blob));
    }, 'image/jpeg', 0.92);
}

// ── Choisir depuis galerie ──
function choisirGalerie(e) {
    const fichier = e.target.files[0];
    if (!fichier) return;
    fichierEnAttente = fichier;
    afficherApercu(URL.createObjectURL(fichier));
    e.target.value = '';
}

// ── Afficher aperçu ──
function afficherApercu(url) {
    document.getElementById('apercu-img').src = url;
    document.getElementById('apercu-wrap').style.display = 'flex';
}
function annulerApercu() {
    fichierEnAttente = null;
    document.getElementById('apercu-wrap').style.display = 'none';
    document.getElementById('apercu-img').src = '';
}

// ── Analyser : envoyer vers chat.php ──
function analyser() {
    if (!fichierEnAttente) return;

    // Stocker le fichier en sessionStorage pour le récupérer dans chat.php
    const lecteur = new FileReader();
    lecteur.onload = ev => {
        sessionStorage.setItem('scan_image_data', ev.target.result);
        sessionStorage.setItem('scan_image_name', fichierEnAttente.name);
        sessionStorage.setItem('scan_image_type', fichierEnAttente.type);
        arreterCamera();
        window.location.href = 'chat.php?scan=1';
    };
    lecteur.readAsDataURL(fichierEnAttente);
}

// ── Torche ──
async function basculerTorche() {
    if (!fluxCamera) return;
    const piste = fluxCamera.getVideoTracks()[0];
    if (!piste || !piste.getCapabilities) return;
    const caps = piste.getCapabilities();
    if (!caps.torch) return;
    torchActive = !torchActive;
    await piste.applyConstraints({ advanced: [{ torch: torchActive }] });
    document.getElementById('btn-torch').classList.toggle('on', torchActive);
}

// Démarrer au chargement
window.addEventListener('load', demarrerCamera);
window.addEventListener('resize', positionnerCadre);

// Arrêter la caméra si on quitte la page
window.addEventListener('beforeunload', arreterCamera);
document.addEventListener('visibilitychange', () => {
    if (document.hidden) arreterCamera();
    else demarrerCamera();
});
</script>
</body>
</html>