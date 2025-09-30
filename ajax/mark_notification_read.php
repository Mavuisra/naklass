<?php
/**
 * AJAX: Marquer une notification comme lue
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

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['notification_id']) || !is_numeric($input['notification_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de notification invalide']);
    exit;
}

try {
    $notificationManager = new NotificationManager();
    $success = $notificationManager->markAsRead($input['notification_id']);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Notification marquée comme lue']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors du marquage']);
    }
    
} catch (Exception $e) {
    error_log("Erreur AJAX marquage notification: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
