<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier l'ID de la classe
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de classe invalide.');
    redirect('index.php');
}

$class_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la classe
$classe = validateClassAccess($class_id, $db);
if (!$classe) {
    redirect('my_classes.php');
}

// Pour les enseignants, vérifier qu'ils ont accès à cette classe
if (hasRole('enseignant') && !hasRole(['admin', 'direction'])) {
    // Vérifier si l'enseignant est assigné à cette classe
    $enseignant_access_query = "SELECT COUNT(*) as has_access FROM classe_cours 
                               WHERE classe_id = :classe_id 
                               AND enseignant_id = (SELECT id FROM enseignants WHERE utilisateur_id = :user_id)
                               AND statut = 'actif'";
    
    $stmt = $db->prepare($enseignant_access_query);
    $stmt->execute([
        'classe_id' => $class_id,
        'user_id' => $_SESSION['user_id']
    ]);
    
    $access = $stmt->fetch();
    
    if (!$access['has_access']) {
        setFlashMessage('error', 'Vous n\'avez pas accès à cette classe.');
        redirect('my_classes.php');
    }
}
    
// Récupérer la liste des élèves de la classe
try {
    // Requête simple et robuste pour récupérer les élèves inscrits
    $students_query = "SELECT i.id as inscription_id,
                              el.prenom, 
                              el.nom, 
                              el.date_naissance,
                              el.sexe,
                              el.photo_url as photo,
                              el.matricule,
                              i.date_inscription,
                              i.statut_inscription,
                              i.statut as inscription_statut
                       FROM inscriptions i
                       JOIN eleves el ON i.eleve_id = el.id
                       WHERE i.classe_id = :class_id 
                       AND i.statut_inscription = 'validée'
                       AND i.statut = 'actif'
                       AND el.statut = 'actif'
                       ORDER BY el.nom ASC, el.prenom ASC";
    
    $stmt = $db->prepare($students_query);
    $stmt->execute(['class_id' => $class_id]);
    $eleves = $stmt->fetchAll();
    
    // Debug: Log le nombre d'élèves trouvés
    error_log("Nombre d'élèves trouvés pour la classe $class_id: " . count($eleves));
    
} catch (Exception $e) {
    $eleves = [];
    error_log("Erreur lors de la récupération des élèves inscrits: " . $e->getMessage());
}

