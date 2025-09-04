<?php
/**
 * Correction des données existantes pour permettre le calcul des moyennes
 * - Corrige les périodes vides dans les évaluations
 * - Valide les notes existantes
 * - Met à jour les bulletins avec les moyennes calculées
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'professeur']);

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = [];

// Traitement de la correction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'fix_data') {
        try {
            $db->beginTransaction();
            
            $fixes_applied = [];
            
            // 1. Corriger les périodes vides dans les évaluations
            $update_periodes = "UPDATE evaluations 
                               SET periode = 'première-periode' 
                               WHERE periode = '' OR periode IS NULL";
            $stmt = $db->prepare($update_periodes);
            $stmt->execute();
            $fixes_applied[] = "Périodes des évaluations corrigées : " . $stmt->rowCount();
            
            // 2. Valider toutes les notes existantes
            $validate_notes = "UPDATE notes 
                              SET validee = 1, 
                                  validee_par = :user_id,
                                  date_validation = NOW()
                              WHERE validee = 0";
            $stmt = $db->prepare($validate_notes);
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
            $fixes_applied[] = "Notes validées : " . $stmt->rowCount();
            
            // 3. Recalculer les moyennes pour tous les bulletins
            $bulletins_query = "SELECT b.*, e.nom, e.prenom, c.nom_classe
                               FROM bulletins b
                               JOIN eleves e ON b.eleve_id = e.id
                               JOIN classes c ON b.classe_id = c.id
                               WHERE c.ecole_id = :ecole_id 
                               AND b.statut = 'actif'";
            $stmt = $db->prepare($bulletins_query);
            $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
            $bulletins = $stmt->fetchAll();
            
            $bulletins_updated = 0;
            
            foreach ($bulletins as $bulletin) {
                // Calculer la moyenne pour ce bulletin
                $moyenne_query = "SELECT AVG(n.valeur) as moyenne, COUNT(n.id) as nb_evaluations
                                 FROM notes n
                                 JOIN evaluations ev ON n.evaluation_id = ev.id
                                 JOIN classe_cours cc ON ev.classe_cours_id = cc.id
                                 WHERE cc.classe_id = :classe_id 
                                 AND ev.periode = :periode
                                 AND n.eleve_id = :eleve_id
                                 AND n.absent = 0
                                 AND n.validee = 1";
                $moyenne_stmt = $db->prepare($moyenne_query);
                $moyenne_stmt->execute([
                    'classe_id' => $bulletin['classe_id'],
                    'periode' => $bulletin['periode'],
                    'eleve_id' => $bulletin['eleve_id']
                ]);
                $moyenne_data = $moyenne_stmt->fetch();
                
                $nouvelle_moyenne = ($moyenne_data['moyenne'] !== null && $moyenne_data['nb_evaluations'] > 0) 
                                   ? round($moyenne_data['moyenne'], 2) 
                                   : null;
                
                // Mettre à jour le bulletin
                $update_query = "UPDATE bulletins 
                                SET moyenne_generale = :moyenne_generale, 
                                    updated_at = NOW(),
                                    updated_by = :updated_by
                                WHERE id = :bulletin_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    'moyenne_generale' => $nouvelle_moyenne,
                    'updated_by' => $_SESSION['user_id'],
                    'bulletin_id' => $bulletin['id']
                ]);
                
                $bulletins_updated++;
            }
            
            $fixes_applied[] = "Bulletins mis à jour : $bulletins_updated";
            
            $db->commit();
            $success = array_merge($success, $fixes_applied);
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "Erreur lors de la correction : " . $e->getMessage();
        }
    }
}

// Récupérer les statistiques
$stats = [];
try {
    // Évaluations avec périodes vides
    $empty_periodes = "SELECT COUNT(*) as count FROM evaluations e
                      JOIN classe_cours cc ON e.classe_cours_id = cc.id
                      JOIN classes c ON cc.classe_id = c.id
                      WHERE c.ecole_id = :ecole_id 
                      AND (e.periode = '' OR e.periode IS NULL)";
    $stmt = $db->prepare($empty_periodes);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['empty_periodes'] = $stmt->fetch()['count'];
    
    // Notes non validées
    $unvalidated_notes = "SELECT COUNT(*) as count FROM notes n
                         JOIN evaluations ev ON n.evaluation_id = ev.id
                         JOIN classe_cours cc ON ev.classe_cours_id = cc.id
                         JOIN classes c ON cc.classe_id = c.id
                         WHERE c.ecole_id = :ecole_id 
                         AND n.validee = 0";
    $stmt = $db->prepare($unvalidated_notes);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['unvalidated_notes'] = $stmt->fetch()['count'];
    
    // Bulletins avec moyennes NULL
    $null_moyennes = "SELECT COUNT(*) as count FROM bulletins b
                     JOIN classes c ON b.classe_id = c.id
                     WHERE c.ecole_id = :ecole_id 
                     AND b.moyenne_generale IS NULL
                     AND b.statut = 'actif'";
    $stmt = $db->prepare($null_moyennes);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['null_moyennes'] = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    $errors[] = "Erreur lors de la récupération des statistiques : " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction des données - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="bi bi-tools me-2"></i>Correction des données existantes</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <?php foreach ($errors as $error): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <?php foreach ($success as $msg): ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($msg); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Statistiques -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['empty_periodes'] ?? 0; ?></h3>
                                        <p class="mb-0">Évaluations sans période</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['unvalidated_notes'] ?? 0; ?></h3>
                                        <p class="mb-0">Notes non validées</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['null_moyennes'] ?? 0; ?></h3>
                                        <p class="mb-0">Bulletins sans moyenne</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>Problèmes identifiés dans la base de données :</h6>
                            <ul class="mb-0">
                                <li><strong>Évaluations sans période :</strong> Les évaluations ont des périodes vides, ce qui empêche le calcul des moyennes</li>
                                <li><strong>Notes non validées :</strong> Les notes existent mais ne sont pas marquées comme validées</li>
                                <li><strong>Bulletins sans moyenne :</strong> Les bulletins sont générés mais les moyennes restent NULL</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle me-2"></i>Ce script va :</h6>
                            <ul class="mb-0">
                                <li>Corriger les périodes vides dans les évaluations (définir comme "première-periode")</li>
                                <li>Valider toutes les notes existantes</li>
                                <li>Recalculer et mettre à jour les moyennes de tous les bulletins</li>
                            </ul>
                        </div>
                        
                        <?php if (($stats['empty_periodes'] ?? 0) > 0 || ($stats['unvalidated_notes'] ?? 0) > 0 || ($stats['null_moyennes'] ?? 0) > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="fix_data">
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-warning btn-lg">
                                        <i class="bi bi-tools me-2"></i>Corriger les données
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                Toutes les données sont correctes ! Les moyennes devraient s'afficher normalement.
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-flex gap-2">
                            <a href="check_notes_data.php" class="btn btn-outline-info">
                                <i class="bi bi-search me-2"></i>Vérifier les données
                            </a>
                            <a href="bulletins.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Retour aux bulletins
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

