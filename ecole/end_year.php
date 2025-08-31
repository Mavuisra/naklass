<?php
/**
 * Clôture de l'année scolaire
 * Permet de terminer l'année scolaire en cours et de préparer la suivante
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

$error = '';
$success = '';
$annee_active = null;
$annees_scolaires = [];

try {
    // Récupérer l'année scolaire active
    $annee_active_query = "SELECT * FROM annees_scolaires WHERE ecole_id = :ecole_id AND active = TRUE AND statut = 'actif' LIMIT 1";
    $annee_active_stmt = $db->prepare($annee_active_query);
    $annee_active_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annee_active = $annee_active_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer toutes les années scolaires
    $annees_query = "SELECT * FROM annees_scolaires WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY date_debut DESC";
    $annees_stmt = $db->prepare($annees_query);
    $annees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annees_scolaires = $annees_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Traitement du formulaire de clôture
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'end_year' && $annee_active) {
            try {
                // Démarrer une transaction
                $db->beginTransaction();
                
                // 1. Désactiver l'année scolaire active
                $deactivate_query = "UPDATE annees_scolaires SET active = FALSE, updated_at = CURRENT_TIMESTAMP, updated_by = :updated_by WHERE id = :annee_id";
                $deactivate_stmt = $db->prepare($deactivate_query);
                $deactivate_stmt->execute([
                    'updated_by' => $_SESSION['user_id'],
                    'annee_id' => $annee_active['id']
                ]);
                
                // 2. Archiver l'année scolaire
                $archive_query = "UPDATE annees_scolaires SET statut = 'archivé', updated_at = CURRENT_TIMESTAMP, updated_by = :updated_by WHERE id = :annee_id";
                $archive_stmt = $db->prepare($archive_query);
                $archive_stmt->execute([
                    'updated_by' => $_SESSION['user_id'],
                    'annee_id' => $annee_active['id']
                ]);
                
                // 3. Archiver les classes de cette année
                $archive_classes_query = "UPDATE classes SET statut = 'archivé', updated_at = CURRENT_TIMESTAMP, updated_by = :updated_by WHERE ecole_id = :ecole_id AND annee_scolaire = :annee_scolaire";
                $archive_classes_stmt = $db->prepare($archive_classes_query);
                $archive_classes_stmt->execute([
                    'updated_by' => $_SESSION['user_id'],
                    'ecole_id' => $_SESSION['ecole_id'],
                    'annee_scolaire' => $annee_active['libelle']
                ]);
                
                // 4. Archiver les inscriptions de cette année
                $archive_inscriptions_query = "UPDATE inscriptions SET statut = 'archivé', updated_at = CURRENT_TIMESTAMP, updated_by = :updated_by WHERE ecole_id = :ecole_id AND annee_scolaire = :annee_scolaire";
                $archive_inscriptions_stmt = $db->prepare($archive_inscriptions_query);
                $archive_inscriptions_stmt->execute([
                    'updated_by' => $_SESSION['user_id'],
                    'ecole_id' => $_SESSION['ecole_id'],
                    'annee_scolaire' => $annee_active['libelle']
                ]);
                
                // Valider la transaction
                $db->commit();
                
                $success = 'L\'année scolaire "' . $annee_active['libelle'] . '" a été clôturée avec succès.';
                
                // Mettre à jour les données affichées
                $annee_active = null;
                
                // Recharger la liste des années
                $annees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                $annees_scolaires = $annees_stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                // Annuler la transaction en cas d'erreur
                $db->rollBack();
                error_log('Erreur lors de la clôture de l\'année scolaire: ' . $e->getMessage());
                $error = 'Erreur lors de la clôture de l\'année scolaire.';
            }
        }
    }
    
} catch (Exception $e) {
    error_log('Erreur lors de la récupération des données: ' . $e->getMessage());
    setFlashMessage('error', 'Erreur lors du chargement des données.');
    redirect('index.php');
}

$page_title = "Clôture de l'Année Scolaire";
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
                                <i class="bi bi-calendar-x"></i>
                                Clôture de l'Année Scolaire
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
                    
                    <!-- Messages d'erreur et de succès -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Statut de l'année scolaire -->
                    <div class="row">
                        <div class="col-12">
                            <?php if ($annee_active): ?>
                                <div class="card border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Année Scolaire Active
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h4 class="text-warning"><?php echo htmlspecialchars($annee_active['libelle']); ?></h4>
                                                <p class="mb-2">
                                                    <strong>Période:</strong> 
                                                    Du <?php echo date('d/m/Y', strtotime($annee_active['date_debut'])); ?>
                                                    au <?php echo date('d/m/Y', strtotime($annee_active['date_fin'])); ?>
                                                </p>
                                                <?php if (!empty($annee_active['description'])): ?>
                                                    <p class="mb-2">
                                                        <strong>Description:</strong> 
                                                        <?php echo htmlspecialchars($annee_active['description']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="alert alert-warning">
                                                    <i class="bi bi-info-circle"></i>
                                                    <strong>Attention:</strong> La clôture de l'année scolaire est une action irréversible.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-check-circle"></i>
                                            Aucune année scolaire active
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0">
                                            Aucune année scolaire n'est actuellement active. 
                                            Vous pouvez créer une nouvelle année scolaire depuis la page de gestion des années.
                                        </p>
                                        <div class="mt-3">
                                            <a href="annees_scolaires.php" class="btn btn-success">
                                                <i class="bi bi-calendar-plus"></i>
                                                Créer une nouvelle année
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Actions de clôture -->
                    <?php if ($annee_active): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-shield-exclamation"></i>
                                        Clôturer l'Année Scolaire
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-danger">
                                        <h6><i class="bi bi-exclamation-triangle"></i> Actions qui seront effectuées :</h6>
                                        <ul class="mb-0">
                                            <li>Désactivation de l'année scolaire active</li>
                                            <li>Archivage de l'année scolaire</li>
                                            <li>Archivage de toutes les classes de cette année</li>
                                            <li>Archivage de toutes les inscriptions de cette année</li>
                                            <li>Préparation pour la création d'une nouvelle année</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="text-center">
                                        <form method="POST" action="" onsubmit="return confirmClosure()">
                                            <input type="hidden" name="action" value="end_year">
                                            <button type="submit" class="btn btn-danger btn-lg">
                                                <i class="bi bi-calendar-x"></i>
                                                Clôturer l'Année Scolaire <?php echo htmlspecialchars($annee_active['libelle']); ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Historique des années scolaires -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-clock-history"></i>
                                        Historique des Années Scolaires
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($annees_scolaires)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                                            <p class="mt-2">Aucune année scolaire trouvée</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Année</th>
                                                        <th>Période</th>
                                                        <th>Statut</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($annees_scolaires as $annee): ?>
                                                        <tr class="<?php echo $annee['active'] ? 'table-warning' : ''; ?>">
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($annee['libelle']); ?></strong>
                                                                <?php if ($annee['active']): ?>
                                                                    <span class="badge bg-warning text-dark ms-2">Active</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                Du <?php echo date('d/m/Y', strtotime($annee['date_debut'])); ?>
                                                                au <?php echo date('d/m/Y', strtotime($annee['date_fin'])); ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $statut_class = '';
                                                                $statut_text = '';
                                                                switch ($annee['statut']) {
                                                                    case 'actif':
                                                                        $statut_class = 'bg-success';
                                                                        $statut_text = 'Actif';
                                                                        break;
                                                                    case 'archivé':
                                                                        $statut_class = 'bg-secondary';
                                                                        $statut_text = 'Archivé';
                                                                        break;
                                                                    default:
                                                                        $statut_class = 'bg-light text-dark';
                                                                        $statut_text = ucfirst($annee['statut']);
                                                                }
                                                                ?>
                                                                <span class="badge <?php echo $statut_class; ?>">
                                                                    <?php echo $statut_text; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="annees_scolaires.php?id=<?php echo $annee['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-eye"></i>
                                                                    Voir
                                                                </a>
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
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        function confirmClosure() {
            return confirm('Êtes-vous sûr de vouloir clôturer l\'année scolaire ?\n\nCette action est irréversible et archivera toutes les données de l\'année en cours.');
        }
    </script>
</body>
</html>
