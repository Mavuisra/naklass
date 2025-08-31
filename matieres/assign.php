<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier l'ID de la matière
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de matière invalide.');
    redirect('index.php');
}

$cours_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

// Récupérer les détails de la matière
try {
    $cours_query = "SELECT c.*, u1.prenom as created_by_prenom, u1.nom as created_by_nom
                     FROM cours c
                     LEFT JOIN utilisateurs u1 ON c.created_by = u1.id
                     WHERE c.id = :cours_id AND c.ecole_id = :ecole_id";
    
    $stmt = $db->prepare($cours_query);
    $stmt->execute(['cours_id' => $cours_id, 'ecole_id' => $_SESSION['ecole_id']]);
    $cours = $stmt->fetch();
    
    if (!$cours) {
        setFlashMessage('error', 'Matière non trouvée.');
        redirect('index.php');
    }
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération de la matière: ' . $e->getMessage());
    redirect('index.php');
}

// Récupérer les statistiques pour les cartes
$stats = [];
try {
    // Nombre total d'élèves inscrits dans l'école
    $query = "SELECT COUNT(DISTINCT i.eleve_id) as total 
              FROM inscriptions i 
              JOIN eleves e ON i.eleve_id = e.id 
              WHERE i.statut_inscription = 'validée' AND e.ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_eleves'] = $stmt->fetch()['total'];
    
    // Nombre de classes actives
    $query = "SELECT COUNT(*) as total FROM classes WHERE statut = 'actif' AND ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_classes'] = $stmt->fetch()['total'];
    
    // Nombre d'enseignants actifs
    $query = "SELECT COUNT(*) as total FROM enseignants WHERE statut_record = 'actif' AND ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_enseignants'] = $stmt->fetch()['total'];
    
    // Nombre de matières actives
    $query = "SELECT COUNT(*) as total FROM cours WHERE statut = 'actif' AND ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats['total_matieres'] = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    // En cas d'erreur, initialiser avec des valeurs par défaut
    $stats['total_eleves'] = 0;
    $stats['total_classes'] = 0;
    $stats['total_enseignants'] = 0;
    $stats['total_matieres'] = 0;
}

// Récupérer les classes disponibles
try {
    $classes_query = "SELECT c.*, 
                             COUNT(DISTINCT i.eleve_id) as nombre_eleves
                      FROM classes c
                      LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut_inscription = 'validée'
                      WHERE c.ecole_id = :ecole_id AND c.statut = 'actif'
                      GROUP BY c.id
                      ORDER BY c.nom_classe ASC";
    
    $stmt = $db->prepare($classes_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classes = $stmt->fetchAll();
    
} catch (Exception $e) {
    $classes = [];
    $errors[] = "Erreur lors de la récupération des classes: " . $e->getMessage();
}

// Récupérer les enseignants disponibles
try {
    $enseignants_query = "SELECT e.*, 
                                 COUNT(DISTINCT cc.id) as nombre_cours_assignes
                          FROM enseignants e
                          LEFT JOIN classe_cours cc ON e.id = cc.enseignant_id AND cc.statut = 'actif'
                          WHERE e.ecole_id = :ecole_id AND e.statut_record = 'actif'
                          GROUP BY e.id
                          ORDER BY e.nom ASC, e.prenom ASC";
    
    $stmt = $db->prepare($enseignants_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $enseignants = $stmt->fetchAll();
    
} catch (Exception $e) {
    $enseignants = [];
    $errors[] = "Erreur lors de la récupération des enseignants: " . $e->getMessage();
}

// Récupérer les affectations existantes
try {
    $assignments_query = "SELECT cc.*, c.nom_classe, c.niveau, c.cycle, c.annee_scolaire,
                                e.nom as enseignant_nom, e.prenom as enseignant_prenom,
                                COUNT(DISTINCT i.eleve_id) as nombre_eleves
                         FROM classe_cours cc
                         JOIN classes c ON cc.classe_id = c.id
                         JOIN enseignants e ON cc.enseignant_id = e.id
                         LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut_inscription = 'validée'
                         WHERE cc.cours_id = :cours_id AND cc.statut = 'actif'
                         GROUP BY cc.id
                         ORDER BY c.cycle ASC, c.niveau ASC, c.nom_classe ASC";
    
    $stmt = $db->prepare($assignments_query);
    $stmt->execute(['cours_id' => $cours_id]);
    $assignments = $stmt->fetchAll();
    
} catch (Exception $e) {
    $assignments = [];
    $errors[] = "Erreur lors de la récupération des affectations: " . $e->getMessage();
}

// Traitement du formulaire d'affectation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_course'])) {
    $classe_id = intval($_POST['classe_id'] ?? 0);
    $enseignant_id = intval($_POST['enseignant_id'] ?? 0);
    $annee_scolaire = sanitize($_POST['annee_scolaire'] ?? '');
    $coefficient_classe = floatval($_POST['coefficient_classe'] ?? $cours['coefficient']);
    $notes_internes = sanitize($_POST['notes_internes'] ?? '');
    
    // Validation
    if ($classe_id <= 0) {
        $errors[] = "Veuillez sélectionner une classe.";
    }
    if ($enseignant_id <= 0) {
        $errors[] = "Veuillez sélectionner un enseignant.";
    }
    if (empty($annee_scolaire)) {
        $errors[] = "Veuillez spécifier l'année scolaire.";
    }
    
    // Vérifier si l'affectation existe déjà
    if (empty($errors)) {
        $existing_query = "SELECT id FROM classe_cours 
                          WHERE classe_id = :classe_id AND cours_id = :cours_id 
                          AND annee_scolaire = :annee_scolaire AND statut = 'actif'";
        $stmt = $db->prepare($existing_query);
        $stmt->execute([
            'classe_id' => $classe_id,
            'cours_id' => $cours_id,
            'annee_scolaire' => $annee_scolaire
        ]);
        
        if ($stmt->fetch()) {
            $errors[] = "Cette matière est déjà affectée à cette classe pour l'année scolaire sélectionnée.";
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Insérer la nouvelle affectation
            $insert_query = "INSERT INTO classe_cours (
                classe_id, cours_id, enseignant_id, annee_scolaire, 
                coefficient_classe, notes_internes, created_by
            ) VALUES (
                :classe_id, :cours_id, :enseignant_id, :annee_scolaire,
                :coefficient_classe, :notes_internes, :created_by
            )";
            
            $stmt = $db->prepare($insert_query);
            $stmt->execute([
                'classe_id' => $classe_id,
                'cours_id' => $cours_id,
                'enseignant_id' => $enseignant_id,
                'annee_scolaire' => $annee_scolaire,
                'coefficient_classe' => $coefficient_classe,
                'notes_internes' => $notes_internes,
                'created_by' => $_SESSION['user_id']
            ]);
            
            $db->commit();
            
            $success = "La matière '{$cours['nom_cours']}' a été affectée avec succès.";
            
            // Rediriger pour éviter la soumission multiple
            redirect("assign.php?id={$cours_id}");
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Erreur lors de l'affectation: " . $e->getMessage();
        }
    }
}

