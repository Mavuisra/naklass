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

// Traitement du formulaire de sauvegarde des notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_notes') {
    try {
        $db->beginTransaction();
        
        $evaluation_id = intval($_POST['evaluation_id'] ?? 0);
        $notes_data = $_POST['notes'] ?? [];
        
        if (!$evaluation_id) {
            throw new Exception("ID d'évaluation manquant.");
        }
        
        // Vérifier que l'évaluation appartient à l'école
        $eval_check = "SELECT e.id, cc.classe_id, c.nom_cours 
                       FROM evaluations e
                       JOIN classe_cours cc ON e.classe_cours_id = cc.id
                       JOIN cours c ON cc.cours_id = c.id
                       WHERE e.id = :eval_id AND c.ecole_id = :ecole_id";
        $eval_stmt = $db->prepare($eval_check);
        $eval_stmt->execute(['eval_id' => $evaluation_id, 'ecole_id' => $_SESSION['ecole_id']]);
        $evaluation = $eval_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evaluation) {
            throw new Exception("Évaluation introuvable.");
        }
        
        foreach ($notes_data as $eleve_id => $note_info) {
            $eleve_id = intval($eleve_id);
            $valeur = !empty($note_info['valeur']) ? floatval($note_info['valeur']) : null;
            $absent = isset($note_info['absent']) ? 1 : 0;
            $excuse = isset($note_info['excuse']) ? 1 : 0;
            $rattrapage = isset($note_info['rattrapage']) ? 1 : 0;
            $commentaire = sanitize($note_info['commentaire'] ?? '');
            
            // Vérifier si la note existe déjà
            $existing_note = "SELECT id FROM notes WHERE evaluation_id = :eval_id AND eleve_id = :eleve_id";
            $existing_stmt = $db->prepare($existing_note);
            $existing_stmt->execute(['eval_id' => $evaluation_id, 'eleve_id' => $eleve_id]);
            $note_exists = $existing_stmt->fetch();
            
            if ($note_exists) {
                // Mettre à jour la note existante
                $update_note = "UPDATE notes SET 
                               valeur = :valeur,
                               absent = :absent,
                               excuse = :excuse,
                               rattrapage = :rattrapage,
                               commentaire = :commentaire,
                               updated_by = :updated_by,
                               updated_at = NOW()
                               WHERE id = :note_id";
                $update_stmt = $db->prepare($update_note);
                $update_stmt->execute([
                    'valeur' => $valeur,
                    'absent' => $absent,
                    'excuse' => $excuse,
                    'rattrapage' => $rattrapage,
                    'commentaire' => $commentaire,
                    'updated_by' => $_SESSION['user_id'],
                    'note_id' => $note_exists['id']
                ]);
            } else {
                // Créer une nouvelle note
                $insert_note = "INSERT INTO notes (
                               evaluation_id, eleve_id, valeur, absent, excuse, rattrapage,
                               commentaire, created_by, created_at
                               ) VALUES (
                               :eval_id, :eleve_id, :valeur, :absent, :excuse, :rattrapage,
                               :commentaire, :created_by, NOW()
                               )";
                $insert_stmt = $db->prepare($insert_note);
                $insert_stmt->execute([
                    'eval_id' => $evaluation_id,
                    'eleve_id' => $eleve_id,
                    'valeur' => $valeur,
                    'absent' => $absent,
                    'excuse' => $excuse,
                    'rattrapage' => $rattrapage,
                                        'commentaire' => $commentaire,
                    'created_by' => $_SESSION['user_id']
                ]);
            }
        }
        
        $db->commit();
        $success = "Notes sauvegardées avec succès !";
        
        // Log de l'action
        logUserAction('SAVE_GRADES', "Saisie notes - Évaluation ID: $evaluation_id");
        
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = $e->getMessage();
        error_log("Erreur sauvegarde notes: " . $e->getMessage());
    }
}

// Récupérer les paramètres de sélection
$annee_scolaire_id = intval($_GET['annee_scolaire_id'] ?? $_POST['annee_scolaire_id'] ?? 0);
$classe_id = intval($_GET['classe_id'] ?? $_POST['classe_id'] ?? 0);
$cours_id = intval($_GET['cours_id'] ?? $_POST['cours_id'] ?? 0);
$periode_id = intval($_GET['periode_id'] ?? $_POST['periode_id'] ?? 0);
$evaluation_id = intval($_GET['evaluation_id'] ?? $_POST['evaluation_id'] ?? 0);

