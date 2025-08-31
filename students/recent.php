<?php
/**
 * Page des Inscriptions Récentes - Tableau de Bord des Étudiants
 * Affiche les inscriptions récentes et les statistiques des étudiants
 */

require_once '../includes/functions.php';
require_once '../config/photo_config.php';

// Vérifier l'authentification
requireAuth();

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier que toutes les données de session sont présentes
if (!isset($_SESSION['ecole_id']) || empty($_SESSION['ecole_id'])) {
    // Recharger les informations utilisateur depuis la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    $user_query = "SELECT u.*, r.code as role_code, e.nom_ecole as nom_ecole 
                   FROM utilisateurs u 
                   JOIN roles r ON u.role_id = r.id 
                   JOIN ecoles e ON u.ecole_id = e.id 
                   WHERE u.id = :user_id AND u.actif = 1 AND u.statut = 'actif'";
    
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user_data = $user_stmt->fetch();
    
    if ($user_data) {
        $_SESSION['ecole_id'] = $user_data['ecole_id'];
        $_SESSION['ecole_nom'] = $user_data['nom_ecole'];
        $_SESSION['user_role'] = $user_data['role_code'];
    } else {
        // L'utilisateur n'existe plus ou n'est plus actif
        session_destroy();
        redirect('../auth/login.php');
    }
} else {
    $database = new Database();
    $db = $database->getConnection();
}

// Statistiques des inscriptions récentes
$stats = [];
$recent_inscriptions = [];

try {
    // Inscriptions des 7 derniers jours
    $query = "SELECT COUNT(*) as total 
              FROM inscriptions i 
              JOIN eleves e ON i.eleve_id = e.id 
              WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
              AND e.ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['inscriptions_7_jours'] = $stmt->fetch()['total'];
    
    // Inscriptions du mois en cours
    $query = "SELECT COUNT(*) as total 
              FROM inscriptions i 
              JOIN eleves e ON i.eleve_id = e.id 
              WHERE MONTH(i.created_at) = MONTH(CURRENT_DATE()) 
              AND YEAR(i.created_at) = YEAR(CURRENT_DATE())
              AND e.ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['inscriptions_mois'] = $stmt->fetch()['total'];
    
    // Total des inscriptions actives
    $query = "SELECT COUNT(*) as total 
              FROM inscriptions i 
              JOIN eleves e ON i.eleve_id = e.id 
              WHERE i.statut = 'actif' 
              AND e.ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_inscriptions_actives'] = $stmt->fetch()['total'];
    
    // Inscriptions en attente
    $query = "SELECT COUNT(*) as total 
              FROM inscriptions i 
              JOIN eleves e ON i.eleve_id = e.id 
              WHERE i.statut = 'en_attente' 
              AND e.ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['inscriptions_en_attente'] = $stmt->fetch()['total'];
    
    // Récupérer les inscriptions récentes (10 dernières) avec le montant réel payé
    $query = "SELECT i.id, i.eleve_id, i.classe_id, i.statut, i.created_at, i.montant_inscription,
                     e.nom as eleve_nom, e.prenom as eleve_prenom, e.photo_path,
                     c.nom_classe as classe_nom, c.niveau as classe_niveau,
                     COALESCE(SUM(p.montant_total), 0) as montant_paye,
                     p.monnaie as devise_paiement
              FROM inscriptions i 
              JOIN eleves e ON i.eleve_id = e.id 
              LEFT JOIN classes c ON i.classe_id = c.id 
              LEFT JOIN paiements p ON e.id = p.eleve_id AND p.statut = 'confirmé'
              WHERE e.ecole_id = :ecole_id 
              GROUP BY i.id, i.eleve_id, i.classe_id, i.statut, i.created_at, i.montant_inscription,
                       e.nom, e.prenom, e.photo_path, c.nom_classe, c.niveau, p.monnaie
              ORDER BY i.created_at DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $recent_inscriptions = $stmt->fetchAll();
    
    // Statistiques par classe
    $query = "SELECT c.nom_classe as classe_nom, c.niveau as classe_niveau,
                     COUNT(i.id) as total_inscriptions,
                     COUNT(CASE WHEN i.statut = 'actif' THEN 1 END) as inscriptions_actives
              FROM classes c 
              LEFT JOIN inscriptions i ON c.id = i.classe_id 
              LEFT JOIN eleves e ON i.eleve_id = e.id AND e.ecole_id = c.ecole_id
              WHERE c.ecole_id = :ecole_id 
              GROUP BY c.id, c.nom_classe, c.niveau
              ORDER BY c.niveau, c.nom_classe";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats_par_classe = $stmt->fetchAll();
    
} catch (Exception $e) {
    // En cas d'erreur, initialiser avec des valeurs par défaut
    $stats = [
        'inscriptions_7_jours' => 0,
        'inscriptions_mois' => 0,
        'total_inscriptions_actives' => 0,
        'inscriptions_en_attente' => 0
    ];
    $recent_inscriptions = [];
    $stats_par_classe = [];
}

