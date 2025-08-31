<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireAuth();
requireRole(['admin', 'direction']);

$database = new Database();
$db = $database->getConnection();

$page_title = "Gestion des Périodes et Trimestres";
$error_message = '';
$success_message = '';

// Récupérer l'année scolaire si spécifiée
$annee_id = $_GET['annee_id'] ?? null;
$annee_courante = null;

if ($annee_id) {
    $query = "SELECT * FROM annees_scolaires WHERE id = :id AND ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $annee_id, 'ecole_id' => $_SESSION['ecole_id']]);
    $annee_courante = $stmt->fetch();
}

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_annee':
                $libelle = trim($_POST['libelle']);
                $date_debut = $_POST['date_debut'];
                $date_fin = $_POST['date_fin'];
                $description = trim($_POST['description'] ?? '');
                
                // Vérifier l'unicité du libellé
                $check_query = "SELECT COUNT(*) FROM annees_scolaires WHERE libelle = :libelle AND ecole_id = :ecole_id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute(['libelle' => $libelle, 'ecole_id' => $_SESSION['ecole_id']]);
                
                if ($check_stmt->fetchColumn() > 0) {
                    throw new Exception("Une année scolaire avec ce libellé existe déjà.");
                }
                
                $db->beginTransaction();
                
                // Créer l'année scolaire
                $annee_query = "INSERT INTO annees_scolaires (ecole_id, libelle, date_debut, date_fin, description, created_by) 
                               VALUES (:ecole_id, :libelle, :date_debut, :date_fin, :description, :created_by)";
                $annee_stmt = $db->prepare($annee_query);
                $annee_stmt->execute([
                    'ecole_id' => $_SESSION['ecole_id'],
                    'libelle' => $libelle,
                    'date_debut' => $date_debut,
                    'date_fin' => $date_fin,
                    'description' => $description,
                    'created_by' => $_SESSION['user_id']
                ]);
                
                $new_annee_id = $db->lastInsertId();
                
                // Créer automatiquement les 3 trimestres
                $trimestres = [
                    ['nom' => '1er Trimestre', 'ordre' => 1],
                    ['nom' => '2ème Trimestre', 'ordre' => 2],
                    ['nom' => '3ème Trimestre', 'ordre' => 3]
                ];
                
                foreach ($trimestres as $trimestre) {
                    $trim_query = "INSERT INTO periodes_scolaires (annee_scolaire_id, nom, type_periode, ordre_periode, created_by) 
                                   VALUES (:annee_id, :nom, 'trimestre', :ordre, :created_by)";
                    $trim_stmt = $db->prepare($trim_query);
                    $trim_stmt->execute([
                        'annee_id' => $new_annee_id,
                        'nom' => $trimestre['nom'],
                        'ordre' => $trimestre['ordre'],
                        'created_by' => $_SESSION['user_id']
                    ]);
                    
                    $trimestre_id = $db->lastInsertId();
                    
                    // Créer 2 périodes d'évaluation par trimestre
                    $periode_base = ($trimestre['ordre'] - 1) * 2;
                    $periodes = [
                        ['nom' => 'Période ' . ($periode_base + 1), 'ordre' => $periode_base + 1],
                        ['nom' => 'Période ' . ($periode_base + 2), 'ordre' => $periode_base + 2]
                    ];
                    
                    foreach ($periodes as $periode) {
                        $per_query = "INSERT INTO periodes_scolaires (annee_scolaire_id, periode_parent_id, nom, type_periode, ordre_periode, created_by) 
                                      VALUES (:annee_id, :parent_id, :nom, 'periode', :ordre, :created_by)";
                        $per_stmt = $db->prepare($per_query);
                        $per_stmt->execute([
                            'annee_id' => $new_annee_id,
                            'parent_id' => $trimestre_id,
                            'nom' => $periode['nom'],
                            'ordre' => $periode['ordre'],
                            'created_by' => $_SESSION['user_id']
                        ]);
                    }
                }
                
                $db->commit();
                $success_message = "Année scolaire créée avec succès avec les trimestres et périodes.";
                
                // Rediriger vers la page de l'année créée
                header("Location: periodes.php?annee_id=" . $new_annee_id);
                exit;
                
            case 'create_periode':
                $annee_id = $_POST['annee_id'];
                $nom = trim($_POST['nom']);
                $type_periode = $_POST['type_periode'];
                $ordre_periode = (int)$_POST['ordre_periode'];
                $date_debut = $_POST['date_debut'] ?? null;
                $date_fin = $_POST['date_fin'] ?? null;
                $parent_id = $_POST['parent_id'] ?? null;
                $coefficient = $_POST['coefficient'] ?? 1.00;
                
                $query = "INSERT INTO periodes_scolaires (annee_scolaire_id, periode_parent_id, nom, type_periode, ordre_periode, date_debut, date_fin, coefficient, created_by) 
                         VALUES (:annee_id, :parent_id, :nom, :type_periode, :ordre, :date_debut, :date_fin, :coefficient, :created_by)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'annee_id' => $annee_id,
                    'parent_id' => $parent_id ?: null,
                    'nom' => $nom,
                    'type_periode' => $type_periode,
                    'ordre' => $ordre_periode,
                    'date_debut' => $date_debut ?: null,
                    'date_fin' => $date_fin ?: null,
                    'coefficient' => $coefficient,
                    'created_by' => $_SESSION['user_id']
                ]);
                
                $success_message = "Période créée avec succès.";
                break;
                
            case 'update_periode':
                $periode_id = $_POST['periode_id'];
                $nom = trim($_POST['nom']);
                $ordre_periode = (int)$_POST['ordre_periode'];
                $date_debut = $_POST['date_debut'] ?? null;
                $date_fin = $_POST['date_fin'] ?? null;
                $coefficient = $_POST['coefficient'] ?? 1.00;
                $verrouillee = isset($_POST['verrouillee']) ? 1 : 0;
                
                $query = "UPDATE periodes_scolaires 
                         SET nom = :nom, ordre_periode = :ordre, date_debut = :date_debut, date_fin = :date_fin, 
                             coefficient = :coefficient, verrouillee = :verrouillee, updated_by = :updated_by, updated_at = NOW() 
                         WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'nom' => $nom,
                    'ordre' => $ordre_periode,
                    'date_debut' => $date_debut ?: null,
                    'date_fin' => $date_fin ?: null,
                    'coefficient' => $coefficient,
                    'verrouillee' => $verrouillee,
                    'updated_by' => $_SESSION['user_id'],
                    'id' => $periode_id
                ]);
                
                $success_message = "Période mise à jour avec succès.";
                break;
                
            case 'delete_periode':
                $periode_id = $_POST['periode_id'];
                
                // Vérifier s'il y a des évaluations liées
                $check_query = "SELECT COUNT(*) FROM evaluations WHERE periode_scolaire_id = :periode_id";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute(['periode_id' => $periode_id]);
                
                if ($check_stmt->fetchColumn() > 0) {
                    throw new Exception("Impossible de supprimer cette période car elle contient des évaluations.");
                }
                
                // Supprimer les périodes enfants d'abord
                $delete_children = "UPDATE periodes_scolaires SET statut = 'supprimé_logique' WHERE periode_parent_id = :periode_id";
                $stmt = $db->prepare($delete_children);
                $stmt->execute(['periode_id' => $periode_id]);
                
                // Supprimer la période principale
                $delete_query = "UPDATE periodes_scolaires SET statut = 'supprimé_logique' WHERE id = :id";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->execute(['id' => $periode_id]);
                
                $success_message = "Période supprimée avec succès.";
                break;
                
            case 'set_active_year':
                $annee_target_id = $_POST['annee_id'];
                
                $db->beginTransaction();
                
                // Désactiver toutes les années
                $deactivate_query = "UPDATE annees_scolaires SET active = FALSE WHERE ecole_id = :ecole_id";
                $deactivate_stmt = $db->prepare($deactivate_query);
                $deactivate_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                
                // Activer l'année cible
                $activate_query = "UPDATE annees_scolaires SET active = TRUE WHERE id = :id AND ecole_id = :ecole_id";
                $activate_stmt = $db->prepare($activate_query);
                $activate_stmt->execute(['id' => $annee_target_id, 'ecole_id' => $_SESSION['ecole_id']]);
                
                $db->commit();
                $success_message = "Année scolaire définie comme active.";
                break;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        $error_message = $e->getMessage();
    }
}