// Récupérer les classes selon le rôle de l'utilisateur
if (hasRole(['admin', 'direction'])) {
    // Admin et direction voient toutes les classes
    $classes_query = "SELECT c.*, COUNT(i.id) as nb_eleves
                      FROM classes c 
                      LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut IN ('validée', 'en_cours')
                      WHERE c.ecole_id = :ecole_id AND c.statut = 'actif'
                      GROUP BY c.id
                      ORDER BY c.niveau, c.nom_classe";
} else {
    // Enseignants voient seulement leurs classes assignées
    // D'abord, récupérer l'ID de l'enseignant et son école
    $enseignant_query = "SELECT e.id as enseignant_id, e.ecole_id 
                         FROM enseignants e 
                         WHERE e.utilisateur_id = :user_id";
    $stmt = $db->prepare($enseignant_query);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $enseignant_info = $stmt->fetch();
    
    if ($enseignant_info) {
        // Utiliser l'ecole_id de l'enseignant, pas celui de la session
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
    } else {
        // Fallback si l'enseignant n'est pas trouvé
        $classes_query = "SELECT 1 WHERE 1=0"; // Requête qui ne retourne rien
    }
}

$classes_stmt = $db->prepare($classes_query);
if (hasRole(['admin', 'direction'])) {
    $classes_params = ['ecole_id' => $_SESSION['ecole_id']];
} else {
    if (isset($enseignant_info)) {
        $classes_params = ['ecole_id' => $ecole_enseignant, 'enseignant_id' => $enseignant_id];
    } else {
        $classes_params = [];
    }
}
$classes_stmt->execute($classes_params);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les cours pour la classe sélectionnée
$cours = [];
if ($classe_id) {
    if (hasRole(['admin', 'direction'])) {
        // Admin et direction voient tous les cours de la classe
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
        // Enseignants voient seulement leurs cours assignés dans cette classe
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
            $cours_query = "SELECT 1 WHERE 1=0"; // Requête qui ne retourne rien
            $cours_params = [];
        }
    }
    
    $cours_stmt = $db->prepare($cours_query);
    $cours_stmt->execute($cours_params);
    $cours = $cours_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les années scolaires
$annees_query = "SELECT * FROM annees_scolaires WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY active DESC, date_debut DESC";
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

