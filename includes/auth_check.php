<?php
/**
 * Vérification de l'authentification et des permissions
 * Fonctions utilitaires pour la sécurité des pages
 */

// Démarrer la session si pas encore démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 * @param string $role Le rôle à vérifier
 * @return bool
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'] ?? $_SESSION['role_code'] ?? $_SESSION['role'] ?? '';
    return $user_role === $role;
}

/**
 * Vérifie si l'utilisateur a au moins un des rôles spécifiés
 * @param array $roles Liste des rôles autorisés
 * @return bool
 */
function hasPermission($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'] ?? $_SESSION['role_code'] ?? $_SESSION['role'] ?? '';
    
    if (is_array($roles)) {
        return in_array($user_role, $roles);
    }
    
    return $user_role === $roles;
}

/**
 * Redirige vers la page de connexion si l'utilisateur n'est pas connecté
 * @param string $redirect_url URL de redirection (optionnel)
 */
function requireLogin($redirect_url = null) {
    if (!isLoggedIn()) {
        $redirect = $redirect_url ?: 'auth/login.php';
        header("Location: $redirect");
        exit();
    }
}

/**
 * Redirige vers la page de connexion si l'utilisateur n'a pas les permissions
 * @param array|string $roles Rôles requis
 * @param string $redirect_url URL de redirection (optionnel)
 */
function requirePermission($roles, $redirect_url = null) {
    if (!hasPermission($roles)) {
        $redirect = $redirect_url ?: 'auth/login.php';
        header("Location: $redirect");
        exit();
    }
}

/**
 * Récupère l'ID de l'utilisateur connecté
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Récupère le rôle de l'utilisateur connecté
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? $_SESSION['role_code'] ?? $_SESSION['role'] ?? null;
}

/**
 * Récupère l'ID de l'école de l'utilisateur connecté
 * @return int|null
 */
function getCurrentUserEcoleId() {
    return $_SESSION['ecole_id'] ?? null;
}

/**
 * Vérifie si l'utilisateur est administrateur
 * @return bool
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Vérifie si l'utilisateur est direction
 * @return bool
 */
function isDirection() {
    return hasRole('direction');
}

/**
 * Vérifie si l'utilisateur est enseignant
 * @return bool
 */
function isEnseignant() {
    return hasRole('enseignant');
}

/**
 * Vérifie si l'utilisateur est secrétaire
 * @return bool
 */
function isSecretaire() {
    return hasRole('secretaire');
}

/**
 * Vérifie si l'utilisateur est caissier
 * @return bool
 */
function isCaissier() {
    return hasRole('caissier');
}

/**
 * Vérifie si l'utilisateur peut accéder aux rapports
 * @return bool
 */
function canAccessReports() {
    return hasPermission(['admin', 'direction', 'secretaire']);
}

/**
 * Vérifie si l'utilisateur peut gérer les classes
 * @return bool
 */
function canManageClasses() {
    return hasPermission(['admin', 'direction', 'secretaire']);
}

/**
 * Vérifie si l'utilisateur peut gérer les finances
 * @return bool
 */
function canManageFinances() {
    return hasPermission(['admin', 'direction', 'caissier']);
}

/**
 * Vérifie si l'utilisateur peut gérer les notes
 * @return bool
 */
function canManageGrades() {
    return hasPermission(['admin', 'direction', 'enseignant']);
}

/**
 * Vérifie si l'utilisateur peut gérer la présence
 * @return bool
 */
function canManagePresence() {
    return hasPermission(['admin', 'direction', 'enseignant']);
}

/**
 * Vérifie si l'utilisateur peut gérer les utilisateurs
 * @return bool
 */
function canManageUsers() {
    return hasPermission(['admin', 'direction']);
}

/**
 * Génère un token CSRF
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie un token CSRF
 * @param string $token Le token à vérifier
 * @return bool
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Nettoie et valide les données d'entrée
 * @param mixed $data Les données à nettoyer
 * @return mixed Les données nettoyées
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    if (is_string($data)) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}

/**
 * Vérifie si la requête est en AJAX
 * @return bool
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Retourne une réponse JSON avec le bon header
 * @param mixed $data Les données à encoder
 * @param int $status_code Le code de statut HTTP
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Retourne une réponse d'erreur JSON
 * @param string $message Le message d'erreur
 * @param int $status_code Le code de statut HTTP
 */
function jsonError($message, $status_code = 400) {
    jsonResponse(['error' => $message], $status_code);
}

/**
 * Retourne une réponse de succès JSON
 * @param mixed $data Les données de succès
 * @param string $message Le message de succès
 */
function jsonSuccess($data = null, $message = 'Opération réussie') {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response);
}

/**
 * Log une action de l'utilisateur
 * @param string $action L'action effectuée
 * @param string $details Détails supplémentaires
 */
function logUserAction($action, $details = '') {
    if (!isLoggedIn()) {
        return;
    }
    
    $user_id = getCurrentUserId();
    $user_role = getCurrentUserRole();
    $ecole_id = getCurrentUserEcoleId();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    // Ici vous pouvez implémenter la logique de logging
    // Par exemple, écrire dans un fichier ou une base de données
    $log_entry = "[$timestamp] User ID: $user_id, Role: $user_role, Ecole: $ecole_id, Action: $action, Details: $details, IP: $ip, UA: $user_agent\n";
    
    $log_file = __DIR__ . '/../logs/user_actions.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Vérifie si l'utilisateur a accès à une école spécifique
 * @param int $ecole_id L'ID de l'école
 * @return bool
 */
function canAccessEcole($ecole_id) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Les administrateurs peuvent accéder à toutes les écoles
    if (isAdmin()) {
        return true;
    }
    
    // Les autres utilisateurs ne peuvent accéder qu'à leur école
    $user_ecole_id = getCurrentUserEcoleId();
    return $user_ecole_id == $ecole_id;
}

/**
 * Vérifie si l'utilisateur peut modifier une ressource
 * @param string $resource_type Le type de ressource
 * @param mixed $resource_data Les données de la ressource
 * @return bool
 */
function canModifyResource($resource_type, $resource_data) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Les administrateurs peuvent tout modifier
    if (isAdmin()) {
        return true;
    }
    
    // Les directions peuvent modifier dans leur école
    if (isDirection()) {
        $user_ecole_id = getCurrentUserEcoleId();
        $resource_ecole_id = $resource_data['ecole_id'] ?? null;
        return $user_ecole_id == $resource_ecole_id;
    }
    
    // Les enseignants peuvent modifier leurs propres ressources
    if (isEnseignant()) {
        $user_id = getCurrentUserId();
        $resource_user_id = $resource_data['user_id'] ?? $resource_data['enseignant_id'] ?? null;
        return $user_id == $resource_user_id;
    }
    
    return false;
}
?>
