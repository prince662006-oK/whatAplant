<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo json_encode([
    "name" => "WhatAPlant — IA Botanique Afrique",
    "short_name" => "WhatAPlant",
    "description" => "Identifiez les plantes, diagnostiquez vos cultures et trouvez des remèdes naturels africains",
    "start_url" => "/",
    "display" => "standalone",
    "background_color" => "#0d5c3a",
    "theme_color" => "#0d5c3a",
    "orientation" => "portrait-primary",
    "lang" => "fr",
    "scope" => "/",
    "id" => "/",
    "icons" => [
        ["src" => "/icons/icon-72.png",  "sizes" => "72x72",  "type" => "image/png", "purpose" => "any"],
        ["src" => "/icons/icon-96.png",  "sizes" => "96x96",  "type" => "image/png", "purpose" => "any"],
        ["src" => "/icons/icon-128.png", "sizes" => "128x128", "type" => "image/png", "purpose" => "any"],
        ["src" => "/icons/icon-192.png", "sizes" => "192x192", "type" => "image/png", "purpose" => "any"],
        ["src" => "/icons/icon-512.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "any"],
        ["src" => "/icons/icon-512.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "maskable"]
    ],
    "shortcuts" => [
        [
            "name" => "Scanner une plante",
            "short_name" => "Scanner",
            "description" => "Ouvrir le scanner directement",
            "url" => "/scan.php",
            "icons" => [["src" => "/icons/icon-96.png", "sizes" => "96x96"]]
        ],
        [
            "name" => "Nouvelle analyse",
            "short_name" => "Analyser",
            "description" => "Démarrer une nouvelle analyse",
            "url" => "/chat.php",
            "icons" => [["src" => "/icons/icon-96.png", "sizes" => "96x96"]]
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
