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
                <!-- Tableau des classes -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-table me-2"></i>
                            Liste des Classes
                        </h5>
                        <span class="badge bg-primary"><?php echo count($classes); ?> classe(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nom de la Classe</th>
                                        <th>Cycle</th>
                                        <th>Niveau</th>
                                        <th>Année Scolaire</th>
                                        <th>Élèves</th>
                                        <th>Capacité</th>
                                        <th>Salle</th>
                                        <th>Statut</th>
                                        <th>Créée le</th>
                                        <th width="140">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
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
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($classe['nom_classe']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $cycle_color; ?>">
                                                    <?php echo ucfirst($classe['cycle']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-outline-secondary">
                                                    <?php echo htmlspecialchars($classe['niveau']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-outline-info">
                                                    <?php echo htmlspecialchars($classe['annee_scolaire']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo $classe['nombre_eleves']; ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($classe['capacite_max'] > 0): ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress me-2" style="width: 60px; height: 8px;">
                                                            <div class="progress-bar <?php echo $capacity_class; ?>" 
                                                                 style="width: <?php echo min(100, $capacity_percentage); ?>%"></div>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo $classe['nombre_eleves']; ?>/<?php echo $classe['capacite_max']; ?>
                                                        </small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Illimitée</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($classe['salle_classe'])): ?>
                                                    <i class="bi bi-geo-alt me-1"></i>
                                                    <?php echo htmlspecialchars($classe['salle_classe']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $classe['statut'] == 'actif' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($classe['statut']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($classe['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="view.php?id=<?php echo $classe['id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" title="Voir">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $classe['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm" title="Modifier">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="students.php?class_id=<?php echo $classe['id']; ?>" 
                                                       class="btn btn-outline-success btn-sm" title="Élèves">
                                                        <i class="bi bi-people"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <?php if (!empty($classe['notes_internes'])): ?>
                                            <tr class="table-light">
                                                <td colspan="10" class="py-2">
                                                    <small class="text-muted">
                                                        <i class="bi bi-chat-text me-1"></i>
                                                        <strong>Notes :</strong> <?php echo htmlspecialchars($classe['notes_internes']); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
