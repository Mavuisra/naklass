<?php
/**
 * Script de correction complète des bulletins
 * Exécute toutes les corrections nécessaires en une seule fois
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'professeur']);

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = [];

// Traitement de la correction complète
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'fix_all') {
        try {
            $db->beginTransaction();
            
            $fixes_applied = [];
            $total_fixes = 0;
            
            // 1. Corriger les périodes vides dans les évaluations
            $update_periodes = "UPDATE evaluations 
                               SET periode = 'première-periode' 
                               WHERE periode = '' OR periode IS NULL";
            $stmt = $db->prepare($update_periodes);
            $stmt->execute();
            $periodes_fixed = $stmt->rowCount();
            $fixes_applied[] = "✅ Périodes des évaluations corrigées : $periodes_fixed";
            $total_fixes += $periodes_fixed;
            
            // 2. Valider toutes les notes existantes
            $validate_notes = "UPDATE notes 
                              SET validee = 1, 
                                  validee_par = :user_id,
                                  date_validation = NOW()
                              WHERE validee = 0";
            $stmt = $db->prepare($validate_notes);
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
            $notes_validated = $stmt->rowCount();
            $fixes_applied[] = "✅ Notes validées : $notes_validated";
            $total_fixes += $notes_validated;
            
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
                // Calculer la moyenne générale pour ce bulletin
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
            
            $fixes_applied[] = "✅ Bulletins mis à jour : $bulletins_updated";
            $total_fixes += $bulletins_updated;
            
            // 4. Corriger les lignes de bulletin (moyennes par matière)
            $lignes_query = "SELECT bl.*, b.eleve_id, b.classe_id, b.periode, c.nom_cours
                           FROM bulletin_lignes bl
                           JOIN bulletins b ON bl.bulletin_id = b.id
                           JOIN cours c ON bl.cours_id = c.id
                           JOIN classes cl ON b.classe_id = cl.id
                           WHERE cl.ecole_id = :ecole_id 
                           AND bl.moyenne_matiere IS NULL
                           AND bl.statut = 'actif'
                           AND b.statut = 'actif'";
            $stmt = $db->prepare($lignes_query);
            $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
            $lignes = $stmt->fetchAll();
            
            $lignes_updated = 0;
            
            foreach ($lignes as $ligne) {
                // Calculer la moyenne pour cette matière et cet élève
                $moyenne_matiere_query = "SELECT AVG(n.valeur) as moyenne, COUNT(n.id) as nb_evaluations
                                         FROM notes n
                                         JOIN evaluations ev ON n.evaluation_id = ev.id
                                         JOIN classe_cours cc ON ev.classe_cours_id = cc.id
                                         WHERE cc.classe_id = :classe_id 
                                         AND cc.cours_id = :cours_id
                                         AND ev.periode = :periode
                                         AND n.eleve_id = :eleve_id
                                         AND n.absent = 0
                                         AND n.validee = 1";
                $moyenne_stmt = $db->prepare($moyenne_matiere_query);
                $moyenne_stmt->execute([
                    'classe_id' => $ligne['classe_id'],
                    'cours_id' => $ligne['cours_id'],
                    'periode' => $ligne['periode'],
                    'eleve_id' => $ligne['eleve_id']
                ]);
                $moyenne_data = $moyenne_stmt->fetch();
                
                $nouvelle_moyenne = ($moyenne_data['moyenne'] !== null && $moyenne_data['nb_evaluations'] > 0) 
                                   ? round($moyenne_data['moyenne'], 2) 
                                   : null;
                
                // Calculer le rang dans la matière
                $rang_query = "SELECT COUNT(*) + 1 as rang
                              FROM bulletin_lignes bl2
                              JOIN bulletins b2 ON bl2.bulletin_id = b2.id
                              WHERE bl2.cours_id = :cours_id
                              AND b2.classe_id = :classe_id
                              AND b2.periode = :periode
                              AND bl2.moyenne_matiere > :moyenne
                              AND bl2.statut = 'actif'
                              AND b2.statut = 'actif'";
                $rang_stmt = $db->prepare($rang_query);
                $rang_stmt->execute([
                    'cours_id' => $ligne['cours_id'],
                    'classe_id' => $ligne['classe_id'],
                    'periode' => $ligne['periode'],
                    'moyenne' => $nouvelle_moyenne ?: 0
                ]);
                $rang_data = $rang_stmt->fetch();
                $rang_matiere = $rang_data['rang'] ?: 1;
                
                // Calculer la moyenne pondérée
                $moyenne_ponderee = $nouvelle_moyenne ? round($nouvelle_moyenne * $ligne['coefficient'], 2) : null;
                
                // Mettre à jour la ligne du bulletin
                $update_query = "UPDATE bulletin_lignes 
                                SET moyenne_matiere = :moyenne_matiere,
                                    rang_matiere = :rang_matiere,
                                    moyenne_ponderee = :moyenne_ponderee,
                                    updated_at = NOW(),
                                    updated_by = :updated_by
                                WHERE id = :ligne_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    'moyenne_matiere' => $nouvelle_moyenne,
                    'rang_matiere' => $rang_matiere,
                    'moyenne_ponderee' => $moyenne_ponderee,
                    'updated_by' => $_SESSION['user_id'],
                    'ligne_id' => $ligne['id']
                ]);
                
                $lignes_updated++;
            }
            
            $fixes_applied[] = "✅ Lignes de bulletin mises à jour : $lignes_updated";
            $total_fixes += $lignes_updated;
            
            $db->commit();
            
            $success[] = "🎉 Correction complète terminée avec succès !";
            $success[] = "📊 Total des corrections appliquées : $total_fixes";
            $success = array_merge($success, $fixes_applied);
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "❌ Erreur lors de la correction : " . $e->getMessage();
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
    
    // Lignes de bulletin avec moyennes NULL
    $null_lignes = "SELECT COUNT(*) as count FROM bulletin_lignes bl
                   JOIN bulletins b ON bl.bulletin_id = b.id
                   JOIN classes c ON b.classe_id = c.id
                   WHERE c.ecole_id = :ecole_id 
                   AND bl.moyenne_matiere IS NULL
                   AND bl.statut = 'actif'
                   AND b.statut = 'actif'";
    $stmt = $db->prepare($null_lignes);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['null_lignes'] = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    $errors[] = "Erreur lors de la récupération des statistiques : " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction complète des bulletins - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-tools me-2"></i>Correction complète des bulletins</h4>
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
                                    <?php echo htmlspecialchars($msg); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Statistiques -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['empty_periodes'] ?? 0; ?></h3>
                                        <p class="mb-0">Évaluations sans période</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['unvalidated_notes'] ?? 0; ?></h3>
                                        <p class="mb-0">Notes non validées</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['null_moyennes'] ?? 0; ?></h3>
                                        <p class="mb-0">Bulletins sans moyenne</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-secondary text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['null_lignes'] ?? 0; ?></h3>
                                        <p class="mb-0">Lignes sans moyenne</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>Ce script de correction complète va :</h6>
                            <ul class="mb-0">
                                <li>✅ Corriger les périodes vides dans les évaluations</li>
                                <li>✅ Valider toutes les notes existantes</li>
                                <li>✅ Recalculer les moyennes générales des bulletins</li>
                                <li>✅ Recalculer les moyennes par matière</li>
                                <li>✅ Calculer les rangs dans chaque matière</li>
                                <li>✅ Calculer les moyennes pondérées</li>
                            </ul>
                        </div>
                        
                        <?php if (($stats['empty_periodes'] ?? 0) > 0 || ($stats['unvalidated_notes'] ?? 0) > 0 || ($stats['null_moyennes'] ?? 0) > 0 || ($stats['null_lignes'] ?? 0) > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="fix_all">
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-tools me-2"></i>🚀 Lancer la correction complète
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                🎉 Toutes les données sont correctes ! Les bulletins et leurs lignes devraient s'afficher normalement.
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="test_moyennes_fix.php" class="btn btn-outline-info">
                                <i class="bi bi-search me-2"></i>Test des corrections de base
                            </a>
                            <a href="test_bulletin_lignes.php" class="btn btn-outline-info">
                                <i class="bi bi-list-check me-2"></i>Test des lignes de bulletin
                            </a>
                            <a href="bulletins.php" class="btn btn-outline-primary">
                                <i class="bi bi-file-text me-2"></i>Voir les bulletins
                            </a>
                            <a href="view_bulletin.php?id=1" class="btn btn-outline-success">
                                <i class="bi bi-eye me-2"></i>Voir un bulletin détaillé
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

