<?php
/**
 * Mise à jour des bulletins existants avec les moyennes calculées
 * Pour corriger les bulletins qui ont des moyennes NULL
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'professeur']);

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = [];

// Traitement de la mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_bulletins') {
        try {
            $db->beginTransaction();
            
            // Récupérer tous les bulletins avec moyenne NULL
            $bulletins_query = "SELECT b.*, e.nom, e.prenom, c.nom_classe
                               FROM bulletins b
                               JOIN eleves e ON b.eleve_id = e.id
                               JOIN classes c ON b.classe_id = c.id
                               WHERE c.ecole_id = :ecole_id 
                               AND b.moyenne_generale IS NULL
                               AND b.statut = 'actif'";
            $stmt = $db->prepare($bulletins_query);
            $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
            $bulletins = $stmt->fetchAll();
            
            $updated_count = 0;
            
            foreach ($bulletins as $bulletin) {
                // Recalculer la moyenne pour ce bulletin
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
                
                $updated_count++;
                
                // Log pour debug
                error_log("Bulletin mis à jour - ID: {$bulletin['id']}, Élève: {$bulletin['nom']} {$bulletin['prenom']}, " .
                         "Classe: {$bulletin['nom_classe']}, Période: {$bulletin['periode']}, " .
                         "Moyenne: " . ($nouvelle_moyenne ?? 'NULL') . 
                         " (évaluations: " . ($moyenne_data['nb_evaluations'] ?? 0) . ")");
            }
            
            $db->commit();
            $success[] = "Mise à jour terminée avec succès !";
            $success[] = "Bulletins mis à jour : $updated_count";
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}

// Récupérer les statistiques des bulletins
$stats = [];
try {
    // Bulletins avec moyenne NULL
    $null_query = "SELECT COUNT(*) as count FROM bulletins b
                   JOIN classes c ON b.classe_id = c.id
                   WHERE c.ecole_id = :ecole_id 
                   AND b.moyenne_generale IS NULL
                   AND b.statut = 'actif'";
    $stmt = $db->prepare($null_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['null_moyennes'] = $stmt->fetch()['count'];
    
    // Bulletins avec moyenne calculée
    $calculated_query = "SELECT COUNT(*) as count FROM bulletins b
                        JOIN classes c ON b.classe_id = c.id
                        WHERE c.ecole_id = :ecole_id 
                        AND b.moyenne_generale IS NOT NULL
                        AND b.statut = 'actif'";
    $stmt = $db->prepare($calculated_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['calculated_moyennes'] = $stmt->fetch()['count'];
    
    // Total des bulletins
    $total_query = "SELECT COUNT(*) as count FROM bulletins b
                   JOIN classes c ON b.classe_id = c.id
                   WHERE c.ecole_id = :ecole_id 
                   AND b.statut = 'actif'";
    $stmt = $db->prepare($total_query);
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
    <title>Mise à jour des bulletins - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="bi bi-tools me-2"></i>Mise à jour des bulletins existants</h4>
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
                                        <p class="mb-0">Total bulletins</p>
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
                                <li>Recalcule les moyennes pour tous les bulletins qui ont une moyenne NULL</li>
                                <li>Utilise les notes existantes dans la base de données</li>
                                <li>Met à jour uniquement les bulletins qui n'ont pas de moyenne</li>
                                <li>Conserve toutes les autres données du bulletin</li>
                            </ul>
                        </div>
                        
                        <?php if (($stats['null_moyennes'] ?? 0) > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_bulletins">
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-warning btn-lg">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Mettre à jour les bulletins (<?php echo $stats['null_moyennes']; ?> bulletins)
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                Tous les bulletins ont déjà des moyennes calculées !
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-flex gap-2">
                            <a href="check_notes_data.php" class="btn btn-outline-info">
                                <i class="bi bi-search me-2"></i>Vérifier les données
                            </a>
                            <a href="create_test_data.php" class="btn btn-outline-primary">
                                <i class="bi bi-database-add me-2"></i>Créer des données de test
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