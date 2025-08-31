<?php
/**
 * Fonctions utilitaires pour Naklass
 * Système de Gestion Scolaire
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Fonction de débogage sécurisée
 */
function debug($data, $die = false) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    if ($die) die();
}

/**
 * Destruction sécurisée de session
 */
function safeSessionDestroy() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        return true;
    }
    return false;
}

/**
 * Nettoyage et validation des données d'entrée
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validation d'email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Génération de token sécurisé
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hachage de mot de passe
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

/**
 * Vérification de mot de passe
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Redirection sécurisée
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Vérification si l'utilisateur est connecté
 */
function isLoggedIn() {
    // Vérifier si les données de session essentielles existent
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Vérifier l'expiration de session
    if (isset($_SESSION['last_activity'])) {
        $session_lifetime = SESSION_LIFETIME; // défini dans config/database.php
        if (time() - $_SESSION['last_activity'] > $session_lifetime) {
            // Session expirée - détruire la session si elle est active
            safeSessionDestroy();
            
            // Rediriger seulement si on n'est pas déjà sur la page d'accueil ou de login
            $current_page = basename($_SERVER['PHP_SELF']);
            if (!in_array($current_page, ['index.php', 'login.php', 'check_session.php'])) {
                redirect('/naklass/index.php');
            }
            
            return false;
        }
        // Mettre à jour l'activité
        $_SESSION['last_activity'] = time();
    }
    
    return true;
}

/**
 * Vérification si l'utilisateur est un Super Administrateur
 */
function isSuperAdmin() {
    // Vérification de base : utilisateur connecté
    if (!isLoggedIn()) {
        return false;
    }
    
    // Priorité 1: Vérification par session
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
        return true;
    }
    
    // Priorité 2: Vérification par rôle dans la session
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin') {
        return true;
    }
    
    // Priorité 3: Vérification par niveau d'accès
    if (isset($_SESSION['niveau_acces']) && $_SESSION['niveau_acces'] === 'super_admin') {
        return true;
    }
    
    // Priorité 4: Vérification en base de données (si nécessaire)
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Vérifier si le rôle super_admin existe
        $check_role_query = "SELECT id FROM roles WHERE code = 'super_admin'";
        $check_role_stmt = $db->prepare($check_role_query);
        $check_role_stmt->execute();
        $super_admin_role_exists = $check_role_stmt->fetch();
        
        if ($super_admin_role_exists && isset($_SESSION['user_id'])) {
            // Vérifier si l'utilisateur a le rôle super_admin
            $query = "SELECT u.id, r.code as role_code 
                      FROM utilisateurs u 
                      JOIN roles r ON u.role_id = r.id 
                      WHERE u.id = :user_id AND r.code = 'super_admin'";
            $stmt = $db->prepare($query);
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Mettre à jour la session
                $_SESSION['is_super_admin'] = true;
                $_SESSION['user_role'] = 'super_admin';
                $_SESSION['niveau_acces'] = 'super_admin';
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        // En cas d'erreur, retourner false
        return false;
    }
}

/**
 * Vérification si la configuration de l'école est complète
 */
