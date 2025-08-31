<?php
/**
 * Page des notes des élèves
 * Style identique à la page d'accueil avec vraies données de la base
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Démarrer la session si pas encore démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier l'authentification
requireAuth();

// Vérifier que l'école est configurée
requireSchoolSetup();

// Récupérer les paramètres de l'URL
$annee_scolaire_id = intval($_GET['annee_scolaire_id'] ?? 0);
$classe_id = intval($_GET['classe_id'] ?? 0);
$cours_id = intval($_GET['cours_id'] ?? 0);
$periode_id = intval($_GET['periode_id'] ?? 0);

$page_title = "Notes des Élèves";

try {
$database = new Database();
$db = $database->getConnection();

    // Récupérer les années scolaires
    $annees_query = "SELECT id, libelle, date_debut, date_fin, active, statut FROM annees_scolaires WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY active DESC, date_debut DESC";
    $annees_stmt = $db->prepare($annees_query);
    $annees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annees_scolaires = $annees_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si aucune année n'est sélectionnée, prendre l'année active ou la plus récente
    if (!$annee_scolaire_id && !empty($annees_scolaires)) {
        foreach ($annees_scolaires as $annee) {
            if ($annee['active']) {
                $annee_scolaire_id = $annee['id'];
                break;
            }
        }
        if (!$annee_scolaire_id) {
            $annee_scolaire_id = $annees_scolaires[0]['id'];
        }
    }
    
    // Récupérer les classes selon le rôle de l'utilisateur
    if (hasRole(['admin', 'direction'])) {
        // Admin et direction voient toutes les classes
        $classes_query = "SELECT c.*, COUNT(i.id) as nb_eleves
                          FROM classes c 
                          LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut IN ('validée', 'en_cours')
                          WHERE c.ecole_id = :ecole_id AND c.statut = 'actif'
                          GROUP BY c.id
                          ORDER BY c.niveau, c.nom_classe";
        $classes_params = ['ecole_id' => $_SESSION['ecole_id']];
    } else {
        // Enseignants voient seulement leurs classes assignées
        $enseignant_query = "SELECT e.id as enseignant_id, e.ecole_id 
                             FROM enseignants e 
                             WHERE e.utilisateur_id = :user_id";
        $stmt = $db->prepare($enseignant_query);
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        $enseignant_info = $stmt->fetch();
        
        if ($enseignant_info) {
            $ecole_enseignant = $enseignant_info['ecole_id'];
            $enseignant_id = $enseignant_info['enseignant_id'];
            
            $classes_query = "SELECT DISTINCT c.*, COUNT(i.id) as nb_eleves
                              FROM classes c 
                              JOIN classe_cours cc ON c.id = cc.classe_id 
                              LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut IN ('validée', 'en_cours')
                              WHERE c.ecole_id = :ecole_id 
                                AND c.statut = 'actif' 
                                AND cc.statut = 'actif'
                                AND cc.enseignant_id = :enseignant_id
                              GROUP BY c.id
                              ORDER BY c.niveau, c.nom_classe";
            $classes_params = ['ecole_id' => $ecole_enseignant, 'enseignant_id' => $enseignant_id];
        } else {
            $classes_query = "SELECT 1 WHERE 1=0";
            $classes_params = [];
        }
    }
    
    $classes_stmt = $db->prepare($classes_query);
    $classes_stmt->execute($classes_params);
    $classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les cours pour la classe sélectionnée
    $cours = [];
    if ($classe_id) {
        if (hasRole(['admin', 'direction'])) {
            $cours_query = "SELECT DISTINCT cc.id as classe_cours_id, cc.classe_id, cc.cours_id, cc.coefficient_classe,
                                   c.nom_classe, c.niveau, c.cycle, cr.nom_cours, cr.code_cours, cr.coefficient,
                                   cr.id, cr.nom_cours, cr.coefficient as coefficient_cours,
                                   e.nom as enseignant_nom, e.prenom as enseignant_prenom,
                                   CONCAT(e.nom, ' ', e.prenom) as enseignant_nom_complet
                            FROM classe_cours cc
                            JOIN classes c ON cc.classe_id = c.id
                            JOIN cours cr ON cc.cours_id = cr.id
                            JOIN enseignants e ON cc.enseignant_id = e.id
                            WHERE c.ecole_id = :ecole_id 
                              AND cc.statut = 'actif' 
                              AND c.statut = 'actif' 
                              AND cr.statut = 'actif'
                              AND cc.classe_id = :classe_id
                            ORDER BY c.niveau, c.nom_classe, cr.nom_cours";
            $cours_params = ['ecole_id' => $_SESSION['ecole_id'], 'classe_id' => $classe_id];
        } else {
            if (isset($enseignant_info)) {
                $cours_query = "SELECT DISTINCT cc.id as classe_cours_id, cc.classe_id, cc.cours_id, cc.coefficient_classe,
                                       c.nom_classe, c.niveau, c.cycle, cr.nom_cours, cr.code_cours, cr.coefficient,
                                       cr.id, cr.nom_cours, cr.coefficient as coefficient_cours,
                                       e.nom as enseignant_nom, e.prenom as enseignant_prenom,
                                       CONCAT(e.nom, ' ', e.prenom) as enseignant_nom_complet
                                FROM classe_cours cc
                                JOIN classes c ON cc.classe_id = c.id
                                JOIN cours cr ON cc.cours_id = cr.id
                                JOIN enseignants e ON cc.enseignant_id = e.id
                                WHERE c.ecole_id = :ecole_id 
                                  AND cc.statut = 'actif' 
                                  AND c.statut = 'actif' 
                                  AND cr.statut = 'actif'
                                  AND cc.classe_id = :classe_id 
                                  AND cc.enseignant_id = :enseignant_id
                                ORDER BY c.niveau, c.nom_classe, cr.nom_cours";
                $cours_params = ['ecole_id' => $ecole_enseignant, 'classe_id' => $classe_id, 'enseignant_id' => $enseignant_id];
            } else {
                $cours_query = "SELECT 1 WHERE 1=0";
                $cours_params = [];
            }
        }
        
    $cours_stmt = $db->prepare($cours_query);
        $cours_stmt->execute($cours_params);
        $cours = $cours_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupérer les périodes pour l'année scolaire sélectionnée
    $periodes = [];
    if ($annee_scolaire_id) {
        $periodes_query = "SELECT p.*, 
                                   CASE 
                                       WHEN p.type_periode = 'trimestre' THEN p.nom
                                       ELSE CONCAT(pt.nom, ' - ', p.nom)
                                   END as nom_complet
                            FROM periodes_scolaires p
                            LEFT JOIN periodes_scolaires pt ON p.periode_parent_id = pt.id
                            WHERE p.annee_scolaire_id = :annee_id AND p.statut = 'actif'
                            ORDER BY p.type_periode DESC, p.ordre_periode";
        $periodes_stmt = $db->prepare($periodes_query);
        $periodes_stmt->execute(['annee_id' => $annee_scolaire_id]);
        $periodes = $periodes_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Si aucune période n'est trouvée, récupérer toutes les périodes actives
    if (empty($periodes)) {
        $periodes_query = "SELECT p.*, 
                                   CASE 
                                       WHEN p.type_periode = 'trimestre' THEN p.nom
                                       ELSE CONCAT(pt.nom, ' - ', p.nom)
                                   END as nom_complet
                            FROM periodes_scolaires p
                            LEFT JOIN periodes_scolaires pt ON p.periode_parent_id = pt.id
                            WHERE p.statut = 'actif'
                            ORDER BY p.type_periode DESC, p.ordre_periode";
        $periodes_stmt = $db->prepare($periodes_query);
        $periodes_stmt->execute();
        $periodes = $periodes_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupérer les notes des élèves
    $notes_eleves = [];
    if ($classe_id && $cours_id && $periode_id) {
        
        // D'abord, récupérer tous les élèves de la classe
        $eleves_query = "SELECT DISTINCT
                            el.id as eleve_id,
                            el.nom as eleve_nom,
                            el.prenom as eleve_prenom,
                            el.matricule,
                            c.nom_classe,
                            cr.nom_cours
                        FROM eleves el
                        JOIN inscriptions i ON el.id = i.eleve_id
                        JOIN classes c ON i.classe_id = c.id
                        JOIN classe_cours cc ON c.id = cc.classe_id
                        JOIN cours cr ON cc.cours_id = cr.id
                        WHERE i.classe_id = :classe_id 
                          AND cc.cours_id = :cours_id
                          AND i.statut IN ('validée', 'en_cours', 'actif')
                          AND el.statut = 'actif'
                          AND cc.statut = 'actif'";
        
        // Ajouter le filtre par enseignant si nécessaire
        if (!hasRole(['admin', 'direction']) && isset($enseignant_info)) {
            $eleves_query .= " AND cc.enseignant_id = :enseignant_id";
            $eleves_params = [
                'classe_id' => $classe_id, 
                'cours_id' => $cours_id, 
                'enseignant_id' => $enseignant_info['enseignant_id']
            ];
        } else {
            $eleves_params = [
                'classe_id' => $classe_id, 
                'cours_id' => $cours_id
            ];
        }
        
        $eleves_query .= " ORDER BY el.nom, el.prenom";
        
        $eleves_stmt = $db->prepare($eleves_query);
        $eleves_stmt->execute($eleves_params);
        $eleves = $eleves_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensuite, pour chaque élève, récupérer ses évaluations et notes
        foreach ($eleves as $eleve) {
            $evaluations_query = "SELECT 
                                    e.id as evaluation_id,
                                    e.nom_evaluation,
                                    e.type_evaluation,
                                    e.date_evaluation,
                                    e.bareme as note_maximale,
                                    e.ponderation,
                                    p.nom as periode_nom,
                                    n.valeur as points_obtenus,
                                    COALESCE(n.absent, 0) as absent,
                                    COALESCE(n.excuse, 0) as excuse,
                                    COALESCE(n.rattrapage, 0) as rattrapage,
                                    n.commentaire,
                                    COALESCE(n.validee, 0) as validee,
                                    n.created_at as date_creation
                                FROM evaluations e
                                JOIN classe_cours cc ON e.classe_cours_id = cc.id
                                JOIN periodes_scolaires p ON e.periode_scolaire_id = p.id
                                LEFT JOIN notes n ON e.id = n.evaluation_id AND n.eleve_id = :eleve_id
                                WHERE cc.classe_id = :classe_id 
                                  AND cc.cours_id = :cours_id
                                  AND e.periode_scolaire_id = :periode_id
                                  AND e.statut = 'actif'
                                  AND cc.statut = 'actif'";
            
            if (!hasRole(['admin', 'direction']) && isset($enseignant_info)) {
                $evaluations_query .= " AND cc.enseignant_id = :enseignant_id";
                $eval_params = [
                    'eleve_id' => $eleve['eleve_id'],
                    'classe_id' => $classe_id, 
                    'cours_id' => $cours_id, 
                    'periode_id' => $periode_id,
                    'enseignant_id' => $enseignant_info['enseignant_id']
                ];
            } else {
                $eval_params = [
                    'eleve_id' => $eleve['eleve_id'],
                    'classe_id' => $classe_id, 
                    'cours_id' => $cours_id, 
                    'periode_id' => $periode_id
                ];
            }
            
            $evaluations_query .= " ORDER BY e.date_evaluation DESC";
            
            $eval_stmt = $db->prepare($evaluations_query);
            $eval_stmt->execute($eval_params);
            $evaluations = $eval_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ajouter l'élève avec ses évaluations
            $eleve['evaluations'] = $evaluations;
            $notes_eleves[] = $eleve;
        }
    }
    
} catch (Exception $e) {
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Naklass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <style>
        .grades-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            position: relative;
            overflow: hidden;
        }
        
        .grades-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .grade-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .grade-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
        }
        
        .grade-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .grade-table {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .grade-table .table {
            margin-bottom: 0;
        }
        
        .grade-table .table th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
        }
        
        .grade-table .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--border-color);
        }
        
        .grade-table .table tbody tr:hover {
            background-color: var(--light-color);
        }
        
        .grade-badge {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .grade-excellent { background-color: #d4edda; color: #155724; }
        .grade-good { background-color: #d1ecf1; color: #0c5460; }
        .grade-average { background-color: #fff3cd; color: #856404; }
        .grade-poor { background-color: #f8d7da; color: #721c24; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-item {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .breadcrumb-custom {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }
        
        .breadcrumb-custom .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .breadcrumb-custom .breadcrumb-item.active {
            color: #6c757d;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .btn-modern {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .filter-group select,
        .filter-group input {
            border-radius: 8px;
            border: 2px solid var(--border-color);
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 182, 0.25);
        }
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
                <div class="user-name"><?php echo $_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']; ?></div>
                <div class="user-role"><?php echo ROLES[$_SESSION['user_role']] ?? $_SESSION['user_role']; ?></div>
                <div class="user-school"><?php echo $_SESSION['ecole_nom']; ?></div>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="../auth/dashboard.php" class="menu-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            
            <?php if (hasRole(['admin', 'direction', 'secretaire'])): ?>
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
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
            <li class="menu-item">
                <a href="../teachers/" class="menu-link">
                    <i class="bi bi-person-badge"></i>
                    <span>Enseignants</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction', 'caissier'])): ?>
            <li class="menu-item">
                <a href="../finance/" class="menu-link">
                    <i class="bi bi-cash-coin"></i>
                    <span>Finances</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
            <li class="menu-item active">
                <a href="../grades/" class="menu-link">
                    <i class="bi bi-journal-text"></i>
                    <span>Notes & Bulletins</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction'])): ?>
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
            <?php endif; ?>
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
                <h1><i class="bi bi-journal-text me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Gestion des notes et évaluations des élèves</p>
            </div>
            
            <div class="topbar-actions">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                    <i class="bi bi-bell"></i>
                    <span class="badge bg-danger">0</span>
                </button>
                
                <div class="dropdown">
                    <button class="btn btn-link p-0" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-4"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile/"><i class="bi bi-person me-2"></i>Profil</a></li>
                        <li><a class="dropdown-item" href="../settings/"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- En-tête avec informations -->
            <div class="grades-header">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-3">Notes des Élèves</h2>
                            <p class="mb-0 opacity-75">
                                <i class="bi bi-calendar3 me-2"></i>
                                <?php if ($annee_scolaire_id): ?>
                                    <?php 
                                    $annee_selectionnee = array_filter($annees_scolaires, function($a) use ($annee_scolaire_id) {
                                        return $a['id'] == $annee_scolaire_id;
                                    });
                                    $annee_selectionnee = reset($annee_selectionnee);
                                    ?>
                                    Année scolaire: <strong><?php echo htmlspecialchars($annee_selectionnee['libelle'] ?? ''); ?></strong>
                                <?php endif; ?>
                                
                                <?php if ($classe_id): ?>
                                    <?php 
                                    $classe_selectionnee = array_filter($classes, function($c) use ($classe_id) {
                                        return $c['id'] == $classe_id;
                                    });
                                    $classe_selectionnee = reset($classe_selectionnee);
                                    ?>
                                    | Classe: <strong><?php echo htmlspecialchars($classe_selectionnee['nom_classe'] ?? ''); ?></strong>
                                <?php endif; ?>
                                
                                <?php if ($cours_id): ?>
                                    <?php 
                                    $cours_selectionne = array_filter($cours, function($c) use ($cours_id) {
                                        return $c['cours_id'] == $cours_id;
                                    });
                                    $cours_selectionne = reset($cours_selectionne);
                                    ?>
                                    | Cours: <strong><?php echo htmlspecialchars($cours_selectionne['nom_cours'] ?? ''); ?></strong>
                                <?php endif; ?>
                                
                                <?php if ($periode_id): ?>
                                    <?php 
                                    $periode_selectionnee = array_filter($periodes, function($p) use ($periode_id) {
                                        return $p['id'] == $periode_id;
                                    });
                                    $periode_selectionnee = reset($periode_selectionnee);
                                    ?>
                                    | Période: <strong><?php echo htmlspecialchars($periode_selectionnee['nom_complet'] ?? ''); ?></strong>
                                <?php endif; ?>
                            </p>
                                </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex flex-column align-items-end">
                                <span class="h4 mb-0"><?php echo date('d/m/Y'); ?></span>
                                <small class="opacity-75"><?php echo date('H:i'); ?></small>
                                </div>
                                </div>
                                </div>
                            </div>
                        </div>

            <!-- Fil d'Ariane -->
            <div class="container">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-custom">
                        <li class="breadcrumb-item"><a href="../index.php">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="../grades/">Notes</a></li>
                        <li class="breadcrumb-item active">Notes des Élèves</li>
                    </ol>
                </nav>

                <!-- Boutons d'action -->
                            <div class="action-buttons">
                    <a href="add_grade.php" class="btn btn-primary btn-modern">
                        <i class="bi bi-plus-circle"></i>
                        Nouvelle Note
                    </a>
                    <a href="grade_import.php" class="btn btn-outline-primary btn-modern">
                        <i class="bi bi-upload"></i>
                        Importer Notes
                    </a>
                    <a href="grade_export.php" class="btn btn-outline-success btn-modern">
                        <i class="bi bi-download"></i>
                        Exporter Notes
                    </a>
                    <a href="grade_reports.php" class="btn btn-outline-info btn-modern">
                                    <i class="bi bi-graph-up"></i>
                        Rapports
                    </a>
            </div>
            
                <!-- Filtres -->
                <div class="filters-section">
                    <h5 class="mb-3"><i class="bi bi-funnel me-2"></i>Filtres</h5>
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="annee_scolaire_id">Année Scolaire</label>
                                <select name="annee_scolaire_id" id="annee_scolaire_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Sélectionner une année</option>
                                    <?php foreach ($annees_scolaires as $annee): ?>
                                        <option value="<?php echo $annee['id']; ?>" <?php echo $annee_scolaire_id == $annee['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($annee['libelle']); ?>
                                            <?php if ($annee['active']): ?>
                                                <span class="badge bg-success ms-2">Active</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                    </div>
                            <div class="filter-group">
                                <label for="classe_id">Classe</label>
                                <select name="classe_id" id="classe_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Sélectionner une classe</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>" <?php echo $classe_id == $classe['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($classe['nom_classe']); ?>
                                            <span class="badge bg-info ms-2"><?php echo $classe['nb_eleves']; ?> élèves</span>
                                        </option>
                <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="cours_id">Cours</label>
                                <select name="cours_id" id="cours_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Sélectionner un cours</option>
                                    <?php foreach ($cours as $c): ?>
                                        <option value="<?php echo $c['cours_id']; ?>" <?php echo $cours_id == $c['cours_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['nom_cours']); ?>
                                            <span class="badge bg-warning ms-2"><?php echo htmlspecialchars($c['enseignant_nom_complet']); ?></span>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="periode_id">Période</label>
                                <select name="periode_id" id="periode_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Sélectionner une période</option>
                                    <?php foreach ($periodes as $periode): ?>
                                        <option value="<?php echo $periode['id']; ?>" <?php echo $periode_id == $periode['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($periode['nom_complet']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                        </div>
                            <div class="filter-group">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-2"></i>Filtrer
                                </button>
                    </div>
                        </div>
                    </form>
                </div>
                
                <!-- Statistiques -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="bi bi-people-fill"></i>
                            </div>
                        <div class="stat-value"><?php echo count($notes_eleves); ?></div>
                        <div class="stat-label">Élèves</div>
                            </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="bi bi-award-fill"></i>
                        </div>
                        <div class="stat-value">
                            <?php 
                            $total_notes = 0;
                            foreach ($notes_eleves as $eleve) {
                                foreach ($eleve['evaluations'] as $eval) {
                                    if ($eval['points_obtenus'] !== null) {
                                        $total_notes++;
                                    }
                                }
                            }
                            echo $total_notes;
                            ?>
                    </div>
                        <div class="stat-label">Notes Saisies</div>
                </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        <div class="stat-value">
                            <?php 
                            $notes_valides = [];
                            foreach ($notes_eleves as $eleve) {
                                foreach ($eleve['evaluations'] as $eval) {
                                    if ($eval['points_obtenus'] !== null && $eval['absent'] != 1) {
                                        $notes_valides[] = $eval['points_obtenus'];
                                    }
                                }
                            }
                            if (!empty($notes_valides)) {
                                $moyenne = array_sum($notes_valides) / count($notes_valides);
                                echo number_format($moyenne, 1);
                            } else {
                                echo '0.0';
                            }
                            ?>
                            </div>
                        <div class="stat-label">Moyenne Générale</div>
                        </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="bi bi-trophy-fill"></i>
                    </div>
                        <div class="stat-value">
                            <?php 
                            $mentions = 0;
                            foreach ($notes_eleves as $eleve) {
                                foreach ($eleve['evaluations'] as $eval) {
                                    if ($eval['points_obtenus'] !== null && $eval['absent'] != 1) {
                                        if ($eval['points_obtenus'] >= 16) {
                                            $mentions++;
                                        }
                                    }
                                }
                            }
                            echo $mentions;
                            ?>
                </div>
                        <div class="stat-label">Mentions</div>
                            </div>
                            </div>

                <!-- Tableau des notes -->
                <div class="grade-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-table me-2"></i>Notes des Élèves</h5>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>Voir tout
                            </button>
                            <button class="btn btn-sm btn-outline-success">
                                <i class="bi bi-pencil me-1"></i>Modifier
                            </button>
                </div>
            </div>
            
                    <div class="grade-table">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                    <th>Rang</th>
                                    <th>Élève</th>
                                    <th>Évaluation</th>
                                    <th>Date</th>
                                    <th>Note Max</th>
                                    <th>Note Obtenue</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php if (!empty($notes_eleves)): ?>
                                    <?php 
                                    $rang = 1;
                                    foreach ($notes_eleves as $eleve): 
                                        foreach ($eleve['evaluations'] as $index => $eval): 
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if ($index === 0): ?>
                                                    <span class="badge bg-primary"><?php echo $rang; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                </td>
                                            <td>
                                                <?php if ($index === 0): ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm me-3">
                                                            <i class="bi bi-person-circle text-primary"></i>
                </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($eleve['eleve_nom'] . ' ' . $eleve['eleve_prenom']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($eleve['matricule']); ?></small>
            </div>
                </div>
                                                <?php else: ?>
                                                    <div class="text-muted text-center">-</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                            <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($eval['nom_evaluation']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($eval['type_evaluation']); ?></small>
                        </div>
                    </td>
                    <td>
                                                <span class="badge bg-info">
                                                    <?php echo date('d/m/Y', strtotime($eval['date_evaluation'])); ?>
                                                </span>
                    </td>
                    <td>
                                                <span class="badge bg-warning">
                                                    <?php echo htmlspecialchars($eval['note_maximale']); ?> pts
                                                </span>
                    </td>
                    <td>
                                                <?php if ($eval['absent'] == 1): ?>
                                                    <span class="badge bg-danger">Absent</span>
                                                <?php elseif ($eval['excuse'] == 1): ?>
                                                    <span class="badge bg-warning">Excusé</span>
                                                <?php elseif ($eval['points_obtenus'] !== null): ?>
                                                    <span class="grade-badge <?php 
                                                        if ($eval['points_obtenus'] >= 16) echo 'grade-excellent';
                                                        elseif ($eval['points_obtenus'] >= 14) echo 'grade-good';
                                                        elseif ($eval['points_obtenus'] >= 12) echo 'grade-average';
                                                        else echo 'grade-poor';
                                                    ?>">
                                                        <?php echo htmlspecialchars($eval['points_obtenus']); ?>/<?php echo htmlspecialchars($eval['note_maximale']); ?>
                        </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non noté</span>
                                                <?php endif; ?>
                    </td>
                    <td>
                                                <?php if ($eval['validee'] == 1): ?>
                                                    <span class="badge bg-success">Validée</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">En attente</span>
                                                <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" title="Voir détails">
                                <i class="bi bi-eye"></i>
                            </button>
                                                    <button class="btn btn-outline-warning" title="Modifier">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" title="Supprimer">
                                                        <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                                    <?php 
                                        endforeach;
                                        $rang++;
                                    endforeach; 
                                    ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3 text-muted">Aucune donnée trouvée</h5>
                                            <p class="text-muted">Aucun élève ou évaluation trouvé pour les critères sélectionnés.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($classe_id && $cours_id && $periode_id && empty($notes_eleves)): ?>
                    <!-- Aucune note trouvée -->
                    <div class="grade-card">
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3 text-muted">Aucune note trouvée</h4>
                            <p class="text-muted">Aucune évaluation ou note n'a été trouvée pour les critères sélectionnés.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal des notifications -->
    <div class="modal fade" id="notificationsModal" tabindex="-1" aria-labelledby="notificationsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notificationsModalLabel">
                        <i class="bi bi-bell me-2"></i>Notifications
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-4">
                        <i class="bi bi-bell-slash text-muted" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-muted">Aucune notification</h6>
                        <p class="text-muted">Vous n'avez pas de nouvelles notifications pour le moment.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar sur mobile
        document.querySelectorAll('.sidebar-toggle').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelector('.sidebar').classList.toggle('collapsed');
            });
        });

        // Animation des cartes au survol
        document.querySelectorAll('.stat-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>
