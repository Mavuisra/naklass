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

try {
    // Récupérer les informations de l'utilisateur connecté
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'];
    
    // Statistiques selon le rôle
    $stats = [];
    
    if (hasRole(['admin', 'direction'])) {
        // Admin et direction voient les statistiques générales
        $query = "SELECT COUNT(*) as total FROM enseignants WHERE statut = 'actif' AND ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $stats['total_enseignants'] = $stmt->fetch()['total'];
        
        $query = "SELECT COUNT(*) as total FROM classes WHERE statut = 'actif' AND ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $stats['total_classes'] = $stmt->fetch()['total'];
        
        $query = "SELECT COUNT(*) as total FROM cours WHERE statut = 'actif' AND ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $stats['total_cours'] = $stmt->fetch()['total'];
        
        $query = "SELECT COUNT(*) as total FROM eleves WHERE statut_scolaire = 'inscrit' AND ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $stats['total_eleves'] = $stmt->fetch()['total'];
        
        // Répartition par genre des enseignants
        $query = "SELECT 
                      SUM(CASE WHEN sexe = 'M' THEN 1 ELSE 0 END) as hommes,
                      SUM(CASE WHEN sexe = 'F' THEN 1 ELSE 0 END) as femmes
                   FROM enseignants 
                   WHERE statut = 'actif' AND ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $repartition = $stmt->fetch();
        $stats['hommes'] = $repartition['hommes'];
        $stats['femmes'] = $repartition['femmes'];
        
        // Expérience moyenne des enseignants
        $query = "SELECT AVG(experience_annees) as moyenne FROM enseignants WHERE statut = 'actif' AND ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $stats['experience_moyenne'] = round($stmt->fetch()['moyenne'], 1);
        
        // Enseignants récemment ajoutés (7 derniers jours)
        $query = "SELECT COUNT(*) as total FROM enseignants WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND ecole_id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $stats['nouveaux_enseignants'] = $stmt->fetch()['total'];
        
        // Classes sans enseignant assigné
        $query = "SELECT COUNT(DISTINCT c.id) as total 
                  FROM classes c 
                  LEFT JOIN classe_cours cc ON c.id = cc.classe_id AND cc.statut = 'actif'
                  WHERE c.statut = 'actif' AND c.ecole_id = :ecole_id AND cc.id IS NULL";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $stats['classes_sans_enseignant'] = $stmt->fetch()['total'];
        
    } else {
        // Enseignant connecté - voir ses propres activités
        $enseignant_query = "SELECT e.* FROM enseignants e 
                             JOIN utilisateurs u ON e.utilisateur_id = u.id 
                             WHERE u.id = :user_id AND e.ecole_id = :ecole_id AND e.statut = 'actif'";
        $stmt = $db->prepare($enseignant_query);
        $stmt->execute(['user_id' => $user_id, 'ecole_id' => $_SESSION['ecole_id']]);
        $enseignant = $stmt->fetch();
        
        if ($enseignant) {
            // Nombre de classes assignées à l'enseignant
            $query = "SELECT COUNT(DISTINCT c.id) as total 
                      FROM classes c 
                      JOIN classe_cours cc ON c.id = cc.classe_id 
                      WHERE cc.enseignant_id = :enseignant_id AND cc.statut = 'actif' AND c.statut = 'actif'";
            $stmt = $db->prepare($query);
            $stmt->execute(['enseignant_id' => $enseignant['id']]);
            $stats['mes_classes'] = $stmt->fetch()['total'];
            
            // Nombre de cours assignés à l'enseignant
            $query = "SELECT COUNT(DISTINCT cc.cours_id) as total 
                      FROM classe_cours cc 
                      WHERE cc.enseignant_id = :enseignant_id AND cc.statut = 'actif'";
            $stmt = $db->prepare($query);
            $stmt->execute(['enseignant_id' => $enseignant['id']]);
            $stats['mes_cours'] = $stmt->fetch()['total'];
            
            // Nombre total d'élèves dans les classes de l'enseignant
            $query = "SELECT COUNT(DISTINCT i.eleve_id) as total 
                      FROM inscriptions i 
                      JOIN classe_cours cc ON i.classe_id = cc.classe_id 
                      WHERE cc.enseignant_id = :enseignant_id AND cc.statut = 'actif' 
                      AND i.statut IN ('validée', 'en_cours')";
            $stmt = $db->prepare($query);
            $stmt->execute(['enseignant_id' => $enseignant['id']]);
            $stats['mes_eleves'] = $stmt->fetch()['total'];
            
            // Nombre d'évaluations à corriger
            $query = "SELECT COUNT(DISTINCT n.id) as total 
                      FROM notes n 
                      JOIN evaluations e ON n.evaluation_id = e.id 
                      JOIN classe_cours cc ON e.classe_cours_id = cc.id 
                      WHERE cc.enseignant_id = :enseignant_id AND n.statut = 'actif' AND n.validee = 0";
            $stmt = $db->prepare($query);
            $stmt->execute(['enseignant_id' => $enseignant['id']]);
            $stats['evaluations_a_corriger'] = $stmt->fetch()['total'];
            
            // Nombre de présences à saisir aujourd'hui
            $query = "SELECT COUNT(DISTINCT cc.id) as total 
                      FROM classe_cours cc 
                      WHERE cc.enseignant_id = :enseignant_id AND cc.statut = 'actif'";
            $stmt = $db->prepare($query);
            $stmt->execute(['enseignant_id' => $enseignant['id']]);
            $stats['presences_a_saisir'] = $stmt->fetch()['total'];
            
            // Prochains cours (aujourd'hui et demain)
            $query = "SELECT COUNT(DISTINCT cc.id) as total 
                      FROM classe_cours cc 
                      JOIN classes c ON cc.classe_id = c.id 
                      WHERE cc.enseignant_id = :enseignant_id AND cc.statut = 'actif' 
                      AND c.statut = 'actif'";
            $stmt = $db->prepare($query);
            $stmt->execute(['enseignant_id' => $enseignant['id']]);
            $stats['prochains_cours'] = $stmt->fetch()['total'];
            
            // Expérience de l'enseignant
            $stats['experience_annees'] = $enseignant['experience_annees'] ?? 0;
            
            // Informations personnelles
            $stats['nom_complet'] = $enseignant['prenom'] . ' ' . $enseignant['nom'];
            $stats['specialites'] = $enseignant['specialites'] ?? '[]';
            
        } else {
            // Enseignant non trouvé
            $stats = [
                'mes_classes' => 0,
                'mes_cours' => 0,
                'mes_eleves' => 0,
                'evaluations_a_corriger' => 0,
                'presences_a_saisir' => 0,
                'prochains_cours' => 0,
                'experience_annees' => 0,
                'nom_complet' => 'Non défini',
                'specialites' => '[]'
            ];
        }
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
    // Initialiser avec des valeurs par défaut
    $stats = [
        'total_enseignants' => 0,
        'total_classes' => 0,
        'total_cours' => 0,
        'total_eleves' => 0,
        'hommes' => 0,
        'femmes' => 0,
        'experience_moyenne' => 0,
        'nouveaux_enseignants' => 0,
        'classes_sans_enseignant' => 0
    ];
}

$page_title = hasRole(['admin', 'direction']) ? "Dashboard Enseignants" : "Mon Tableau de Bord";
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
                    <i class="bi bi-person-workspace me-2"></i>
                    <?php echo $page_title; ?>
                </h1>
                <p class="text-muted">
                    <?php if (hasRole(['admin', 'direction'])): ?>
                        Vue d'ensemble du personnel enseignant de votre établissement
                    <?php else: ?>
                        Tableau de bord personnel - <?php echo htmlspecialchars($stats['nom_complet'] ?? 'Enseignant'); ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nouvel Enseignant
                </a>
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
                <?php if (hasRole(['admin', 'direction'])): ?>
                    <!-- Statistiques générales pour admin/direction -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary">
                                <i class="bi bi-person-workspace"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_enseignants']); ?></h3>
                                <p>Enseignants Actifs</p>
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
                                <p>Classes Actives</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-info">
                                <i class="bi bi-book"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_cours']); ?></h3>
                                <p>Cours Actifs</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['total_eleves']); ?></h3>
                                <p>Élèves Inscrits</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Statistiques personnelles pour l'enseignant -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['mes_classes']); ?></h3>
                                <p>Mes Classes</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-success">
                                <i class="bi bi-book"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['mes_cours']); ?></h3>
                                <p>Mes Cours</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-info">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['mes_eleves']); ?></h3>
                                <p>Mes Élèves</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $stats['experience_annees']; ?></h3>
                                <p>Années d'Expérience</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Deuxième ligne de statistiques -->
            <div class="row g-4 mb-4">
                <?php if (hasRole(['admin', 'direction'])): ?>
                    <!-- Statistiques générales pour admin/direction -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-info">
                                <i class="bi bi-gender-male"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['hommes']); ?></h3>
                                <p>Enseignants Hommes</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-gender-female"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['femmes']); ?></h3>
                                <p>Enseignantes Femmes</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-success">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $stats['experience_moyenne']; ?></h3>
                                <p>Années d'Expérience Moy.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary">
                                <i class="bi bi-person-plus"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['nouveaux_enseignants']); ?></h3>
                                <p>Nouveaux (7 jours)</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Statistiques personnelles pour l'enseignant -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-info">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['evaluations_a_corriger']); ?></h3>
                                <p>Évaluations à Corriger</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['presences_a_saisir']); ?></h3>
                                <p>Présences à Saisir</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-success">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['prochains_cours']); ?></h3>
                                <p>Prochains Cours</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo htmlspecialchars($stats['nom_complet']); ?></h3>
                                <p>Votre Nom</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Alertes et notifications -->
            <div class="row g-4 mb-4">
                <?php if (hasRole(['admin', 'direction'])): ?>
                    <!-- Alertes pour admin/direction -->
                    <?php if ($stats['classes_sans_enseignant'] > 0): ?>
                    <div class="col-md-6">
                        <div class="alert-card alert-warning">
                            <div class="alert-icon">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div class="alert-content">
                                <h5>Classes sans enseignant</h5>
                                <p><?php echo $stats['classes_sans_enseignant']; ?> classe(s) n'ont pas d'enseignant assigné</p>
                                <a href="../classes/assign.php" class="btn btn-sm btn-outline-warning">Assigner des enseignants</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($stats['nouveaux_enseignants'] > 0): ?>
                    <div class="col-md-6">
                        <div class="alert-card alert-info">
                            <div class="alert-icon">
                                <i class="bi bi-person-plus"></i>
                            </div>
                            <div class="alert-content">
                                <h5>Nouveaux enseignants</h5>
                                <p><?php echo $stats['nouveaux_enseignants']; ?> nouvel(le)(s) enseignant(e)(s) cette semaine</p>
                                <a href="index.php" class="btn btn-sm btn-outline-info">Voir la liste</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Alertes pour l'enseignant connecté -->
                    <?php if ($stats['evaluations_a_corriger'] > 0): ?>
                    <div class="col-md-6">
                        <div class="alert-card alert-warning">
                            <div class="alert-icon">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div class="alert-content">
                                <h5>Évaluations à corriger</h5>
                                <p><?php echo $stats['evaluations_a_corriger']; ?> évaluation(s) en attente de correction</p>
                                <a href="../grades/notes_entry.php" class="btn btn-sm btn-outline-warning">Corriger les notes</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($stats['presences_a_saisir'] > 0): ?>
                    <div class="col-md-6">
                        <div class="alert-card alert-info">
                            <div class="alert-icon">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <div class="alert-content">
                                <h5>Présences à saisir</h5>
                                <p><?php echo $stats['presences_a_saisir']; ?> cours nécessitent la saisie des présences</p>
                                <a href="../presence/classe.php" class="btn btn-sm btn-outline-info">Saisir les présences</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Actions rapides -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="bi bi-<?php echo hasRole(['admin', 'direction']) ? 'graph-up' : 'person-badge'; ?> me-2"></i>
                                <?php echo hasRole(['admin', 'direction']) ? 'Répartition par genre' : 'Informations personnelles'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (hasRole(['admin', 'direction'])): ?>
                                <!-- Répartition par genre pour admin/direction -->
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="d-flex align-items-center justify-content-center mb-3">
                                            <div class="stat-icon bg-info me-3" style="width: 50px; height: 50px;">
                                                <i class="bi bi-gender-male"></i>
                                            </div>
                                            <div>
                                                <h4 class="mb-0"><?php echo $stats['hommes']; ?></h4>
                                                <small class="text-muted">Hommes</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-center justify-content-center mb-3">
                                            <div class="stat-icon bg-warning me-3" style="width: 50px; height: 50px;">
                                                <i class="bi bi-gender-female"></i>
                                            </div>
                                            <div>
                                                <h4 class="mb-0"><?php echo $stats['femmes']; ?></h4>
                                                <small class="text-muted">Femmes</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Informations personnelles pour l'enseignant -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="stat-icon bg-primary me-3" style="width: 50px; height: 50px;">
                                                <i class="bi bi-person-badge"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Nom complet</h6>
                                                <p class="mb-0"><?php echo htmlspecialchars($stats['nom_complet']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="stat-icon bg-success me-3" style="width: 50px; height: 50px;">
                                                <i class="bi bi-clock"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Expérience</h6>
                                                <p class="mb-0"><?php echo $stats['experience_annees']; ?> année(s)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                $specialites = json_decode($stats['specialites'], true);
                                if (is_array($specialites) && !empty($specialites)): 
                                ?>
                                <div class="mt-3">
                                    <h6 class="mb-2">Spécialités :</h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($specialites as $specialite): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($specialite); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-list-check me-2"></i>Actions rapides</h5>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <?php if (hasRole(['admin', 'direction'])): ?>
                                    <a href="create.php" class="quick-action-btn">
                                        <i class="bi bi-person-plus"></i>
                                        <span>Nouvel enseignant</span>
                                    </a>
                                    
                                    <a href="../classes/assign.php" class="quick-action-btn">
                                        <i class="bi bi-link"></i>
                                        <span>Assigner des cours</span>
                                    </a>
                                    
                                    <a href="../matieres/" class="quick-action-btn">
                                        <i class="bi bi-book"></i>
                                        <span>Gérer les matières</span>
                                    </a>
                                    
                                    <a href="index.php" class="quick-action-btn">
                                        <i class="bi bi-list-ul"></i>
                                        <span>Liste complète</span>
                                    </a>
                                <?php else: ?>
                                    <a href="../grades/notes_entry.php" class="quick-action-btn">
                                        <i class="bi bi-journal-text"></i>
                                        <span>Saisir les notes</span>
                                    </a>
                                    
                                    <a href="../presence/classe.php" class="quick-action-btn">
                                        <i class="bi bi-clipboard-check"></i>
                                        <span>Saisir les présences</span>
                                    </a>
                                    
                                    <a href="../classes/my_classes.php" class="quick-action-btn">
                                        <i class="bi bi-building"></i>
                                        <span>Mes classes</span>
                                    </a>
                                    
                                    <a href="../profile/edit.php" class="quick-action-btn">
                                        <i class="bi bi-person-gear"></i>
                                        <span>Mon profil</span>
                                    </a>
                                <?php endif; ?>
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