// Récupérer toutes les années scolaires
$annees_query = "SELECT * FROM annees_scolaires WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY date_debut DESC";
$annees_stmt = $db->prepare($annees_query);
$annees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$annees_scolaires = $annees_stmt->fetchAll();

// Récupérer les périodes de l'année courante si sélectionnée
$periodes_par_type = [];
if ($annee_courante) {
    $periodes_query = "SELECT * FROM periodes_scolaires 
                      WHERE annee_scolaire_id = :annee_id AND statut = 'actif' 
                      ORDER BY type_periode, ordre_periode";
    $periodes_stmt = $db->prepare($periodes_query);
    $periodes_stmt->execute(['annee_id' => $annee_courante['id']]);
    $periodes = $periodes_stmt->fetchAll();
    
    // Organiser les périodes par type
    foreach ($periodes as $periode) {
        $periodes_par_type[$periode['type_periode']][] = $periode;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Naklass</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- CSS personnalisés -->
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/naklass-theme.css" rel="stylesheet">
    
    <style>
    /**
     * CSS pour le module de gestion des périodes et trimestres
     */

    /* Cards pour les années scolaires */
    .year-card {
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .year-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .year-card.active {
        border-left: 4px solid #28a745;
    }

    /* Cards pour les trimestres */
    .trimestre-card {
        border-left: 4px solid #007bff;
        transition: all 0.3s ease;
    }

    .trimestre-card:hover {
        box-shadow: 0 2px 10px rgba(0, 123, 255, 0.2);
    }

    /* Cards pour les périodes d'évaluation */
    .periode-card {
        border-left: 4px solid #28a745;
        transition: all 0.3s ease;
    }

    .periode-card:hover {
        box-shadow: 0 2px 10px rgba(40, 167, 69, 0.2);
    }

    /* Badge pour les périodes verrouillées */
    .locked-badge {
        background: linear-gradient(45deg, #ffc107, #fd7e14);
        color: white;
        font-size: 0.75rem;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }

    /* États des périodes */
    .period-status {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .period-status.active {
        background-color: #d1ecf1;
        color: #0c5460;
    }

    .period-status.locked {
        background-color: #fff3cd;
        color: #856404;
    }

    /* Animation pour les cartes */
    .card-animate {
        animation: slideInUp 0.5s ease-out;
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .card-body {
            padding: 15px;
        }
        
        .modal-dialog {
            margin: 10px;
        }
    }
    </style>
</head>
<body>

<div class="main-content">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="content-area">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="index.php"><i class="bi bi-journal-text me-1"></i>Notes & Bulletins</a>
                        </li>
                        <li class="breadcrumb-item active">Périodes</li>
                    </ol>
                </nav>
                <h1><i class="bi bi-calendar-week me-2"></i><?php echo $page_title; ?></h1>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Retour
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnneeModal">
                    <i class="bi bi-plus-circle me-1"></i>Nouvelle Année
                </button>
                <?php if ($annee_courante): ?>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createPeriodeModal">
                    <i class="bi bi-plus-circle me-1"></i>Nouvelle Période
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Navigation des années scolaires -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-collection me-2"></i>Années Scolaires
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($annees_scolaires)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                                <h6 class="text-muted mt-3">Aucune année scolaire</h6>
                                <p class="text-muted">Créez votre première année scolaire pour commencer.</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($annees_scolaires as $annee): ?>
                                    <div class="col-lg-4 col-md-6">
                                        <div class="card year-card h-100 <?php echo $annee['active'] ? 'border-success' : ''; ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($annee['libelle']); ?></h6>
                                                    <?php if ($annee['active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-muted small mb-2">
                                                    Du <?php echo date('d/m/Y', strtotime($annee['date_debut'])); ?>
                                                    au <?php echo date('d/m/Y', strtotime($annee['date_fin'])); ?>
                                                </p>
                                                <?php if ($annee['description']): ?>
                                                    <p class="text-muted small"><?php echo htmlspecialchars($annee['description']); ?></p>
                                                <?php endif; ?>
                                                <div class="d-flex gap-1">
                                                    <a href="periodes.php?annee_id=<?php echo $annee['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="bi bi-eye me-1"></i>Gérer
                                                    </a>
                                                    <?php if (!$annee['active']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="set_active_year">
                                                            <input type="hidden" name="annee_id" value="<?php echo $annee['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-success btn-sm">
                                                                <i class="bi bi-check-circle me-1"></i>Activer
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
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
        </div>

        <!-- Détails de l'année sélectionnée -->
        <?php if ($annee_courante): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-calendar-event me-2"></i>
                                Périodes de l'année <?php echo htmlspecialchars($annee_courante['libelle']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($periodes_par_type)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-calendar-plus fs-1 text-muted"></i>
                                    <h6 class="text-muted mt-3">Aucune période configurée</h6>
                                    <p class="text-muted">Les trimestres et périodes seront créés automatiquement lors de la création d'une nouvelle année.</p>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createPeriodeModal">
                                        <i class="bi bi-plus-circle me-1"></i>Créer une période
                                    </button>
                                </div>
                            <?php else: ?>
                                <!-- Trimestres -->
                                <?php if (isset($periodes_par_type['trimestre'])): ?>
                                    <h6 class="text-primary mb-3">
                                        <i class="bi bi-calendar3 me-2"></i>Trimestres
                                    </h6>
                                    <div class="row g-3 mb-4">
                                        <?php foreach ($periodes_par_type['trimestre'] as $trimestre): ?>
                                            <div class="col-lg-4">
                                                <div class="card trimestre-card">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="card-title mb-0"><?php echo htmlspecialchars($trimestre['nom']); ?></h6>
                                                            <?php if ($trimestre['verrouillee']): ?>
                                                                <span class="locked-badge badge">
                                                                    <i class="bi bi-lock me-1"></i>Verrouillé
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="text-muted small mb-2">Ordre: <?php echo $trimestre['ordre_periode']; ?></p>
                                                        <?php if ($trimestre['date_debut'] && $trimestre['date_fin']): ?>
                                                            <p class="text-muted small mb-2">
                                                                Du <?php echo date('d/m/Y', strtotime($trimestre['date_debut'])); ?>
                                                                au <?php echo date('d/m/Y', strtotime($trimestre['date_fin'])); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <div class="d-flex gap-1">
                                                            <button type="button" class="btn btn-outline-primary btn-sm edit-periode" 
                                                                    data-periode='<?php echo json_encode($trimestre); ?>'>
                                                                <i class="bi bi-pencil me-1"></i>Modifier
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger btn-sm delete-periode" 
                                                                    data-periode-id="<?php echo $trimestre['id']; ?>" 
                                                                    data-periode-nom="<?php echo htmlspecialchars($trimestre['nom']); ?>">
                                                                <i class="bi bi-trash me-1"></i>Supprimer
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Périodes d'évaluation -->
                                <?php if (isset($periodes_par_type['periode'])): ?>
                                    <h6 class="text-success mb-3">
                                        <i class="bi bi-calendar-check me-2"></i>Périodes d'Évaluation
                                    </h6>
                                    <div class="row g-2">
                                        <?php foreach ($periodes_par_type['periode'] as $periode): ?>
                                            <div class="col-lg-3 col-md-4 col-sm-6">
                                                <div class="card periode-card">
                                                    <div class="card-body p-3">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="card-title mb-0 small"><?php echo htmlspecialchars($periode['nom']); ?></h6>
                                                            <?php if ($periode['verrouillee']): ?>
                                                                <i class="bi bi-lock text-warning"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="text-muted small mb-2">
                                                            Ordre: <?php echo $periode['ordre_periode']; ?> |
                                                            Coeff: <?php echo $periode['coefficient']; ?>
                                                        </p>
                                                        <?php if ($periode['date_debut'] && $periode['date_fin']): ?>
                                                            <p class="text-muted small mb-2">
                                                                <?php echo date('d/m', strtotime($periode['date_debut'])); ?> -
                                                                <?php echo date('d/m', strtotime($periode['date_fin'])); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <div class="d-flex gap-1">
                                                            <button type="button" class="btn btn-outline-primary btn-sm edit-periode" 
                                                                    data-periode='<?php echo json_encode($periode); ?>'>
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger btn-sm delete-periode" 
                                                                    data-periode-id="<?php echo $periode['id']; ?>" 
                                                                    data-periode-nom="<?php echo htmlspecialchars($periode['nom']); ?>">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de création d'année scolaire -->
<div class="modal fade" id="createAnneeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle Année Scolaire</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_annee">
                    
                    <div class="mb-3">
                        <label for="libelle" class="form-label">Libellé <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="libelle" name="libelle" 
                               placeholder="Ex: 2024-2025" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_debut" class="form-label">Date de début <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_fin" class="form-label">Date de fin <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Description optionnelle de l'année scolaire"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Création automatique :</strong> 3 trimestres et 6 périodes d'évaluation seront créés automatiquement.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer l'année</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de création de période -->
<?php if ($annee_courante): ?>
<div class="modal fade" id="createPeriodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle Période</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_periode">
                    <input type="hidden" name="annee_id" value="<?php echo $annee_courante['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="nom_periode" class="form-label">Nom de la période <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom_periode" name="nom" 
                               placeholder="Ex: 1er Trimestre, Période 1" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type_periode" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type_periode" name="type_periode" required>
                                    <option value="">Choisir...</option>
                                    <option value="trimestre">Trimestre</option>
                                    <option value="periode">Période d'évaluation</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="ordre_periode" class="form-label">Ordre <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="ordre_periode" name="ordre_periode" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div id="parent_periode_div" class="mb-3" style="display: none;">
                        <label for="parent_id" class="form-label">Trimestre parent</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">Aucun</option>
                            <?php if (isset($periodes_par_type['trimestre'])): ?>
                                <?php foreach ($periodes_par_type['trimestre'] as $trimestre): ?>
                                    <option value="<?php echo $trimestre['id']; ?>">
                                        <?php echo htmlspecialchars($trimestre['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_debut_periode" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="date_debut_periode" name="date_debut">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_fin_periode" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="date_fin_periode" name="date_fin">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="coefficient" class="form-label">Coefficient</label>
                        <input type="number" class="form-control" id="coefficient" name="coefficient" 
                               min="0" max="10" step="0.01" value="1.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Créer la période</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal de modification de période -->
<div class="modal fade" id="editPeriodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier la Période</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_periode">
                    <input type="hidden" name="periode_id" id="edit_periode_id">
                    
                    <div class="mb-3">
                        <label for="edit_nom" class="form-label">Nom de la période <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_nom" name="nom" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_ordre" class="form-label">Ordre <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_ordre" name="ordre_periode" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_coefficient" class="form-label">Coefficient</label>
                                <input type="number" class="form-control" id="edit_coefficient" name="coefficient" 
                                       min="0" max="10" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_date_debut" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="edit_date_debut" name="date_debut">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_date_fin" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="edit_date_fin" name="date_fin">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_verrouillee" name="verrouillee">
                            <label class="form-check-label" for="edit_verrouillee">
                                Période verrouillée (empêche la modification des notes)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="deletePeriodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer la période <strong id="delete_periode_nom"></strong> ?</p>
                <p class="text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Cette action supprimera également toutes les périodes enfants associées.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="POST" class="d-inline" id="deletePeriodeForm">
                    <input type="hidden" name="action" value="delete_periode">
                    <input type="hidden" name="periode_id" id="delete_periode_id">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du changement de type de période
    const typePeriodeSelect = document.getElementById('type_periode');
    const parentPeriodeDiv = document.getElementById('parent_periode_div');
    
    if (typePeriodeSelect) {
        typePeriodeSelect.addEventListener('change', function() {
            if (this.value === 'periode') {
                parentPeriodeDiv.style.display = 'block';
            } else {
                parentPeriodeDiv.style.display = 'none';
            }
        });
    }
    
    // Gestion de l'édition de période
    document.querySelectorAll('.edit-periode').forEach(button => {
        button.addEventListener('click', function() {
            const periode = JSON.parse(this.getAttribute('data-periode'));
            
            document.getElementById('edit_periode_id').value = periode.id;
            document.getElementById('edit_nom').value = periode.nom;
            document.getElementById('edit_ordre').value = periode.ordre_periode;
            document.getElementById('edit_coefficient').value = periode.coefficient;
            document.getElementById('edit_date_debut').value = periode.date_debut || '';
            document.getElementById('edit_date_fin').value = periode.date_fin || '';
            document.getElementById('edit_verrouillee').checked = periode.verrouillee == 1;
            
            const editModal = new bootstrap.Modal(document.getElementById('editPeriodeModal'));
            editModal.show();
        });
    });
    
    // Gestion de la suppression de période
    document.querySelectorAll('.delete-periode').forEach(button => {
        button.addEventListener('click', function() {
            const periodeId = this.getAttribute('data-periode-id');
            const periodeNom = this.getAttribute('data-periode-nom');
            
            document.getElementById('delete_periode_id').value = periodeId;
            document.getElementById('delete_periode_nom').textContent = periodeNom;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deletePeriodeModal'));
            deleteModal.show();
        });
    });
});
</script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Dashboard JS -->
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
