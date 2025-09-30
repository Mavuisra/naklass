<?php
/**
 * AJAX: Obtenir le nombre de notifications non lues
 */

require_once '../includes/functions.php';
require_once '../includes/NotificationManager.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

try {
    $notificationManager = new NotificationManager();
    $count = $notificationManager->getUnreadCount();
    
    echo json_encode(['success' => true, 'count' => $count]);
    
} catch (Exception $e) {
    error_log("Erreur AJAX comptage notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur', 'count' => 0]);
}
?>
