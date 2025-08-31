<?php
/**
 * Page de validation des écoles par le Super Admin
 * Permet de valider, rejeter ou demander des modifications sur les écoles
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est super admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'super_admin') {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ecole_id = (int)($_POST['ecole_id'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');
    
    if ($ecole_id && in_array($action, ['approve', 'reject', 'request_changes'])) {
        try {
            $db->beginTransaction();
            
            switch ($action) {
                case 'approve':
                    $query = "UPDATE ecoles SET 
                              super_admin_validated = TRUE,
                              validation_status = 'approved',
                              date_validation_super_admin = NOW(),
                              validated_by_super_admin = :super_admin_id
                              WHERE id = :ecole_id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'super_admin_id' => $_SESSION['user_id'],
                        'ecole_id' => $ecole_id
                    ]);
                    
                    // Créer notification pour l'admin de l'école
                    $query = "INSERT INTO super_admin_notifications (type, ecole_id, message) 
                             VALUES ('school_validation', :ecole_id, :message)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'ecole_id' => $ecole_id,
                        'message' => 'Votre école a été validée par le super administrateur !'
                    ]);
                    
                    // Enregistrer dans l'historique
                    $query = "INSERT INTO school_validation_history (ecole_id, action, performed_by, notes) 
                             VALUES (:ecole_id, 'validated', :super_admin_id, :notes)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'ecole_id' => $ecole_id,
                        'super_admin_id' => $_SESSION['user_id'],
                        'notes' => $notes ?: 'Validation par super admin'
                    ]);
                    
                    $success = "L'école a été validée avec succès !";
                    break;
                    
                case 'reject':
                    $query = "UPDATE ecoles SET 
                              super_admin_validated = FALSE,
                              validation_status = 'rejected',
                              validation_notes = :notes
                              WHERE id = :ecole_id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'notes' => $notes,
                        'ecole_id' => $ecole_id
                    ]);
                    
                    // Créer notification pour l'admin de l'école
                    $query = "INSERT INTO super_admin_notifications (type, ecole_id, message) 
                             VALUES ('school_rejection', :ecole_id, :message)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'ecole_id' => $ecole_id,
                        'message' => 'Votre école a été rejetée par le super administrateur. Raison : ' . $notes
                    ]);
                    
                    // Enregistrer dans l'historique
                    $query = "INSERT INTO school_validation_history (ecole_id, action, performed_by, notes) 
                             VALUES (:ecole_id, 'rejected', :super_admin_id, :notes)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'ecole_id' => $ecole_id,
                        'super_admin_id' => $_SESSION['user_id'],
                        'notes' => $notes ?: 'Rejet par super admin'
                    ]);
                    
                    $success = "L'école a été rejetée avec succès !";
                    break;
                    
                case 'request_changes':
                    $query = "UPDATE ecoles SET 
                              super_admin_validated = FALSE,
                              validation_status = 'needs_changes',
                              validation_notes = :notes
                              WHERE id = :ecole_id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'notes' => $notes,
                        'ecole_id' => $ecole_id
                    ]);
                    
                    // Créer notification pour l'admin de l'école
                    $query = "INSERT INTO super_admin_notifications (type, ecole_id, message) 
                             VALUES ('school_rejection', :ecole_id, :message)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'ecole_id' => $ecole_id,
                        'message' => 'Le super administrateur demande des modifications : ' . $notes
                    ]);
                    
                    // Enregistrer dans l'historique
                    $query = "INSERT INTO school_validation_history (ecole_id, action, performed_by, notes) 
                             VALUES (:ecole_id, 'changes_requested', :super_admin_id, :notes)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        'ecole_id' => $ecole_id,
                        'super_admin_id' => $_SESSION['user_id'],
                        'notes' => $notes ?: 'Modifications demandées par super admin'
                    ]);
                    
                    $success = "Les modifications ont été demandées avec succès !";
                    break;
            }
            
            $db->commit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Erreur système : ' . $e->getMessage();
        }
    }
}

// Récupérer les écoles à valider
$query = "SELECT e.*, 
          u.nom as admin_nom, u.prenom as admin_prenom, u.email as admin_email,
          COUNT(san.id) as notification_count
          FROM ecoles e
          LEFT JOIN utilisateurs u ON e.created_by_visitor = u.id
          LEFT JOIN super_admin_notifications san ON e.id = san.ecole_id AND san.is_read = FALSE
          WHERE e.validation_status IN ('pending', 'needs_changes')
          GROUP BY e.id
          ORDER BY e.date_creation_ecole ASC";

$stmt = $db->prepare($query);
$stmt->execute();
$ecoles_pending = $stmt->fetchAll();

// Récupérer les écoles validées
$query = "SELECT e.*, 
          u.nom as admin_nom, u.prenom as admin_prenom, u.email as admin_email,
          sa.nom as super_admin_nom, sa.prenom as super_admin_prenom
          FROM ecoles e
          LEFT JOIN utilisateurs u ON e.created_by_visitor = u.id
          LEFT JOIN utilisateurs sa ON e.validated_by_super_admin = sa.id
          WHERE e.validation_status = 'approved'
          ORDER BY e.date_validation_super_admin DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$ecoles_approved = $stmt->fetchAll();

// Récupérer les écoles rejetées
$query = "SELECT e.*, 
          u.nom as admin_nom, u.prenom as admin_prenom, u.email as admin_email
          FROM ecoles e
          LEFT JOIN utilisateurs u ON e.created_by_visitor = u.id
          WHERE e.validation_status = 'rejected'
          ORDER BY e.updated_at DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$ecoles_rejected = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation des Écoles - Super Admin Naklass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .school-card {
            transition: transform 0.2s;
        }
        .school-card:hover {
            transform: translateY(-2px);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../superadmin/dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="school_validation.php">
                                <i class="bi bi-building-check me-2"></i>Validation Écoles
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-building-check me-2"></i>Validation des Écoles
                    </h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Écoles en attente de validation -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock me-2"></i>Écoles en attente de validation 
                                    <span class="badge bg-dark ms-2"><?php echo count($ecoles_pending); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($ecoles_pending)): ?>
                                    <p class="text-muted text-center">Aucune école en attente de validation.</p>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($ecoles_pending as $ecole): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="card school-card h-100 position-relative">
                                                    <?php if ($ecole['notification_count'] > 0): ?>
                                                        <span class="notification-badge"><?php echo $ecole['notification_count']; ?></span>
                                                    <?php endif; ?>
                                                    
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo htmlspecialchars($ecole['nom']); ?></h6>
                                                        <p class="card-text small">
                                                            <strong>Admin:</strong> <?php echo htmlspecialchars($ecole['admin_nom'] . ' ' . $ecole['admin_prenom']); ?><br>
                                                            <strong>Email:</strong> <?php echo htmlspecialchars($ecole['admin_email']); ?><br>
                                                            <strong>Téléphone:</strong> <?php echo htmlspecialchars($ecole['telephone']); ?><br>
                                                            <strong>Créée le:</strong> <?php echo date('d/m/Y', strtotime($ecole['date_creation_ecole'])); ?>
                                                        </p>
                                                        
                                                        <div class="d-grid gap-2">
                                                            <button class="btn btn-success btn-sm" 
                                                                    onclick="showValidationModal(<?php echo $ecole['id']; ?>, 'approve', '<?php echo htmlspecialchars($ecole['nom']); ?>')">
                                                                <i class="bi bi-check-circle me-1"></i>Valider
                                                            </button>
                                                            <button class="btn btn-warning btn-sm" 
                                                                    onclick="showValidationModal(<?php echo $ecole['id']; ?>, 'request_changes', '<?php echo htmlspecialchars($ecole['nom']); ?>')">
                                                                <i class="bi bi-pencil me-1"></i>Demander modifications
                                                            </button>
                                                            <button class="btn btn-danger btn-sm" 
                                                                    onclick="showValidationModal(<?php echo $ecole['id']; ?>, 'reject', '<?php echo htmlspecialchars($ecole['nom']); ?>')">
                                                                <i class="bi bi-x-circle me-1"></i>Rejeter
                                                            </button>
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

                <!-- Écoles validées -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-check-circle me-2"></i>Écoles validées 
                                    <span class="badge bg-light text-dark ms-2"><?php echo count($ecoles_approved); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($ecoles_approved)): ?>
                                    <p class="text-muted text-center">Aucune école validée.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>École</th>
                                                    <th>Admin</th>
                                                    <th>Validée le</th>
                                                    <th>Par</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ecoles_approved as $ecole): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($ecole['nom']); ?></td>
                                                        <td><?php echo htmlspecialchars($ecole['admin_nom'] . ' ' . $ecole['admin_prenom']); ?></td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($ecole['date_validation_super_admin'])); ?></td>
                                                        <td><?php echo htmlspecialchars($ecole['super_admin_nom'] . ' ' . $ecole['super_admin_prenom']); ?></td>
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

                <!-- Écoles rejetées -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-x-circle me-2"></i>Écoles rejetées 
                                    <span class="badge bg-light text-dark ms-2"><?php echo count($ecoles_rejected); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($ecoles_rejected)): ?>
                                    <p class="text-muted text-center">Aucune école rejetée.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>École</th>
                                                    <th>Admin</th>
                                                    <th>Raison du rejet</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ecoles_rejected as $ecole): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($ecole['nom']); ?></td>
                                                        <td><?php echo htmlspecialchars($ecole['admin_nom'] . ' ' . $ecole['admin_prenom']); ?></td>
                                                        <td><?php echo htmlspecialchars($ecole['validation_notes']); ?></td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($ecole['updated_at'])); ?></td>
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
            </main>
        </div>
    </div>

    <!-- Modal de validation -->
    <div class="modal fade" id="validationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="validationModalTitle">Validation d'école</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="validationAction">
                        <input type="hidden" name="ecole_id" id="validationEcoleId">
                        
                        <p id="validationMessage"></p>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (optionnel)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Ajoutez des commentaires ou des instructions..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn" id="validationSubmitBtn">Confirmer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showValidationModal(ecoleId, action, ecoleNom) {
            const modal = new bootstrap.Modal(document.getElementById('validationModal'));
            const actionInput = document.getElementById('validationAction');
            const ecoleIdInput = document.getElementById('validationEcoleId');
            const message = document.getElementById('validationMessage');
            const submitBtn = document.getElementById('validationSubmitBtn');
            
            actionInput.value = action;
            ecoleIdInput.value = ecoleId;
            
            switch (action) {
                case 'approve':
                    message.innerHTML = `Êtes-vous sûr de vouloir <strong>valider</strong> l'école <strong>${ecoleNom}</strong> ?`;
                    submitBtn.className = 'btn btn-success';
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Valider';
                    break;
                case 'reject':
                    message.innerHTML = `Êtes-vous sûr de vouloir <strong>rejeter</strong> l'école <strong>${ecoleNom}</strong> ?`;
                    submitBtn.className = 'btn btn-danger';
                    submitBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i>Rejeter';
                    break;
                case 'request_changes':
                    message.innerHTML = `Êtes-vous sûr de vouloir <strong>demander des modifications</strong> pour l'école <strong>${ecoleNom}</strong> ?`;
                    submitBtn.className = 'btn btn-warning';
                    submitBtn.innerHTML = '<i class="bi bi-pencil me-1"></i>Demander modifications';
                    break;
            }
            
            modal.show();
        }
    </script>
</body>
</html>