function isSchoolSetupComplete() {
    // Les Super Admins n'ont pas besoin de configuration d'école
    if (isSuperAdmin()) {
        return true;
    }
    
    // Vérifier si l'utilisateur a une école
    if (!isset($_SESSION['ecole_id'])) {
        return false;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT configuration_complete, super_admin_validated, statut FROM ecoles WHERE id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $ecole = $stmt->fetch();
        
        if ($ecole) {
            // Si l'école est active et validée, considérer que la configuration est complète
            if ($ecole['statut'] === 'actif' && $ecole['super_admin_validated']) {
                return true;
            }
            
            // Sinon, vérifier la configuration manuelle
            return $ecole['configuration_complete'] && $ecole['super_admin_validated'];
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Vérification si l'école est validée par le super admin
 */
function isSchoolValidatedBySuperAdmin() {
    // Les Super Admins n'ont pas besoin de validation
    if (isSuperAdmin()) {
        return true;
    }
    
    // Vérifier si l'utilisateur a une école
    if (!isset($_SESSION['ecole_id'])) {
        return false;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT super_admin_validated FROM ecoles WHERE id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $ecole = $stmt->fetch();
        
        if ($ecole) {
            return $ecole['super_admin_validated'];
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Vérification si l'école existe déjà et est active
 */
function hasActiveSchool() {
    // Les Super Admins n'ont pas d'école spécifique
    if (isSuperAdmin()) {
        return false;
    }
    
    // Vérifier si l'utilisateur a une école
    if (!isset($_SESSION['ecole_id'])) {
        return false;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, statut, super_admin_validated FROM ecoles WHERE id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $ecole = $stmt->fetch();
        
        if ($ecole) {
            // L'école existe et est active
            return $ecole['statut'] === 'actif' && $ecole['super_admin_validated'];
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Vérification et redirection vers la configuration si nécessaire
 * À utiliser dans les pages protégées
 */
function requireSchoolSetup() {
    if (!isLoggedIn()) {
        redirect('/naklass/auth/login.php');
        return;
    }
    
    // Ne pas rediriger si on est déjà sur la page de configuration ou de logout
    $current_page = basename($_SERVER['PHP_SELF']);
    if (in_array($current_page, ['school_setup.php', 'logout.php'])) {
        return;
    }
    
    // Vérifier si l'utilisateur est admin
    if ($_SESSION['user_role'] === 'admin') {
        // Si l'admin a déjà une école active, pas besoin de configuration
        if (hasActiveSchool()) {
            return; // L'école est déjà active, continuer
        }
        
        // Si l'école n'est pas encore active, vérifier si elle existe déjà
        if (isset($_SESSION['ecole_id'])) {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                $query = "SELECT id, statut, super_admin_validated FROM ecoles WHERE id = :ecole_id";
                $stmt = $db->prepare($query);
                $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                $ecole = $stmt->fetch();
                
                if ($ecole && $ecole['statut'] === 'actif') {
                    // L'école existe et est active, pas besoin de configuration
                    return;
                }
            } catch (Exception $e) {
                // En cas d'erreur, continuer sans redirection
                error_log('Erreur lors de la vérification de l\'école: ' . $e->getMessage());
            }
        }
        
        // Si on arrive ici, l'école n'est pas encore active, rediriger vers la configuration
        if (!isSchoolSetupComplete()) {
            redirect('/naklass/auth/school_setup.php');
            return;
        }
    }
    
    // Si l'utilisateur n'est pas admin mais que l'école n'est pas configurée,
    // afficher un message d'attente
    if ($_SESSION['user_role'] !== 'admin' && !isSchoolSetupComplete()) {
        showSchoolSetupPendingMessage();
        exit();
    }
}

/**
 * Affichage d'un message d'attente pour les utilisateurs non-admin
 * quand la configuration de l'école n'est pas complète
 */
function showSchoolSetupPendingMessage() {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Configuration en attente - <?php echo APP_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card mt-5">
                        <div class="card-body text-center p-5">
                            <div class="mb-4">
                                <i class="bi bi-hourglass-split text-warning" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="card-title mb-4">Configuration en attente</h3>
                            <p class="card-text mb-4">
                                La configuration initiale de votre établissement n'est pas encore complétée. 
                                Veuillez contacter l'administrateur système pour finaliser la configuration.
                            </p>
                            <p class="text-muted small mb-4">
                                Une fois la configuration terminée, vous pourrez accéder à toutes les fonctionnalités du système.
                            </p>
                            <a href="/naklass/auth/logout.php" class="btn btn-secondary">
                                <i class="bi bi-box-arrow-right me-2"></i>Se déconnecter
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Vérification du rôle utilisateur
 */
function hasRole($required_roles) {
    if (!isLoggedIn()) return false;
    
    // Les Super Admins ont accès à tout
    if (isSuperAdmin()) {
        return true;
    }
    
    $user_role = $_SESSION['user_role'] ?? '';
    
    if (is_string($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    return in_array($user_role, $required_roles) || in_array('admin', [$user_role]);
}

/**
 * Vérification spécifique pour les fonctions Super Admin
 */
function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        setFlashMessage('error', 'Accès refusé. Seuls les Super Administrateurs peuvent accéder à cette section.');
        redirect('/naklass/auth/dashboard.php');
    }
}

/**
 * Vérification si l'utilisateur peut accéder aux données d'une école spécifique
 */
function canAccessSchool($ecole_id) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Les Super Admins peuvent accéder à toutes les écoles
    if (isSuperAdmin()) {
        return true;
    }
    
    // Les autres utilisateurs ne peuvent accéder qu'à leur école
    return isset($_SESSION['ecole_id']) && $_SESSION['ecole_id'] == $ecole_id;
}

/**
 * Obtenir la liste des écoles accessibles par l'utilisateur connecté
 */
function getAccessibleSchools() {
    if (!isLoggedIn()) {
        return [];
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if (isSuperAdmin()) {
            // Super Admin peut voir toutes les écoles
            $query = "SELECT * FROM ecoles WHERE statut = 'actif' ORDER BY nom";
            $stmt = $db->prepare($query);
            $stmt->execute();
        } else {
            // Utilisateur normal ne peut voir que son école
            $query = "SELECT * FROM ecoles WHERE id = :ecole_id AND statut = 'actif'";
            $stmt = $db->prepare($query);
            $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        }
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Protection des pages nécessitant une authentification
 */
function requireAuth($redirect_to = 'login.php') {
    if (!isLoggedIn()) {
        redirect($redirect_to);
    }
    
    // Vérifier que les données de session essentielles sont présentes
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        // Détruire la session si elle est active
        safeSessionDestroy();
        redirect($redirect_to);
    }
}

/**
 * Protection des pages nécessitant un rôle spécifique
 */
function requireRole($required_roles, $redirect_to = 'dashboard.php') {
    requireAuth();
    
    if (!hasRole($required_roles)) {
        $_SESSION['error'] = "Accès non autorisé.";
        redirect($redirect_to);
    }
}

/**
 * Messages flash
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessages() {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Récupérer un seul message flash (compatible avec les pages de profil)
 */
function getFlashMessage() {
    $messages = $_SESSION['flash'] ?? [];
    if (!empty($messages)) {
        unset($_SESSION['flash']);
        // Retourner le premier message avec son type
        foreach ($messages as $type => $message) {
            return ['type' => $type, 'message' => $message];
        }
    }
    return null;
}

/**
 * Formatage des dates
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

/**
 * Formatage des montants
 */
function formatAmount($amount, $currency = 'CDF') {
    if (empty($amount)) return '0';
    
    $formatted = number_format($amount, 2, ',', ' ');
    
    switch ($currency) {
        case 'USD':
            return $formatted . ' $';
        case 'EUR':
            return $formatted . ' €';
        case 'CDF':
        default:
            return $formatted . ' FC';
    }
}

/**
 * Génération de matricule unique
 */
function generateMatricule($type = 'EL', $db = null) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    $year = date('y');
    $prefix = $type . $year;
    
    // Déterminer la table selon le type
    $table = ($type === 'EL') ? 'eleves' : 'enseignants';
    $column = ($type === 'EL') ? 'matricule' : 'matricule_enseignant';
    
    // Récupérer le dernier numéro
    $query = "SELECT MAX(CAST(SUBSTRING($column, " . (strlen($prefix) + 1) . ") AS UNSIGNED)) as last_num 
              FROM $table 
              WHERE $column LIKE :prefix";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['prefix' => $prefix . '%']);
    $result = $stmt->fetch();
    
    $next_num = ($result['last_num'] ?? 0) + 1;
    
    return $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

/**
 * Upload de fichiers sécurisé
 */
function uploadFile($file, $directory = 'documents', $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors de l\'upload du fichier.'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé.'];
    }
    
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Fichier trop volumineux (max 5MB).'];
    }
    
    $upload_dir = UPLOAD_PATH . $directory . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    }
    
    return ['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier.'];
}

/**
 * Logging des actions utilisateur
 */
function logUserAction($action, $details = '', $user_id = null) {
    if (!$user_id && isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO user_logs (user_id, action, details, ip_address, user_agent, created_at) 
              VALUES (:user_id, :action, :details, :ip, :user_agent, NOW())";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute([
            'user_id' => $user_id,
            'action' => $action,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Log silencieux en cas d'erreur
        error_log("Erreur log utilisateur: " . $e->getMessage());
    }
}

/**
 * Génération de reçu de paiement
 */
function generateRecuNumber($date = null) {
    if (!$date) $date = date('Y-m-d');
    
    $database = new Database();
    $db = $database->getConnection();
    
    $date_part = date('Ymd', strtotime($date));
    $prefix = 'REC' . $date_part;
    
    $query = "SELECT MAX(CAST(SUBSTRING(numero_recu, 12) AS UNSIGNED)) as last_num 
              FROM paiements 
              WHERE DATE(date_paiement) = :date";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['date' => $date]);
    $result = $stmt->fetch();
    
    $next_num = ($result['last_num'] ?? 0) + 1;
    
    return $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

/**
 * Calcul de l'âge
 */
function calculateAge($birthdate) {
    if (empty($birthdate)) return 0;
    
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birth);
    
    return $age->y;
}

/**
 * Validation des données élève
 */
function validateStudentData($data) {
    $errors = [];
    
    if (empty($data['nom'])) {
        $errors[] = "Le nom est obligatoire.";
    }
    
    if (empty($data['prenom'])) {
        $errors[] = "Le prénom est obligatoire.";
    }
    
    if (empty($data['sexe']) || !in_array($data['sexe'], ['M', 'F', 'Autre'])) {
        $errors[] = "Le sexe doit être spécifié.";
    }
    
    if (empty($data['date_naissance'])) {
        $errors[] = "La date de naissance est obligatoire.";
    } elseif (strtotime($data['date_naissance']) > time()) {
        $errors[] = "La date de naissance ne peut pas être dans le futur.";
    }
    
    if (!empty($data['email']) && !validateEmail($data['email'])) {
        $errors[] = "L'email n'est pas valide.";
    }
    
    return $errors;
}

/**
 * Génération de rapport PDF (placeholder pour intégration future)
 */
function generatePDFReport($type, $data, $filename = null) {
    // Cette fonction sera implémentée avec une librairie PDF comme TCPDF ou FPDF
    return ['success' => false, 'message' => 'Génération PDF à implémenter'];
}

/**
 * Envoi d'email (placeholder pour intégration future)
 */
function sendEmail($to, $subject, $message, $headers = []) {
    // Cette fonction sera implémentée avec PHPMailer ou une API email
    return ['success' => false, 'message' => 'Envoi email à implémenter'];
}

/**
 * Conversion de devises (placeholder pour intégration future)
 */
function convertCurrency($amount, $from, $to) {
    // Cette fonction sera implémentée avec une API de taux de change
    if ($from === $to) return $amount;
    
    // Taux fictifs pour démonstration
    $rates = [
        'USD_CDF' => 2000,
        'EUR_CDF' => 2200,
        'USD_EUR' => 0.85
    ];
    
    $key = $from . '_' . $to;
    if (isset($rates[$key])) {
        return $amount * $rates[$key];
    }
    
    return $amount;
}

/**
 * Vérifier si une colonne existe dans une table
 */
function columnExists($table, $column, $db = null) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $query = "SHOW COLUMNS FROM $table LIKE :column";
        $stmt = $db->prepare($query);
        $stmt->execute(['column' => $column]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtenir l'URL de la photo de profil d'un utilisateur
 */
function getProfilePhotoUrl($photo_path) {
    if (empty($photo_path)) {
        return null;
    }
    
    $photo_file_path = UPLOAD_PATH . 'profile/' . $photo_path;
    if (file_exists($photo_file_path)) {
        return UPLOAD_URL . 'profile/' . htmlspecialchars($photo_path);
    }
    
    return null;
}

/**
 * Mise à jour sécurisée des tentatives de connexion
 */
function updateLoginAttempts($user_id, $success = true, $db = null) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        if ($success) {
            // Connexion réussie
            if (columnExists('utilisateurs', 'tentatives_connexion', $db)) {
                $query = "UPDATE utilisateurs SET 
                         derniere_connexion_at = NOW(), 
                         tentatives_connexion = 0 
                         WHERE id = :id";
            } else {
                $query = "UPDATE utilisateurs SET derniere_connexion_at = NOW() WHERE id = :id";
            }
        } else {
            // Connexion échouée
            if (columnExists('utilisateurs', 'tentatives_connexion', $db)) {
                $query = "UPDATE utilisateurs SET 
                         tentatives_connexion = COALESCE(tentatives_connexion, 0) + 1,
                         derniere_tentative = NOW() 
                         WHERE id = :id";
            } else {
                // Si les colonnes n'existent pas, ne rien faire
                return true;
            }
        }
        
        $stmt = $db->prepare($query);
        return $stmt->execute(['id' => $user_id]);
        
    } catch (Exception $e) {
        error_log("Erreur lors de la mise à jour des tentatives de connexion: " . $e->getMessage());
        return false;
    }
}

/**
 * Fonctions de chiffrement pour sécuriser les IDs dans les URLs
 */

/**
 * Chiffre un ID pour utilisation sécurisée dans les URLs
 * @param int $id L'ID à chiffrer
 * @return string L'ID chiffré encodé en base64 URL-safe
 */
function encryptId($id) {
    // Clé secrète basée sur une configuration de l'application
    $secret_key = hash('sha256', APP_NAME . '_encryption_key_' . date('Y'));
    
    // Méthode de chiffrement
    $method = 'AES-256-CBC';
    
    // Vecteur d'initialisation aléatoire
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    
    // Chiffrement
    $encrypted = openssl_encrypt($id, $method, $secret_key, 0, $iv);
    
    // Combinaison IV + données chiffrées, encodage base64 URL-safe
    $result = base64_encode($iv . $encrypted);
    return str_replace(['+', '/', '='], ['-', '_', ''], $result);
}

/**
 * Déchiffre un ID chiffré
 * @param string $encrypted_id L'ID chiffré
 * @return int|false L'ID déchiffré ou false en cas d'erreur
 */
function decryptId($encrypted_id) {
    try {
        // Clé secrète (même que pour le chiffrement)
        $secret_key = hash('sha256', APP_NAME . '_encryption_key_' . date('Y'));
        
        // Méthode de chiffrement
        $method = 'AES-256-CBC';
        
        // Décodage base64 URL-safe
        $data = str_replace(['-', '_'], ['+', '/'], $encrypted_id);
        $data = base64_decode($data);
        
        if ($data === false) {
            return false;
        }
        
        // Longueur du vecteur d'initialisation
        $iv_length = openssl_cipher_iv_length($method);
        
        if (strlen($data) <= $iv_length) {
            return false;
        }
        
        // Extraction de l'IV et des données chiffrées
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        // Déchiffrement
        $decrypted = openssl_decrypt($encrypted, $method, $secret_key, 0, $iv);
        
        // Validation que c'est bien un nombre
        if ($decrypted === false || !is_numeric($decrypted)) {
            return false;
        }
        
        return (int)$decrypted;
        
    } catch (Exception $e) {
        error_log("Erreur lors du déchiffrement d'ID: " . $e->getMessage());
        return false;
    }
}

/**
 * Crée un lien sécurisé avec ID chiffré
 * @param string $base_url L'URL de base
 * @param int $id L'ID à chiffrer
 * @param string $param_name Le nom du paramètre (défaut: 'id')
 * @return string L'URL complète avec ID chiffré
 */
function createSecureLink($base_url, $id, $param_name = 'id') {
    $encrypted_id = encryptId($id);
    $separator = strpos($base_url, '?') !== false ? '&' : '?';
    return $base_url . $separator . $param_name . '=' . urlencode($encrypted_id);
}

/**
 * Récupère et déchiffre un ID depuis les paramètres GET
 * @param string $param_name Le nom du paramètre (défaut: 'id')
 * @return int|false L'ID déchiffré ou false si invalide
 */
function getSecureId($param_name = 'id') {
    if (!isset($_GET[$param_name]) || empty($_GET[$param_name])) {
        return false;
    }
    
    return decryptId($_GET[$param_name]);
}

/**
 * Valide et récupère un ID sécurisé avec vérification de permissions
 * @param string $param_name Le nom du paramètre
 * @param string $table La table à vérifier
 * @param array $where_conditions Conditions supplémentaires de vérification
 * @return int|false L'ID validé ou false
 */
function validateSecureId($param_name, $table, $where_conditions = []) {
    $id = getSecureId($param_name);
    
    if ($id === false) {
        return false;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Construction de la requête de validation
        $where_clauses = ["id = :id"];
        $params = ['id' => $id];
        
        // Ajout des conditions supplémentaires
        foreach ($where_conditions as $condition => $value) {
            $where_clauses[] = "$condition = :$condition";
            $params[$condition] = $value;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        $query = "SELECT id FROM $table WHERE $where_sql";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetch() ? $id : false;
        
    } catch (Exception $e) {
        error_log("Erreur lors de la validation d'ID sécurisé: " . $e->getMessage());
        return false;
    }
}

/**
 * Chiffre un ID d'élève avec un encodage simple (base64 + préfixe)
 * @param int $id L'ID à chiffrer
 * @return string L'ID chiffré
 */
function encryptStudentId($id) {
    $prefix = "STU_" . $id;
    return base64_encode($prefix);
}

/**
 * Déchiffre un ID d'élève chiffré avec un encodage simple
 * @param string $encrypted_id L'ID chiffré
 * @return int|false L'ID déchiffré ou false en cas d'erreur
 */
function decryptStudentId($encrypted_id) {
    try {
        $decoded = base64_decode($encrypted_id);
        if ($decoded === false) {
            return false;
        }
        
        $clean_id = str_replace('STU_', '', $decoded);
        
        if (is_numeric($clean_id) && $clean_id > 0) {
            return (int)$clean_id;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Erreur lors du déchiffrement d'ID d'élève: " . $e->getMessage());
        return false;
    }
}

/**
 * Gestion de l'upload de photos
 * @param array $file Le fichier $_FILES['photo']
 * @param string $directory Le répertoire de destination (students, teachers, etc.)
 * @param int $id L'ID de l'entité
 * @return array Résultat avec success et message
 */
function handlePhotoUpload($file, $directory, $id) {
    // Vérifier s'il y a une erreur d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors de l\'upload du fichier.'];
    }
    
    // Vérifier la taille du fichier (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Le fichier est trop volumineux (max 5MB).'];
    }
    
    // Vérifier le type de fichier
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Format de fichier non supporté. Utilisez JPG, PNG ou GIF.'];
    }
    
    // Créer le répertoire de destination s'il n'existe pas
    $upload_dir = __DIR__ . "/../uploads/$directory/";
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Impossible de créer le répertoire de destination.'];
        }
    }
    
    // Générer un nom de fichier unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $directory . '_' . $id . '_' . uniqid() . '.' . strtolower($extension);
    $file_path = $upload_dir . $filename;
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Redimensionner l'image si l'extension GD est disponible
        if (extension_loaded('gd')) {
            resizeImage($file_path, 400, 400);
        }
        
        // Retourner le chemin relatif pour la base de données
        return [
            'success' => true, 
            'file_path' => "uploads/$directory/$filename",
            'message' => 'Photo uploadée avec succès.'
        ];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier.'];
    }
}

/**
 * Redimensionner une image
 * @param string $file_path Chemin du fichier
 * @param int $max_width Largeur maximale
 * @param int $max_height Hauteur maximale
 */
function resizeImage($file_path, $max_width, $max_height) {
    // Vérifier si l'extension GD est disponible
    if (!extension_loaded('gd')) {
        error_log("Extension GD non disponible pour le redimensionnement d'image");
        return false;
    }
    
    try {
        $image_info = getimagesize($file_path);
        if (!$image_info) return false;
        
        $width = $image_info[0];
        $height = $image_info[1];
        $type = $image_info[2];
        
        // Calculer les nouvelles dimensions
        if ($width <= $max_width && $height <= $max_height) {
            return true; // Pas besoin de redimensionner
        }
        
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
        
        // Vérifier si les fonctions d'image sont disponibles
        $source = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                if (function_exists('imagecreatefromjpeg')) {
                    $source = imagecreatefromjpeg($file_path);
                }
                break;
            case IMAGETYPE_PNG:
                if (function_exists('imagecreatefrompng')) {
                    $source = imagecreatefrompng($file_path);
                }
                break;
            case IMAGETYPE_GIF:
                if (function_exists('imagecreatefromgif')) {
                    $source = imagecreatefromgif($file_path);
                }
                break;
            default:
                return false;
        }
        
        if (!$source) {
            error_log("Impossible de créer l'image source pour le type: " . $type);
            return false;
        }
        
        // Créer l'image de destination
        $destination = imagecreatetruecolor($new_width, $new_height);
        if (!$destination) {
            imagedestroy($source);
            return false;
        }
        
        // Préserver la transparence pour PNG et GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
            imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Redimensionner
        $resize_success = imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        if (!$resize_success) {
            imagedestroy($source);
            imagedestroy($destination);
            return false;
        }
        
        // Sauvegarder
        $save_success = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                if (function_exists('imagejpeg')) {
                    $save_success = imagejpeg($destination, $file_path, 85);
                }
                break;
            case IMAGETYPE_PNG:
                if (function_exists('imagepng')) {
                    $save_success = imagepng($destination, $file_path, 6);
                }
                break;
            case IMAGETYPE_GIF:
                if (function_exists('imagegif')) {
                    $save_success = imagegif($destination, $file_path);
                }
                break;
        }
        
        // Libérer la mémoire
        imagedestroy($source);
        imagedestroy($destination);
        
        return $save_success;
    } catch (Exception $e) {
        error_log("Erreur lors du redimensionnement de l'image: " . $e->getMessage());
        return false;
    }
}

