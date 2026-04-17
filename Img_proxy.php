<?php
/**
 * img_proxy.php — WhatAPlant FINAL
 * Source 1 : Wikipedia (image réelle de la plante par nom scientifique)
 * Source 2 : Unsplash avec mots-clés précis
 * Source 3 : SVG placeholder informatif
 */
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }

$plant  = strip_tags(trim(substr($_GET['plant']  ?? '', 0, 100)));
$prompt = strip_tags(trim(substr($_GET['prompt']  ?? '', 0, 200)));
$type   = $_GET['type'] ?? 'plant'; // plant, dish, medicine
$seed   = abs((int)($_GET['seed'] ?? rand(1,99999)));

// ── Cache 24h ──
$cache_dir  = sys_get_temp_dir() . '/whataplan_v4/';
$cache_key  = md5($plant.$prompt.$type.$seed);
$cache_file = $cache_dir . $cache_key . '.jpg';
if (!is_dir($cache_dir)) @mkdir($cache_dir, 0755, true);

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 86400) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    readfile($cache_file); exit;
}

// ── Fonction cURL générique ──
function telecharger(string $url, int $timeout = 20): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'WhatAPlant/1.0 (contact@whataplan.ci)',
        CURLOPT_HTTPHEADER     => ['Accept: */*'],
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err || $code !== 200 || !$data) return null;
    return ['data'=>$data, 'type'=>$type, 'size'=>strlen($data)];
}

$img_data = null;
$img_type = 'image/jpeg';

// ════════════════════════════════════════════════════════
// TYPE : PLANTE → Wikipedia API (image officielle réelle)
// ════════════════════════════════════════════════════════
if ($type === 'plant' && !empty($plant)) {

    // Étape 1 : Wikipedia API français puis anglais — chercher par nom scientifique
    $search_url = 'https://fr.wikipedia.org/w/api.php?action=query&titles='
                . urlencode($plant)
                . '&prop=pageimages&format=json&pithumbsize=480&pilicense=any';

    $wiki = telecharger($search_url, 12);
    if ($wiki) {
        $wdata = json_decode($wiki['data'], true);
        $pages = $wdata['query']['pages'] ?? [];
        $page  = reset($pages);
        $thumb = $page['thumbnail']['source'] ?? null;

        if ($thumb) {
            $img = telecharger($thumb, 15);
            if ($img && $img['size'] > 3000 && str_contains($img['type'], 'image')) {
                $img_data = $img['data'];
                $img_type = $img['type'];
            }
        }
    }

    // Étape 2 : Essayer Wikipedia anglais si français échoue
    if (!$img_data) {
        $search_url = 'https://en.wikipedia.org/w/api.php?action=query&titles='
                    . urlencode($plant)
                    . '&prop=pageimages&format=json&pithumbsize=480&pilicense=any';
        $wiki = telecharger($search_url, 12);
        if ($wiki) {
            $wdata = json_decode($wiki['data'], true);
            $pages = $wdata['query']['pages'] ?? [];
            $page  = reset($pages);
            $thumb = $page['thumbnail']['source'] ?? null;
            if ($thumb) {
                $img = telecharger($thumb, 15);
                if ($img && $img['size'] > 3000 && str_contains($img['type'], 'image')) {
                    $img_data = $img['data'];
                    $img_type = $img['type'];
                }
            }
        }
    }

    // Étape 2 : Essayer Wikimedia Commons si Wikipedia échoue
    if (!$img_data) {
        $commons_url = 'https://commons.wikimedia.org/w/api.php?action=query&titles='
                     . urlencode($plant)
                     . '&prop=pageimages&format=json&pithumbsize=480';
        $commons = telecharger($commons_url, 12);
        if ($commons) {
            $cdata = json_decode($commons['data'], true);
            $cpages = $cdata['query']['pages'] ?? [];
            $cpage  = reset($cpages);
            $cthumb = $cpage['thumbnail']['source'] ?? null;
            if ($cthumb) {
                $img = telecharger($cthumb, 15);
                if ($img && $img['size'] > 3000 && str_contains($img['type'], 'image')) {
                    $img_data = $img['data'];
                    $img_type = $img['type'];
                }
            }
        }
    }
}

