<?php
/**
 * Visualisation d'école - Interface Super Admin
 * Affiche les détails complets d'une école
 */
require_once '../../includes/functions.php';

// Vérifier l'authentification et les droits Super Admin
requireAuth();
requireSuperAdmin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Récupérer l'ID de l'école depuis l'URL
$ecole_id = $_GET['id'] ?? 0;

if (!$ecole_id) {
    header('Location: index.php');
    exit;
}

// Récupérer les informations de l'école
try {
    $query = "SELECT e.*, u.nom as admin_nom, u.prenom as admin_prenom, u.email as admin_email, 
                     u.actif as admin_actif, u.derniere_connexion_at, u.created_at as admin_created_at,
                     r.libelle as role_nom
              FROM ecoles e 
              LEFT JOIN utilisateurs u ON e.id = u.ecole_id AND u.role_id = 1
              LEFT JOIN roles r ON u.role_id = r.id
              WHERE e.id = :ecole_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $ecole_id]);
    $ecole = $stmt->fetch();
    
    if (!$ecole) {
        $message = "❌ École non trouvée !";
        $message_type = 'danger';
    }
} catch (Exception $e) {
    $message = "❌ Erreur lors de la récupération de l'école : " . $e->getMessage();
    $message_type = 'danger';
    $ecole = null;
}

// Récupérer les statistiques de l'école
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'total_students' => 0,
    'total_teachers' => 0,
    'total_classes' => 0,
    'total_courses' => 0,
    'total_inscriptions' => 0,
    'total_emploi_temps' => 0,
    'total_finance_records' => 0
];

