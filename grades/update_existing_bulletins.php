<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';
$updated_count = 0;

// Traitement de la mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_bulletins') {
        try {
            $db->beginTransaction();
            
            // Récupérer tous les bulletins qui n'ont pas de lignes
            $bulletins_query = "SELECT b.*, c.ecole_id 
                               FROM bulletins b
                               JOIN classes c ON b.classe_id = c.id
                               WHERE c.ecole_id = :ecole_id 
                               AND b.statut = 'actif'
                               AND NOT EXISTS (
                                   SELECT 1 FROM bulletin_lignes bl 
                                   WHERE bl.bulletin_id = b.id
                               )";
            $bulletins_stmt = $db->prepare($bulletins_query);
            $bulletins_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
            $bulletins_sans_lignes = $bulletins_stmt->fetchAll();
            
            foreach ($bulletins_sans_lignes as $bulletin) {
                // Récupérer les matières de la classe
                $matieres_query = "SELECT DISTINCT c.id as cours_id, c.code_cours, c.nom_cours, c.coefficient
                                  FROM cours c
                                  JOIN classe_cours cc ON c.id = cc.cours_id
                                  WHERE cc.classe_id = :classe_id 
                                  AND c.statut = 'actif'
                                  ORDER BY c.coefficient DESC, c.nom_cours";
                $matieres_stmt = $db->prepare($matieres_query);
                $matieres_stmt->execute(['classe_id' => $bulletin['classe_id']]);
                $matieres = $matieres_stmt->fetchAll();
                
                // Créer les lignes du bulletin pour chaque matière
                foreach ($matieres as $matiere) {
                                         // Calculer la moyenne de l'élève pour cette matière et cette période
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
                    $moyenne_matiere_stmt = $db->prepare($moyenne_matiere_query);
                    $moyenne_matiere_stmt->execute([
                        'classe_id' => $bulletin['classe_id'],
                        'cours_id' => $matiere['cours_id'],
                        'periode' => $bulletin['periode'],
                        'eleve_id' => $bulletin['eleve_id']
                    ]);
                    $moyenne_matiere_data = $moyenne_matiere_stmt->fetch();
                    
                    $moyenne_matiere = $moyenne_matiere_data['moyenne'] ? round($moyenne_matiere_data['moyenne'], 2) : null;
                    
                                         // Calculer le rang de l'élève dans cette matière
                     $rang_matiere_query = "SELECT COUNT(*) + 1 as rang
                                          FROM (
                                              SELECT e.id, AVG(n.valeur) as moyenne
                                              FROM eleves e
                                              JOIN inscriptions i ON e.id = i.eleve_id
                                              JOIN notes n ON e.id = n.eleve_id
                                              JOIN evaluations ev ON n.evaluation_id = ev.id
                                              JOIN classe_cours cc ON ev.classe_cours_id = cc.id
                                              WHERE cc.classe_id = :classe_id 
                                              AND cc.cours_id = :cours_id
                                              AND ev.periode = :periode
                                              AND n.absent = 0
                                              AND n.validee = 1
                                              AND i.classe_id = :classe_id
                                              AND i.annee_scolaire = :annee_scolaire
                                              GROUP BY e.id
                                              HAVING AVG(n.valeur) > :moyenne_eleve
                                          ) as classement";
                    $rang_matiere_stmt = $db->prepare($rang_matiere_query);
                    $rang_matiere_stmt->execute([
                        'classe_id' => $bulletin['classe_id'],
                        'cours_id' => $matiere['cours_id'],
                        'periode' => $bulletin['periode'],
                        'annee_scolaire' => $bulletin['annee_scolaire'],
                        'moyenne_eleve' => $moyenne_matiere ?: 0
                    ]);
                    $rang_matiere_data = $rang_matiere_stmt->fetch();
                    $rang_matiere = $rang_matiere_data['rang'] ?: 1;
                    
                    // Calculer la moyenne pondérée
                    $moyenne_ponderee = $moyenne_matiere ? round($moyenne_matiere * $matiere['coefficient'], 2) : null;
                    
                    // Insérer la ligne du bulletin
                    $insert_ligne_query = "INSERT INTO bulletin_lignes (bulletin_id, cours_id, moyenne_matiere, 
                                                                      rang_matiere, moyenne_ponderee, coefficient,
                                                                      statut, created_by) 
                                         VALUES (:bulletin_id, :cours_id, :moyenne_matiere, 
                                                 :rang_matiere, :moyenne_ponderee, :coefficient,
                                                 'actif', :created_by)";
                    $insert_ligne_stmt = $db->prepare($insert_ligne_query);
                    $insert_ligne_stmt->execute([
                        'bulletin_id' => $bulletin['id'],
                        'cours_id' => $matiere['cours_id'],
                        'moyenne_matiere' => $moyenne_matiere,
                        'rang_matiere' => $rang_matiere,
                        'moyenne_ponderee' => $moyenne_ponderee,
                        'coefficient' => $matiere['coefficient'],
                        'created_by' => $_SESSION['user_id']
                    ]);
                }
                
                $updated_count++;
            }
            
            safeCommit($db);
            $success = "Mise à jour terminée ! {$updated_count} bulletins ont été complétés avec leurs lignes détaillées.";
            
        } catch (Exception $e) {
            safeRollback($db);
            $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}

// Compter les bulletins sans lignes
$count_query = "SELECT COUNT(*) as total
                FROM bulletins b
                JOIN classes c ON b.classe_id = c.id
                WHERE c.ecole_id = :ecole_id 
                AND b.statut = 'actif'
                AND NOT EXISTS (
                    SELECT 1 FROM bulletin_lignes bl 
                    WHERE bl.bulletin_id = b.id
                )";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$count_result = $count_stmt->fetch();
$bulletins_sans_lignes_count = $count_result['total'];

$page_title = "Mise à jour des bulletins existants";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-tools me-2"></i><?php echo $page_title; ?></h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h5>Erreurs :</h5>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>État des bulletins</h5>
            </div>
            <div class="card-body">
                <p><strong>Bulletins sans lignes détaillées :</strong> <?php echo $bulletins_sans_lignes_count; ?></p>
                
                <?php if ($bulletins_sans_lignes_count > 0): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Attention :</strong> Certains bulletins existants n'ont pas de lignes détaillées (notes par matière).
                        Utilisez le bouton ci-dessous pour les compléter.
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_bulletins">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Êtes-vous sûr de vouloir mettre à jour tous les bulletins ?')">
                            <i class="bi bi-arrow-clockwise me-2"></i>Mettre à jour les bulletins existants
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Parfait !</strong> Tous les bulletins ont leurs lignes détaillées.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="bulletins.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Retour aux bulletins
            </a>
        </div>
    </div>
</body>
</html>