/**
 * Générer un numéro de reçu unique
 * @param PDO $db Connexion à la base de données
 * @param int $ecole_id ID de l'école
 * @return string Numéro de reçu unique
 */
function generateReceiptNumber($db, $ecole_id) {
    $year = date('Y');
    $month = date('m');
    
    // Format: REC-YYYY-MM-NNNN (ex: REC-2024-03-0001)
    $prefix = "REC-$year-$month-";
    
    // Compter les reçus du mois
    $count_query = "SELECT COUNT(*) as total FROM paiements 
                    WHERE YEAR(date_paiement) = :year 
                    AND MONTH(date_paiement) = :month
                    AND numero_recu LIKE :prefix";
    
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute([
        'year' => $year,
        'month' => $month,
        'prefix' => $prefix . '%'
    ]);
    
    $count = $count_stmt->fetch()['total'];
    $number = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    
    return $prefix . $number;
}

/**
 * Générer une référence de transaction unique
 * @param PDO $db Connexion à la base de données
 * @param string $mode Mode de paiement pour personnaliser le préfixe
 * @return string Référence de transaction unique
 */
function generateTransactionReference($db, $mode = 'PAY') {
    $date = date('Ymd');
    $time = date('His');
    
    // Préfixes selon le mode de paiement
    $prefixes = [
        'espèces' => 'ESP',
        'mobile_money' => 'MM',
        'carte' => 'CB',
        'virement' => 'VIR',
        'chèque' => 'CHQ'
    ];
    
    $prefix = $prefixes[$mode] ?? 'PAY';
    
    // Format: ESP-20240824-143022-001
    $base_ref = "$prefix-$date-$time";
    
    // Vérifier l'unicité et ajouter un compteur si nécessaire
    $counter = 1;
    $reference = $base_ref . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
    
    while (transactionReferenceExists($db, $reference)) {
        $counter++;
        $reference = $base_ref . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        
        // Sécurité : éviter une boucle infinie
        if ($counter > 999) {
            $reference = $base_ref . '-' . uniqid();
            break;
        }
    }
    
    return $reference;
}

