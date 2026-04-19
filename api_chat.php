<?php
/**
 * api_chat.php — WhatAPlant FINAL SOUTENANCE
 * Corrigé : accolade manquante + images Wikimedia
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
define('GROQ_API_KEY',      getenv('GROQ_API_KEY')     ?: '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');
define('PLANTNET_API_KEY',  getenv('PLANTNET_API_KEY') ?: '2b10Ur6NApHlKKjxGB9oXxCge');
define('GROQ_MODELE_TEXTE', 'llama-3.3-70b-versatile');
define('GROQ_MODELE_VISION','meta-llama/llama-4-scout-17b-16e-instruct');
define('GROQ_URL',          'https://api.groq.com/openai/v1/chat/completions');
define('PLANTNET_URL',      'https://my-api.plantnet.org/v2/identify/all');
define('UPLOADS_DIR',       __DIR__ . '/uploads/');

if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
    file_put_contents(UPLOADS_DIR . '.htaccess', "Options -Indexes\n");
}

// ── DONNÉES POST ──
$id_user    = (int)$_SESSION['user_id'];
$id_disc    = (int)($_POST['disc_id'] ?? 0);
$message    = trim($_POST['message'] ?? '');
$table_disc = "discussions_utilisateur_" . $id_user;
$table_msg  = "messages_utilisateur_"    . $id_user;

// ── DÉTECTION TYPE DE REQUÊTE ──
$mots_plantes = ['moringa','manioc','basilic','gombo','gombô','plantain','igname','cacao',
    'café','maïs','tomate','ananas','papaye','gingembre','curcuma','menthe','eucalyptus',
    'vernonia','amarante','patate','piment','aubergine','courge','haricot','arachide',
    'sorgho','mil','sésame','coton','hévéa','palmier','baobab','neem','karité',
    'citronnier','manguier','avocatier','bananier','cocotier','caféier','cacaoyer'];

$est_question_plante = false;
if (!empty($message)) {
    $msg_lower = mb_strtolower($message);
    foreach ($mots_plantes as $mot) {
        if (str_contains($msg_lower, $mot)) { $est_question_plante = true; break; }
    }
    $mots_generaux = ['bonjour','bonsoir','salut','comment tu','tu vas','ça va','merci',
        'aide-moi','hello','qui es-tu','présente-toi','que peux-tu'];
    foreach ($mots_generaux as $mot) {
        if (str_contains($msg_lower, $mot)) { $est_question_plante = false; break; }
    }
}

// ── TRAITEMENT IMAGE ──
$image_base64 = ''; $image_mime = 'image/jpeg';
$mode_vision = false; $image_sauvegardee = null;
$plantnet_result = null; $contexte_plantnet = '';

if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $chemin_tmp = $_FILES['image']['tmp_name'];
    $image_mime = mime_content_type($chemin_tmp);
    if (!in_array($image_mime, ['image/jpeg','image/jpg','image/png','image/webp'])) {
        echo json_encode(['error' => 'Format non supporté. Utilisez JPEG, PNG ou WebP.']); exit;
    }
    $binaire = file_get_contents($chemin_tmp);

    // Compression image — vérifie si l'extension GD est disponible
    if (function_exists('imagecreatefromstring') && !empty($binaire)) {
        $ressource = @imagecreatefromstring($binaire);
        if ($ressource) {
            $w = imagesx($ressource); $h = imagesy($ressource);
            if ($w > 1024) {
                $nh  = (int)($h * 1024 / $w);
                $red = imagecreatetruecolor(1024, $nh);
                imagecopyresampled($red, $ressource, 0,0,0,0, 1024,$nh,$w,$h);
                imagedestroy($ressource); $ressource = $red;
            }
            ob_start(); imagejpeg($ressource, null, 82); $compresse = ob_get_clean();
            $image_base64 = base64_encode($compresse);
            $image_mime   = 'image/jpeg';
            imagedestroy($ressource);
        } else {
            // GD disponible mais image non reconnue → encoder directement
            $image_base64 = base64_encode($binaire);
        }
    } else {
        // GD non disponible → encoder directement sans compression
        $image_base64 = base64_encode($binaire);
    }
    $mode_vision         = true;
    $est_question_plante = true;
    $nom_fichier         = 'img_'.$id_user.'_'.time().'_'.rand(100,999).'.jpg';
    $chemin_sauvegarde   = UPLOADS_DIR . $nom_fichier;
    file_put_contents($chemin_sauvegarde, base64_decode($image_base64));
    $image_sauvegardee   = 'uploads/' . $nom_fichier;

    $plantnet_result = callPlantNet($chemin_tmp, 'image/jpeg');
    if ($plantnet_result && $plantnet_result['score'] >= 10) {
        $contexte_plantnet  = "=== IDENTIFICATION PLANTNET ===\n";
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
    echo json_encode(['error' => 'Message ou image requis.']); exit;
}

// ── HISTORIQUE ──
$historique = [];
if ($id_disc > 0) {
    try {
        $req = $conn->prepare("SELECT role, contenu FROM `$table_msg` WHERE discussion_id=? ORDER BY cree_le DESC LIMIT 8");
        $req->execute([$id_disc]);
        foreach (array_reverse($req->fetchAll(PDO::FETCH_ASSOC)) as $l) {
            $propre = preg_replace('/<!--IMGMETA:[^-]*-->/s', '', $l['contenu']);
            $historique[] = [
                'role'    => $l['role'] === 'ai' ? 'assistant' : 'user',
                'content' => mb_substr(strip_tags($propre), 0, 500),
            ];
        }
    } catch(Exception $e) {}
}

// ── SYSTEM PROMPT ──
$system_prompt = <<<'PROMPT'
Tu es WhatAPlant, l'application IA botanique et agricole de référence pour l'Afrique.
Tes utilisateurs : agriculteurs, herboristes, familles, étudiants en agronomie de Côte d'Ivoire.

RÈGLES ABSOLUES :
R1 — Commence TOUJOURS par [BADGES:...]. Si question générale → [BADGES:aucun]
R2 — Nom scientifique en <em>Genre espèce</em>. Si PlantNet identifié → l'utiliser obligatoirement.
R3 — HTML uniquement : <strong> <em> <br> <ul> <li>. Pas d'astérisques ni de #.
R4 — Si comestible → terminer par [RECIPE:{...}] avec recette de LA plante analysée.
R5 — Ne JAMAIS mélanger les plantes. Manioc → tout sur manioc. Moringa → tout sur moringa.
R6 — Question générale (bonjour, comment vas-tu...) → [BADGES:aucun], réponse simple.

CAS A — IMAGE PLANTE :
<strong>🔬 Identification</strong><br>
Nom commun : [français + nom local ivoirien]<br>
Nom scientifique : <em>[Genre espèce]</em><br>
Famille : [famille botanique]<br>
Confiance : [Élevée/Moyenne/Faible]<br><br>
<strong>🌿 État de santé</strong><br>[Saine/Malade + description + traitement si malade]<br><br>
<strong>🍽️ Comestibilité</strong><br>[Oui/Non/Partiellement + parties + nutrition]<br><br>
<strong>💊 Propriétés médicinales</strong><br>[usages + préparation + posologie + contre-indications]<br>
⚕️ Consultez un médecin si symptômes persistent plus de 48h<br><br>
<strong>☠️ Toxicité</strong><br>Niveau : [Aucun/Faible/Moyen/Élevé]<br><br>
<strong>🌾 Impact agricole</strong><br>[Invasive ? Compatible avec quelles cultures ?]<br><br>
<strong>💡 Le saviez-vous ?</strong><br>[Fait surprenant en Afrique]

CAS B — PLANTATION : Diagnostic + maturité + conseils agronomiques

CAS C — SYMPTÔME HUMAIN : 2-3 plantes africaines avec préparation et posologie

BADGES : [BADGES:comestible,medicinal] [BADGES:comestible] [BADGES:medicinal] [BADGES:toxique] [BADGES:agricole] [BADGES:aucun]

SI COMESTIBLE → [RECIPE:{"title":"Recette avec NOM PLANTE","items":["Ingrédient 1","Étape 1 : ...","Étape 2 : ..."]}]
SI MALADIE → [DISEASE:{"name":"nom","treatment":["Naturel : ...","Chimique : ..."],"prevention":["Action 1"]}]
SI MATURITÉ → [HARVEST:{"stage":"stade","days":"X jours","advice":"conseil"}]

Réponds en français. Sois précis et utile.
PROMPT;

// ── APPEL GROQ ──
$modele   = $mode_vision ? GROQ_MODELE_VISION : GROQ_MODELE_TEXTE;
$texte_u  = $contexte_plantnet . ($message ?: "Analyse cette image selon le format demandé.");
$contenu_user = [];
if ($mode_vision)
    $contenu_user[] = ['type'=>'image_url','image_url'=>['url'=>"data:{$image_mime};base64,{$image_base64}"]];
$contenu_user[] = ['type'=>'text','text'=>$texte_u];
$msg_user = $mode_vision ? $contenu_user : $texte_u;

$messages = array_merge(
    [['role'=>'system','content'=>$system_prompt]],
    $historique,
    [['role'=>'user','content'=>$msg_user]]
);

$payload = ['model'=>$modele,'messages'=>$messages,'max_tokens'=>2000,'temperature'=>0.2];
$ch = curl_init(GROQ_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.GROQ_API_KEY],
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$brut = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($cerr) { echo json_encode(['error'=>'Erreur réseau cURL : '.$cerr]); exit; }
if ($code !== 200) {
    $d = json_decode($brut, true);
    $m = $d['error']['message'] ?? substr($brut, 0, 200);
    echo json_encode(['error' => match(true) {
        $code===401 => '❌ Clé Groq invalide — vérifiez GROQ_API_KEY dans Railway Variables',
        $code===429 => '⏱️ Trop de requêtes Groq, attendez quelques secondes',
        default     => "Erreur Groq HTTP $code : $m"
    }]); exit;
}
$ia_brut = json_decode($brut, true)['choices'][0]['message']['content'] ?? null;
if (!$ia_brut) { echo json_encode(['error' => "L'IA n'a pas répondu."]); exit; }

// ── PARSING ──
$html   = $ia_brut;
$badges = $recette = $maladie = $recolte = null;

if (preg_match('/\[BADGES:([^\]]+)\]/i', $html, $m)) {
    $badges = array_map('trim', explode(',', strtolower($m[1])));
    $html   = str_replace($m[0], '', $html);
}
$badges = $badges ?: [];
$est_question_generale = in_array('aucun', $badges) || empty($badges);

if (preg_match('/\[RECIPE:\s*(\{.*?\})\s*\]/s', $html, $m)) {
    $rd = json_decode($m[1], true);
    if ($rd && isset($rd['title'], $rd['items'])) $recette = $rd;
    $html = str_replace($m[0], '', $html);
}
if (preg_match('/\[DISEASE:\s*(\{.*?\})\s*\]/s', $html, $m)) {
    $md = json_decode($m[1], true);
    if ($md && isset($md['name'])) $maladie = $md;
    $html = str_replace($m[0], '', $html);
}
if (preg_match('/\[HARVEST:\s*(\{.*?\})\s*\]/s', $html, $m)) {
    $hd = json_decode($m[1], true);
    if ($hd && isset($hd['stage'])) $recolte = $hd;
    $html = str_replace($m[0], '', $html);
}

// Nettoyage Markdown résiduel
$html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
$html = preg_replace('/\*(.+?)\*/s',     '<em>$1</em>', $html);
$html = preg_replace('/^#{1,4}\s+(.+)$/m', '<strong>$1</strong><br>', $html);
$html = preg_replace('/\[(RECIPE|DISEASE|HARVEST|IMG_PLAT|IMG_REMEDE|BADGES)[^\]]*\]/s', '', $html);
$html = trim($html);

