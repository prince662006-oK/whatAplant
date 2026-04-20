cat > /home/claude/api_chat_corrige.php << 'PHPEOF'
<?php
/**
 * api_chat.php — WhatAPlant FINAL CORRIGÉ
 * BDD : scans_plantes + maladies_detectees + alertes_systeme
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

$id_user    = (int)$_SESSION['user_id'];
$id_disc    = (int)($_POST['disc_id'] ?? 0);
$message    = trim($_POST['message'] ?? '');
$table_disc = "discussions_utilisateur_" . $id_user;
$table_msg  = "messages_utilisateur_"    . $id_user;

// ── DÉTECTION TYPE ──
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
    foreach (['bonjour','bonsoir','salut','comment tu','tu vas','ça va','merci','aide-moi','hello','qui es-tu','présente-toi','que peux-tu'] as $mot) {
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
        echo json_encode(['error' => 'Format non supporté.']); exit;
    }
    $binaire = file_get_contents($chemin_tmp);
    if (function_exists('imagecreatefromstring') && !empty($binaire)) {
        $ressource = @imagecreatefromstring($binaire);
        if ($ressource) {
            $w = imagesx($ressource); $h = imagesy($ressource);
            if ($w > 1024) {
                $nh = (int)($h * 1024 / $w);
                $red = imagecreatetruecolor(1024, $nh);
                imagecopyresampled($red, $ressource, 0,0,0,0, 1024,$nh,$w,$h);
                imagedestroy($ressource); $ressource = $red;
            }
            ob_start(); imagejpeg($ressource, null, 82); $compresse = ob_get_clean();
            $image_base64 = base64_encode($compresse);
            $image_mime = 'image/jpeg';
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
        $contexte_plantnet  = "=== IDENTIFICATION PLANTNET ===\n";
        $contexte_plantnet .= "Espèce : {$plantnet_result['nom_scientifique']}\n";
        $contexte_plantnet .= "Confiance : {$plantnet_result['score']}%\n";
        if (!empty($plantnet_result['nom_commun']))
            $contexte_plantnet .= "Noms communs : ".implode(', ', $plantnet_result['nom_commun'])."\n";
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
            $historique[] = ['role' => $l['role']==='ai' ? 'assistant' : 'user', 'content' => mb_substr(strip_tags($propre), 0, 500)];
        }
    } catch(Exception $e) {}
}

// ── SYSTEM PROMPT ──
$system_prompt = <<<'PROMPT'
Tu es WhatAPlant, l'application IA botanique et agricole de référence pour l'Afrique.
Tes utilisateurs : agriculteurs, herboristes, familles, étudiants en Côte d'Ivoire.

RÈGLES ABSOLUES :
R1 — TOUJOURS commencer par [BADGES:...]. Si question générale → [BADGES:aucun]
R2 — Nom scientifique en <em>Genre espèce</em>. Si PlantNet identifié → l'utiliser OBLIGATOIREMENT.
R3 — HTML uniquement. JAMAIS d'astérisques ni de #.
R4 — Si comestible → terminer par [RECIPE:{...}] avec recette de LA plante analysée.
R5 — Ne JAMAIS mélanger les plantes.
R6 — Question générale → [BADGES:aucun], réponse courte et chaleureuse.
R7 — Si plante malade, OBLIGATOIREMENT inclure [DISEASE:{...}] avec traitement naturel ET chimique.

CAS A — IMAGE PLANTE :
<strong>🔬 Identification</strong><br>
Nom commun : [français + nom local ivoirien si connu]<br>
Nom scientifique : <em>[Genre espèce]</em><br>
Famille : [famille botanique]<br>
Confiance : [Élevée/Moyenne/Faible]<br><br>
<strong>🌿 État de santé</strong><br>
[Saine OU Malade — décrire visuellement ce qui est observé]<br>
[Si malade : nom exact de la maladie, symptômes visibles, gravité]<br><br>
<strong>🍽️ Comestibilité</strong><br>[Oui/Non/Partiellement + parties comestibles + nutrition]<br><br>
<strong>💊 Propriétés médicinales</strong><br>[usages + préparation + posologie]<br>
⚕️ Consultez un médecin si symptômes persistent plus de 48h<br><br>
<strong>☠️ Toxicité</strong><br>Niveau : [Aucun/Faible/Moyen/Élevé]<br><br>
<strong>🌾 Impact agricole</strong><br>[Invasive ? Compatible avec quelles cultures ?]<br><br>
<strong>💡 Le saviez-vous ?</strong><br>[Fait surprenant en Afrique]

CAS B — PLANTATION/CULTURE : Diagnostic santé + maturité + conseils agronomiques

CAS C — SYMPTÔME HUMAIN : 2-3 plantes africaines + préparation + posologie

BADGES disponibles :
[BADGES:comestible,medicinal] [BADGES:comestible] [BADGES:medicinal]
[BADGES:toxique] [BADGES:agricole] [BADGES:agricole,malade] [BADGES:aucun]

FORMAT OBLIGATOIRE EN FIN DE RÉPONSE :

SI COMESTIBLE → exactement sur une ligne :
[RECIPE:{"title":"Nom recette","items":["Ingrédient 1","Ingrédient 2","Étape 1 : ...","Étape 2 : ..."]}]

SI MALADIE DÉTECTÉE → exactement sur une ligne :
[DISEASE:{"name":"Nom exact maladie","type":"fongique|bacterienne|virale|parasitaire|autre","severity":"faible|moyenne|elevee|critique","treatment":["Naturel : neem + eau + savon, vaporiser 3x/semaine","Chimique : Mancozèbe 2g/L, traitement hebdomadaire"],"prevention":["Rotation des cultures","Éviter l'humidité excessive"]}]

SI STADE MATURITÉ → exactement sur une ligne :
[HARVEST:{"stage":"stade","days":"X-Y jours","advice":"conseil précis"}]

Réponds TOUJOURS en français. Sois précis, scientifique et utile.
PROMPT;

// ── APPEL GROQ ──
$modele   = $mode_vision ? GROQ_MODELE_VISION : GROQ_MODELE_TEXTE;
$texte_u  = $contexte_plantnet . ($message ?: "Analyse complètement cette image selon le format demandé.");
$contenu_user = [];
if ($mode_vision)
    $contenu_user[] = ['type'=>'image_url','image_url'=>['url'=>"data:{$image_mime};base64,{$image_base64}"]];
$contenu_user[] = ['type'=>'text','text'=>$texte_u];
$msg_user = $mode_vision ? $contenu_user : $texte_u;
$messages = array_merge([['role'=>'system','content'=>$system_prompt]], $historique, [['role'=>'user','content'=>$msg_user]]);
$payload  = ['model'=>$modele,'messages'=>$messages,'max_tokens'=>2000,'temperature'=>0.2];

$ch = curl_init(GROQ_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.GROQ_API_KEY],
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_TIMEOUT        => 90, CURLOPT_SSL_VERIFYPEER => true,
]);
$brut = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
if ($cerr) { echo json_encode(['error'=>'Erreur réseau : '.$cerr]); exit; }
if ($code !== 200) {
    $d = json_decode($brut, true); $m = $d['error']['message'] ?? substr($brut,0,200);
    echo json_encode(['error' => match(true) {
        $code===401 => '❌ Clé Groq invalide', $code===429 => '⏱️ Trop de requêtes',
        default => "Erreur Groq HTTP $code : $m"
    }]); exit;
}
$ia_brut = json_decode($brut, true)['choices'][0]['message']['content'] ?? null;
if (!$ia_brut) { echo json_encode(['error' => "L'IA n'a pas répondu."]); exit; }

// ── PARSING ──
$html = $ia_brut;
$badges = $recette = $maladie = $recolte = null;

if (preg_match('/\[BADGES:([^\]]+)\]/i', $html, $m)) {
    $badges = array_map('trim', explode(',', strtolower($m[1])));
    $html   = str_replace($m[0], '', $html);
}
$badges = $badges ?: [];
$est_question_generale = in_array('aucun', $badges) || empty($badges);

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

$html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
$html = preg_replace('/\*(.+?)\*/s',     '<em>$1</em>', $html);
$html = preg_replace('/^#{1,4}\s+(.+)$/m', '<strong>$1</strong><br>', $html);
$html = preg_replace('/\[(RECIPE|DISEASE|HARVEST|IMG_PLAT|IMG_REMEDE|BADGES)[^\]]*\]/s', '', $html);
$html = trim($html);

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
if (!$est_question_generale && ($mode_vision || $est_question_plante)) {
    if (preg_match('/<em>([A-Z][a-z]+(?: [a-z]+)+)<\/em>/u', $html, $ns)) $nom_sci = trim($ns[1]);
    if ($plantnet_result && $plantnet_result['score'] >= 15) $nom_sci = $plantnet_result['nom_scientifique'];
    if (empty($nom_sci)) $nom_sci = mb_substr($message, 0, 50);
    if ($image_sauvegardee)                                           $img_plante = $image_sauvegardee;
    elseif ($plantnet_result && !empty($plantnet_result['image_url'])) $img_plante = $plantnet_result['image_url'];
    if (!in_array('toxique',$badges) && in_array('comestible',$badges) && !empty($nom_sci)) {
        $trad = ['arachide'=>'peanut','manioc'=>'cassava','moringa'=>'moringa','gombo'=>'okra',
                 'gombô'=>'okra','igname'=>'yam','maïs'=>'maize','tomate'=>'tomato',
                 'aubergine'=>'eggplant','haricot'=>'bean','papaye'=>'papaya','banane'=>'banana'];
        $titre_rec = $recette['title'] ?? '';
        $terme = !empty($titre_rec) ? strtolower($titre_rec) : strtolower($nom_sci).' food';
        foreach ($trad as $fr=>$en) $terme = str_replace($fr, $en, $terme);
        $terme = preg_replace('/(maison|africain[e]?|traditionnel[le]?|de la|du|de|au|le|la|les|et)\b/iu', ' ', $terme);
        $img_plat = '__FOOD__'.urlencode(trim(preg_replace('/\s+/',' ',$terme)).' dish');
    }
    if (!in_array('toxique',$badges) && in_array('medicinal',$badges) && !empty($nom_sci))
        $img_remede = '__MED__'.urlencode($nom_sci);
}

