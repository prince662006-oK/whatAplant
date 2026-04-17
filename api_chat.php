<?php
/**
 * api_chat.php — WhatAPlant v6
 * Corrections : images cohérentes, pas d'images pour questions générales
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

// ============================================================
// CONFIG
// ============================================================
define('GROQ_API_KEY',      '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');
define('PLANTNET_API_KEY',  '2b10Ur6NApHlKKjxGB9oXxCge');
define('GROQ_MODELE_TEXTE', 'llama-3.3-70b-versatile');
define('GROQ_MODELE_VISION','meta-llama/llama-4-scout-17b-16e-instruct');
define('GROQ_URL',          'https://api.groq.com/openai/v1/chat/completions');
define('PLANTNET_URL',      'https://my-api.plantnet.org/v2/identify/all');
define('UPLOADS_DIR',       __DIR__ . '/uploads/');

if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
    file_put_contents(UPLOADS_DIR . '.htaccess', "Options -Indexes\n");
}

// ============================================================
// DONNÉES POST
// ============================================================
$id_user    = (int)$_SESSION['user_id'];
$id_disc    = (int)($_POST['disc_id'] ?? 0);
$message    = trim($_POST['message'] ?? '');
$table_disc = "discussions_utilisateur_" . $id_user;
$table_msg  = "messages_utilisateur_"    . $id_user;

// ============================================================
// DÉTECTION DU TYPE DE REQUÊTE
// ============================================================
// On détecte si l'utilisateur parle d'une plante précise ou fait une question générale
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
    // Questions générales → pas d'images
    $mots_generaux = ['bonjour','bonsoir','salut','comment','tu vas','ça va','merci',
        'aide','hello','qui es-tu','présente','quoi','que peux'];
    foreach ($mots_generaux as $mot) {
        if (str_contains($msg_lower, $mot)) { $est_question_plante = false; break; }
    }
}

// ============================================================
// TRAITEMENT IMAGE
// ============================================================
$image_base64      = '';
$image_mime        = 'image/jpeg';
$mode_vision       = false;
$image_sauvegardee = null;
$plantnet_result   = null;
$contexte_plantnet = '';

if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $chemin_tmp = $_FILES['image']['tmp_name'];
    $image_mime = mime_content_type($chemin_tmp);
    $mimes_ok   = ['image/jpeg','image/jpg','image/png','image/webp'];

    if (!in_array($image_mime, $mimes_ok)) {
        echo json_encode(['error' => 'Format non supporté. Utilisez JPEG, PNG ou WebP.']); exit;
    }

    $binaire   = file_get_contents($chemin_tmp);
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
        $image_base64 = base64_encode($binaire);
    }
    $mode_vision = true;
    $est_question_plante = true; // Image = forcément une plante

    $nom_fichier       = 'img_' . $id_user . '_' . time() . '_' . rand(100,999) . '.jpg';
    $chemin_sauvegarde = UPLOADS_DIR . $nom_fichier;
    file_put_contents($chemin_sauvegarde, base64_decode($image_base64));
    $image_sauvegardee = 'uploads/' . $nom_fichier;

    // PlantNet
    $plantnet_result = callPlantNet($chemin_tmp, 'image/jpeg');
    if ($plantnet_result && $plantnet_result['score'] >= 10) {
        $contexte_plantnet  = "=== IDENTIFICATION PLANTNET (HAUTE PRÉCISION) ===\n";
        $contexte_plantnet .= "Espèce identifiée : {$plantnet_result['nom_scientifique']}\n";
        $contexte_plantnet .= "Score de confiance : {$plantnet_result['score']}%\n";
        if (!empty($plantnet_result['nom_commun']))
            $contexte_plantnet .= "Noms communs : " . implode(', ', $plantnet_result['nom_commun']) . "\n";
        if (!empty($plantnet_result['famille']))
            $contexte_plantnet .= "Famille botanique : {$plantnet_result['famille']}\n";
        $contexte_plantnet .= "=== UTILISE CES INFORMATIONS OBLIGATOIREMENT ===\n\n";
    }
}

if (empty($message) && !$mode_vision) {
    echo json_encode(['error' => 'Message ou image requis.']); exit;
}

// ============================================================
// HISTORIQUE
// ============================================================
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

// ============================================================
// SYSTEM PROMPT
// ============================================================
$system_prompt = <<<'PROMPT'
Tu es WhatAPlant, l'application IA botanique et agricole de référence pour l'Afrique.
Tes utilisateurs : agriculteurs, herboristes, familles, étudiants en agronomie de Côte d'Ivoire et pays tropicaux.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
RÈGLES ABSOLUES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

R1 — BADGE EN PREMIER : La toute première chose écrite DOIT être [BADGES:...].
     EXCEPTION : si c'est une question générale sans rapport avec une plante précise,
     écris [BADGES:aucun] et réponds normalement sans sections.

R2 — NOM SCIENTIFIQUE : Toujours écrire <em>Genre espèce</em> en italique.
     Si PlantNet a fourni une identification → utilise-la OBLIGATOIREMENT.
     INTERDIT : donner le nom scientifique d'une plante si l'utilisateur parle d'une autre.

R3 — HTML UNIQUEMENT : <strong> <em> <br> <ul> <li> — JAMAIS d'astérisques ni de #.

R4 — RECETTE : OBLIGATOIRE si la plante est comestible. La recette DOIT utiliser
     EXACTEMENT la plante analysée (ex: si c'est le manioc → recette de manioc).

R5 — COHÉRENCE : Ne JAMAIS mélanger les plantes.
     Si l'utilisateur demande le manioc → tout parle du manioc.
     Si l'utilisateur demande le moringa → tout parle du moringa.
     Ne pas ajouter d'informations sur une autre plante non demandée.

R6 — QUESTION GÉNÉRALE : Si l'utilisateur dit "bonjour", "comment tu vas", "aide-moi",
     etc. → réponds simplement et chaleureusement. [BADGES:aucun]. Pas de recette.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CAS A — IMAGE D'UNE PLANTE (feuille, fleur, tige, fruit)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

<strong>🔬 Identification</strong><br>
Nom commun : [nom français + nom local ivoirien si connu]<br>
Nom scientifique : <em>[Genre espèce — EXACTEMENT celui de PlantNet si fourni]</em><br>
Famille : [famille botanique]<br>
Confiance : [Élevée/Moyenne/Faible]<br>
<br>
<strong>🌿 État de santé</strong><br>
[Saine/Malade] — [description visuelle précise]<br>
[Si malade : nom exact maladie + traitement naturel + traitement chimique]<br>
<br>
<strong>🍽️ Comestibilité</strong><br>
[Oui/Non/Partiellement] — [parties consommables + préparation]<br>
Valeurs nutritives : [protéines, vitamines, minéraux]<br>
<br>
<strong>💊 Propriétés médicinales</strong><br>
Usages : [maladies traitées par CETTE plante]<br>
Préparation : [X g dans Y litres, bouillir Z min, filtrer]<br>
Posologie : Adulte [dose] — Enfant [dose]<br>
⚠️ Contre-indications : [liste]<br>
⚕️ Consultez un médecin si les symptômes persistent plus de 48h<br>
<br>
<strong>☠️ Toxicité</strong><br>
Niveau : [Aucun/Faible/Moyen/Élevé]<br>
[Détails si toxique]<br>
<br>
<strong>🌾 Impact agricole</strong><br>
[Invasive ? Compatible avec quelles cultures ?]<br>
<br>
<strong>💡 Le saviez-vous ?</strong><br>
[Fait surprenant sur CETTE plante en Afrique]<br>

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CAS B — PLANTATION / CULTURE AGRICOLE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
<strong>🌾 Culture identifiée</strong><br>
[Nom exact + nom scientifique <em>...</em>]<br>
<strong>🩺 Diagnostic santé</strong><br>
État : [Saine/Malade/Stressée]<br>
[Si malade : nom maladie, symptômes, traitement naturel puis chimique]<br>
<strong>📅 Maturité</strong><br>
Stade : [stade exact] — Récolte dans : [X jours]<br>
<strong>📈 Conseils agronomiques</strong><br>
[Conseils pratiques adaptés à l'Afrique]<br>

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CAS C — SYMPTÔME HUMAIN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Donner 2-3 plantes africaines spécifiques pour CE symptôme précis :
<strong>🌿 [Nom commun] (<em>Nom scientifique</em>)</strong><br>
Partie : [feuilles/racines/écorce]<br>
Préparation : [X g dans Y litres, bouillir Z min, filtrer]<br>
Adulte : [dose/fréquence] — Enfant : [dose adaptée]<br>
⚠️ Contre-indications : [liste]<br>
⚕️ Consultez un médecin si les symptômes persistent plus de 48h<br>

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FORMAT OBLIGATOIRE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Badges (première ligne TOUJOURS) :
[BADGES:comestible,medicinal] [BADGES:comestible] [BADGES:medicinal]
[BADGES:toxique] [BADGES:agricole] [BADGES:agricole,malade] [BADGES:aucun]

SI COMESTIBLE → une seule ligne à la fin (recette de LA PLANTE ANALYSÉE) :
[RECIPE:{"title":"Recette traditionnelle avec [NOM DE LA PLANTE]","items":["Quantité ingrédient 1","Quantité ingrédient 2","Étape 1 : ...","Étape 2 : ...","Étape 3 : ..."]}]

SI MALADIE → une seule ligne à la fin :
[DISEASE:{"name":"Nom maladie","treatment":["Naturel : ...","Chimique : ..."],"prevention":["Action 1","Action 2"]}]

SI MATURITÉ → une seule ligne à la fin :
[HARVEST:{"stage":"stade","days":"X-Y jours","advice":"conseil récolte"}]

Réponds TOUJOURS en français. Sois précis, scientifique et utile pour les Africains.
PROMPT;

// ============================================================
// CONSTRUCTION MESSAGE
// ============================================================
$modele  = $mode_vision ? GROQ_MODELE_VISION : GROQ_MODELE_TEXTE;
$texte_u = $contexte_plantnet . ($message ?: "Analyse précisément cette image selon le format demandé.");

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

// ============================================================
// APPEL GROQ
// ============================================================
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

if ($cerr) { echo json_encode(['error'=>'Erreur réseau : '.$cerr]); exit; }
if ($code !== 200) {
    $d = json_decode($brut, true);
    $m = $d['error']['message'] ?? substr($brut,0,200);
    echo json_encode(['error' => match(true) {
        $code===401 => '❌ Clé Groq invalide',
        $code===429 => '⏱️ Trop de requêtes, attendez quelques secondes',
        default     => "Erreur HTTP $code : $m"
    }]); exit;
}
$ia_brut = json_decode($brut, true)['choices'][0]['message']['content'] ?? null;
if (!$ia_brut) { echo json_encode(['error'=>"L'IA n'a pas répondu."]); exit; }

// ============================================================
// PARSING
// ============================================================
$html   = $ia_brut;
$badges = $recette = $maladie = $recolte = null;

if (preg_match('/\[BADGES:([^\]]+)\]/i', $html, $m)) {
    $badges = array_map('trim', explode(',', strtolower($m[1])));
    $html   = str_replace($m[0], '', $html);
}
$badges = $badges ?: [];
// Si badge "aucun" → pas d'images, pas de recette
$est_question_generale = in_array('aucun', $badges);

if (preg_match('/\[RECIPE:\s*(\{.*?\})\s*\]/s', $html, $m)) {
    $rd = json_decode($m[1], true);
    if ($rd && isset($rd['title'],$rd['items'])) $recette = $rd;
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

// Nettoyage
$html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
$html = preg_replace('/\*(.+?)\*/s',     '<em>$1</em>',         $html);
$html = preg_replace('/^#{1,4}\s+(.+)$/m','<strong>$1</strong><br>', $html);
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

