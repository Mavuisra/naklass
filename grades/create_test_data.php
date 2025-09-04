<?php
/**
 * Création de données de test pour les évaluations et notes
 * Pour permettre le test de génération des bulletins
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'professeur']);

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = [];

// Traitement de la création des données de test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_test_data') {
        try {
            $db->beginTransaction();
            
            // 1. Récupérer les relations classe-cours existantes
            $classe_cours_query = "SELECT cc.*, c.nom_cours, cl.nom_classe
                                  FROM classe_cours cc
                                  JOIN cours c ON cc.cours_id = c.id
                                  JOIN classes cl ON cc.classe_id = cl.id
                                  WHERE cl.ecole_id = :ecole_id AND cc.statut = 'actif'";
            $stmt = $db->prepare($classe_cours_query);
            $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
            $classe_cours = $stmt->fetchAll();
            
            if (empty($classe_cours)) {
                throw new Exception("Aucune relation classe-cours trouvée. Veuillez d'abord configurer les cours pour les classes.");
            }
            
            $evaluations_created = 0;
            $notes_created = 0;
            
            // 2. Créer des évaluations pour chaque classe-cours
            foreach ($classe_cours as $cc) {
                $periodes = ['1er Trimestre', '2ème Trimestre', '3ème Trimestre'];
                $types_evaluations = ['interro', 'devoir', 'examen'];
                
                foreach ($periodes as $periode) {
                    foreach ($types_evaluations as $type) {
                        // Vérifier si l'évaluation existe déjà
                        $existing_eval = "SELECT id FROM evaluations 
                                        WHERE classe_cours_id = :classe_cours_id 
                                        AND periode = :periode 
                                        AND type_evaluation = :type";
                        $stmt = $db->prepare($existing_eval);
                        $stmt->execute([
                            'classe_cours_id' => $cc['id'],
                            'periode' => $periode,
                            'type' => $type
                        ]);
                        
                        if (!$stmt->fetch()) {
                            // Créer l'évaluation
                            $insert_eval = "INSERT INTO evaluations (classe_cours_id, periode, nom_evaluation, 
                                                                    type_evaluation, date_evaluation, bareme, ponderation, 
                                                                    statut, created_by) 
                                           VALUES (:classe_cours_id, :periode, :nom_evaluation, :type_evaluation, 
                                                   :date_evaluation, :bareme, :ponderation, 'actif', :created_by)";
                            $stmt = $db->prepare($insert_eval);
                            $stmt->execute([
                                'classe_cours_id' => $cc['id'],
                                'periode' => $periode,
                                'nom_evaluation' => ucfirst($type) . ' - ' . $cc['nom_cours'] . ' - ' . $periode,
                                'type_evaluation' => $type,
                                'date_evaluation' => date('Y-m-d'),
                                'bareme' => 20.00,
                                'ponderation' => 1.00,
                                'created_by' => $_SESSION['user_id']
                            ]);
                            
                            $evaluation_id = $db->lastInsertId();
                            $evaluations_created++;
                            
                            // 3. Récupérer les élèves de cette classe
                            $eleves_query = "SELECT e.* FROM eleves e 
                                            JOIN inscriptions i ON e.id = i.eleve_id 
                                            WHERE i.classe_id = :classe_id 
                                            AND i.statut_inscription = 'validée'
                                            AND e.statut = 'actif'";
                            $stmt = $db->prepare($eleves_query);
                            $stmt->execute(['classe_id' => $cc['classe_id']]);
                            $eleves = $stmt->fetchAll();
                            
                            // 4. Créer des notes pour chaque élève
                            foreach ($eleves as $eleve) {
                                // Vérifier si la note existe déjà
                                $existing_note = "SELECT id FROM notes 
                                                WHERE evaluation_id = :evaluation_id 
                                                AND eleve_id = :eleve_id";
                                $stmt = $db->prepare($existing_note);
                                $stmt->execute([
                                    'evaluation_id' => $evaluation_id,
                                    'eleve_id' => $eleve['id']
                                ]);
                                
                                if (!$stmt->fetch()) {
                                    // Générer une note aléatoire entre 8 et 18
                                    $note = rand(8, 18) + (rand(0, 9) / 10); // Note avec décimales
                                    
                                    $insert_note = "INSERT INTO notes (evaluation_id, eleve_id, valeur, absent, excuse, 
                                                                     rattrapage, validee, statut, created_by) 
                                                   VALUES (:evaluation_id, :eleve_id, :valeur, :absent, :excuse, 
                                                           :rattrapage, :validee, 'actif', :created_by)";
                                    $stmt = $db->prepare($insert_note);
                                    $stmt->execute([
                                        'evaluation_id' => $evaluation_id,
                                        'eleve_id' => $eleve['id'],
                                        'valeur' => $note,
                                        'absent' => 0,
                                        'excuse' => 0,
                                        'rattrapage' => 0,
                                        'validee' => 1,
                                        'created_by' => $_SESSION['user_id']
                                    ]);
                                    
                                    $notes_created++;
                                }
                            }
                        }
                    }
                }
            }
            
            $db->commit();
            $success[] = "Données de test créées avec succès !";
            $success[] = "Évaluations créées : $evaluations_created";
            $success[] = "Notes créées : $notes_created";
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "Erreur lors de la création des données de test : " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création de données de test - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="bi bi-database-add me-2"></i>Création de données de test</h4>
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
                        
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>Qu'est-ce que ce script fait ?</h6>
                            <ul class="mb-0">
                                <li>Crée des évaluations pour chaque cours de chaque classe</li>
                                <li>Génère des notes aléatoires (entre 8 et 18) pour chaque élève</li>
                                <li>Permet de tester la génération des bulletins</li>
                                <li>Les données sont créées pour les 3 trimestres</li>
                            </ul>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="create_test_data">
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-database-add me-2"></i>Créer les données de test
                                </button>
                            </div>
                        </form>
                        
                        <hr>
                        
                        <div class="d-flex gap-2">
                            <a href="check_notes_data.php" class="btn btn-outline-info">
                                <i class="bi bi-search me-2"></i>Vérifier les données existantes
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

