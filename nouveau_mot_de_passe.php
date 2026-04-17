<?php 
require_once 'connect_db.php';

$erreurs = [];

if (!isset($_SESSION['reset_verified']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: recuperation.php");
    exit();
}

if (isset($_POST['changer_password'])) {
    $nouveau = $_POST['nouveau_password'];
    $confirm = $_POST['confirm_password'];

    if (empty($nouveau) || empty($confirm)) {
        $erreurs[] = "Veuillez remplir tous les champs";
    } elseif ($nouveau !== $confirm) {
        $erreurs[] = "Les mots de passe ne correspondent pas";
    } elseif (strlen($nouveau) < 6) {
        $erreurs[] = "Le mot de passe doit contenir au moins 6 caractères";
    } else {
        $hashed = password_hash($nouveau, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
        if ($stmt->execute([$hashed, $_SESSION['reset_user_id']])) {
            // Nettoyage des sessions
            unset($_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_verified'], $_SESSION['reset_code']);

            $_SESSION['success'] = "Votre mot de passe a été modifié avec succès !";
            header("Location: index.php");
            exit();
        } else {
            $erreurs[] = "Une erreur s'est produite";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe - WhatAPlant</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f4fbf7 0%, #e8f5ee 100%);
            color: #1f3a2f;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .conteneur {
            background: white;
            padding: 55px 45px;
            border-radius: 32px;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 25px 60px rgba(27, 138, 94, 0.15);
            border: 1px solid #b8e0d0;
        }

        .conteneur::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(to right, #1b8a5e, #34d399);
        }

        .en-tete {
            text-align: center;
            margin-bottom: 40px;
        }
        .logo {
            font-size: 62px;
            margin-bottom: 15px;
        }
        .titre {
            font-size: 30px;
            font-weight: 700;
            color: #1b8a5e;
        }

        .champ {
            width: 100%;
            padding: 18px 22px;
            margin: 16px 0;
            background: #f8fff9;
            border: 2.5px solid #c8e6d0;
            border-radius: 18px;
            color: #1f3a2f;
            font-size: 17px;
            transition: all 0.4s ease;
        }

        .champ:focus {
            border-color: #1b8a5e;
            box-shadow: 0 0 0 5px rgba(27, 138, 94, 0.18);
        }

        .bouton {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #1b8a5e, #34d399);
            color: white;
            border: none;
            border-radius: 18px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 30px;
        }

        .bouton:hover {
            transform: translateY(-3px);
        }

        .erreur {
            color: #d32f2f;
            background: #ffebee;
            padding: 14px 18px;
            border-radius: 14px;
            margin: 18px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="conteneur">
        <div class="en-tete">
            <div class="logo">🔐</div>
            <h1 class="titre">Nouveau mot de passe</h1>
        </div>
        
        <?php if (!empty($erreurs)): ?>
            <?php foreach($erreurs as $erreur): ?>
                <div class="erreur"><?= $erreur ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST">
            <input type="password" name="nouveau_password" class="champ" placeholder="Nouveau mot de passe" required>
            <input type="password" name="confirm_password" class="champ" placeholder="Confirmer le nouveau mot de passe" required>
            
            <button type="submit" name="changer_password" class="bouton">Changer le mot de passe</button>
        </form>
    </div>
</body>
</html>