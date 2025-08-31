<?php
/**
 * Tableau de bord du Super Administrateur
 * Gestion centralisée de toutes les écoles
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les droits Super Admin
requireAuth();
requireSuperAdmin();

$database = new Database();
$db = $database->getConnection();

// Statistiques globales
$stats = [];

// Nombre total d'écoles
    $query = "SELECT COUNT(*) as total_ecoles FROM ecoles WHERE validation_status = 'approved'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_ecoles'] = $stmt->fetch()['total_ecoles'];

// Vérifier si la colonne activee existe
$check_column_query = "SHOW COLUMNS FROM ecoles LIKE 'activee'";
$check_stmt = $db->prepare($check_column_query);
$check_stmt->execute();
$activee_column_exists = $check_stmt->fetch();

if ($activee_column_exists) {
    // Écoles actives
    $query = "SELECT COUNT(*) as ecoles_actives FROM ecoles WHERE validation_status = 'approved' AND activee = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['ecoles_actives'] = $stmt->fetch()['ecoles_actives'];

    // Écoles en attente
    $query = "SELECT COUNT(*) as ecoles_attente FROM ecoles WHERE validation_status = 'approved' AND activee = FALSE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['ecoles_attente'] = $stmt->fetch()['ecoles_attente'];
} else {
    // Fallback si la colonne n'existe pas encore
    $stats['ecoles_actives'] = $stats['total_ecoles']; // Considérer toutes comme actives
    $stats['ecoles_attente'] = 0;
}

// Demandes d'inscription en attente (vérifier si la table existe)
try {
    $query = "SELECT COUNT(*) as demandes_attente FROM demandes_inscription_ecoles WHERE statut = 'en_attente'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['demandes_attente'] = $stmt->fetch()['demandes_attente'];
} catch (PDOException $e) {
    // Table n'existe pas encore
    $stats['demandes_attente'] = 0;
}

// Nombre total d'utilisateurs
$check_super_admin_column = "SHOW COLUMNS FROM utilisateurs LIKE 'is_super_admin'";
$check_stmt = $db->prepare($check_super_admin_column);
$check_stmt->execute();
$super_admin_column_exists = $check_stmt->fetch();

if ($super_admin_column_exists) {
    $query = "SELECT COUNT(*) as total_utilisateurs FROM utilisateurs WHERE statut = 'actif' AND is_super_admin = FALSE";
} else {
    $query = "SELECT COUNT(*) as total_utilisateurs FROM utilisateurs WHERE statut = 'actif'";
}
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_utilisateurs'] = $stmt->fetch()['total_utilisateurs'];

// Récupérer la liste des écoles avec leurs administrateurs
try {
    if ($activee_column_exists) {
        $query = "SELECT e.*, u.nom as admin_nom, u.prenom as admin_prenom, u.email as admin_email, u.actif as admin_actif, u.derniere_connexion_at
                  FROM ecoles e 
                  LEFT JOIN utilisateurs u ON e.id = u.ecole_id AND u.niveau_acces = 'school_admin'
                  WHERE e.validation_status = 'approved' 
                  ORDER BY e.activee DESC, e.nom_ecole ASC";
    } else {
        $query = "SELECT e.*, u.nom as admin_nom, u.prenom as admin_prenom, u.email as admin_email, u.actif as admin_actif, u.derniere_connexion_at
                  FROM ecoles e 
                  LEFT JOIN utilisateurs u ON e.id = u.ecole_id AND u.role_id = 1
                  WHERE e.validation_status = 'approved' 
                  ORDER BY e.nom_ecole ASC";
    }
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ecoles = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback simple
    $query = "SELECT * FROM ecoles WHERE validation_status = 'approved' ORDER BY nom_ecole ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ecoles = $stmt->fetchAll();
    
    // Ajouter des champs vides pour la compatibilité
    foreach ($ecoles as &$ecole) {
        $ecole['admin_nom'] = null;
        $ecole['admin_prenom'] = null;
        $ecole['admin_email'] = null;
        $ecole['admin_actif'] = null;
        $ecole['derniere_connexion_at'] = null;
        $ecole['activee'] = true; // Par défaut
    }
}

// Récupérer les demandes d'inscription récentes
try {
    $query = "SELECT * FROM demandes_inscription_ecoles WHERE statut = 'en_attente' ORDER BY created_at DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $demandes_recentes = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table n'existe pas encore
    $demandes_recentes = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Administrateur - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <style>
        /* Style spécifique Super Admin pour identifier visuellement */
        .sidebar-logo i {
            color: #ffd700; /* Doré pour différencier */
        }
        .user-avatar i {
            color: #ffd700;
        }
        .menu-item.active .menu-link {
            border-left-color: #ffd700;
        }
        .menu-link:hover {
            border-left-color: #ffd700;
        }
    </style>