// ============================================================
// IMAGES — UNIQUEMENT si question de plante précise
// ============================================================
$img_plante = null;
$img_plat   = null;
$img_remede = null;
$nom_sci    = '';

// Ne générer des images QUE si c'est une vraie analyse de plante
if (!$est_question_generale && ($mode_vision || $est_question_plante)) {

    // Extraire nom scientifique depuis la réponse IA
    if (preg_match('/<em>([A-Z][a-z]+(?: [a-z]+)+)<\/em>/u', $html, $ns))
        $nom_sci = trim($ns[1]);

    // PlantNet prioritaire (si score suffisant)
    if ($plantnet_result && $plantnet_result['score'] >= 15)
        $nom_sci = $plantnet_result['nom_scientifique'];

    if (empty($nom_sci)) $nom_sci = mb_substr($message, 0, 50);

    // Image plante : photo uploadée (toujours exacte) ou PlantNet
    if ($image_sauvegardee) {
        $img_plante = $image_sauvegardee;
    } elseif ($plantnet_result && !empty($plantnet_result['image_url'])) {
        $img_plante = $plantnet_result['image_url'];
    }
    // Sinon le JS chargera depuis Wikipedia avec $nom_sci

    // ── Image PLAT cuisiné ── chercher le plat en anglais pour Wikipedia
    if (!in_array('toxique', $badges) && in_array('comestible', $badges) && !empty($nom_sci)) {
        $titre_recette = $recette['title'] ?? '';
        // Table de traduction FR → EN pour meilleure recherche Wikipedia
        $traductions = [
            'arachide'=>'peanut','manioc'=>'cassava','moringa'=>'moringa leaves',
            'gombo'=>'okra','gombô'=>'okra','plantain'=>'plantain',
            'igname'=>'yam','maïs'=>'maize','tomate'=>'tomato',
            'aubergine'=>'eggplant','haricot'=>'bean','riz'=>'rice',
            'patate'=>'sweet potato','papaye'=>'papaya','banane'=>'banana',
            'vernonia'=>'vernonia','amarante'=>'amaranth','piment'=>'chili pepper',
            'basilic'=>'basil','gingembre'=>'ginger','curcuma'=>'turmeric',
        ];
        if (!empty($titre_recette)) {
            $terme = strtolower($titre_recette);
            foreach ($traductions as $fr => $en) { $terme = str_replace($fr, $en, $terme); }
            // Supprimer les articles français inutiles
            $terme = preg_replace('/(maison|africain[e]?|traditionnel[le]?|sauce|de la|du|de|au|à la|le|la|les|et)/iu', ' ', $terme);
            $terme = trim(preg_replace('/\s+/', ' ', $terme));
            $img_plat = '__FOOD__' . urlencode($terme . ' food');
        } else {
            $img_plat = '__FOOD__' . urlencode(strtolower($nom_sci) . ' food dish');
        }
    }

    // ── Image REMÈDE ── chercher herbal medicine + nom plante
    if (!in_array('toxique', $badges) && in_array('medicinal', $badges) && !empty($nom_sci)) {
        // On cherche "X traditional medicine" pas juste "X" qui donne la plante botanique
        $img_remede = '__MED__' . urlencode($nom_sci . ' traditional medicine herbal');
    }


// ============================================================
// SAUVEGARDE BDD
// ============================================================
$titre_disc = '';
try {
    if ($id_disc === 0) {
        $titre = mb_substr($message ?: 'Analyse de plante', 0, 60);
        if (mb_strlen($message) > 60) $titre .= '…';
        $conn->prepare("INSERT INTO `$table_disc` (titre) VALUES (?)")->execute([$titre]);
        $id_disc    = (int)$conn->lastInsertId();
        $titre_disc = $titre;
    } else {
        $r = $conn->prepare("SELECT titre FROM `$table_disc` WHERE id=?");
        $r->execute([$id_disc]);
        $titre_disc = $r->fetchColumn() ?: 'Discussion';
    }

    $msg_user_bdd = $mode_vision ? '[IMAGE:'.$image_sauvegardee.'] '.($message ?: '') : $message;
    $conn->prepare("INSERT INTO `$table_msg` (discussion_id,role,contenu) VALUES (?,'user',?)")
         ->execute([$id_disc, $msg_user_bdd]);

    $meta = base64_encode(json_encode([
        'img_plante' => $img_plante,
        'img_plat'   => $img_plat,
        'img_remede' => $img_remede,
        'nom_sci'    => $nom_sci,
        'nom_plat'   => $recette['title'] ?? '',
    ]));
    $conn->prepare("INSERT INTO `$table_msg` (discussion_id,role,contenu) VALUES (?,'ai',?)")
         ->execute([$id_disc, $html.'<!--IMGMETA:'.$meta.':IMGMETA-->']);

    // ── Enregistrement stats Power BI (seulement si plante identifiée) ──
    if (!empty($nom_sci) && !$est_question_generale) {

        // Extraire infos supplémentaires depuis la réponse IA
        $est_malade_val   = ($maladie !== null) ? 1 : 0;
        $nom_maladie_val  = $maladie['name'] ?? '';
        $est_invasive_val = (str_contains(strtolower($html), 'invasive') || str_contains(strtolower($html), 'allélopathique')) ? 1 : 0;
        $niveau_toxi_val  = 'aucun';
        if (in_array('toxique', $badges)) {
            if (str_contains(strtolower($html), 'élevé') || str_contains(strtolower($html), 'grave')) $niveau_toxi_val = 'eleve';
            elseif (str_contains(strtolower($html), 'moyen') || str_contains(strtolower($html), 'modéré')) $niveau_toxi_val = 'moyen';
            else $niveau_toxi_val = 'faible';
        }

        // Insérer dans scans_plantes (avec toutes les colonnes Power BI)
        try {
            $conn->prepare("INSERT INTO `scans_plantes`
                (user_id, nom_scientifique, nom_commun, famille, score_confiance,
                 type_action, badges, image_path,
                 est_malade, nom_maladie, est_invasive, niveau_toxicite,
                 pays, region)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $id_user,
                    $nom_sci,
                    implode(', ', array_slice($plantnet_result['nom_commun'] ?? [], 0, 3)),
                    $plantnet_result['famille'] ?? '',
                    $plantnet_result['score']   ?? 0,
                    $mode_vision ? 'upload' : 'texte',
                    implode(',', $badges),
                    $image_sauvegardee ?? '',
                    $est_malade_val,
                    $nom_maladie_val,
                    $est_invasive_val,
                    $niveau_toxi_val,
                    'Côte d\'Ivoire',
                    '',
                ]);
            $scan_id = (int)$conn->lastInsertId();
        } catch(Exception $e) {
            $scan_id = 0;
        }

        // Si maladie détectée → insérer dans maladies_detectees
        if ($maladie && $scan_id > 0) {
            try {
                // Déterminer type de maladie
                $type_mal = 'autre';
                $desc_mal = strtolower($maladie['name'] ?? '');
                if (str_contains($desc_mal, 'fongique') || str_contains($desc_mal, 'champignon') || str_contains($desc_mal, 'mildiou') || str_contains($desc_mal, 'rouille')) $type_mal = 'fongique';
                elseif (str_contains($desc_mal, 'bactéri') || str_contains($desc_mal, 'bacterial')) $type_mal = 'bacterienne';
                elseif (str_contains($desc_mal, 'viral') || str_contains($desc_mal, 'virus') || str_contains($desc_mal, 'mosaïque')) $type_mal = 'virale';
                elseif (str_contains($desc_mal, 'parasite') || str_contains($desc_mal, 'insecte') || str_contains($desc_mal, 'ravageur')) $type_mal = 'parasitaire';

                $traitement_nat = implode(' | ', array_filter($maladie['treatment'] ?? [], fn($t) => str_contains(strtolower($t), 'naturel')));
                $traitement_chi = implode(' | ', array_filter($maladie['treatment'] ?? [], fn($t) => str_contains(strtolower($t), 'chimique') || str_contains(strtolower($t), 'fongicide')));

                $conn->prepare("INSERT INTO `maladies_detectees`
                    (scan_id, user_id, plante_hote, nom_maladie, type_maladie,
                     traitement_naturel, traitement_chimique)
                    VALUES (?,?,?,?,?,?,?)")
                    ->execute([$scan_id, $id_user, $nom_sci, $maladie['name'], $type_mal, $traitement_nat, $traitement_chi]);
            } catch(Exception $e) {}
        }

        // Vérifier et générer des alertes automatiques
        try {
            // Alerte maladie si même maladie détectée 5+ fois en 7 jours
            if ($maladie) {
                $r_alerte = $conn->prepare("SELECT COUNT(*) FROM `maladies_detectees`
                    WHERE nom_maladie = ? AND cree_le >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                $r_alerte->execute([$maladie['name']]);
                $nb_cas = (int)$r_alerte->fetchColumn();

                if ($nb_cas >= 5) {
                    // Vérifier qu'une alerte similaire n'existe pas déjà aujourd'hui
                    $r_exist = $conn->prepare("SELECT COUNT(*) FROM `alertes_systeme`
                        WHERE entite = ? AND type_alerte = 'maladie_hausse'
                        AND cree_le >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $r_exist->execute([$maladie['name']]);
                    if ((int)$r_exist->fetchColumn() === 0) {
                        $conn->prepare("INSERT INTO `alertes_systeme`
                            (type_alerte, titre, description, entite, nb_cas)
                            VALUES ('maladie_hausse', ?, ?, ?, ?)")
                            ->execute([
                                "⚠️ Maladie en hausse : {$maladie['name']}",
                                "La maladie \"{$maladie['name']}\" a été détectée {$nb_cas} fois en 7 jours sur {$nom_sci}.",
                                $maladie['name'],
                                $nb_cas,
                            ]);
                    }
                }
            }

            // Alerte plante invasive
            if ($est_invasive_val) {
                $r_inv = $conn->prepare("SELECT COUNT(*) FROM `scans_plantes`
                    WHERE nom_scientifique = ? AND est_invasive = 1
                    AND cree_le >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $r_inv->execute([$nom_sci]);
                $nb_inv = (int)$r_inv->fetchColumn();
                if ($nb_inv >= 3) {
                    $r_exist = $conn->prepare("SELECT COUNT(*) FROM `alertes_systeme`
                        WHERE entite = ? AND type_alerte = 'plante_invasive'
                        AND cree_le >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                    $r_exist->execute([$nom_sci]);
                    if ((int)$r_exist->fetchColumn() === 0) {
                        $conn->prepare("INSERT INTO `alertes_systeme`
                            (type_alerte, titre, description, entite, nb_cas)
                            VALUES ('plante_invasive', ?, ?, ?, ?)")
                            ->execute([
                                "🚨 Plante invasive : {$nom_sci}",
                                "{$nom_sci} signalée {$nb_inv} fois comme invasive ce mois-ci.",
                                $nom_sci,
                                $nb_inv,
                            ]);
                    }
                }
            }
        } catch(Exception $e) {}

        // Mettre à jour utilisateurs_stats
        try {
            $conn->prepare("INSERT INTO `utilisateurs_stats`
                (user_id, nb_scans_total, nb_plantes_uniques, derniere_activite)
                VALUES (?, 1, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    nb_scans_total    = nb_scans_total + 1,
                    derniere_activite = NOW()")
                ->execute([$id_user]);

            // Recalculer plantes uniques
            $r_uniq = $conn->prepare("SELECT COUNT(DISTINCT nom_scientifique) FROM `scans_plantes` WHERE user_id=?");
            $r_uniq->execute([$id_user]);
            $nb_uniq = (int)$r_uniq->fetchColumn();
            $conn->prepare("UPDATE `utilisateurs_stats` SET nb_plantes_uniques=? WHERE user_id=?")
                ->execute([$nb_uniq, $id_user]);
        } catch(Exception $e) {}
    }
} catch(Exception $e) {
    if (empty($titre_disc)) $titre_disc = 'Discussion';
}

// ============================================================
// RÉPONSE JSON
// ============================================================
echo json_encode([
    'disc_id'       => $id_disc,
    'disc_titre'    => $titre_disc,
    'html_response' => $html,
    'plain_text'    => $plain_text,
    'recipe'        => $recette,
    'recette'       => $recette,
    'maladie'       => $maladie,
    'recolte'       => $recolte,
    'img_plante'    => $img_plante,
    'image_plante'  => $img_plante,
    'img_dish'      => $img_plat,
    'image_plat'    => $img_plat,
    'img_med'       => $img_remede,
    'image_remede'  => $img_remede,
    'nom_sci'       => $nom_sci,
    'nom_plat'      => $recette['title'] ?? '',
    'est_generale'  => $est_question_generale,
], JSON_UNESCAPED_UNICODE);
exit;

// ============================================================
// FONCTIONS
// ============================================================
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
}