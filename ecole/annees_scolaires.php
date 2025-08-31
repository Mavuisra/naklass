<?php
/**
 * Gestion des années scolaires
 * Permet de créer, modifier et activer les années scolaires
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
$annee_to_edit = null;
$action = $_GET['action'] ?? '';

try {
    // Récupérer toutes les années scolaires
    $annees_query = "SELECT * FROM annees_scolaires WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY date_debut DESC";
    $annees_stmt = $db->prepare($annees_query);
    $annees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annees_scolaires = $annees_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer l'année scolaire active
    $annee_active_query = "SELECT * FROM annees_scolaires WHERE ecole_id = :ecole_id AND active = TRUE AND statut = 'actif' LIMIT 1";
    $annee_active_stmt = $db->prepare($annee_active_query);
    $annee_active_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annee_active = $annee_active_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Traitement des actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    // Créer une nouvelle année scolaire
                    $libelle = sanitize($_POST['libelle'] ?? '');
                    $date_debut = $_POST['date_debut'] ?? '';
                    $date_fin = $_POST['date_fin'] ?? '';
                    $description = sanitize($_POST['description'] ?? '');
                    
                    if (empty($libelle) || empty($date_debut) || empty($date_fin)) {
                        $error = 'Veuillez remplir tous les champs obligatoires.';
                    } elseif (strtotime($date_debut) >= strtotime($date_fin)) {
                        $error = 'La date de début doit être antérieure à la date de fin.';
                    } else {
                        try {
                            // Vérifier si l'année scolaire existe déjà
                            $check_query = "SELECT COUNT(*) as total FROM annees_scolaires WHERE libelle = :libelle AND ecole_id = :ecole_id AND statut = 'actif'";
                            $check_stmt = $db->prepare($check_query);
                            $check_stmt->execute(['libelle' => $libelle, 'ecole_id' => $_SESSION['ecole_id']]);
                            
                            if ($check_stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0) {
                                $error = 'Une année scolaire avec ce libellé existe déjà.';
                            } else {
                                // Créer l'année scolaire
                                $create_query = "INSERT INTO annees_scolaires (ecole_id, libelle, date_debut, date_fin, description, active, created_by) 
                                               VALUES (:ecole_id, :libelle, :date_debut, :date_fin, :description, FALSE, :created_by)";
                                $create_stmt = $db->prepare($create_query);
                                $create_stmt->execute([
                                    'ecole_id' => $_SESSION['ecole_id'],
                                    'libelle' => $libelle,
                                    'date_debut' => $date_debut,
                                    'date_fin' => $date_fin,
                                    'description' => $description,
                                    'created_by' => $_SESSION['user_id']
                                ]);
                                
                                $success = 'L\'année scolaire "' . $libelle . '" a été créée avec succès.';
                                
                                // Recharger la liste
                                $annees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                                $annees_scolaires = $annees_stmt->fetchAll(PDO::FETCH_ASSOC);
                            }
                        } catch (Exception $e) {
                            error_log('Erreur lors de la création de l\'année scolaire: ' . $e->getMessage());
                            $error = 'Erreur lors de la création de l\'année scolaire.';
                        }
                    }
                    break;
                    
                case 'edit':
                    // Modifier une année scolaire
                    $annee_id = intval($_POST['annee_id'] ?? 0);
                    $libelle = sanitize($_POST['libelle'] ?? '');
                    $date_debut = $_POST['date_debut'] ?? '';
                    $date_fin = $_POST['date_fin'] ?? '';
                    $description = sanitize($_POST['description'] ?? '');
                    
                    if (empty($annee_id) || empty($libelle) || empty($date_debut) || empty($date_fin)) {
                        $error = 'Veuillez remplir tous les champs obligatoires.';
                    } elseif (strtotime($date_debut) >= strtotime($date_fin)) {
                        $error = 'La date de début doit être antérieure à la date de fin.';
                    } else {
                        try {
                            // Vérifier si l'année scolaire existe et appartient à l'école
                            $check_query = "SELECT id FROM annees_scolaires WHERE id = :annee_id AND ecole_id = :ecole_id AND statut = 'actif'";
                            $check_stmt = $db->prepare($check_query);
                            $check_stmt->execute(['annee_id' => $annee_id, 'ecole_id' => $_SESSION['ecole_id']]);
                            
                            if (!$check_stmt->fetch()) {
                                $error = 'Année scolaire non trouvée ou non autorisée.';
                            } else {
                                // Mettre à jour l'année scolaire
                                $update_query = "UPDATE annees_scolaires SET 
                                               libelle = :libelle, 
                                               date_debut = :date_debut, 
                                               date_fin = :date_fin, 
                                               description = :description,
                                               updated_at = CURRENT_TIMESTAMP,
                                               updated_by = :updated_by 
                                               WHERE id = :annee_id";
                                $update_stmt = $db->prepare($update_query);
                                $update_stmt->execute([
                                    'libelle' => $libelle,
                                    'date_debut' => $date_debut,
                                    'date_fin' => $date_fin,
                                    'description' => $description,
                                    'updated_by' => $_SESSION['user_id'],
                                    'annee_id' => $annee_id
                                ]);
                                
                                $success = 'L\'année scolaire a été modifiée avec succès.';
                                
                                // Recharger la liste
                                $annees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                                $annees_scolaires = $annees_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Réinitialiser l'édition
                                $annee_to_edit = null;
                                $action = '';
                            }
                        } catch (Exception $e) {
                            error_log('Erreur lors de la modification de l\'année scolaire: ' . $e->getMessage());
                            $error = 'Erreur lors de la modification de l\'année scolaire.';
                        }
                    }
                    break;
                    
                case 'activate':
                    // Activer une année scolaire
                    $annee_id = intval($_POST['annee_id'] ?? 0);
                    
                    if (empty($annee_id)) {
                        $error = 'ID de l\'année scolaire manquant.';
                    } else {
                        try {
                            // Démarrer une transaction
                            $db->beginTransaction();
                            
                            // Désactiver toutes les années scolaires
                            $deactivate_query = "UPDATE annees_scolaires SET active = FALSE, updated_at = CURRENT_TIMESTAMP, updated_by = :updated_by WHERE ecole_id = :ecole_id";
                            $deactivate_stmt = $db->prepare($deactivate_query);
                            $deactivate_stmt->execute([
                                'updated_by' => $_SESSION['user_id'],
                                'ecole_id' => $_SESSION['ecole_id']
                            ]);
                            
                            // Activer l'année scolaire sélectionnée
                            $activate_query = "UPDATE annees_scolaires SET active = TRUE, updated_at = CURRENT_TIMESTAMP, updated_by = :updated_by WHERE id = :annee_id AND ecole_id = :ecole_id";
                            $activate_stmt = $db->prepare($activate_query);
                            $activate_stmt->execute([
                                'updated_by' => $_SESSION['user_id'],
                                'annee_id' => $annee_id,
                                'ecole_id' => $_SESSION['ecole_id']
                            ]);
                            
                            // Valider la transaction
                            $db->commit();
                            
                            $success = 'L\'année scolaire a été activée avec succès.';
                            
                            // Recharger les données
                            $annee_active_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                            $annee_active = $annee_active_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $annees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                            $annees_scolaires = $annees_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                        } catch (Exception $e) {
                            // Annuler la transaction en cas d'erreur
                            $db->rollBack();
                            error_log('Erreur lors de l\'activation de l\'année scolaire: ' . $e->getMessage());
                            $error = 'Erreur lors de l\'activation de l\'année scolaire.';
                        }
                    }
                    break;
            }
        }
    }
    
    // Récupérer l'année à éditer si demandé
    if ($action === 'edit' && isset($_GET['id'])) {
        $edit_id = intval($_GET['id']);
        $edit_query = "SELECT * FROM annees_scolaires WHERE id = :annee_id AND ecole_id = :ecole_id AND statut = 'actif'";
        $edit_stmt = $db->prepare($edit_query);
        $edit_stmt->execute(['annee_id' => $edit_id, 'ecole_id' => $_SESSION['ecole_id']]);
        $annee_to_edit = $edit_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$annee_to_edit) {
            setFlashMessage('error', 'Année scolaire non trouvée.');
            redirect('annees_scolaires.php');
        }
    }
    
} catch (Exception $e) {
    error_log('Erreur lors de la gestion des années scolaires: ' . $e->getMessage());
    setFlashMessage('error', 'Erreur lors du chargement des données.');
    redirect('index.php');
}

$page_title = "Gestion des Années Scolaires";
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
                                <i class="bi bi-calendar-plus"></i>
                                Gestion des Années Scolaires
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
                    
                    <!-- Année scolaire active -->
                    <?php if ($annee_active): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-calendar-check"></i>
                                        Année Scolaire Active
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h4 class="text-success mb-2"><?php echo htmlspecialchars($annee_active['libelle']); ?></h4>
                                            <p class="mb-1">
                                                <strong>Période:</strong> 
                                                Du <?php echo date('d/m/Y', strtotime($annee_active['date_debut'])); ?>
                                                au <?php echo date('d/m/Y', strtotime($annee_active['date_fin'])); ?>
                                            </p>
                                            <?php if (!empty($annee_active['description'])): ?>
                                                <p class="mb-0">
                                                    <strong>Description:</strong> 
                                                    <?php echo htmlspecialchars($annee_active['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="badge bg-success fs-6">Année Active</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Formulaire de création/modification -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-<?php echo $annee_to_edit ? 'pencil' : 'plus-circle'; ?>"></i>
                                        <?php echo $annee_to_edit ? 'Modifier l\'Année Scolaire' : 'Créer une Nouvelle Année Scolaire'; ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="<?php echo $annee_to_edit ? 'edit' : 'create'; ?>">
                                        <?php if ($annee_to_edit): ?>
                                            <input type="hidden" name="annee_id" value="<?php echo $annee_to_edit['id']; ?>">
                                        <?php endif; ?>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="libelle" class="form-label">Libellé de l'année *</label>
                                                    <input type="text" class="form-control" id="libelle" name="libelle" 
                                                           value="<?php echo htmlspecialchars($annee_to_edit['libelle'] ?? ''); ?>" 
                                                           placeholder="Ex: 2024-2025" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="date_debut" class="form-label">Date de début *</label>
                                                    <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                                           value="<?php echo $annee_to_edit['date_debut'] ?? ''; ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="date_fin" class="form-label">Date de fin *</label>
                                                    <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                                           value="<?php echo $annee_to_edit['date_fin'] ?? ''; ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3" 
                                                      placeholder="Description optionnelle de l'année scolaire"><?php echo htmlspecialchars($annee_to_edit['description'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <?php if ($annee_to_edit): ?>
                                                <a href="annees_scolaires.php" class="btn btn-secondary">
                                                    <i class="bi bi-arrow-left"></i>
                                                    Annuler
                                                </a>
                                            <?php endif; ?>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-<?php echo $annee_to_edit ? 'check-circle' : 'plus-circle'; ?>"></i>
                                                <?php echo $annee_to_edit ? 'Modifier' : 'Créer'; ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Liste des années scolaires -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-list-ul"></i>
                                        Années Scolaires
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($annees_scolaires)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                                            <p class="mt-2">Aucune année scolaire trouvée</p>
                                            <p class="text-muted">Créez votre première année scolaire en utilisant le formulaire ci-dessus.</p>
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
                                                        <tr class="<?php echo $annee['active'] ? 'table-success' : ''; ?>">
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($annee['libelle']); ?></strong>
                                                                <?php if ($annee['active']): ?>
                                                                    <span class="badge bg-success ms-2">Active</span>
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
                                                                <div class="btn-group" role="group">
                                                                    <?php if (!$annee['active']): ?>
                                                                        <form method="POST" action="" style="display: inline;">
                                                                            <input type="hidden" name="action" value="activate">
                                                                            <input type="hidden" name="annee_id" value="<?php echo $annee['id']; ?>">
                                                                            <button type="submit" class="btn btn-sm btn-success" 
                                                                                    onclick="return confirm('Activer cette année scolaire ?')">
                                                                                <i class="bi bi-check-circle"></i>
                                                                                Activer
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                    
                                                                    <a href="annees_scolaires.php?action=edit&id=<?php echo $annee['id']; ?>" 
                                                                       class="btn btn-sm btn-outline-primary">
                                                                        <i class="bi bi-pencil"></i>
                                                                        Modifier
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
