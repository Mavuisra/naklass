<?php
require_once '../includes/functions.php';

// Vérifier l'authentification
requireAuth();

// Rediriger les Super Admins vers leur interface dédiée
if (isSuperAdmin()) {
    redirect('/naklass/superadmin/index.php');
}

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
        redirect('login.php');
    }
} else {
    $database = new Database();
    $db = $database->getConnection();
}

// Statistiques générales pour le tableau de bord
$stats = [];

try {
    // Nombre total d'élèves actifs
    $query = "SELECT COUNT(*) as total FROM eleves WHERE statut_scolaire = 'inscrit' AND ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_eleves'] = $stmt->fetch()['total'];
    
    // Nombre d'enseignants actifs
    $query = "SELECT COUNT(*) as total FROM enseignants WHERE statut = 'actif' AND ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_enseignants'] = $stmt->fetch()['total'];
    
    // Nombre de classes actives
    $query = "SELECT COUNT(*) as total FROM classes WHERE statut = 'actif' AND ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_classes'] = $stmt->fetch()['total'];
    
    // Montant total des paiements du mois
    $query = "SELECT COALESCE(SUM(p.montant_total), 0) as total 
              FROM paiements p 
              JOIN eleves e ON p.eleve_id = e.id 
              WHERE MONTH(p.date_paiement) = MONTH(CURRENT_DATE()) 
              AND YEAR(p.date_paiement) = YEAR(CURRENT_DATE())
              AND p.statut = 'confirmé'
              AND e.ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['paiements_mois'] = $stmt->fetch()['total'];
    
    // Élèves en retard de paiement
    $query = "SELECT COUNT(DISTINCT sf.eleve_id) as total 
              FROM situation_frais sf 
              JOIN eleves e ON sf.eleve_id = e.id 
              WHERE sf.en_retard = TRUE AND sf.reste > 0 
              AND e.ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['eleves_retard'] = $stmt->fetch()['total'];
    
    // Récentes inscriptions (7 derniers jours)
    $query = "SELECT COUNT(*) as total 
              FROM inscriptions i 
              JOIN eleves e ON i.eleve_id = e.id 
              WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
              AND e.ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['nouvelles_inscriptions'] = $stmt->fetch()['total'];
    
    // Évolution des paiements par mois (12 derniers mois)
    $query = "SELECT 
                DATE_FORMAT(p.date_paiement, '%Y-%m') as mois,
                DATE_FORMAT(p.date_paiement, '%M %Y') as mois_lisible,
                COALESCE(SUM(p.montant_total), 0) as total_paiements,
                COUNT(DISTINCT p.id) as nombre_paiements,
                COUNT(DISTINCT p.eleve_id) as nombre_eleves
              FROM paiements p 
              JOIN eleves e ON p.eleve_id = e.id 
              WHERE p.date_paiement >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
              AND p.statut = 'confirmé'
              AND e.ecole_id = :ecole_id
              GROUP BY DATE_FORMAT(p.date_paiement, '%Y-%m')
              ORDER BY mois ASC";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['evolution_paiements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques des modes de paiement
    $query = "SELECT 
                p.mode_paiement,
                COUNT(*) as nombre_paiements,
                COALESCE(SUM(p.montant_total), 0) as total_montant
              FROM paiements p 
              JOIN eleves e ON p.eleve_id = e.id 
              WHERE p.date_paiement >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
              AND p.statut = 'confirmé'
              AND e.ecole_id = :ecole_id
              GROUP BY p.mode_paiement
              ORDER BY total_montant DESC";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['modes_paiement'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 des types de frais les plus payés
    $query = "SELECT 
                tf.libelle as type_frais,
                COUNT(pl.id) as nombre_paiements,
                COALESCE(SUM(pl.montant_ligne), 0) as total_montant
              FROM paiement_lignes pl
              JOIN paiements p ON pl.paiement_id = p.id
              JOIN types_frais tf ON pl.type_frais_id = tf.id
              JOIN eleves e ON p.eleve_id = e.id
              WHERE p.date_paiement >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
              AND p.statut = 'confirmé'
              AND e.ecole_id = :ecole_id
              GROUP BY tf.id, tf.libelle
              ORDER BY total_montant DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['top_types_frais'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // En cas d'erreur, initialiser avec des valeurs par défaut
    $stats = [
        'total_eleves' => 0,
        'total_enseignants' => 0,
        'total_classes' => 0,
        'paiements_mois' => 0,
        'eleves_retard' => 0,
        'nouvelles_inscriptions' => 0,
        'evolution_paiements' => [],
        'modes_paiement' => [],
        'top_types_frais' => []
    ];
}

// Récupérer les messages flash
$flash_messages = getFlashMessages();

$page_title = "Tableau de Bord";
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
            <li class="menu-item active">
                <a href="dashboard.php" class="menu-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            
            <?php if (hasRole(['admin', 'direction', 'secretaire'])): ?>
            <li class="menu-item">
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
            <a href="logout.php" class="logout-btn">
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
                <h1><i class="bi bi-speedometer2 me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Vue d'ensemble de votre établissement</p>
            </div>
            
            <div class="topbar-actions">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                    <i class="bi bi-bell"></i>
                    <span class="badge bg-danger">3</span>
                </button>
                
                <div class="dropdown">
                    <button class="btn btn-link p-0" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-4"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile/"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                        <li><a class="dropdown-item" href="../settings/"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Contenu du tableau de bord -->
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
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_eleves']); ?></h3>
                            <p>Élèves inscrits</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_enseignants']); ?></h3>
                            <p>Enseignants actifs</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_classes']); ?></h3>
                            <p>Classes actives</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatAmount($stats['paiements_mois']); ?></h3>
                            <p>Paiements ce mois</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Alertes et notifications -->
            <div class="row g-4 mb-4">
                <?php if ($stats['eleves_retard'] > 0): ?>
                <div class="col-md-6">
                    <div class="alert-card alert-warning">
                        <div class="alert-icon">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="alert-content">
                            <h5>Retards de paiement</h5>
                            <p><?php echo $stats['eleves_retard']; ?> élève(s) en retard de paiement</p>
                            <a href="../finance/retards.php" class="btn btn-sm btn-outline-warning">Voir les détails</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['nouvelles_inscriptions'] > 0): ?>
                <div class="col-md-6">
                    <div class="alert-card alert-info">
                        <div class="alert-icon">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <div class="alert-content">
                            <h5>Nouvelles inscriptions</h5>
                            <p><?php echo $stats['nouvelles_inscriptions']; ?> nouvelle(s) inscription(s) cette semaine</p>
                            <a href="../students/recent.php" class="btn btn-sm btn-outline-info">Voir les détails</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tableau des élèves qui ont payé (pour le caissier) -->
            <?php if (hasRole(['caissier'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-cash-check me-2"></i>Élèves qui ont payé</h5>
                </div>
                <div class="card-body">
                    <!-- Filtres -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="filter_date_debut" class="form-label">Date début</label>
                            <input type="date" class="form-control" id="filter_date_debut" name="filter_date_debut">
                        </div>
                        <div class="col-md-3">
                            <label for="filter_date_fin" class="form-label">Date fin</label>
                            <input type="date" class="form-control" id="filter_date_fin" name="filter_date_fin">
                        </div>
                        <div class="col-md-3">
                            <label for="filter_classe" class="form-label">Classe</label>
                            <select class="form-select" id="filter_classe" name="filter_classe">
                                <option value="">Toutes les classes</option>
                                <?php
                                $classes_query = "SELECT id, nom_classe FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom_classe";
                                $classes_stmt = $db->prepare($classes_query);
                                $classes_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                                while ($classe = $classes_stmt->fetch()) {
                                    echo '<option value="' . $classe['id'] . '">' . htmlspecialchars($classe['nom_classe']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter_statut" class="form-label">Statut</label>
                            <select class="form-select" id="filter_statut" name="filter_statut">
                                <option value="">Tous les statuts</option>
                                <option value="confirmé">Confirmé</option>
                                <option value="en_attente">En attente</option>
                                <option value="annulé">Annulé</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-primary" onclick="appliquerFiltres()">
                                <i class="bi bi-funnel me-2"></i>Appliquer les filtres
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="reinitialiserFiltres()">
                                <i class="bi bi-arrow-clockwise me-2"></i>Réinitialiser
                            </button>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="button" class="btn btn-success" onclick="exporterDonnees()">
                                <i class="bi bi-download me-2"></i>Exporter
                            </button>
                        </div>
                    </div>
                    
                    <!-- Tableau des paiements -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablePaiements">
                            <thead class="table-light">
                                <tr>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Type de frais</th>
                                    <th>Montant payé</th>
                                    <th>Date de paiement</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Récupérer les paiements avec filtres
                                $paiements_query = "SELECT 
                                    p.id as paiement_id,
                                    p.montant_total,
                                    p.date_paiement,
                                    p.statut,
                                    p.mode_paiement,
                                    p.reference_transaction,
                                    p.numero_recu,
                                    e.nom as eleve_nom,
                                    e.prenom as eleve_prenom,
                                    e.matricule,
                                    c.nom_classe,
                                    pl.montant_ligne,
                                    tf.libelle as type_frais_nom
                                FROM paiements p
                                JOIN eleves e ON p.eleve_id = e.id
                                JOIN inscriptions i ON e.id = i.eleve_id
                                JOIN classes c ON i.classe_id = c.id
                                JOIN paiement_lignes pl ON p.id = pl.paiement_id
                                JOIN types_frais tf ON pl.type_frais_id = tf.id
                                WHERE e.ecole_id = :ecole_id
                                ORDER BY p.date_paiement DESC
                                LIMIT 50";
                                
                                $paiements_stmt = $db->prepare($paiements_query);
                                $paiements_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                                
                                while ($paiement = $paiements_stmt->fetch()) {
                                    $statut_class = '';
                                    switch ($paiement['statut']) {
                                        case 'confirmé':
                                            $statut_class = 'badge bg-success';
                                            break;
                                        case 'en_attente':
                                            $statut_class = 'badge bg-warning';
                                            break;
                                        case 'annulé':
                                            $statut_class = 'badge bg-danger';
                                            break;
                                        default:
                                            $statut_class = 'badge bg-secondary';
                                    }
                                    
                                    echo '<tr>';
                                    echo '<td>';
                                    echo '<div class="d-flex align-items-center">';
                                    echo '<div class="avatar-sm me-3">';
                                    echo '<i class="bi bi-person-circle text-primary"></i>';
                                    echo '</div>';
                                    echo '<div>';
                                    echo '<div class="fw-bold">' . htmlspecialchars($paiement['eleve_nom'] . ' ' . $paiement['eleve_prenom']) . '</div>';
                                    echo '<small class="text-muted">' . htmlspecialchars($paiement['matricule']) . '</small>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</td>';
                                    echo '<td><span class="badge bg-info">' . htmlspecialchars($paiement['nom_classe']) . '</span></td>';
                                    echo '<td>' . htmlspecialchars($paiement['type_frais_nom']) . '</td>';
                                    echo '<td><strong class="text-success">' . number_format($paiement['montant_ligne'], 0, ',', ' ') . ' FC</strong></td>';
                                    echo '<td>' . date('d/m/Y', strtotime($paiement['date_paiement'])) . '</td>';
                                    echo '<td><span class="' . $statut_class . '">' . htmlspecialchars($paiement['statut']) . '</span></td>';
                                    echo '<td>';
                                    echo '<div class="btn-group btn-group-sm">';
                                    echo '<button type="button" class="btn btn-outline-primary" onclick="voirDetails(' . $paiement['paiement_id'] . ')" title="Voir détails">';
                                    echo '<i class="bi bi-eye"></i>';
                                    echo '</button>';
                                    echo '<button type="button" class="btn btn-outline-success" onclick="imprimerRecu(' . $paiement['paiement_id'] . ')" title="Imprimer reçu">';
                                    echo '<i class="bi bi-printer"></i>';
                                    echo '</button>';
                                    echo '</div>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <nav aria-label="Pagination des paiements">
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1">Précédent</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Suivant</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Graphiques et tableaux -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-graph-up me-2"></i>Évolution des paiements</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($stats['evolution_paiements'])): ?>
                                <canvas id="paymentsChart" height="300"></canvas>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-graph-up fs-1 text-muted"></i>
                                    <p class="text-muted mt-2">Aucune donnée de paiement disponible</p>
                                    <small>Les données apparaîtront après les premiers paiements</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="row g-3">
                        <!-- Modes de paiement -->
                        <?php if (!empty($stats['modes_paiement'])): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6><i class="bi bi-credit-card me-2"></i>Modes de paiement</h6>
                                </div>
                                <div class="card-body p-3">
                                    <?php foreach (array_slice($stats['modes_paiement'], 0, 3) as $mode): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary"><?php echo htmlspecialchars(ucfirst($mode['mode_paiement'])); ?></span>
                                            <span class="fw-bold"><?php echo formatAmount($mode['total_montant']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Types de frais populaires -->
                        <?php if (!empty($stats['top_types_frais'])): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6><i class="bi bi-tags me-2"></i>Types de frais populaires</h6>
                                </div>
                                <div class="card-body p-3">
                                    <?php foreach ($stats['top_types_frais'] as $frais): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-truncate me-2"><?php echo htmlspecialchars($frais['type_frais']); ?></span>
                                            <span class="fw-bold text-success"><?php echo formatAmount($frais['total_montant']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Actions rapides -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6><i class="bi bi-list-check me-2"></i>Actions rapides</h6>
                                </div>
                                <div class="card-body p-3">
                                    <div class="quick-actions">
                                        <?php if (hasRole(['admin', 'direction', 'secretaire'])): ?>
                                        <a href="../students/add.php" class="quick-action-btn">
                                            <i class="bi bi-person-plus"></i>
                                            <span>Nouvel élève</span>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasRole(['admin', 'direction', 'caissier'])): ?>
                                        <a href="../finance/payment.php" class="quick-action-btn">
                                            <i class="bi bi-cash"></i>
                                            <span>Nouveau paiement</span>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
                                        <a href="../grades/entry.php" class="quick-action-btn">
                                            <i class="bi bi-pencil"></i>
                                            <span>Saisir notes</span>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasRole(['admin', 'direction'])): ?>
                                        <a href="../reports/generate.php" class="quick-action-btn">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Générer rapport</span>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Modal des notifications -->
    <div class="modal fade" id="notificationsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-bell me-2"></i>Notifications
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="notification-item">
                        <i class="bi bi-exclamation-circle text-warning"></i>
                        <div>
                            <strong>Retards de paiement</strong>
                            <p class="mb-0"><?php echo $stats['eleves_retard']; ?> élèves en retard de paiement</p>
                            <small class="text-muted">Il y a 2 heures</small>
                        </div>
                    </div>
                    
                    <div class="notification-item">
                        <i class="bi bi-person-plus text-success"></i>
                        <div>
                            <strong>Nouvelles inscriptions</strong>
                            <p class="mb-0"><?php echo $stats['nouvelles_inscriptions']; ?> nouvelles inscriptions cette semaine</p>
                            <small class="text-muted">Il y a 1 jour</small>
                        </div>
                    </div>
                    
                    <div class="notification-item">
                        <i class="bi bi-calendar-check text-info"></i>
                        <div>
                            <strong>Fin de trimestre</strong>
                            <p class="mb-0">Le trimestre se termine dans 15 jours</p>
                            <small class="text-muted">Il y a 3 jours</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary">Marquer comme lues</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/dashboard-enhanced.js"></script>
    
    <!-- Données PHP pour les graphiques -->
    <script>
        // Données d'évolution des paiements depuis la base de données
        const evolutionPaiements = <?php echo json_encode($stats['evolution_paiements']); ?>;
        const modesPaiement = <?php echo json_encode($stats['modes_paiement']); ?>;
        const topTypesFrais = <?php echo json_encode($stats['top_types_frais']); ?>;
    </script>
    
    <!-- Script pour le tableau des paiements -->
    <script>
        // Fonction pour appliquer les filtres
        function appliquerFiltres() {
            const dateDebut = document.getElementById('filter_date_debut').value;
            const dateFin = document.getElementById('filter_date_fin').value;
            const classe = document.getElementById('filter_classe').value;
            const statut = document.getElementById('filter_statut').value;
            
            // Ici vous pouvez ajouter la logique pour filtrer le tableau
            // ou faire une requête AJAX pour récupérer les données filtrées
            console.log('Filtres appliqués:', { dateDebut, dateFin, classe, statut });
            
            // Exemple : masquer les lignes qui ne correspondent pas aux filtres
            const tbody = document.querySelector('#tablePaiements tbody');
            const rows = tbody.querySelectorAll('tr');
            
            rows.forEach(row => {
                let visible = true;
                
                // Filtre par classe
                if (classe && !row.querySelector('td:nth-child(2)').textContent.includes(classe)) {
                    visible = false;
                }
                
                // Filtre par statut
                if (statut && !row.querySelector('td:nth-child(6)').textContent.includes(statut)) {
                    visible = false;
                }
                
                row.style.display = visible ? '' : 'none';
            });
        }
        
        // Fonction pour réinitialiser les filtres
        function reinitialiserFiltres() {
            document.getElementById('filter_date_debut').value = '';
            document.getElementById('filter_date_fin').value = '';
            document.getElementById('filter_classe').value = '';
            document.getElementById('filter_statut').value = '';
            
            // Afficher toutes les lignes
            const rows = document.querySelectorAll('#tablePaiements tbody tr');
            rows.forEach(row => row.style.display = '');
        }
        
        // Fonction pour exporter les données
        function exporterDonnees() {
            // Ici vous pouvez ajouter la logique d'export (CSV, PDF, etc.)
            alert('Fonctionnalité d\'export à implémenter');
        }
        
        // Fonction pour voir les détails d'un paiement
        function voirDetails(paiementId) {
            // Ici vous pouvez ajouter la logique pour afficher les détails
            alert('Détails du paiement ' + paiementId + ' à implémenter');
        }
        
        // Fonction pour imprimer un reçu
        function imprimerRecu(paiementId) {
            // Ici vous pouvez ajouter la logique d'impression
            alert('Impression du reçu ' + paiementId + ' à implémenter');
        }
        
        // Initialiser les filtres avec la date du jour
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            document.getElementById('filter_date_debut').value = firstDay.toISOString().split('T')[0];
            document.getElementById('filter_date_fin').value = today.toISOString().split('T')[0];
        });
    </script>
</body>
</html>