/**
 * Vérifier si une référence de transaction existe déjà
 * @param PDO $db Connexion à la base de données
 * @param string $reference Référence à vérifier
 * @return bool True si la référence existe
 */
function transactionReferenceExists($db, $reference) {
    $query = "SELECT COUNT(*) FROM paiements WHERE reference_transaction = :reference";
    $stmt = $db->prepare($query);
    $stmt->execute(['reference' => $reference]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Convertit un groupe de trois chiffres en lettres
 * 
 * @param int $num Le nombre à convertir (0-999)
 * @return string Le nombre en lettres
 */
function convertNumberGroup($num) {
    $units = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'
    ];
    
    $tens = [
        '', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'
    ];
    
    $result = '';
    
    if ($num >= 100) {
        $hundreds = intval($num / 100);
        if ($hundreds == 1) {
            $result .= 'cent';
        } else {
            $result .= $units[$hundreds] . ' cent';
        }
        if ($num % 100 > 0) {
            $result .= ($hundreds > 1) ? 's ' : ' ';
        }
        $num %= 100;
    }
    
    if ($num >= 20) {
        $ten = intval($num / 10);
        $unit = $num % 10;
        
        $result .= $tens[$ten];
        
        if ($unit > 0) {
            if ($ten == 7 || $ten == 9) {
                $result .= '-' . $units[10 + $unit];
            } else {
                $result .= ($unit == 1 && $ten == 8) ? '' : '-' . $units[$unit];
                if ($ten == 8 && $unit == 0) {
                    $result .= 's';
                }
            }
        }
    } elseif ($num > 0) {
        $result .= $units[$num];
    }
    
    return trim($result);
}

/**
 * Convertit un nombre en lettres (français)
 * 
 * @param float $number Le nombre à convertir
 * @return string Le nombre en lettres
 */
function numberToWords($number) {
    $number = floatval($number);
    
    if ($number == 0) {
        return 'zéro';
    }
    
    $scales = [
        '', 'mille', 'million', 'milliard', 'billion'
    ];
    
    // Séparer la partie entière et décimale
    $parts = explode('.', number_format($number, 2, '.', ''));
    $integer_part = intval($parts[0]);
    $decimal_part = isset($parts[1]) ? intval($parts[1]) : 0;
    
    // Convertir la partie entière
    $result = '';
    if ($integer_part > 0) {
        $groups = [];
        $scale_index = 0;
        
        while ($integer_part > 0) {
            $group = $integer_part % 1000;
            if ($group > 0) {
                $group_text = convertNumberGroup($group);
                
                if ($scale_index > 0) {
                    $scale = $scales[$scale_index];
                    if ($group > 1 && $scale_index > 1) {
                        $scale .= 's';
                    }
                    $group_text .= ' ' . $scale;
                }
                
                array_unshift($groups, $group_text);
            }
            
            $integer_part = intval($integer_part / 1000);
            $scale_index++;
        }
        
        $result = implode(' ', $groups);
    }
    
    // Ajouter la partie décimale si nécessaire
    if ($decimal_part > 0) {
        if (!empty($result)) {
            $result .= ' virgule ';
        }
        
        if ($decimal_part < 10) {
            $result .= 'zéro ' . convertNumberGroup($decimal_part);
        } else {
            $result .= convertNumberGroup($decimal_part);
        }
    }
    
    return $result ?: 'zéro';
}

/**
 * Gère le rollback d'une transaction de manière sécurisée
 * 
 * @param PDO $db L'instance de la base de données
 * @return bool True si le rollback a été effectué, false sinon
 */
function safeRollback($db) {
    if ($db && $db->inTransaction()) {
        return $db->rollBack();
    }
    return false;
}

/**
 * Gère le commit d'une transaction de manière sécurisée
 * 
 * @param PDO $db L'instance de la base de données
 * @return bool True si le commit a été effectué, false sinon
 */
function safeCommit($db) {
    if ($db && $db->inTransaction()) {
        return $db->commit();
    }
    return false;
}

// ============================================================================
// FONCTIONS STANDARDISÉES POUR LA GESTION DES CLASSES ET ÉTUDIANTS
// ============================================================================

/**
 * Récupère les détails d'une classe avec validation des permissions
 * 
 * @param int $class_id ID de la classe
 * @param PDO $db Instance de la base de données
 * @return array|false Détails de la classe ou false si erreur
 */
function getClassDetails($class_id, $db = null) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $class_query = "SELECT c.*, 
                               c.niveau as niveau_nom,
                               c.cycle as section_nom,
                               e.prenom as enseignant_prenom,
                               e.nom as enseignant_nom,
                               u.prenom as created_by_prenom,
                               u.nom as created_by_nom
                        FROM classes c
                        LEFT JOIN enseignants e ON c.professeur_principal_id = e.id
                        LEFT JOIN utilisateurs u ON c.created_by = u.id
                        WHERE c.id = :class_id AND c.ecole_id = :ecole_id";
        
        $stmt = $db->prepare($class_query);
        $stmt->execute([
            'class_id' => $class_id,
            'ecole_id' => $_SESSION['ecole_id']
        ]);
        
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des détails de la classe {$class_id}: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère la liste des étudiants inscrits dans une classe
 * 
 * @param int $class_id ID de la classe
 * @param PDO $db Instance de la base de données
 * @param bool $include_inactive Inclure les étudiants inactifs
 * @return array Liste des étudiants
 */
function getClassStudents($class_id, $db = null, $include_inactive = false) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        // Condition de statut simplifiée
        $status_condition = $include_inactive ? "" : "AND i.statut = 'actif'";
        
        $students_query = "SELECT i.id as inscription_id,
                                 i.eleve_id,
                                 el.prenom, 
                                 el.nom, 
                                 el.date_naissance,
                                 el.sexe,
                                 el.photo_path as photo,
                                 el.matricule,
                                 el.telephone,
                                 el.email,
                                 i.date_inscription,
                                 i.statut_inscription,
                                 i.statut as inscription_statut
                          FROM inscriptions i
                          JOIN eleves el ON i.eleve_id = el.id
                          WHERE i.classe_id = :class_id 
                          AND i.statut_inscription = 'validée'
                          {$status_condition}
                          ORDER BY el.nom ASC, el.prenom ASC";
        
        $stmt = $db->prepare($students_query);
        $stmt->execute(['class_id' => $class_id]);
        
        $result = $stmt->fetchAll();
        
        // Debug: Log le nombre d'étudiants trouvés
        error_log("getClassStudents($class_id): " . count($result) . " étudiants trouvés");
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des étudiants de la classe {$class_id}: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère le nombre d'étudiants inscrits dans une classe
 * 
 * @param int $class_id ID de la classe
 * @param PDO $db Instance de la base de données
 * @param bool $active_only Compter seulement les étudiants actifs
 * @return int Nombre d'étudiants
 */
function getClassStudentCount($class_id, $db = null, $active_only = true) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $status_condition = $active_only ? "AND i.statut = 'actif' AND el.statut = 'actif'" : "";
        
        $count_query = "SELECT COUNT(DISTINCT i.eleve_id) as total
                        FROM inscriptions i
                        JOIN eleves el ON i.eleve_id = el.id
                        WHERE i.classe_id = :class_id 
                        AND i.statut_inscription = 'validée'
                        {$status_condition}";
        
        $stmt = $db->prepare($count_query);
        $stmt->execute(['class_id' => $class_id]);
        
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Erreur lors du comptage des étudiants de la classe {$class_id}: " . $e->getMessage());
        return 0;
    }
}

