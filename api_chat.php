<?php
/**
 * api_chat.php — WhatAPlant FINAL CORRIGÉ
 * Corrections : BDD + gestion erreurs + colonnes exactes
 */

ob_start();
set_error_handler(function($code, $msg, $file, $line) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => "Erreur PHP [$code]: $msg ligne $line dans ".basename($file)]);
    exit;
});

register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => "Erreur fatale: {$e['message']} ligne {$e['line']}"]);
    }
});

require_once 'connect_db.php';
requireLogin();

ob_clean();
header('Content-Type: application/json; charset=utf-8');

// ── CONFIG ──
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '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');
define('PLANTNET_API_KEY', getenv('PLANTNET_API_KEY') ?: '2b10Ur6NApHlKKjxGB9oXxCge');
define('GROQ_MODELE_TEXTE', 'llama-3.3-70b-versatile');
define('GROQ_MODELE_VISION', 'meta-llama/llama-4-scout-17b-16e-instruct');
define('GROQ_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('PLANTNET_URL', 'https://my-api.plantnet.org/v2/identify/all');
define('UPLOADS_DIR', __DIR__ . '/uploads/');

if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
    file_put_contents(UPLOADS_DIR . '.htaccess', "Options -Indexes\n");
}

// ── DONNÉES ──
$id_user = (int)$_SESSION['user_id'];
$id_disc = (int)($_POST['disc_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$table_disc = "discussions_utilisateur_" . $id_user;
$table_msg = "messages_utilisateur_" . $id_user;

// ── TRAITEMENT IMAGE ──
$image_base64 = ''; 
$image_mime = 'image/jpeg';
$mode_vision = false; 
$image_sauvegardee = null;
$plantnet_result = null; 
$contexte_plantnet = '';

if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $chemin_tmp = $_FILES['image']['tmp_name'];
    $image_mime = mime_content_type($chemin_tmp);

    if (!in_array($image_mime, ['image/jpeg','image/jpg','image/png','image/webp'])) {
        echo json_encode(['error' => 'Format non supporté. Utilisez JPEG, PNG ou WebP.']); 
        exit;
    }

    $binaire = file_get_contents($chemin_tmp);

    // Compression si possible
    if (function_exists('imagecreatefromstring') && !empty($binaire)) {
        $ressource = @imagecreatefromstring($binaire);
        if ($ressource) {
            $w = imagesx($ressource); 
            $h = imagesy($ressource);
            if ($w > 1024) {
                $nh = (int)($h * 1024 / $w);
                $red = imagecreatetruecolor(1024, $nh);
                imagecopyresampled($red, $ressource, 0,0,0,0, 1024,$nh,$w,$h);
                imagedestroy($ressource); 
                $ressource = $red;
            }
            ob_start(); 
            imagejpeg($ressource, null, 82); 
            $compresse = ob_get_clean();
            $image_base64 = base64_encode($compresse);
            imagedestroy($ressource);
        } else {
            $image_base64 = base64_encode($binaire);
        }
    } else {
        $image_base64 = base64_encode($binaire);
    }

    $mode_vision = true;
    $est_question_plante = true;

    $nom_fichier = 'img_'.$id_user.'_'.time().'_'.rand(100,999).'.jpg';
    file_put_contents(UPLOADS_DIR . $nom_fichier, base64_decode($image_base64));
    $image_sauvegardee = 'uploads/' . $nom_fichier;

    $plantnet_result = callPlantNet($chemin_tmp, 'image/jpeg');

    if ($plantnet_result && $plantnet_result['score'] >= 10) {
        $contexte_plantnet = "=== IDENTIFICATION PLANTNET ===\n";
        $contexte_plantnet .= "Espèce : {$plantnet_result['nom_scientifique']}\n";
        $contexte_plantnet .= "Confiance : {$plantnet_result['score']}%\n";
        if (!empty($plantnet_result['nom_commun']))
            $contexte_plantnet .= "Noms communs : " . implode(', ', $plantnet_result['nom_commun']) . "\n";
        if (!empty($plantnet_result['famille']))
            $contexte_plantnet .= "Famille : {$plantnet_result['famille']}\n";
        $contexte_plantnet .= "=== UTILISE CES INFORMATIONS OBLIGATOIREMENT ===\n\n";
    }
}

if (empty($message) && !$mode_vision) {
    echo json_encode(['error' => 'Message ou image requis.']); 
    exit;
}

// ... (le reste du code jusqu'au parsing reste identique - je ne le recopie pas pour gagner de la place)

// ── SAUVEGARDE BDD (VERSION FINALE ET SÉCURISÉE) ──
$titre_disc = '';
$scan_id = 0;

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Discussion
    if ($id_disc === 0) {
        $titre = mb_substr($message ?: 'Analyse de plante', 0, 60);
        if (mb_strlen($message) > 60) $titre .= '…';
        $conn->prepare("INSERT INTO `$table_disc` (titre) VALUES (?)")->execute([$titre]);
        $id_disc = (int)$conn->lastInsertId();
        $titre_disc = $titre;
    } else {
        $r = $conn->prepare("SELECT titre FROM `$table_disc` WHERE id=?");
        $r->execute([$id_disc]);
        $titre_disc = $r->fetchColumn() ?: 'Discussion';
    }

    // Messages user + AI
    $msg_user_bdd = $mode_vision ? '[IMAGE:'.$image_sauvegardee.'] '.($message ?: '') : $message;
    $conn->prepare("INSERT INTO `$table_msg` (discussion_id, role, contenu) VALUES (?, 'user', ?)")
         ->execute([$id_disc, $msg_user_bdd]);

    $meta = base64_encode(json_encode([
        'img_plante' => $img_plante,
        'img_plat'   => $img_plat,
        'img_remede' => $img_remede,
        'nom_sci'    => $nom_sci,
        'nom_plat'   => $recette['title'] ?? '',
    ]));

    $contenu_ai = $html . '<!--IMGMETA:' . $meta . ':IMGMETA-->';
    $conn->prepare("INSERT INTO `$table_msg` (discussion_id, role, contenu) VALUES (?, 'ai', ?)")
         ->execute([$id_disc, $contenu_ai]);

    // SCANS_PLANTES
    if (!empty($nom_sci) && !$est_question_generale) {

        $nom_commun_str = is_array($plantnet_result['nom_commun'] ?? null) 
            ? mb_substr(implode(', ', array_slice($plantnet_result['nom_commun'], 0, 3)), 0, 500) 
            : '';

        $famille_str = is_array($plantnet_result) ? mb_substr($plantnet_result['famille'] ?? '', 0, 100) : '';
        $score_int = is_array($plantnet_result) ? (int)($plantnet_result['score'] ?? 0) : 0;
        $badges_str = mb_substr(implode(',', is_array($badges) ? $badges : []), 0, 100);

        $conn->prepare("INSERT INTO `scans_plantes`
            (user_id, nom_scientifique, nom_commun, famille, score_confiance, 
             type_action, badges, image_path, est_malade)
            VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([
                $id_user,
                mb_substr($nom_sci, 0, 200),
                $nom_commun_str,
                $famille_str,
                $score_int,
                $mode_vision ? 'upload' : 'texte',
                $badges_str,
                $image_sauvegardee ?? '',
                ($maladie !== null) ? 1 : 0
            ]);

        $scan_id = (int)$conn->lastInsertId();

        // MALADIES_DETECTEES
        if ($maladie && $scan_id > 0) {
            $type_mal = 'autre';
            $desc = strtolower($maladie['name'] ?? '');
            if (str_contains($desc, 'fongique') || str_contains($desc, 'mildiou')) $type_mal = 'fongique';
            elseif (str_contains($desc, 'bactéri')) $type_mal = 'bacterienne';
            elseif (str_contains($desc, 'viral') || str_contains($desc, 'virus')) $type_mal = 'virale';

            $conn->prepare("INSERT INTO `maladies_detectees`
                (scan_id, user_id, plante_hote, nom_maladie, type_maladie, severite, 
                 traitement_naturel, traitement_chimique, region)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $scan_id,
                    $id_user,
                    $nom_sci,
                    $maladie['name'] ?? '',
                    $type_mal,
                    $maladie['severity'] ?? 'moyenne',
                    $maladie['treatment'][0] ?? null,
                    $maladie['treatment'][1] ?? null,
                    "Côte d'Ivoire"
                ]);
        }

        // ALERTES_SYSTEME (optionnel)
        if ($maladie && $scan_id > 0 && $score_int >= 60) {
            $conn->prepare("INSERT INTO `alertes_systeme`
                (type_alerte, titre, description, entite, region, nb_cas, seuil_alerte, est_active)
                VALUES (?,?,?,?,?,?,?,?)")
                ->execute([
                    'maladie_hausse',
                    "Maladie détectée : " . ($maladie['name'] ?? ''),
                    "Détection de " . ($maladie['name'] ?? '') . " sur " . $nom_sci,
                    $nom_sci,
                    "Côte d'Ivoire",
                    1,
                    5,
                    1
                ]);
        }

        // Stats
        $conn->prepare("INSERT INTO `utilisateurs_stats` 
            (user_id, nb_scans_total, nb_plantes_uniques, derniere_activite)
            VALUES (?, 1, 1, NOW())
            ON DUPLICATE KEY UPDATE nb_scans_total = nb_scans_total + 1, derniere_activite = NOW()")
            ->execute([$id_user]);
    }

} catch (PDOException $e) {
    error_log("ERREUR BDD Railway : " . $e->getMessage() . " | Ligne " . $e->getLine());
    // On ne bloque pas la réponse utilisateur
}

if (empty($titre_disc)) $titre_disc = 'Discussion';

// ── RÉPONSE JSON ──
echo json_encode([
    'disc_id' => $id_disc,
    'disc_titre' => $titre_disc,
    'html_response' => $html,
    'plain_text' => $plain_text,
    'recipe' => $recette,
    'maladie' => $maladie,
    'img_plante' => $img_plante,
    'nom_sci' => $nom_sci,
    'est_generale' => $est_question_generale,
], JSON_UNESCAPED_UNICODE);

exit;

// ── FONCTION PLANTNET ──
function callPlantNet(string $chemin, string $mime): ?array {
    if (!file_exists($chemin)) return null;
    $url = PLANTNET_URL . '?api-key=' . PLANTNET_API_KEY . '&lang=fr&include-related-images=true';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['organs'=>'auto','images'=>new CURLFile($chemin,$mime,'plant.jpg')],
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$res) return null;
    $data = json_decode($res, true);
    if (empty($data['results'][0])) return null;
    $top = $data['results'][0]; 
    $sp = $top['species'] ?? [];
    return [
        'nom_scientifique' => $sp['scientificNameWithoutAuthor'] ?? '',
        'score' => round(($top['score'] ?? 0) * 100),
        'nom_commun' => $sp['commonNames'] ?? [],
        'famille' => $sp['family']['scientificNameWithoutAuthor'] ?? '',
        'image_url' => $top['images'][0]['url']['m'] ?? null,
    ];
}
