<?php
/**
 * router.php — Routeur Railway pour WhatAPlant
 * Sert les fichiers statiques avec les bons Content-Type
 * Requis par Railway avec le serveur PHP intégré
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// ── Types MIME pour les fichiers statiques PWA ──
$types_mime = [
    '.json'    => 'application/manifest+json',
    '.js'      => 'application/javascript',
    '.css'     => 'text/css',
    '.png'     => 'image/png',
    '.jpg'     => 'image/jpeg',
    '.jpeg'    => 'image/jpeg',
    '.svg'     => 'image/svg+xml',
    '.ico'     => 'image/x-icon',
    '.webp'    => 'image/webp',
    '.woff2'   => 'font/woff2',
    '.woff'    => 'font/woff',
    '.ttf'     => 'font/ttf',
];

// Chemin du fichier physique
$fichier = __DIR__ . $uri;

// Si le fichier existe et n'est pas PHP → le servir directement
if ($uri !== '/' && file_exists($fichier) && !is_dir($fichier)) {
    $ext = strtolower(substr($uri, strrpos($uri, '.')));

    if (isset($types_mime[$ext])) {
        // Headers spéciaux pour Service Worker
        if ($uri === '/sw.js') {
            header('Content-Type: application/javascript');
            header('Service-Worker-Allowed: /');
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }
        // Headers pour manifest.json
        elseif ($uri === '/manifest.json') {
            header('Content-Type: application/manifest+json');
            header('Cache-Control: public, max-age=86400');
        }
        // Icônes et images
        elseif (in_array($ext, ['.png','.jpg','.jpeg','.svg','.ico','.webp'])) {
            header('Content-Type: ' . $types_mime[$ext]);
            header('Cache-Control: public, max-age=604800');
            header('Access-Control-Allow-Origin: *');
        }
        else {
            header('Content-Type: ' . $types_mime[$ext]);
        }

        readfile($fichier);
        return true;
    }

    // Fichier PHP → laisser le serveur l'exécuter
    if ($ext === '.php') {
        return false;
    }
}

// Fichier non trouvé avec extension → 404
if ($uri !== '/' && strpos($uri, '.') !== false && !file_exists($fichier)) {
    http_response_code(404);
    echo '404 Not Found';
    return true;
}

// Tout le reste → laisser PHP gérer (pages .php, routing)
return false;
