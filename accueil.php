<?php 
require_once 'connect_db.php';
requireLogin();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Accueil - WhatAPlant</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap');
    
    :root {
        --primary: #10b981;
        --primary-dark: #065f46;
        --glass: rgba(255, 255, 255, 0.1);
        --glass-border: rgba(255, 255, 255, 0.2);
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        margin: 0;
        min-height: 100vh;
        background: linear-gradient(135deg, rgba(0,0,0,0.6), rgba(0,0,0,0.3)), 
                    url('https://images.unsplash.com/photo-1466692476868-aef1dfb1e735?auto=format&fit=crop&q=80') center/cover no-repeat;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow-x: hidden;
    }

    /* Animation d'entrée */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .container {
        max-width: 800px;
        width: 90%;
        text-align: center;
        z-index: 10;
        animation: fadeInUp 0.8s ease-out;
    }

    .logo-container {
        font-size: 70px;
        margin-bottom: 1rem;
        display: inline-block;
        animation: bounce 3s infinite ease-in-out;
    }

    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }

    h1 {
        font-size: clamp(2.5rem, 5vw, 4rem);
        font-weight: 800;
        margin-bottom: 1rem;
        letter-spacing: -1px;
        background: linear-gradient(to bottom, #ffffff, #a7f3d0);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .tagline {
        font-size: 1.2rem;
        opacity: 0.9;
        margin-bottom: 3rem;
        line-height: 1.6;
    }

    /* Effet Glassmorphism */
    .card {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        padding: 3rem;
        border-radius: 40px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        margin-bottom: 2rem;
    }

    .card h2 {
        margin-top: 0;
        font-size: 1.8rem;
        color: #6ee7b7;
    }

    .btn-main {
        background: var(--primary);
        color: white;
        text-decoration: none;
        padding: 1.2rem 2.5rem;
        font-size: 1.1rem;
        font-weight: 700;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: none;
        cursor: pointer;
        box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
    }

    .btn-main:hover {
        transform: scale(1.05);
        background: #34d399;
        box-shadow: 0 15px 30px rgba(16, 185, 129, 0.5);
    }

    .secondary-nav {
        margin-top: 2rem;
        font-size: 0.95rem;
    }

    .secondary-nav a {
        color: #6ee7b7;
        text-decoration: none;
        font-weight: 600;
        border-bottom: 1px solid transparent;
        transition: 0.3s;
    }

    .secondary-nav a:hover {
        border-bottom: 1px solid #6ee7b7;
    }
</style>

<body>
    <div class="container">
        <div class="logo-container">🌿</div>
        
        <h1>WhatAPlant</h1>
        <p class="tagline">L'intelligence artificielle au service de votre jardin et de votre santé.</p>

        <div class="card">
            <h2>Ravi de vous revoir, <?= htmlspecialchars($_SESSION['nom']) ?> !</h2>
            <p>Identifiez une plante en un instant ou posez vos questions à notre expert botanique.</p>
            
            <a href="chat.php" class="btn-main">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"/></svg>
                Démarrer une discussion
            </a>
        </div>

        <div class="secondary-nav">
            Besoin d'une analyse rapide ? 
            <a href="scan.php">Scanner une photo →</a>
        </div>
    </div>
</body>
</html>