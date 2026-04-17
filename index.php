<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d5c3a">
    <title>WhatAPlant</title>
    <link rel="manifest" href="manifest.php">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0d5c3a;
            height: 100dvh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .scenes {
            position: fixed; inset: 0;
            z-index: 0;
        }

        .scene {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            opacity: 0;
            transition: opacity 1s ease;
        }
        .scene.active { opacity: 1; }

        /* Scène 1 — Chercheur qui scanne */
        .scene-1 { background: linear-gradient(160deg, #052e1c 0%, #0d5c3a 50%, #1b8a5e 100%); }

        /* Scène 2 — Famille qui observe une plante */
        .scene-2 { background: linear-gradient(160deg, #1a3a0d 0%, #2d6a1f 50%, #4a9e35 100%); }

        /* Scène 3 — Agriculteur dans son champ */
        .scene-3 { background: linear-gradient(160deg, #0a2e1a 0%, #1b6b3a 50%, #34d399 100%); }

        .illustration {
            width: min(320px, 85vw);
            height: min(320px, 85vw);
            position: relative;
        }

        .logo-wrap {
            position: relative; z-index: 10;
            text-align: center;
            animation: logoEntrance 1s ease forwards;
        }
        @keyframes logoEntrance {
            from { opacity:0; transform: scale(0.7) translateY(30px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }

        .logo-icon {
            font-size: 72px;
            display: block;
            margin-bottom: 12px;
            animation: sway 3s ease-in-out infinite;
            filter: drop-shadow(0 0 24px rgba(52,211,153,.5));
        }
        @keyframes sway {
            0%,100% { transform: rotate(-6deg) scale(1); }
            50%      { transform: rotate(6deg) scale(1.05); }
        }

        .logo-name {
            font-size: 36px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 1px;
            text-shadow: 0 2px 20px rgba(0,0,0,.4);
        }
        .logo-tagline {
            font-size: 14px;
            color: rgba(255,255,255,.7);
            margin-top: 6px;
            letter-spacing: .5px;
        }

        /* ── Scène SVG ── */
        .scene-svg {
            position: absolute; inset: 0;
            display: flex; align-items: flex-end; justify-content: center;
            padding-bottom: 0;
            pointer-events: none;
        }

        /* ── Contenu principal ── */
        .contenu {
            position: relative; z-index: 10;
            display: flex; flex-direction: column; align-items: center;
            gap: 40px;
            width: 100%;
            padding: 40px 24px;
        }

        /* ── Barre de progression ── */
        .progress-wrap {
            width: min(260px, 70vw);
            position: relative;
            z-index: 10;
        }
        .progress-bar {
            width: 100%;
            height: 3px;
            background: rgba(255,255,255,.2);
            border-radius: 50px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #34d399, #a7f3d0);
            border-radius: 50px;
            width: 0%;
            animation: progressAnim 8s linear forwards;
        }
        @keyframes progressAnim { to { width: 100%; } }

        .progress-label {
            text-align: center;
            color: rgba(255,255,255,.6);
            font-size: 12px;
            margin-top: 10px;
            letter-spacing: 1px;
        }

        /* ── Points indicateurs de scène ── */
        .dots {
            display: flex; gap: 8px;
            position: relative; z-index: 10;
        }
        .dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,.3);
            transition: all .4s;
        }
        .dot.active {
            background: #34d399;
            transform: scale(1.3);
            box-shadow: 0 0 8px rgba(52,211,153,.6);
        }

        /* ── Animation plantes en bas ── */
        .plantes-deco {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 180px;
            pointer-events: none;
            z-index: 1;
        }

        /* ── Particules flottantes ── */
        .particule {
            position: fixed;
            font-size: 20px;
            animation: flotter linear infinite;
            opacity: 0;
            z-index: 1;
        }
        @keyframes flotter {
            0%   { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10%  { opacity: .7; }
            90%  { opacity: .7; }
            100% { transform: translateY(-20vh) rotate(360deg); opacity: 0; }
        }

        .scene-texte {
            position: fixed;
            bottom: 100px;
            left: 0; right: 0;
            text-align: center;
            z-index: 10;
            padding: 0 24px;
            animation: texteChange .5s ease;
        }
        @keyframes texteChange {
            from { opacity:0; transform: translateY(10px); }
            to   { opacity:1; transform: translateY(0); }
        }
        .scene-titre {
            font-size: 18px;
            font-weight: 700;
            color: white;
            margin-bottom: 6px;
            text-shadow: 0 2px 10px rgba(0,0,0,.5);
        }
        .scene-desc {
            font-size: 13px;
            color: rgba(255,255,255,.7);
            line-height: 1.5;
        }

        /* ── Overlay sombre en bas ── */
        .overlay-bas {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 250px;
            background: linear-gradient(transparent, rgba(0,0,0,.5));
            z-index: 5;
            pointer-events: none;
        }
    </style>
</head>
<body>

<!-- Particules flottantes -->
<div id="particules"></div>

<!-- Scènes d'arrière-plan -->
<div class="scenes" id="scenes">

    <!-- Scène 1 : Scan d'une plante -->
    <div class="scene scene-1 active" id="scene-0">
        <svg width="100%" height="100%" viewBox="0 0 400 600" preserveAspectRatio="xMidYMax meet">
            <!-- Fond jungle -->
            <defs>
                <radialGradient id="glow1" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#34d399" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#0d5c3a" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <ellipse cx="200" cy="400" rx="220" ry="220" fill="url(#glow1)"/>
            <!-- Plantes décoratives -->
            <g opacity="0.6">
                <!-- Feuille gauche grande -->
                <path d="M10,580 Q30,480 80,440 Q60,500 10,580Z" fill="#1b8a5e"/>
                <path d="M10,580 Q50,500 80,440" stroke="#0d5c3a" stroke-width="2" fill="none"/>
                <!-- Feuille droite grande -->
                <path d="M390,570 Q370,470 320,430 Q340,490 390,570Z" fill="#1b8a5e"/>
                <path d="M390,570 Q350,490 320,430" stroke="#0d5c3a" stroke-width="2" fill="none"/>
                <!-- Feuilles supplémentaires -->
                <path d="M0,520 Q40,430 90,410 Q70,460 0,520Z" fill="#34d399" opacity="0.4"/>
                <path d="M400,510 Q360,420 310,400 Q330,450 400,510Z" fill="#34d399" opacity="0.4"/>
            </g>
            <!-- Personnage qui scanne -->
            <g transform="translate(160, 260)">
                <!-- Corps -->
                <ellipse cx="40" cy="140" rx="30" ry="40" fill="#1b5e3d"/>
                <!-- Tête -->
                <circle cx="40" cy="90" r="28" fill="#f5c5a3"/>
                <!-- Cheveux -->
                <ellipse cx="40" cy="68" rx="28" ry="12" fill="#3d2000"/>
                <!-- Bras gauche tenant le téléphone -->
                <path d="M15,120 Q-10,140 -15,160" stroke="#f5c5a3" stroke-width="10" fill="none" stroke-linecap="round"/>
                <!-- Téléphone -->
                <rect x="-35" y="155" width="28" height="48" rx="4" fill="#1a1a2e"/>
                <rect x="-32" y="158" width="22" height="38" rx="2" fill="#34d399" opacity="0.8"/>
                <!-- Rayon scanner animé -->
                <line x1="-21" y1="177" x2="-21" y2="177" stroke="#00ff88" stroke-width="2" opacity="0.9">
                    <animate attributeName="y2" values="165;195;165" dur="1.5s" repeatCount="indefinite"/>
                    <animate attributeName="opacity" values="1;0.3;1" dur="1.5s" repeatCount="indefinite"/>
                </line>
                <!-- Bras droit -->
                <path d="M65,120 Q85,140 80,155" stroke="#f5c5a3" stroke-width="10" fill="none" stroke-linecap="round"/>
                <!-- Jambes -->
                <path d="M25,175 L20,230" stroke="#0d3d26" stroke-width="12" fill="none" stroke-linecap="round"/>
                <path d="M55,175 L60,230" stroke="#0d3d26" stroke-width="12" fill="none" stroke-linecap="round"/>
                <!-- Plante scannée -->
                <path d="M85,200 Q110,160 130,140 Q140,180 120,210 Q100,220 85,200Z" fill="#34d399"/>
                <path d="M85,200 Q120,180 140,155" stroke="#1b8a5e" stroke-width="2" fill="none"/>
                <!-- Particules scan -->
                <circle cx="110" cy="170" r="3" fill="#00ff88" opacity="0.8">
                    <animate attributeName="r" values="2;5;2" dur="1s" repeatCount="indefinite"/>
                    <animate attributeName="opacity" values="0.8;0.2;0.8" dur="1s" repeatCount="indefinite"/>
                </circle>
                <circle cx="125" cy="155" r="2" fill="#a7f3d0" opacity="0.6">
                    <animate attributeName="r" values="1;4;1" dur="1.3s" repeatCount="indefinite"/>
                </circle>
            </g>
        </svg>
    </div>

    <!-- Scène 2 : Famille qui observe une plante -->
    <div class="scene scene-2" id="scene-1">
        <svg width="100%" height="100%" viewBox="0 0 400 600" preserveAspectRatio="xMidYMax meet">
            <defs>
                <radialGradient id="glow2" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#a7f3d0" stop-opacity="0.2"/>
                    <stop offset="100%" stop-color="#1a3a0d" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <ellipse cx="200" cy="380" rx="250" ry="200" fill="url(#glow2)"/>
            <!-- Sol avec herbe -->
            <path d="M0,580 Q100,540 200,550 Q300,560 400,540 L400,600 L0,600Z" fill="#1a4d1a"/>
            <!-- Grand arbre/plante central -->
            <path d="M200,200 L200,420" stroke="#5d3d1e" stroke-width="18" fill="none" stroke-linecap="round"/>
            <!-- Feuilles de l'arbre -->
            <circle cx="200" cy="170" r="75" fill="#2d8a2d" opacity="0.9"/>
            <circle cx="160" cy="200" r="55" fill="#34a034" opacity="0.8"/>
            <circle cx="240" cy="200" r="55" fill="#34a034" opacity="0.8"/>
            <circle cx="200" cy="140" r="45" fill="#4ac44a" opacity="0.7"/>
            <!-- Fruits sur l'arbre -->
            <circle cx="180" cy="175" r="10" fill="#ff6b35"/>
            <circle cx="215" cy="160" r="9" fill="#ff6b35"/>
            <circle cx="195" cy="195" r="8" fill="#ffaa00"/>
            <!-- Personnage 1 : Adulte -->
            <g transform="translate(80, 300)">
                <ellipse cx="30" cy="120" rx="25" ry="35" fill="#8b4513"/>
                <circle cx="30" cy="80" r="24" fill="#c8a07a"/>
                <ellipse cx="30" cy="60" rx="24" ry="10" fill="#1a0a00"/>
                <path d="M8,105 Q-5,130 0,150" stroke="#c8a07a" stroke-width="8" fill="none" stroke-linecap="round"/>
                <path d="M52,105 Q65,125 60,148" stroke="#c8a07a" stroke-width="8" fill="none" stroke-linecap="round"/>
                <path d="M18,155 L15,210" stroke="#5a3010" stroke-width="10" fill="none" stroke-linecap="round"/>
                <path d="M42,155 L45,210" stroke="#5a3010" stroke-width="10" fill="none" stroke-linecap="round"/>
                <!-- Bulle de dialogue -->
                <rect x="-20" y="30" width="80" height="30" rx="8" fill="white" opacity="0.9"/>
                <polygon points="15,60 5,70 25,60" fill="white" opacity="0.9"/>
                <text x="20" y="50" text-anchor="middle" font-size="18" fill="#1b8a5e">👁️🌿</text>
            </g>
            <!-- Personnage 2 : Enfant -->
            <g transform="translate(250, 340)">
                <ellipse cx="25" cy="95" rx="20" ry="28" fill="#2d6a9f"/>
                <circle cx="25" cy="65" r="20" fill="#f5c5a3"/>
                <ellipse cx="25" cy="48" rx="20" ry="9" fill="#3d2000"/>
                <path d="M8,88 Q-5,108 0,125" stroke="#f5c5a3" stroke-width="7" fill="none" stroke-linecap="round"/>
                <path d="M42,88 Q52,105 48,122" stroke="#f5c5a3" stroke-width="7" fill="none" stroke-linecap="round"/>
                <path d="M15,122 L12,170" stroke="#1a3d6e" stroke-width="8" fill="none" stroke-linecap="round"/>
                <path d="M35,122 L38,170" stroke="#1a3d6e" stroke-width="8" fill="none" stroke-linecap="round"/>
                <!-- Téléphone levé -->
                <rect x="44" y="100" width="22" height="36" rx="3" fill="#1a1a2e"/>
                <rect x="46" y="103" width="18" height="28" rx="2" fill="#34d399" opacity="0.8"/>
            </g>
        </svg>
    </div>

    <!-- Scène 3 : Agriculteur dans son champ -->
    <div class="scene scene-3" id="scene-2">
        <svg width="100%" height="100%" viewBox="0 0 400 600" preserveAspectRatio="xMidYMax meet">
            <defs>
                <radialGradient id="sun" cx="75%" cy="20%" r="30%">
                    <stop offset="0%" stop-color="#ffd700" stop-opacity="0.4"/>
                    <stop offset="100%" stop-color="#1b6b3a" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <!-- Ciel et soleil -->
            <rect width="400" height="600" fill="url(#sun)"/>
            <!-- Sol du champ -->
            <path d="M0,450 Q100,420 200,430 Q300,440 400,420 L400,600 L0,600Z" fill="#3d2000" opacity="0.8"/>
            <!-- Rangées de plantes (manioc, maïs...) -->
            <g opacity="0.85">
                <!-- Rangée 1 -->
                <path d="M20,450 Q20,380 40,340 Q30,380 55,420 Q40,390 60,430 Q50,395 70,445" stroke="#2d8a2d" stroke-width="3" fill="none"/>
                <ellipse cx="40" cy="335" rx="22" ry="15" fill="#4ac44a" transform="rotate(-15 40 335)"/>
                <ellipse cx="56" cy="318" rx="18" ry="12" fill="#34d399" transform="rotate(10 56 318)"/>
                <!-- Rangée 2 -->
                <path d="M100,440 Q100,365 120,325 Q110,368 135,408" stroke="#2d8a2d" stroke-width="3" fill="none"/>
                <ellipse cx="120" cy="320" rx="22" ry="15" fill="#4ac44a" transform="rotate(-10 120 320)"/>
                <ellipse cx="138" cy="305" rx="18" ry="12" fill="#34d399" transform="rotate(15 138 305)"/>
                <!-- Rangée 3 (avec fruit visible) -->
                <path d="M180,435 Q180,355 200,315" stroke="#5d3d1e" stroke-width="4" fill="none"/>
                <ellipse cx="200" cy="308" rx="25" ry="18" fill="#4ac44a"/>
                <ellipse cx="178" cy="320" rx="20" ry="14" fill="#34d399" transform="rotate(-20 178 320)"/>
                <ellipse cx="222" cy="318" rx="20" ry="14" fill="#34d399" transform="rotate(20 222 318)"/>
                <!-- Fruit -->
                <ellipse cx="200" cy="370" rx="18" ry="28" fill="#ffaa00"/>
                <ellipse cx="200" cy="370" rx="14" ry="24" fill="#ffd700" opacity="0.5"/>
                <!-- Rangée 4 -->
                <path d="M280,442 Q280,362 300,322" stroke="#2d8a2d" stroke-width="3" fill="none"/>
                <ellipse cx="300" cy="316" rx="22" ry="15" fill="#4ac44a" transform="rotate(-5 300 316)"/>
                <!-- Rangée 5 -->
                <path d="M350,448 Q355,368 370,330" stroke="#2d8a2d" stroke-width="3" fill="none"/>
                <ellipse cx="370" cy="324" rx="20" ry="14" fill="#4ac44a" transform="rotate(12 370 324)"/>
            </g>
            <!-- Agriculteur -->
            <g transform="translate(60, 300)">
                <!-- Chapeau -->
                <ellipse cx="30" cy="72" rx="38" ry="12" fill="#c8960a"/>
                <path d="M8,72 Q30,50 52,72Z" fill="#e6ac1a"/>
                <!-- Corps avec vêtements traditionnels -->
                <ellipse cx="30" cy="140" rx="26" ry="40" fill="#e67e22"/>
                <!-- Motif tissu -->
                <path d="M10,125 Q30,120 50,125 M10,140 Q30,135 50,140 M10,155 Q30,150 50,155" stroke="#c0392b" stroke-width="2" fill="none"/>
                <!-- Tête -->
                <circle cx="30" cy="90" r="25" fill="#c8a07a"/>
                <!-- Bras avec machette/outil -->
                <path d="M5,120 Q-15,145 -20,165" stroke="#c8a07a" stroke-width="9" fill="none" stroke-linecap="round"/>
                <path d="M-20,158 L-35,140" stroke="#888" stroke-width="4" fill="none" stroke-linecap="round"/>
                <path d="M-35,140 L-18,152" stroke="#aaa" stroke-width="3" fill="none"/>
                <!-- Bras droit levé -->
                <path d="M55,120 Q70,100 75,90" stroke="#c8a07a" stroke-width="9" fill="none" stroke-linecap="round"/>
                <!-- Jambes -->
                <path d="M18,178 L14,240" stroke="#5a3010" stroke-width="11" fill="none" stroke-linecap="round"/>
                <path d="M42,178 L46,240" stroke="#5a3010" stroke-width="11" fill="none" stroke-linecap="round"/>
                <!-- Téléphone dans la main droite -->
                <rect x="68" y="80" width="24" height="38" rx="4" fill="#1a1a2e"/>
                <rect x="70" y="83" width="20" height="30" rx="2" fill="#34d399" opacity="0.9"/>
                <!-- Icône plante sur écran -->
                <text x="80" y="102" text-anchor="middle" font-size="14" fill="white">🌿</text>
                <!-- Bulle résultat IA -->
                <rect x="80" y="40" width="100" height="35" rx="8" fill="white" opacity="0.92"/>
                <polygon points="82,75 75,85 92,75" fill="white" opacity="0.92"/>
                <text x="130" y="55" text-anchor="middle" font-size="11" fill="#0d5c3a" font-weight="bold">✅ Manioc sain</text>
                <text x="130" y="68" text-anchor="middle" font-size="10" fill="#1b8a5e">Récolte dans 14j</text>
            </g>
        </svg>
    </div>
</div>

<!-- Overlay gradient bas -->
<div class="overlay-bas"></div>

<!-- Plantes décoratives bas -->
<svg class="plantes-deco" viewBox="0 0 400 180" preserveAspectRatio="xMidYMax meet" xmlns="http://www.w3.org/2000/svg">
    <path d="M0,180 Q20,120 60,100 Q40,140 0,180Z" fill="#1b8a5e" opacity="0.5"/>
    <path d="M0,180 Q40,130 80,115 Q60,150 0,180Z" fill="#34d399" opacity="0.3"/>
    <path d="M380,180 Q365,115 330,100 Q350,140 380,180Z" fill="#1b8a5e" opacity="0.5"/>
    <path d="M400,180 Q375,125 340,110 Q360,148 400,180Z" fill="#34d399" opacity="0.3"/>
    <path d="M170,180 Q190,140 210,125 Q195,155 170,180Z" fill="#0d5c3a" opacity="0.4"/>
    <path d="M220,180 Q235,145 250,130 Q240,158 220,180Z" fill="#1b8a5e" opacity="0.3"/>
</svg>

<!-- Contenu principal -->
<div class="contenu">
    <!-- Logo -->
    <div class="logo-wrap">
        <span class="logo-icon">🌿</span>
        <div class="logo-name">WhatAPlant</div>
        <div class="logo-tagline">L'IA botanique pour l'Afrique</div>
    </div>

    <!-- Points de scène -->
    <div class="dots">
        <div class="dot active" id="dot-0"></div>
        <div class="dot" id="dot-1"></div>
        <div class="dot" id="dot-2"></div>
    </div>

    <!-- Barre de progression -->
    <div class="progress-wrap">
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <div class="progress-label" id="loading-label">Chargement...</div>
    </div>
</div>

<!-- Texte de la scène -->
<div class="scene-texte">
    <div class="scene-titre" id="scene-titre">Scannez n'importe quelle plante</div>
    <div class="scene-desc" id="scene-desc">Identifiez en quelques secondes si elle est comestible, médicinale ou toxique</div>
</div>

<script>
const scenes = [
    {
        titre: "📱 Scannez n'importe quelle plante",
        desc: "Identifiez en quelques secondes si elle est comestible, médicinale ou toxique",
    },
    {
        titre: "👨‍👩‍👧 Protégez votre famille",
        desc: "Connaissez les plantes dangereuses avant qu'il ne soit trop tard",
    },
    {
        titre: "🌾 Optimisez vos cultures",
        desc: "Diagnostic de maladies, stade de maturité et conseils agronomiques",
    },
];

const labels = ['Démarrage...', 'Identification...', 'Analyse en cours...', 'Prêt !'];
let sceneActuelle = 0;
const dureeScene  = 8000 / 3; // 3 scènes en 8 secondes

// Particules flottantes
const emojis = ['🌿','🍃','🌱','🌾','🍀','🌺','🌻','🌴'];
const conteneurPart = document.getElementById('particules');
for (let i = 0; i < 12; i++) {
    const p = document.createElement('div');
    p.className = 'particule';
    p.textContent = emojis[Math.floor(Math.random() * emojis.length)];
    p.style.left  = Math.random() * 100 + 'vw';
    p.style.animationDuration  = (6 + Math.random() * 8) + 's';
    p.style.animationDelay     = (Math.random() * 6) + 's';
    p.style.fontSize            = (14 + Math.random() * 16) + 'px';
    conteneurPart.appendChild(p);
}

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js')
    .then(() => console.log('Service Worker OK'))
    .catch(err => console.log('Erreur SW:', err));
}

// Changement de scène
function changerScene(index) {
    // Masquer toutes les scènes
    document.querySelectorAll('.scene').forEach((s,i) => {
        s.classList.remove('active');
    });
    document.querySelectorAll('.dot').forEach((d,i) => {
        d.classList.remove('active');
    });

    // Activer la scène courante
    document.getElementById('scene-'+index)?.classList.add('active');
    document.getElementById('dot-'+index)?.classList.add('active');

    // Mettre à jour le texte
    const s = scenes[index];
    const titre = document.getElementById('scene-titre');
    const desc  = document.getElementById('scene-desc');
    titre.style.animation = 'none'; titre.offsetHeight;
    titre.style.animation = 'texteChange .5s ease';
    titre.textContent = s.titre;
    desc.textContent  = s.desc;

    // Label de chargement
    document.getElementById('loading-label').textContent = labels[index] || 'Prêt !';
}

let timer = 0;
const intervalle = setInterval(() => {
    timer++;
    sceneActuelle = timer % 3;
    changerScene(sceneActuelle);
}, dureeScene);

// Redirection après 8 secondes
setTimeout(() => {
    clearInterval(intervalle);
    document.getElementById('loading-label').textContent = 'Redirection...';
    // Transition douce
    document.body.style.transition = 'opacity .8s';
    document.body.style.opacity = '0';
    setTimeout(() => {
        window.location.href = 'connexion.php';
    }, 800);
}, 8000);
</script>
</body>
</html>