// Traitement de la suppression d'affectation
if (isset($_POST['delete_assignment'])) {
    $assignment_id = intval($_POST['assignment_id'] ?? 0);
    
    if ($assignment_id > 0) {
        try {
            $db->beginTransaction();
            
            // Supprimer logiquement l'affectation
            $delete_query = "UPDATE classe_cours SET statut = 'supprimé_logique', updated_by = :updated_by WHERE id = :assignment_id";
            $stmt = $db->prepare($delete_query);
            $stmt->execute([
                'assignment_id' => $assignment_id,
                'updated_by' => $_SESSION['user_id']
            ]);
            
            $db->commit();
            
            $success = "L'affectation a été supprimée avec succès.";
            
            // Rediriger pour éviter la soumission multiple
            redirect("assign.php?id={$cours_id}");
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Erreur lors de la suppression: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affecter la matière <?php echo htmlspecialchars($cours['nom_cours']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <style>
        .sidebar-logo i { color: #ffd700; }
        .user-avatar i { color: #ffd700; }
        .menu-item.active .menu-link { border-left-color: #ffd700; }
        .menu-link:hover { border-left-color: #ffd700; }
        .stat-card { transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .matiere-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem 0; margin-bottom: 2rem; border-radius: 15px; }
        .assignment-card { transition: transform 0.2s, box-shadow 0.2s; border: 1px solid #e9ecef; }
        .assignment-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .form-section { background: #f8f9fa; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; }
        .stats-card { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 10px; padding: 1.5rem; }
    </style>
</head>
<body>
    <!-- Navigation latérale -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="bi bi-mortarboard-fill"></i>
                <span>Naklass</span>
            </div>
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="bi bi-person-circle"></i>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?></div>
                <div class="user-role"><?php echo ROLES[$_SESSION['user_role']] ?? $_SESSION['user_role']; ?></div>
                <div class="user-school"><?php echo htmlspecialchars($_SESSION['ecole_nom']); ?></div>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="../auth/dashboard.php" class="menu-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../students/" class="menu-link">
                    <i class="bi bi-people"></i>
                    <span>Élèves</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../classes/" class="menu-link">
                    <i class="bi bi-building"></i>
                    <span>Classes</span>
                </a>
            </li>
            
            <li class="menu-item active">
                <a href="../matieres/" class="menu-link">
                    <i class="bi bi-book"></i>
                    <span>Matières</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../teachers/" class="menu-link">
                    <i class="bi bi-person-badge"></i>
                    <span>Enseignants</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../finance/" class="menu-link">
                    <i class="bi bi-cash-coin"></i>
                    <span>Finances</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../grades/" class="menu-link">
                    <i class="bi bi-journal-text"></i>
                    <span>Notes & Bulletins</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../reports/" class="menu-link">
                    <i class="bi bi-graph-up"></i>
                    <span>Rapports</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../settings/" class="menu-link">
                    <i class="bi bi-gear"></i>
                    <span>Paramètres</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </nav>
    
    <!-- Contenu principal -->
    <main class="main-content">
        <!-- Barre supérieure -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-book me-2"></i>Affecter la matière</h1>
                <p class="text-muted">Gérer les affectations de <?php echo htmlspecialchars($cours['nom_cours']); ?></p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-2"></i>Retour aux matières
                </a>
            </div>
        </header>
        
        <!-- Contenu principal -->
        <div class="content-area">
            <!-- Messages flash -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php foreach ($errors as $error): ?>
                        <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Cartes de statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_eleves']); ?></h3>
                            <p>Élèves inscrits</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_classes']); ?></h3>
                            <p>Classes actives</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_enseignants']); ?></h3>
                            <p>Enseignants actifs</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-book"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_matieres']); ?></h3>
                            <p>Matières actives</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- En-tête de la matière -->
            <div class="matiere-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2">
                            <i class="bi bi-book me-3"></i>
                            <?php echo htmlspecialchars($cours['nom_cours']); ?>
                        </h1>
                        <p class="mb-1">
                            <strong>Code:</strong> <?php echo htmlspecialchars($cours['code_cours']); ?> |
                            <strong>Coefficient:</strong> <?php echo $cours['coefficient']; ?> |
                            <strong>Barème:</strong> <?php echo $cours['bareme_max']; ?>
                        </p>
                        <?php if (!empty($cours['description'])): ?>
                            <p class="mb-0 opacity-75"><?php echo htmlspecialchars($cours['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="view.php?id=<?php echo $cours_id; ?>" class="btn btn-outline-light me-2">
                            <i class="bi bi-eye me-2"></i>Voir
                        </a>
                        <a href="edit.php?id=<?php echo $cours_id; ?>" class="btn btn-outline-light">
                            <i class="bi bi-pencil me-2"></i>Modifier
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Formulaire d'affectation rapide -->
            <div class="card assignment-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-plus-circle me-2"></i>Nouvelle affectation
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="classe_id" class="form-label">Classe</label>
                                <select class="form-select" id="classe_id" name="classe_id" required>
                                    <option value="">Choisir une classe...</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>">
                                            <?php echo htmlspecialchars($classe['nom_classe']); ?>
                                            (<?php echo $classe['nombre_eleves']; ?> élèves)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="enseignant_id" class="form-label">Enseignant</label>
                                <select class="form-select" id="enseignant_id" name="enseignant_id" required>
                                    <option value="">Choisir un enseignant...</option>
                                    <?php foreach ($enseignants as $enseignant): ?>
                                        <option value="<?php echo $enseignant['id']; ?>">
                                            <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="annee_scolaire" class="form-label">Année scolaire</label>
                                <input type="text" class="form-control" id="annee_scolaire" name="annee_scolaire" 
                                       value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>" required>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="coefficient_classe" class="form-label">Coefficient</label>
                                <input type="number" class="form-control" id="coefficient_classe" name="coefficient_classe" 
                                       value="<?php echo $cours['coefficient']; ?>" step="0.1" min="0" required>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="notes_internes" class="form-label">Notes</label>
                                <input type="text" class="form-control" id="notes_internes" name="notes_internes" 
                                       placeholder="Optionnel">
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" name="assign_course" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Affecter la matière
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Affectations existantes -->
            <div class="card assignment-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-list-check me-2"></i>Affectations existantes
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assignments)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">Aucune affectation trouvée pour cette matière.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Classe</th>
                                        <th>Enseignant</th>
                                        <th>Année scolaire</th>
                                        <th>Élèves</th>
                                        <th>Coefficient</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($assignment['nom_classe']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($assignment['cycle'] . ' - ' . $assignment['niveau']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($assignment['enseignant_prenom'] . ' ' . $assignment['enseignant_nom']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($assignment['annee_scolaire']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $assignment['nombre_eleves']; ?> élèves</span>
                                            </td>
                                            <td><?php echo $assignment['coefficient_classe']; ?></td>
                                            <td>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                    <button type="submit" name="delete_assignment" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette affectation ?')">
                                                        <i class="bi bi-trash"></i>
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
    </main>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
