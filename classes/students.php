<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier l'ID de la classe
if (!isset($_GET['class_id']) || !is_numeric($_GET['class_id'])) {
    setFlashMessage('error', 'ID de classe invalide.');
    redirect('index.php');
}

$class_id = (int)$_GET['class_id'];
$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la classe AVANT le traitement des actions
try {
    $class_query = "SELECT c.* FROM classes c WHERE c.id = :class_id AND c.ecole_id = :ecole_id";
    
    $stmt = $db->prepare($class_query);
    $stmt->execute([
        'class_id' => $class_id,
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    $classe = $stmt->fetch();
    
    if (!$classe) {
        setFlashMessage('error', 'Classe non trouvée.');
        redirect('index.php');
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération des détails de la classe: ' . $e->getMessage());
    redirect('index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_student':
                $eleve_id = (int)$_POST['eleve_id'];
                $date_inscription = $_POST['date_inscription'];
                $notes = sanitize($_POST['notes']);
                
                // Vérifier si l'élève n'est pas déjà inscrit dans cette classe
                try {
                    $check_query = "SELECT id FROM inscriptions WHERE eleve_id = :eleve_id AND classe_id = :classe_id AND statut != 'annulée'";
                    $stmt = $db->prepare($check_query);
                    $stmt->execute(['eleve_id' => $eleve_id, 'classe_id' => $class_id]);
                    
                    if ($stmt->fetch()) {
                        setFlashMessage('error', 'Cet élève est déjà inscrit dans cette classe.');
                    } else {
                        // Inscrire l'élève
                        $insert_query = "INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire, date_inscription, statut, remarques, created_by, statut_record) 
                                       VALUES (:eleve_id, :classe_id, :annee_scolaire, :date_inscription, 'validée', :notes, :created_by, 'actif')";
                        $stmt = $db->prepare($insert_query);
                        $result = $stmt->execute([
                            'eleve_id' => $eleve_id,
                            'classe_id' => $class_id,
                            'annee_scolaire' => $classe['annee_scolaire'],
                            'date_inscription' => $date_inscription,
                            'notes' => $notes,
                            'created_by' => $_SESSION['user_id']
                        ]);
                        
                        if ($result) {
                            // Mettre à jour l'effectif de la classe
                            $update_effectif = "UPDATE classes SET effectif_actuel = effectif_actuel + 1 WHERE id = :classe_id";
                            $stmt = $db->prepare($update_effectif);
                            $stmt->execute(['classe_id' => $class_id]);
                            
                            setFlashMessage('success', 'Élève inscrit avec succès dans la classe.');
                        } else {
                            setFlashMessage('error', 'Erreur lors de l\'inscription de l\'élève.');
                        }
                    }
                } catch (Exception $e) {
                    setFlashMessage('error', 'Erreur lors de l\'inscription : ' . $e->getMessage());
                }
                break;
                
            case 'remove_student':
                $inscription_id = (int)$_POST['inscription_id'];
                
                try {
                    $update_query = "UPDATE inscriptions SET statut = 'archivé', updated_at = CURRENT_TIMESTAMP WHERE id = :inscription_id AND classe_id = :classe_id";
                    $stmt = $db->prepare($update_query);
                    $result = $stmt->execute([
                        'inscription_id' => $inscription_id,
                        'classe_id' => $class_id
                    ]);
                    
                    if ($result) {
                        // Mettre à jour l'effectif de la classe
                        $update_effectif = "UPDATE classes SET effectif_actuel = GREATEST(0, effectif_actuel - 1) WHERE id = :classe_id";
                        $stmt = $db->prepare($update_effectif);
                        $stmt->execute(['classe_id' => $class_id]);
                        
                        setFlashMessage('success', 'Élève retiré de la classe avec succès.');
                    } else {
                        setFlashMessage('error', 'Erreur lors du retrait de l\'élève.');
                    }
                } catch (Exception $e) {
                    setFlashMessage('error', 'Erreur lors du retrait : ' . $e->getMessage());
                }
                break;
        }
    }
    
    // Redirection pour éviter la soumission multiple
    redirect("students.php?class_id={$class_id}");
}

// Les détails de la classe ont déjà été récupérés plus haut

// Récupérer la liste des élèves de la classe
try {
    $students_query = "SELECT i.*, 
                              el.prenom, 
                              el.nom, 
                              el.date_naissance,
                              el.sexe,
                              el.photo_path as photo,
                              el.matricule,
                              el.telephone,
                              el.email
                       FROM inscriptions i
                       JOIN eleves el ON i.eleve_id = el.id
                       WHERE i.classe_id = :class_id AND i.statut = 'actif'
                       ORDER BY el.nom ASC, el.prenom ASC";
    
    $stmt = $db->prepare($students_query);
    $stmt->execute(['class_id' => $class_id]);
    $eleves = $stmt->fetchAll();
    
} catch (Exception $e) {
    $eleves = [];
    error_log("Erreur lors de la récupération des élèves inscrits: " . $e->getMessage());
    // Ne pas afficher l'erreur à l'utilisateur, juste logger
}

// Récupérer la liste des élèves disponibles pour inscription
try {
    $available_students_query = "SELECT el.* FROM eleves el 
                                WHERE el.ecole_id = :ecole_id 
                                AND el.statut = 'actif'
                                AND el.id NOT IN (
                                    SELECT COALESCE(eleve_id, 0) FROM inscriptions 
                                    WHERE classe_id = :class_id AND statut = 'actif'
                                )
                                ORDER BY el.nom ASC, el.prenom ASC";
    
    $stmt = $db->prepare($available_students_query);
    $stmt->execute([
        'ecole_id' => $_SESSION['ecole_id'],
        'class_id' => $class_id
    ]);
    $eleves_disponibles = $stmt->fetchAll();
    
} catch (Exception $e) {
    $eleves_disponibles = [];
    // Log l'erreur pour debug
    error_log("Erreur lors de la récupération des élèves disponibles: " . $e->getMessage());
}

// Calculer les statistiques
$nombre_eleves_actifs = count($eleves);
$capacite_percentage = $classe['capacite_max'] > 0 ? ($nombre_eleves_actifs / $classe['capacite_max']) * 100 : 0;

$page_title = "Gestion des Élèves - " . $classe['nom_classe'];
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
                <p class="text-muted">Gérer les inscriptions d'élèves dans cette classe</p>
            </div>
            
            <div class="topbar-actions">
                <a href="view.php?id=<?php echo $classe['id']; ?>" class="btn btn-outline-info me-2">
                    <i class="bi bi-eye me-2"></i>Voir la Classe
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
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
            
            <!-- Informations de la classe -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-1"><?php echo htmlspecialchars($classe['nom_classe']); ?></h5>
                            <p class="text-muted mb-0">
                                <span class="badge bg-primary me-2"><?php echo ucfirst($classe['cycle']); ?></span>
                                <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($classe['niveau']); ?></span>
                                <span class="badge bg-info"><?php echo htmlspecialchars($classe['annee_scolaire']); ?></span>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="d-flex align-items-center justify-content-md-end">
                                <span class="me-3">
                                    <strong><?php echo $nombre_eleves_actifs; ?></strong> élève(s) inscrit(s)
                                </span>
                                <?php if ($classe['capacite_max']): ?>
                                    <span class="badge bg-<?php echo $capacite_percentage >= 80 ? 'danger' : ($capacite_percentage >= 60 ? 'warning' : 'success'); ?>">
                                        <?php echo $nombre_eleves_actifs; ?>/<?php echo $classe['capacite_max']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Liste des élèves inscrits -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Élèves Inscrits</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($eleves)): ?>
                                <div class="text-center p-4">
                                    <i class="bi bi-people display-4 text-muted mb-3"></i>
                                    <h6>Aucun élève inscrit</h6>
                                    <p class="text-muted">Cette classe n'a pas encore d'élèves inscrits.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Élève</th>
                                                <th>Matricule</th>
                                                <th>Statut</th>
                                                <th>Date d'inscription</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($eleves as $eleve): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($eleve['photo']): ?>
                                                                <img src="../uploads/students/<?php echo htmlspecialchars($eleve['photo']); ?>" 
                                                                     alt="Photo" class="rounded-circle me-2" width="32" height="32">
                                                            <?php else: ?>
                                                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white me-2" 
                                                                     style="width: 32px; height: 32px;">
                                                                    <i class="bi bi-person"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></div>
                                                                <small class="text-muted">
                                                                    <?php echo htmlspecialchars($eleve['date_naissance']); ?> 
                                                                    (<?php echo $eleve['sexe'] == 'M' ? 'Garçon' : 'Fille'; ?>)
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($eleve['matricule']); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">Inscrit</span>
                                                    </td>
                                                    <td>
                                                        <?php echo date('d/m/Y', strtotime($eleve['date_inscription'])); ?>
                                                    </td>
                                                    <td>
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Êtes-vous sûr de vouloir retirer cet élève de la classe ?');">
                                                            <input type="hidden" name="action" value="remove_student">
                                                            <input type="hidden" name="inscription_id" value="<?php echo $eleve['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer de la classe">
                                                                <i class="bi bi-person-x"></i>
                                                            </button>
                                                        </form>
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
                
                <!-- Ajouter un élève -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Ajouter un Élève</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($eleves_disponibles)): ?>
                                <div class="text-center p-3">
                                    <i class="bi bi-check-circle text-success display-6 mb-2"></i>
                                    <p class="text-muted">Tous les élèves sont déjà inscrits dans une classe.</p>
                                </div>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_student">
                                    
                                    <div class="mb-3">
                                        <label for="eleve_id" class="form-label">Sélectionner un élève</label>
                                        <select class="form-select" id="eleve_id" name="eleve_id" required>
                                            <option value="">Choisir un élève...</option>
                                            <?php foreach ($eleves_disponibles as $eleve): ?>
                                                <option value="<?php echo $eleve['id']; ?>">
                                                    <?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom'] . ' (' . $eleve['matricule'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="date_inscription" class="form-label">Date d'inscription</label>
                                        <input type="date" class="form-control" id="date_inscription" name="date_inscription" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes (optionnel)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                  placeholder="Observations, remarques..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-person-plus me-2"></i>Inscrire l'Élève
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
