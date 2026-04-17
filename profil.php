<?php 
require_once 'connect_db.php';
requireLogin(); // Sécurité : redirige si non connecté

// Récupération des informations de l'utilisateur
$stmt = $conn->prepare("SELECT nom, email, date_creation, derniere_connexion FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - WhatAPlant</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6d0 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Décoration de fond animée */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%2327ae60" fill-opacity="0.03" d="M20,20 L30,15 L40,20 L50,15 L60,20 L70,15 L80,20 L85,30 L80,40 L85,50 L80,60 L85,70 L80,80 L70,85 L60,80 L50,85 L40,80 L30,85 L20,80 L15,70 L20,60 L15,50 L20,40 L15,30 L20,20Z"/></svg>');
            background-repeat: repeat;
            background-size: 60px;
            opacity: 0.4;
            pointer-events: none;
            animation: floatPattern 20s linear infinite;
        }

        @keyframes floatPattern {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(100px, 50px) rotate(360deg); }
        }

        /* Header moderne */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid rgba(39, 174, 96, 0.2);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            background: none;
            color: #27ae60;
            font-size: 2rem;
        }

        .back-link {
            color: #27ae60;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            transition: all 0.3s ease;
            background: rgba(39, 174, 96, 0.1);
        }

        .back-link:hover {
            background: rgba(39, 174, 96, 0.2);
            transform: translateX(-5px);
        }

        /* Container principal */
        .main {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            position: relative;
            z-index: 2;
        }

        /* Carte de profil premium */
        .profile-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 2rem;
            padding: 3rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(39, 174, 96, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: slideUp 0.6s ease-out;
        }

        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 35px 60px -15px rgba(39, 174, 96, 0.3);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Avatar premium */
        .avatar-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .avatar {
            width: 130px;
            height: 130px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            font-size: 3.5rem;
            font-weight: 600;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 15px 35px rgba(39, 174, 96, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .avatar::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: rotate(0deg) translate(-30%, -30%); }
            100% { transform: rotate(360deg) translate(30%, 30%); }
        }

        .avatar-status {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 24px;
            height: 24px;
            background: #2ecc71;
            border: 3px solid white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .nom {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1f3a2f, #27ae60);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }

        .email {
            color: #6b7280;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 2rem;
        }

        /* Grille d'informations améliorée */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .info-item {
            background: linear-gradient(135deg, #f9fef9, #f0f9f0);
            padding: 1.5rem;
            border-radius: 1.2rem;
            border: 1px solid rgba(39, 174, 96, 0.15);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .info-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #27ae60, #2ecc71);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .info-item:hover::before {
            transform: scaleX(1);
        }

        .info-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(39, 174, 96, 0.1);
        }

        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            color: #27ae60;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-label i {
            font-size: 0.9rem;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f3a2f;
        }

        /* Boutons modernes */
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.9rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            box-shadow: 0 8px 20px rgba(39, 174, 96, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(39, 174, 96, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #3498db, #5dade2);
            color: white;
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(52, 152, 219, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #f97316);
            color: white;
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(239, 68, 68, 0.4);
        }

        hr {
            margin: 2rem 0;
            border: none;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(39, 174, 96, 0.3), transparent);
        }

        /* Badge de statistiques */
        .stats-badge {
            text-align: center;
            margin-top: 1.5rem;
            padding: 1rem;
            background: rgba(39, 174, 96, 0.05);
            border-radius: 1rem;
            font-size: 0.85rem;
            color: #27ae60;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-card {
                padding: 1.5rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .nom {
                font-size: 1.5rem;
            }
            
            .avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
        }

        /* Animation de chargement */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo">
            <i class="fas fa-leaf"></i>
            <span>WhatAPlant</span>
        </div>
        <a href="accueil.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Accueil
        </a>
    </div>

    <div class="main">
        <div class="profile-card">
            <div class="avatar-wrapper">
                <div class="avatar">
                    <?= strtoupper(substr($user['nom'], 0, 1)) ?>
                </div>
                <div class="avatar-status"></div>
            </div>
            
            <h1 class="nom"><?= htmlspecialchars($user['nom']) ?></h1>
            <p class="email">
                <i class="fas fa-envelope"></i>
                <?= htmlspecialchars($user['email']) ?>
            </p>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-calendar-alt"></i>
                        Membre depuis
                    </div>
                    <div class="info-value">
                        <?= date('d F Y', strtotime($user['date_creation'])) ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-clock"></i>
                        Dernière activité
                    </div>
                    <div class="info-value">
                        <?= $user['derniere_connexion'] 
                            ? date('d F Y \à H:i', strtotime($user['derniere_connexion'])) 
                            : 'Première connexion' ?>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button onclick="window.location.href='chat.php'" class="btn btn-primary">
                    <i class="fas fa-comments"></i>
                    Chat IA
                </button>
                
                <button onclick="window.location.href='scan.php'" class="btn btn-secondary">
                    <i class="fas fa-camera"></i>
                    Scanner une plante
                </button>
            </div>

            <div class="stats-badge">
                <i class="fas fa-chart-line"></i>
                Vous avez identifié <strong>0</strong> plantes cette semaine | <i class="fas fa-trophy"></i> Niveau Débutant
            </div>

            <hr>

            <div style="text-align: center;">
                <button onclick="if(confirm('Voulez-vous vraiment vous déconnecter ?')) window.location.href='logout.php'" 
                        class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </button>
            </div>
        </div>
    </div>

    <script>
        // Animation subtile au chargement
        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.info-item, .btn');
            cards.forEach((card, index) => {
                card.style.animation = `fadeIn 0.5s ease-out ${index * 0.1}s forwards`;
                card.style.opacity = '0';
            });
        });

        // Effet de particules flottantes (optionnel)
        const createParticle = () => {
            const particle = document.createElement('div');
            particle.style.position = 'fixed';
            particle.style.width = '2px';
            particle.style.height = '2px';
            particle.style.backgroundColor = '#27ae60';
            particle.style.borderRadius = '50%';
            particle.style.pointerEvents = 'none';
            particle.style.opacity = '0.3';
            particle.style.left = Math.random() * window.innerWidth + 'px';
            particle.style.top = Math.random() * window.innerHeight + 'px';
            particle.style.animation = 'floatParticle 8s linear infinite';
            document.body.appendChild(particle);
            
            setTimeout(() => particle.remove(), 8000);
        };

        // Ajouter quelques particules pour l'ambiance
        setInterval(createParticle, 3000);
        
        // Style pour les particules
        const style = document.createElement('style');
        style.textContent = `
            @keyframes floatParticle {
                0% {
                    transform: translateY(0) rotate(0deg);
                    opacity: 0;
                }
                10% {
                    opacity: 0.3;
                }
                90% {
                    opacity: 0.3;
                }
                100% {
                    transform: translateY(-100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>