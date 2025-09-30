<?php
/**
 * AJAX: Marquer toutes les notifications comme lues
 */

require_once '../includes/functions.php';
require_once '../includes/NotificationManager.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $notificationManager = new NotificationManager();
    $success = $notificationManager->markAllAsRead();
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Toutes les notifications ont été marquées comme lues']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors du marquage']);
    }
    
} catch (Exception $e) {
    error_log("Erreur AJAX marquage toutes notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
