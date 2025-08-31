<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Paramètres de pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

    // Paramètres de recherche et filtrage
    $search = sanitize($_GET['search'] ?? '');
    $statut_filter = sanitize($_GET['statut'] ?? '');

try {
    // Construction de la requête avec filtres
    $where_conditions = ["c.ecole_id = :ecole_id"];
    $params = ['ecole_id' => $_SESSION['ecole_id']];

    if (!empty($search)) {
        $where_conditions[] = "(c.nom_cours LIKE :search OR c.code_cours LIKE :search OR c.description LIKE :search)";
        $params['search'] = "%$search%";
    }

    if (!empty($statut_filter)) {
        $where_conditions[] = "c.statut = :statut";
        $params['statut'] = $statut_filter;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Compter le total de matières
    $count_query = "SELECT COUNT(*) as total FROM cours c WHERE $where_clause";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_matieres = $stmt->fetch()['total'];

    // Récupérer les matières avec pagination
    $matieres_query = "SELECT c.*, 
                              u1.prenom as created_by_prenom, u1.nom as created_by_nom,
                              u2.prenom as updated_by_prenom, u2.nom as updated_by_nom,
                              COUNT(DISTINCT cc.id) as nombre_classes,
                              COUNT(DISTINCT cc.enseignant_id) as nombre_enseignants
                       FROM cours c
                       LEFT JOIN utilisateurs u1 ON c.created_by = u1.id
                       LEFT JOIN utilisateurs u2 ON c.updated_by = u2.id
                       LEFT JOIN classe_cours cc ON c.id = cc.cours_id AND cc.statut = 'actif'
                       WHERE $where_clause
                       GROUP BY c.id
                       ORDER BY c.nom_cours ASC
                       LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($matieres_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $matieres = $stmt->fetchAll();

    // Statistiques générales
    $stats_query = "SELECT 
                        COUNT(*) as total_matieres,
                        SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as matieres_actives,
                        COUNT(*) as total_toutes
                    FROM cours 
                    WHERE ecole_id = :ecole_id";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats = $stmt->fetch();

} catch (Exception $e) {
    $matieres = [];
    $total_matieres = 0;
    $stats = ['total_matieres' => 0, 'matieres_actives' => 0, 'total_toutes' => 0];
    error_log("Erreur lors de la récupération des matières: " . $e->getMessage());
    // Debug: afficher l'erreur pour le développement
    if (isset($_GET['debug'])) {
        echo "<div class='alert alert-danger'>Erreur SQL: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Calculs de pagination
$total_pages = ceil($total_matieres / $limit);

$page_title = "Gestion des Matières";
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
        .matiere-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .matiere-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #0077b6;
        }
        
        .coefficient-badge {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
        }
        
        .type-badge {
            font-size: 0.75rem;
        }
        
        .search-container {
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
                <h1><i class="bi bi-book me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Gérez les matières et cours de votre établissement</p>
            </div>
            
            <div class="topbar-actions">
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-2"></i>Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="create.php"><i class="bi bi-plus-circle me-2"></i>Nouvelle Matière</a></li>
                        <li><a class="dropdown-item" href="import.php"><i class="bi bi-upload me-2"></i>Importer</a></li>
                        <li><a class="dropdown-item" href="export.php"><i class="bi bi-download me-2"></i>Exporter</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../classes/"><i class="bi bi-building me-2"></i>Gérer les Classes</a></li>
                    </ul>
                </div>
                
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nouvelle Matière
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
                            <i class="bi bi-book"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_matieres']); ?></h3>
                            <p>Total Matières</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['matieres_actives']); ?></h3>
                            <p>Actives</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-bookmark"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_toutes']); ?></h3>
                            <p>Total</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-star"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_matieres']); ?></h3>
                            <p>Affectées</p>
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
                            <div class="quick-actions">
                                <a href="create.php" class="quick-action-btn">
                                    <i class="bi bi-plus-circle"></i>
                                    <span>Nouvelle matière</span>
                                </a>
                                
                                <a href="../classes/" class="quick-action-btn">
                                    <i class="bi bi-building"></i>
                                    <span>Gérer classes</span>
                                </a>
                                
                                <a href="../grades/" class="quick-action-btn">
                                    <i class="bi bi-journal-text"></i>
                                    <span>Saisir notes</span>
                                </a>
                                
                                <a href="export.php" class="quick-action-btn">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    <span>Exporter matières</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-info-circle me-2"></i>Informations</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-book-half text-primary fs-3"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Gestion des Matières</h6>
                                    <small class="text-muted">Créez, modifiez et assignez les matières aux classes</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-link-45deg text-success fs-3"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Assignations</h6>
                                    <small class="text-muted">Liez les matières aux classes et enseignants</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-calculator text-info fs-3"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Coefficients</h6>
                                    <small class="text-muted">Configurez les poids pour les moyennes</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtres et recherche -->
            <div class="search-container p-4 mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Rechercher</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Nom, code ou description...">
                        </div>
                    </div>
                    

                    
                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="">Tous les statuts</option>
                            <option value="actif" <?php echo $statut_filter === 'actif' ? 'selected' : ''; ?>>
                                Actif
                            </option>
                            <option value="archivé" <?php echo $statut_filter === 'archivé' ? 'selected' : ''; ?>>
                                Archivé
                            </option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter"></i> Filtrer
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Liste des matières -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list me-2"></i>Liste des Matières
                        <span class="badge bg-primary ms-2"><?php echo $total_matieres; ?></span>
                    </h5>
                    <div class="d-flex gap-2">
                        <a href="import.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-upload me-1"></i>Importer
                        </a>
                        <a href="export.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-download me-1"></i>Exporter
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($matieres)): ?>
                        <div class="text-center p-5">
                            <i class="bi bi-book display-4 text-muted mb-3"></i>
                            <h6>Aucune matière trouvée</h6>
                            <p class="text-muted">
                                <?php if (!empty($search) || !empty($statut_filter)): ?>
                                    Aucune matière ne correspond à vos critères de recherche.
                                    <br><a href="index.php">Afficher toutes les matières</a>
                                <?php else: ?>
                                    Commencez par créer vos premières matières.
                                <?php endif; ?>
                            </p>
                            <a href="create.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Créer une Matière
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($matieres as $matiere): ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card matiere-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title mb-1">
                                                        <?php echo htmlspecialchars($matiere['nom_cours']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        Code: <?php echo htmlspecialchars($matiere['code_cours']); ?>
                                                    </small>
                                                </div>
                                                <div class="d-flex flex-column align-items-end gap-1">
                                                    <span class="badge coefficient-badge">
                                                        Coeff: <?php echo $matiere['coefficient']; ?>
                                                    </span>
                                                    <span class="badge statut-badge <?php echo $matiere['statut'] === 'actif' ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo ucfirst($matiere['statut']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($matiere['description']): ?>
                                                <p class="card-text small text-muted mb-3">
                                                    <?php echo htmlspecialchars(substr($matiere['description'], 0, 100)); ?>
                                                    <?php echo strlen($matiere['description']) > 100 ? '...' : ''; ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-6">
                                                    <div class="border-end">
                                                        <strong><?php echo $matiere['nombre_classes']; ?></strong>
                                                        <small class="d-block text-muted">Classes</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <strong><?php echo $matiere['nombre_enseignants']; ?></strong>
                                                    <small class="d-block text-muted">Enseignants</small>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view.php?id=<?php echo $matiere['id']; ?>" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $matiere['id']; ?>" 
                                                       class="btn btn-outline-secondary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="assign.php?id=<?php echo $matiere['id']; ?>" 
                                                       class="btn btn-outline-success">
                                                        <i class="bi bi-link-45deg"></i>
                                                    </a>
                                                </div>
                                                <span class="badge <?php echo $matiere['actif'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $matiere['actif'] ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="card-footer text-muted small">
                                            <i class="bi bi-clock me-1"></i>
                                            Créé le <?php echo date('d/m/Y', strtotime($matiere['created_at'])); ?>
                                            <?php if ($matiere['created_by_prenom']): ?>
                                                par <?php echo htmlspecialchars($matiere['created_by_prenom'] . ' ' . $matiere['created_by_nom']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Navigation des matières">
                                <ul class="pagination justify-content-center mt-4">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&statut=<?php echo urlencode($statut_filter); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&statut=<?php echo urlencode($statut_filter); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&statut=<?php echo urlencode($statut_filter); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                            
                            <div class="text-center text-muted">
                                Page <?php echo $page; ?> sur <?php echo $total_pages; ?> 
                                (<?php echo $total_matieres; ?> matière<?php echo $total_matieres > 1 ? 's' : ''; ?> au total)
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        // Animation des cartes au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.matiere-card');
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
