<?php
<?php
$host     = 'nozomi.proxy.rlwy.net';
$port     = '14824'; // On ajoute le port spécifique ici
$dbname   = 'railway';
$username = 'root';
$password = 'GEmBBTNXtOErtvGKVFPBlDIjcTbgMnAJ';

try {
    // Le secret est ici : on ajoute "port=$port" dans la chaîne DSN
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $conn = new PDO($dsn, $username, $password);
    
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Test de succès (optionnel, à retirer après)
    // echo "Connexion réussie !"; 

} catch(PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
// Protection contre le double session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function clean($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Si appel AJAX/API → retourner JSON au lieu de rediriger
        if (basename($_SERVER['PHP_SELF']) === 'api_chat.php' ||
            !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error'    => 'Session expirée. Veuillez vous reconnecter.',
                'redirect' => 'index.php'
            ]);
            exit;
        }
        header("Location: connexion.php");
        exit();
    }
}

/**
 * Crée les tables personnelles d'un utilisateur
 * À appeler dans inscription.php après le INSERT utilisateurs
 */
function createUserTables(PDO $conn, int $user_id): void {
    $td = "discussions_utilisateur_" . $user_id;
    $tm = "messages_utilisateur_"    . $user_id;

    $conn->exec("CREATE TABLE IF NOT EXISTS `$td` (
        id      INT AUTO_INCREMENT PRIMARY KEY,
        titre   VARCHAR(150) NOT NULL,
        cree_le DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX   idx_cree_le (cree_le)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->exec("CREATE TABLE IF NOT EXISTS `$tm` (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        discussion_id INT NOT NULL,
        role          ENUM('user','ai') NOT NULL,
        contenu       TEXT NOT NULL,
        cree_le       DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (discussion_id) REFERENCES `$td`(id) ON DELETE CASCADE,
        INDEX idx_disc_id (discussion_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
?>
