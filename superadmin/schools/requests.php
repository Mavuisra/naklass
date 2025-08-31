<?php
/**
 * Gestion des demandes d'inscription d'écoles
 * Interface Super Admin
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
    $demande_id = $_POST['demande_id'] ?? '';
    
    if ($action === 'approve' && $demande_id) {
        // Approuver la demande et créer l'école
        try {
            $db->beginTransaction();
            
            // Récupérer les détails de la demande
            $query = "SELECT * FROM demandes_inscription_ecoles WHERE id = :id AND statut = 'en_attente'";
            $stmt = $db->prepare($query);
            $stmt->execute(['id' => $demande_id]);
            $demande = $stmt->fetch();
            
            if ($demande) {
                // Créer l'école avec les colonnes existantes
                $query = "INSERT INTO ecoles (
                    nom_ecole, adresse, telephone, email, directeur_nom, 
                    validation_status, super_admin_validated, date_creation_ecole
                ) VALUES (
                    :nom_ecole, :adresse, :telephone, :email, :directeur_nom,
                    'approved', TRUE, NOW()
                )";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'nom_ecole' => $demande['nom_ecole'],
                    'adresse' => $demande['adresse'],
                    'telephone' => $demande['telephone'],
                    'email' => $demande['email'],
                    'directeur_nom' => $demande['directeur_nom']
                ]);
                
                $ecole_id = $db->lastInsertId();
                
                // Marquer la demande comme approuvée
                $query = "UPDATE demandes_inscription_ecoles SET 
                          statut = 'approuvee', 
                          processed_by = :super_admin_id, 
                          processed_at = NOW() 
                          WHERE id = :demande_id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'super_admin_id' => $_SESSION['user_id'],
                    'demande_id' => $demande_id
                ]);
                
                $db->commit();
                
                $message = "✅ Demande approuvée avec succès ! L'école '{$demande['nom_ecole']}' a été créée.";
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Erreur lors de l'approbation : " . $e->getMessage();
            $message_type = 'danger';
        }
    } elseif ($action === 'reject' && $demande_id) {
        // Rejeter la demande
        $motif_rejet = sanitize($_POST['motif_rejet'] ?? '');
        
        try {
            $query = "UPDATE demandes_inscription_ecoles SET 
                      statut = 'rejetee', 
                      notes = :motif,
                      processed_by = :super_admin_id, 
                      processed_at = NOW() 
                      WHERE id = :demande_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'motif' => $motif_rejet,
                'super_admin_id' => $_SESSION['user_id'],
                'demande_id' => $demande_id
            ]);
            
            $message = "✅ Demande rejetée avec succès.";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "❌ Erreur lors du rejet : " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Récupérer toutes les demandes
try {
    $query = "SELECT * FROM demandes_inscription_ecoles ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $demandes = $stmt->fetchAll();
} catch (Exception $e) {
    $demandes = [];
    $message = "❌ Erreur lors de la récupération des demandes : " . $e->getMessage();
    $message_type = 'danger';
}

// Compter les demandes par statut
$stats = [
    'total' => count($demandes),
    'en_attente' => 0,
    'approuvee' => 0,
    'rejetee' => 0
];

foreach ($demandes as $demande) {
    $stats[$demande['statut']]++;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Demandes - Super Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/dashboard.css" rel="stylesheet">
    <link href="../../assets/css/common.css" rel="stylesheet">
    <style>
        .sidebar-logo i { color: #ffd700; }
        .user-avatar i { color: #ffd700; }
        .menu-item.active .menu-link { border-left-color: #ffd700; }
        .menu-link:hover { border-left-color: #ffd700; }
        .request-card { transition: transform 0.2s, box-shadow 0.2s; }
        .request-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .status-badge { padding: 0.5rem 1rem; border-radius: 25px; font-weight: 600; font-size: 0.875rem; }
        .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-approved { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-rejected { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
            
            <li class="menu-item">
                <a href="../schools/index.php" class="menu-link">
                    <i class="bi bi-building"></i>
                    <span>Écoles</span>
                </a>
            </li>
            
            <li class="menu-item active">
                <a href="requests.php" class="menu-link">
                    <i class="bi bi-envelope"></i>
                    <span>Demandes</span>
                    <?php if ($stats['en_attente'] > 0): ?>
                        <span class="badge bg-danger ms-auto"><?php echo $stats['en_attente']; ?></span>
                    <?php endif; ?>
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
                <h1><i class="bi bi-envelope me-2"></i>Gestion des Demandes d'Inscription</h1>
                <p class="text-muted">Traiter les demandes d'inscription d'écoles</p>
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
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total']); ?></h3>
                            <p>Total Demandes</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['en_attente']); ?></h3>
                            <p>En Attente</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['approuvee']); ?></h3>
                            <p>Approuvées</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['rejetee']); ?></h3>
                            <p>Rejetées</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Liste des demandes -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Demandes d'inscription d'écoles
                    </h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Imprimer
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($demandes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-envelope-open fs-1 text-muted"></i>
                            <h5 class="mt-3 text-muted">Aucune demande d'inscription</h5>
                            <p class="text-muted">Les nouvelles demandes apparaîtront ici automatiquement.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($demandes as $demande): ?>
                                <div class="col-12">
                                    <div class="card request-card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="bi bi-building me-2"></i>
                                                    <?php echo htmlspecialchars($demande['nom_ecole']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    Demande reçue le <?php echo date('d/m/Y à H:i', strtotime($demande['created_at'])); ?>
                                                </small>
                                            </div>
                                            <span class="status-badge status-<?php echo $demande['statut'] === 'en_attente' ? 'pending' : ($demande['statut'] === 'approuvee' ? 'approved' : 'rejected'); ?>">
                                                <?php 
                                                switch($demande['statut']) {
                                                    case 'en_attente': echo 'En attente'; break;
                                                    case 'approuvee': echo 'Approuvée'; break;
                                                    case 'rejetee': echo 'Rejetée'; break;
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Email :</strong> <?php echo htmlspecialchars($demande['email']); ?></p>
                                                    <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($demande['telephone'] ?? 'Non renseigné'); ?></p>
                                                    <p><strong>Directeur :</strong> <?php echo htmlspecialchars($demande['directeur_nom'] ?? 'Non renseigné'); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Adresse :</strong><br><?php echo htmlspecialchars($demande['adresse'] ?? 'Non renseignée'); ?></p>
                                                    <?php if (!empty($demande['notes'])): ?>
                                                        <p><strong>Notes :</strong><br><?php echo htmlspecialchars($demande['notes']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($demande['statut'] === 'rejetee' && !empty($demande['notes'])): ?>
                                                <div class="alert alert-danger mt-3">
                                                    <strong>Motif du rejet :</strong><br>
                                                    <?php echo htmlspecialchars($demande['notes']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($demande['statut'] === 'en_attente'): ?>
                                            <div class="card-footer">
                                                <div class="d-flex gap-2">
                                                    <!-- Bouton Approuver -->
                                                    <form method="POST" class="flex-fill">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="demande_id" value="<?php echo $demande['id']; ?>">
                                                        <button type="submit" class="btn btn-success w-100" 
                                                                onclick="return confirm('Êtes-vous sûr de vouloir approuver cette demande ? L\'école sera créée automatiquement.')">
                                                            <i class="bi bi-check-circle me-1"></i>Approuver
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- Bouton Rejeter -->
                                                    <button type="button" class="btn btn-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $demande['id']; ?>">
                                                        <i class="bi bi-x-circle me-1"></i>Rejeter
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Modal de rejet -->
                                            <div class="modal fade" id="rejectModal<?php echo $demande['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Rejeter la demande</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Vous êtes sur le point de rejeter la demande de <strong><?php echo htmlspecialchars($demande['nom_ecole']); ?></strong>.</p>
                                                                <div class="mb-3">
                                                                    <label for="motif_rejet" class="form-label">Motif du rejet :</label>
                                                                    <textarea class="form-control" id="motif_rejet" name="motif_rejet" rows="3" required 
                                                                              placeholder="Expliquez pourquoi cette demande est rejetée..."></textarea>
                                                                </div>
                                                                <input type="hidden" name="action" value="reject">
                                                                <input type="hidden" name="demande_id" value="<?php echo $demande['id']; ?>">
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                <button type="submit" class="btn btn-danger">Rejeter</button>
                                                            </div>
                                                        </form>
                                                    </div>
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
    </main>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>