// Badges HTML
$carte_badges = [
    'comestible' => ['comestible','🍽️ Comestible'],
    'medicinal'  => ['medicinal', '💊 Médicinal'],
    'toxique'    => ['toxique',   '☠️ Toxique'],
    'agricole'   => ['agricole',  '🌾 Agricole'],
    'malade'     => ['malade',    '🔴 Maladie'],
];
$bh = '';
foreach ($badges as $b) {
    if (isset($carte_badges[$b]))
        $bh .= "<span class=\"badge {$carte_badges[$b][0]}\">{$carte_badges[$b][1]}</span> ";
}
if ($bh) $html = rtrim($bh).'<br><br>'.ltrim($html);
$html       = trim($html);
$plain_text = trim(strip_tags($html));

// ── IMAGES ──
$img_plante = null; $img_plat = null; $img_remede = null; $nom_sci = '';

// Générer images SEULEMENT si c'est une vraie plante (pas question générale)
if (!$est_question_generale && ($mode_vision || $est_question_plante)) {

    if (preg_match('/<em>([A-Z][a-z]+(?: [a-z]+)+)<\/em>/u', $html, $ns))
        $nom_sci = trim($ns[1]);
    if ($plantnet_result && $plantnet_result['score'] >= 15)
        $nom_sci = $plantnet_result['nom_scientifique'];
    if (empty($nom_sci)) $nom_sci = mb_substr($message, 0, 50);

    // Image plante = photo uploadée (toujours exacte)
    if ($image_sauvegardee) {
        $img_plante = $image_sauvegardee;
    } elseif ($plantnet_result && !empty($plantnet_result['image_url'])) {
        $img_plante = $plantnet_result['image_url'];
    }

    // Image plat — terme de recherche Wikimedia
    if (!in_array('toxique', $badges) && in_array('comestible', $badges) && !empty($nom_sci)) {
        $traductions = [
            'arachide'=>'peanut','manioc'=>'cassava','moringa'=>'moringa',
            'gombo'=>'okra','gombô'=>'okra','plantain'=>'plantain',
            'igname'=>'yam','maïs'=>'maize','tomate'=>'tomato',
            'aubergine'=>'eggplant','haricot'=>'bean','riz'=>'rice',
            'patate'=>'sweet potato','papaye'=>'papaya','banane'=>'banana',
            'piment'=>'chili pepper','basilic'=>'basil','gingembre'=>'ginger',
        ];
        $titre_rec = $recette['title'] ?? '';
        if (!empty($titre_rec)) {
            $terme = strtolower($titre_rec);
            foreach ($traductions as $fr => $en) $terme = str_replace($fr, $en, $terme);
            $terme = preg_replace('/(maison|africain[e]?|traditionnel[le]?|de la|du|de|au|le|la|les|et)\b/iu', ' ', $terme);
            $terme = trim(preg_replace('/\s+/', ' ', $terme));
        } else {
            $terme = strtolower($nom_sci) . ' food';
        }
        $img_plat = '__FOOD__' . urlencode($terme . ' dish');
    }

    // Image remède — terme de recherche Wikimedia
    if (!in_array('toxique', $badges) && in_array('medicinal', $badges) && !empty($nom_sci)) {
        $img_remede = '__MED__' . urlencode($nom_sci);
    }

} // ← FIN du if (!$est_question_generale)

