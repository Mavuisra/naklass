<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'enseignant
try {
    if (hasRole(['admin', 'direction'])) {
        // Admin et direction peuvent voir toutes les classes
        $enseignant = null;
    } else {
        // Vérifier que l'utilisateur est bien un enseignant
        $enseignant_query = "SELECT e.* FROM enseignants e 
                             JOIN utilisateurs u ON e.utilisateur_id = u.id 
                             WHERE u.id = :user_id AND e.ecole_id = :ecole_id AND e.statut = 'actif'";
        $stmt = $db->prepare($enseignant_query);
        $stmt->execute(['user_id' => $user_id, 'ecole_id' => $_SESSION['ecole_id']]);
        $enseignant = $stmt->fetch();
        
        if (!$enseignant) {
            throw new Exception("Enseignant non trouvé ou non autorisé. Contactez l'administration.");
        }
    }
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// Récupérer les classes et cours selon le rôle
try {
    $classes = []; // Initialiser $classes
    
    if (hasRole(['admin', 'direction'])) {
        // Admin et direction voient toutes les classes
        $classes_query = "SELECT DISTINCT c.*, 
                                 COUNT(DISTINCT i.eleve_id) as nb_eleves,
                                 COUNT(DISTINCT cc.cours_id) as nb_cours
                          FROM classes c 
                          LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut IN ('validée', 'en_cours')
                          LEFT JOIN classe_cours cc ON c.id = cc.classe_id AND cc.statut = 'actif'
                          WHERE c.ecole_id = :ecole_id AND c.statut = 'actif'
                          GROUP BY c.id
                          ORDER BY c.niveau, c.nom_classe";
        $stmt = $db->prepare($classes_query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $classes = $stmt->fetchAll();
    } else {
        // Enseignants voient seulement leurs classes
        if ($enseignant) {
            $classes_query = "SELECT DISTINCT c.*, 
                                     COUNT(DISTINCT i.eleve_id) as nb_eleves,
                                     COUNT(DISTINCT cc.cours_id) as nb_cours
                              FROM classes c 
                              JOIN classe_cours cc ON c.id = cc.classe_id
                              LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut IN ('validée', 'en_cours')
                              WHERE c.ecole_id = :ecole_id AND c.statut = 'actif'
                              AND cc.enseignant_id = :enseignant_id AND cc.statut = 'actif'
                              GROUP BY c.id
                              ORDER BY c.niveau, c.nom_classe";
            $stmt = $db->prepare($classes_query);
            $stmt->execute(['ecole_id' => $_SESSION['ecole_id'], 'enseignant_id' => $enseignant['id']]);
            $classes = $stmt->fetchAll();
        }
        // Si pas d'enseignant, $classes reste un tableau vide
    }
    
} catch (Exception $e) {
    $classes = [];
    $errors[] = "Erreur lors de la récupération des classes: " . $e->getMessage();
}

// Récupérer les cours de l'enseignant
try {
    $cours = []; // Initialiser $cours
    
    if (hasRole(['admin', 'direction'])) {
        // Admin et direction voient tous les cours
        $cours_query = "SELECT DISTINCT cc.id as classe_cours_id, cc.classe_id, cc.cours_id, cc.coefficient_classe,
                               c.nom_classe, c.niveau, c.cycle, co.nom_cours, co.code_cours, co.coefficient,
                               e.nom as enseignant_nom, e.prenom as enseignant_prenom
                        FROM classe_cours cc
                        JOIN classes c ON cc.classe_id = c.id
                        JOIN cours co ON cc.cours_id = co.id
                        JOIN enseignants e ON cc.enseignant_id = e.id
                        WHERE c.ecole_id = :ecole_id AND cc.statut = 'actif' AND c.statut = 'actif' AND co.statut = 'actif'
                        ORDER BY c.niveau, c.nom_classe, co.nom_cours";
        $stmt = $db->prepare($cours_query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $cours = $stmt->fetchAll();
    } else {
                    // Enseignants voient seulement leurs cours
            if ($enseignant) {
                $cours_query = "SELECT DISTINCT cc.id as classe_cours_id, cc.classe_id, cc.cours_id, cc.coefficient_classe,
                                       c.nom_classe, c.niveau, c.cycle, co.nom_cours, co.code_cours, co.coefficient,
                                       e.nom as enseignant_nom, e.prenom as enseignant_prenom
                                FROM classe_cours cc
                                JOIN classes c ON cc.classe_id = c.id
                                JOIN cours co ON cc.cours_id = co.id
                                JOIN enseignants e ON cc.enseignant_id = e.id
                                WHERE c.ecole_id = :ecole_id AND cc.statut = 'actif' AND c.statut = 'actif' AND co.statut = 'actif'
                                AND cc.enseignant_id = :enseignant_id
                                ORDER BY c.niveau, c.nom_classe, co.nom_cours";
            $stmt = $db->prepare($cours_query);
            $stmt->execute(['ecole_id' => $_SESSION['ecole_id'], 'enseignant_id' => $enseignant['id']]);
            $cours = $stmt->fetchAll();
        }
        // Si pas d'enseignant, $cours reste un tableau vide
    }
    
} catch (Exception $e) {
    $cours = [];
    $errors[] = "Erreur lors de la récupération des cours: " . $e->getMessage();
}

// Statistiques
$stats = [
    'total_classes' => count($classes),
    'total_cours' => count($cours),
    'total_eleves' => array_sum(array_column($classes, 'nb_eleves')),
    'annee_scolaire' => date('Y') . '-' . (date('Y') + 1)
];

$page_title = hasRole(['admin', 'direction']) ? "Toutes les Classes" : "Mes Classes et Cours";
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
        .class-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
        }
        .class-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .course-card {
            transition: all 0.3s ease;
            border-left: 4px solid #28a745;
        }
        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .cycle-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .niveau-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
                <h1>
                    <i class="bi bi-<?php echo hasRole(['admin', 'direction']) ? 'building' : 'person-badge'; ?> me-2"></i>
                    <?php echo $page_title; ?>
                </h1>
                <p class="text-muted">
                    <?php if (hasRole(['admin', 'direction'])): ?>
                        Vue d'ensemble de toutes les classes de l'école
                    <?php else: ?>
                        Vos classes et cours pour l'année scolaire <?php echo $stats['annee_scolaire']; ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="topbar-actions">
                <?php if (hasRole(['admin', 'direction'])): ?>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Retour
                    </a>
                <?php endif; ?>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Messages d'erreur -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Cartes de statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_classes']); ?></h3>
                            <p>Classes</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-book"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_cours']); ?></h3>
                            <p>Cours</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_eleves']); ?></h3>
                            <p>Élèves</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-calendar"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['annee_scolaire']; ?></h3>
                            <p>Année Scolaire</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Classes -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-building me-2"></i>
                        Classes (<?php echo $stats['total_classes']; ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($classes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-building display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">
                                <?php if (hasRole(['admin', 'direction'])): ?>
                                    Aucune classe trouvée
                                <?php else: ?>
                                    Aucune classe assignée
                                <?php endif; ?>
                            </h5>
                            <p class="text-muted">
                                <?php if (hasRole(['admin', 'direction'])): ?>
                                    Commencez par créer des classes.
                                <?php else: ?>
                                    Contactez l'administration pour être assigné à des classes.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($classes as $classe): ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card class-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title mb-1">
                                                        <?php echo htmlspecialchars($classe['nom_classe']); ?>
                                                    </h6>
                                                    <div class="mb-2">
                                                        <span class="badge bg-primary niveau-badge me-2">
                                                            <?php echo htmlspecialchars($classe['niveau']); ?>
                                                        </span>
                                                        <span class="badge bg-info cycle-badge">
                                                            <?php echo htmlspecialchars($classe['cycle']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-success">
                                                        <?php echo $classe['nb_eleves']; ?> élève(s)
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="bi bi-book me-1"></i>
                                                    <?php echo $classe['nb_cours']; ?> cours
                                                </small>
                                                <?php if ($classe['salle_classe']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="bi bi-geo-alt me-1"></i>
                                                        Salle <?php echo htmlspecialchars($classe['salle_classe']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view.php?id=<?php echo $classe['id']; ?>" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-eye"></i> Voir
                                                    </a>
                                                    <?php if (hasRole(['admin', 'direction'])): ?>
                                                        <a href="edit.php?id=<?php echo $classe['id']; ?>" 
                                                           class="btn btn-outline-secondary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($classe['annee_scolaire']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Cours -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-book me-2"></i>
                        Cours (<?php echo $stats['total_cours']; ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($cours)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-book display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">
                                <?php if (hasRole(['admin', 'direction'])): ?>
                                    Aucun cours trouvé
                                <?php else: ?>
                                    Aucun cours assigné
                                <?php endif; ?>
                            </h5>
                            <p class="text-muted">
                                <?php if (hasRole(['admin', 'direction'])): ?>
                                    Commencez par créer des cours et les affecter aux classes.
                                <?php else: ?>
                                    Contactez l'administration pour être assigné à des cours.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Classe</th>
                                        <th>Cours</th>
                                        <th>Coefficient</th>
                                        <th>Enseignant</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cours as $c): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($c['nom_classe']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($c['niveau']); ?> - 
                                                        <?php echo htmlspecialchars($c['cycle']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($c['nom_cours']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        Code: <?php echo htmlspecialchars($c['code_cours']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo $c['coefficient_classe'] ?: $c['coefficient']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <i class="bi bi-person me-1"></i>
                                                    <?php echo htmlspecialchars($c['enseignant_prenom'] . ' ' . $c['enseignant_nom']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="../grades/notes_entry.php?classe=<?php echo $c['classe_id']; ?>&cours=<?php echo $c['cours_id']; ?>" 
                                                       class="btn btn-outline-primary" title="Gérer les notes">
                                                        <i class="bi bi-journal-text"></i>
                                                    </a>
                                                    
                                                    <a href="../presence/classe.php?id=<?php echo $c['classe_id']; ?>" 
                                                       class="btn btn-outline-success" title="Gérer la présence">
                                                        <i class="bi bi-clipboard-check"></i>
                                                    </a>
                                                    
                                                    <?php if (hasRole(['admin', 'direction'])): ?>
                                                        <a href="../matieres/edit.php?id=<?php echo $c['cours_id']; ?>" 
                                                           class="btn btn-outline-secondary" title="Modifier le cours">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