// Récupérer les évaluations pour le cours et la période sélectionnés
$evaluations = [];
if ($cours_id && $periode_id) {
    if (hasRole(['admin', 'direction'])) {
        // Admin et direction voient toutes les évaluations
        $evaluations_query = "SELECT e.id, e.nom_evaluation, e.type_evaluation, e.date_evaluation, e.bareme, 
                                    e.ponderation, e.description, e.statut, e.periode_scolaire_id, cc.classe_id
                             FROM evaluations e
                             JOIN classe_cours cc ON e.classe_cours_id = cc.id
                             WHERE cc.cours_id = :cours_id AND cc.classe_id = :classe_id
                             AND e.periode_scolaire_id = :periode_id
                             ORDER BY e.date_evaluation DESC";
        $eval_params = ['cours_id' => $cours_id, 'classe_id' => $classe_id, 'periode_id' => $periode_id];
    } else {
        // Enseignants voient seulement leurs évaluations
        if (isset($enseignant_info)) {
            $evaluations_query = "SELECT e.id, e.nom_evaluation, e.type_evaluation, e.date_evaluation, e.bareme, 
                                        e.ponderation, e.description, e.statut, e.periode_scolaire_id, cc.classe_id
                                 FROM evaluations e
                                 JOIN classe_cours cc ON e.classe_cours_id = cc.id
                                 WHERE cc.cours_id = :cours_id AND cc.classe_id = :classe_id
                                 AND e.periode_scolaire_id = :periode_id
                                 AND cc.enseignant_id = :enseignant_id
                                 ORDER BY e.date_evaluation DESC";
            $eval_params = ['cours_id' => $cours_id, 'classe_id' => $classe_id, 'periode_id' => $periode_id, 'enseignant_id' => $enseignant_id];
        } else {
            $evaluations_query = "SELECT 1 WHERE 1=0"; // Requête qui ne retourne rien
            $eval_params = [];
        }
    }
    
    $eval_stmt = $db->prepare($evaluations_query);
    $eval_stmt->execute($eval_params);
    $evaluations = $eval_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les élèves et leurs notes pour l'évaluation sélectionnée
$eleves_notes = [];
$evaluation_info = null;
if ($evaluation_id) {
    // Informations de l'évaluation
    if (hasRole(['admin', 'direction'])) {
        // Admin et direction voient toutes les évaluations
        $eval_info_query = "SELECT e.id, e.nom_evaluation, e.type_evaluation, e.date_evaluation, e.bareme, 
                                  e.ponderation, e.description, e.statut, e.periode_scolaire_id,
                                  c.nom_cours, c.coefficient, cl.nom_classe as classe_nom, cc.classe_id
                           FROM evaluations e
                           JOIN classe_cours cc ON e.classe_cours_id = cc.id
                           JOIN cours c ON cc.cours_id = c.id
                           JOIN classes cl ON cc.classe_id = cl.id
                           WHERE e.id = :evaluation_id";
        $eval_info_params = ['evaluation_id' => $evaluation_id];
    } else {
        // Enseignants voient seulement leurs évaluations
        if (isset($enseignant_info)) {
            $eval_info_query = "SELECT e.id, e.nom_evaluation, e.type_evaluation, e.date_evaluation, e.bareme, 
                                      e.ponderation, e.description, e.statut, e.periode_scolaire_id,
                                      c.nom_cours, c.coefficient, cl.nom_classe as classe_nom, cc.classe_id
                               FROM evaluations e
                               JOIN classe_cours cc ON e.classe_cours_id = cc.id
                               JOIN cours c ON cc.cours_id = c.id
                               JOIN classes cl ON cc.classe_id = cl.id
                               WHERE e.id = :evaluation_id AND cc.enseignant_id = :enseignant_id";
            $eval_info_params = ['evaluation_id' => $evaluation_id, 'enseignant_id' => $enseignant_id];
        } else {
            $eval_info_query = "SELECT 1 WHERE 1=0"; // Requête qui ne retourne rien
            $eval_info_params = [];
        }
    }
    
    $eval_info_stmt = $db->prepare($eval_info_query);
    $eval_info_stmt->execute($eval_info_params);
    $evaluation_info = $eval_info_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($evaluation_info) {
        // Récupérer les élèves de la classe avec leurs notes
        $eleves_query = "SELECT el.id, el.matricule, el.nom, el.prenom,
                                n.id as note_id, n.valeur, n.absent, n.excuse, n.rattrapage, n.commentaire,
                                n.validee, n.created_at as date_creation
                         FROM eleves el
                         JOIN inscriptions i ON el.id = i.eleve_id
                         LEFT JOIN notes n ON el.id = n.eleve_id AND n.evaluation_id = :evaluation_id
                         WHERE i.classe_id = :classe_id 
                         AND i.statut IN ('validée', 'en_cours', 'actif')
                         AND el.statut = 'actif'
                         ORDER BY el.nom, el.prenom";
        $eleves_stmt = $db->prepare($eleves_query);
        $eleves_stmt->execute([
            'evaluation_id' => $evaluation_id,
            'classe_id' => $evaluation_info['classe_id']
        ]);
        $eleves_notes = $eleves_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$page_title = "Saisie des Notes";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Naklass</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- CSS personnalisés -->
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/naklass-theme.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- En-tête -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-journal-text me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
                <p class="text-muted">Saisie et modification des notes des élèves</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Retour
                </a>
                <?php if ($evaluation_id && !empty($eleves_notes)): ?>
                    <button type="button" class="btn btn-success" onclick="saveAllNotes()">
                        <i class="bi bi-check-circle me-1"></i>Sauvegarder tout
                    </button>
                <?php endif; ?>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            
            <!-- Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Sélection Classe/Cours/Évaluation -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2"></i>Sélection de l'évaluation
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!hasRole(['admin', 'direction'])): ?>
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Mode Enseignant :</strong> Vous ne voyez que vos classes et cours assignés.
                        </div>
                    <?php endif; ?>
                    <form method="GET" class="row g-4" id="selectionForm">
                        <!-- Première ligne -->
                        <div class="col-md-6">
                            <label for="annee_scolaire_id" class="form-label fw-bold">
                                <i class="bi bi-calendar-event me-2"></i>Année scolaire
                            </label>
                            <select class="form-select form-select-lg" id="annee_scolaire_id" name="annee_scolaire_id" onchange="loadPeriodes()">
                                <?php foreach ($annees_scolaires as $annee): ?>
                                    <option value="<?php echo $annee['id']; ?>" 
                                            <?php echo $annee_scolaire_id == $annee['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($annee['libelle']); ?>
                                        <?php if ($annee['active']): ?>
                                            <span class="badge bg-warning text-dark ms-2">⭐ Active</span>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="periode_id" class="form-label fw-bold">
                                <i class="bi bi-calendar-week me-2"></i>Trimestre / Période
                            </label>
                            <select class="form-select form-select-lg" id="periode_id" name="periode_id" required onchange="loadEvaluations()"
                                    <?php echo !$annee_scolaire_id ? 'disabled' : ''; ?>>
                                <option value="">Sélectionner une période</option>
                                <?php 
                                $current_trimestre = '';
                                foreach ($periodes as $periode): 
                                    if ($periode['type_periode'] === 'trimestre') {
                                        $current_trimestre = $periode['nom'];
                                        echo '<optgroup label="' . htmlspecialchars($current_trimestre) . '">';
                                        echo '<option value="' . $periode['id'] . '"' . ($periode_id == $periode['id'] ? ' selected' : '') . '>';
                                        echo htmlspecialchars($periode['nom']) . ' (Synthèse)';
                                        echo '</option>';
                                    } else {
                                        echo '<option value="' . $periode['id'] . '"' . ($periode_id == $periode['id'] ? ' selected' : '') . '>';
                                        echo htmlspecialchars($periode['nom']);
                                        echo '</option>';
                                    }
                                    
                                    // Fermer le groupe si c'est le dernier élément ou si le suivant est un trimestre
                                    $next_key = array_search($periode, $periodes) + 1;
                                    if ($next_key >= count($periodes) || (isset($periodes[$next_key]) && $periodes[$next_key]['type_periode'] === 'trimestre')) {
                                        if ($periode['type_periode'] === 'periode') {
                                            echo '</optgroup>';
                                        }
                                    }
                                endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Deuxième ligne -->
                        <div class="col-md-6">
                            <label for="classe_id" class="form-label fw-bold">
                                <i class="bi bi-building me-2"></i>Classe
                            </label>
                            <select class="form-select form-select-lg" id="classe_id" name="classe_id" required onchange="loadCours()">
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                            <?php echo $classe_id == $classe['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['nom_classe']); ?>
                                        <span class="badge bg-info ms-2"><?php echo $classe['nb_eleves']; ?> élèves</span>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="cours_id" class="form-label fw-bold">
                                <i class="bi bi-book me-2"></i>Cours/Matière
                            </label>
                            <select class="form-select form-select-lg" id="cours_id" name="cours_id" required onchange="resetEvaluations()" 
                                    <?php echo !$classe_id ? 'disabled' : ''; ?>>
                                <option value="">Sélectionner un cours</option>
                                <?php foreach ($cours as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" 
                                            <?php echo $cours_id == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['nom_cours']); ?>
                                        <?php if (!empty($c['enseignant_nom_complet'])): ?>
                                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($c['enseignant_nom_complet']); ?></span>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Troisième ligne -->
                        <div class="col-12">
                            <label for="evaluation_id" class="form-label fw-bold">
                                <i class="bi bi-journal-text me-2"></i>Évaluation
                            </label>
                            <select class="form-select form-select-lg" id="evaluation_id" name="evaluation_id" required onchange="loadNotes()"
                                    <?php echo !$periode_id ? 'disabled' : ''; ?>>
                                <option value="">Sélectionner une évaluation</option>
                                <?php foreach ($evaluations as $eval): ?>
                                    <option value="<?php echo $eval['id']; ?>" 
                                            <?php echo $evaluation_id == $eval['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($eval['nom_evaluation']); ?> 
                                        <span class="badge bg-primary ms-2"><?php echo formatDate($eval['date_evaluation'], 'd/m/Y'); ?></span>
                                        <span class="badge bg-success ms-2"><?php echo $eval['bareme']; ?> pts</span>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Bouton de validation -->
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-5" id="validateSelection">
                                <i class="bi bi-check-circle me-2"></i>Valider la sélection
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($evaluation_id && $evaluation_info): ?>
                <!-- Informations de l'évaluation -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>Détails de l'évaluation
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="bi bi-journal-text display-6 text-primary mb-2"></i>
                                    <h6 class="fw-bold">Évaluation</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($evaluation_info['nom_evaluation']); ?></p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="bi bi-book display-6 text-success mb-2"></i>
                                    <h6 class="fw-bold">Cours</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($evaluation_info['nom_cours']); ?></p>
                                    <small class="text-muted">Coef. <?php echo $evaluation_info['coefficient']; ?></small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="bi bi-calendar-event display-6 text-warning mb-2"></i>
                                    <h6 class="fw-bold">Date</h6>
                                    <p class="mb-0"><?php echo formatDate($evaluation_info['date_evaluation'], 'd/m/Y'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="bi bi-star display-6 text-danger mb-2"></i>
                                    <h6 class="fw-bold">Note max</h6>
                                    <span class="badge bg-primary fs-5"><?php echo $evaluation_info['bareme']; ?> points</span>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($evaluation_info['description'])): ?>
                            <div class="mt-4 p-3 bg-light rounded">
                                <h6 class="fw-bold"><i class="bi bi-text-paragraph me-2"></i>Description :</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($evaluation_info['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Saisie des notes -->
                <?php if (!empty($eleves_notes)): ?>
                    <div class="card">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-table me-2"></i>Saisie des notes
                            </h5>
                            <div>
                                <span class="badge bg-light text-dark fs-6"><?php echo count($eleves_notes); ?> élève(s)</span>
                                <button type="button" class="btn btn-light btn-sm ms-2" onclick="toggleAllAbsent()">
                                    <i class="bi bi-person-x me-1"></i>Tout marquer absent
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <form id="notesForm" method="POST">
                                <input type="hidden" name="action" value="save_notes">
                                <input type="hidden" name="evaluation_id" value="<?php echo $evaluation_id; ?>">
                                <input type="hidden" name="annee_scolaire_id" value="<?php echo $annee_scolaire_id; ?>">
                                <input type="hidden" name="classe_id" value="<?php echo $classe_id; ?>">
                                <input type="hidden" name="cours_id" value="<?php echo $cours_id; ?>">
                                <input type="hidden" name="periode_id" value="<?php echo $periode_id; ?>">
                                
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th width="5%" class="text-center">#</th>
                                                <th width="15%">Matricule</th>
                                                <th width="25%">Nom de l'élève</th>
                                                <th width="12%" class="text-center">Note / <?php echo $evaluation_info['bareme']; ?></th>
                                                <th width="8%" class="text-center">Absent</th>
                                                <th width="8%" class="text-center">Excuse</th>
                                                <th width="25%">Commentaire</th>
                                                <th width="8%" class="text-center">Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($eleves_notes as $index => $eleve): ?>
                                                <tr class="align-middle">
                                                    <td class="text-center">
                                                        <span class="badge bg-secondary"><?php echo $index + 1; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="font-monospace fw-bold text-primary"><?php echo htmlspecialchars($eleve['matricule']); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-person-circle text-success me-2 fs-5"></i>
                                                            <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="number" 
                                                               class="form-control form-control-lg note-input text-center fw-bold" 
                                                               name="notes[<?php echo $eleve['id']; ?>][valeur]"
                                                               value="<?php echo $eleve['valeur']; ?>"
                                                               min="0" 
                                                               max="<?php echo $evaluation_info['bareme']; ?>"
                                                               step="0.25"
                                                               data-eleve-id="<?php echo $eleve['id']; ?>"
                                                               style="max-width: 80px;">
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="form-check d-flex justify-content-center">
                                                            <input type="checkbox" 
                                                                   class="form-check-input absent-checkbox" 
                                                                   name="notes[<?php echo $eleve['id']; ?>][absent]"
                                                                   <?php echo $eleve['absent'] ? 'checked' : ''; ?>
                                                                   data-eleve-id="<?php echo $eleve['id']; ?>"
                                                                   style="transform: scale(1.5);">
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="form-check d-flex justify-content-center">
                                                            <input type="checkbox" 
                                                                   class="form-check-input" 
                                                                   name="notes[<?php echo $eleve['id']; ?>][excuse]"
                                                                   <?php echo $eleve['excuse'] ? 'checked' : ''; ?>
                                                                   style="transform: scale(1.5);">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" 
                                                               class="form-control" 
                                                               name="notes[<?php echo $eleve['id']; ?>][commentaire]"
                                                               value="<?php echo htmlspecialchars($eleve['commentaire']); ?>"
                                                               placeholder="Commentaire...">
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($eleve['note_id']): ?>
                                                            <?php if ($eleve['validee']): ?>
                                                                <span class="badge bg-success fs-6">
                                                                    <i class="bi bi-check-circle me-1"></i>Validée
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark fs-6">
                                                                    <i class="bi bi-clock me-1"></i>Saisie
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if ($eleve['date_creation']): ?>
                                                                <br><small class="text-muted">
                                                                    <?php echo formatDate($eleve['date_creation'], 'd/m/Y H:i'); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary fs-6">Non saisi</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-4 p-3 bg-light rounded">
                                    <div>
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Les notes sont automatiquement sauvegardées. 
                                            Utilisez Tab pour naviguer rapidement entre les champs.
                                        </small>
                                    </div>
                                    <div>
                                        <button type="submit" class="btn btn-success btn-lg px-4">
                                            <i class="bi bi-check-circle me-1"></i>Sauvegarder toutes les notes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucun élève trouvé pour cette classe et cette évaluation.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div> <!-- End content-area -->
    </div> <!-- End main-content -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JS personnalisés -->
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        // Fonctions de navigation
        function loadPeriodes() {
            const anneeId = document.getElementById('annee_scolaire_id').value;
            const periodeSelect = document.getElementById('periode_id');
            const evalSelect = document.getElementById('evaluation_id');
            
            // Reset des sélections suivantes
            periodeSelect.innerHTML = '<option value="">Sélectionner une période</option>';
            evalSelect.innerHTML = '<option value="">Sélectionner une évaluation</option>';
            periodeSelect.disabled = !anneeId;
            evalSelect.disabled = true;
            
            if (anneeId) {
                document.getElementById('selectionForm').submit();
            }
        }
        
        function loadCours() {
            const classeId = document.getElementById('classe_id').value;
            const coursSelect = document.getElementById('cours_id');
            const evalSelect = document.getElementById('evaluation_id');
            
            // Reset des sélections suivantes
            coursSelect.innerHTML = '<option value="">Sélectionner un cours</option>';
            evalSelect.innerHTML = '<option value="">Sélectionner une évaluation</option>';
            coursSelect.disabled = !classeId;
            evalSelect.disabled = true;
            
            if (classeId) {
                document.getElementById('selectionForm').submit();
            }
        }
        
        function resetEvaluations() {
            const evalSelect = document.getElementById('evaluation_id');
            evalSelect.innerHTML = '<option value="">Sélectionner une évaluation</option>';
            evalSelect.disabled = true;
        }
        
        function loadEvaluations() {
            const periodeId = document.getElementById('periode_id').value;
            const coursId = document.getElementById('cours_id').value;
            const evalSelect = document.getElementById('evaluation_id');
            
            evalSelect.innerHTML = '<option value="">Sélectionner une évaluation</option>';
            evalSelect.disabled = !(periodeId && coursId);
            
            if (periodeId && coursId) {
                document.getElementById('selectionForm').submit();
            }
        }
        
        function loadNotes() {
            const evalId = document.getElementById('evaluation_id').value;
            if (evalId) {
                document.getElementById('selectionForm').submit();
            }
        }
        
        // Gestion des checkboxes absent
        document.addEventListener('DOMContentLoaded', function() {
            const absentCheckboxes = document.querySelectorAll('.absent-checkbox');
            
            absentCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const eleveId = this.dataset.eleveId;
                    const noteInput = document.querySelector(`input[name="notes[${eleveId}][valeur]"]`);
                    
                    if (this.checked) {
                        noteInput.value = '';
                        noteInput.disabled = true;
                        noteInput.style.backgroundColor = '#f8f9fa';
                    } else {
                        noteInput.disabled = false;
                        noteInput.style.backgroundColor = '';
                    }
                });
                
                // Appliquer l'état initial
                if (checkbox.checked) {
                    const eleveId = checkbox.dataset.eleveId;
                    const noteInput = document.querySelector(`input[name="notes[${eleveId}][valeur]"]`);
                    noteInput.disabled = true;
                    noteInput.style.backgroundColor = '#f8f9fa';
                }
            });
        });
        
        // Fonction pour marquer tous comme absents
        function toggleAllAbsent() {
            const checkboxes = document.querySelectorAll('.absent-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
                checkbox.dispatchEvent(new Event('change'));
            });
        }
        
        // Auto-sauvegarde
        function saveAllNotes() {
            document.getElementById('notesForm').submit();
        }
        
        // Validation des notes
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('note-input')) {
                const max = parseFloat(e.target.max);
                const value = parseFloat(e.target.value);
                
                if (value > max) {
                    e.target.setCustomValidity(`La note ne peut pas dépasser ${max}`);
                    e.target.classList.add('is-invalid');
                } else {
                    e.target.setCustomValidity('');
                    e.target.classList.remove('is-invalid');
                }
            }
        });
        
        // Navigation au clavier
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                // Permettre la navigation normale avec Tab
                return;
            }
            
            if (e.key === 'Enter') {
                e.preventDefault();
                const currentInput = e.target;
                
                if (currentInput.classList.contains('note-input')) {
                    // Passer au champ suivant
                    const allInputs = Array.from(document.querySelectorAll('.note-input'));
                    const currentIndex = allInputs.indexOf(currentInput);
                    
                    if (currentIndex < allInputs.length - 1) {
                        allInputs[currentIndex + 1].focus();
                        allInputs[currentIndex + 1].select();
                    }
                }
            }
        });
    </script>