// ── SAUVEGARDE BDD ──
$titre_disc = '';
$scan_id = 0;

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Discussion
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

    // 2. Message utilisateur
    $msg_user_bdd = $mode_vision ? '[IMAGE:'.$image_sauvegardee.'] '.($message ?: '') : $message;
    $conn->prepare("INSERT INTO `$table_msg` (discussion_id, role, contenu) VALUES (?, 'user', ?)")
         ->execute([$id_disc, $msg_user_bdd]);

    // 3. Message IA
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

    // ==================== SCANS_PLANTES ====================
    if (!empty($nom_sci) && !$est_question_generale) {

        $stmt = $conn->prepare("INSERT INTO `scans_plantes`
            (user_id, nom_scientifique, nom_commun, famille, score_confiance,
             type_action, badges, image_path, est_malade)
            VALUES (?,?,?,?,?,?,?,?,?)");

        $stmt->execute([
            $id_user,
            mb_substr($nom_sci, 0, 255),
            mb_substr(implode(', ', array_slice($plantnet_result['nom_commun'] ?? [], 0, 3)), 0, 500),
            mb_substr($plantnet_result['famille'] ?? '', 0, 100),
            (int)($plantnet_result['score'] ?? 0),
            $mode_vision ? 'upload' : 'texte',
            mb_substr(implode(',', $badges ?: []), 0, 255),
            $image_sauvegardee ?? '',
            ($maladie !== null) ? 1 : 0
        ]);

        $scan_id = (int)$conn->lastInsertId();

        // ==================== MALADIES_DETECTEES ====================
        if ($maladie && $scan_id > 0) {
            $type_mal = 'autre';
            $desc = strtolower($maladie['name'] ?? '');
            if (str_contains($desc, 'fongique') || str_contains($desc, 'mildiou')) $type_mal = 'fongique';
            elseif (str_contains($desc, 'bactéri')) $type_mal = 'bacterienne';
            elseif (str_contains($desc, 'viral') || str_contains($desc, 'virus')) $type_mal = 'virale';

            $conn->prepare("INSERT INTO `maladies_detectees`
                (scan_id, user_id, plante_hote, nom_maladie, type_maladie)
                VALUES (?,?,?,?,?)")
                ->execute([
                    $scan_id,
                    $id_user,
                    $nom_sci,
                    $maladie['name'] ?? '',
                    $type_mal
                ]);
        }

        // ==================== ALERTES_SYSTEME (optionnel) ====================
        if ($maladie && $scan_id > 0 && ($plantnet_result['score'] ?? 0) >= 70) {
            $titre_alerte = "Maladie détectée sur " . $nom_sci;
            $description = "Une " . strtolower($maladie['name'] ?? 'maladie') . 
                           " a été détectée sur " . $nom_sci . 
                           " avec une confiance de " . ($plantnet_result['score'] ?? 0) . "%";

            $conn->prepare("INSERT INTO `alertes_systeme`
                (type_alerte, titre, description, entite, region, nb_cas, seuil_alerte, est_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    'maladie_plante',
                    $titre_alerte,
                    $description,
                    $nom_sci,
                    "Côte d'Ivoire",
                    1,
                    5,
                    1
                ]);
        }

        // Stats utilisateur
        $conn->prepare("INSERT INTO `utilisateurs_stats`
            (user_id, nb_scans_total, nb_plantes_uniques, derniere_activite)
            VALUES (?, 1, 1, NOW())
            ON DUPLICATE KEY UPDATE nb_scans_total = nb_scans_total + 1, derniere_activite = NOW()")
            ->execute([$id_user]);
    }

} catch (PDOException $e) {
    error_log("ERREUR BDD Railway - Ligne " . $e->getLine() . " : " . $e->getMessage());

    // On renvoie l'erreur clairement dans la réponse JSON
    echo json_encode([
        'error'   => 'Erreur lors de l\'enregistrement en base de données',
        'message' => $e->getMessage(),
        'line'    => $e->getLine(),
        'debug'   => 'Vérifie les logs Railway pour plus de détails'
    ], JSON_UNESCAPED_UNICODE);
    exit;   // ← Important pour voir l'erreur
}

if (empty($titre_disc)) $titre_disc = 'Discussion';

// ── RÉPONSE JSON ──
echo json_encode([
    'disc_id'      => $id_disc,
    'disc_titre'   => $titre_disc,
    'html_response'=> $html,
    'plain_text'   => $plain_text,
    'recipe'       => $recette,
    'recette'      => $recette,
    'maladie'      => $maladie,
    'recolte'      => $recolte,
    'img_plante'   => $img_plante,
    'image_plante' => $img_plante,
    'img_dish'     => $img_plat,
    'image_plat'   => $img_plat,
    'img_med'      => $img_remede,
    'image_remede' => $img_remede,
    'nom_sci'      => $nom_sci,
    'nom_plat'     => $recette['title'] ?? '',
    'est_generale' => $est_question_generale,
], JSON_UNESCAPED_UNICODE);
exit;

// ── FONCTIONS ──
function callPlantNet(string $chemin, string $mime): ?array {
    if (!file_exists($chemin)) return null;
    $url = PLANTNET_URL . '?api-key=' . PLANTNET_API_KEY . '&lang=fr&include-related-images=true';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['organs'=>'auto','images'=>new CURLFile($chemin,$mime,'plant.jpg')],
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$res) return null;
    $data = json_decode($res, true);
    if (empty($data['results'][0])) return null;
    $top = $data['results'][0]; $sp = $top['species'] ?? [];
    return [
        'nom_scientifique' => $sp['scientificNameWithoutAuthor'] ?? $data['bestMatch'] ?? '',
        'score'            => round(($top['score'] ?? 0) * 100),
        'nom_commun'       => $sp['commonNames'] ?? [],
        'famille'          => $sp['family']['scientificNameWithoutAuthor'] ?? '',
        'image_url'        => $top['images'][0]['url']['m'] ?? null,
    ];
}