// ── SAUVEGARDE BDD ──
$titre_disc = ''; $scan_id = 0;
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Discussion
    if ($id_disc === 0) {
        $titre = mb_substr($message ?: 'Analyse de plante', 0, 60);
        if (mb_strlen($message) > 60) $titre .= '…';
        $conn->prepare("INSERT INTO `$table_disc` (titre) VALUES (?)")->execute([$titre]);
        $id_disc = (int)$conn->lastInsertId(); $titre_disc = $titre;
    } else {
        $r = $conn->prepare("SELECT titre FROM `$table_disc` WHERE id=?");
        $r->execute([$id_disc]); $titre_disc = $r->fetchColumn() ?: 'Discussion';
    }

    // 2. Message user + IA
    $msg_user_bdd = $mode_vision ? '[IMAGE:'.$image_sauvegardee.'] '.($message ?: '') : $message;
    $conn->prepare("INSERT INTO `$table_msg` (discussion_id,role,contenu) VALUES (?,'user',?)")->execute([$id_disc, $msg_user_bdd]);
    $meta = base64_encode(json_encode(['img_plante'=>$img_plante,'img_plat'=>$img_plat,'img_remede'=>$img_remede,'nom_sci'=>$nom_sci,'nom_plat'=>$recette['title']??'']));
    $conn->prepare("INSERT INTO `$table_msg` (discussion_id,role,contenu) VALUES (?,'ai',?)")->execute([$id_disc, $html.'<!--IMGMETA:'.$meta.':IMGMETA-->']);

    // 3. scans_plantes
    if (!empty($nom_sci) && !$est_question_generale) {
        $nom_commun_str = '';
        if (is_array($plantnet_result) && !empty($plantnet_result['nom_commun']))
            $nom_commun_str = mb_substr(implode(', ', array_slice($plantnet_result['nom_commun'], 0, 3)), 0, 200);
        $famille_str = is_array($plantnet_result) ? mb_substr($plantnet_result['famille'] ?? '', 0, 150) : '';
        $score_int   = is_array($plantnet_result) ? (int)($plantnet_result['score'] ?? 0) : 0;
        $badges_str  = mb_substr(implode(',', is_array($badges) ? $badges : []), 0, 100);

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
                ($maladie !== null) ? 1 : 0,
            ]);
        $scan_id = (int)$conn->lastInsertId();

        // 4. maladies_detectees (toutes les colonnes)
        if ($maladie && $scan_id > 0) {
            $nom_mal   = mb_substr($maladie['name'] ?? '', 0, 200);
            $desc_mal  = strtolower($nom_mal);
            // Déterminer le type
            $type_mal = 'autre';
            if (str_contains($desc_mal,'fongique')||str_contains($desc_mal,'mildiou')||str_contains($desc_mal,'rouille')||str_contains($desc_mal,'champignon')) $type_mal='fongique';
            elseif (str_contains($desc_mal,'bactéri')||str_contains($desc_mal,'bacterial')) $type_mal='bacterienne';
            elseif (str_contains($desc_mal,'viral')||str_contains($desc_mal,'virus')||str_contains($desc_mal,'mosaïque')) $type_mal='virale';
            elseif (str_contains($desc_mal,'parasite')||str_contains($desc_mal,'insecte')||str_contains($desc_mal,'ravageur')) $type_mal='parasitaire';
            elseif (str_contains($desc_mal,'carence')) $type_mal='carence';
            // Sévérité depuis le JSON maladie
            $severite = mb_substr($maladie['severity'] ?? 'moyenne', 0, 20);
            if (!in_array($severite, ['faible','moyenne','elevee','critique'])) $severite = 'moyenne';
            // Traitements
            $traitements   = $maladie['treatment'] ?? [];
            $trait_nat_arr = array_filter($traitements, fn($t) => str_contains(strtolower($t), 'naturel'));
            $trait_chi_arr = array_filter($traitements, fn($t) => str_contains(strtolower($t), 'chimique') || str_contains(strtolower($t), 'fongicide') || str_contains(strtolower($t), 'pesticide'));
            $trait_nat = mb_substr(implode(' | ', $trait_nat_arr) ?: implode(' | ', array_slice($traitements, 0, 1)), 0, 500);
            $trait_chi = mb_substr(implode(' | ', $trait_chi_arr) ?: implode(' | ', array_slice($traitements, 1, 1)), 0, 500);

            $conn->prepare("INSERT INTO `maladies_detectees`
                (scan_id, user_id, plante_hote, nom_maladie, type_maladie, severite,
                 traitement_naturel, traitement_chimique, region)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $scan_id,
                    $id_user,
                    mb_substr($nom_sci, 0, 200),
                    $nom_mal,
                    $type_mal,
                    $severite,
                    $trait_nat ?: null,
                    $trait_chi ?: null,
                    "Côte d'Ivoire",
                ]);
        }

        // 5. alertes_systeme — types VALIDES dans l'ENUM
        // ENUM('maladie_hausse','plante_invasive','toxique_frequente','region_risque')
        try {
            // Alerte si maladie détectée
            if ($maladie && $scan_id > 0) {
                $nom_mal_alerte = $maladie['name'] ?? '';
                // Compter les cas de cette maladie sur 7 jours
                $r_count = $conn->prepare("SELECT COUNT(*) FROM `maladies_detectees` WHERE nom_maladie=? AND cree_le >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                $r_count->execute([$nom_mal_alerte]);
                $nb_cas = (int)$r_count->fetchColumn();

                // Créer alerte dès 1 cas (pour la démo) — ou nb_cas >= 3 en prod
                $r_exist = $conn->prepare("SELECT COUNT(*) FROM `alertes_systeme` WHERE entite=? AND type_alerte='maladie_hausse' AND cree_le >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $r_exist->execute([$nom_mal_alerte]);
                if ((int)$r_exist->fetchColumn() === 0) {
                    $conn->prepare("INSERT INTO `alertes_systeme`
                        (type_alerte, titre, description, entite, region, nb_cas, seuil_alerte, est_active)
                        VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([
                            'maladie_hausse',
                            "⚠️ Maladie détectée : {$nom_mal_alerte}",
                            "La maladie \"{$nom_mal_alerte}\" a été détectée sur {$nom_sci} en Côte d'Ivoire. {$nb_cas} cas signalés en 7 jours.",
                            mb_substr($nom_mal_alerte, 0, 200),
                            "Côte d'Ivoire",
                            max(1, $nb_cas),
                            5,
                            1,
                        ]);
                }
            }

            // Alerte plante invasive
            $html_lower = strtolower($html);
            if (str_contains($html_lower, 'invasive') || str_contains($html_lower, 'allélopathique')) {
                $r_inv = $conn->prepare("SELECT COUNT(*) FROM `scans_plantes` WHERE nom_scientifique=? AND badges LIKE '%agricole%' AND cree_le >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $r_inv->execute([$nom_sci]);
                $nb_inv = (int)$r_inv->fetchColumn();
                $r_exist2 = $conn->prepare("SELECT COUNT(*) FROM `alertes_systeme` WHERE entite=? AND type_alerte='plante_invasive' AND cree_le >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                $r_exist2->execute([$nom_sci]);
                if ((int)$r_exist2->fetchColumn() === 0) {
                    $conn->prepare("INSERT INTO `alertes_systeme`
                        (type_alerte, titre, description, entite, region, nb_cas, seuil_alerte, est_active)
                        VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([
                            'plante_invasive',
                            "🚨 Plante invasive : {$nom_sci}",
                            "{$nom_sci} a été identifiée comme invasive ou allélopathique. {$nb_inv} signalement(s) ce mois.",
                            mb_substr($nom_sci, 0, 200),
                            "Côte d'Ivoire",
                            max(1, $nb_inv),
                            3,
                            1,
                        ]);
                }
            }

            // Alerte plante toxique fréquente
            if (in_array('toxique', $badges)) {
                $r_tox = $conn->prepare("SELECT COUNT(*) FROM `scans_plantes` WHERE nom_scientifique=? AND badges LIKE '%toxique%' AND cree_le >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $r_tox->execute([$nom_sci]);
                $nb_tox = (int)$r_tox->fetchColumn();
                if ($nb_tox >= 2) {
                    $r_exist3 = $conn->prepare("SELECT COUNT(*) FROM `alertes_systeme` WHERE entite=? AND type_alerte='toxique_frequente' AND cree_le >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                    $r_exist3->execute([$nom_sci]);
                    if ((int)$r_exist3->fetchColumn() === 0) {
                        $conn->prepare("INSERT INTO `alertes_systeme`
                            (type_alerte, titre, description, entite, region, nb_cas, seuil_alerte, est_active)
                            VALUES (?,?,?,?,?,?,?,?)")
                            ->execute([
                                'toxique_frequente',
                                "☠️ Plante toxique fréquente : {$nom_sci}",
                                "{$nom_sci} a été scannée {$nb_tox} fois comme toxique ce mois. Sensibilisation recommandée.",
                                mb_substr($nom_sci, 0, 200),
                                "Côte d'Ivoire",
                                $nb_tox,
                                5,
                                1,
                            ]);
                    }
                }
            }
        } catch(PDOException $e_alerte) {
            // Log l'erreur alertes sans bloquer la réponse
            error_log("Erreur alertes_systeme: ".$e_alerte->getMessage());
        }

        // 6. utilisateurs_stats
        try {
            $conn->prepare("INSERT INTO `utilisateurs_stats` (user_id,nb_scans_total,nb_plantes_uniques,derniere_activite)
                VALUES (?,1,1,NOW())
                ON DUPLICATE KEY UPDATE nb_scans_total=nb_scans_total+1, derniere_activite=NOW()")
                ->execute([$id_user]);
            $r_uniq = $conn->prepare("SELECT COUNT(DISTINCT nom_scientifique) FROM `scans_plantes` WHERE user_id=?");
            $r_uniq->execute([$id_user]);
            $conn->prepare("UPDATE `utilisateurs_stats` SET nb_plantes_uniques=? WHERE user_id=?")->execute([(int)$r_uniq->fetchColumn(), $id_user]);
        } catch(PDOException $e) { error_log("Erreur stats: ".$e->getMessage()); }
    }

} catch(PDOException $e) {
    error_log("ERREUR BDD : ".$e->getMessage()." ligne ".$e->getLine());
    // On continue quand même — la réponse IA doit s'afficher même si BDD échoue
    if (empty($titre_disc)) $titre_disc = 'Discussion';
}

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
    'scan_id'      => $scan_id,
], JSON_UNESCAPED_UNICODE);
exit;

function callPlantNet(string $chemin, string $mime): ?array {
    if (!file_exists($chemin)) return null;
    $url = PLANTNET_URL.'?api-key='.PLANTNET_API_KEY.'&lang=fr&include-related-images=true';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS     => ['organs'=>'auto','images'=>new CURLFile($chemin,$mime,'plant.jpg')],
        CURLOPT_TIMEOUT => 25, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ]);
    $res  = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$res) return null;
    $data = json_decode($res, true);
    if (empty($data['results'][0])) return null;
    $top = $data['results'][0]; $sp = $top['species'] ?? [];
    return [
        'nom_scientifique' => $sp['scientificNameWithoutAuthor'] ?? $data['bestMatch'] ?? '',
        'score'     => round(($top['score'] ?? 0) * 100),
        'nom_commun'=> $sp['commonNames'] ?? [],
        'famille'   => $sp['family']['scientificNameWithoutAuthor'] ?? '',
        'image_url' => $top['images'][0]['url']['m'] ?? null,
    ];
}
