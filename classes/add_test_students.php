<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

$database = new Database();
$db = $database->getConnection();

$page_title = "Ajouter des Élèves de Test";
$errors = [];
$success = [];

// Vérifier si la table eleves existe et a des données
try {
    $check_query = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id";
    $stmt = $db->prepare($check_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $total_eleves = $stmt->fetch()['total'];
    
    if ($total_eleves == 0) {
        // Ajouter des élèves de test
        $test_students = [
            [
                'matricule' => 'ELE001',
                'nom' => 'Dupont',
                'prenom' => 'Marie',
                'date_naissance' => '2010-05-15',
                'sexe' => 'F',
                'telephone' => '0123456789',
                'email' => 'marie.dupont@test.com'
            ],
            [
                'matricule' => 'ELE002',
                'nom' => 'Martin',
                'prenom' => 'Pierre',
                'date_naissance' => '2010-08-22',
                'sexe' => 'M',
                'telephone' => '0123456790',
                'email' => 'pierre.martin@test.com'
            ],
            [
                'matricule' => 'ELE003',
                'nom' => 'Bernard',
                'prenom' => 'Sophie',
                'date_naissance' => '2010-03-10',
                'sexe' => 'F',
                'telephone' => '0123456791',
                'email' => 'sophie.bernard@test.com'
            ],
            [
                'matricule' => 'ELE004',
                'nom' => 'Petit',
                'prenom' => 'Thomas',
                'date_naissance' => '2010-11-18',
                'sexe' => 'M',
                'telephone' => '0123456792',
                'email' => 'thomas.petit@test.com'
            ],
            [
                'matricule' => 'ELE005',
                'nom' => 'Robert',
                'prenom' => 'Emma',
                'date_naissance' => '2010-07-05',
                'sexe' => 'F',
                'telephone' => '0123456793',
                'email' => 'emma.robert@test.com'
            ]
        ];
        
        $insert_query = "INSERT INTO eleves (ecole_id, matricule, nom, prenom, date_naissance, sexe, telephone, email, statut) 
                        VALUES (:ecole_id, :matricule, :nom, :prenom, :date_naissance, :sexe, :telephone, :email, 'actif')";
        
        $stmt = $db->prepare($insert_query);
        
        foreach ($test_students as $student) {
            $stmt->execute([
                'ecole_id' => $_SESSION['ecole_id'],
                'matricule' => $student['matricule'],
                'nom' => $student['nom'],
                'prenom' => $student['prenom'],
                'date_naissance' => $student['date_naissance'],
                'sexe' => $student['sexe'],
                'telephone' => $student['telephone'],
                'email' => $student['email']
            ]);
        }
        
        $success[] = "✓ " . count($test_students) . " élèves de test ajoutés avec succès";
        
    } else {
        $success[] = "✓ La table 'eleves' contient déjà {$total_eleves} élève(s)";
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur lors de l'ajout des élèves de test : " . $e->getMessage();
}

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
    <link href="../assets/css/common.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation latérale -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Contenu principal -->
    <main class="main-content">
        <!-- Barre supérieure -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-person-plus me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Ajouter des élèves de test pour tester le système</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour aux Classes
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Résultats</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success">
                                    <h6><i class="bi bi-check-circle me-2"></i>Opération réussie</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($success as $msg): ?>
                                            <li><?php echo htmlspecialchars($msg); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-center mt-4">
                                <a href="index.php" class="btn btn-primary me-2">
                                    <i class="bi bi-building me-2"></i>Retour aux Classes
                                </a>
                                <a href="add_test_students.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Réessayer
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
