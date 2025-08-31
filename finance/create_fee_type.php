<?php
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
    
    if (!$input) {
        throw new Exception('Données invalides');
    }
    
    // Validation des données
    $libelle = sanitize($input['libelle'] ?? '');
    $code = sanitize($input['code'] ?? '');
    $montant_standard = floatval($input['montant_standard'] ?? 0);
    $monnaie = sanitize($input['monnaie'] ?? 'CDF');
    $type_recurrence = sanitize($input['type_recurrence'] ?? 'unique');
    $description = sanitize($input['description'] ?? '');
    
    if (empty($libelle)) {
        throw new Exception('Le libellé est obligatoire');
    }
    
    // Vérifier que le libellé n'existe pas déjà pour cette école
    $check_query = "SELECT id FROM types_frais WHERE ecole_id = :ecole_id AND libelle = :libelle AND statut = 'actif'";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([
        'ecole_id' => $_SESSION['ecole_id'],
        'libelle' => $libelle
    ]);
    
    if ($check_stmt->fetch()) {
        throw new Exception('Un type de frais avec ce libellé existe déjà');
    }
    
    // Générer un code automatique si non fourni
    if (empty($code)) {
        $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $libelle), 0, 8));
        
        // Vérifier l'unicité du code
        $code_check_query = "SELECT id FROM types_frais WHERE ecole_id = :ecole_id AND code = :code AND statut = 'actif'";
        $code_check_stmt = $db->prepare($code_check_query);
        $code_check_stmt->execute([
            'ecole_id' => $_SESSION['ecole_id'],
            'code' => $code
        ]);
        
        if ($code_check_stmt->fetch()) {
            $code = $code . '_' . time();
        }
    }
    
    // Insérer le nouveau type de frais
    $insert_query = "INSERT INTO types_frais (
        ecole_id, code_frais, libelle, description, montant_defaut, monnaie, 
        type_recurrence, obligatoire, statut, created_by
    ) VALUES (
        :ecole_id, :code_frais, :libelle, :description, :montant_defaut, :monnaie,
        :type_recurrence, 1, 'actif', :created_by
    )";
    
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->execute([
        'ecole_id' => $_SESSION['ecole_id'],
        'code_frais' => $code,
        'libelle' => $libelle,
        'description' => $description,
        'montant_defaut' => $montant_standard,
        'monnaie' => $monnaie,
        'type_recurrence' => $type_recurrence,
        'created_by' => $_SESSION['user_id']
    ]);
    
    $fee_type_id = $db->lastInsertId();
    
    // Log de l'action
    logUserAction('CREATE_FEE_TYPE', "Nouveau type de frais créé: $libelle (ID: $fee_type_id)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Type de frais créé avec succès',
        'fee_type_id' => $fee_type_id,
        'fee_type' => [
            'id' => $fee_type_id,
            'libelle' => $libelle,
            'code' => $code,
            'montant_standard' => $montant_standard,
            'monnaie' => $monnaie,
            'type_recurrence' => $type_recurrence
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
