<?php 
require_once 'connect_db.php';
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$erreurs = [];

if (isset($_POST['envoyer_code'])) {
    $email = clean($_POST['email']);

    if (empty($email)) {
        $erreurs[] = "Veuillez entrer votre adresse email";
    } else {
        $stmt = $conn->prepare("SELECT id, nom FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $utilisateur = $stmt->fetch();

        if ($utilisateur) {
            $user_id = $utilisateur['id'];
            $nom     = $utilisateur['nom'] ?? 'Utilisateur';
            $code    = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

            $conn->prepare("DELETE FROM recuperation_mot_de_passe WHERE utilisateur_id = ? AND utilise = 0")
                 ->execute([$user_id]);

            $expire = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $stmt = $conn->prepare("INSERT INTO recuperation_mot_de_passe (utilisateur_id, code, expire_le) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $code, $expire]);

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'douaprinceregistrenene@gmail.com';        // ← CHANGE ICI
                $mail->Password   = 'vbrl xotr yrxl mneg';          // ← App Password (16 caractères)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                // Destinataire
                $mail->setFrom('douaprinceregistrenene@gmail.com', 'WhatAPlant AI');
                $mail->addAddress($email, $nom);

                // Contenu du mail
                $mail->isHTML(true);
                $mail->Subject = 'Votre code de vérification - WhatAPlant';

                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 30px; background: #f0fdf4; border-radius: 16px;'>
                        <h2 style='color: #10b981;'>Bonjour {$nom},</h2>
                        <p>Voici votre code de vérification pour réinitialiser votre mot de passe :</p>
                        <h1 style='color: #10b981; font-size: 48px; letter-spacing: 10px; text-align: center; margin: 30px 0;'>{$code}</h1>
                        <p>Ce code est valide pendant <strong>15 minutes</strong>.</p>
                        <p style='color: #6b7280; font-size: 14px;'>Si vous n'avez pas demandé cette réinitialisation, veuillez ignorer cet email.</p>
                        <hr style='margin: 25px 0; border-color: #d1fae5;'>
                        <p style='font-size: 13px; color: #10b981;'>🌿 WhatAPlant AI - Analyse intelligente des plantes</p>
                    </div>
                ";

                $mail->AltBody = "Votre code de vérification est : {$code}. Valable pendant 15 minutes.";

                $mail->send();

                // Sauvegarde en session pour les pages suivantes
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_user_id'] = $user_id;

                header("Location: code_verification.php");
                exit();

            } catch (Exception $e) {
                $erreurs[] = "Impossible d'envoyer l'email. Veuillez réessayer plus tard.";
                // Pour debug (à supprimer en production) :
                // $erreurs[] = "Erreur technique : " . $mail->ErrorInfo;
            }

        } else {
            $erreurs[] = "Aucun compte trouvé avec cet email";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récupération | WhatAPlant AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #065f46;
            --text-main: #064e3b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at center, #f0fdf4 0%, #dcfce7 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .conteneur {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            padding: 45px;
            border-radius: 30px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(6, 78, 59, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.8);
            text-align: center;
        }

        .icon-recovery {
            background: #ecfdf5;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--primary);
            border: 2px solid #d1fae5;
        }

        .titre {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 10px;
        }

        .sous-titre {
            color: #6b7280;
            font-size: 15px;
            line-height: 1.5;
        }

        .group-champ {
            position: relative;
            margin-bottom: 25px;
        }

        .group-champ i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
        }

        .champ {
    width: 100%;
    padding: 16px 16px 16px 50px; /* 50px à gauche pour laisser la place à l'icône */
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    font-size: 16px;
    /* Supprime le margin-right ici */
    display: block; 
}

        * {
    box-sizing: border-box;
}

        .champ:focus {
            outline: none;
            border-color: (--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .bouton:hover {
            background: #044e3a;
            transform: translateY(-2px);
        }

        .erreur {
            background: #fff1f2;
            color: #be123c;
            padding: 14px;
            border-radius: 14px;
            margin-bottom: 25px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="conteneur">
        <div class="icon-recovery">
            <i data-lucide="shield-question" size="40"></i>
        </div>
        <h1 class="titre">Accès perdu ?</h1>
        <p class="sous-titre">Entrez votre email pour recevoir un code de vérification.</p>

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
                <i data-lucide="mail" size="20"></i>
                <input type="email" name="email" class="champ" placeholder="votre@email.com" required>
            </div>
            
            <button type="submit" name="envoyer_code" class="bouton">
                Envoyer le code
                <i data-lucide="send" size="18"></i>
            </button>
        </form>

        <a href="connexion.php" style="margin-top:30px; display:inline-block; color:#6b7280; text-decoration:none;">
            ← Retour à la connexion
        </a>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>