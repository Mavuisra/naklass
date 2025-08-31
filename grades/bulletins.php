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

// Récupérer l'année scolaire depuis l'URL
$annee_id = isset($_GET['annee_id']) ? (int)$_GET['annee_id'] : null;

if (!$annee_id) {
    // Rediriger vers la sélection d'année scolaire
    redirect('index.php');
}

// Récupérer les informations de l'année scolaire
try {
    $annee_query = "SELECT * FROM annees_scolaires WHERE id = :annee_id AND ecole_id = :ecole_id AND statut = 'actif'";
    $annee_stmt = $db->prepare($annee_query);
    $annee_stmt->execute(['annee_id' => $annee_id, 'ecole_id' => $_SESSION['ecole_id']]);
    $annee_scolaire = $annee_stmt->fetch();
    
    if (!$annee_scolaire) {
        throw new Exception("Année scolaire non trouvée ou non autorisée.");
    }
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// Récupérer les périodes scolaires de cette année
$periodes = [];
if (!$errors) {
    try {
        $periodes_query = "SELECT * FROM periodes_scolaires 
                          WHERE annee_scolaire_id = :annee_id 
                          AND statut = 'actif' 
                          ORDER BY ordre_periode";
        $periodes_stmt = $db->prepare($periodes_query);
        $periodes_stmt->execute(['annee_id' => $annee_id]);
        $periodes = $periodes_stmt->fetchAll();
    } catch (Exception $e) {
        $errors[] = "Erreur lors de la récupération des périodes : " . $e->getMessage();
    }
}

// Récupérer les classes de cette année
$classes = [];
if (!$errors) {
    try {
        $classes_query = "SELECT c.*, COUNT(i.eleve_id) as effectif 
                         FROM classes c 
                         LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.annee_scolaire = c.annee_scolaire
                         WHERE c.ecole_id = :ecole_id 
                         AND c.annee_scolaire = :annee_scolaire 
                         AND c.statut = 'actif'
                         GROUP BY c.id
                         ORDER BY c.niveau, c.nom_classe";
        $classes_stmt = $db->prepare($classes_query);
        $classes_stmt->execute([
            'ecole_id' => $_SESSION['ecole_id'],
            'annee_scolaire' => $annee_scolaire['libelle']
        ]);
        $classes = $classes_stmt->fetchAll();
    } catch (Exception $e) {
        $errors[] = "Erreur lors de la récupération des classes : " . $e->getMessage();
    }
}

// Récupérer les bulletins existants
$bulletins = [];
if (!$errors) {
    try {
        $bulletins_query = "SELECT b.*, e.nom, e.prenom, e.matricule, c.nom_classe, c.niveau,
                                  u.nom as generateur_nom, u.prenom as generateur_prenom
                           FROM bulletins b
                           JOIN eleves e ON b.eleve_id = e.id
                           JOIN classes c ON b.classe_id = c.id
                           LEFT JOIN utilisateurs u ON b.genere_par = u.id
                           WHERE c.ecole_id = :ecole_id 
                           AND b.annee_scolaire = :annee_scolaire
                           AND b.statut = 'actif'
                           ORDER BY c.niveau, c.nom_classe, e.nom, e.prenom";
        $bulletins_stmt = $db->prepare($bulletins_query);
        $bulletins_stmt->execute([
            'ecole_id' => $_SESSION['ecole_id'],
            'annee_scolaire' => $annee_scolaire['libelle']
        ]);
        $bulletins = $bulletins_stmt->fetchAll();
    } catch (Exception $e) {
        $errors[] = "Erreur lors de la récupération des bulletins : " . $e->getMessage();
    }
}

// Traitement de la génération de bulletins
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_bulletins') {
        try {
            $classe_id = (int)$_POST['classe_id'];
            $periode = sanitize($_POST['periode']);
            
            // Vérifier que la classe existe et appartient à l'école
            $classe_check = "SELECT * FROM classes WHERE id = :classe_id AND ecole_id = :ecole_id AND statut = 'actif'";
            $classe_stmt = $db->prepare($classe_check);
            $classe_stmt->execute(['classe_id' => $classe_id, 'ecole_id' => $_SESSION['ecole_id']]);
            $classe = $classe_stmt->fetch();
            
            if (!$classe) {
                throw new Exception("Classe non trouvée ou non autorisée.");
            }
            
                         // Récupérer les élèves de cette classe (requête plus souple)
             $eleves_query = "SELECT e.* FROM eleves e 
                             JOIN inscriptions i ON e.id = i.eleve_id 
                             WHERE i.classe_id = :classe_id 
                             AND i.annee_scolaire = :annee_scolaire 
                             ORDER BY e.nom, e.prenom";
            $eleves_stmt = $db->prepare($eleves_query);
            $eleves_stmt->execute([
                'classe_id' => $classe_id,
                'annee_scolaire' => $annee_scolaire['libelle']
            ]);
            $eleves = $eleves_stmt->fetchAll();
            
            if (empty($eleves)) {
                throw new Exception("Aucun élève trouvé dans cette classe pour cette année scolaire.");
            }
            
            $db->beginTransaction();
            
            foreach ($eleves as $eleve) {
                // Vérifier si le bulletin existe déjà
                $existing_query = "SELECT id FROM bulletins 
                                 WHERE eleve_id = :eleve_id 
                                 AND classe_id = :classe_id 
                                 AND periode = :periode 
                                 AND annee_scolaire = :annee_scolaire";
                $existing_stmt = $db->prepare($existing_query);
                $existing_stmt->execute([
                    'eleve_id' => $eleve['id'],
                    'classe_id' => $classe_id,
                    'periode' => $periode,
                    'annee_scolaire' => $annee_scolaire['libelle']
                ]);
                
                if (!$existing_stmt->fetch()) {
                                         // Calculer la moyenne générale de l'élève pour cette période
                     $moyenne_query = "SELECT AVG(n.valeur) as moyenne, COUNT(n.id) as nb_evaluations
                                      FROM notes n
                                      JOIN evaluations ev ON n.evaluation_id = ev.id
                                      JOIN classe_cours cc ON ev.classe_cours_id = cc.id
                                      WHERE cc.classe_id = :classe_id 
                                      AND ev.periode = :periode
                                      AND n.eleve_id = :eleve_id
                                      AND n.absent = 0
                                      AND n.validee = 1";
                    $moyenne_stmt = $db->prepare($moyenne_query);
                    $moyenne_stmt->execute([
                        'classe_id' => $classe_id,
                        'periode' => $periode,
                        'eleve_id' => $eleve['id']
                    ]);
                    $moyenne_data = $moyenne_stmt->fetch();
                    
                    $moyenne_generale = $moyenne_data['moyenne'] ? round($moyenne_data['moyenne'], 2) : null;
                    
                                         // Insérer le bulletin
                     $insert_query = "INSERT INTO bulletins (eleve_id, classe_id, periode, annee_scolaire, 
                                                           moyenne_generale, effectif_classe, genere_le, genere_par, 
                                                           statut, created_by) 
                                    VALUES (:eleve_id, :classe_id, :periode, :annee_scolaire, 
                                            :moyenne_generale, :effectif_classe, NOW(), :genere_par, 
                                            'actif', :created_by)";
                     $insert_stmt = $db->prepare($insert_query);
                     $insert_stmt->execute([
                         'eleve_id' => $eleve['id'],
                         'classe_id' => $classe_id,
                         'periode' => $periode,
                         'annee_scolaire' => $annee_scolaire['libelle'],
                         'moyenne_generale' => $moyenne_generale,
                         'effectif_classe' => count($eleves),
                         'genere_par' => $_SESSION['user_id'],
                         'created_by' => $_SESSION['user_id']
                     ]);
                     
                     $bulletin_id = $db->lastInsertId();
                     
                     // Récupérer les matières de la classe
                     $matieres_query = "SELECT DISTINCT c.id as cours_id, c.code_cours, c.nom_cours, c.coefficient
                                       FROM cours c
                                       JOIN classe_cours cc ON c.id = cc.cours_id
                                       WHERE cc.classe_id = :classe_id 
                                       AND c.statut = 'actif'
                                       ORDER BY c.coefficient DESC, c.nom_cours";
                     $matieres_stmt = $db->prepare($matieres_query);
                     $matieres_stmt->execute(['classe_id' => $classe_id]);
                     $matieres = $matieres_stmt->fetchAll();
                     
                     // Créer les lignes du bulletin pour chaque matière
                     foreach ($matieres as $matiere) {
                         // Calculer la moyenne de l'élève pour cette matière et cette période
                         $moyenne_matiere_query = "SELECT AVG(n.valeur) as moyenne, COUNT(n.id) as nb_evaluations
                                                  FROM notes n
                                                  JOIN evaluations ev ON n.evaluation_id = ev.id
                                                  JOIN classe_cours cc ON ev.classe_cours_id = cc.id
                                                  WHERE cc.classe_id = :classe_id 
                                                  AND cc.cours_id = :cours_id
                                                  AND ev.periode = :periode
                                                  AND n.eleve_id = :eleve_id
                                                  AND n.absent = 0
                                                  AND n.validee = 1";
                         $moyenne_matiere_stmt = $db->prepare($moyenne_matiere_query);
                         $moyenne_matiere_stmt->execute([
                             'classe_id' => $classe_id,
                             'cours_id' => $matiere['cours_id'],
                             'periode' => $periode,
                             'eleve_id' => $eleve['id']
                         ]);
                         $moyenne_matiere_data = $moyenne_matiere_stmt->fetch();
                         
                         $moyenne_matiere = $moyenne_matiere_data['moyenne'] ? round($moyenne_matiere_data['moyenne'], 2) : null;
                         
                         // Calculer le rang de l'élève dans cette matière
                         $rang_matiere_query = "SELECT COUNT(*) + 1 as rang
                                              FROM (
                                                  SELECT e.id, AVG(n.valeur) as moyenne
                                                  FROM eleves e
                                                  JOIN inscriptions i ON e.id = i.eleve_id
                                                  JOIN notes n ON e.id = n.eleve_id
                                                  JOIN evaluations ev ON n.evaluation_id = ev.id
                                                  JOIN classe_cours cc ON ev.classe_cours_id = cc.id
                                                  WHERE cc.classe_id = :classe_id 
                                                  AND cc.cours_id = :cours_id
                                                  AND ev.periode = :periode
                                                  AND n.absent = 0
                                                  AND n.validee = 1
                                                  AND i.classe_id = :classe_id
                                                  AND i.annee_scolaire = :annee_scolaire
                                                  GROUP BY e.id
                                                  HAVING AVG(n.valeur) > :moyenne_eleve
                                              ) as classement";
                         $rang_matiere_stmt = $db->prepare($rang_matiere_query);
                         $rang_matiere_stmt->execute([
                             'classe_id' => $classe_id,
                             'cours_id' => $matiere['cours_id'],
                             'periode' => $periode,
                             'annee_scolaire' => $annee_scolaire['libelle'],
                             'moyenne_eleve' => $moyenne_matiere ?: 0
                         ]);
                         $rang_matiere_data = $rang_matiere_stmt->fetch();
                         $rang_matiere = $rang_matiere_data['rang'] ?: 1;
                         
                         // Calculer la moyenne pondérée
                         $moyenne_ponderee = $moyenne_matiere ? round($moyenne_matiere * $matiere['coefficient'], 2) : null;
                         
                         // Insérer la ligne du bulletin
                         $insert_ligne_query = "INSERT INTO bulletin_lignes (bulletin_id, cours_id, moyenne_matiere, 
                                                                           rang_matiere, moyenne_ponderee, coefficient,
                                                                           statut, created_by) 
                                              VALUES (:bulletin_id, :cours_id, :moyenne_matiere, 
                                                      :rang_matiere, :moyenne_ponderee, :coefficient,
                                                      'actif', :created_by)";
                         $insert_ligne_stmt = $db->prepare($insert_ligne_query);
                         $insert_ligne_stmt->execute([
                             'bulletin_id' => $bulletin_id,
                             'cours_id' => $matiere['cours_id'],
                             'moyenne_matiere' => $moyenne_matiere,
                             'rang_matiere' => $rang_matiere,
                             'moyenne_ponderee' => $moyenne_ponderee,
                             'coefficient' => $matiere['coefficient'],
                             'created_by' => $_SESSION['user_id']
                         ]);
                     }
                }
            }
            
            safeCommit($db);
            $success = "Bulletins générés avec succès pour la classe et la période sélectionnées !";
            
            // Recharger les bulletins
            $bulletins_stmt->execute([
                'ecole_id' => $_SESSION['ecole_id'],
                'annee_scolaire' => $annee_scolaire['libelle']
            ]);
            $bulletins = $bulletins_stmt->fetchAll();
            
        } catch (Exception $e) {
            safeRollback($db);
            $errors[] = "Erreur lors de la génération des bulletins : " . $e->getMessage();
        }
    }
}

