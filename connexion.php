<?php 
require_once 'connect_db.php';

$erreurs = [];

if (isset($_POST['connecter'])) {
    $email        = clean($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];

    if (empty($email) || empty($mot_de_passe)) {
        $erreurs[] = "Veuillez remplir tous les champs";
    } else {
        $stmt = $conn->prepare("SELECT id, nom, mot_de_passe FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $utilisateur = $stmt->fetch();

        if ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
            $_SESSION['user_id'] = $utilisateur['id'];
            $_SESSION['nom']     = $utilisateur['nom'];
            $_SESSION['email']   = $email;

            $update = $conn->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?");
            $update->execute([$utilisateur['id']]);

            header("Location: accueil.php");
            exit();
        } else {
            $erreurs[] = "Email ou mot de passe incorrect";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d5c3a">
    <title>Connexion | WhatAPlant AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #065f46;
            --bg-light: #f0fdf4;
            --text-main: #064e3b;
            --glass: rgba(255, 255, 255, 0.9);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at top left, #dcfce7 0%, #f0fdf4 50%, #ecfdf5 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow-x: hidden;
        }

        /* Décoration d'arrière-plan */
        .blob {
            position: absolute;
            width: 400px;
            height: 400px;
            background: var(--primary);
            filter: blur(80px);
            opacity: 0.1;
            z-index: -1;
            border-radius: 50%;
        }

        .conteneur {
            background: var(--glass);
            backdrop-filter: blur(10px);
            padding: 50px 40px;
            border-radius: 30px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 40px rgba(6, 78, 59, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.6);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .en-tete {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-box {
            background: linear-gradient(135deg, var(--primary), #34d399);
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .titre {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary-dark);
            letter-spacing: -0.5px;
            margin: 0;
        }

        .sous-titre {
            color: #6b7280;
            font-size: 15px;
            margin-top: 5px;
        }

        .group-champ {
            margin-bottom: 20px;
            position: relative;
        }

        .group-champ i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            width: 20px;
        }

        .champ {
            width: 100%;
            padding: 16px 16px 16px 50px;
            box-sizing: border-box;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            font-size: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .champ:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            transform: scale(1.01);
        }

        .bouton {
            width: 100%;
            padding: 16px;
            background: var(--primary-dark);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .bouton:hover {
            background: #044e3a;
            box-shadow: 0 10px 20px rgba(4, 78, 58, 0.2);
            transform: translateY(-2px);
        }

        .erreur {
            background: #fef2f2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #fee2e2;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pied {
            margin-top: 30px;
            text-align: center;
            font-size: 14px;
        }

        .lien {
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .lien:hover {
            color: var(--primary);
        }

        .separateur {
            margin: 0 10px;
            color: #d1d5db;
        }
    </style>
</head>
<body>
    <div class="blob" style="top: 10%; left: 10%;"></div>
    <div class="blob" style="bottom: 10%; right: 10%; background: #34d399;"></div>

    <div class="conteneur">
        <div class="en-tete">
            <div class="logo-box">
                <i data-lucide="leaf" size="36"></i>
            </div>
            <h1 class="titre">WhatAPlant <span style="color:var(--primary)">AI</span></h1>
            <p class="sous-titre">Identifiez la nature en un clin d'œil</p>
        </div>

        <?php if (!empty($erreurs)): ?>
            <?php foreach($erreurs as $erreur): ?>
                <div class="erreur">
                    <i data-lucide="alert-circle" size="18"></i>
                    <?= $erreur ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST">
            <div class="group-champ">
                <i data-lucide="mail"></i>
                <input type="email" name="email" class="champ" placeholder="Votre email" required>
            </div>
            
            <div class="group-champ">
                <i data-lucide="lock"></i>
                <input type="password" name="mot_de_passe" class="champ" placeholder="Mot de passe" required>
            </div>
            
            <button type="submit" name="connecter" class="bouton">
                Se connecter
                <i data-lucide="arrow-right" size="18"></i>
            </button>
        </form>

        <div class="pied">
            <a href="recuperation.php" class="lien">Mot de passe oublié ?</a>
            <div style="margin-top: 15px;">
                <span style="color: #6b7280;">Nouveau ici ?</span> 
                <a href="inscription.php" class="lien">Créer un compte</a>
            </div>
        </div>
    </div>

    <script>
        // Initialisation des icônes Lucide
        lucide.createIcons();
    </script>
</body>
</html>
