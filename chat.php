<?php 
require_once 'connect_db.php';
requireLogin();

$identifiant_utilisateur = $_SESSION['user_id'];
$nom_utilisateur         = htmlspecialchars($_SESSION['nom']);
$table_discussions       = "discussions_utilisateur_" . $identifiant_utilisateur;
$table_messages          = "messages_utilisateur_"    . $identifiant_utilisateur;

$discussions = [];
try {
    $requete     = $conn->query("SELECT id, titre, cree_le FROM `$table_discussions` ORDER BY cree_le DESC LIMIT 50");
    $discussions = $requete->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { $discussions = []; }

$id_discussion = isset($_GET['disc']) ? (int)$_GET['disc'] : 0;
$historique_messages = [];
if ($id_discussion > 0) {
    try {
        $requete = $conn->prepare("SELECT role, contenu, cree_le FROM `$table_messages` WHERE discussion_id = ? ORDER BY cree_le ASC");
        $requete->execute([$id_discussion]);
        $historique_messages = $requete->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $historique_messages = []; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>WhatAPlant — Assistant IA Botanique</title>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <meta name="theme-color" content="#0d5c3a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="WhatAPlant">
    <meta name="mobile-web-app-capable" content="yes">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --vert-fonce:#0d5c3a; --vert-moyen:#1b8a5e; --vert-clair:#34d399;
            --vert-pale:#a7f3d0; --vert-fond:#e8f5ee; --vert-page:#f4fbf7;
            --bordure:#c8e6d0; --texte:#1f3a2f; --texte-doux:#4a6b5a;
            --blanc:#ffffff; --rouge:#ef4444; --largeur-menu:285px;
            --transition:cubic-bezier(.4,0,.2,1);
        }
        body { font-family:'Nunito',sans-serif; background:var(--vert-page); height:100dvh; overflow:hidden; display:flex; color:var(--texte); }

        #fond-assombri { display:none; position:fixed; inset:0; background:rgba(0,0,0,.3); z-index:90; backdrop-filter:blur(3px); animation:apparition .2s var(--transition); }
        #fond-assombri.visible { display:block; }
        @keyframes apparition { from{opacity:0} to{opacity:1} }

        .menu-lateral { width:var(--largeur-menu); background:var(--blanc); border-right:1px solid var(--bordure); display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:100; transform:translateX(-100%); transition:transform .32s var(--transition); box-shadow:4px 0 30px rgba(13,92,58,.12); }
        .menu-lateral.ouvert { transform:translateX(0); }

        .entete-menu { padding:20px 18px 16px; background:linear-gradient(135deg,var(--vert-fonce) 0%,var(--vert-moyen) 60%,var(--vert-clair) 100%); display:flex; align-items:center; justify-content:space-between; }
        .logo-menu { font-family:'Playfair Display',serif; font-size:22px; color:white; display:flex; align-items:center; gap:8px; }
        .bouton-fermer-menu { background:rgba(255,255,255,.18); border:none; color:white; width:30px; height:30px; border-radius:50%; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .2s; }
        .bouton-fermer-menu:hover { background:rgba(255,255,255,.32); }

        .btn-nouvelle-discussion { margin:14px 14px 6px; padding:12px 16px; background:var(--vert-moyen); color:white; border:none; border-radius:12px; font-size:14px; font-weight:700; font-family:'Nunito',sans-serif; cursor:pointer; display:flex; align-items:center; gap:8px; transition:background .2s,transform .15s; }
        .btn-nouvelle-discussion:hover { background:var(--vert-fonce); transform:scale(1.02); }

        .titre-section-menu { padding:10px 14px 4px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:var(--texte-doux); }

        .element-menu { padding:10px 14px; margin:2px 8px; display:flex; align-items:center; gap:10px; color:var(--texte); font-size:14px; border-radius:10px; cursor:pointer; transition:background .2s; white-space:nowrap; overflow:hidden; }
        .element-menu:hover { background:var(--vert-fond); }
        .element-menu.actif { background:var(--vert-pale); font-weight:700; }
        .element-menu .icone { font-size:16px; flex-shrink:0; width:20px; text-align:center; }
        .element-menu .titre-disc { overflow:hidden; text-overflow:ellipsis; }
        .element-menu .date-disc { font-size:11px; color:var(--texte-doux); margin-left:auto; flex-shrink:0; }

        .liste-discussions { flex:1; overflow-y:auto; padding-bottom:4px; }
        .liste-discussions::-webkit-scrollbar { width:4px; }
        .liste-discussions::-webkit-scrollbar-thumb { background:var(--bordure); border-radius:4px; }

        .pied-menu { border-top:1px solid var(--bordure); padding:8px 8px 14px; }

        .zone-principale { flex:1; display:flex; flex-direction:column; min-width:0; }

        .entete-chat { padding:12px 18px; background:var(--blanc); border-bottom:1px solid var(--bordure); display:flex; align-items:center; gap:12px; box-shadow:0 2px 12px rgba(13,92,58,.07); flex-shrink:0; }
        .bouton-menu { background:var(--vert-fond); border:1px solid var(--bordure); width:40px; height:40px; border-radius:10px; font-size:19px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .2s; flex-shrink:0; }
        .bouton-menu:hover { background:var(--vert-pale); }
        .marque { font-family:'Playfair Display',serif; font-size:20px; color:var(--vert-moyen); display:flex; align-items:center; gap:7px; }
        .titre-discussion-active { font-size:14px; font-weight:600; color:var(--texte-doux); margin-left:auto; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

        .zone-messages { flex:1; overflow-y:auto; padding:24px 18px; display:flex; flex-direction:column; gap:18px; background:var(--vert-page); }
        .zone-messages::-webkit-scrollbar { width:5px; }
        .zone-messages::-webkit-scrollbar-thumb { background:var(--bordure); border-radius:5px; }

        .ecran-bienvenue { text-align:center; margin-top:50px; animation:glissement .5s var(--transition); }
        .ecran-bienvenue .plante-animee { font-size:72px; margin-bottom:16px; display:block; animation:balancement 3s ease-in-out infinite; }
        @keyframes balancement { 0%,100%{transform:rotate(-4deg)} 50%{transform:rotate(4deg)} }
        @keyframes glissement { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
        .ecran-bienvenue h2 { font-family:'Playfair Display',serif; font-size:26px; color:var(--vert-fonce); margin-bottom:10px; }
        .ecran-bienvenue p  { font-size:15px; color:var(--texte-doux); max-width:440px; margin:0 auto 24px; line-height:1.7; }

        .suggestions { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; max-width:500px; margin:0 auto; }
        .suggestion { padding:8px 16px; background:white; border:1.5px solid var(--bordure); border-radius:50px; font-size:13px; font-weight:600; color:var(--vert-moyen); cursor:pointer; transition:all .2s; }
        .suggestion:hover { background:var(--vert-moyen); color:white; border-color:var(--vert-moyen); transform:scale(1.03); }

        .message { max-width:82%; animation:apparition-message .22s var(--transition); }
        @keyframes apparition-message { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
        .message.utilisateur { align-self:flex-end; }
        .message.assistant   { align-self:flex-start; }

        .bulle { padding:13px 18px; border-radius:20px; line-height:1.7; font-size:15px; }
        .message.utilisateur .bulle { background:var(--vert-moyen); color:white; border-bottom-right-radius:4px; }
        .message.assistant .bulle   { background:white; color:var(--texte); border:1px solid var(--bordure); border-bottom-left-radius:4px; }
        .bulle img { max-width:220px; border-radius:12px; display:block; margin-top:8px; }
        .bulle strong { font-weight:700; }

        .badge { display:inline-block; padding:3px 10px; border-radius:50px; font-size:12px; font-weight:700; margin:4px 3px 0 0; }
        .badge.comestible { background:#d1fae5; color:#065f46; }
        .badge.medicinal  { background:#dbeafe; color:#1e40af; }
        .badge.toxique    { background:#fee2e2; color:#991b1b; }
        .badge.agricole   { background:#fef9c3; color:#854d0e; }
        .badge.malade     { background:#fee2e2; color:#991b1b; }

        .horodatage { font-size:11px; color:var(--texte-doux); margin-top:4px; padding:0 4px; }
        .message.utilisateur .horodatage { text-align:right; }

        .en-train-d-ecrire { align-self:flex-start; }
        .points-ecriture { background:white; border:1px solid var(--bordure); border-radius:20px; border-bottom-left-radius:4px; padding:14px 20px; display:flex; gap:6px; align-items:center; }
        .points-ecriture span { width:8px; height:8px; border-radius:50%; background:var(--vert-clair); display:block; animation:rebond 1.2s infinite; }
        .points-ecriture span:nth-child(2) { animation-delay:.2s; }
        .points-ecriture span:nth-child(3) { animation-delay:.4s; }
        @keyframes rebond { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-7px)} }

        .bouton-lecture { background:none; border:none; cursor:pointer; font-size:16px; padding:4px 6px; border-radius:6px; color:var(--texte-doux); transition:color .2s,background .2s; margin-top:4px; }
        .bouton-lecture:hover { color:var(--vert-moyen); background:var(--vert-fond); }
        .bouton-lecture.en-lecture { color:var(--vert-moyen); animation:pulsation .8s infinite; }
        @keyframes pulsation { 0%,100%{opacity:1} 50%{opacity:.4} }

        .grille-images { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
        .carte-image { flex:1; min-width:150px; }
        .etiquette-image { font-size:12px; font-weight:800; margin-bottom:6px; text-transform:uppercase; letter-spacing:.05em; }
        .etiquette-plat   { color:var(--vert-moyen); }
        .etiquette-remede { color:#1e40af; }
        .carte-image img  { display:none; width:100%; border-radius:12px; border:2px solid var(--bordure); }
        .squelette-image { width:100%; height:150px; border-radius:12px; background:linear-gradient(90deg,#e8f5ee 25%,#d4f0e4 50%,#e8f5ee 75%); background-size:200% 100%; animation:effet-vague 1.5s infinite; display:flex; align-items:center; justify-content:center; font-size:13px; color:var(--texte-doux); font-weight:600; }
        @keyframes effet-vague { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

        .carte-recette { background:var(--vert-fond); border:1px solid var(--bordure); border-radius:14px; padding:14px 16px; margin-top:12px; }
        .titre-recette { font-family:'Playfair Display',serif; color:var(--vert-fonce); font-size:16px; font-weight:700; margin-bottom:10px; }
        .label-section-recette { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--texte-doux); margin:10px 0 5px; }
        .carte-recette ul, .carte-recette ol { padding-left:18px; font-size:14px; line-height:1.9; }
        .carte-recette li { margin-bottom:2px; }

        .carte-maladie { background:#fff1f2; border:1.5px solid #fca5a5; border-radius:14px; padding:14px 16px; margin-top:12px; }
        .titre-maladie { font-size:15px; font-weight:700; color:#991b1b; margin-bottom:10px; }
        .label-section-maladie { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#b91c1c; margin:10px 0 5px; }
        .carte-maladie ul { padding-left:18px; font-size:14px; line-height:1.9; }
        .carte-maladie li { margin-bottom:2px; }

        .carte-recolte { background:#fffbeb; border:1.5px solid #fcd34d; border-radius:14px; padding:14px 16px; margin-top:12px; }
        .titre-recolte { font-size:15px; font-weight:700; color:#854d0e; margin-bottom:8px; }
        .badge-stade { display:inline-block; padding:5px 14px; border-radius:50px; font-size:13px; font-weight:700; margin-bottom:10px; background:#fef9c3; color:#713f12; }

        .zone-saisie { padding:12px 16px 16px; background:white; border-top:1px solid var(--bordure); flex-shrink:0; }

        .boutons-action { display:flex; gap:8px; margin-bottom:10px; }
        .bouton-action { display:flex; align-items:center; gap:6px; padding:8px 14px; background:var(--vert-page); border:1.5px solid var(--bordure); border-radius:50px; font-size:13px; font-weight:600; font-family:'Nunito',sans-serif; color:var(--texte-doux); cursor:pointer; transition:all .2s; white-space:nowrap; }
        .bouton-action:hover { background:var(--vert-moyen); color:white; border-color:var(--vert-moyen); }
        .bouton-action .icone-btn { font-size:16px; }

        .rangee-saisie { display:flex; align-items:center; gap:8px; background:var(--vert-page); border:2px solid var(--bordure); border-radius:50px; padding:5px 5px 5px 16px; transition:border-color .2s; }
        .rangee-saisie:focus-within { border-color:var(--vert-moyen); }
        .rangee-saisie input { flex:1; border:none; outline:none; background:transparent; font-size:15px; font-family:'Nunito',sans-serif; color:var(--texte); }
        .rangee-saisie input::placeholder { color:#9ab8a8; }

        .bouton-rond { width:44px; height:44px; border:none; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:19px; transition:transform .15s,background .2s; flex-shrink:0; }
        .bouton-rond:hover { transform:scale(1.08); }
        .bouton-micro { background:var(--vert-fond); color:var(--vert-moyen); border:1.5px solid var(--bordure) !important; }
        .bouton-micro.enregistrement { background:#fee2e2; color:var(--rouge); border-color:#fca5a5 !important; animation:pulsation-micro .9s infinite; }
        @keyframes pulsation-micro { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
        .bouton-envoyer { background:var(--vert-moyen); color:white; }
        .bouton-envoyer:hover { background:var(--vert-fonce); }
        .bouton-envoyer:disabled { background:var(--bordure); cursor:not-allowed; }

        #entree-fichier { display:none; }

        #apercu-image-conteneur { display:none; margin-bottom:10px; position:relative; width:fit-content; }
        #apercu-image { width:80px; height:80px; object-fit:cover; border-radius:12px; border:2px solid var(--bordure); display:block; }
        #supprimer-image { position:absolute; top:-6px; right:-6px; background:var(--rouge); color:white; border:none; width:20px; height:20px; border-radius:50%; font-size:11px; cursor:pointer; display:flex; align-items:center; justify-content:center; }

        #notification { position:fixed; bottom:90px; left:50%; transform:translateX(-50%); background:#1f3a2f; color:white; padding:10px 20px; border-radius:50px; font-size:13px; font-weight:600; z-index:999; opacity:0; pointer-events:none; transition:opacity .3s; }
        #notification.visible { opacity:1; }

        #modale-camera { display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:999; flex-direction:column; align-items:center; justify-content:center; }
    </style>
</head>
<body>

<div id="fond-assombri" onclick="fermerMenu()"></div>

<!-- MENU LATÉRAL -->
<div class="menu-lateral" id="menu-lateral">
    <div class="entete-menu">
        <div class="logo-menu">🌿 WhatAPlant</div>
        <button class="bouton-fermer-menu" onclick="fermerMenu()">✕</button>
    </div>
    <button class="btn-nouvelle-discussion" onclick="nouvelleDiscussion()">✚ Nouvelle discussion</button>
    <div class="titre-section-menu">Historique</div>
    <div class="liste-discussions" id="liste-discussions">
        <?php if (empty($discussions)): ?>
            <div style="padding:14px 18px;font-size:13px;color:var(--texte-doux);">Aucune discussion pour l'instant.</div>
        <?php else: ?>
            <?php foreach($discussions as $disc): ?>
                <div class="element-menu <?= $disc['id']==$id_discussion ? 'actif' : '' ?>"
                     data-disc="<?= $disc['id'] ?>"
                     onclick="chargerDiscussion(<?= $disc['id'] ?>, <?= htmlspecialchars(json_encode($disc['titre'])) ?>)">
                    <span class="icone">💬</span>
                    <span class="titre-disc"><?= htmlspecialchars($disc['titre']) ?></span>
                    <span class="date-disc"><?= date('d/m', strtotime($disc['cree_le'])) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="titre-section-menu">Menu</div>
    <div class="element-menu" onclick="window.location.href='accueil.php'"><span class="icone">🏠</span> Accueil</div>
    <div class="element-menu" onclick="window.location.href='profil.php'"><span class="icone">👤</span> Mon profil</div>
    <div class="element-menu" onclick="window.location.href='statistiques.php'"><span class="icone">📊</span> Statistiques</div>
    <div class="pied-menu">
        <div class="element-menu" style="color:#d32f2f;" onclick="window.location.href='logout.php'">
            <span class="icone">⭍</span> Déconnexion
        </div>
    </div>
</div>

<!-- ZONE PRINCIPALE -->
<div class="zone-principale">
    <div class="entete-chat">
        <button class="bouton-menu" onclick="basculerMenu()">☰</button>
        <div class="marque">🌿 WhatAPlant</div>
        <div class="titre-discussion-active" id="titre-discussion-active">
            <?php
            if ($id_discussion > 0) {
                $cle = array_search($id_discussion, array_column($discussions, 'id'));
                echo htmlspecialchars($discussions[$cle]['titre'] ?? 'Discussion');
            } else { echo 'Nouvelle discussion'; }
            ?>
        </div>
    </div>

    <div class="zone-messages" id="zone-messages">
        <?php if ($id_discussion > 0 && !empty($historique_messages)): ?>
            <?php foreach($historique_messages as $msg): ?>
                <?php if($msg['role'] === 'user'): ?>
                    <?php
                    $contenu_user = $msg['contenu'];
                    $img_user_path = null;
                    if (preg_match('/^\[IMAGE:(uploads\/[^\]]+)\]\s*(.*)$/s', $contenu_user, $iu)) {
                        $img_user_path = $iu[1];
                        $contenu_user  = trim($iu[2]);
                    }
                    ?>
                    <div class="message utilisateur">
                        <div class="bulle">
                            <?php if ($img_user_path && file_exists(__DIR__.'/'.$img_user_path)): ?>
                                <img src="<?= htmlspecialchars($img_user_path) ?>" alt="Image envoyée"
                                     style="max-width:220px;border-radius:12px;display:block;margin-bottom:6px;border:2px solid rgba(255,255,255,.3);">
                            <?php endif; ?>
                            <?= $contenu_user ? htmlspecialchars($contenu_user) : '' ?>
                        </div>
                        <div class="horodatage"><?= date('H:i', strtotime($msg['cree_le'])) ?></div>
                    </div>
                <?php else: ?>
                    <?php
                    $contenu_msg = $msg['contenu'];
                    $img_meta    = ['img_plante'=>null,'img_plat'=>null,'img_remede'=>null,'nom_sci'=>'','nom_plat'=>''];
                    if (preg_match('/<!--IMGMETA:([A-Za-z0-9+\/=]+):IMGMETA-->/', $contenu_msg, $mm)) {
                        $decoded = json_decode(base64_decode($mm[1]), true);
                        if ($decoded) $img_meta = array_merge($img_meta, $decoded);
                        $contenu_msg = str_replace($mm[0], '', $contenu_msg);
                    }
                    $uid_hist = 'h' . preg_replace('/[^a-z0-9]/i', '', $msg['cree_le']);
                    $has_plante = !empty($img_meta['img_plante']);
                    $nom_sci_h  = htmlspecialchars($img_meta['nom_sci'] ?? '');
                    $nom_plat_h = htmlspecialchars($img_meta['nom_plat'] ?? '');
                    $terme_plat = $nom_plat_h ?: $nom_sci_h;
                    $terme_rem  = $nom_sci_h;
                    ?>
                    <div class="message assistant">
                        <div class="bulle">
                            <?= $contenu_msg ?>
                            <?php if ($has_plante || !empty($img_meta['img_plat']) || !empty($img_meta['img_remede']) || $nom_sci_h): ?>
                            <div class="grille-images">
                                <?php if ($has_plante): ?>
                                <div class="carte-image">
                                    <div class="etiquette-image" style="color:var(--vert-fonce);">🌿 Plante identifiée</div>
                                    <img src="<?= htmlspecialchars($img_meta['img_plante']) ?>" alt="Plante"
                                         style="display:block;width:100%;border-radius:12px;border:2px solid var(--bordure);"
                                         onerror="this.style.display='none'">
                                </div>
                                <?php elseif ($nom_sci_h): ?>
                                <div class="carte-image">
                                    <div class="etiquette-image" style="color:var(--vert-fonce);">🌿 Plante identifiée</div>
                                    <div id="sq-<?= $uid_hist ?>p" class="squelette-image">🌿 Chargement...</div>
                                    <img id="img-<?= $uid_hist ?>p" style="display:none;width:100%;border-radius:12px;border:2px solid var(--bordure);" alt="Plante">
                                </div>
                                <script>chargerImageWiki(<?= json_encode($nom_sci_h) ?>, 'img-<?= $uid_hist ?>p', 'sq-<?= $uid_hist ?>p', 'plant');</script>
                                <?php endif; ?>

                                <?php if ($terme_plat): ?>
                                <div class="carte-image">
                                    <div class="etiquette-image etiquette-plat">🍽️ Plat cuisiné</div>
                                    <div id="sq-<?= $uid_hist ?>d" class="squelette-image">🍽️ Chargement...</div>
                                    <img id="img-<?= $uid_hist ?>d" style="display:none;width:100%;border-radius:12px;border:2px solid var(--bordure);" alt="Plat cuisiné">
                                </div>
                                <script>chargerImageWiki(<?= json_encode($terme_plat) ?>, 'img-<?= $uid_hist ?>d', 'sq-<?= $uid_hist ?>d', 'food');</script>
                                <?php endif; ?>

                                <?php if ($terme_rem): ?>
                                <div class="carte-image">
                                    <div class="etiquette-image etiquette-remede">💊 Remède médicinal</div>
                                    <div id="sq-<?= $uid_hist ?>r" class="squelette-image">💊 Chargement...</div>
                                    <img id="img-<?= $uid_hist ?>r" style="display:none;width:100%;border-radius:12px;border:2px solid var(--bordure);" alt="Remède médicinal">
                                </div>
                                <script>chargerImageWiki(<?= json_encode($terme_rem) ?>, 'img-<?= $uid_hist ?>r', 'sq-<?= $uid_hist ?>r', 'medicine');</script>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;align-items:center;gap:4px;">
                            <button class="bouton-lecture" onclick="lireTexte(this)" data-texte="<?= htmlspecialchars(strip_tags($contenu_msg)) ?>">🔊</button>
                            <div class="horodatage"><?= date('H:i', strtotime($msg['cree_le'])) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="ecran-bienvenue" id="ecran-bienvenue">
                <span class="plante-animee">🌿</span>
                <h2>Bonjour, <?= $nom_utilisateur ?> !</h2>
                <p>Posez-moi une question sur les plantes, envoyez une photo, ou choisissez une suggestion.</p>
                <div class="suggestions">
                    <div class="suggestion" onclick="utiliserSuggestion('Qu\'est-ce que le moringa ?')">🌱 Moringa</div>
                    <div class="suggestion" onclick="utiliserSuggestion('Propriétés médicinales du basilic ?')">🌿 Basilic</div>
                    <div class="suggestion" onclick="utiliserSuggestion('Comment cuisiner le gombô ?')">🍃 Gombô</div>
                    <div class="suggestion" onclick="utiliserSuggestion('Plantes médicinales d\'Afrique de l\'Ouest')">🌍 Plantes africaines</div>
                    <div class="suggestion" onclick="utiliserSuggestion('Le manioc est-il comestible ?')">🥬 Manioc</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ZONE DE SAISIE -->
    <div class="zone-saisie">
        <div id="apercu-image-conteneur">
            <img id="apercu-image" src="" alt="Aperçu">
            <button id="supprimer-image" onclick="supprimerImage()">✕</button>
        </div>
        <div class="boutons-action">
            <button class="bouton-action" onclick="document.getElementById('entree-fichier').click()">
                <span class="icone-btn">🖼️</span> Télécharger
            </button>
            <button class="bouton-action" onclick="ouvrirCamera()">
                <span class="icone-btn">📷</span> Scanner
            </button>
        </div>
        <input type="file" id="entree-fichier" accept="image/*" onchange="gererImage(event)">

        <div id="modale-camera">
            <video id="flux-video" autoplay playsinline style="max-width:100%;max-height:65vh;border-radius:16px;"></video>
            <div style="display:flex;gap:16px;margin-top:20px;">
                <button onclick="prendrePhoto()" style="background:#1b8a5e;color:white;border:none;padding:14px 32px;border-radius:50px;font-size:16px;font-weight:700;cursor:pointer;">📸 Capturer</button>
                <button onclick="fermerCamera()" style="background:#555;color:white;border:none;padding:14px 24px;border-radius:50px;font-size:16px;cursor:pointer;">✕ Annuler</button>
            </div>
            <canvas id="canvas-photo" style="display:none;"></canvas>
        </div>

        <div class="rangee-saisie">
            <button class="bouton-rond bouton-micro" id="bouton-micro" onclick="basculerMicro()" title="Message vocal">🎤</button>
            <input type="text" id="champ-message"
                   placeholder="Décrivez une plante ou posez une question..."
                   onkeypress="if(event.key==='Enter') envoyerMessage()">
            <button class="bouton-rond bouton-envoyer" id="bouton-envoyer" onclick="envoyerMessage()">↑</button>
        </div>
    </div>
</div>

<div id="notification"></div>

<script>
// ═══════════════════════════════════════════════
// VARIABLES D'ÉTAT
// ═══════════════════════════════════════════════
const ID_UTILISATEUR   = <?= (int)$identifiant_utilisateur ?>;
let idDiscussionActuelle = <?= (int)$id_discussion ?>;
let imageBase64EnAttente = null;
let fichierImageEnAttente = null;
let envoiEnCours = false;
let reconnaissanceVocale = null;
let enregistrementActif = false;
let utteranceActuelle = null;
let dernierEnvoi = 0;
const DELAI_MINIMUM_MS = 2000;
let minuterieDelai = null;
let fluxCamera = null;

// ═══════════════════════════════════════════════
// UTILITAIRES
// ═══════════════════════════════════════════════
function heureActuelle() {
    return new Date().toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
}
function dateAujourdhui() {
    const d = new Date();
    return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0');
}
function defilerVersLeBas() {
    const z = document.getElementById('zone-messages');
    if (z) z.scrollTop = z.scrollHeight;
}
function echapperHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function echapperAttribut(s) {
    return String(s||'').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function afficherNotification(msg) {
    const n = document.getElementById('notification');
    n.textContent = msg; n.classList.add('visible');
    setTimeout(() => n.classList.remove('visible'), 3500);
}

// ═══════════════════════════════════════════════
// MENU LATÉRAL
// ═══════════════════════════════════════════════
function basculerMenu() {
    const m = document.getElementById('menu-lateral');
    const f = document.getElementById('fond-assombri');
    if (m.classList.contains('ouvert')) fermerMenu();
    else { m.classList.add('ouvert'); f.classList.add('visible'); }
}
function fermerMenu() {
    document.getElementById('menu-lateral').classList.remove('ouvert');
    document.getElementById('fond-assombri').classList.remove('visible');
}
function chargerDiscussion(id) {
    fermerMenu();
    window.location.href = 'chat.php?disc=' + id;
}
function nouvelleDiscussion() {
    fermerMenu();
    idDiscussionActuelle = 0;
    document.getElementById('titre-discussion-active').textContent = 'Nouvelle discussion';
    document.getElementById('zone-messages').innerHTML =
        '<div class="ecran-bienvenue" id="ecran-bienvenue">'
        + '<span class="plante-animee">🌿</span>'
        + '<h2>Bonjour, <?= $nom_utilisateur ?> !</h2>'
        + '<p>Commençons une nouvelle discussion !</p>'
        + '<div class="suggestions">'
        + '<div class="suggestion" onclick="utiliserSuggestion(\'Qu\\\'est-ce que le moringa ?\')">🌱 Moringa</div>'
        + '<div class="suggestion" onclick="utiliserSuggestion(\'Comment cuisiner le gombô ?\')">🍃 Gombô</div>'
        + '<div class="suggestion" onclick="utiliserSuggestion(\'Le manioc est-il comestible ?\')">🥬 Manioc</div>'
        + '<div class="suggestion" onclick="utiliserSuggestion(\'Plantes médicinales d\\\'Afrique\')">🌍 Afrique</div>'
        + '</div></div>';
}
function ajouterDiscussionAuMenu(id, titre) {
    const liste = document.getElementById('liste-discussions');
    if (liste.querySelector('[data-disc="' + id + '"]')) {
        liste.querySelector('[data-disc="' + id + '"]').classList.add('actif');
        return;
    }
    const div = document.createElement('div');
    div.className = 'element-menu actif';
    div.dataset.disc = id;
    div.innerHTML = '<span class="icone">💬</span>'
        + '<span class="titre-disc">' + echapperHtml(titre) + '</span>'
        + '<span class="date-disc">' + dateAujourdhui() + '</span>';
    div.onclick = () => chargerDiscussion(id);
    liste.querySelectorAll('.element-menu').forEach(el => el.classList.remove('actif'));
    liste.insertBefore(div, liste.firstChild);
    const vide = liste.querySelector('div:not(.element-menu)');
    if (vide) vide.remove();
}

// ═══════════════════════════════════════════════
// SUGGESTIONS
// ═══════════════════════════════════════════════
function utiliserSuggestion(texte) {
    document.getElementById('champ-message').value = texte;
    envoyerMessage();
}

// ═══════════════════════════════════════════════
// GESTION DES IMAGES
// ═══════════════════════════════════════════════
function gererImage(ev) {
    const f = ev.target.files[0];
    if (!f) return;
    fichierImageEnAttente = f;
    const r = new FileReader();
    r.onload = e => {
        imageBase64EnAttente = e.target.result;
        document.getElementById('apercu-image').src = imageBase64EnAttente;
        document.getElementById('apercu-image-conteneur').style.display = 'block';
    };
    r.readAsDataURL(f);
    ev.target.value = '';
}
function supprimerImage() {
    imageBase64EnAttente = null;
    fichierImageEnAttente = null;
    document.getElementById('apercu-image-conteneur').style.display = 'none';
    document.getElementById('apercu-image').src = '';
}

// ═══════════════════════════════════════════════
// CAMÉRA
// ═══════════════════════════════════════════════
async function ouvrirCamera() {
    const modale = document.getElementById('modale-camera');
    const video  = document.getElementById('flux-video');
    modale.style.display = 'flex';
    try {
        fluxCamera = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false
        });
        video.srcObject = fluxCamera;
    } catch(e) {
        try {
            fluxCamera = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            video.srcObject = fluxCamera;
        } catch(e2) {
            fermerCamera();
            afficherNotification('❌ Caméra inaccessible : ' + e2.message);
        }
    }
}
function prendrePhoto() {
    const video  = document.getElementById('flux-video');
    const canvas = document.getElementById('canvas-photo');
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
        const f = new File([blob], 'scan_plante.jpg', { type: 'image/jpeg' });
        fichierImageEnAttente = f;
        const r = new FileReader();
        r.onload = ev => {
            imageBase64EnAttente = ev.target.result;
            document.getElementById('apercu-image').src = imageBase64EnAttente;
            document.getElementById('apercu-image-conteneur').style.display = 'block';
        };
        r.readAsDataURL(f);
        fermerCamera();
        setTimeout(() => envoyerMessage(), 300);
    }, 'image/jpeg', 0.92);
}
function fermerCamera() {
    if (fluxCamera) { fluxCamera.getTracks().forEach(t => t.stop()); fluxCamera = null; }
    document.getElementById('modale-camera').style.display = 'none';
    document.getElementById('flux-video').srcObject = null;
}

// ═══════════════════════════════════════════════
// DÉLAI ENTRE ENVOIS
// ═══════════════════════════════════════════════
function demarrerDelai() {
    const btn = document.getElementById('bouton-envoyer');
    let r = Math.ceil(DELAI_MINIMUM_MS / 1000);
    btn.disabled = true; btn.textContent = r + 's';
    minuterieDelai = setInterval(() => {
        r--;
        if (r <= 0) { clearInterval(minuterieDelai); btn.disabled = false; btn.textContent = '↑'; }
        else btn.textContent = r + 's';
    }, 1000);
}

// ═══════════════════════════════════════════════
// ENVOI MESSAGE — FONCTION PRINCIPALE
// ═══════════════════════════════════════════════
async function envoyerMessage() {
    if (envoiEnCours) return;
    const champ = document.getElementById('champ-message');
    const texte = champ.value.trim();
    if (!texte && !imageBase64EnAttente) return;

    const maintenant = Date.now();
    if (!imageBase64EnAttente && (maintenant - dernierEnvoi) < DELAI_MINIMUM_MS && dernierEnvoi > 0) {
        afficherNotification('⏳ Attendez ' + Math.ceil((DELAI_MINIMUM_MS - (maintenant - dernierEnvoi)) / 1000) + 's');
        return;
    }

    envoiEnCours = true;
    dernierEnvoi = Date.now();
    demarrerDelai();

    const ecran = document.getElementById('ecran-bienvenue');
    if (ecran) ecran.remove();

    const zone = document.getElementById('zone-messages');

    // Bulle utilisateur
    const divU = document.createElement('div');
    divU.className = 'message utilisateur';
    divU.innerHTML = '<div class="bulle">'
        + (imageBase64EnAttente ? '<img src="' + imageBase64EnAttente + '" alt="Image envoyée">' : '')
        + (texte ? echapperHtml(texte) : '')
        + '</div><div class="horodatage">' + heureActuelle() + '</div>';
    zone.appendChild(divU);
    defilerVersLeBas();

    // Sauvegarder fichier AVANT de nettoyer
    const fichierPourEnvoi = fichierImageEnAttente;
    document.getElementById('apercu-image-conteneur').style.display = 'none';
    document.getElementById('apercu-image').src = '';
    imageBase64EnAttente = null;
    fichierImageEnAttente = null;
    champ.value = '';

    // Indicateur écriture
    const divE = document.createElement('div');
    divE.className = 'message assistant en-train-d-ecrire';
    divE.id = 'indicateur-ecriture';
    divE.innerHTML = '<div class="points-ecriture"><span></span><span></span><span></span></div>';
    zone.appendChild(divE);
    defilerVersLeBas();

    const form = new FormData();
    form.append('user_id', ID_UTILISATEUR);
    form.append('disc_id', idDiscussionActuelle);
    form.append('message', texte);
    if (fichierPourEnvoi) form.append('image', fichierPourEnvoi);

    try {
        const rep = await fetch('api_chat.php', { method: 'POST', body: form });
        const ct  = rep.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            const txt = await rep.text();
            divE.remove();
            afficherNotification('❌ Erreur serveur — vérifiez les logs');
            console.error('Non-JSON:', txt.slice(0, 500));
            envoiEnCours = false;
            return;
        }

        const d = await rep.json();
        divE.remove();

        if (d.error) { afficherNotification('❌ ' + d.error); envoiEnCours = false; return; }

        if (d.disc_id) idDiscussionActuelle = d.disc_id;
        if (d.disc_titre) {
            document.getElementById('titre-discussion-active').textContent = d.disc_titre;
            ajouterDiscussionAuMenu(d.disc_id, d.disc_titre);
        }

        // Construire la bulle IA
        let bulle = d.html_response || '';

        // Recette
        if (d.recipe || d.recette) {
            const rec = d.recipe || d.recette;
            const items = rec.items || [];
            const ingr  = items.filter(e => !e.toLowerCase().startsWith('étape') && !e.match(/^\d+\./));
            const etap  = items.filter(e =>  e.toLowerCase().startsWith('étape') ||  e.match(/^\d+\./));
            bulle += '<div class="carte-recette"><div class="titre-recette">🍽️ ' + echapperHtml(rec.title) + '</div>';
            if (ingr.length) {
                bulle += '<div class="label-section-recette">Ingrédients</div><ul>';
                ingr.forEach(i => bulle += '<li>' + echapperHtml(i) + '</li>');
                bulle += '</ul>';
            }
            if (etap.length) {
                bulle += '<div class="label-section-recette">Préparation</div><ol>';
                etap.forEach(e => bulle += '<li>' + echapperHtml(e.replace(/^étape\s*\d+\s*:\s*/i,'').replace(/^\d+\s*\.\s*/,'')) + '</li>');
                bulle += '</ol>';
            }
            bulle += '</div>';
        }

        // Maladie
        if (d.maladie) {
            const m = d.maladie;
            bulle += '<div class="carte-maladie"><div class="titre-maladie">🔴 ' + echapperHtml(m.name) + '</div>';
            if (m.treatment && m.treatment.length) {
                bulle += '<div class="label-section-maladie">💊 Traitements</div><ul>';
                m.treatment.forEach(t => bulle += '<li>' + echapperHtml(t) + '</li>');
                bulle += '</ul>';
            }
            if (m.prevention && m.prevention.length) {
                bulle += '<div class="label-section-maladie">🛡️ Prévention</div><ul>';
                m.prevention.forEach(p => bulle += '<li>' + echapperHtml(p) + '</li>');
                bulle += '</ul>';
            }
            bulle += '</div>';
        }

        // Récolte
        if (d.recolte) {
            const r = d.recolte;
            bulle += '<div class="carte-recolte"><div class="titre-recolte">🌾 Stade de maturité</div>'
                + '<div class="badge-stade">📅 ' + echapperHtml(r.stage) + '</div><br>'
                + '<strong>Récolte estimée :</strong> ' + echapperHtml(r.days||'') + '<br>'
                + '<strong>Conseil :</strong> ' + echapperHtml(r.advice||'')
                + '</div>';
        }

        // Images
        const uid = 'i' + Date.now();
        const imgPlante = d.img_plante || d.image_plante || null;
        const nomSci    = (d.nom_sci   || '').trim();
        const nomPlat   = (d.nom_plat  || '').trim();

        // Décoder les préfixes __FOOD__ __MED__ __WIKI__
        function decoderTerme(val) {
            if (!val) return null;
            if (val.startsWith('__FOOD__') || val.startsWith('__MED__') || val.startsWith('__WIKI__'))
                return decodeURIComponent(val.replace(/^__(FOOD|MED|WIKI)__/, ''));
            return null;
        }

        const termeP = decoderTerme(d.img_dish || d.image_plat)
                    || (nomPlat ? nomPlat + ' food' : null)
                    || (nomSci  ? nomSci  + ' food dish' : null);
        const termeR = decoderTerme(d.img_med || d.image_remede)
                    || (nomSci ? nomSci + ' traditional medicine herbal' : null);

        const montrerImages = !d.est_generale && (imgPlante || nomSci);

        if (montrerImages) {
            bulle += '<div class="grille-images">';

            // Image plante
            if (imgPlante && !imgPlante.startsWith('__')) {
                bulle += '<div class="carte-image">'
                    + '<div class="etiquette-image" style="color:var(--vert-fonce);">🌿 Plante identifiée</div>'
                    + '<img src="' + imgPlante + '" alt="Plante" style="display:block;width:100%;border-radius:12px;border:2px solid var(--bordure);" onerror="this.remove();">'
                    + '</div>';
            } else if (nomSci) {
                const pId = uid+'p', pSk = uid+'ps';
                bulle += '<div class="carte-image">'
                    + '<div class="etiquette-image" style="color:var(--vert-fonce);">🌿 Plante identifiée</div>'
                    + '<div id="' + pSk + '" class="squelette-image">🌿 Chargement...</div>'
                    + '<img id="' + pId + '" style="display:none;width:100%;border-radius:12px;" alt="Plante">'
                    + '</div>';
                setTimeout((a,b,c) => () => chargerImageWiki(a,b,c,'plant'), 100)(nomSci, pId, pSk);
            }

            // Image plat
            if (termeP) {
                const dId = uid+'d', dSk = uid+'ds';
                bulle += '<div class="carte-image">'
                    + '<div class="etiquette-image etiquette-plat">🍽️ Plat cuisiné</div>'
                    + '<div id="' + dSk + '" class="squelette-image">🍽️ Chargement...</div>'
                    + '<img id="' + dId + '" style="display:none;width:100%;border-radius:12px;" alt="Plat cuisiné">'
                    + '</div>';
                setTimeout((a,b,c) => () => chargerImageWiki(a,b,c,'food'), 200)(termeP, dId, dSk);
            }

            // Image remède
            if (termeR) {
                const rId = uid+'r', rSk = uid+'rs';
                bulle += '<div class="carte-image">'
                    + '<div class="etiquette-image etiquette-remede">💊 Remède médicinal</div>'
                    + '<div id="' + rSk + '" class="squelette-image">💊 Chargement...</div>'
                    + '<img id="' + rId + '" style="display:none;width:100%;border-radius:12px;" alt="Remède">'
                    + '</div>';
                setTimeout((a,b,c) => () => chargerImageWiki(a,b,c,'medicine'), 300)(termeR, rId, rSk);
            }

            bulle += '</div>';
        }

        const divA = document.createElement('div');
        divA.className = 'message assistant';
        divA.innerHTML = '<div class="bulle">' + bulle + '</div>'
            + '<div style="display:flex;align-items:center;gap:4px;">'
            + '<button class="bouton-lecture" onclick="lireTexte(this)" data-texte="' + echapperAttribut(d.plain_text) + '">🔊</button>'
            + '<div class="horodatage">' + heureActuelle() + '</div>'
            + '</div>';
        zone.appendChild(divA);

        // Lancer les setTimeout des images
        if (montrerImages) {
            if (!imgPlante && nomSci)
                setTimeout(() => chargerImageWiki(nomSci, uid+'p', uid+'ps', 'plant'), 100);
            if (termeP)
                setTimeout(() => chargerImageWiki(termeP, uid+'d', uid+'ds', 'food'), 200);
            if (termeR)
                setTimeout(() => chargerImageWiki(termeR, uid+'r', uid+'rs', 'medicine'), 300);
        }

        defilerVersLeBas();

    } catch(err) {
        try { divE.remove(); } catch(e) {}
        afficherNotification('❌ Erreur réseau : ' + err.message);
        console.error(err);
    } finally {
        envoiEnCours = false;
        defilerVersLeBas();
    }
}

// ═══════════════════════════════════════════════
// SYNTHÈSE VOCALE
// ═══════════════════════════════════════════════
function lireTexte(bouton) {
    const texte = bouton.dataset.texte;
    if (!texte) return;
    if (utteranceActuelle && speechSynthesis.speaking) {
        speechSynthesis.cancel();
        document.querySelectorAll('.bouton-lecture.en-lecture').forEach(b => b.classList.remove('en-lecture'));
        if (bouton.classList.contains('en-lecture')) { bouton.classList.remove('en-lecture'); return; }
    }
    const propre = texte.replace(/[*_#`]/g, '').slice(0, 500);
    utteranceActuelle = new SpeechSynthesisUtterance(propre);
    utteranceActuelle.lang  = 'fr-FR';
    utteranceActuelle.rate  = 0.95;
    utteranceActuelle.pitch = 1.05;
    const voix = speechSynthesis.getVoices();
    const vfr  = voix.find(v => v.lang.startsWith('fr') && v.name.toLowerCase().includes('female'))
              || voix.find(v => v.lang.startsWith('fr'));
    if (vfr) utteranceActuelle.voice = vfr;
    bouton.classList.add('en-lecture');
    utteranceActuelle.onend = () => bouton.classList.remove('en-lecture');
    speechSynthesis.speak(utteranceActuelle);
}
if (window.speechSynthesis) {
    speechSynthesis.onvoiceschanged = () => speechSynthesis.getVoices();
}

// ═══════════════════════════════════════════════
// MICRO / RECONNAISSANCE VOCALE
// ═══════════════════════════════════════════════
function basculerMicro() {
    const btn = document.getElementById('bouton-micro');
    const SR  = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { afficherNotification('Reconnaissance vocale non supportée'); return; }
    if (enregistrementActif) {
        if (reconnaissanceVocale) reconnaissanceVocale.stop();
        btn.classList.remove('enregistrement'); btn.textContent = '🎤';
        enregistrementActif = false; return;
    }
    reconnaissanceVocale = new SR();
    reconnaissanceVocale.lang = 'fr-FR';
    reconnaissanceVocale.interimResults = true;
    reconnaissanceVocale.continuous = false;
    reconnaissanceVocale.onresult = ev => {
        const t = Array.from(ev.results).map(r => r[0].transcript).join('');
        document.getElementById('champ-message').value = t;
        if (ev.results[ev.results.length-1].isFinal) {
            btn.classList.remove('enregistrement'); btn.textContent = '🎤';
            enregistrementActif = false;
            setTimeout(() => envoyerMessage(), 400);
        }
    };
    reconnaissanceVocale.onerror = reconnaissanceVocale.onend = () => {
        btn.classList.remove('enregistrement'); btn.textContent = '🎤';
        enregistrementActif = false;
    };
    reconnaissanceVocale.start();
    btn.classList.add('enregistrement'); btn.textContent = '⏹️';
    enregistrementActif = true;
}

// ═══════════════════════════════════════════════
// CHARGEMENT D'IMAGES — WIKIMEDIA COMMONS
// ═══════════════════════════════════════════════
async function chargerImageWiki(terme, idImg, idSkeleton, type) {
    const imgEl = document.getElementById(idImg);
    const skEl  = document.getElementById(idSkeleton);
    if (!imgEl) return;

    const t = (type || '').toLowerCase();
    let termes = [];

    if (t === 'food') {
        const mots = terme.replace(/[^a-zA-Z0-9 ]/g,' ').trim().split(' ').filter(m => m.length > 2).slice(0,3);
        termes = [mots.join(' ') + ' food', mots[0] + ' dish', mots.slice(0,2).join(' ')];
    } else if (t === 'medicine') {
        const nomSci = terme.replace(/ traditional medicine herbal/g,'').replace(/ herbal/g,'').trim();
        termes = [nomSci, nomSci + ' medicinal use', nomSci + ' plant'];
    } else {
        termes = [terme, terme.split(' ').slice(0,2).join(' ')];
    }

    for (const rech of termes) {
        if (!rech || rech.trim().length < 3) continue;

        // Wikimedia Commons generator
        try {
            const url = 'https://commons.wikimedia.org/w/api.php?action=query&generator=search'
                + '&gsrsearch=' + encodeURIComponent(rech) + '&gsrnamespace=6&gsrlimit=8'
                + '&prop=imageinfo&iiprop=url&iiurlwidth=480&format=json&origin=*';
            const rep  = await fetch(url, { signal: AbortSignal.timeout(7000) });
            const data = await rep.json();
            const pages = data?.query?.pages || {};
            for (const page of Object.values(pages)) {
                const info = page?.imageinfo?.[0];
                const thumb = info?.thumburl;
                const fullUrl = info?.url || '';
                const ext = fullUrl.split('.').pop().toLowerCase().split('?')[0];
                if (!['jpg','jpeg','png','webp'].includes(ext)) continue;
                const titre = (page.title || '').toLowerCase();
                if (titre.includes('logo') || titre.includes('icon') || titre.includes('map') || titre.includes('flag')) continue;
                if (thumb) return _afficherImage(thumb, imgEl, skEl, terme);
            }
        } catch(e) {}

        // Wikipedia EN
        const en = await _wikiPageImage(rech, 'en');
        if (en) return _afficherImage(en, imgEl, skEl, terme);

        // Wikipedia FR
        const fr = await _wikiPageImage(rech, 'fr');
        if (fr) return _afficherImage(fr, imgEl, skEl, terme);
    }

    _afficherPlaceholder(terme, imgEl, skEl, type);
}

async function _wikiPageImage(terme, lang) {
    try {
        const urlS = 'https://' + lang + '.wikipedia.org/w/api.php?action=query&list=search'
            + '&srsearch=' + encodeURIComponent(terme) + '&srlimit=3&format=json&origin=*';
        const repS = await fetch(urlS, { signal: AbortSignal.timeout(6000) });
        const datS = await repS.json();
        for (const r of (datS?.query?.search || [])) {
            const urlI = 'https://' + lang + '.wikipedia.org/w/api.php?action=query&pageids=' + r.pageid
                + '&prop=pageimages&pithumbsize=480&pilicense=any&format=json&origin=*';
            const repI = await fetch(urlI, { signal: AbortSignal.timeout(5000) });
            const datI = await repI.json();
            const thumb = Object.values(datI?.query?.pages || {})[0]?.thumbnail?.source;
            if (thumb) return thumb;
        }
    } catch(e) {}
    return null;
}

function _afficherImage(src, imgEl, skEl, terme) {
    imgEl.src = src;
    imgEl.onload  = () => { if (skEl) skEl.remove(); imgEl.style.display = 'block'; };
    imgEl.onerror = () => _afficherPlaceholder(terme, imgEl, skEl, '');
}

function _afficherPlaceholder(terme, imgEl, skEl, type) {
    const t = ((type||'') + ' ' + (terme||'')).toLowerCase();
    let emoji = '🌿', fond = '#f0fdf4', col = '#166534';
    if (t.includes('food')||t.includes('dish')||t.includes('plat')) { emoji='🍲'; fond='#fff8f0'; col='#c2410c'; }
    else if (t.includes('medic')||t.includes('herbal')) { emoji='💊'; fond='#f0fff4'; col='#065f46'; }
    else if (t.includes('manioc')||t.includes('cassava')) emoji='🥬';
    else if (t.includes('moringa')) emoji='🌱';
    else if (t.includes('banana')||t.includes('plantain')) emoji='🍌';
    else if (t.includes('tomat')) emoji='🍅';
    else if (t.includes('gombo')||t.includes('okra')) emoji='🫛';
    const label = (terme||'').split(' ').slice(0,4).join(' ');
    const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="480" height="300" viewBox="0 0 480 300">'
        + '<rect width="480" height="300" fill="' + fond + '" rx="12"/>'
        + '<rect x="2" y="2" width="476" height="296" fill="none" stroke="#bbf7d0" stroke-width="2" rx="10"/>'
        + '<text x="240" y="132" text-anchor="middle" font-size="56">' + emoji + '</text>'
        + '<text x="240" y="178" text-anchor="middle" font-family="Georgia,serif" font-size="13" fill="' + col + '">' + label + '</text>'
        + '</svg>';
    if (skEl) skEl.remove();
    imgEl.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svg)));
    imgEl.style.display = 'block';
}

// ═══════════════════════════════════════════════
// INITIALISATION
// ═══════════════════════════════════════════════
window.addEventListener('load', () => {
    defilerVersLeBas();

    // Image depuis scan.php via sessionStorage
    const scanData = sessionStorage.getItem('scan_image_data');
    if (scanData && new URLSearchParams(location.search).get('scan') === '1') {
        sessionStorage.removeItem('scan_image_data');
        sessionStorage.removeItem('scan_image_name');
        sessionStorage.removeItem('scan_image_type');
        fetch(scanData).then(r => r.blob()).then(blob => {
            const f = new File([blob], 'scan.jpg', { type: 'image/jpeg' });
            fichierImageEnAttente = f;
            imageBase64EnAttente  = scanData;
            document.getElementById('apercu-image').src = scanData;
            document.getElementById('apercu-image-conteneur').style.display = 'block';
            setTimeout(() => envoyerMessage(), 600);
        });
    }

    // Enregistrer le Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .then(r => console.log('SW enregistré'))
            .catch(e => console.warn('SW:', e));
    }
});
</script>
</body>
</html>