// Récupérer les messages flash
$flash_messages = getFlashMessages();

$page_title = "Bulletins Scolaires";
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
                <p class="text-muted">Gestion des bulletins scolaires - <?php echo $annee_scolaire['libelle'] ?? ''; ?></p>
            </div>
            
                         <div class="topbar-actions">
                 <a href="update_existing_bulletins.php" class="btn btn-outline-warning me-2">
                     <i class="bi bi-tools"></i>
                     <span>Mettre à jour</span>
                 </a>
                 <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#generateBulletinsModal">
                     <i class="bi bi-plus-circle"></i>
                     <span>Générer Bulletins</span>
                 </button>
                
                <div class="dropdown">
                    <button class="btn btn-link p-0" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-4"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile/"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                        <li><a class="dropdown-item" href="../settings/"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Contenu principal -->
        <div class="content-area">
            <!-- Messages flash -->
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Informations de l'année scolaire -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-calendar-event me-2"></i>Année Scolaire</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Libellé :</strong> <?php echo htmlspecialchars($annee_scolaire['libelle'] ?? ''); ?></p>
                                    <p><strong>Période :</strong> <?php echo htmlspecialchars($annee_scolaire['date_debut'] ?? '') . ' au ' . htmlspecialchars($annee_scolaire['date_fin'] ?? ''); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Classes :</strong> <?php echo count($classes); ?></p>
                                    <p><strong>Périodes :</strong> <?php echo count($periodes); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques des bulletins -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($bulletins); ?></h3>
                            <p>Bulletins générés</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($classes); ?></h3>
                            <p>Classes actives</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-calendar-range"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($periodes); ?></h3>
                            <p>Périodes scolaires</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo array_sum(array_column($classes, 'effectif')); ?></h3>
                            <p>Total élèves</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Liste des bulletins -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="bi bi-list-ul me-2"></i>Bulletins Générés</h5>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportBulletins('pdf')">
                                    <i class="bi bi-file-pdf"></i> PDF
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportBulletins('excel')">
                                    <i class="bi bi-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($bulletins)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-journal-text display-1 text-muted"></i>
                                    <h4 class="mt-3 text-muted">Aucun bulletin généré</h4>
                                    <p class="text-muted">Utilisez le bouton "Générer Bulletins" pour créer les premiers bulletins.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Élève</th>
                                                <th>Matricule</th>
                                                <th>Classe</th>
                                                <th>Période</th>
                                                <th>Moyenne</th>
                                                <th>Statut</th>
                                                <th>Généré le</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bulletins as $bulletin): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm me-2">
                                                                <i class="bi bi-person-circle fs-4"></i>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($bulletin['nom'] . ' ' . $bulletin['prenom']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($bulletin['niveau']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($bulletin['matricule']); ?></td>
                                                    <td><?php echo htmlspecialchars($bulletin['nom_classe']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($bulletin['periode']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($bulletin['moyenne_generale']): ?>
                                                            <span class="fw-bold <?php echo $bulletin['moyenne_generale'] >= 10 ? 'text-success' : 'text-danger'; ?>">
                                                                <?php echo number_format($bulletin['moyenne_generale'], 2); ?>/20
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($bulletin['valide']): ?>
                                                            <span class="badge bg-success">Validé</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">En attente</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo $bulletin['genere_le'] ? date('d/m/Y H:i', strtotime($bulletin['genere_le'])) : '-'; ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="view_bulletin.php?id=<?php echo $bulletin['id']; ?>" class="btn btn-outline-primary" title="Voir">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="edit_bulletin.php?id=<?php echo $bulletin['id']; ?>" class="btn btn-outline-secondary" title="Modifier">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-success" title="Valider" onclick="validerBulletin(<?php echo $bulletin['id']; ?>)">
                                                                <i class="bi bi-check-circle"></i>
                                                            </button>
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
    </main>
    
    <!-- Modal de génération de bulletins -->
    <div class="modal fade" id="generateBulletinsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Générer des Bulletins
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_bulletins">
                        
                        <div class="mb-3">
                            <label for="classe_id" class="form-label">Classe</label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom_classe']); ?> 
                                        (<?php echo $classe['effectif']; ?> élèves)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="periode" class="form-label">Période</label>
                            <select class="form-select" id="periode" name="periode" required>
                                <option value="">Sélectionner une période</option>
                                <?php foreach ($periodes as $periode): ?>
                                    <option value="<?php echo htmlspecialchars($periode['nom']); ?>">
                                        <?php echo htmlspecialchars($periode['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                                                 <div class="alert alert-info">
                             <i class="bi bi-info-circle me-2"></i>
                             <strong>Note :</strong> Cette action va générer des bulletins pour tous les élèves de la classe sélectionnée 
                             pour la période choisie. Les bulletins existants ne seront pas dupliqués.
                         </div>
                         
                         <div class="text-center">
                             <button type="button" class="btn btn-outline-info btn-sm" onclick="testNotes()">
                                 <i class="bi bi-bug me-1"></i>Tester la récupération des notes
                             </button>
                         </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Générer les Bulletins
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Fonction d'export des bulletins
        function exportBulletins(format) {
            const anneeId = <?php echo $annee_id; ?>;
            let url = `export_bulletins.php?annee_id=${anneeId}&format=${format}`;
            window.open(url, '_blank');
        }
        
        // Fonction de validation des bulletins
        function validerBulletin(bulletinId) {
            if (confirm('Êtes-vous sûr de vouloir valider ce bulletin ?')) {
                // Ici, vous pouvez ajouter une requête AJAX pour valider le bulletin
                alert('Fonctionnalité de validation à implémenter');
            }
        }
        
                 // Initialisation des tooltips Bootstrap
         var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
         var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
             return new bootstrap.Tooltip(tooltipTriggerEl);
         });
         
         // Fonction de test des notes
         function testNotes() {
             const classeId = document.getElementById('classe_id').value;
             if (classeId) {
                 const url = `test_notes.php?annee_id=<?php echo $annee_id; ?>&classe_id=${classeId}`;
                 window.open(url, '_blank');
             } else {
                 alert('Veuillez d\'abord sélectionner une classe');
             }
         }
         

    </script>
</body>
</html>
