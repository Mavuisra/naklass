<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$page_title = "Correction des Inscriptions Manquantes";
$errors = [];
$success = [];
$actions = [];

// Récupérer l'ID de l'école
$ecole_id = $_SESSION['ecole_id'] ?? null;

if (!$ecole_id) {
    $errors[] = "Aucun ID d'école trouvé dans la session";
}

// Diagnostic détaillé des inscriptions
if ($ecole_id) {
    try {
        $success[] = "=== DIAGNOSTIC DES INSCRIPTIONS ===";
        
        // 1. Vérifier toutes les inscriptions (tous statuts)
        $all_inscriptions = $db->query("
            SELECT i.*, e.nom, e.prenom, c.nom_classe, c.niveau, c.cycle
            FROM inscriptions i 
            JOIN eleves e ON i.eleve_id = e.id 
            LEFT JOIN classes c ON i.classe_id = c.id
            WHERE e.ecole_id = $ecole_id 
            ORDER BY i.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $success[] = "Total inscriptions (tous statuts): " . count($all_inscriptions);
        
        foreach ($all_inscriptions as $inscription) {
            $success[] = "  - " . $inscription['nom'] . " " . $inscription['prenom'] . 
                        " → Classe: " . ($inscription['nom_classe'] ?? 'ID:' . $inscription['classe_id']) . 
                        " (Statut: " . $inscription['statut'] . ", Année: " . $inscription['annee_scolaire'] . ")";
        }
        
        // 2. Vérifier les inscriptions avec statut 'validée' ou 'en_cours'
        $valid_inscriptions = $db->query("
            SELECT i.*, e.nom, e.prenom, c.nom_classe, c.niveau, c.cycle
            FROM inscriptions i 
            JOIN eleves e ON i.eleve_id = e.id 
            LEFT JOIN classes c ON i.classe_id = c.id
            WHERE e.ecole_id = $ecole_id 
            AND i.statut IN ('validée', 'en_cours')
            ORDER BY i.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $success[] = "Inscriptions valides (validée/en_cours): " . count($valid_inscriptions);
        
        // 3. Vérifier les élèves sans inscriptions valides
        $eleves_sans_inscription = $db->query("
            SELECT e.*, 
                   COUNT(i.id) as nb_inscriptions,
                   GROUP_CONCAT(i.statut) as statuts_inscriptions
            FROM eleves e
            LEFT JOIN inscriptions i ON e.id = i.eleve_id
            WHERE e.ecole_id = $ecole_id
            GROUP BY e.id
            HAVING nb_inscriptions = 0 OR nb_inscriptions IS NULL
            ORDER BY e.nom, e.prenom
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $success[] = "Élèves sans inscriptions valides: " . count($eleves_sans_inscription);
        
        foreach ($eleves_sans_inscription as $eleve) {
            $success[] = "  - " . $eleve['nom'] . " " . $eleve['prenom'] . 
                        " (ID: " . $eleve['id'] . ", Statut scolaire: " . $eleve['statut_scolaire'] . ")";
        }
        
        // 4. Vérifier les classes disponibles
        $classes_disponibles = $db->query("
            SELECT id, nom_classe, niveau, cycle, annee_scolaire, effectif_actuel, capacite_max
            FROM classes 
            WHERE ecole_id = $ecole_id AND statut = 'actif'
            ORDER BY niveau, nom_classe
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $success[] = "Classes disponibles: " . count($classes_disponibles);
        
        foreach ($classes_disponibles as $classe) {
            $success[] = "  - " . $classe['nom_classe'] . 
                        " (Niveau: " . $classe['niveau'] . 
                        ", Cycle: " . $classe['cycle'] . 
                        ", Année: " . $classe['annee_scolaire'] . 
                        ", Effectif: " . $classe['effectif_actuel'] . "/" . $classe['capacite_max'] . ")";
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur lors du diagnostic: " . $e->getMessage();
    }
}

// Actions de correction
if ($ecole_id && !empty($_POST['action'])) {
    try {
        $action = $_POST['action'];
        
        if ($action === 'create_inscription' && !empty($_POST['eleve_id']) && !empty($_POST['classe_id'])) {
            $eleve_id = intval($_POST['eleve_id']);
            $classe_id = intval($_POST['classe_id']);
            $annee_scolaire = $_POST['annee_scolaire'] ?? '2024-2025';
            
            // Créer l'inscription
            $insert_query = "INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire, date_inscription, statut, statut_inscription, created_at, updated_at, created_by, statut_record) 
                           VALUES (:eleve_id, :classe_id, :annee_scolaire, CURDATE(), 'actif', 'validée', NOW(), NOW(), :created_by, 'actif')";
            
            $stmt = $db->prepare($insert_query);
            $stmt->execute([
                'eleve_id' => $eleve_id,
                'classe_id' => $classe_id,
                'annee_scolaire' => $annee_scolaire,
                'created_by' => $_SESSION['user_id'] ?? 1
            ]);
            
            $actions[] = "✓ Inscription créée pour l'élève ID $eleve_id dans la classe ID $classe_id";
            
        } elseif ($action === 'fix_inscription_status' && !empty($_POST['inscription_id'])) {
            $inscription_id = intval($_POST['inscription_id']);
            $new_status = $_POST['new_status'] ?? 'validée';
            
            // Corriger le statut de l'inscription
            $update_query = "UPDATE inscriptions SET statut_inscription = :new_status, updated_at = NOW() WHERE id = :inscription_id";
            $stmt = $db->prepare($update_query);
            $stmt->execute([
                'new_status' => $new_status,
                'inscription_id' => $inscription_id
            ]);
            
            $actions[] = "✓ Statut de l'inscription ID $inscription_id mis à jour vers '$new_status'";
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur lors de l'action de correction: " . $e->getMessage();
    }
}

// Recharger les données après correction
if (!empty($actions)) {
    try {
        $success[] = "=== DONNÉES APRÈS CORRECTION ===";
        
        // Vérifier les inscriptions valides après correction
        $inscriptions_apres = $db->query("
            SELECT i.*, e.nom, e.prenom, c.nom_classe, c.niveau, c.cycle
            FROM inscriptions i 
            JOIN eleves e ON i.eleve_id = e.id 
            LEFT JOIN classes c ON i.classe_id = c.id
            WHERE e.ecole_id = $ecole_id 
            AND i.statut_inscription IN ('validée', 'en_cours')
            ORDER BY i.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $success[] = "Inscriptions valides après correction: " . count($inscriptions_apres);
        
        foreach ($inscriptions_apres as $inscription) {
            $success[] = "  - " . $inscription['nom'] . " " . $inscription['prenom'] . 
                        " → " . ($inscription['nom_classe'] ?? 'Classe ID:' . $inscription['classe_id']) . 
                        " (Statut: " . $inscription['statut_inscription'] . ")";
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur lors de la vérification après correction: " . $e->getMessage();
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
                        <h4><i class="bi bi-wrench me-2"></i><?php echo $page_title; ?></h4>
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
                        
                        <?php if (!empty($actions)): ?>
                            <div class="alert alert-success">
                                <h5><i class="bi bi-check-circle me-2"></i>Actions effectuées:</h5>
                                <ul class="mb-0">
                                    <?php foreach ($actions as $action): ?>
                                        <li><?php echo htmlspecialchars($action); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-info">
                                <h5><i class="bi bi-info-circle me-2"></i>Résultats du diagnostic:</h5>
                                <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.9em;"><?php foreach ($success as $message): ?><?php echo htmlspecialchars($message) . "\n"; ?><?php endforeach; ?></pre>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Formulaire de création d'inscription -->
                        <?php if ($ecole_id && !empty($eleves_sans_inscription) && !empty($classes_disponibles)): ?>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6><i class="bi bi-plus-circle me-2"></i>Créer une inscription manquante</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="row g-3">
                                        <input type="hidden" name="action" value="create_inscription">
                                        
                                        <div class="col-md-4">
                                            <label for="eleve_id" class="form-label">Élève</label>
                                            <select class="form-select" id="eleve_id" name="eleve_id" required>
                                                <option value="">Sélectionner un élève</option>
                                                <?php foreach ($eleves_sans_inscription as $eleve): ?>
                                                    <option value="<?php echo $eleve['id']; ?>">
                                                        <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="classe_id" class="form-label">Classe</label>
                                            <select class="form-select" id="classe_id" name="classe_id" required>
                                                <option value="">Sélectionner une classe</option>
                                                <?php foreach ($classes_disponibles as $classe): ?>
                                                    <option value="<?php echo $classe['id']; ?>">
                                                        <?php echo htmlspecialchars($classe['nom_classe'] . ' (' . $classe['niveau'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="annee_scolaire" class="form-label">Année scolaire</label>
                                            <input type="text" class="form-control" id="annee_scolaire" name="annee_scolaire" 
                                                   value="2024-2025" placeholder="2024-2025">
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-plus-circle me-2"></i>Créer l'inscription
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="index.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-2"></i>Retour à la liste des étudiants
                            </a>
                            <a href="debug_table_data.php" class="btn btn-info ms-2">
                                <i class="bi bi-bug me-2"></i>Diagnostic complet
                            </a>
                            <a href="test_table_query.php" class="btn btn-warning ms-2">
                                <i class="bi bi-search me-2"></i>Tester la requête
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