/**
 * Récupère la liste des cours assignés à une classe
 * 
 * @param int $class_id ID de la classe
 * @param PDO $db Instance de la base de données
 * @param bool $active_only Inclure seulement les cours actifs
 * @return array Liste des cours
 */
function getClassCourses($class_id, $db = null, $active_only = true) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $status_condition = $active_only ? "AND cc.statut = 'actif' AND c.statut = 'actif'" : "";
        
        $cours_query = "SELECT cc.*, c.nom_cours, c.code_cours, c.coefficient,
                               e.prenom as enseignant_prenom, e.nom as enseignant_nom
                        FROM classe_cours cc
                        JOIN cours c ON cc.cours_id = c.id
                        LEFT JOIN enseignants e ON cc.enseignant_id = e.id
                        WHERE cc.classe_id = :classe_id 
                        {$status_condition}
                        ORDER BY c.nom_cours";
        
        $stmt = $db->prepare($cours_query);
        $stmt->execute(['classe_id' => $class_id]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des cours de la classe {$class_id}: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère la liste des étudiants disponibles pour inscription dans une classe
 * 
 * @param int $class_id ID de la classe
 * @param PDO $db Instance de la base de données
 * @return array Liste des étudiants disponibles
 */
function getAvailableStudentsForClass($class_id, $db = null) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $available_students_query = "SELECT el.* FROM eleves el 
                                    WHERE el.ecole_id = :ecole_id 
                                    AND el.statut = 'actif'
                                    AND el.id NOT IN (
                                        SELECT COALESCE(eleve_id, 0) FROM inscriptions 
                                        WHERE classe_id = :class_id AND statut = 'actif'
                                    )
                                    ORDER BY el.nom ASC, el.prenom ASC";
        
        $stmt = $db->prepare($available_students_query);
        $stmt->execute([
            'ecole_id' => $_SESSION['ecole_id'],
            'class_id' => $class_id
        ]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des étudiants disponibles pour la classe {$class_id}: " . $e->getMessage());
        return [];
    }
}

