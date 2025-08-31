<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Récupérer les statistiques de configuration
try {
    // Statistiques des utilisateurs
    $users_stats_query = "SELECT 
                            COUNT(*) as total_users,
                            SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as active_users,
                            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
                            SUM(CASE WHEN role = 'enseignant' THEN 1 ELSE 0 END) as teacher_users
                          FROM utilisateurs 
                          WHERE ecole_id = :ecole_id";
    
    $stmt = $db->prepare($users_stats_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $users_stats = $stmt->fetch();
    
    // Statistiques des classes
    $classes_stats_query = "SELECT 
                              COUNT(*) as total_classes,
                              SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as active_classes
                            FROM classes 
                            WHERE ecole_id = :ecole_id";
    
    $stmt = $db->prepare($classes_stats_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classes_stats = $stmt->fetch();
    
    // Statistiques des matières
    $matieres_stats_query = "SELECT 
                               COUNT(*) as total_matieres,
                               SUM(CASE WHEN actif = 1 THEN 1 ELSE 0 END) as active_matieres
                             FROM cours 
                             WHERE ecole_id = :ecole_id";
    
    $stmt = $db->prepare($matieres_stats_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $matieres_stats = $stmt->fetch();
    
    // Statistiques des périodes
    $periodes_stats_query = "SELECT 
                               COUNT(*) as total_periodes,
                               SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as active_periodes
                             FROM periodes_scolaires 
                             WHERE ecole_id = :ecole_id";
    
    $stmt = $db->prepare($periodes_stats_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $periodes_stats = $stmt->fetch();
    
} catch (Exception $e) {
    $users_stats = ['total_users' => 0, 'active_users' => 0, 'admin_users' => 0, 'teacher_users' => 0];
    $classes_stats = ['total_classes' => 0, 'active_classes' => 0];
    $matieres_stats = ['total_matieres' => 0, 'active_matieres' => 0];
    $periodes_stats = ['total_periodes' => 0, 'active_periodes' => 0];
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
}

$page_title = "Paramètres du Système";
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
        .settings-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .settings-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #0077b6;
        }
        
        .config-section {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem;
            text-decoration: none;
            color: var(--text-color);
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .quick-action-btn i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        .quick-action-btn span {
            font-weight: 500;
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
                <h1><i class="bi bi-gear me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Configurez et gérez votre système Naklass</p>
            </div>
            
            <div class="topbar-actions">
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-2"></i>Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="users.php"><i class="bi bi-people me-2"></i>Gérer Utilisateurs</a></li>
                        <li><a class="dropdown-item" href="school.php"><i class="bi bi-building me-2"></i>Configuration École</a></li>
                        <li><a class="dropdown-item" href="security.php"><i class="bi bi-shield-lock me-2"></i>Sécurité</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="backup.php"><i class="bi bi-cloud-arrow-up me-2"></i>Sauvegarde</a></li>
                    </ul>
                </div>
                
                <a href="school.php" class="btn btn-primary">
                    <i class="bi bi-building me-2"></i>Configuration École
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
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($users_stats['total_users']); ?></h3>
                            <p>Total Utilisateurs</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($classes_stats['total_classes']); ?></h3>
                            <p>Total Classes</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-book"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($matieres_stats['total_matieres']); ?></h3>
                            <p>Total Matières</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($periodes_stats['total_periodes']); ?></h3>
                            <p>Périodes Scolaires</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-lightning-charge me-2"></i>Actions Rapides</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <a href="users.php" class="quick-action-btn">
                                        <i class="bi bi-people"></i>
                                        <span>Gérer utilisateurs</span>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="school.php" class="quick-action-btn">
                                        <i class="bi bi-building"></i>
                                        <span>Configuration école</span>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="security.php" class="quick-action-btn">
                                        <i class="bi bi-shield-lock"></i>
                                        <span>Paramètres sécurité</span>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="backup.php" class="quick-action-btn">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <span>Sauvegarde système</span>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="link_teacher_accounts.php" class="quick-action-btn">
                                        <i class="bi bi-link"></i>
                                        <span>Lier comptes enseignants</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-info-circle me-2"></i>Informations Système</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-server text-primary fs-3"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Version Naklass</h6>
                                    <small class="text-muted">Système de gestion scolaire</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-shield-check text-success fs-3"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Sécurité</h6>
                                    <small class="text-muted">Authentification et autorisation</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-gear text-info fs-3"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Configuration</h6>
                                    <small class="text-muted">Paramètres personnalisables</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sections de configuration -->
            <div class="row g-4">
                <!-- Gestion des utilisateurs -->
                <div class="col-lg-6">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-people me-2"></i>Gestion des Utilisateurs
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="border-end">
                                        <strong><?php echo $users_stats['active_users']; ?></strong>
                                        <small class="d-block text-muted">Actifs</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-end">
                                        <strong><?php echo $users_stats['admin_users']; ?></strong>
                                        <small class="d-block text-muted">Administrateurs</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <strong><?php echo $users_stats['teacher_users']; ?></strong>
                                    <small class="d-block text-muted">Enseignants</small>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="users.php" class="btn btn-outline-primary">
                                    <i class="bi bi-people me-2"></i>Gérer les Utilisateurs
                                </a>
                                <a href="users.php?action=create" class="btn btn-outline-success">
                                    <i class="bi bi-person-plus me-2"></i>Nouvel Utilisateur
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Configuration de l'école -->
                <div class="col-lg-6">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-building me-2"></i>Configuration de l'École
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="border-end">
                                        <strong><?php echo $classes_stats['active_classes']; ?></strong>
                                        <small class="d-block text-muted">Classes Actives</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <strong><?php echo $matieres_stats['active_matieres']; ?></strong>
                                    <small class="d-block text-muted">Matières Actives</small>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="school.php" class="btn btn-outline-primary">
                                    <i class="bi bi-building me-2"></i>Paramètres École
                                </a>
                                <a href="../classes/" class="btn btn-outline-info">
                                    <i class="bi bi-gear me-2"></i>Gérer Classes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sécurité et sauvegarde -->
                <div class="col-lg-6">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-shield-lock me-2"></i>Sécurité et Sauvegarde
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Authentification sécurisée</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Gestion des rôles</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Logs d'activité</span>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="security.php" class="btn btn-outline-warning">
                                    <i class="bi bi-shield-lock me-2"></i>Paramètres Sécurité
                                </a>
                                <a href="backup.php" class="btn btn-outline-info">
                                    <i class="bi bi-cloud-arrow-up me-2"></i>Sauvegarde
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Périodes scolaires -->
                <div class="col-lg-6">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-calendar-event me-2"></i>Périodes Scolaires
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="border-end">
                                        <strong><?php echo $periodes_stats['active_periodes']; ?></strong>
                                        <small class="d-block text-muted">Périodes Actives</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <strong><?php echo $periodes_stats['total_periodes']; ?></strong>
                                    <small class="d-block text-muted">Total Périodes</small>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="../grades/periodes.php" class="btn btn-outline-primary">
                                    <i class="bi bi-calendar-event me-2"></i>Gérer Périodes
                                </a>
                                <a href="../grades/" class="btn btn-outline-success">
                                    <i class="bi bi-journal-text me-2"></i>Notes & Bulletins
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
    
    <script>
        // Animation des cartes au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.settings-card');
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

