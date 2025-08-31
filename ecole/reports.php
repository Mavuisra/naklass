<?php
/**
 * Rapports de l'école
 * Affiche des statistiques et rapports sur l'école
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
    // Statistiques générales
    $stats = [];
    
    // Nombre total d'élèves
    $eleves_query = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id AND statut = 'actif'";
    $eleves_stmt = $db->prepare($eleves_query);
    $eleves_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_eleves'] = $eleves_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre d'élèves par sexe
    $eleves_sexe_query = "SELECT sexe, COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id AND statut = 'actif' GROUP BY sexe";
    $eleves_sexe_stmt = $db->prepare($eleves_sexe_query);
    $eleves_sexe_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['eleves_par_sexe'] = $eleves_sexe_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Nombre total de classes
    $classes_query = "SELECT COUNT(*) as total FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif'";
    $classes_stmt = $db->prepare($classes_query);
    $classes_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_classes'] = $classes_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre de classes par cycle
    $classes_cycle_query = "SELECT cycle, COUNT(*) as total FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' GROUP BY cycle";
    $classes_cycle_stmt = $db->prepare($classes_cycle_query);
    $classes_cycle_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['classes_par_cycle'] = $classes_cycle_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Nombre total d'enseignants
    $enseignants_query = "SELECT COUNT(*) as total FROM enseignants WHERE ecole_id = :ecole_id AND statut = 'actif'";
    $enseignants_stmt = $db->prepare($enseignants_query);
    $enseignants_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_enseignants'] = $enseignants_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre d'enseignants par sexe
    $enseignants_sexe_query = "SELECT sexe, COUNT(*) as total FROM enseignants WHERE ecole_id = :ecole_id AND statut = 'actif' GROUP BY sexe";
    $enseignants_sexe_stmt = $db->prepare($enseignants_sexe_query);
    $enseignants_sexe_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['enseignants_par_sexe'] = $enseignants_sexe_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Année scolaire active
    $annee_active_query = "SELECT * FROM annees_scolaires WHERE ecole_id = :ecole_id AND active = TRUE AND statut = 'actif' LIMIT 1";
    $annee_active_stmt = $db->prepare($annee_active_query);
    $annee_active_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annee_active = $annee_active_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Effectifs par classe pour l'année active
    $effectifs_query = "";
    $effectifs_par_classe = [];
    if ($annee_active) {
        $effectifs_query = "SELECT c.nom_classe, c.niveau, c.cycle, c.effectif_actuel, c.capacite_max,
                           ROUND((c.effectif_actuel / c.capacite_max) * 100, 1) as taux_remplissage
                           FROM classes c 
                           WHERE c.ecole_id = :ecole_id AND c.annee_scolaire = :annee_scolaire AND c.statut = 'actif'
                           ORDER BY c.cycle, c.niveau, c.nom_classe";
        $effectifs_stmt = $db->prepare($effectifs_query);
        $effectifs_stmt->execute([
            'ecole_id' => $_SESSION['ecole_id'],
            'annee_scolaire' => $annee_active['libelle']
        ]);
        $effectifs_par_classe = $effectifs_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log('Erreur lors de la récupération des statistiques: ' . $e->getMessage());
    setFlashMessage('error', 'Erreur lors du chargement des rapports.');
    $stats = [];
    $effectifs_par_classe = [];
    $annee_active = null;
}

$page_title = "Rapports de l'École";
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
                                <i class="bi bi-file-earmark-text"></i>
                                Rapports de l'École
                            </h1>
                        </div>
                        <div class="col-auto">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i>
                                Retour
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
                    
                    <!-- Statistiques générales -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-people" style="font-size: 2rem;"></i>
                                    <h3 class="mt-2"><?php echo $stats['total_eleves'] ?? 0; ?></h3>
                                    <p class="mb-0">Élèves</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-building" style="font-size: 2rem;"></i>
                                    <h3 class="mt-2"><?php echo $stats['total_classes'] ?? 0; ?></h3>
                                    <p class="mb-0">Classes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-person-badge" style="font-size: 2rem;"></i>
                                    <h3 class="mt-2"><?php echo $stats['total_enseignants'] ?? 0; ?></h3>
                                    <p class="mb-0">Enseignants</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-warning text-dark">
                                <div class="card-body text-center">
                                    <i class="bi bi-calendar-check" style="font-size: 2rem;"></i>
                                    <h3 class="mt-2"><?php echo $annee_active ? 'Active' : 'Aucune'; ?></h3>
                                    <p class="mb-0">Année Scolaire</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Détails des statistiques -->
                    <div class="row">
                        <!-- Répartition des élèves par sexe -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-pie-chart"></i>
                                        Répartition des Élèves par Sexe
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($stats['eleves_par_sexe'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Sexe</th>
                                                        <th>Nombre</th>
                                                        <th>Pourcentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($stats['eleves_par_sexe'] as $eleve): ?>
                                                        <tr>
                                                            <td>
                                                                <?php
                                                                $sexe_text = '';
                                                                $sexe_class = '';
                                                                switch ($eleve['sexe']) {
                                                                    case 'M':
                                                                        $sexe_text = 'Masculin';
                                                                        $sexe_class = 'text-primary';
                                                                        break;
                                                                    case 'F':
                                                                        $sexe_text = 'Féminin';
                                                                        $sexe_class = 'text-danger';
                                                                        break;
                                                                    default:
                                                                        $sexe_text = 'Autre';
                                                                        $sexe_class = 'text-secondary';
                                                                }
                                                                ?>
                                                                <span class="<?php echo $sexe_class; ?>">
                                                                    <i class="bi bi-<?php echo $eleve['sexe'] === 'M' ? 'gender-male' : ($eleve['sexe'] === 'F' ? 'gender-female' : 'question-circle'); ?>"></i>
                                                                    <?php echo $sexe_text; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo $eleve['total']; ?></td>
                                                            <td>
                                                                <?php 
                                                                $pourcentage = $stats['total_eleves'] > 0 ? round(($eleve['total'] / $stats['total_eleves']) * 100, 1) : 0;
                                                                echo $pourcentage . '%';
                                                                ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center">Aucune donnée disponible</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Répartition des classes par cycle -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-diagram-3"></i>
                                        Répartition des Classes par Cycle
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($stats['classes_par_cycle'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Cycle</th>
                                                        <th>Nombre</th>
                                                        <th>Pourcentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($stats['classes_par_cycle'] as $classe): ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-primary">
                                                                    <?php echo ucfirst($classe['cycle']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo $classe['total']; ?></td>
                                                            <td>
                                                                <?php 
                                                                $pourcentage = $stats['total_classes'] > 0 ? round(($classe['total'] / $stats['total_classes']) * 100, 1) : 0;
                                                                echo $pourcentage . '%';
                                                                ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center">Aucune donnée disponible</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Effectifs par classe -->
                    <?php if ($annee_active && !empty($effectifs_par_classe)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-table"></i>
                                        Effectifs par Classe - <?php echo htmlspecialchars($annee_active['libelle']); ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Classe</th>
                                                    <th>Niveau</th>
                                                    <th>Cycle</th>
                                                    <th>Effectif</th>
                                                    <th>Capacité</th>
                                                    <th>Taux de remplissage</th>
                                                    <th>Statut</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($effectifs_par_classe as $classe): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($classe['nom_classe']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($classe['niveau']); ?></td>
                                                        <td>
                                                            <span class="badge bg-primary">
                                                                <?php echo ucfirst($classe['cycle']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $classe['effectif_actuel']; ?></td>
                                                        <td><?php echo $classe['capacite_max']; ?></td>
                                                        <td>
                                                            <?php 
                                                            $taux = $classe['taux_remplissage'];
                                                            $taux_class = '';
                                                            if ($taux >= 90) {
                                                                $taux_class = 'text-danger';
                                                            } elseif ($taux >= 75) {
                                                                $taux_class = 'text-warning';
                                                            } else {
                                                                $taux_class = 'text-success';
                                                            }
                                                            ?>
                                                            <span class="<?php echo $taux_class; ?> fw-bold">
                                                                <?php echo $taux; ?>%
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $statut_class = '';
                                                            $statut_text = '';
                                                            if ($taux >= 90) {
                                                                $statut_class = 'bg-danger';
                                                                $statut_text = 'Complet';
                                                            } elseif ($taux >= 75) {
                                                                $statut_class = 'bg-warning text-dark';
                                                                $statut_text = 'Presque complet';
                                                            } else {
                                                                $statut_class = 'bg-success';
                                                                $statut_text = 'Disponible';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $statut_class; ?>">
                                                                <?php echo $statut_text; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Actions -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-gear"></i>
                                        Actions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <a href="index.php" class="btn btn-outline-primary w-100">
                                                <i class="bi bi-building"></i><br>
                                                Retour à l'école
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
                                            <a href="../students/" class="btn btn-outline-info w-100">
                                                <i class="bi bi-people"></i><br>
                                                Gérer les élèves
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
