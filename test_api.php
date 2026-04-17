<?php
/**
 * test_api.php — Diagnostic rapide
 * Ouvrez : http://127.0.0.1/projet/test_api.php
 * SUPPRIMEZ APRÈS UTILISATION
 */

// Simuler une session connectée pour tester
session_start();
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Diagnostic WhatAPlant</title>
<style>
  body { font-family: monospace; background: #f4fbf7; padding: 20px; }
  .ok  { color: #065f46; background: #d1fae5; padding: 8px 12px; border-radius: 8px; margin: 6px 0; display:block; }
  .err { color: #991b1b; background: #fee2e2; padding: 8px 12px; border-radius: 8px; margin: 6px 0; display:block; }
  .info{ color: #1e40af; background: #dbeafe; padding: 8px 12px; border-radius: 8px; margin: 6px 0; display:block; }
  pre  { background: #1f2937; color: #a7f3d0; padding: 14px; border-radius: 8px; white-space: pre-wrap; word-break: break-all; font-size: 13px; }
  h2   { color: #1b8a5e; margin: 20px 0 8px; }
  button { background: #1b8a5e; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 15px; cursor: pointer; margin: 10px 0; }
</style>
</head>
<body>
<h1>🌿 WhatAPlant — Diagnostic</h1>

<h2>1. Fichiers présents</h2>
<?php
$fichiers = ['api_chat.php', 'connect_db.php', 'img_proxy.php', 'chat.php'];
foreach ($fichiers as $f) {
    if (file_exists(__DIR__ . '/' . $f)) {
        echo "<span class='ok'>✅ $f trouvé (".round(filesize(__DIR__.'/'.$f)/1024, 1)." Ko)</span>";
    } else {
        echo "<span class='err'>❌ $f INTROUVABLE dans ".htmlspecialchars(__DIR__)."</span>";
    }
}
?>

<h2>2. Clé Groq dans api_chat.php</h2>
<?php
if (file_exists(__DIR__ . '/api_chat.php')) {
    $src = file_get_contents(__DIR__ . '/api_chat.php');
    if (preg_match("/define\('GROQ_API_KEY',\s*'([^']+)'\)/", $src, $m)) {
        $cle = $m[1];
        if ($cle === 'VOTRE_CLE_GROQ_GSK' || strpos($cle, 'VOTRE') !== false) {
            echo "<span class='err'>❌ Clé pas encore configurée : '$cle'</span>";
        } elseif (strpos($cle, 'gsk_') === 0) {
            echo "<span class='ok'>✅ Clé Groq présente : gsk_****" . substr($cle, -4) . "</span>";
        } else {
            echo "<span class='err'>⚠️ Clé présente mais format inhabituel : " . substr($cle, 0, 10) . "...</span>";
        }
    } else {
        echo "<span class='err'>❌ define GROQ_API_KEY introuvable dans api_chat.php</span>";
    }
}
?>

<h2>3. Test appel direct api_chat.php (POST vide)</h2>
<?php
$ch = curl_init('http://127.0.0.1/' . basename(__DIR__) . '/api_chat.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => ['disc_id'=>0, 'message'=>'test'],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_COOKIE         => 'PHPSESSID=' . session_id(),
]);
$rep  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "<span class='err'>❌ Erreur cURL : $err</span>";
} else {
    echo "<span class='info'>HTTP $code — " . strlen($rep) . " octets reçus</span>";
    $json = json_decode($rep, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<span class='ok'>✅ Réponse JSON valide</span>";
        echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . "</pre>";
    } else {
        echo "<span class='err'>❌ Réponse NON-JSON — voici ce qu'on reçoit :</span>";
        echo "<pre>" . htmlspecialchars(substr($rep, 0, 1000)) . "</pre>";
    }
}
?>

<h2>4. Test fetch JavaScript depuis le navigateur</h2>
<button onclick="testerFetch()">▶ Tester le fetch vers api_chat.php</button>
<div id="resultat-fetch"></div>

<script>
async function testerFetch() {
    const div = document.getElementById('resultat-fetch');
    div.innerHTML = '<span class="info">⏳ Test en cours...</span>';
    try {
        const fd = new FormData();
        fd.append('disc_id', '0');
        fd.append('message', 'test diagnostic');

        const rep = await fetch('api_chat.php', { method: 'POST', body: fd });
        const type = rep.headers.get('content-type') || '';
        div.innerHTML = `<span class="info">HTTP ${rep.status} — Content-Type: ${type}</span>`;

        if (type.includes('application/json')) {
            const data = await rep.json();
            div.innerHTML += `<span class="ok">✅ JSON valide reçu</span>`;
            div.innerHTML += `<pre>${JSON.stringify(data, null, 2)}</pre>`;
        } else {
            const texte = await rep.text();
            div.innerHTML += `<span class="err">❌ Réponse non-JSON :</span>`;
            div.innerHTML += `<pre>${texte.slice(0, 800)}</pre>`;
        }
    } catch(e) {
        div.innerHTML = `<span class="err">❌ Erreur fetch : ${e.message}</span>
            <br><span class="info">→ Cela signifie que api_chat.php génère une erreur fatale PHP ou n'existe pas</span>`;
    }
}
</script>

<hr style="margin:30px 0">
<p style="color:#4a6b5a;font-size:13px">⚠️ Supprimez ce fichier après utilisation.</p>
</body>
</html>