<?php
/**
 * Gestion des Évaluations
 * Page pour créer, modifier et gérer les évaluations d'une année scolaire
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['ecole_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Vérifier les permissions
if (!hasRole(['admin', 'direction', 'enseignant'])) {
    header('Location: ../auth/dashboard.php');
    exit;
}

$errors = [];
$success = '';
$evaluations = [];
$classe_cours = [];
$periodes = [];

// Récupérer l'ID de l'année scolaire depuis l'URL
$annee_id = isset($_GET['annee_id']) ? (int)$_GET['annee_id'] : 0;

if (!$annee_id) {
    header('Location: index.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Récupérer les informations de l'année scolaire
    $annee_query = "SELECT * FROM annees_scolaires WHERE id = :annee_id AND ecole_id = :ecole_id";
    $annee_stmt = $db->prepare($annee_query);
    $annee_stmt->execute(['annee_id' => $annee_id, 'ecole_id' => $_SESSION['ecole_id']]);
    $annee_scolaire = $annee_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$annee_scolaire) {
        setFlashMessage('error', "L'année scolaire demandée n'existe pas ou n'appartient pas à votre école.");
        header('Location: index.php');
        exit;
    }
    
    // Vérifier que l'année scolaire est bien définie avant de continuer
    if (!isset($annee_scolaire['libelle'])) {
        setFlashMessage('error', "Informations de l'année scolaire incomplètes.");
        header('Location: index.php');
        exit;
    }
    
    // Traitement de la création d'évaluation
    if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_evaluation') {
        $nom = trim($_POST['nom']);
        $description = trim($_POST['description']);
        $date_evaluation = $_POST['date_evaluation'];
        $coefficient = (float)$_POST['coefficient'];
        $periode_scolaire_id = (int)$_POST['periode_scolaire_id'];
        $classe_cours_id = (int)$_POST['classe_cours_id'];
        $type_evaluation = $_POST['type_evaluation'];
        
        // Validation
        if (empty($nom)) {
            $errors[] = "Le nom de l'évaluation est requis";
        }
        if (empty($date_evaluation)) {
            $errors[] = "La date de l'évaluation est requise";
        }
        if ($coefficient <= 0) {
            $errors[] = "Le coefficient doit être supérieur à 0";
        }
        if (!$periode_scolaire_id) {
            $errors[] = "La période scolaire est requise";
        }
        if (!$classe_cours_id) {
            $errors[] = "La classe et le cours sont requis";
        }
        
        if (empty($errors)) {
            try {
                $insert_query = "INSERT INTO evaluations (nom_evaluation, description, date_evaluation, ponderation, 
                                annee_scolaire_id, periode_scolaire_id, classe_cours_id, type_evaluation, 
                                created_by, statut) 
                                VALUES (:nom, :description, :date_evaluation, :coefficient, 
                                :annee_scolaire_id, :periode_scolaire_id, :classe_cours_id, :type_evaluation, 
                                :created_by, 'actif')";
                
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->execute([
                    'nom' => $nom,
                    'description' => $description,
                    'date_evaluation' => $date_evaluation,
                    'coefficient' => $coefficient,
                    'annee_scolaire_id' => $annee_id,
                    'periode_scolaire_id' => $periode_scolaire_id,
                    'classe_cours_id' => $classe_cours_id,
                    'type_evaluation' => $type_evaluation,
                    'created_by' => $_SESSION['user_id']
                ]);
                
                $success = "Évaluation créée avec succès";
                
            } catch (Exception $e) {
                $errors[] = "Erreur lors de la création : " . $e->getMessage();
            }
        }
    }
    
    // Traitement de la suppression d'évaluation
    if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_evaluation') {
        $evaluation_id = (int)$_POST['evaluation_id'];
        
        try {
            // Vérifier que l'évaluation appartient à l'école via l'année scolaire
            $check_query = "SELECT e.id FROM evaluations e 
                           JOIN annees_scolaires a ON e.annee_scolaire_id = a.id 
                           WHERE e.id = :evaluation_id AND a.ecole_id = :ecole_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute(['evaluation_id' => $evaluation_id, 'ecole_id' => $_SESSION['ecole_id']]);
            
            if ($check_stmt->fetch()) {
                $delete_query = "UPDATE evaluations SET statut = 'supprimé_logique' WHERE id = :evaluation_id";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->execute(['evaluation_id' => $evaluation_id]);
                
                $success = "Évaluation supprimée avec succès";
            } else {
                $errors[] = "Évaluation non trouvée ou accès non autorisé";
            }
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
    
    // Récupérer les périodes scolaires de l'année
    $periodes_query = "SELECT * FROM periodes_scolaires 
                       WHERE annee_scolaire_id = :annee_id AND statut = 'actif' 
                       ORDER BY type_periode, ordre_periode";
    $periodes_stmt = $db->prepare($periodes_query);
    $periodes_stmt->execute(['annee_id' => $annee_id]);
    $periodes = $periodes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les classe_cours disponibles pour l'année scolaire
    $classe_cours_query = "SELECT cc.*, c.nom_classe, c.niveau, co.nom_cours, e.nom as enseignant_nom, e.prenom as enseignant_prenom
                           FROM classe_cours cc
                           JOIN classes c ON cc.classe_id = c.id
                           JOIN cours co ON cc.cours_id = co.id
                           JOIN enseignants e ON cc.enseignant_id = e.id
                           WHERE c.ecole_id = :ecole_id 
                           AND cc.statut = 'actif' 
                           AND c.statut = 'actif'
                           AND co.statut = 'actif'
                           AND e.statut_record = 'actif'
                           AND cc.annee_scolaire = :annee_scolaire
                           ORDER BY c.niveau, c.nom_classe, co.nom_cours";
    $classe_cours_stmt = $db->prepare($classe_cours_query);
    $classe_cours_stmt->execute(['ecole_id' => $_SESSION['ecole_id'], 'annee_scolaire' => $annee_scolaire['libelle']]);
    $classe_cours = $classe_cours_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Afficher le nombre de classe_cours trouvés
    if (empty($classe_cours)) {
        error_log("Aucun classe_cours trouvé pour l'école " . $_SESSION['ecole_id'] . " et l'année " . $annee_scolaire['libelle']);
        
        // Essayer de trouver des classe_cours sans filtre d'année pour debug
        $debug_query = "SELECT cc.*, c.nom_classe, co.nom_cours, e.nom as enseignant_nom, e.prenom as enseignant_prenom, cc.annee_scolaire as cc_annee, c.annee_scolaire as c_annee
                        FROM classe_cours cc
                        JOIN classes c ON cc.classe_id = c.id
                        JOIN cours co ON cc.cours_id = co.id
                        JOIN enseignants e ON cc.enseignant_id = e.id
                        WHERE c.ecole_id = :ecole_id AND cc.statut = 'actif'
                        LIMIT 5";
        $debug_stmt = $db->prepare($debug_query);
        $debug_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $debug_classe_cours = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($debug_classe_cours)) {
            error_log("Debug - classe_cours trouvés (sans filtre d'année): " . json_encode($debug_classe_cours));
        }
    }
    
    // Note: Les matières sont déterminées par les classes
    
    // Récupérer les évaluations existantes
    $evaluations_query = "SELECT e.*, c.nom_classe, co.nom_cours, p.nom as periode_nom, p.type_periode,
                          COUNT(n.id) as nb_notes
                          FROM evaluations e
                          LEFT JOIN classe_cours cc ON e.classe_cours_id = cc.id
                          LEFT JOIN classes c ON cc.classe_id = c.id
                          LEFT JOIN cours co ON cc.cours_id = co.id
                          LEFT JOIN periodes_scolaires p ON e.periode_scolaire_id = p.id
                          LEFT JOIN notes n ON e.id = n.evaluation_id AND n.statut = 'actif'
                          WHERE e.annee_scolaire_id = :annee_id AND e.statut = 'actif'
                          GROUP BY e.id
                          ORDER BY e.date_evaluation DESC, c.nom_classe";
    $evaluations_stmt = $db->prepare($evaluations_query);
    $evaluations_stmt->execute(['annee_id' => $annee_id]);
    $evaluations = $evaluations_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $errors[] = "Erreur de base de données : " . $e->getMessage();
}

$page_title = "Gestion des Évaluations - " . $annee_scolaire['libelle'];
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
                <h1><i class="bi bi-clipboard-check me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
                <p class="text-muted">Gestion des évaluations pour l'année scolaire <?php echo htmlspecialchars($annee_scolaire['libelle']); ?></p>
            </div>
            
            <div class="topbar-actions">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEvaluationModal">
                    <i class="bi bi-plus-circle me-1"></i>Nouvelle évaluation
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Retour
                </a>
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

            <!-- Statistiques -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Évaluations</h6>
                                    <h3 class="mb-0"><?php echo count($evaluations); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-clipboard-check fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Cours/Classes</h6>
                                    <h3 class="mb-0"><?php echo count($classe_cours); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-collection fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Périodes</h6>
                                    <h3 class="mb-0"><?php echo count($periodes); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-calendar-event fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Évaluations Actives</h6>
                                    <h3 class="mb-0"><?php echo count(array_filter($evaluations, function($e) { return $e['statut'] === 'actif'; })); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-check-circle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des évaluations -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-ul me-2"></i>Liste des Évaluations
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($evaluations)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                            <p class="text-muted mt-2">Aucune évaluation créée pour cette année scolaire</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEvaluationModal">
                                <i class="bi bi-plus-circle me-1"></i>Créer la première évaluation
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Évaluation</th>
                                        <th>Classe</th>
                                        <th>Cours</th>
                                        <th>Période</th>
                                        <th>Date</th>
                                        <th>Coefficient</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($evaluations as $eval): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($eval['nom_evaluation']); ?></strong>
                                                    <?php if ($eval['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($eval['description']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($eval['nom_classe'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($eval['nom_cours'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo ($eval['type_periode'] ?? '') === 'trimestre' ? 'success' : 'info'; ?>">
                                                    <?php echo htmlspecialchars($eval['periode_nom'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo date('d/m/Y', strtotime($eval['date_evaluation'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo $eval['ponderation']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $eval['nb_notes'] > 0 ? 'success' : 'light'; ?>">
                                                    <?php echo $eval['nb_notes']; ?> note(s)
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="notes_entry.php?evaluation_id=<?php echo $eval['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Saisir les notes">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteEvaluation(<?php echo $eval['id']; ?>, '<?php echo htmlspecialchars($eval['nom_evaluation']); ?>')"
                                                            title="Supprimer">
                                                        <i class="bi bi-trash"></i>
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

    <!-- Modal Création Évaluation -->
    <div class="modal fade" id="createEvaluationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle Évaluation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_evaluation">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">Nom de l'évaluation *</label>
                                    <input type="text" class="form-control" id="nom" name="nom" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type_evaluation" class="form-label">Type d'évaluation *</label>
                                    <select class="form-select" id="type_evaluation" name="type_evaluation" required>
                                        <option value="">Sélectionner...</option>
                                        <option value="interro">Interrogation</option>
                                        <option value="devoir">Devoir</option>
                                        <option value="examen">Examen</option>
                                        <option value="projet">Projet</option>
                                        <option value="rattrapage">Rattrapage</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="date_evaluation" class="form-label">Date de l'évaluation *</label>
                                    <input type="date" class="form-control" id="date_evaluation" name="date_evaluation" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="coefficient" class="form-label">Coefficient *</label>
                                    <input type="number" class="form-control" id="coefficient" name="coefficient" 
                                           value="1.00" step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="periode_scolaire_id" class="form-label">Période scolaire *</label>
                                    <select class="form-select" id="periode_scolaire_id" name="periode_scolaire_id" required>
                                        <option value="">Sélectionner...</option>
                                        <?php foreach ($periodes as $periode): ?>
                                            <option value="<?php echo $periode['id']; ?>">
                                                <?php echo htmlspecialchars($periode['nom']); ?> 
                                                (<?php echo $periode['type_periode']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="classe_cours_id" class="form-label">Classe et Cours *</label>
                                    <select class="form-select" id="classe_cours_id" name="classe_cours_id" required>
                                        <option value="">Sélectionner...</option>
                                        <?php if (!empty($classe_cours)): ?>
                                            <?php foreach ($classe_cours as $cc): ?>
                                                <option value="<?php echo $cc['id']; ?>">
                                                    <?php echo htmlspecialchars($cc['nom_classe']); ?> - 
                                                    <?php echo htmlspecialchars($cc['nom_cours']); ?> 
                                                    (<?php echo htmlspecialchars($cc['enseignant_prenom'] . ' ' . $cc['enseignant_nom']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="" disabled>Aucune classe/cours disponible</option>
                                        <?php endif; ?>
                                    </select>
                                    <?php if (empty($classe_cours)): ?>
                                        <div class="form-text text-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Aucune classe/cours trouvée pour l'année <?php echo htmlspecialchars($annee_scolaire['libelle']); ?>.
                                            <br>
                                            <strong>Debug:</strong> 
                                            <?php if (isset($debug_classe_cours) && !empty($debug_classe_cours)): ?>
                                                <?php echo count($debug_classe_cours); ?> classe_cours trouvés sans filtre d'année.
                                                <br>
                                                <small>Années disponibles: 
                                                <?php 
                                                $annees = array_unique(array_merge(
                                                    array_column($debug_classe_cours, 'cc_annee'),
                                                    array_column($debug_classe_cours, 'c_annee')
                                                ));
                                                echo implode(', ', array_filter($annees));
                                                ?>
                                                </small>
                                            <?php else: ?>
                                                Aucun classe_cours trouvé du tout dans cette école.
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer l'évaluation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Confirmation Suppression -->
    <div class="modal fade" id="deleteEvaluationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer l'évaluation <strong id="evalName"></strong> ?</p>
                    <p class="text-danger"><small>Cette action est irréversible et supprimera également toutes les notes associées.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_evaluation">
                        <input type="hidden" name="evaluation_id" id="evalId">
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript personnalisé -->
    <script>
        function deleteEvaluation(evalId, evalName) {
            document.getElementById('evalId').value = evalId;
            document.getElementById('evalName').textContent = evalName;
            new bootstrap.Modal(document.getElementById('deleteEvaluationModal')).show();
        }
        
        // Initialiser la date d'évaluation à aujourd'hui
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_evaluation').value = today;
        });
    </script>
</body>
</html>
