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
$specialite_filter = sanitize($_GET['specialite'] ?? '');

try {
    // Construction de la requête avec filtres
    $where_conditions = ["e.ecole_id = :ecole_id"];
    $params = ['ecole_id' => $_SESSION['ecole_id']];

    if (!empty($search)) {
        $where_conditions[] = "(e.nom LIKE :search OR e.prenom LIKE :search OR e.matricule_enseignant LIKE :search OR e.email LIKE :search)";
        $params['search'] = "%$search%";
    }

    if (!empty($statut_filter)) {
        $where_conditions[] = "e.statut_record = :statut";
        $params['statut'] = $statut_filter;
    }

    if (!empty($specialite_filter)) {
        $where_conditions[] = "JSON_SEARCH(e.specialites, 'one', :specialite) IS NOT NULL";
        $params['specialite'] = $specialite_filter;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Compter le total d'enseignants
    $count_query = "SELECT COUNT(*) as total FROM enseignants e WHERE $where_clause";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_enseignants = $stmt->fetch()['total'];

    // Récupérer les enseignants avec pagination
    $enseignants_query = "SELECT e.*, 
                                 COUNT(DISTINCT cc.id) as nombre_cours_assignes,
                                 COUNT(DISTINCT c.id) as nombre_classes
                          FROM enseignants e
                          LEFT JOIN classe_cours cc ON e.id = cc.enseignant_id AND cc.statut = 'actif'
                          LEFT JOIN classes c ON cc.classe_id = c.id AND c.statut = 'actif'
                          WHERE $where_clause
                          GROUP BY e.id
                          ORDER BY e.nom ASC, e.prenom ASC
                          LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($enseignants_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $enseignants = $stmt->fetchAll();

    // Statistiques générales
    $stats_query = "SELECT 
                        COUNT(*) as total_enseignants,
                        SUM(CASE WHEN statut_record = 'actif' THEN 1 ELSE 0 END) as enseignants_actifs,
                        SUM(CASE WHEN sexe = 'M' THEN 1 ELSE 0 END) as hommes,
                        SUM(CASE WHEN sexe = 'F' THEN 1 ELSE 0 END) as femmes,
                        AVG(experience_annees) as experience_moyenne
                    FROM enseignants 
                    WHERE ecole_id = :ecole_id";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats = $stmt->fetch();

    // Récupérer les spécialités disponibles pour le filtre
    $specialites_query = "SELECT DISTINCT 
                             JSON_UNQUOTE(JSON_EXTRACT(specialites, '$[*]')) as specialite
                          FROM enseignants 
                          WHERE ecole_id = :ecole_id 
                          AND specialites IS NOT NULL 
                          AND specialites != '[]'";
    
    $stmt = $db->prepare($specialites_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $specialites_disponibles = $stmt->fetchAll();

} catch (Exception $e) {
    $enseignants = [];
    $total_enseignants = 0;
    $stats = ['total_enseignants' => 0, 'enseignants_actifs' => 0, 'hommes' => 0, 'femmes' => 0, 'experience_moyenne' => 0];
    $specialites_disponibles = [];
    error_log("Erreur lors de la récupération des enseignants: " . $e->getMessage());
    // Debug: afficher l'erreur pour le développement
    if (isset($_GET['debug'])) {
        echo "<div class='alert alert-danger'>Erreur SQL: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Calculs de pagination
$total_pages = ceil($total_enseignants / $limit);

$page_title = "Gestion des Enseignants";
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
        .enseignant-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .enseignant-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #0077b6;
        }
        
        .experience-badge {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
        }
        
        .statut-badge {
            font-size: 0.75rem;
        }
        
        .search-container {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        
        .specialite-tag {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
            border-radius: 15px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            margin: 0.1rem;
            display: inline-block;
        }
        
        .photo-enseignant {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .photo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
                <h1><i class="bi bi-person-workspace me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Gérez le personnel enseignant de votre établissement</p>
            </div>
            
            <div class="topbar-actions">
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-2"></i>Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="create.php"><i class="bi bi-plus-circle me-2"></i>Nouvel Enseignant</a></li>
                        <li><a class="dropdown-item" href="import.php"><i class="bi bi-upload me-2"></i>Importer</a></li>
                        <li><a class="dropdown-item" href="export.php"><i class="bi bi-download me-2"></i>Exporter</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../matieres/"><i class="bi bi-book me-2"></i>Gérer les Matières</a></li>
                    </ul>
                </div>
                
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nouvel Enseignant
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
                            <i class="bi bi-person-workspace"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_enseignants']); ?></h3>
                            <p>Total Enseignants</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['enseignants_actifs']); ?></h3>
                            <p>Enseignants Actifs</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-gender-male"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['hommes']); ?></h3>
                            <p>Hommes</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-gender-female"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['femmes']); ?></h3>
                            <p>Femmes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barre de recherche et filtres -->
            <div class="search-container p-4 mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Recherche</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Nom, prénom, matricule ou email...">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="">Tous les statuts</option>
                            <option value="actif" <?php echo $statut_filter === 'actif' ? 'selected' : ''; ?>>
                                Actif
                            </option>
                            <option value="suspendu" <?php echo $statut_filter === 'suspendu' ? 'selected' : ''; ?>>
                                Suspendu
                            </option>
                            <option value="congé" <?php echo $statut_filter === 'congé' ? 'selected' : ''; ?>>
                                Congé
                            </option>
                            <option value="retraité" <?php echo $statut_filter === 'retraité' ? 'selected' : ''; ?>>
                                Retraité
                            </option>
                            <option value="démissionné" <?php echo $statut_filter === 'démissionné' ? 'selected' : ''; ?>>
                                Démissionné
                            </option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="specialite" class="form-label">Spécialité</label>
                        <select class="form-select" id="specialite" name="specialite">
                            <option value="">Toutes les spécialités</option>
                            <?php foreach ($specialites_disponibles as $spec): ?>
                                <?php if (!empty($spec['specialite'])): ?>
                                    <option value="<?php echo htmlspecialchars($spec['specialite']); ?>" 
                                            <?php echo $specialite_filter === $spec['specialite'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($spec['specialite']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-2"></i>Rechercher
                        </button>
                    </div>
                </form>
            </div>

            <!-- Liste des enseignants -->
            <div class="row g-4">
                <?php if (empty($enseignants)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="bi bi-person-workspace display-1 text-muted"></i>
                            <h3 class="mt-3">Aucun enseignant trouvé</h3>
                            <p class="text-muted">
                                <?php if (!empty($search) || !empty($statut_filter) || !empty($specialite_filter)): ?>
                                    Aucun enseignant ne correspond à vos critères de recherche.
                                    <br><a href="index.php">Afficher tous les enseignants</a>
                                <?php else: ?>
                                    Commencez par ajouter votre premier enseignant.
                                <?php endif; ?>
                            </p>
                            <a href="create.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Ajouter un enseignant
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($enseignants as $enseignant): ?>
                        <div class="col-xl-4 col-lg-6 col-md-6">
                            <div class="enseignant-card card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-start mb-3">
                                        <?php if (!empty($enseignant['photo_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($enseignant['photo_path']); ?>" 
                                                 alt="Photo de <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>"
                                                 class="photo-enseignant me-3">
                                        <?php else: ?>
                                            <div class="photo-placeholder me-3">
                                                <?php echo strtoupper(substr($enseignant['prenom'], 0, 1) . substr($enseignant['nom'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-1">
                                                <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                            </h5>
                                            <p class="text-muted mb-1">
                                                <i class="bi bi-card-text me-1"></i>
                                                <?php echo htmlspecialchars($enseignant['matricule_enseignant']); ?>
                                            </p>
                                            <span class="badge statut-badge <?php echo $enseignant['statut_record'] === 'actif' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo ucfirst($enseignant['statut_record']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="view.php?id=<?php echo $enseignant['id']; ?>">
                                                    <i class="bi bi-eye me-2"></i>Voir
                                                </a></li>
                                                <li><a class="dropdown-item" href="edit.php?id=<?php echo $enseignant['id']; ?>">
                                                    <i class="bi bi-pencil me-2"></i>Modifier
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="../matieres/assign.php?enseignant_id=<?php echo $enseignant['id']; ?>">
                                                    <i class="bi bi-book me-2"></i>Affecter des matières
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($enseignant['specialites'])): ?>
                                        <div class="mb-3">
                                            <?php 
                                            $specialites = json_decode($enseignant['specialites'], true);
                                            if (is_array($specialites)):
                                                foreach (array_slice($specialites, 0, 3) as $specialite):
                                            ?>
                                                <span class="specialite-tag"><?php echo htmlspecialchars($specialite); ?></span>
                                            <?php 
                                                endforeach;
                                                if (count($specialites) > 3):
                                            ?>
                                                <span class="specialite-tag">+<?php echo count($specialites) - 3; ?> autres</span>
                                            <?php 
                                                endif;
                                            endif;
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <div class="border-end">
                                                <h6 class="mb-1"><?php echo $enseignant['nombre_cours_assignes']; ?></h6>
                                                <small class="text-muted">Cours assignés</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <h6 class="mb-1"><?php echo $enseignant['nombre_classes']; ?></h6>
                                            <small class="text-muted">Classes</small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php if ($enseignant['experience_annees']): ?>
                                            <span class="badge experience-badge">
                                                <i class="bi bi-clock me-1"></i>
                                                <?php echo $enseignant['experience_annees']; ?> an(s) d'expérience
                                            </span>
                                        <?php endif; ?>
                                        
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $enseignant['id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $enseignant['id']; ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des pages" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="bi bi-chevron-left"></i> Précédent
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Suivant <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialisation des tooltips Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Auto-submit du formulaire de recherche lors du changement de filtre
        document.getElementById('statut').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('specialite').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>

