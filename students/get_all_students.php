<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

try {
    // Récupérer tous les élèves inscrits de l'école
    $query = "SELECT DISTINCT e.id, e.prenom, e.nom, e.matricule, e.statut
              FROM eleves e
              JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'validée'
              WHERE e.ecole_id = :ecole_id AND e.statut = 'actif'
              ORDER BY e.nom, e.prenom";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $students = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'count' => count($students)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des élèves',
        'message' => $e->getMessage()
    ]);
}
?>