// Récupérer les messages flash
$flash_messages = getFlashMessages();

$page_title = "Inscriptions Récentes";
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
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="bi bi-mortarboard-fill"></i>
                <span>Naklass</span>
            </div>
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="bi bi-person-circle"></i>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']; ?></div>
                <div class="user-role"><?php echo ROLES[$_SESSION['user_role']] ?? $_SESSION['user_role']; ?></div>
                <div class="user-school"><?php echo $_SESSION['ecole_nom']; ?></div>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="../auth/dashboard.php" class="menu-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            
            <?php if (hasRole(['admin', 'direction', 'secretaire'])): ?>
            <li class="menu-item active">
                <a href="../students/" class="menu-link">
                    <i class="bi bi-people"></i>
                    <span>Élèves</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../classes/" class="menu-link">
                    <i class="bi bi-building"></i>
                    <span>Classes</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
            <li class="menu-item">
                <a href="../teachers/" class="menu-link">
                    <i class="bi bi-person-badge"></i>
                    <span>Enseignants</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction', 'caissier'])): ?>
            <li class="menu-item">
                <a href="../finance/" class="menu-link">
                    <i class="bi bi-cash-coin"></i>
                    <span>Finances</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
            <li class="menu-item">
                <a href="../grades/" class="menu-link">
                    <i class="bi bi-journal-text"></i>
                    <span>Notes & Bulletins</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction'])): ?>
            <li class="menu-item">
                <a href="../reports/" class="menu-link">
                    <i class="bi bi-graph-up"></i>
                    <span>Rapports</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../settings/" class="menu-link">
                    <i class="bi bi-gear"></i>
                    <span>Paramètres</span>
                </a>
            </li>
            <?php endif; ?>
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
                <h1><i class="bi bi-clock-history me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Suivi des inscriptions récentes et des nouveaux étudiants</p>
            </div>
            
            <div class="topbar-actions">
                <a href="add.php" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i>
                    <span>Nouvelle Inscription</span>
                </a>
                
                <div class="dropdown">
                    <button class="btn btn-link p-0" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-4"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile/"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                        <li><a class="dropdown-item" href="../settings/"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Contenu de la page -->
        <div class="content-area">
            <!-- Messages flash -->
            <?php foreach ($flash_messages as $type => $message): ?>
                <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            
            <!-- Cartes de statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-calendar-plus"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['inscriptions_7_jours']); ?></h3>
                            <p>7 derniers jours</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-calendar-month"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['inscriptions_mois']); ?></h3>
                            <p>Ce mois</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_inscriptions_actives']); ?></h3>
                            <p>Inscriptions actives</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['inscriptions_en_attente']); ?></h3>
                            <p>En attente</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Inscriptions récentes -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2"></i>
                                Inscriptions Récentes
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_inscriptions)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Aucune inscription récente trouvée</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Étudiant</th>
                                                <th>Classe</th>
                                                <th>Date d'inscription</th>
                                                <th>Statut</th>
                                                <th>Montant</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_inscriptions as $inscription): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($inscription['photo_path'] && photoExists($inscription['photo_path'])): ?>
                                                                <img src="<?php echo getPhotoUrl($inscription['photo_path']); ?>" 
                                                                     class="rounded-circle me-2" width="32" height="32" 
                                                                     alt="Photo de profil"
                                                                     style="object-fit: cover;">
                                                            <?php else: ?>
                                                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                                                     style="width: 32px; height: 32px;">
                                                                    <i class="bi bi-person text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($inscription['eleve_prenom'] . ' ' . $inscription['eleve_nom']); ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($inscription['classe_nom']): ?>
                                                            <span class="badge bg-primary"><?php echo htmlspecialchars($inscription['classe_nom']); ?></span>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($inscription['classe_niveau']); ?></small>
                                                        <?php else: ?>
                                                            <span class="text-muted">Non assigné</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="text-nowrap">
                                                            <?php echo date('d/m/Y', strtotime($inscription['created_at'])); ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo date('H:i', strtotime($inscription['created_at'])); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = '';
                                                        $status_text = '';
                                                        switch ($inscription['statut']) {
                                                            case 'actif':
                                                                $status_class = 'bg-success';
                                                                $status_text = 'Active';
                                                                break;
                                                            case 'en_attente':
                                                                $status_class = 'bg-warning';
                                                                $status_text = 'En attente';
                                                                break;
                                                            case 'terminee':
                                                                $status_class = 'bg-info';
                                                                $status_text = 'Terminée';
                                                                break;
                                                            case 'annulee':
                                                                $status_class = 'bg-danger';
                                                                $status_text = 'Annulée';
                                                                break;
                                                            default:
                                                                $status_class = 'bg-secondary';
                                                                $status_text = ucfirst($inscription['statut']);
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($inscription['montant_paye'] > 0): ?>
                                                            <span class="fw-bold"><?php echo number_format($inscription['montant_paye'], 0, ',', ' '); ?> <?php echo $inscription['devise_paiement'] ?? 'CDF'; ?></span>
                                                        <?php elseif ($inscription['montant_inscription'] > 0): ?>
                                                            <span class="fw-bold text-muted"><?php echo number_format($inscription['montant_inscription'], 0, ',', ' '); ?> CDF</span>
                                                            <br><small class="text-muted">(Montant d'inscription)</small>
                                                        <?php else: ?>
                                                            <span class="text-muted">Aucun paiement</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="view.php?id=<?php echo $inscription['eleve_id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary" 
                                                               title="Voir le profil">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="edit.php?id=<?php echo $inscription['eleve_id']; ?>" 
                                                               class="btn btn-sm btn-outline-warning" 
                                                               title="Modifier">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
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
            </div>
            
            <!-- Statistiques par classe -->
            <div class="row g-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bar-chart me-2"></i>
                                Répartition par Classe
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($stats_par_classe)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-bar-chart text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Aucune donnée disponible</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($stats_par_classe as $classe): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="border rounded p-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($classe['classe_nom']); ?></h6>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($classe['classe_niveau']); ?></span>
                                                </div>
                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <div class="fw-bold text-primary"><?php echo number_format($classe['total_inscriptions']); ?></div>
                                                        <small class="text-muted">Total</small>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="fw-bold text-success"><?php echo number_format($classe['inscriptions_actives']); ?></div>
                                                        <small class="text-muted">Actives</small>
                                                    </div>
                                                </div>
                                                <?php if ($classe['total_inscriptions'] > 0): ?>
                                                    <div class="progress mt-2" style="height: 6px;">
                                                        <div class="progress-bar bg-success" 
                                                             style="width: <?php echo ($classe['inscriptions_actives'] / $classe['total_inscriptions']) * 100; ?>%">
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
