<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier l'ID de la classe
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de classe invalide.');
    redirect('index.php');
}

$class_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la classe
$classe = validateClassAccess($class_id, $db);
if (!$classe) {
    redirect('index.php');
}

// Récupérer les élèves de la classe
$eleves = getClassStudents($class_id, $db);

// Récupérer les cours de la classe
$cours = getClassCourses($class_id, $db);

$nombre_eleves = count($eleves);
$page_title = "Présence - " . $classe['nom_classe'];
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
                <p class="text-muted">Faire la présence des élèves de cette classe</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
                <a href="new_session.php?classe_id=<?php echo $classe['id']; ?>" class="btn btn-primary">
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
            
            <div class="row">
                <!-- Informations de la classe -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations de la Classe</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Nom de la classe</h6>
                                    <p class="h5 mb-3"><?php echo htmlspecialchars($classe['nom_classe']); ?></p>
                                    
                                    <h6 class="text-muted">Niveau</h6>
                                    <p class="mb-3"><?php echo htmlspecialchars($classe['niveau']); ?></p>
                                    
                                    <h6 class="text-muted">Cycle</h6>
                                    <p class="mb-3"><?php echo htmlspecialchars($classe['cycle']); ?></p>
                                    
                                    <h6 class="text-muted">Statut</h6>
                                    <span class="badge bg-<?php echo ($classe['statut'] == 'actif') ? 'success' : 'secondary'; ?> mb-3">
                                        <?php echo ucfirst($classe['statut']); ?>
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Enseignant principal</h6>
                                    <p class="mb-3">
                                        <?php if (isset($classe['enseignant_prenom']) && $classe['enseignant_prenom']): ?>
                                            <?php echo htmlspecialchars($classe['enseignant_prenom'] . ' ' . $classe['enseignant_nom']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non assigné</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <h6 class="text-muted">Salle</h6>
                                    <p class="mb-3"><?php echo htmlspecialchars($classe['salle_classe'] ?? 'Non définie'); ?></p>
                                    
                                    <h6 class="text-muted">Année Scolaire</h6>
                                    <p class="mb-3"><?php echo htmlspecialchars($classe['annee_scolaire'] ?? 'Non définie'); ?></p>
                                    
                                    <h6 class="text-muted">Créée le</h6>
                                    <p class="mb-3"><?php echo isset($classe['created_at']) ? date('d/m/Y', strtotime($classe['created_at'])) : 'Non défini'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cours de la classe -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-book me-2"></i>Cours de la Classe</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($cours)): ?>
                                <div class="text-center p-4">
                                    <i class="bi bi-book display-4 text-muted mb-3"></i>
                                    <h6>Aucun cours assigné</h6>
                                    <p class="text-muted">Cette classe n'a pas encore de cours assignés.</p>
                                    <a href="../classes/assign.php?id=<?php echo $classe['id']; ?>" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-2"></i>Assigner des Cours
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Cours</th>
                                                <th>Coefficient</th>
                                                <th>Enseignant</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cours as $c): ?>
                                                <tr>
                                                    <td><code><?php echo htmlspecialchars($c['code_cours']); ?></code></td>
                                                    <td><strong><?php echo htmlspecialchars($c['nom_cours']); ?></strong></td>
                                                    <td><span class="badge bg-secondary"><?php echo $c['coefficient']; ?></span></td>
                                                    <td>
                                                        <?php if ($c['enseignant_prenom']): ?>
                                                            <?php echo htmlspecialchars($c['enseignant_prenom'] . ' ' . $c['enseignant_nom']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Non assigné</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="session.php?classe_id=<?php echo $classe['id']; ?>&cours_id=<?php echo $c['cours_id']; ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="bi bi-clipboard-check me-1"></i>Présence
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiques et actions -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Statistiques</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <h2 class="text-primary"><?php echo $nombre_eleves; ?></h2>
                                <p class="text-muted mb-0">Élèves inscrits</p>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="new_session.php?classe_id=<?php echo $classe['id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-clipboard-check me-2"></i>Nouvelle Session
                                </a>
                                <a href="../classes/students.php?class_id=<?php echo $classe['id']; ?>" class="btn btn-success">
                                    <i class="bi bi-people me-2"></i>Gérer les Élèves
                                </a>
                                <a href="../classes/view.php?id=<?php echo $classe['id']; ?>" class="btn btn-info">
                                    <i class="bi bi-eye me-2"></i>Voir la Classe
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions rapides -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Actions Rapides</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if (!empty($cours)): ?>
                                    <a href="session.php?classe_id=<?php echo $classe['id']; ?>&cours_id=<?php echo $cours[0]['cours_id']; ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="bi bi-clipboard-check me-2"></i>Présence <?php echo htmlspecialchars($cours[0]['nom_cours']); ?>
                                    </a>
                                <?php endif; ?>
                                <a href="historique.php?classe_id=<?php echo $classe['id']; ?>" class="btn btn-outline-info">
                                    <i class="bi bi-clock-history me-2"></i>Historique
                                </a>
                                <a href="export.php?classe_id=<?php echo $classe['id']; ?>" class="btn btn-outline-success">
                                    <i class="bi bi-download me-2"></i>Exporter
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
