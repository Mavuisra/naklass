<?php
/**
 * API pour récupérer les cours d'une classe (AJAX)
 */
require_once '../includes/functions.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier les permissions
if (!hasRole(['admin', 'direction', 'enseignant'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Vérifier les paramètres
if (!isset($_GET['classe_id']) || !is_numeric($_GET['classe_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de classe invalide']);
    exit;
}

$classe_id = (int)$_GET['classe_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier que la classe appartient à l'école de l'utilisateur
    $classe = validateClassAccess($classe_id, $db);
    if (!$classe) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Classe non trouvée']);
        exit;
    }
    
    // Récupérer les cours assignés à cette classe
    $cours_query = "SELECT c.id, c.nom_cours, c.code_cours, c.description,
                           e.prenom as enseignant_prenom, e.nom as enseignant_nom
                    FROM cours c
                    JOIN classe_cours cc ON c.id = cc.cours_id
                    LEFT JOIN enseignants e ON cc.enseignant_id = e.id
                    WHERE cc.classe_id = :classe_id
                    AND c.statut = 'actif'
                    ORDER BY c.nom_cours ASC";
    
    $stmt = $db->prepare($cours_query);
    $stmt->execute(['classe_id' => $classe_id]);
    $cours = $stmt->fetchAll();
    
    // Formater les données pour le JSON
    $formatted_cours = [];
    foreach ($cours as $c) {
        $formatted_cours[] = [
            'id' => $c['id'],
            'nom_cours' => $c['nom_cours'],
            'code_cours' => $c['code_cours'],
            'description' => $c['description'],
            'enseignant' => trim($c['enseignant_prenom'] . ' ' . $c['enseignant_nom'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'cours' => $formatted_cours,
        'count' => count($formatted_cours)
    ]);
    
} catch (Exception $e) {
    error_log("Erreur get_cours_for_classe: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>



