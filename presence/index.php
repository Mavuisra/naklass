<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Récupérer les classes de l'école avec le nombre d'élèves
try {
    $classes_query = "SELECT c.* FROM classes c 
                      WHERE c.ecole_id = :ecole_id 
                      AND c.statut = 'actif'
                      ORDER BY c.cycle ASC, c.niveau ASC, c.nom_classe ASC";
    
    $stmt = $db->prepare($classes_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classes = $stmt->fetchAll();
    
    // Ajouter le nombre d'élèves pour chaque classe
    foreach ($classes as &$classe) {
        $classe['nombre_eleves'] = getClassStudentCount($classe['id'], $db, true);
    }
    
} catch (Exception $e) {
    $classes = [];
    setFlashMessage('error', 'Erreur lors de la récupération des classes: ' . $e->getMessage());
}

$page_title = "Gestion de la Présence";
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
                <h1><i class="bi bi-clipboard-check me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Gérez la présence des élèves par classe et par cours</p>
            </div>
            
            <div class="topbar-actions">
                <a href="test_session.php" class="btn btn-outline-success me-2" title="Tester la page session">
                    <i class="bi bi-clipboard-check me-2"></i>Test Session
                </a>
                <a href="test_eleves_query.php" class="btn btn-outline-warning me-2" title="Tester la requête des élèves">
                    <i class="bi bi-play-circle me-2"></i>Test Requête
                </a>
                <a href="verify_eleves_structure.php" class="btn btn-outline-info me-2" title="Vérifier la structure des tables">
                    <i class="bi bi-database me-2"></i>Vérifier Structure
                </a>
                <a href="new_session.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nouvelle Session
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <?php
            $flash_messages = getFlashMessages();
            foreach ($flash_messages as $type => $message):
            ?>
                <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            
            <!-- Statistiques générales -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-building display-4"></i>
                            <h4><?php echo count($classes); ?></h4>
                            <p class="mb-0">Classes Actives</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-people display-4"></i>
                            <h4><?php echo array_sum(array_column($classes, 'nombre_eleves')); ?></h4>
                            <p class="mb-0">Total Élèves</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar-check display-4"></i>
                            <h4><?php echo date('d/m/Y'); ?></h4>
                            <p class="mb-0">Date Aujourd'hui</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-clock display-4"></i>
                            <h4><?php echo date('H:i'); ?></h4>
                            <p class="mb-0">Heure Actuelle</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Classes disponibles -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>Classes Disponibles</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($classes)): ?>
                        <div class="text-center p-4">
                            <i class="bi bi-building display-4 text-muted mb-3"></i>
                            <h6>Aucune classe disponible</h6>
                            <p class="text-muted">Aucune classe active n'a été trouvée pour cette école.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($classes as $classe): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="bi bi-building me-2"></i>
                                                <?php echo htmlspecialchars($classe['nom_classe']); ?>
                                            </h6>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <strong>Niveau:</strong> <?php echo htmlspecialchars($classe['niveau']); ?><br>
                                                    <strong>Cycle:</strong> <?php echo htmlspecialchars($classe['cycle']); ?><br>
                                                    <strong>Élèves:</strong> <?php echo $classe['nombre_eleves']; ?>
                                                </small>
                                            </p>
                                        </div>
                                        <div class="card-footer">
                                            <div class="d-grid gap-2">
                                                <a href="classe.php?id=<?php echo $classe['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="bi bi-clipboard-check me-1"></i>Faire la Présence
                                                </a>
                                                <a href="historique.php?classe_id=<?php echo $classe['id']; ?>" 
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="bi bi-clock-history me-1"></i>Historique
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sessions récentes -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Sessions Récentes</h5>
                </div>
                <div class="card-body">
                    <div class="text-center p-4">
                        <i class="bi bi-info-circle display-4 text-muted mb-3"></i>
                        <h6>Fonctionnalité en développement</h6>
                        <p class="text-muted">L'historique des sessions de présence sera bientôt disponible.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
