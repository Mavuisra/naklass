<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

// Récupérer les classes et enseignants pour les listes déroulantes
try {
    // Récupérer les classes
    $classes_query = "SELECT id, nom_classe, niveau FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY niveau, nom_classe";
    $classes_stmt = $db->prepare($classes_query);
    $classes_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classes = $classes_stmt->fetchAll();
    
    // Récupérer les enseignants
    $enseignants_query = "SELECT id, nom, prenom FROM enseignants WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom, prenom";
    $enseignants_stmt = $db->prepare($enseignants_query);
    $enseignants_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $enseignants = $enseignants_stmt->fetchAll();
    
} catch (Exception $e) {
    $errors[] = "Erreur lors de la récupération des données: " . $e->getMessage();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom_cours' => sanitize($_POST['nom_cours'] ?? ''),
        'code_cours' => sanitize($_POST['code_cours'] ?? ''),
        'classe_id' => (int)($_POST['classe_id'] ?? 0),
        'enseignant_id' => (int)($_POST['enseignant_id'] ?? 0),
        'coefficient' => !empty($_POST['coefficient']) ? floatval($_POST['coefficient']) : 1.00
    ];
    
    // Validation des données
    if (empty($data['nom_cours'])) {
        $errors[] = "Le nom de la matière est obligatoire.";
    }
    
    if (empty($data['code_cours'])) {
        $errors[] = "Le code de la matière est obligatoire.";
    }
    
    if ($data['classe_id'] <= 0) {
        $errors[] = "Veuillez sélectionner une classe.";
    }
    
    if ($data['enseignant_id'] <= 0) {
        $errors[] = "Veuillez sélectionner un enseignant.";
    }
    
    if ($data['coefficient'] <= 0 || $data['coefficient'] > 10) {
        $errors[] = "Le coefficient doit être compris entre 0.1 et 10.";
    }
    
    // Vérifier l'unicité du code de cours dans l'école
    if (!empty($data['code_cours'])) {
        $check_query = "SELECT id FROM cours WHERE code_cours = :code_cours AND ecole_id = :ecole_id";
        $stmt = $db->prepare($check_query);
        $stmt->execute(['code_cours' => $data['code_cours'], 'ecole_id' => $_SESSION['ecole_id']]);
        if ($stmt->fetch()) {
            $errors[] = "Une matière avec ce code existe déjà dans votre établissement.";
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Insérer la nouvelle matière
            $insert_query = "INSERT INTO cours (
                ecole_id, code_cours, nom_cours, coefficient, created_by
            ) VALUES (
                :ecole_id, :code_cours, :nom_cours, :coefficient, :created_by
            )";
            
            $stmt = $db->prepare($insert_query);
            $insert_data = [
                'ecole_id' => $_SESSION['ecole_id'],
                'code_cours' => $data['code_cours'],
                'nom_cours' => $data['nom_cours'],
                'coefficient' => $data['coefficient'],
                'created_by' => $_SESSION['user_id']
            ];
            
            $stmt->execute($insert_data);
            $cours_id = $db->lastInsertId();
            
            // Créer l'affectation classe-matière avec enseignant
            $affectation_query = "INSERT INTO classe_cours (classe_id, cours_id, enseignant_id, annee_scolaire, statut, created_by) 
                                 VALUES (:classe_id, :cours_id, :enseignant_id, :annee_scolaire, 'actif', :created_by)";
            $stmt = $db->prepare($affectation_query);
            $stmt->execute([
                'classe_id' => $data['classe_id'],
                'cours_id' => $cours_id,
                'enseignant_id' => $data['enseignant_id'],
                'annee_scolaire' => date('Y') . '-' . (date('Y') + 1),
                'created_by' => $_SESSION['user_id']
            ]);
            
            $db->commit();
            
            setFlashMessage('success', "La matière '{$data['nom_cours']}' a été créée avec succès et affectée à la classe et à l'enseignant.");
            redirect("index.php");
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Erreur lors de la création de la matière: " . $e->getMessage();
        }
    }
}

$page_title = "Créer une Matière";
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
    <style>
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 600;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
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
                <h1><i class="bi bi-plus-circle me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Créez une nouvelle matière avec les informations essentielles</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </header>
        
        <!-- Contenu principal -->
        <div class="content-area">
            <!-- Affichage des erreurs -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Formulaire simplifié -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="form-container p-4">
                        <form method="POST" novalidate>
                            <h5 class="section-title mb-4">
                                <i class="bi bi-info-circle me-2"></i>Informations de la Matière
                            </h5>
                            
                            <div class="row">
                                <!-- Nom de la matière -->
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="nom_cours" class="form-label">
                                            Nom de la Matière <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="nom_cours" name="nom_cours" 
                                               value="<?php echo htmlspecialchars($_POST['nom_cours'] ?? ''); ?>" 
                                               placeholder="Ex: Mathématiques, Français, Histoire..." required>
                                        <div class="form-text">Nom complet de la matière</div>
                                    </div>
                                </div>
                                
                                <!-- Code de la matière -->
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="code_cours" class="form-label">
                                            Code <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="code_cours" name="code_cours" 
                                               value="<?php echo htmlspecialchars($_POST['code_cours'] ?? ''); ?>" 
                                               placeholder="Ex: MATH, FR, HIST" 
                                               style="text-transform: uppercase;" required>
                                        <div class="form-text">Code unique de la matière</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Classe -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="classe_id" class="form-label">
                                            Classe <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="classe_id" name="classe_id" required>
                                            <option value="">Sélectionnez une classe</option>
                                            <?php foreach ($classes as $classe): ?>
                                                <option value="<?php echo $classe['id']; ?>" 
                                                        <?php echo ($_POST['classe_id'] ?? '') == $classe['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($classe['nom_classe'] . ' (' . $classe['niveau'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Classe à laquelle cette matière sera enseignée</div>
                                    </div>
                                </div>
                                
                                <!-- Enseignant -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="enseignant_id" class="form-label">
                                            Enseignant <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="enseignant_id" name="enseignant_id" required>
                                            <option value="">Sélectionnez un enseignant</option>
                                            <?php foreach ($enseignants as $enseignant): ?>
                                                <option value="<?php echo $enseignant['id']; ?>" 
                                                        <?php echo ($_POST['enseignant_id'] ?? '') == $enseignant['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Enseignant responsable de cette matière</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Coefficient -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-4">
                                        <label for="coefficient" class="form-label">Coefficient</label>
                                        <input type="number" class="form-control" id="coefficient" name="coefficient" 
                                               value="<?php echo htmlspecialchars($_POST['coefficient'] ?? '1.0'); ?>" 
                                               min="0.1" max="10" step="0.1" required>
                                        <div class="form-text">Poids de la matière dans le calcul de la moyenne</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-2"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Créer la Matière
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        // Conversion automatique du code en majuscules
        document.getElementById('code_cours').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>