</head>
<body>
    <!-- Navigation latérale -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="bi bi-shield-check"></i>
                <span>Super Admin</span>
            </div>
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="bi bi-person-badge"></i>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?></div>
                <div class="user-role">Super Administrateur</div>
                <div class="user-school">Gestion Multi-Écoles</div>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-item active">
                <a href="index.php" class="menu-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="schools/index.php" class="menu-link">
                    <i class="bi bi-building"></i>
                    <span>Écoles</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="schools/requests.php" class="menu-link">
                    <i class="bi bi-envelope"></i>
                    <span>Demandes</span>
                    <?php if ($stats['demandes_attente'] > 0): ?>
                        <span class="badge bg-danger ms-auto"><?php echo $stats['demandes_attente']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="users/create-admin.php" class="menu-link">
                    <i class="bi bi-person-plus"></i>
                    <span>Créer Admin</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="users/super-admins.php" class="menu-link">
                    <i class="bi bi-shield"></i>
                    <span>Super Admins</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="reports/index.php" class="menu-link">
                    <i class="bi bi-graph-up"></i>
                    <span>Rapports</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../auth/dashboard.php" class="menu-link">
                    <i class="bi bi-house"></i>
                    <span>Dashboard Normal</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </nav>
    
    <!-- Contenu principal -->
    <main class="main-content">
        <!-- Barre supérieure -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-shield-check me-2"></i>Super Administrateur</h1>
                <p class="text-muted">Gestion centralisée de toutes les écoles Naklass</p>
            </div>
            
            <div class="topbar-actions">
                <?php if ($stats['demandes_attente'] > 0): ?>
                <a href="schools/requests.php" class="btn btn-outline-warning">
                    <i class="bi bi-envelope"></i>
                    <span class="badge bg-danger"><?php echo $stats['demandes_attente']; ?></span>
                </a>
                <?php endif; ?>
                
                <div class="dropdown">
                    <button class="btn btn-link p-0" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-4"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile/"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                        <li><a class="dropdown-item" href="../install_super_admin_simple.php"><i class="bi bi-gear me-2"></i>Configuration</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Contenu du tableau de bord -->
        <div class="content-area">
            <!-- Alert Configuration -->
            <?php if (!$activee_column_exists || !$super_admin_column_exists): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Configuration incomplète :</strong> 
                    Le système Super Admin n'est pas encore complètement configuré. 
                    <a href="../install_super_admin_simple.php" class="alert-link">Exécutez l'installation</a> 
                    pour activer toutes les fonctionnalités.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Cartes de statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_ecoles']); ?></h3>
                            <p>Écoles Total</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['ecoles_actives']); ?></h3>
                            <p>Écoles Actives</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['ecoles_attente']); ?></h3>
                            <p>En Attente</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_utilisateurs']); ?></h3>
                            <p>Utilisateurs</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertes et notifications -->
            <div class="row g-4 mb-4">
                <?php if ($stats['demandes_attente'] > 0): ?>
                <div class="col-md-6">
                    <div class="alert-card alert-warning">
                        <div class="alert-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="alert-content">
                            <h5>Nouvelles demandes</h5>
                            <p><?php echo $stats['demandes_attente']; ?> demande(s) d'inscription en attente</p>
                            <a href="schools/requests.php" class="btn btn-sm btn-outline-warning">Traiter les demandes</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['ecoles_attente'] > 0): ?>
                <div class="col-md-6">
                    <div class="alert-card alert-info">
                        <div class="alert-icon">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="alert-content">
                            <h5>Écoles en attente</h5>
                            <p><?php echo $stats['ecoles_attente']; ?> école(s) en attente d'activation</p>
                            <a href="schools/index.php" class="btn btn-sm btn-outline-info">Voir les détails</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Actions rapides -->
            <div class="row g-4 mb-4">
                <div class="col-md-12">
                    <h4 class="mb-3">Actions rapides</h4>
                    <div class="quick-actions">
                        <a href="schools/create.php" class="quick-action-btn">
                            <i class="bi bi-plus-circle"></i>
                            <div>
                                <strong>Créer une nouvelle école</strong>
                                <small class="d-block text-muted">Ajouter un établissement au système</small>
                            </div>
                        </a>
                        
                        <a href="users/create-admin.php" class="quick-action-btn">
                            <i class="bi bi-person-plus"></i>
                            <div>
                                <strong>Créer un administrateur</strong>
                                <small class="d-block text-muted">Nouvel admin pour une école</small>
                            </div>
                        </a>
                        
                        <a href="schools/index.php" class="quick-action-btn">
                            <i class="bi bi-building"></i>
                            <div>
                                <strong>Gérer toutes les écoles</strong>
                                <small class="d-block text-muted">Vue complète du système</small>
                            </div>
                        </a>
                        
                        <a href="reports/index.php" class="quick-action-btn">
                            <i class="bi bi-graph-up"></i>
                            <div>
                                <strong>Rapports globaux</strong>
                                <small class="d-block text-muted">Analytics multi-écoles</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Liste des écoles et demandes -->
            <div class="row g-4">
                <!-- Liste des écoles -->
                <div class="col-lg-8">
                    <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">🏫 Écoles dans le système</h5>
                        <a href="schools/index.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($ecoles)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-building fs-1 text-muted"></i>
                                <p class="text-muted mt-2">Aucune école dans le système</p>
                                <a href="schools/create.php" class="btn btn-primary">Créer la première école</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>École</th>
                                            <th>Administrateur</th>
                                            <th>Statut</th>
                                            <th>Dernière connexion</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ecoles as $ecole): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($ecole['nom_ecole']); ?></strong>
                                                        <?php if (isset($ecole['sigle']) && $ecole['sigle']): ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($ecole['sigle']); ?>)</small>
                                                        <?php endif; ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($ecole['regime'] ?? 'Non défini'); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (isset($ecole['admin_nom']) && $ecole['admin_nom']): ?>
                                                        <div>
                                                            <?php echo htmlspecialchars(($ecole['admin_prenom'] ?? '') . ' ' . ($ecole['admin_nom'] ?? '')); ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($ecole['admin_email'] ?? ''); ?></small>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-danger">
                                                            <i class="bi bi-exclamation-triangle me-1"></i>Aucun admin
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($ecole['activee']) && $ecole['activee']): ?>
                                                        <span class="school-status status-active">
                                                            <i class="bi bi-check-circle me-1"></i>Active
                                                        </span>
                                                    <?php elseif (isset($ecole['activee']) && !$ecole['activee']): ?>
                                                        <span class="school-status status-pending">
                                                            <i class="bi bi-clock me-1"></i>En attente
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="school-status status-active">
                                                            <i class="bi bi-info-circle me-1"></i>Standard
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($ecole['derniere_connexion_at']) && $ecole['derniere_connexion_at']): ?>
                                                        <small><?php echo date('d/m/Y H:i', strtotime($ecole['derniere_connexion_at'])); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">Jamais connecté</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="schools/view.php?id=<?php echo $ecole['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Voir détails">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if (!$ecole['admin_nom']): ?>
                                                            <a href="users/create-admin.php?ecole_id=<?php echo $ecole['id']; ?>" 
                                                               class="btn btn-outline-success" title="Créer admin">
                                                                <i class="bi bi-person-plus"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if (isset($ecole['activee']) && !$ecole['activee']): ?>
                                                            <a href="schools/activate.php?id=<?php echo $ecole['id']; ?>" 
                                                               class="btn btn-outline-warning" title="Activer">
                                                                <i class="bi bi-play"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
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

                <!-- Demandes récentes -->
                <div class="col-lg-4">
                    <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">📋 Demandes récentes</h5>
                        <a href="schools/requests.php" class="btn btn-sm btn-outline-warning">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($demandes_recentes)): ?>
                            <div class="text-center py-3">
                                <i class="bi bi-envelope fs-3 text-muted"></i>
                                <p class="text-muted mt-2 mb-0">Aucune demande en attente</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($demandes_recentes as $demande): ?>
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($demande['nom_ecole'] ?? 'École non définie'); ?></h6>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($demande['directeur_nom'] ?? ''); ?><br>
                                            <?php echo htmlspecialchars($demande['email'] ?? ''); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo isset($demande['created_at']) ? date('d/m/Y', strtotime($demande['created_at'])) : 'Date inconnue'; ?>
                                        </small>
                                    </div>
                                    <a href="schools/requests.php?id=<?php echo $demande['id'] ?? '#'; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
