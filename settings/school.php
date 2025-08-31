<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Récupérer les informations de l'école
try {
    $school_query = "SELECT * FROM ecoles WHERE id = :ecole_id";
    $stmt = $db->prepare($school_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $school = $stmt->fetch();
    
    // Statistiques de l'école
    $stats_query = "SELECT 
                        (SELECT COUNT(*) FROM classes WHERE ecole_id = :ecole_id) as total_classes,
                        (SELECT COUNT(*) FROM eleves WHERE ecole_id = :ecole_id AND statut = 'actif') as total_eleves,
                        (SELECT COUNT(*) FROM utilisateurs WHERE ecole_id = :ecole_id AND statut = 'actif') as total_users,
                        (SELECT COUNT(*) FROM cours WHERE ecole_id = :ecole_id AND actif = 1) as total_matieres";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    $school = [];
    $stats = ['total_classes' => 0, 'total_eleves' => 0, 'total_users' => 0, 'total_matieres' => 0];
    error_log("Erreur lors de la récupération des informations de l'école: " . $e->getMessage());
}

$page_title = "Configuration de l'École";
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
    <link href="../assets/css/naklass-theme.css" rel="stylesheet">
    
    <style>
        .config-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .config-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #0077b6;
        }
        
        .school-info {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
    </style>
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
                <p class="text-muted">Configurez les paramètres de votre établissement</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
                
                <a href="edit_school.php" class="btn btn-primary">
                    <i class="bi bi-pencil me-2"></i>Modifier
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
            
            <!-- Cartes de statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_classes']); ?></h3>
                            <p>Total Classes</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_eleves']); ?></h3>
                            <p>Total Élèves</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_users']); ?></h3>
                            <p>Total Utilisateurs</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-book"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_matieres']); ?></h3>
                            <p>Total Matières</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informations de l'école -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card config-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle me-2"></i>Informations Générales
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($school): ?>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Nom de l'établissement</label>
                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($school['nom']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Code de l'établissement</label>
                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($school['code']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Type d'établissement</label>
                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($school['type'] ?? 'Non spécifié'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Statut</label>
                                        <span class="badge <?php echo $school['statut'] === 'actif' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $school['statut'] === 'actif' ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Adresse</label>
                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($school['adresse'] ?? 'Non spécifiée'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Ville</label>
                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($school['ville'] ?? 'Non spécifiée'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Téléphone</label>
                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($school['telephone'] ?? 'Non spécifié'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Email</label>
                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($school['email'] ?? 'Non spécifié'); ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($school['description']): ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Description</label>
                                        <p class="form-control-plaintext"><?php echo htmlspecialchars($school['description']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Date de création</label>
                                        <p class="form-control-plaintext"><?php echo date('d/m/Y', strtotime($school['created_at'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Dernière modification</label>
                                        <p class="form-control-plaintext"><?php echo $school['updated_at'] ? date('d/m/Y', strtotime($school['updated_at'])) : 'Jamais modifié'; ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="bi bi-exclamation-triangle text-warning fs-1 mb-3"></i>
                                    <h6>Aucune information trouvée</h6>
                                    <p class="text-muted">Les informations de l'école n'ont pas pu être récupérées.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card config-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-gear me-2"></i>Actions Rapides
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="edit_school.php" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil me-2"></i>Modifier les informations
                                </a>
                                
                                <a href="../classes/" class="btn btn-outline-info">
                                    <i class="bi bi-building me-2"></i>Gérer les classes
                                </a>
                                
                                <a href="../matieres/" class="btn btn-outline-success">
                                    <i class="bi bi-book me-2"></i>Gérer les matières
                                </a>
                                
                                <a href="users.php" class="btn btn-outline-warning">
                                    <i class="bi bi-people me-2"></i>Gérer les utilisateurs
                                </a>
                                
                                <a href="backup.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-cloud-arrow-up me-2"></i>Sauvegarde
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card config-card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-shield-check me-2"></i>État du Système
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-check-circle text-success fs-4"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Configuration</h6>
                                    <small class="text-muted">École configurée</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-check-circle text-success fs-4"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Base de données</h6>
                                    <small class="text-muted">Connectée</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-check-circle text-success fs-4"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Sécurité</h6>
                                    <small class="text-muted">Active</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        // Animation des cartes au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.config-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>

