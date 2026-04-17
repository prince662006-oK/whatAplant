<?php
/**
 * statistiques.php — Tableau de bord WhatAPlant
 * Visualise les plantes les plus scannées/uploadées
 */
require_once 'connect_db.php';
requireLogin();

$id_user  = (int)$_SESSION['user_id'];
$nom_user = htmlspecialchars($_SESSION['nom']);

// ── Créer la table si elle n'existe pas encore ──
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS `scans_plantes` (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        user_id          INT NOT NULL,
        nom_scientifique VARCHAR(200) NOT NULL,
        nom_commun       VARCHAR(200) DEFAULT '',
        famille          VARCHAR(150) DEFAULT '',
        score_confiance  INT DEFAULT 0,
        type_action      ENUM('scan','upload','texte') NOT NULL DEFAULT 'upload',
        badges           VARCHAR(100) DEFAULT '',
        image_path       VARCHAR(300) DEFAULT '',
        cree_le          DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user    (user_id),
        INDEX idx_nom_sci (nom_scientifique),
        INDEX idx_cree_le (cree_le)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(Exception $e) {}

// ── Stats globales de l'utilisateur ──
$total_scans = 0;
$plantes_uniques = 0;
try {
    $r = $conn->prepare("SELECT COUNT(*) as total, COUNT(DISTINCT nom_scientifique) as uniques FROM `scans_plantes` WHERE user_id=?");
    $r->execute([$id_user]);
    $row = $r->fetch();
    $total_scans    = $row['total']   ?? 0;
    $plantes_uniques = $row['uniques'] ?? 0;
} catch(Exception $e) {}

// ── Top plantes scannées par l'utilisateur ──
$top_plantes = [];
try {
    $r = $conn->prepare("
        SELECT
            nom_scientifique,
            MAX(nom_commun)      AS nom_commun,
            MAX(famille)         AS famille,
            COUNT(*)             AS total,
            SUM(CASE WHEN type_action='scan'   THEN 1 ELSE 0 END) AS nb_camera,
            SUM(CASE WHEN type_action='upload' THEN 1 ELSE 0 END) AS nb_upload,
            SUM(CASE WHEN type_action='texte'  THEN 1 ELSE 0 END) AS nb_texte,
            AVG(score_confiance) AS score_moyen,
            MAX(badges)          AS badges,
            MAX(image_path)      AS image_path,
            MAX(cree_le)         AS dernier_scan
        FROM `scans_plantes`
        WHERE user_id = ?
        GROUP BY nom_scientifique
        ORDER BY total DESC
        LIMIT 20
    ");
    $r->execute([$id_user]);
    $top_plantes = $r->fetchAll();
} catch(Exception $e) {}

// ── Statistiques globales (toutes les plantes de tous les utilisateurs) ──
$top_global = [];
try {
    $r = $conn->query("
        SELECT
            nom_scientifique,
            MAX(nom_commun) AS nom_commun,
            COUNT(*)        AS total,
            COUNT(DISTINCT user_id) AS nb_utilisateurs,
            MAX(badges)     AS badges,
            MAX(image_path) AS image_path
        FROM `scans_plantes`
        GROUP BY nom_scientifique
        ORDER BY total DESC
        LIMIT 10
    ");
    $top_global = $r->fetchAll();
} catch(Exception $e) {}

// ── Répartition par badge ──
$stats_badges = [];
try {
    $r = $conn->prepare("SELECT badges, COUNT(*) as total FROM `scans_plantes` WHERE user_id=? AND badges != '' GROUP BY badges ORDER BY total DESC");
    $r->execute([$id_user]);
    $stats_badges = $r->fetchAll();
} catch(Exception $e) {}

// ── Derniers scans ──
$derniers = [];
try {
    $r = $conn->prepare("SELECT * FROM `scans_plantes` WHERE user_id=? ORDER BY cree_le DESC LIMIT 5");
    $r->execute([$id_user]);
    $derniers = $r->fetchAll();
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques — WhatAPlant</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d5c3a">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --g1:#0d5c3a; --g2:#1b8a5e; --g3:#34d399;
            --g4:#a7f3d0; --g5:#e8f5ee; --g6:#f4fbf7;
            --bord:#c8e6d0; --txt:#1f3a2f; --txt2:#4a6b5a;
            --blanc:#ffffff; --rouge:#ef4444;
        }
        body { font-family:'Nunito',sans-serif; background:var(--g6); color:var(--txt); min-height:100vh; }

        /* En-tête */
        .entete {
            background: linear-gradient(135deg,var(--g1),var(--g2) 60%,var(--g3));
            padding: 16px 24px;
            display: flex; align-items: center; gap: 14px;
            box-shadow: 0 2px 20px rgba(13,92,58,.2);
            position: sticky; top:0; z-index:50;
        }
        .btn-retour {
            background:rgba(255,255,255,.18); border:none; color:white;
            width:40px; height:40px; border-radius:50%; font-size:18px;
            cursor:pointer; display:flex; align-items:center; justify-content:center;
            transition:background .2s;
        }
        .btn-retour:hover { background:rgba(255,255,255,.32); }
        .entete-titre { font-family:'Playfair Display',serif; font-size:22px; color:white; }
        .entete-sous   { font-size:13px; color:rgba(255,255,255,.75); }

        /* Contenu */
        .contenu { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }

        /* Cartes de stats rapides */
        .cartes-stats {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr));
            gap: 16px; margin-bottom: 32px;
        }
        .carte-stat {
            background: var(--blanc); border-radius: 16px;
            border: 1px solid var(--bord);
            padding: 20px 16px; text-align: center;
            box-shadow: 0 2px 12px rgba(13,92,58,.06);
        }
        .stat-icone { font-size: 36px; margin-bottom: 8px; }
        .stat-valeur { font-size: 32px; font-weight: 900; color: var(--g2); }
        .stat-label  { font-size: 12px; color: var(--txt2); font-weight: 600; margin-top: 4px; text-transform:uppercase; letter-spacing:.05em; }

        /* Titre de section */
        .titre-section {
            font-family:'Playfair Display',serif;
            font-size: 20px; color: var(--g1);
            margin: 32px 0 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .titre-section::after {
            content:''; flex:1; height:2px;
            background: linear-gradient(90deg,var(--g4),transparent);
        }

        /* Grille de plantes */
        .grille-plantes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }
        .carte-plante {
            background: var(--blanc); border-radius: 16px;
            border: 1px solid var(--bord);
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(13,92,58,.06);
            transition: transform .2s, box-shadow .2s;
        }
        .carte-plante:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(13,92,58,.12); }

        /* Zone image */
        .carte-img-wrap {
            position: relative; height: 160px;
            background: var(--g5);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }
        .carte-img-wrap img {
            width:100%; height:100%; object-fit:cover;
        }
        .carte-img-wrap .placeholder {
            font-size: 56px;
        }
        .badge-rang {
            position:absolute; top:10px; left:10px;
            background: var(--g1); color:white;
            font-size:12px; font-weight:800;
            padding:3px 10px; border-radius:50px;
        }
        .badge-count {
            position:absolute; top:10px; right:10px;
            background: rgba(0,0,0,.55); color:white;
            font-size:12px; font-weight:700;
            padding:3px 10px; border-radius:50px;
            backdrop-filter: blur(4px);
        }

        /* Corps de la carte */
        .carte-corps { padding: 14px; }
        .nom-commun    { font-size:15px; font-weight:700; color:var(--txt); }
        .nom-sci       { font-size:12px; color:var(--txt2); font-style:italic; margin-top:2px; }
        .famille       { font-size:11px; color:var(--g2); font-weight:600; margin-top:4px; }

        /* Mini badges */
        .mini-badges { display:flex; flex-wrap:wrap; gap:4px; margin-top:10px; }
        .mini-badge {
            font-size:11px; font-weight:700; padding:2px 8px;
            border-radius:50px;
        }
        .mini-badge.comestible { background:#d1fae5; color:#065f46; }
        .mini-badge.medicinal  { background:#dbeafe; color:#1e40af; }
        .mini-badge.toxique    { background:#fee2e2; color:#991b1b; }
        .mini-badge.agricole   { background:#fef9c3; color:#854d0e; }

        /* Stats de la carte */
        .carte-stats-ligne {
            display:flex; gap:8px; margin-top:10px;
            font-size:12px; color:var(--txt2);
        }
        .carte-stats-ligne span {
            display:flex; align-items:center; gap:3px;
        }

        /* Tableau global */
        .tableau {
            width:100%; border-collapse:collapse;
            background:var(--blanc); border-radius:16px;
            overflow:hidden; box-shadow:0 2px 12px rgba(13,92,58,.06);
        }
        .tableau th {
            background:var(--g1); color:white;
            padding:12px 16px; text-align:left;
            font-size:13px; font-weight:700;
        }
        .tableau td {
            padding:12px 16px; font-size:14px;
            border-bottom:1px solid var(--bord);
        }
        .tableau tr:last-child td { border-bottom:none; }
        .tableau tr:hover td { background:var(--g5); }

        .barre-progress {
            height:6px; border-radius:50px;
            background:var(--g5); overflow:hidden;
        }
        .barre-fill {
            height:100%; border-radius:50px;
            background:linear-gradient(90deg,var(--g3),var(--g2));
            transition:width .5s;
        }

        /* Message vide */
        .message-vide {
            text-align:center; padding:60px 24px;
            color:var(--txt2);
        }
        .message-vide .icone { font-size:64px; margin-bottom:16px; display:block; }
        .message-vide h3 { font-size:18px; font-weight:700; margin-bottom:8px; color:var(--txt); }
        .message-vide p  { font-size:14px; line-height:1.6; }
        .btn-scanner {
            display:inline-flex; align-items:center; gap:8px;
            margin-top:20px; padding:12px 24px;
            background:var(--g2); color:white;
            border:none; border-radius:50px;
            font-size:15px; font-weight:700;
            font-family:'Nunito',sans-serif;
            cursor:pointer; text-decoration:none;
            transition:background .2s;
        }
        .btn-scanner:hover { background:var(--g1); }

        /* Derniers scans */
        .liste-recents { display:flex; flex-direction:column; gap:10px; }
        .item-recent {
            background:var(--blanc); border:1px solid var(--bord);
            border-radius:12px; padding:12px 16px;
            display:flex; align-items:center; gap:14px;
        }
        .item-recent-img {
            width:56px; height:56px; border-radius:10px;
            object-fit:cover; flex-shrink:0;
            background:var(--g5);
        }
        .item-recent-infos { flex:1; min-width:0; }
        .item-recent-nom   { font-size:14px; font-weight:700; }
        .item-recent-sci   { font-size:12px; color:var(--txt2); font-style:italic; }
        .item-recent-date  { font-size:11px; color:var(--txt2); margin-top:3px; }
        .icone-type {
            font-size:20px; flex-shrink:0;
        }

        @media (max-width:600px) {
            .grille-plantes { grid-template-columns:1fr 1fr; }
            .cartes-stats   { grid-template-columns:1fr 1fr; }
        }
    </style>
</head>
<body>

<!-- En-tête -->
<div class="entete">
    <button class="btn-retour" onclick="history.back()">←</button>
    <div>
        <div class="entete-titre">📊 Tableau de bord</div>
        <div class="entete-sous">Plantes analysées par <?= $nom_user ?></div>
    </div>
</div>

<div class="contenu">

    <!-- Cartes stats rapides -->
    <div class="cartes-stats">
        <div class="carte-stat">
            <div class="stat-icone">🔍</div>
            <div class="stat-valeur"><?= number_format($total_scans) ?></div>
            <div class="stat-label">Analyses totales</div>
        </div>
        <div class="carte-stat">
            <div class="stat-icone">🌿</div>
            <div class="stat-valeur"><?= number_format($plantes_uniques) ?></div>
            <div class="stat-label">Espèces uniques</div>
        </div>
        <div class="carte-stat">
            <div class="stat-icone">📷</div>
            <div class="stat-valeur">
                <?php
                try {
                    $r = $conn->prepare("SELECT COUNT(*) FROM `scans_plantes` WHERE user_id=? AND type_action='upload'");
                    $r->execute([$id_user]); echo $r->fetchColumn();
                } catch(Exception $e) { echo 0; }
                ?>
            </div>
            <div class="stat-label">Photos uploadées</div>
        </div>
        <div class="carte-stat">
            <div class="stat-icone">🌍</div>
            <div class="stat-valeur"><?= count($top_global) ?></div>
            <div class="stat-label">Plantes populaires</div>
        </div>
    </div>

    <?php if (empty($top_plantes)): ?>
    <!-- Aucun scan encore -->
    <div class="message-vide">
        <span class="icone">🌱</span>
        <h3>Aucune plante analysée encore</h3>
        <p>Scannez ou téléchargez des photos de plantes pour voir vos statistiques ici.</p>
        <a href="chat.php" class="btn-scanner">🌿 Commencer l'analyse</a>
    </div>

    <?php else: ?>

    <!-- Mes plantes les plus analysées -->
    <div class="titre-section">🌿 Mes plantes les plus analysées</div>
    <div class="grille-plantes">
        <?php foreach($top_plantes as $i => $p): ?>
        <?php
        $badges_arr = array_filter(explode(',', $p['badges'] ?? ''));
        $emoji = '🌿';
        if (in_array('comestible',$badges_arr)) $emoji = '🍽️';
        if (in_array('medicinal',$badges_arr))  $emoji = '💊';
        if (in_array('toxique',$badges_arr))    $emoji = '☠️';
        if (in_array('agricole',$badges_arr))   $emoji = '🌾';
        ?>
        <div class="carte-plante">
            <div class="carte-img-wrap">
                <?php if ($p['image_path'] && file_exists(__DIR__.'/'.$p['image_path'])): ?>
                    <img src="<?= htmlspecialchars($p['image_path']) ?>"
                         alt="<?= htmlspecialchars($p['nom_scientifique']) ?>">
                <?php else: ?>
                    <!-- Charger depuis Wikipedia -->
                    <div class="placeholder" id="ph-<?= $i ?>">🌿</div>
                    <img id="wi-<?= $i ?>" style="display:none;width:100%;height:100%;object-fit:cover;" alt="<?= htmlspecialchars($p['nom_scientifique']) ?>">
                    <script>
                    (function(){
                        rechercherWikiImage(<?= json_encode($p['nom_scientifique']) ?>, 'en')
                        .then(function(url){
                            if(url){
                                var img = document.getElementById('wi-<?= $i ?>');
                                var ph  = document.getElementById('ph-<?= $i ?>');
                                img.src = url;
                                img.onload = function(){ if(ph) ph.remove(); img.style.display='block'; };
                            }
                        });
                    })();
                    </script>
                <?php endif; ?>
                <span class="badge-rang">#<?= $i+1 ?></span>
                <span class="badge-count">🔍 <?= $p['total'] ?> fois</span>
            </div>
            <div class="carte-corps">
                <div class="nom-commun"><?= htmlspecialchars($p['nom_commun'] ?: 'Plante inconnue') ?></div>
                <div class="nom-sci"><?= htmlspecialchars($p['nom_scientifique']) ?></div>
                <?php if ($p['famille']): ?>
                <div class="famille">Famille : <?= htmlspecialchars($p['famille']) ?></div>
                <?php endif; ?>

                <div class="mini-badges">
                    <?php foreach($badges_arr as $b): ?>
                    <span class="mini-badge <?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="carte-stats-ligne">
                    <span>📷 <?= $p['nb_camera'] + $p['nb_upload'] ?> photos</span>
                    <?php if ($p['score_moyen'] > 0): ?>
                    <span>✅ <?= round($p['score_moyen']) ?>% confiance</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:11px;color:var(--txt2);margin-top:6px;">
                    Dernier scan : <?= date('d/m/Y', strtotime($p['dernier_scan'])) ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Derniers scans -->
    <div class="titre-section">🕐 Dernières analyses</div>
    <div class="liste-recents">
        <?php foreach($derniers as $d): ?>
        <?php
        $icone_type = $d['type_action'] === 'upload' ? '📷' : ($d['type_action'] === 'scan' ? '📱' : '💬');
        ?>
        <div class="item-recent">
            <div class="icone-type"><?= $icone_type ?></div>
            <?php if ($d['image_path'] && file_exists(__DIR__.'/'.$d['image_path'])): ?>
                <img class="item-recent-img" src="<?= htmlspecialchars($d['image_path']) ?>" alt="photo">
            <?php else: ?>
                <div class="item-recent-img" style="display:flex;align-items:center;justify-content:center;font-size:28px;">🌿</div>
            <?php endif; ?>
            <div class="item-recent-infos">
                <div class="item-recent-nom"><?= htmlspecialchars($d['nom_commun'] ?: $d['nom_scientifique']) ?></div>
                <div class="item-recent-sci"><?= htmlspecialchars($d['nom_scientifique']) ?></div>
                <div class="item-recent-date"><?= date('d/m/Y à H:i', strtotime($d['cree_le'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <!-- Top global (toutes les plantes de tous les utilisateurs) -->
    <?php if (!empty($top_global)): ?>
    <div class="titre-section">🌍 Plantes les plus scannées (global)</div>
    <table class="tableau">
        <thead>
            <tr>
                <th>#</th>
                <th>Espèce</th>
                <th>Nom commun</th>
                <th>Total scans</th>
                <th>Popularité</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $max_global = $top_global[0]['total'] ?? 1;
        foreach($top_global as $i => $g): ?>
        <tr>
            <td><strong><?= $i+1 ?></strong></td>
            <td><em><?= htmlspecialchars($g['nom_scientifique']) ?></em></td>
            <td><?= htmlspecialchars($g['nom_commun'] ?: '—') ?></td>
            <td><strong><?= $g['total'] ?></strong></td>
            <td style="min-width:120px;">
                <div class="barre-progress">
                    <div class="barre-fill" style="width:<?= round($g['total']/$max_global*100) ?>%"></div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div style="text-align:center;margin-top:32px;">
        <a href="chat.php" class="btn-scanner">🌿 Nouvelle analyse</a>
    </div>

</div>

<script>
/* rechercherWikiImage utilisée pour les images des cartes */
async function rechercherWikiImage(terme, lang) {
    try {
        const urlSearch = `https://${lang}.wikipedia.org/w/api.php`
            + `?action=query&list=search&srsearch=${encodeURIComponent(terme)}`
            + `&srlimit=2&format=json&origin=*`;
        const rep  = await fetch(urlSearch, { signal: AbortSignal.timeout(7000) });
        const data = await rep.json();
        const resultats = data?.query?.search || [];
        for (const res of resultats) {
            const urlImg = `https://${lang}.wikipedia.org/w/api.php`
                + `?action=query&pageids=${res.pageid}`
                + `&prop=pageimages&pithumbsize=400&pilicense=any&format=json&origin=*`;
            const repImg  = await fetch(urlImg, { signal: AbortSignal.timeout(5000) });
            const dataImg = await repImg.json();
            const pages   = dataImg?.query?.pages || {};
            const page    = Object.values(pages)[0];
            const thumb   = page?.thumbnail?.source;
            if (thumb) return thumb;
        }
    } catch(e) {}
    return null;
}
</script>
</body>
</html>