// ════════════════════════════════════════════════════════
// TYPE : PLAT → Unsplash avec nom du plat + cuisine africaine
// ════════════════════════════════════════════════════════
if ($type === 'dish') {
    // Extraire les mots-clés du plat
    $mots = array_filter(explode(' ', strtolower($prompt)), fn($m) => strlen($m) > 2);
    $mots = array_slice($mots, 0, 4);
    $q1   = urlencode(implode(',', $mots) . ',african,food,traditional,dish,meal');
    $q2   = urlencode('african,traditional,food,cooking,meal,plate');

    foreach (["https://source.unsplash.com/480x340/?{$q1}", "https://source.unsplash.com/480x340/?{$q2}"] as $url) {
        $img = telecharger($url, 20);
        if ($img && $img['size'] > 5000 && str_contains($img['type'], 'image')) {
            $img_data = $img['data']; $img_type = $img['type']; break;
        }
    }
}

// ════════════════════════════════════════════════════════
// TYPE : REMÈDE → Unsplash herbes médicinales
// ════════════════════════════════════════════════════════
if ($type === 'medicine') {
    $mots = array_slice(array_filter(explode(' ', strtolower($plant ?: $prompt)),
                        fn($m) => strlen($m) > 2), 0, 3);
    $q1   = urlencode(implode(',', $mots) . ',herbal,plant,medicine,leaves,natural');
    $q2   = urlencode('african,herbs,natural,remedy,traditional,medicine,mortar');

    foreach (["https://source.unsplash.com/480x340/?{$q1}", "https://source.unsplash.com/480x340/?{$q2}"] as $url) {
        $img = telecharger($url, 20);
        if ($img && $img['size'] > 5000 && str_contains($img['type'], 'image')) {
            $img_data = $img['data']; $img_type = $img['type']; break;
        }
    }
}

// ════════════════════════════════════════════════════════
// Fallback Unsplash générique si rien n'a fonctionné
// ════════════════════════════════════════════════════════
if (!$img_data) {
    $q = match($type) {
        'dish'     => 'african,food,traditional,cooking',
        'medicine' => 'herbs,natural,medicine,plants',
        default    => 'tropical,plant,green,leaf,nature',
    };
    $img = telecharger("https://source.unsplash.com/480x340/?{$q}&sig={$seed}", 20);
    if ($img && $img['size'] > 5000 && str_contains($img['type'], 'image')) {
        $img_data = $img['data']; $img_type = $img['type'];
    }
}

// ════════════════════════════════════════════════════════
// Retourner l'image trouvée
// ════════════════════════════════════════════════════════
if ($img_data) {
    @file_put_contents($cache_file, $img_data);
    header('Content-Type: ' . $img_type);
    header('Cache-Control: public, max-age=86400');
    echo $img_data; exit;
}

// ════════════════════════════════════════════════════════
// SVG Placeholder final (si tout échoue)
// ════════════════════════════════════════════════════════
header('Content-Type: image/svg+xml');
header('Cache-Control: no-store');

$emoji = match($type) { 'dish'=>'🍲', 'medicine'=>'🌿', default=>'🌱' };
$fond  = match($type) { 'dish'=>'#fff8f0', 'medicine'=>'#f0fff4', default=>'#f0fdf4' };
$col   = match($type) { 'dish'=>'#c2410c', 'medicine'=>'#065f46', default=>'#166534' };
$label = htmlspecialchars(mb_substr($plant ?: $prompt, 0, 35));

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="480" height="340" viewBox="0 0 480 340">
  <rect width="480" height="340" fill="{$fond}" rx="12"/>
  <rect x="2" y="2" width="476" height="336" fill="none" stroke="#bbf7d0" stroke-width="2" rx="10"/>
  <text x="240" y="148" text-anchor="middle" font-size="58">{$emoji}</text>
  <text x="240" y="196" text-anchor="middle" font-family="Georgia,serif" font-size="15" font-weight="bold" fill="{$col}">{$label}</text>
  <text x="240" y="220" text-anchor="middle" font-family="Arial,sans-serif" font-size="12" fill="#6b7280">Image non disponible hors ligne</text>
</svg>
SVG;