/**
 * Valide l'accès à une classe (vérifie l'existence et les permissions)
 * 
 * @param int $class_id ID de la classe
 * @param PDO $db Instance de la base de données
 * @return array|false Détails de la classe ou false si accès refusé
 */
function validateClassAccess($class_id, $db = null) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    // Vérifier l'ID de la classe
    if (!$class_id || !is_numeric($class_id)) {
        setFlashMessage('error', 'ID de classe invalide.');
        return false;
    }
    
    // Récupérer les détails de la classe
    $classe = getClassDetails($class_id, $db);
    if (!$classe) {
        setFlashMessage('error', 'Classe non trouvée ou accès refusé.');
        return false;
    }
    
    return $classe;
}

/**
 * Met à jour l'effectif actuel d'une classe
 * 
 * @param int $class_id ID de la classe
 * @param PDO $db Instance de la base de données
 * @return bool True si la mise à jour a réussi
 */
function updateClassEffectif($class_id, $db = null) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $count = getClassStudentCount($class_id, $db, true);
        
        $update_query = "UPDATE classes SET effectif_actuel = :effectif WHERE id = :class_id";
        $stmt = $db->prepare($update_query);
        
        return $stmt->execute([
            'effectif' => $count,
            'class_id' => $class_id
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur lors de la mise à jour de l'effectif de la classe {$class_id}: " . $e->getMessage());
        return false;
    }
}

