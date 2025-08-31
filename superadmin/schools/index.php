<?php
/**
 * Gestion des écoles - Interface Super Admin
 * Liste et gestion de toutes les écoles du système
 */
require_once '../../includes/functions.php';

// Vérifier l'authentification et les droits Super Admin
requireAuth();
requireSuperAdmin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ecole_id = $_POST['ecole_id'] ?? '';
    
    if ($action === 'activate' && $ecole_id) {
        // Activer une école
        try {
            $query = "UPDATE ecoles SET activee = TRUE WHERE id = :ecole_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['ecole_id' => $ecole_id]);
            
            $message = "✅ École activée avec succès !";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "❌ Erreur lors de l'activation : " . $e->getMessage();
            $message_type = 'danger';
        }
    } elseif ($action === 'deactivate' && $ecole_id) {
        // Désactiver une école
        try {
            $query = "UPDATE ecoles SET activee = FALSE WHERE id = :ecole_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['ecole_id' => $ecole_id]);
            
            $message = "✅ École désactivée avec succès !";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "❌ Erreur lors de la désactivation : " . $e->getMessage();
            $message_type = 'danger';
        }
    } elseif ($action === 'delete' && $ecole_id) {
        // Supprimer une école (avec confirmation)
        try {
            $db->beginTransaction();
            
            // Supprimer d'abord les utilisateurs de l'école
            $query = "DELETE FROM utilisateurs WHERE ecole_id = :ecole_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['ecole_id' => $ecole_id]);
            
            // Puis supprimer l'école
            $query = "DELETE FROM ecoles WHERE id = :ecole_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['ecole_id' => $ecole_id]);
            
            $db->commit();
            
            $message = "✅ École supprimée avec succès !";
            $message_type = 'success';
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Erreur lors de la suppression : " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Récupérer toutes les écoles avec leurs administrateurs
try {
    $query = "SELECT e.*, u.nom as admin_nom, u.prenom as admin_prenom, u.email as admin_email, 
                     u.actif as admin_actif, u.derniere_connexion_at
              FROM ecoles e 
              LEFT JOIN utilisateurs u ON e.id = u.ecole_id AND u.role_id = 1
              ORDER BY e.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ecoles = $stmt->fetchAll();
} catch (Exception $e) {
    $ecoles = [];
    $message = "❌ Erreur lors de la récupération des écoles : " . $e->getMessage();
    $message_type = 'danger';
}

// Compter les écoles par statut
$stats = [
    'total' => count($ecoles),
    'approved' => 0,
    'pending' => 0,
    'active' => 0,
    'inactive' => 0
];

foreach ($ecoles as $ecole) {
    if ($ecole['validation_status'] === 'approved') $stats['approved']++;
    if ($ecole['validation_status'] === 'pending') $stats['pending']++;
    if (isset($ecole['activee']) && $ecole['activee']) $stats['active']++;
    if (isset($ecole['activee']) && !$ecole['activee']) $stats['inactive']++;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Écoles - Super Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/dashboard.css" rel="stylesheet">
    <link href="../../assets/css/common.css" rel="stylesheet">
    <style>
        .sidebar-logo i { color: #ffd700; }
        .user-avatar i { color: #ffd700; }
        .menu-item.active .menu-link { border-left-color: #ffd700; }
        .menu-link:hover { border-left-color: #ffd700; }
        .school-card { transition: transform 0.2s, box-shadow 0.2s; }
        .school-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .status-badge { padding: 0.5rem 1rem; border-radius: 25px; font-weight: 600; font-size: 0.875rem; }
        .status-approved { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-active { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .status-inactive { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
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
                <h1><i class="bi bi-building me-2"></i>Gestion des Écoles</h1>
                <p class="text-muted">Gérer toutes les écoles du système Naklass</p>
            </div>
            
            <div class="topbar-actions">
                <a href="../index.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i>
                    <span>Retour au tableau de bord</span>
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
            
            <!-- Cartes de statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card stats-card">
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total']); ?></h3>
                            <p>Total Écoles</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['approved']); ?></h3>
                            <p>Approuvées</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['pending']); ?></h3>
                            <p>En Attente</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-play-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['active']); ?></h3>
                            <p>Actives</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="row g-4 mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Actions rapides</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex gap-3 flex-wrap">
                                <a href="create.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Créer une école
                                </a>
                                <a href="requests.php" class="btn btn-warning">
                                    <i class="bi bi-envelope me-2"></i>Voir les demandes
                                </a>
                                <a href="../users/create-admin.php" class="btn btn-success">
                                    <i class="bi bi-person-plus me-2"></i>Créer un admin
                                </a>
                                <button class="btn btn-outline-secondary" onclick="window.print()">
                                    <i class="bi bi-printer me-2"></i>Imprimer la liste
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Liste des écoles -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Liste des écoles
                    </h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-primary"><?php echo $stats['total']; ?> école(s)</span>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($ecoles)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-building fs-1 text-muted"></i>
                            <h5 class="mt-3 text-muted">Aucune école dans le système</h5>
                            <p class="text-muted">Commencez par créer la première école ou traiter les demandes d'inscription.</p>
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="create.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Créer la première école
                                </a>
                                <a href="requests.php" class="btn btn-warning">
                                    <i class="bi bi-envelope me-2"></i>Voir les demandes
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($ecoles as $ecole): ?>
                                <div class="col-12">
                                    <div class="card school-card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <i class="bi bi-building me-2"></i>
                                                    <?php echo htmlspecialchars($ecole['nom_ecole']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    Créée le <?php echo date('d/m/Y', strtotime($ecole['created_at'] ?? 'now')); ?>
                                                </small>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <!-- Statut de validation -->
                                                <span class="status-badge status-<?php echo $ecole['validation_status'] === 'approved' ? 'approved' : 'pending'; ?>">
                                                    <?php echo $ecole['validation_status'] === 'approved' ? 'Approuvée' : 'En attente'; ?>
                                                </span>
                                                
                                                <!-- Statut d'activation -->
                                                <?php if (isset($ecole['activee'])): ?>
                                                    <span class="status-badge status-<?php echo $ecole['activee'] ? 'active' : 'inactive'; ?>">
                                                        <?php echo $ecole['activee'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Email :</strong> <?php echo htmlspecialchars($ecole['email'] ?? 'Non renseigné'); ?></p>
                                                    <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($ecole['telephone'] ?? 'Non renseigné'); ?></p>
                                                    <p><strong>Directeur :</strong> <?php echo htmlspecialchars($ecole['directeur_nom'] ?? 'Non renseigné'); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Adresse :</strong><br><?php echo htmlspecialchars($ecole['adresse'] ?? 'Non renseignée'); ?></p>
                                                    <p><strong>Administrateur :</strong><br>
                                                        <?php if ($ecole['admin_nom']): ?>
                                                            <?php echo htmlspecialchars($ecole['admin_prenom'] . ' ' . $ecole['admin_nom']); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($ecole['admin_email']); ?></small>
                                                        <?php else: ?>
                                                            <span class="text-danger">Aucun administrateur</span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <?php if ($ecole['derniere_connexion_at']): ?>
                                                <div class="mt-3">
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock me-1"></i>
                                                        Dernière connexion : <?php echo date('d/m/Y à H:i', strtotime($ecole['derniere_connexion_at'])); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="card-footer">
                                            <div class="d-flex gap-2 justify-content-between">
                                                <div class="d-flex gap-2">
                                                    <!-- Bouton Voir -->
                                                    <a href="view.php?id=<?php echo $ecole['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="bi bi-eye"></i> Voir
                                                    </a>
                                                    
                                                    <!-- Bouton Modifier -->
                                                    <a href="edit.php?id=<?php echo $ecole['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                        <i class="bi bi-pencil"></i> Modifier
                                                    </a>
                                                    
                                                    <!-- Bouton Créer Admin si pas d'admin -->
                                                    <?php if (!$ecole['admin_nom']): ?>
                                                        <a href="../users/create-admin.php?ecole_id=<?php echo $ecole['id']; ?>" class="btn btn-outline-success btn-sm">
                                                            <i class="bi bi-person-plus"></i> Créer Admin
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="d-flex gap-2">
                                                    <!-- Bouton Activer/Désactiver -->
                                                    <?php if (isset($ecole['activee'])): ?>
                                                        <?php if ($ecole['activee']): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <input type="hidden" name="ecole_id" value="<?php echo $ecole['id']; ?>">
                                                                <button type="submit" class="btn btn-warning btn-sm" 
                                                                        onclick="return confirm('Êtes-vous sûr de vouloir désactiver cette école ?')">
                                                                    <i class="bi bi-pause"></i> Désactiver
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="activate">
                                                                <input type="hidden" name="ecole_id" value="<?php echo $ecole['id']; ?>">
                                                                <button type="submit" class="btn btn-success btn-sm">
                                                                    <i class="bi bi-play"></i> Activer
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Bouton Supprimer -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="ecole_id" value="<?php echo $ecole['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('⚠️ ATTENTION : Cette action est irréversible !\n\nÊtes-vous sûr de vouloir supprimer cette école et tous ses utilisateurs ?')">
                                                            <i class="bi bi-trash"></i> Supprimer
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>
