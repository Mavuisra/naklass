<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$page_title = "Vérification des Colonnes de la Table Inscriptions";
$errors = [];
$success = [];

// Récupérer l'ID de l'école
$ecole_id = $_SESSION['ecole_id'] ?? null;

if (!$ecole_id) {
    $errors[] = "Aucun ID d'école trouvé dans la session";
}

// Vérifier la structure de la table inscriptions
try {
    $success[] = "=== STRUCTURE DE LA TABLE INSCRIPTIONS ===";
    
    // Décrire la table inscriptions
    $columns = $db->query("DESCRIBE inscriptions")->fetchAll(PDO::FETCH_ASSOC);
    $success[] = "Colonnes de la table 'inscriptions':";
    
    foreach ($columns as $column) {
        $success[] = "  - " . $column['Field'] . " (" . $column['Type'] . ") " . 
                    ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . 
                    ($column['Default'] ? " DEFAULT " . $column['Default'] : "");
    }
    
    // Vérifier les données existantes
    $success[] = "";
    $success[] = "=== DONNÉES EXISTANTES ===";
    
    $all_data = $db->query("SELECT * FROM inscriptions ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $success[] = "Données récentes dans la table inscriptions:";
    
    foreach ($all_data as $index => $row) {
        $success[] = "--- Inscription " . ($index + 1) . " ---";
        foreach ($row as $key => $value) {
            $success[] = "  $key: " . ($value ?? 'NULL');
        }
        $success[] = "";
    }
    
    // Vérifier les valeurs uniques dans les colonnes statut
    $success[] = "=== VALEURS UNIQUES DANS LES COLONNES STATUT ===";
    
    // Vérifier la colonne 'statut' si elle existe
    $statut_values = $db->query("SELECT DISTINCT statut FROM inscriptions WHERE statut IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $success[] = "Valeurs uniques dans la colonne 'statut': " . implode(', ', $statut_values ?: ['Aucune']);
    
    // Vérifier la colonne 'statut_inscription' si elle existe
    try {
        $statut_inscription_values = $db->query("SELECT DISTINCT statut_inscription FROM inscriptions WHERE statut_inscription IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $success[] = "Valeurs uniques dans la colonne 'statut_inscription': " . implode(', ', $statut_inscription_values ?: ['Aucune']);
    } catch (Exception $e) {
        $success[] = "Colonne 'statut_inscription' n'existe pas ou erreur: " . $e->getMessage();
    }
    
    // Vérifier les inscriptions avec l'élève mandongo glody
    $success[] = "";
    $success[] = "=== INSCRIPTIONS DE L'ÉLÈVE MANDONGO GLODY ===";
    
    $mandongo_inscriptions = $db->query("
        SELECT i.*, e.nom, e.prenom
        FROM inscriptions i
        JOIN eleves e ON i.eleve_id = e.id
        WHERE e.nom = 'mandongo' AND e.prenom = 'glody'
        ORDER BY i.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($mandongo_inscriptions)) {
        $success[] = "Aucune inscription trouvée pour mandongo glody";
    } else {
        $success[] = "Inscriptions trouvées pour mandongo glody: " . count($mandongo_inscriptions);
        foreach ($mandongo_inscriptions as $inscription) {
            $success[] = "  - ID: " . $inscription['id'] . 
                        ", Classe: " . $inscription['classe_id'] . 
                        ", Statut: " . ($inscription['statut'] ?? 'NULL') . 
                        ", Statut_inscription: " . ($inscription['statut_inscription'] ?? 'NULL') . 
                        ", Année: " . $inscription['annee_scolaire'];
        }
    }
    
    // Vérifier les classes disponibles
    $success[] = "";
    $success[] = "=== CLASSES DISPONIBLES ===";
    
    $classes = $db->query("SELECT id, nom_classe, niveau, cycle, annee_scolaire FROM classes WHERE ecole_id = $ecole_id")->fetchAll(PDO::FETCH_ASSOC);
    $success[] = "Classes dans l'école: " . count($classes);
    
    foreach ($classes as $classe) {
        $success[] = "  - ID: " . $classe['id'] . 
                    ", Nom: " . $classe['nom_classe'] . 
                    ", Niveau: " . $classe['niveau'] . 
                    ", Cycle: " . $classe['cycle'] . 
                    ", Année: " . $classe['annee_scolaire'];
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur lors de la vérification: " . $e->getMessage();
}

// Test de la requête corrigée
if ($ecole_id) {
    try {
        $success[] = "";
        $success[] = "=== TEST DE LA REQUÊTE CORRIGÉE ===";
        
        // Tester avec la colonne statut
        $test_query1 = "SELECT DISTINCT e.*, 
                               c.nom_classe, c.niveau, c.cycle,
                               i.annee_scolaire,
                               i.statut as statut_inscription,
                               COUNT(DISTINCT t.id) as nb_tuteurs
                        FROM eleves e 
                        LEFT JOIN (
                            SELECT eleve_id, classe_id, annee_scolaire, statut
                            FROM inscriptions 
                            WHERE statut IN ('validée', 'en_cours')
                            ORDER BY created_at DESC
                        ) i ON e.id = i.eleve_id
                        LEFT JOIN classes c ON i.classe_id = c.id
                        LEFT JOIN eleve_tuteurs et ON e.id = et.eleve_id
                        LEFT JOIN tuteurs t ON et.tuteur_id = t.id
                        WHERE e.ecole_id = $ecole_id
                        GROUP BY e.id
                        ORDER BY e.nom, e.prenom
                        LIMIT 3";
        
        $test_stmt1 = $db->query($test_query1);
        $test_results1 = $test_stmt1->fetchAll(PDO::FETCH_ASSOC);
        
        $success[] = "Test avec colonne 'statut': " . count($test_results1) . " résultats";
        
        foreach ($test_results1 as $index => $student) {
            $success[] = "  Élève " . ($index + 1) . ": " . $student['nom'] . " " . $student['prenom'];
            $success[] = "    Classe: " . ($student['nom_classe'] ?? 'NULL');
            $success[] = "    Statut inscription: " . ($student['statut_inscription'] ?? 'NULL');
            $success[] = "    Tuteurs: " . $student['nb_tuteurs'];
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur lors du test de la requête: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="bi bi-database me-2"></i><?php echo $page_title; ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h5><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées:</h5>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-info">
                                <h5><i class="bi bi-info-circle me-2"></i>Résultats de la vérification:</h5>
                                <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.9em;"><?php foreach ($success as $message): ?><?php echo htmlspecialchars($message) . "\n"; ?><?php endforeach; ?></pre>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="index.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-2"></i>Retour à la liste des étudiants
                            </a>
                            <a href="fix_missing_inscriptions.php" class="btn btn-warning ms-2">
                                <i class="bi bi-wrench me-2"></i>Corriger les inscriptions
                            </a>
                            <a href="debug_table_data.php" class="btn btn-info ms-2">
                                <i class="bi bi-bug me-2"></i>Diagnostic complet
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