<style>
.main-content {
    margin-left: 250px;
    padding: 20px;
    min-height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.topbar {
    background: white;
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 28px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid rgba(255,255,255,0.2);
}

.topbar-title h1 {
    margin: 0 0 8px 0;
    font-size: 28px;
    font-weight: 800;
    color: #2c3e50;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.topbar-title p {
    margin: 0;
    color: #7f8c8d;
    font-size: 1.1rem;
}

.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    background: rgba(255,255,255,0.95);
}

.card-header {
    border-bottom: 2px solid rgba(0,0,0,0.1);
    border-radius: 16px 16px 0 0 !important;
    padding: 24px 28px;
    font-weight: 600;
}

.card-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #2c3e50;
}

.card-body {
    padding: 28px;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
}

.form-select-lg {
    border-radius: 12px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.form-select-lg:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    transform: translateY(-2px);
}

.table {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 16px 12px;
    border: none;
}

.table td {
    padding: 16px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f8f9fa;
}

.table tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
    transform: scale(1.01);
    transition: all 0.2s ease;
}

.note-input {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
    font-weight: 600;
}

.note-input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    transform: translateY(-1px);
}

.note-input.is-invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6-.8.8.8.8.8-.8z'%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.btn {
    border-radius: 12px;
    font-weight: 600;
    padding: 12px 24px;
    transition: all 0.3s ease;
    border: none;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.btn-lg {
    padding: 16px 32px;
    font-size: 1.1rem;
}

.badge {
    border-radius: 8px;
    font-weight: 600;
    padding: 8px 12px;
}

.form-check-input {
    border-radius: 6px;
    border: 2px solid #dee2e6;
    transition: all 0.2s ease;
}

.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.form-check-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
}

/* Animations */
.card {
    animation: slideInUp 0.6s ease-out;
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

/* Responsive */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
    
    .topbar {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
        padding: 20px;
    }
    
    .topbar-actions {
        width: 100%;
        justify-content: center;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .btn-lg {
        padding: 12px 24px;
        font-size: 1rem;
    }
}

@media (max-width: 991px) {
    .main-content {
        margin-left: 0;
    }
}

/* Custom scrollbar */
.table-responsive::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>

</body>
</html>
