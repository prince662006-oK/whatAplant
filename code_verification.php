<?php 
require_once 'connect_db.php';

$erreurs = [];

if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_email'])) {
    header("Location: recuperation.php");
    exit();
}

if (isset($_POST['verifier_code'])) {
    $code_saisi = clean($_POST['code']);

    if (empty($code_saisi)) {
        $erreurs[] = "Veuillez entrer le code";
    } else {
        $stmt = $conn->prepare("SELECT * FROM recuperation_mot_de_passe 
                               WHERE utilisateur_id = ? 
                               AND code = ? 
                               AND expire_le > NOW() 
                               AND utilise = 0");
        $stmt->execute([$_SESSION['reset_user_id'], $code_saisi]);
        $reset = $stmt->fetch();

        if ($reset) {
            $conn->prepare("UPDATE recuperation_mot_de_passe SET utilise = 1 WHERE id = ?")
                 ->execute([$reset['id']]);

            $_SESSION['reset_verified'] = true;
            header("Location: nouveau_mot_de_passe.php");
            exit();
        } else {
            $erreurs[] = "Code incorrect ou expiré";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification | WhatAPlant AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #065f46;
            --text-main: #064e3b;
            --glass: rgba(255, 255, 255, 0.95);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at top right, #dcfce7 0%, #f0fdf4 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .conteneur {
            background: var(--glass);
            backdrop-filter: blur(15px);
            padding: 50px 40px;
            border-radius: 35px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 60px rgba(6, 78, 59, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.8);
            text-align: center;
            position: relative;
        }

        .en-tete { margin-bottom: 35px; }

        .icon-key {
            background: linear-gradient(135deg, #10b981, #059669);
            width: 75px;
            height: 75px;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            box-shadow: 0 12px 20px rgba(16, 185, 129, 0.25);
            transform: rotate(-5deg);
        }

        .titre {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 12px;
        }

        .info-mail {
            background: #f0fdf4;
            padding: 12px;
            border-radius: 12px;
            font-size: 14.5px;
            color: #4a6b5a;
            border: 1px dashed #b8e0d0;
            margin-bottom: 25px;
        }

        .champ-code {
            width: 100%;
            padding: 20px;
            background: #ffffff;
            border: 2.5px solid #e5e7eb;
            border-radius: 20px;
            font-size: 32px;
            font-weight: 800;
            text-align: center;
            letter-spacing: 15px;
            color: var(--primary-dark);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 25px;
        }

        .champ-code:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 5px rgba(16, 185, 129, 0.15);
            background: #fff;
        }

        .bouton {
            width: 100%;
            padding: 18px;
            background: var(--primary-dark);
            color: white;
            border: none;
            border-radius: 18px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: 0.3s ease;
        }

        .bouton:hover {
            background: #044e3a;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(4, 78, 58, 0.2);
        }

        .erreur {
            background: #fef2f2;
            color: #b91c1c;
            padding: 14px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #fee2e2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .lien-retour {
            margin-top: 30px;
            display: inline-block;
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
        }

        .lien-retour:hover {
            color: var(--primary);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="conteneur">
        <div class="en-tete">
            <div class="icon-key">
                <i data-lucide="shield-check" size="40"></i>
            </div>
            <h1 class="titre">Vérification</h1>
            <div class="info-mail">
                Nous avons envoyé un code à 4 chiffres à :<br>
                <strong style="color: var(--primary-dark)"><?= htmlspecialchars($_SESSION['reset_email']) ?></strong>
            </div>
        </div>
        
        <?php if (!empty($erreurs)): ?>
            <?php foreach($erreurs as $erreur): ?>
                <div class="erreur">
                    <i data-lucide="alert-triangle" size="18"></i>
                    <?= $erreur ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST">
            <input type="text" 
                   name="code" 
                   class="champ-code" 
                   maxlength="4" 
                   placeholder="0000" 
                   pattern="\d{4}" 
                   required 
                   autofocus
                   autocomplete="one-time-code">
            
            <button type="submit" name="verifier_code" class="bouton">
                Valider le code
                <i data-lucide="check" size="20"></i>
            </button>
        </form>

        <a href="recuperation.php" class="lien-retour">
            <i data-lucide="refresh-cw" size="14" style="vertical-align: middle; margin-right: 5px;"></i>
            Renvoyer un nouveau code
        </a>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>