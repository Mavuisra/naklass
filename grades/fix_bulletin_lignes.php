<?php
/**
 * Correction des lignes de bulletin (notes par matière)
 * Recalcule les moyennes par matière pour tous les bulletins
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
    if ($_POST['action'] === 'fix_bulletin_lignes') {
        try {
            $db->beginTransaction();
            
            $fixes_applied = [];
            
            // 1. Récupérer toutes les lignes de bulletin avec moyennes NULL
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
                
                // Log pour debug
                error_log("Ligne bulletin mise à jour - ID: {$ligne['id']}, Matière: {$ligne['nom_cours']}, " .
                         "Moyenne: " . ($nouvelle_moyenne ?? 'NULL') . 
                         " (évaluations: " . ($moyenne_data['nb_evaluations'] ?? 0) . ")");
            }
            
            $fixes_applied[] = "Lignes de bulletin mises à jour : $lignes_updated";
            
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
    // Lignes de bulletin avec moyennes NULL
    $null_moyennes = "SELECT COUNT(*) as count FROM bulletin_lignes bl
                     JOIN bulletins b ON bl.bulletin_id = b.id
                     JOIN classes c ON b.classe_id = c.id
                     WHERE c.ecole_id = :ecole_id 
                     AND bl.moyenne_matiere IS NULL
                     AND bl.statut = 'actif'
                     AND b.statut = 'actif'";
    $stmt = $db->prepare($null_moyennes);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['null_moyennes'] = $stmt->fetch()['count'];
    
    // Lignes de bulletin avec moyennes calculées
    $calculated_moyennes = "SELECT COUNT(*) as count FROM bulletin_lignes bl
                           JOIN bulletins b ON bl.bulletin_id = b.id
                           JOIN classes c ON b.classe_id = c.id
                           WHERE c.ecole_id = :ecole_id 
                           AND bl.moyenne_matiere IS NOT NULL
                           AND bl.statut = 'actif'
                           AND b.statut = 'actif'";
    $stmt = $db->prepare($calculated_moyennes);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['calculated_moyennes'] = $stmt->fetch()['count'];
    
    // Total des lignes de bulletin
    $total = "SELECT COUNT(*) as count FROM bulletin_lignes bl
             JOIN bulletins b ON bl.bulletin_id = b.id
             JOIN classes c ON b.classe_id = c.id
             WHERE c.ecole_id = :ecole_id 
             AND bl.statut = 'actif'
             AND b.statut = 'actif'";
    $stmt = $db->prepare($total);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total'] = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    $errors[] = "Erreur lors de la récupération des statistiques : " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction des lignes de bulletin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="bi bi-list-check me-2"></i>Correction des lignes de bulletin (notes par matière)</h4>
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
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['total'] ?? 0; ?></h3>
                                        <p class="mb-0">Total lignes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['calculated_moyennes'] ?? 0; ?></h3>
                                        <p class="mb-0">Avec moyennes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['null_moyennes'] ?? 0; ?></h3>
                                        <p class="mb-0">Sans moyennes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>Qu'est-ce que ce script fait ?</h6>
                            <ul class="mb-0">
                                <li>Recalcule les moyennes par matière pour tous les bulletins</li>
                                <li>Met à jour les rangs dans chaque matière</li>
                                <li>Calcule les moyennes pondérées</li>
                                <li>Corrige uniquement les lignes qui ont des moyennes NULL</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle me-2"></i>Important :</h6>
                            <p class="mb-0">Ce script doit être exécuté après avoir corrigé les données de base (évaluations et notes) avec le script <code>fix_existing_data.php</code>.</p>
                        </div>
                        
                        <?php if (($stats['null_moyennes'] ?? 0) > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="fix_bulletin_lignes">
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-warning btn-lg">
                                        <i class="bi bi-list-check me-2"></i>Corriger les lignes de bulletin (<?php echo $stats['null_moyennes']; ?> lignes)
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                Toutes les lignes de bulletin ont déjà des moyennes calculées !
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-flex gap-2">
                            <a href="fix_existing_data.php" class="btn btn-outline-primary">
                                <i class="bi bi-tools me-2"></i>Corriger les données de base
                            </a>
                            <a href="test_moyennes_fix.php" class="btn btn-outline-info">
                                <i class="bi bi-search me-2"></i>Tester les corrections
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

