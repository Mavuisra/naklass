<?php
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (isLoggedIn()) {
    // Log de déconnexion
    logUserAction('LOGOUT', 'Déconnexion utilisateur');
    
    // Détruire la session si elle est active
    safeSessionDestroy();
    
    // Supprimer les cookies de session si ils existent
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

// Rediriger vers la page de connexion
redirect('login.php');
?>
