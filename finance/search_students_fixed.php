<?php
/**
 * Fichier de recherche d'élèves corrigé
 * Version corrigée pour résoudre les problèmes de noms de champs
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'caissier']);

// Vérifier la configuration de l'école
requireSchoolSetup();

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

try {
    // Récupérer les données JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $query = sanitize($input['query'] ?? '');
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Requête trop courte']);
        exit;
    }
    
    // Rechercher les élèves avec leurs classes - REQUÊTE CORRIGÉE
    $search_query = "SELECT e.id, e.matricule, e.nom, e.prenom,
                            c.nom_classe, 
                            c.niveau,
                            c.cycle,
                            i.annee_scolaire
                     FROM eleves e 
                     LEFT JOIN inscriptions i ON e.id = i.eleve_id 
                          AND i.statut IN ('validée', 'en_cours')
                     LEFT JOIN classes c ON i.classe_id = c.id
                     WHERE e.ecole_id = ? 
                     AND e.statut = 'actif'
                     AND (e.nom LIKE ? 
                          OR e.prenom LIKE ? 
                          OR e.matricule LIKE ?)
                     GROUP BY e.id
                     ORDER BY e.nom, e.prenom
                     LIMIT 10";
    
    $stmt = $db->prepare($search_query);
    $search_term = "%$query%";
    $stmt->execute([
        $_SESSION['ecole_id'],
        $search_term,
        $search_term,
        $search_term
    ]);
    
    $students = $stmt->fetchAll();
    
    // Formater les résultats - LOGIQUE CORRIGÉE
    $results = [];
    foreach ($students as $student) {
        // Construire l'information de classe de manière plus robuste
        $classe_info = '';
        if ($student['nom_classe']) {
            if ($student['niveau'] && $student['cycle']) {
                $classe_info = $student['niveau'] . ' - ' . $student['nom_classe'];
            } elseif ($student['niveau']) {
                $classe_info = $student['niveau'] . ' - ' . $student['nom_classe'];
            } elseif ($student['cycle']) {
                $classe_info = $student['cycle'] . ' - ' . $student['nom_classe'];
            } else {
                $classe_info = $student['nom_classe'];
            }
        }
        
        // Ajouter l'année scolaire si disponible
        if ($student['annee_scolaire']) {
            $classe_info .= ' (' . $student['annee_scolaire'] . ')';
        }
        
        $results[] = [
            'id' => $student['id'],
            'matricule' => $student['matricule'],
            'nom' => $student['nom'],
            'prenom' => $student['prenom'],
            'classe' => $classe_info,
            'reste' => 0, // À calculer séparément si nécessaire
            'total_paye' => 0 // À calculer séparément si nécessaire
        ];
    }
    
    echo json_encode([
        'success' => true,
        'students' => $results,
        'count' => count($results),
        'query' => $query, // Pour le débogage
        'debug' => [
            'total_results' => count($results),
            'search_term' => $search_term,
            'ecole_id' => $_SESSION['ecole_id']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la recherche: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
