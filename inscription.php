<?php 
require_once 'connect_db.php';

$erreurs = [];

if (isset($_POST['inscrire'])) {
    $nom          = clean($_POST['nom']);
    $email        = clean($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];
    $confirmation = $_POST['confirmation'];

    if (empty($nom) || empty($email) || empty($mot_de_passe) || empty($confirmation)) {
        $erreurs[] = "Tous les champs sont obligatoires";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse email n'est pas valide";
    }
    if ($mot_de_passe !== $confirmation) {
        $erreurs[] = "Les mots de passe ne correspondent pas";
    }
    if (strlen($mot_de_passe) < 6) {
        $erreurs[] = "Le mot de passe doit contenir au moins 6 caractères";
    }

    if (empty($erreurs)) {
        $stmt = $conn->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $erreurs[] = "Cet email est déjà utilisé";
        }
    }

    if (empty($erreurs)) {
        $mot_de_passe_hache = password_hash($mot_de_passe, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe) VALUES (?, ?, ?)");
        
        if ($stmt->execute([$nom, $email, $mot_de_passe_hache])) {
            $user_id = $conn->lastInsertId();
            $table_discussions = "discussions_utilisateur_" . $user_id;
            $table_messages    = "messages_utilisateur_" . $user_id;

            $sql_disc = "CREATE TABLE IF NOT EXISTS `$table_discussions` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titre VARCHAR(150) NOT NULL,
                cree_le DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

            $sql_msg = "CREATE TABLE IF NOT EXISTS `$table_messages` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                discussion_id INT NOT NULL,
                role ENUM('user', 'ai') NOT NULL,
                contenu TEXT NOT NULL,
                cree_le DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (discussion_id) REFERENCES `$table_discussions`(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

            $conn->exec($sql_disc);
            $conn->exec($sql_msg);

            $_SESSION['success'] = "Compte créé avec succès ! Connectez-vous.";
            header("Location: connexion.php");
            exit();
        } else {
            $erreurs[] = "Une erreur s'est produite lors de la création du compte";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription | WhatAPlant AI</title>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            background: radial-gradient(circle at bottom right, #dcfce7 0%, #f0fdf4 50%, #ecfdf5 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .blob {
            position: absolute;
            width: 300px;
            height: 300px;
            background: var(--primary);
            filter: blur(80px);
            opacity: 0.1;
            z-index: -1;
            border-radius: 50%;
        }

        .conteneur {
            background: var(--glass);
            backdrop-filter: blur(12px);
            padding: 40px;
            border-radius: 30px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(6, 78, 59, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.7);
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .en-tete {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-box {
            background: linear-gradient(135deg, var(--primary), #34d399);
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            box-shadow: 0 8px 15px rgba(16, 185, 129, 0.2);
        }

        .titre {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary-dark);
            margin: 0;
        }

        .sous-titre {
            color: #6b7280;
            font-size: 14px;
            margin-top: 5px;
        }

        .form-grid {
            display: grid;
            gap: 15px;
        }

        .group-champ {
            position: relative;
        }

        .group-champ i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            width: 18px;
        }

        .champ {
            width: 100%;
            padding: 14px 14px 14px 45px;
            box-sizing: border-box;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .champ:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            background: #fff;
        }

        .bouton {
            width: 100%;
            padding: 15px;
            background: var(--primary-dark);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: 0.3s;
        }

        .bouton:hover {
            background: #044e3a;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(4, 78, 58, 0.2);
        }

        .erreur {
            background: #fef2f2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            border: 1px solid #fee2e2;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pied {
            margin-top: 25px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }

        .lien {
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 700;
        }

        .lien:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="blob" style="top: 5%; right: 5%;"></div>
    <div class="blob" style="bottom: 5%; left: 5%; background: #34d399;"></div>

    <div class="conteneur">
        <div class="en-tete">
            <div class="logo-box">
                <i data-lucide="user-plus" size="30"></i>
            </div>
            <h1 class="titre">Rejoignez l'aventure</h1>
            <p class="sous-titre">Commencez à explorer la flore avec l'IA</p>
        </div>

        <?php if (!empty($erreurs)): ?>
            <div class="erreur-container">
                <?php foreach($erreurs as $erreur): ?>
                    <div class="erreur">
                        <i data-lucide="shield-alert" size="16"></i>
                        <?= $erreur ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="form-grid">
            <div class="group-champ">
                <i data-lucide="user"></i>
                <input type="text" name="nom" class="champ" placeholder="Nom complet" required>
            </div>

            <div class="group-champ">
                <i data-lucide="mail"></i>
                <input type="email" name="email" class="champ" placeholder="Adresse email" required>
            </div>

            <div class="group-champ">
                <i data-lucide="lock"></i>
                <input type="password" name="mot_de_passe" class="champ" placeholder="Mot de passe (6+ car.)" required>
            </div>

            <div class="group-champ">
                <i data-lucide="check-circle"></i>
                <input type="password" name="confirmation" class="champ" placeholder="Confirmer le mot de passe" required>
            </div>
            
            <button type="submit" name="inscrire" class="bouton">
                Créer mon compte
                <i data-lucide="leaf" size="18"></i>
            </button>
        </form>

        <div class="pied">
            Déjà inscrit ? <a href="connexion.php" class="lien">Se connecter</a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>