// Récupérer le nombre total d'élèves dans l'école pour information
try {
    $total_eleves_query = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id AND statut = 'actif'";
    $stmt = $db->prepare($total_eleves_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $total_eleves_ecole = $stmt->fetch()['total'];
    
    // Récupérer aussi le nombre total d'inscriptions pour cette classe
    $total_inscriptions_query = "SELECT COUNT(*) as total FROM inscriptions WHERE classe_id = :classe_id";
    $stmt = $db->prepare($total_inscriptions_query);
    $stmt->execute(['classe_id' => $class_id]);
    $total_inscriptions_classe = $stmt->fetch()['total'];
    
    error_log("Total élèves école: $total_eleves_ecole, Total inscriptions classe $class_id: $total_inscriptions_classe");
    
} catch (Exception $e) {
    $total_eleves_ecole = 0;
    $total_inscriptions_classe = 0;
    error_log("Erreur lors du comptage des élèves: " . $e->getMessage());
}

// Récupérer les cours de la classe
$cours = getClassCourses($class_id, $db);

// Calculer les statistiques
$stats = getClassStatistics($class_id, $db);
$nombre_eleves = $stats['nombre_eleves'];
$capacite_percentage = $stats['capacite_percentage'];
$capacity_class = $stats['capacity_class'];

$page_title = "Classe : " . $classe['nom_classe'];
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
                <h1><i class="bi bi-building me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Détails et gestion de la classe</p>
            </div>
            
            <div class="topbar-actions">
                <a href="edit.php?id=<?php echo $classe['id']; ?>" class="btn btn-primary me-2">
                    <i class="bi bi-pencil me-2"></i>Modifier
                </a>
                <a href="students.php?class_id=<?php echo $classe['id']; ?>" class="btn btn-success me-2">
                    <i class="bi bi-people me-2"></i>Gérer les Élèves
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
            
            <div class="row">
                <!-- Informations de la classe -->
                        <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations de la Classe</h5>
                                    </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Nom de la classe</h6>
                                    <p class="h5 mb-3"><?php echo htmlspecialchars($classe['nom_classe'] ?? 'Non défini'); ?></p>
                                    
                                    <h6 class="text-muted">Niveau</h6>
                                    <p class="mb-3"><?php echo htmlspecialchars($classe['niveau'] ?? 'Non défini'); ?></p>
                                    
                                    <h6 class="text-muted">Cycle</h6>
                                    <p class="mb-3"><?php echo htmlspecialchars($classe['cycle'] ?? 'Non défini'); ?></p>
                                    
                                    <h6 class="text-muted">Statut</h6>
                                    <span class="badge bg-<?php echo ($classe['statut'] == 'actif' || $classe['statut'] == 'active') ? 'success' : 'secondary'; ?> mb-3">
                                        <?php echo ucfirst($classe['statut'] ?? 'Non défini'); ?>
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Enseignant principal</h6>
                                    <p class="mb-3">
                                        <?php if (isset($classe['enseignant_prenom']) && $classe['enseignant_prenom']): ?>
                                            <?php echo htmlspecialchars($classe['enseignant_prenom'] . ' ' . $classe['enseignant_nom']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non assigné</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <h6 class="text-muted">Salle</h6>
                                    <p class="mb-3"><?php echo htmlspecialchars($classe['salle_classe'] ?? 'Non définie'); ?></p>
                                    
                                    <h6 class="text-muted">Année Scolaire</h6>
                                    <p class="mb-3"><?php echo htmlspecialchars($classe['annee_scolaire'] ?? 'Non définie'); ?></p>
                                    
                                    <h6 class="text-muted">Créée le</h6>
                                    <p class="mb-3"><?php echo isset($classe['created_at']) ? date('d/m/Y', strtotime($classe['created_at'])) : 'Non défini'; ?></p>
                                </div>
                            </div>
                            
                            <!-- Informations supplémentaires -->
                            <div class="mt-3">
                                <h6 class="text-muted">Informations</h6>
                                <p class="mb-0">
                                    <small class="text-muted">
                                        Cycle d'enseignement : <strong><?php echo htmlspecialchars($classe['cycle'] ?? 'Non défini'); ?></strong><br>
                                        Capacité maximale : <strong><?php echo htmlspecialchars($classe['capacite_max'] ?? 'Non définie'); ?></strong> élèves
                                    </small>
                                </p>
                            </div>
                    </div>
                </div>
            </div>
            
                <!-- Statistiques et capacité -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Statistiques</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <h2 class="text-primary"><?php echo $nombre_eleves; ?></h2>
                                <p class="text-muted mb-0">Élèves inscrits</p>
                                <?php if (isset($total_eleves_ecole)): ?>
                                    <small class="text-muted">sur <?php echo $total_eleves_ecole; ?> élève(s) dans l'école</small>
                                <?php endif; ?>
                                
                                <?php if (isset($total_inscriptions_classe) && $total_inscriptions_classe > 0): ?>
                                    <br><small class="text-info">
                                        (<?php echo $total_inscriptions_classe; ?> inscription(s) au total)
                                    </small>
                                <?php endif; ?>
                                
                                <?php if ($nombre_eleves == 0 && isset($total_inscriptions_classe) && $total_inscriptions_classe > 0): ?>
                                    <div class="alert alert-warning mt-2 p-2">
                                        <small>
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            Il y a des inscriptions mais elles ne sont pas validées ou actives.
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($classe['capacite_max']) && $classe['capacite_max'] > 0): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">Capacité</small>
                                        <small class="fw-bold">
                                            <?php echo $nombre_eleves; ?> / <?php echo $classe['capacite_max']; ?>
                                            </small>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar <?php echo $capacity_class; ?>" 
                                             style="width: <?php echo min(100, $capacite_percentage); ?>%"></div>
                                </div>
                                    <small class="text-muted">
                                        <?php echo number_format($capacite_percentage, 1); ?>% de remplissage
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (hasRole(['admin', 'direction'])): ?>
                                <div class="d-grid">
                                    <a href="students.php?class_id=<?php echo $classe['id']; ?>" class="btn btn-success">
                                        <i class="bi bi-people me-2"></i>Gérer les Élèves
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="d-grid">
                                    <a href="students.php?class_id=<?php echo $classe['id']; ?>" class="btn btn-outline-secondary" title="Accès réservé aux administrateurs">
                                        <i class="bi bi-eye me-2"></i>Voir les Élèves
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Section de debug temporaire -->
                            <?php if ($_SESSION['user_role'] == 'admin'): ?>
                                <div class="mt-3">
                                    <details>
                                        <summary class="btn btn-outline-info btn-sm">Debug Info</summary>
                                        <div class="mt-2 p-2 bg-light rounded">
                                            <small>
                                                <strong>Classe ID:</strong> <?php echo $class_id; ?><br>
                                                <strong>École ID:</strong> <?php echo $_SESSION['ecole_id']; ?><br>
                                                <strong>Élèves comptés:</strong> <?php echo $nombre_eleves; ?><br>
                                                <strong>Total inscriptions:</strong> <?php echo $total_inscriptions_classe ?? 'N/A'; ?><br>
                                                <strong>Total élèves école:</strong> <?php echo $total_eleves_ecole ?? 'N/A'; ?><br>
                                                
                                                <?php if (!empty($eleves)): ?>
                                                    <strong>Premier élève:</strong> <?php echo htmlspecialchars($eleves[0]['prenom'] . ' ' . $eleves[0]['nom']); ?><br>
                                                <?php endif; ?>
                                                
                                                <?php
                                                // Test rapide de la table inscriptions
                                                try {
                                                    $test_query = "SELECT COUNT(*) as count, 
                                                                          GROUP_CONCAT(DISTINCT statut) as statuts,
                                                                          GROUP_CONCAT(DISTINCT statut_inscription) as statuts_inscription
                                                                   FROM inscriptions 
                                                                   WHERE classe_id = :classe_id";
                                                    $test_stmt = $db->prepare($test_query);
                                                    $test_stmt->execute(['classe_id' => $class_id]);
                                                    $test_result = $test_stmt->fetch();
                                                    echo "<strong>Test inscriptions:</strong> " . $test_result['count'] . " total<br>";
                                                    echo "<strong>Statuts:</strong> " . $test_result['statuts'] . "<br>";
                                                    echo "<strong>Statuts inscription:</strong> " . $test_result['statuts_inscription'] . "<br>";
                                                } catch (Exception $e) {
                                                    echo "<strong>Erreur test:</strong> " . $e->getMessage() . "<br>";
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    </details>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Liste des élèves -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Élèves de la Classe</h5>
                        <span class="badge bg-primary"><?php echo $nombre_eleves; ?> élève(s)</span>
                    </div>
                        <div class="card-body">
                            <?php if (empty($eleves)): ?>
                                <div class="text-center p-4">
                            <i class="bi bi-people display-4 text-muted mb-3"></i>
                            <h6>Aucun élève inscrit</h6>
                            <p class="text-muted">
                                Cette classe n'a pas encore d'élèves inscrits.
                                <?php if (isset($total_eleves_ecole) && $total_eleves_ecole > 0): ?>
                                    <br><strong><?php echo $total_eleves_ecole; ?> élève(s) disponible(s) dans l'école.</strong>
                                <?php elseif (isset($total_eleves_ecole) && $total_eleves_ecole == 0): ?>
                                    <br><strong class="text-warning">Aucun élève dans l'école. Ajoutez d'abord des élèves.</strong>
                                <?php endif; ?>
                            </p>
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="students.php?class_id=<?php echo $classe['id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Gérer les Élèves
                                </a>
                                <?php if (isset($total_eleves_ecole) && $total_eleves_ecole == 0): ?>
                                    <a href="add_test_students.php" class="btn btn-warning">
                                        <i class="bi bi-person-plus me-2"></i>Ajouter des Élèves de Test
                                    </a>
                                <?php endif; ?>
                            </div>
                                </div>
                            <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Matricule</th>
                                        <th>Date de naissance</th>
                                        <th>Sexe</th>
                                        <th>Date d'inscription</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($eleves as $eleve): ?>
                                        <tr>
                                            <td>
                                                <?php if ($eleve['photo']): ?>
                                                    <img src="../uploads/students/<?php echo htmlspecialchars($eleve['photo']); ?>" 
                                                         alt="Photo" class="rounded-circle" width="40" height="40">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="bi bi-person"></i>
                                                            </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($eleve['nom']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($eleve['prenom']); ?></td>
                                            <td><code><?php echo htmlspecialchars($eleve['matricule']); ?></code></td>
                                            <td><?php echo date('d/m/Y', strtotime($eleve['date_naissance'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $eleve['sexe'] == 'M' ? 'primary' : 'pink'; ?>">
                                                    <?php echo $eleve['sexe'] == 'M' ? 'Masculin' : 'Féminin'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($eleve['date_inscription'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Cours de la classe -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-book me-2"></i>Cours de la Classe</h5>
                        <span class="badge bg-info"><?php echo count($cours); ?> cours</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cours)): ?>
                            <div class="text-center p-4">
                                <i class="bi bi-book display-4 text-muted mb-3"></i>
                                <h6>Aucun cours assigné</h6>
                                <p class="text-muted">Cette classe n'a pas encore de cours assignés.</p>
                                <a href="assign.php?id=<?php echo $classe['id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Assigner des Cours
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Cours</th>
                                            <th>Coefficient</th>
                                            <th>Enseignant</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cours as $c): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($c['code_cours']); ?></code></td>
                                                <td><strong><?php echo htmlspecialchars($c['nom_cours']); ?></strong></td>
                                                <td><span class="badge bg-secondary"><?php echo $c['coefficient']; ?></span></td>
                                                <td>
                                                    <?php if ($c['enseignant_prenom']): ?>
                                                        <?php echo htmlspecialchars($c['enseignant_prenom'] . ' ' . $c['enseignant_nom']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Non assigné</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="presence.php?classe_id=<?php echo $classe['id']; ?>&cours_id=<?php echo $c['cours_id']; ?>" 
                                                           class="btn btn-outline-primary" title="Faire la présence">
                                                            <i class="bi bi-clipboard-check"></i> Présence
                                                        </a>
                                                        <a href="notes.php?classe_id=<?php echo $classe['id']; ?>&cours_id=<?php echo $c['cours_id']; ?>" 
                                                           class="btn btn-outline-success" title="Gérer les notes">
                                                            <i class="bi bi-pencil-square"></i> Notes
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
                
                <!-- Actions rapides -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Actions Rapides</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="presence.php?classe_id=<?php echo $classe['id']; ?>" class="btn btn-primary w-100">
                                    <i class="bi bi-clipboard-check me-2"></i>Faire la Présence
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="notes.php?classe_id=<?php echo $classe['id']; ?>" class="btn btn-success w-100">
                                    <i class="bi bi-pencil-square me-2"></i>Gérer les Notes
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="students.php?class_id=<?php echo $classe['id']; ?>" class="btn btn-info w-100">
                                    <i class="bi bi-people me-2"></i>Gérer les Élèves
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="assign.php?id=<?php echo $classe['id']; ?>" class="btn btn-warning w-100">
                                    <i class="bi bi-book me-2"></i>Assigner des Cours
                                </a>
                            </div>
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