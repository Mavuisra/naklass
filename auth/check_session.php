<?php
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (isLoggedIn()) {
    // Vérifier l'expiration de la session
    $last_activity = $_SESSION['last_activity'] ?? 0;
    $session_lifetime = SESSION_LIFETIME;
    
    if (time() - $last_activity > $session_lifetime) {
        // Session expirée - rediriger vers la page d'accueil
        session_destroy();
        redirect('../index.php');
    } else {
        // Mettre à jour le timestamp d'activité
        $_SESSION['last_activity'] = time();
        
        // Retourner une réponse JSON valide pour les vérifications AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['valid' => true]);
        }
    }
} else {
    // Non connecté - rediriger vers la page d'accueil
    redirect('../index.php');
}
?>
