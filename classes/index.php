<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Récupérer les classes avec leurs statistiques
try {
    $classes_query = "SELECT c.*, 
                             u.prenom as created_by_prenom,
                             u.nom as created_by_nom
                      FROM classes c
                      LEFT JOIN utilisateurs u ON c.created_by = u.id
                      WHERE c.ecole_id = :ecole_id
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

$page_title = "Gestion des Classes";
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
                <h1><i class="bi bi-building me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Gérer les classes et les assignations d'élèves</p>
            </div>
            
            <div class="topbar-actions">
                <a href="verify_columns.php" class="btn btn-outline-warning me-2" title="Vérifier les colonnes">
                    <i class="bi bi-check-circle me-2"></i>Vérifier Colonnes
                </a>
                <a href="test_view_quick.php" class="btn btn-outline-success me-2" title="Test rapide de la page view">
                    <i class="bi bi-lightning me-2"></i>Test Rapide
                </a>
                <a href="test_view.php" class="btn btn-outline-info me-2" title="Test complet de la page view">
                    <i class="bi bi-bug me-2"></i>Test Complet
                </a>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nouvelle Classe
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
            
            <?php if (empty($classes)): ?>
                <div class="text-center p-5">
                    <i class="bi bi-building display-1 text-muted mb-3"></i>
                    <h5>Aucune classe créée</h5>
                    <p class="text-muted">Commencez par créer vos premières classes pour organiser vos élèves.</p>
                    <a href="create.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Créer la Première Classe
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($classes as $classe): ?>
                        <?php
                        $capacity_percentage = $classe['capacite_max'] > 0 ? ($classe['nombre_eleves'] / $classe['capacite_max']) * 100 : 0;
                        $capacity_class = 'bg-success';
                        if ($capacity_percentage >= 80) $capacity_class = 'bg-danger';
                        elseif ($capacity_percentage >= 60) $capacity_class = 'bg-warning';
                        
                        // Déterminer la couleur du cycle
                        $cycle_colors = [
                            'maternelle' => 'info',
                            'primaire' => 'primary',
                            'secondaire' => 'success',
                            'supérieur' => 'warning'
                        ];
                        $cycle_color = $cycle_colors[$classe['cycle']] ?? 'secondary';
                        ?>
                        
                        <div class="col-lg-4 col-md-6">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($classe['nom_classe']); ?></h5>
                                    <span class="badge bg-<?php echo $classe['statut'] == 'actif' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($classe['statut']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                            <span class="badge bg-<?php echo $cycle_color; ?>">
                                                <?php echo ucfirst($classe['cycle']); ?>
                                            </span>
                                            <span class="badge bg-outline-secondary">
                                                <?php echo htmlspecialchars($classe['niveau']); ?>
                                            </span>
                                            <span class="badge bg-outline-info">
                                                <?php echo htmlspecialchars($classe['annee_scolaire']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted">Élèves inscrits</small>
                                            <small class="fw-bold">
                                                <?php echo $classe['nombre_eleves']; ?>
                                                <?php if ($classe['capacite_max'] > 0): ?>
                                                    / <?php echo $classe['capacite_max']; ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        
                                        <?php if ($classe['capacite_max'] > 0): ?>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar <?php echo $capacity_class; ?>" 
                                                     style="width: <?php echo min(100, $capacity_percentage); ?>%"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($classe['salle_classe']): ?>
                                        <p class="card-text small text-muted mb-2">
                                            <i class="bi bi-geo-alt me-1"></i>
                                            Salle: <?php echo htmlspecialchars($classe['salle_classe']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($classe['notes_internes']): ?>
                                        <p class="card-text small text-muted">
                                            <?php echo htmlspecialchars(substr($classe['notes_internes'], 0, 100)) . (strlen($classe['notes_internes']) > 100 ? '...' : ''); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Créée le <?php echo date('d/m/Y', strtotime($classe['created_at'])); ?>
                                        </small>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $classe['id']; ?>" 
                                               class="btn btn-outline-info" title="Voir">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $classe['id']; ?>" 
                                               class="btn btn-outline-primary" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="students.php?class_id=<?php echo $classe['id']; ?>" 
                                               class="btn btn-outline-success" title="Élèves">
                                                <i class="bi bi-people"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
