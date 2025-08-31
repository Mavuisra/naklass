<?php
/**
 * Gestion de l'école - Tableau de bord principal
 * Permet de consulter et modifier les informations de l'école
 * et de gérer les années scolaires
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Vérifier si l'utilisateur a une école
if (!isset($_SESSION['ecole_id'])) {
    setFlashMessage('error', 'Aucune école associée à votre compte.');
    redirect('../auth/login.php');
}

// Vérifier si l'utilisateur a les droits admin ou direction
if (!hasRole(['admin', 'direction'])) {
    setFlashMessage('error', 'Vous devez être administrateur ou membre de la direction pour accéder à cette page.');
    redirect('../auth/dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

try {
    // Récupérer les informations de l'école
    $ecole_query = "SELECT * FROM ecoles WHERE id = :ecole_id";
    $ecole_stmt = $db->prepare($ecole_query);
    $ecole_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $ecole = $ecole_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ecole) {
        setFlashMessage('error', 'École non trouvée.');
        redirect('../auth/dashboard.php');
    }
    
    // Récupérer les années scolaires
    $annees_query = "SELECT * FROM annees_scolaires WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY date_debut DESC";
    $annees_stmt = $db->prepare($annees_query);
    $annees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annees_scolaires = $annees_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer l'année scolaire active
    $annee_active_query = "SELECT * FROM annees_scolaires WHERE ecole_id = :ecole_id AND active = TRUE AND statut = 'actif' LIMIT 1";
    $annee_active_stmt = $db->prepare($annee_active_query);
    $annee_active_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annee_active = $annee_active_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Statistiques de base
    $stats = [];
    
    // Nombre d'élèves
    $eleves_query = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id AND statut = 'actif'";
    $eleves_stmt = $db->prepare($eleves_query);
    $eleves_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['eleves'] = $eleves_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre de classes
    $classes_query = "SELECT COUNT(*) as total FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif'";
    $classes_stmt = $db->prepare($classes_query);
    $classes_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['classes'] = $classes_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre d'enseignants
    $enseignants_query = "SELECT COUNT(*) as total FROM enseignants WHERE ecole_id = :ecole_id AND statut = 'actif'";
    $enseignants_stmt = $db->prepare($enseignants_query);
    $enseignants_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['enseignants'] = $enseignants_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    error_log('Erreur lors de la récupération des données de l\'école: ' . $e->getMessage());
    setFlashMessage('error', 'Erreur lors du chargement des données.');
    $ecole = [];
    $annees_scolaires = [];
    $annee_active = null;
    $stats = ['eleves' => 0, 'classes' => 0, 'enseignants' => 0];
}

$page_title = "Gestion de l'École - " . ($ecole['nom_ecole'] ?? 'École');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Naklass</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="content-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="page-title">
                                <i class="bi bi-building"></i>
                                Gestion de l'École
                            </h1>
                        </div>
                        <div class="col-auto">
                            <a href="edit.php" class="btn btn-primary">
                                <i class="bi bi-pencil"></i>
                                Modifier les informations
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <div class="content">
                <div class="container-fluid">
                    <!-- Flash Messages -->
                    <?php include '../includes/flash_messages.php'; ?>
                    
                    <!-- Informations de l'école -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-info-circle"></i>
                                        Informations de l'École
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Nom de l'école:</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($ecole['nom_ecole'] ?? 'Non défini'); ?></p>
                                            </div>
                                            
                                            <?php if (!empty($ecole['sigle'])): ?>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Sigle:</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($ecole['sigle']); ?></p>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Adresse:</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($ecole['adresse'] ?? 'Non définie'); ?></p>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Téléphone:</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($ecole['telephone'] ?? 'Non défini'); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Email:</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($ecole['email'] ?? 'Non défini'); ?></p>
                                            </div>
                                            
                                            <?php if (!empty($ecole['site_web'])): ?>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Site web:</label>
                                                <p class="mb-0">
                                                    <a href="<?php echo htmlspecialchars($ecole['site_web']); ?>" target="_blank">
                                                        <?php echo htmlspecialchars($ecole['site_web']); ?>
                                                    </a>
                                                </p>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Directeur:</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($ecole['directeur_nom'] ?? 'Non défini'); ?></p>
                                            </div>
                                            
                                            <?php if (!empty($ecole['regime'])): ?>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Régime:</label>
                                                <p class="mb-0"><?php echo htmlspecialchars($ecole['regime']); ?></p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <!-- Statistiques -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-graph-up"></i>
                                        Statistiques
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="stat-item">
                                                <h3 class="text-primary"><?php echo $stats['eleves']; ?></h3>
                                                <small class="text-muted">Élèves</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="stat-item">
                                                <h3 class="text-success"><?php echo $stats['classes']; ?></h3>
                                                <small class="text-muted">Classes</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="stat-item">
                                                <h3 class="text-info"><?php echo $stats['enseignants']; ?></h3>
                                                <small class="text-muted">Enseignants</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Année scolaire active -->
                            <?php if ($annee_active): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-calendar-check"></i>
                                        Année Scolaire Active
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <h5 class="text-success"><?php echo htmlspecialchars($annee_active['libelle']); ?></h5>
                                    <p class="mb-1">
                                        <small class="text-muted">
                                            Du <?php echo date('d/m/Y', strtotime($annee_active['date_debut'])); ?>
                                            au <?php echo date('d/m/Y', strtotime($annee_active['date_fin'])); ?>
                                        </small>
                                    </p>
                                    <a href="annees_scolaires.php" class="btn btn-sm btn-outline-primary">
                                        Gérer les années
                                    </a>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        Aucune année active
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-2">Aucune année scolaire n'est actuellement active.</p>
                                    <a href="annees_scolaires.php" class="btn btn-sm btn-primary">
                                        Créer une année
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Actions rapides -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-lightning"></i>
                                        Actions Rapides
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <a href="edit.php" class="btn btn-outline-primary w-100">
                                                <i class="bi bi-pencil"></i><br>
                                                Modifier l'école
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="annees_scolaires.php" class="btn btn-outline-success w-100">
                                                <i class="bi bi-calendar-plus"></i><br>
                                                Gérer les années
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="end_year.php" class="btn btn-outline-warning w-100">
                                                <i class="bi bi-calendar-x"></i><br>
                                                Clôturer l'année
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="reports.php" class="btn btn-outline-info w-100">
                                                <i class="bi bi-file-earmark-text"></i><br>
                                                Rapports
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
