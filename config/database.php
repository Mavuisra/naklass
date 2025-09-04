<?php
/**
 * Configuration de la base de données Naklass
 * Système de Gestion Scolaire
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'naklass_db';
    private $username = 'root';
    private $password = '';
    private $conn = null;
    
    public function getConnection() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                $this->username, 
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            echo "Erreur de connexion: " . $e->getMessage();
            die();
        }
        
        return $this->conn;
    }
}

// Configuration globale de l'application
define('APP_NAME', 'Naklass - Système de Gestion Scolaire');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/naklass/');

// Configuration de sécurité
define('BCRYPT_COST', 12);
define('SESSION_LIFETIME', 3600); // 1 heure

// Configuration des rôles
define('ROLES', [
    'admin' => 'Administrateur',
    'direction' => 'Direction',
    'enseignant' => 'Enseignant',
    'secretaire' => 'Secrétaire',
    'caissier' => 'Caissier'
]);

// Configuration des devises
define('CURRENCIES', [
    'CDF' => 'Franc Congolais',
    'USD' => 'Dollar Américain',
    'EUR' => 'Euro'
]);

// Configuration des cycles scolaires
define('CYCLES', [
    'maternelle' => 'Maternelle',
    'primaire' => 'Primaire',
    'secondaire' => 'Secondaire',
    'supérieur' => 'Supérieur'
]);

// Configuration des uploads (avec vérification pour éviter les redéfinitions)
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', __DIR__ . '/../uploads/');
}
if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', '/naklass/uploads/');
}

// Créer le dossier d'uploads s'il n'existe pas
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// Démarrage de la session si pas encore démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
