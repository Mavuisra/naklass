<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Traitement des actions
$action = $_GET['action'] ?? '';
$user_id = intval($_GET['user_id'] ?? 0);
$success = '';
$errors = [];

if ($action && $user_id) {
    try {
        // Vérifier que l'utilisateur existe et appartient à l'école
        $check_user = "SELECT u.*, r.code as role_code, r.libelle as role_libelle 
                       FROM utilisateurs u 
                       JOIN roles r ON u.role_id = r.id 
                       WHERE u.id = :user_id AND u.ecole_id = :ecole_id";
        $stmt = $db->prepare($check_user);
        $stmt->execute(['user_id' => $user_id, 'ecole_id' => $_SESSION['ecole_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("Utilisateur non trouvé ou non autorisé.");
        }
        
        // Empêcher la suppression de son propre compte
        if ($user_id == $_SESSION['user_id']) {
            throw new Exception("Vous ne pouvez pas modifier ou supprimer votre propre compte.");
        }
        
        switch ($action) {
            case 'toggle_status':
                $new_status = $user['actif'] ? 0 : 1;
                $status_text = $new_status ? 'activé' : 'désactivé';
                
                $update = "UPDATE utilisateurs SET actif = :status, updated_at = NOW(), updated_by = :updated_by WHERE id = :user_id";
                $stmt = $db->prepare($update);
                $stmt->execute([
                    'status' => $new_status,
                    'updated_by' => $_SESSION['user_id'],
                    'user_id' => $user_id
                ]);
                
                logUserAction('TOGGLE_USER_STATUS', "Utilisateur {$user['prenom']} {$user['nom']} $status_text");
                $success = "L'utilisateur {$user['prenom']} {$user['nom']} a été $status_text avec succès.";
                break;
                
            case 'delete':
                // Suppression logique
                $delete = "UPDATE utilisateurs SET statut = 'supprimé_logique', updated_at = NOW(), updated_by = :updated_by WHERE id = :user_id";
                $stmt = $db->prepare($delete);
                $stmt->execute([
                    'updated_by' => $_SESSION['user_id'],
                    'user_id' => $user_id
                ]);
                
                logUserAction('DELETE_USER', "Utilisateur {$user['prenom']} {$user['nom']} supprimé logiquement");
                $success = "L'utilisateur {$user['prenom']} {$user['nom']} a été supprimé avec succès.";
                break;
                
            case 'reset_password':
                // Générer un nouveau mot de passe temporaire
                $temp_password = generateSecureToken(8);
                $password_hash = hashPassword($temp_password);
                
                $update = "UPDATE utilisateurs SET mot_de_passe_hash = :password_hash, updated_at = NOW(), updated_by = :updated_by WHERE id = :user_id";
                $stmt = $db->prepare($update);
                $stmt->execute([
                    'password_hash' => $password_hash,
                    'updated_by' => $_SESSION['user_id'],
                    'user_id' => $user_id
                ]);
                
                logUserAction('RESET_USER_PASSWORD', "Mot de passe réinitialisé pour {$user['prenom']} {$user['nom']}");
                $success = "Le mot de passe de {$user['prenom']} {$user['nom']} a été réinitialisé. Nouveau mot de passe temporaire : <strong>$temp_password</strong>";
                break;
                
            default:
                throw new Exception("Action non reconnue.");
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Paramètres de pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Paramètres de recherche et filtrage
$search = sanitize($_GET['search'] ?? '');
$role_filter = sanitize($_GET['role'] ?? '');
$statut_filter = sanitize($_GET['statut'] ?? '');

try {
    // Construction de la requête avec filtres
    $where_conditions = ["u.ecole_id = :ecole_id", "u.statut != 'supprimé_logique'"];
    $params = ['ecole_id' => $_SESSION['ecole_id']];

    if (!empty($search)) {
        $where_conditions[] = "(u.prenom LIKE :search OR u.nom LIKE :search OR u.email LIKE :search)";
        $params['search'] = "%$search%";
    }

    if (!empty($role_filter)) {
        $where_conditions[] = "r.code = :role";
        $params['role'] = $role_filter;
    }

    if ($statut_filter !== '') {
        $where_conditions[] = "u.actif = :statut";
        $params['statut'] = $statut_filter;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Compter le total d'utilisateurs
    $count_query = "SELECT COUNT(*) as total 
                    FROM utilisateurs u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE $where_clause";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_users = $stmt->fetch()['total'];

    // Récupérer les utilisateurs avec pagination
    $users_query = "SELECT u.*, r.code as role_code, r.libelle as role_libelle, r.description as role_description
                    FROM utilisateurs u
                    JOIN roles r ON u.role_id = r.id
                    WHERE $where_clause
                    ORDER BY u.nom ASC, u.prenom ASC
                    LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($users_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();

    // Statistiques générales
    $stats_query = "SELECT 
                        COUNT(*) as total_users,
                        SUM(CASE WHEN u.actif = 1 THEN 1 ELSE 0 END) as active_users,
                        SUM(CASE WHEN r.code = 'admin' THEN 1 ELSE 0 END) as admin_users,
                        SUM(CASE WHEN r.code = 'enseignant' THEN 1 ELSE 0 END) as teacher_users,
                        SUM(CASE WHEN r.code = 'direction' THEN 1 ELSE 0 END) as direction_users,
                        SUM(CASE WHEN r.code = 'secretaire' THEN 1 ELSE 0 END) as secretary_users,
                        SUM(CASE WHEN r.code = 'caissier' THEN 1 ELSE 0 END) as cashier_users
                    FROM utilisateurs u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.ecole_id = :ecole_id AND u.statut != 'supprimé_logique'";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats = $stmt->fetch();

    // Récupérer les rôles pour le filtre
    $roles_query = "SELECT code, libelle FROM roles WHERE statut = 'actif' ORDER BY niveau_hierarchie, libelle";
    $stmt = $db->prepare($roles_query);
    $stmt->execute();
    $available_roles = $stmt->fetchAll();

} catch (Exception $e) {
    $users = [];
    $total_users = 0;
    $stats = ['total_users' => 0, 'active_users' => 0, 'admin_users' => 0, 'teacher_users' => 0, 'direction_users' => 0, 'secretary_users' => 0, 'cashier_users' => 0];
    $available_roles = [];
    $errors[] = "Erreur lors de la récupération des utilisateurs: " . $e->getMessage();
}

// Calculs de pagination
$total_pages = ceil($total_users / $limit);

$page_title = "Gestion des Utilisateurs";
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
        .user-card {
            transition: all 0.3s ease;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .role-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
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
                <h1><i class="bi bi-people me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Gérez les utilisateurs de votre école</p>
            </div>
            
            <div class="topbar-actions">
                <a href="create_user.php" class="btn btn-primary">
                    <i class="bi bi-person-plus me-2"></i>Nouvel Utilisateur
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Messages d'erreur et de succès -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-people display-6"></i>
                            <h3><?php echo number_format($stats['total_users']); ?></h3>
                            <p class="mb-0">Total Utilisateurs</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-person-check display-6"></i>
                            <h3><?php echo number_format($stats['active_users']); ?></h3>
                            <p class="mb-0">Utilisateurs Actifs</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-shield-check display-6"></i>
                            <h3><?php echo number_format($stats['admin_users']); ?></h3>
                            <p class="mb-0">Administrateurs</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-person-badge display-6"></i>
                            <h3><?php echo number_format($stats['teacher_users']); ?></h3>
                            <p class="mb-0">Enseignants</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-person-workspace display-6"></i>
                            <h3><?php echo number_format($stats['secretary_users']); ?></h3>
                            <p class="mb-0">Secrétaires</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-cash-coin display-6"></i>
                            <h3><?php echo number_format($stats['cashier_users']); ?></h3>
                            <p class="mb-0">Caissiers</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtres et recherche -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Recherche</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Nom, prénom ou email...">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="role" class="form-label">Rôle</label>
                            <select class="form-select" id="role" name="role">
                                <option value="">Tous les rôles</option>
                                <?php foreach ($available_roles as $role): ?>
                                    <option value="<?php echo $role['code']; ?>" 
                                            <?php echo $role_filter === $role['code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['libelle']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="">Tous les statuts</option>
                                <option value="1" <?php echo $statut_filter === '1' ? 'selected' : ''; ?>>Actif</option>
                                <option value="0" <?php echo $statut_filter === '0' ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-2"></i>Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Liste des utilisateurs -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Utilisateurs (<?php echo $total_users; ?>)</h5>
                    <a href="create_user.php" class="btn btn-success btn-sm">
                        <i class="bi bi-person-plus me-2"></i>Ajouter
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-people display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">Aucun utilisateur trouvé</h5>
                            <p class="text-muted">Commencez par créer votre premier utilisateur.</p>
                            <a href="create_user.php" class="btn btn-primary">
                                <i class="bi bi-person-plus me-2"></i>Créer un utilisateur
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Rôle</th>
                                        <th>Contact</th>
                                        <th>Statut</th>
                                        <th>Dernière connexion</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-3">
                                                        <i class="bi bi-person-circle fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h6>
                                                        <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary role-badge">
                                                    <?php echo htmlspecialchars($user['role_libelle']); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($user['role_description'] ?? ''); ?></small>
                                            </td>
                                            <td>
                                                <div>
                                                    <i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?>
                                                </div>
                                                <?php if ($user['telephone']): ?>
                                                    <div>
                                                        <i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($user['telephone']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-indicator <?php echo $user['actif'] ? 'status-active' : 'status-inactive'; ?>"></span>
                                                <?php echo $user['actif'] ? 'Actif' : 'Inactif'; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['derniere_connexion_at']): ?>
                                                    <small><?php echo formatDateTime($user['derniere_connexion_at']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Jamais connecté</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-outline-primary"
                                                           title="Modifier">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        
                                                        <a href="?action=toggle_status&user_id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-outline-<?php echo $user['actif'] ? 'warning' : 'success'; ?>"
                                                           title="<?php echo $user['actif'] ? 'Désactiver' : 'Activer'; ?>"
                                                           onclick="return confirm('Êtes-vous sûr de vouloir <?php echo $user['actif'] ? 'désactiver' : 'activer'; ?> cet utilisateur ?')">
                                                            <i class="bi bi-<?php echo $user['actif'] ? 'pause' : 'play'; ?>"></i>
                                                        </a>
                                                        
                                                        <a href="?action=reset_password&user_id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-outline-info"
                                                           title="Réinitialiser le mot de passe"
                                                           onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur ?')">
                                                            <i class="bi bi-key"></i>
                                                        </a>
                                                        
                                                        <a href="?action=delete&user_id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-outline-danger"
                                                           title="Supprimer"
                                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Votre compte</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Pagination des utilisateurs" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&statut=<?php echo urlencode($statut_filter); ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&statut=<?php echo urlencode($statut_filter); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&statut=<?php echo urlencode($statut_filter); ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>