if ($ecole) {
    try {
        // Compter les utilisateurs
        $query = "SELECT COUNT(*) as total FROM utilisateurs WHERE ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $ecole_id]);
        $stats['total_users'] = $stmt->fetch()['total'];
        
        // Compter les utilisateurs actifs
        $query = "SELECT COUNT(*) as total FROM utilisateurs WHERE ecole_id = :ecole_id AND actif = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $ecole_id]);
        $stats['active_users'] = $stmt->fetch()['total'];
        
        // Compter les élèves
        $query = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $ecole_id]);
        $stats['total_students'] = $stmt->fetch()['total'];
        
        // Compter les enseignants
        $query = "SELECT COUNT(*) as total FROM enseignants WHERE ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $ecole_id]);
        $stats['total_teachers'] = $stmt->fetch()['total'];
        
        // Compter les classes
        $query = "SELECT COUNT(*) as total FROM classes WHERE ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $ecole_id]);
        $stats['total_classes'] = $stmt->fetch()['total'];
        
        // Compter les cours
        $query = "SELECT COUNT(*) as total FROM cours WHERE ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $ecole_id]);
        $stats['total_courses'] = $stmt->fetch()['total'];
        
        // Compter les inscriptions (via les classes)
        $query = "SELECT COUNT(*) as total FROM inscriptions i 
                  INNER JOIN classes c ON i.classe_id = c.id 
                  WHERE c.ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $ecole_id]);
        $stats['total_inscriptions'] = $stmt->fetch()['total'];
        
        // Compter les emplois du temps
        $query = "SELECT COUNT(*) as total FROM emploi_du_temps WHERE ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $ecole_id]);
        $stats['total_emploi_temps'] = $stmt->fetch()['total'];
        
        // Compter les enregistrements financiers
        $query = "SELECT COUNT(*) as total FROM paiements WHERE ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $ecole_id]);
        $stats['total_finance_records'] = $stmt->fetch()['total'];
        
    } catch (Exception $e) {
        // Ignorer les erreurs si les tables n'existent pas encore
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de l'École - Super Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/dashboard.css" rel="stylesheet">
    <link href="../../assets/css/common.css" rel="stylesheet">
    <style>
        .sidebar-logo i { color: #ffd700; }
        .user-avatar i { color: #ffd700; }
        .menu-item.active .menu-link { border-left-color: #ffd700; }
        .menu-link:hover { border-left-color: #ffd700; }
        .info-card { transition: transform 0.2s, box-shadow 0.2s; }
        .info-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .status-badge { padding: 0.5rem 1rem; border-radius: 25px; font-weight: 600; font-size: 0.875rem; }
        .status-approved { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-active { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .status-inactive { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .detail-row { border-bottom: 1px solid #e9ecef; padding: 0.75rem 0; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #495057; }
        .detail-value { color: #212529; }
        .empty-value { color: #6c757d; font-style: italic; }
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
            <li class="menu-item">
                <a href="../index.php" class="menu-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            
            <li class="menu-item active">
                <a href="index.php" class="menu-link">
                    <i class="bi bi-building"></i>
                    <span>Écoles</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="requests.php" class="menu-link">
                    <i class="bi bi-envelope"></i>
                    <span>Demandes</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../users/create-admin.php" class="menu-link">
                    <i class="bi bi-person-plus"></i>
                    <span>Créer Admin</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../users/super-admins.php" class="menu-link">
                    <i class="bi bi-shield"></i>
                    <span>Super Admins</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../../auth/logout.php" class="logout-btn">
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
                <h1><i class="bi bi-eye me-2"></i>Détails de l'École</h1>
                <p class="text-muted">
                    <?php if ($ecole): ?>
                        <?php echo htmlspecialchars($ecole['nom_ecole']); ?>
                    <?php else: ?>
                        École non trouvée
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i>
                    <span>Retour à la liste</span>
                </a>
            </div>
        </header>
        
        <!-- Contenu du tableau de bord -->
        <div class="content-area">
            <!-- Messages de notification -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($ecole): ?>
                <!-- Cartes de statistiques -->
                <div class="row g-4 mb-4">
                    <!-- Statistiques principales -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stats-card">
                            <div class="stat-icon bg-white bg-opacity-25">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_users']); ?></h3>
                                <p>Total Utilisateurs</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-success">
                                <i class="bi bi-person-check"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['active_users']); ?></h3>
                                <p>Utilisateurs Actifs</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-info">
                                <i class="bi bi-mortarboard"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_students']); ?></h3>
                                <p>Étudiants</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_teachers']); ?></h3>
                                <p>Enseignants</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistiques académiques -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_classes']); ?></h3>
                                <p>Classes</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-secondary">
                                <i class="bi bi-book"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_courses']); ?></h3>
                                <p>Cours</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-dark">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_inscriptions']); ?></h3>
                                <p>Inscriptions</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-info">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_emploi_temps']); ?></h3>
                                <p>Emplois du Temps</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistiques financières -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-success">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_finance_records']); ?></h3>
                                <p>Paiements</p>
                            </div>
                        </div>
                    </div>
                </div>
                

                
                <!-- Informations détaillées -->
                <div class="row g-4">
                    <!-- Informations générales de l'école -->
                    <div class="col-lg-8">
                        <div class="card info-card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-building me-2"></i>
                                    Informations générales
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Nom de l'école :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['nom_ecole']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Code école :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['code_ecole']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Sigle :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['sigle']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Type d'établissement :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['type_etablissement']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Régime :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['regime']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Type d'enseignement :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['type_enseignement']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Langue d'enseignement :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['langue_enseignement']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Email :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['email']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['email']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Téléphone :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['telephone']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['telephone']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Fax :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['fax']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['fax']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Site web :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['site_web']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['site_web']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Directeur :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['directeur_nom']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['directeur_nom']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Contact directeur :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['directeur_contact']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['directeur_contact']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Téléphone directeur :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['directeur_telephone']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['directeur_telephone']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Email directeur :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['directeur_email']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['directeur_email']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Adresse :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['adresse']): ?>
                                                <span class="detail-value"><?php echo nl2br(htmlspecialchars($ecole['adresse'])); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseignée</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Ville :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['ville']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Province :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['province']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['province']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseignée</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Pays :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['pays']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Boîte postale :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['bp']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['bp']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseignée</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Devise :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['devise_principale']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Fuseau horaire :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['fuseau_horaire']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Numéro d'autorisation :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['numero_autorisation']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['numero_autorisation']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Date d'autorisation :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['date_autorisation']): ?>
                                                <span class="detail-value"><?php echo date('d/m/Y', strtotime($ecole['date_autorisation'])); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseignée</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Description :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['description_etablissement']): ?>
                                                <span class="detail-value"><?php echo nl2br(htmlspecialchars($ecole['description_etablissement'])); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseignée</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Logo :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['logo_path']): ?>
                                                <img src="../../<?php echo htmlspecialchars($ecole['logo_path']); ?>" 
                                                     alt="Logo de l'école" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                                            <?php else: ?>
                                                <span class="empty-value">Aucun logo</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Statut :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="badge bg-<?php echo $ecole['statut'] === 'actif' ? 'success' : ($ecole['statut'] === 'archivé' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($ecole['statut']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Options secondaire :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['options_secondaire']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['options_secondaire']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Sections humanités :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['sections_humanites']): ?>
                                                <span class="detail-value"><?php echo htmlspecialchars($ecole['sections_humanites']); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Notes de validation :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['validation_notes']): ?>
                                                <span class="detail-value"><?php echo nl2br(htmlspecialchars($ecole['validation_notes'])); ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Aucune note</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Date de création :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value">
                                                <?php echo date('d/m/Y à H:i', strtotime($ecole['created_at'] ?? 'now')); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Dernière mise à jour :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <span class="detail-value">
                                                <?php echo date('d/m/Y à H:i', strtotime($ecole['updated_at'] ?? 'now')); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Créé par :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['created_by']): ?>
                                                <span class="detail-value">Utilisateur #<?php echo $ecole['created_by']; ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non spécifié</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <span class="detail-label">Mis à jour par :</span>
                                        </div>
                                        <div class="col-md-8">
                                            <?php if ($ecole['updated_by']): ?>
                                                <span class="detail-value">Utilisateur #<?php echo $ecole['updated_by']; ?></span>
                                            <?php else: ?>
                                                <span class="empty-value">Non spécifié</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statuts et informations système -->
                    <div class="col-lg-4">
                        <div class="card info-card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-gear me-2"></i>
                                    Statuts et configuration
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Statut de validation :</label>
                                    <div>
                                        <span class="status-badge status-<?php echo $ecole['validation_status'] === 'approved' ? 'approved' : 'pending'; ?>">
                                            <?php echo $ecole['validation_status'] === 'approved' ? 'Approuvée' : 'En attente'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (isset($ecole['activee'])): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Statut d'activation :</label>
                                        <div>
                                            <span class="status-badge status-<?php echo $ecole['activee'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $ecole['activee'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label">Validée par Super Admin :</label>
                                    <div>
                                        <?php if ($ecole['super_admin_validated']): ?>
                                            <span class="badge bg-success">Oui</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Non</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Date de validation :</label>
                                    <div>
                                        <?php if ($ecole['date_validation_super_admin']): ?>
                                            <small class="text-muted"><?php echo date('d/m/Y à H:i', strtotime($ecole['date_validation_super_admin'])); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Non validée</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Validée par :</label>
                                    <div>
                                        <?php if ($ecole['validated_by_super_admin']): ?>
                                            <span class="badge bg-info">Super Admin #<?php echo $ecole['validated_by_super_admin']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Non spécifié</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Configuration complète :</label>
                                    <div>
                                        <?php if ($ecole['configuration_complete']): ?>
                                            <span class="badge bg-success">Oui</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Non</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Date de configuration :</label>
                                    <div>
                                        <?php if ($ecole['date_configuration']): ?>
                                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($ecole['date_configuration'])); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Non configurée</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Version :</label>
                                    <div>
                                        <code class="bg-light px-2 py-1 rounded"><?php echo $ecole['version']; ?></code>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Date de création école :</label>
                                    <div>
                                        <?php if ($ecole['date_creation_ecole']): ?>
                                            <small class="text-muted"><?php echo date('d/m/Y à H:i', strtotime($ecole['date_creation_ecole'])); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Non spécifiée</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Créée par visiteur :</label>
                                    <div>
                                        <?php if ($ecole['created_by_visitor']): ?>
                                            <span class="badge bg-info">Visiteur #<?php echo $ecole['created_by_visitor']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Non spécifié</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Notes internes :</label>
                                    <div>
                                        <?php if ($ecole['notes_internes']): ?>
                                            <small class="text-muted"><?php echo nl2br(htmlspecialchars($ecole['notes_internes'])); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Aucune note</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ID de l'école :</label>
                                    <div>
                                        <code class="bg-light px-2 py-1 rounded"><?php echo $ecole['id']; ?></code>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informations d'activation -->
                        <div class="card info-card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-power me-2"></i>
                                    Activation et Gestion
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Statut d'activation :</label>
                                    <div>
                                        <?php if (isset($ecole['activee'])): ?>
                                            <span class="status-badge status-<?php echo $ecole['activee'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $ecole['activee'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Non défini</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Date d'activation :</label>
                                    <div>
                                        <?php if ($ecole['date_activation']): ?>
                                            <small class="text-muted"><?php echo date('d/m/Y à H:i', strtotime($ecole['date_activation'])); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Non activée</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Activée par :</label>
                                    <div>
                                        <?php if ($ecole['activee_par']): ?>
                                            <span class="badge bg-info">Utilisateur #<?php echo $ecole['activee_par']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Non spécifié</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Actions disponibles :</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if (isset($ecole['activee'])): ?>
                                            <?php if ($ecole['activee']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <input type="hidden" name="ecole_id" value="<?php echo $ecole['id']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm" 
                                                            onclick="return confirm('Êtes-vous sûr de vouloir désactiver cette école ?')">
                                                        <i class="bi bi-pause me-1"></i>Désactiver
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="ecole_id" value="<?php echo $ecole['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="bi bi-play me-1"></i>Activer
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <a href="edit.php?id=<?php echo $ecole['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-pencil me-1"></i>Modifier
                                        </a>
                                        
                                        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                                            <i class="bi bi-printer me-1"></i>Imprimer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informations de l'administrateur -->
                        <?php if ($ecole['admin_nom']): ?>
                            <div class="card info-card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-person-badge me-2"></i>
                                        Administrateur
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Nom complet :</label>
                                        <div>
                                            <span class="detail-value">
                                                <?php echo htmlspecialchars($ecole['admin_prenom'] . ' ' . $ecole['admin_nom']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email :</label>
                                        <div>
                                            <span class="detail-value"><?php echo htmlspecialchars($ecole['admin_email']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Rôle :</label>
                                        <div>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($ecole['role_nom'] ?? 'Administrateur'); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Statut :</label>
                                        <div>
                                            <?php if ($ecole['admin_actif']): ?>
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactif</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($ecole['derniere_connexion_at']): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Dernière connexion :</label>
                                            <div>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y à H:i', strtotime($ecole['derniere_connexion_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Créé le :</label>
                                        <div>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($ecole['admin_created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card info-card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Administrateur manquant
                                    </h5>
                                </div>
                                <div class="card-body text-center">
                                    <i class="bi bi-person-x fs-1 text-warning mb-3"></i>
                                    <p class="text-muted">Cette école n'a pas encore d'administrateur.</p>
                                    <a href="../users/create-admin.php?ecole_id=<?php echo $ecole['id']; ?>" class="btn btn-success">
                                        <i class="bi bi-person-plus me-2"></i>Créer un admin
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- École non trouvée -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-exclamation-triangle fs-1 text-warning mb-3"></i>
                        <h5 class="text-muted">École non trouvée</h5>
                        <p class="text-muted">L'école que vous recherchez n'existe pas ou a été supprimée.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left me-2"></i>Retour à la liste
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>