/**
 * Calcule les statistiques d'une classe
 * 
 * @param int $class_id ID de la classe
 * @param PDO $db Instance de la base de données
 * @return array Statistiques de la classe
 */
function getClassStatistics($class_id, $db = null) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $classe = getClassDetails($class_id, $db);
        if (!$classe) {
            return [];
        }
        
        $nombre_eleves = getClassStudentCount($class_id, $db, true);
        $capacite_percentage = $classe['capacite_max'] > 0 ? ($nombre_eleves / $classe['capacite_max']) * 100 : 0;
        
        // Déterminer la classe CSS pour la capacité
        if ($capacite_percentage >= 90) {
            $capacity_class = 'bg-danger';
        } elseif ($capacite_percentage >= 75) {
            $capacity_class = 'bg-warning';
        } else {
            $capacity_class = 'bg-success';
        }
        
        return [
            'nombre_eleves' => $nombre_eleves,
            'capacite_max' => $classe['capacite_max'],
            'capacite_percentage' => $capacite_percentage,
            'capacity_class' => $capacity_class,
            'classe' => $classe
        ];
        
    } catch (Exception $e) {
        error_log("Erreur lors du calcul des statistiques de la classe {$class_id}: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les classes d'un enseignant spécifique
 * 
 * @param int $enseignant_id ID de l'enseignant
 * @param PDO $db Instance de la base de données
 * @param bool $active_only Inclure seulement les classes actives
 * @return array Liste des classes
 */
function getTeacherClasses($enseignant_id, $db = null, $active_only = true) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $status_condition = $active_only ? "AND c.statut = 'actif' AND cc.statut = 'actif'" : "";
        
        $classes_query = "SELECT DISTINCT c.*, 
                                 COUNT(DISTINCT i.eleve_id) as nb_eleves,
                                 COUNT(DISTINCT cc.cours_id) as nb_cours
                          FROM classes c 
                          JOIN classe_cours cc ON c.id = cc.classe_id
                          LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut IN ('validée', 'en_cours')
                          WHERE cc.enseignant_id = :enseignant_id 
                          {$status_condition}
                          GROUP BY c.id
                          ORDER BY c.niveau, c.nom_classe";
        
        $stmt = $db->prepare($classes_query);
        $stmt->execute(['enseignant_id' => $enseignant_id]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des classes de l'enseignant {$enseignant_id}: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les cours d'un enseignant spécifique
 * 
 * @param int $enseignant_id ID de l'enseignant
 * @param PDO $db Instance de la base de données
 * @param bool $active_only Inclure seulement les cours actifs
 * @return array Liste des cours
 */
function getTeacherCourses($enseignant_id, $db = null, $active_only = true) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $status_condition = $active_only ? "AND cc.statut = 'actif' AND c.statut = 'actif' AND co.statut = 'actif'" : "";
        
        $cours_query = "SELECT cc.*, c.nom_classe, c.niveau, c.cycle, co.nom_cours, co.code_cours, co.coefficient,
                               e.nom as enseignant_nom, e.prenom as enseignant_prenom
                        FROM classe_cours cc
                        JOIN classes c ON cc.classe_id = c.id
                        JOIN cours co ON cc.cours_id = co.id
                        JOIN enseignants e ON cc.enseignant_id = e.id
                        WHERE cc.enseignant_id = :enseignant_id 
                        {$status_condition}
                        ORDER BY c.niveau, c.nom_classe, co.nom_cours";
        
        $stmt = $db->prepare($cours_query);
        $stmt->execute(['enseignant_id' => $enseignant_id]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des cours de l'enseignant {$enseignant_id}: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les classes et cours d'un enseignant connecté
 * 
 * @param PDO $db Instance de la base de données
 * @return array Tableau avec 'classes' et 'cours'
 */
function getCurrentTeacherData($db = null) {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        // Récupérer l'ID de l'enseignant
        $enseignant_query = "SELECT e.id FROM enseignants e 
                             JOIN utilisateurs u ON e.utilisateur_id = u.id 
                             WHERE u.id = :user_id AND e.ecole_id = :ecole_id AND e.statut = 'actif'";
        $stmt = $db->prepare($enseignant_query);
        $stmt->execute(['user_id' => $_SESSION['user_id'], 'ecole_id' => $_SESSION['ecole_id']]);
        $enseignant = $stmt->fetch();
        
        if (!$enseignant) {
            return ['classes' => [], 'cours' => []];
        }
        
        $classes = getTeacherClasses($enseignant['id'], $db);
        $cours = getTeacherCourses($enseignant['id'], $db);
        
        return [
            'classes' => $classes,
            'cours' => $cours,
            'enseignant_id' => $enseignant['id']
        ];
        
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des données de l'enseignant connecté: " . $e->getMessage());
        return ['classes' => [], 'cours' => []];
    }
}
?>
