<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$page_title = "Modifier l'École";
$success_message = '';
$error_message = '';

// Récupérer les informations actuelles de l'école
try {
    $school_query = "SELECT * FROM ecoles WHERE id = :ecole_id";
    $stmt = $db->prepare($school_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $school = $stmt->fetch();
    
    if (!$school) {
        $error_message = "Impossible de récupérer les informations de l'école.";
    }
} catch (Exception $e) {
    $error_message = "Erreur lors de la récupération des informations: " . $e->getMessage();
    $school = [];
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des champs requis
        $required_fields = ['nom', 'adresse', 'telephone', 'email', 'directeur_nom'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ '$field' est requis.");
            }
        }
        
        // Préparation des données
        $update_data = [
            'nom' => trim($_POST['nom']),
            'adresse' => trim($_POST['adresse']),
            'telephone' => trim($_POST['telephone']),
            'email' => trim($_POST['email']),
            'directeur_nom' => trim($_POST['directeur_nom']),
            'updated_by' => $_SESSION['user_id'],
            'ecole_id' => $_SESSION['ecole_id']
        ];
        
        // Champs optionnels
        $optional_fields = [
            'sigle', 'description', 'regime', 'devise_principale', 'site_web', 'fax', 'bp',
            'directeur_telephone', 'directeur_email', 'numero_autorisation', 'date_autorisation'
        ];
        
        foreach ($optional_fields as $field) {
            if (isset($_POST[$field])) {
                $update_data[$field] = trim($_POST[$field]);
            }
        }
        
        // Gestion des tableaux (types d'enseignement et langues)
        $type_enseignement = isset($_POST['type_enseignement']) ? implode(',', $_POST['type_enseignement']) : '';
        $langue_enseignement = isset($_POST['langue_enseignement']) ? implode(',', $_POST['langue_enseignement']) : '';
        
        // Construction de la requête SQL dynamique
        $sql_parts = [];
        $params = [];
        
        foreach ($update_data as $field => $value) {
            if ($field !== 'ecole_id') {
                $sql_parts[] = "$field = :$field";
                $params[$field] = $value;
            }
        }
        
        // Ajout des champs spéciaux
        if (!empty($type_enseignement)) {
            $sql_parts[] = "type_enseignement = :type_enseignement";
            $params['type_enseignement'] = $type_enseignement;
        }
        
        if (!empty($langue_enseignement)) {
            $sql_parts[] = "langue_enseignement = :langue_enseignement";
            $params['langue_enseignement'] = $langue_enseignement;
        }
        
        $params['ecole_id'] = $_SESSION['ecole_id'];
        
        $sql = "UPDATE ecoles SET " . implode(', ', $sql_parts) . " WHERE id = :ecole_id";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $success_message = "Les informations de l'école ont été mises à jour avec succès.";
            
            // Recharger les données de l'école
            $stmt = $db->prepare($school_query);
            $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
            $school = $stmt->fetch();
        } else {
            $error_message = "Erreur lors de la mise à jour des informations.";
        }
        
    } catch (Exception $e) {
        $error_message = "Erreur: " . $e->getMessage();
    }
}

// Préparer les données pour l'affichage
$current_data = [];
if ($school) {
    $current_data = [
        'nom' => $school['nom'] ?? '',
        'sigle' => $school['sigle'] ?? '',
        'adresse' => $school['adresse'] ?? '',
        'telephone' => $school['telephone'] ?? '',
        'email' => $school['email'] ?? '',
        'site_web' => $school['site_web'] ?? '',
        'fax' => $school['fax'] ?? '',
        'bp' => $school['bp'] ?? '',
        'regime' => $school['regime'] ?? '',
        'type_enseignement' => !empty($school['type_enseignement']) ? explode(',', $school['type_enseignement']) : [],
        'langue_enseignement' => !empty($school['langue_enseignement']) ? explode(',', $school['langue_enseignement']) : [],
        'devise_principale' => $school['devise_principale'] ?? 'CDF',
        'directeur_nom' => $school['directeur_nom'] ?? '',
        'directeur_telephone' => $school['directeur_telephone'] ?? '',
        'directeur_email' => $school['directeur_email'] ?? '',
        'numero_autorisation' => $school['numero_autorisation'] ?? '',
        'date_autorisation' => $school['date_autorisation'] ?? '',
        'description' => $school['description'] ?? ''
    ];
}
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
        .edit-form {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .required-label::after {
            content: ' *';
            color: #dc3545;
        }
        
        .form-control:focus {
            border-color: #0077b6;
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 182, 0.25);
        }
        
        .btn-save {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            border: none;
            color: white;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 119, 182, 0.3);
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
                <h1><i class="bi bi-pencil me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Modifiez les informations de votre établissement</p>
            </div>
            
            <div class="topbar-actions">
                <a href="school.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($school): ?>
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="card edit-form">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-building me-2"></i>Modifier les informations de l'école
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="editSchoolForm">
                                    <!-- Informations générales -->
                                    <div class="form-section">
                                        <h6 class="mb-3">
                                            <i class="bi bi-info-circle me-2"></i>Informations générales de l'établissement
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label for="nom" class="form-label required-label">Nom complet de l'école</label>
                                                    <input type="text" class="form-control" id="nom" name="nom" 
                                                           value="<?php echo htmlspecialchars($current_data['nom']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="sigle" class="form-label">Sigle</label>
                                                    <input type="text" class="form-control" id="sigle" name="sigle" 
                                                           value="<?php echo htmlspecialchars($current_data['sigle']); ?>" 
                                                           maxlength="10">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description de l'établissement</label>
                                            <textarea class="form-control" id="description" name="description" 
                                                      rows="3"><?php echo htmlspecialchars($current_data['description']); ?></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="regime" class="form-label">Régime</label>
                                                    <select class="form-control" id="regime" name="regime">
                                                        <option value="">Choisir...</option>
                                                        <option value="public" <?php echo $current_data['regime'] === 'public' ? 'selected' : ''; ?>>Public</option>
                                                        <option value="privé" <?php echo $current_data['regime'] === 'privé' ? 'selected' : ''; ?>>Privé</option>
                                                        <option value="conventionné" <?php echo $current_data['regime'] === 'conventionné' ? 'selected' : ''; ?>>Conventionné</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="devise_principale" class="form-label">Devise principale</label>
                                                    <select class="form-control" id="devise_principale" name="devise_principale">
                                                        <option value="CDF" <?php echo $current_data['devise_principale'] === 'CDF' ? 'selected' : ''; ?>>Franc Congolais (CDF)</option>
                                                        <option value="USD" <?php echo $current_data['devise_principale'] === 'USD' ? 'selected' : ''; ?>>Dollar Américain (USD)</option>
                                                        <option value="EUR" <?php echo $current_data['devise_principale'] === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Coordonnées -->
                                    <div class="form-section">
                                        <h6 class="mb-3">
                                            <i class="bi bi-geo-alt me-2"></i>Coordonnées de l'établissement
                                        </h6>
                                        <div class="mb-3">
                                            <label for="adresse" class="form-label required-label">Adresse complète</label>
                                            <textarea class="form-control" id="adresse" name="adresse" rows="2" required><?php echo htmlspecialchars($current_data['adresse']); ?></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="telephone" class="form-label required-label">Téléphone principal</label>
                                                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                                                           value="<?php echo htmlspecialchars($current_data['telephone']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="fax" class="form-label">Fax</label>
                                                    <input type="tel" class="form-control" id="fax" name="fax" 
                                                           value="<?php echo htmlspecialchars($current_data['fax']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="bp" class="form-label">Boîte postale</label>
                                                    <input type="text" class="form-control" id="bp" name="bp" 
                                                           value="<?php echo htmlspecialchars($current_data['bp']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label required-label">Email principal</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?php echo htmlspecialchars($current_data['email']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="site_web" class="form-label">Site web</label>
                                                    <input type="url" class="form-control" id="site_web" name="site_web" 
                                                           value="<?php echo htmlspecialchars($current_data['site_web']); ?>" 
                                                           placeholder="https://www.exemple.cd">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Configuration pédagogique -->
                                    <div class="form-section">
                                        <h6 class="mb-3">
                                            <i class="bi bi-book me-2"></i>Configuration pédagogique
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Types d'enseignement proposés</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="type_enseignement[]" value="maternelle" id="maternelle"
                                                               <?php echo in_array('maternelle', $current_data['type_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="maternelle">Maternelle</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="type_enseignement[]" value="primaire" id="primaire"
                                                               <?php echo in_array('primaire', $current_data['type_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="primaire">Primaire</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="type_enseignement[]" value="secondaire" id="secondaire"
                                                               <?php echo in_array('secondaire', $current_data['type_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="secondaire">Secondaire</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="type_enseignement[]" value="technique" id="technique"
                                                               <?php echo in_array('technique', $current_data['type_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="technique">Technique</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="type_enseignement[]" value="professionnel" id="professionnel"
                                                               <?php echo in_array('professionnel', $current_data['type_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="professionnel">Professionnel</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="type_enseignement[]" value="université" id="université"
                                                               <?php echo in_array('université', $current_data['type_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="université">Université</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Langues d'enseignement</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="langue_enseignement[]" value="français" id="français"
                                                               <?php echo in_array('français', $current_data['langue_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="français">Français</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="langue_enseignement[]" value="anglais" id="anglais"
                                                               <?php echo in_array('anglais', $current_data['langue_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="anglais">Anglais</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="langue_enseignement[]" value="lingala" id="lingala"
                                                               <?php echo in_array('lingala', $current_data['langue_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="lingala">Lingala</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="langue_enseignement[]" value="kikongo" id="kikongo"
                                                               <?php echo in_array('kikongo', $current_data['langue_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="kikongo">Kikongo</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="langue_enseignement[]" value="tshiluba" id="tshiluba"
                                                               <?php echo in_array('tshiluba', $current_data['langue_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="tshiluba">Tshiluba</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="langue_enseignement[]" value="swahili" id="swahili"
                                                               <?php echo in_array('swahili', $current_data['langue_enseignement']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="swahili">Swahili</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Direction -->
                                    <div class="form-section">
                                        <h6 class="mb-3">
                                            <i class="bi bi-person-badge me-2"></i>Informations du directeur
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="directeur_nom" class="form-label required-label">Nom complet du directeur</label>
                                                    <input type="text" class="form-control" id="directeur_nom" name="directeur_nom" 
                                                           value="<?php echo htmlspecialchars($current_data['directeur_nom']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="directeur_telephone" class="form-label">Téléphone du directeur</label>
                                                    <input type="tel" class="form-control" id="directeur_telephone" name="directeur_telephone" 
                                                           value="<?php echo htmlspecialchars($current_data['directeur_telephone']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="directeur_email" class="form-label">Email du directeur</label>
                                            <input type="email" class="form-control" id="directeur_email" name="directeur_email" 
                                                   value="<?php echo htmlspecialchars($current_data['directeur_email']); ?>">
                                        </div>
                                    </div>

                                    <!-- Autorisation officielle -->
                                    <div class="form-section">
                                        <h6 class="mb-3">
                                            <i class="bi bi-file-earmark-text me-2"></i>Autorisation officielle (optionnel)
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="numero_autorisation" class="form-label">Numéro d'autorisation</label>
                                                    <input type="text" class="form-control" id="numero_autorisation" name="numero_autorisation" 
                                                           value="<?php echo htmlspecialchars($current_data['numero_autorisation']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="date_autorisation" class="form-label">Date d'autorisation</label>
                                                    <input type="date" class="form-control" id="date_autorisation" name="date_autorisation" 
                                                           value="<?php echo htmlspecialchars($current_data['date_autorisation']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center mt-4">
                                        <button type="submit" class="btn btn-save btn-lg px-5">
                                            <i class="bi bi-check-circle me-2"></i>Enregistrer les modifications
                                        </button>
                                        
                                        <a href="school.php" class="btn btn-outline-secondary btn-lg px-4 ms-3">
                                            <i class="bi bi-x-circle me-2"></i>Annuler
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Erreur</h5>
                            </div>
                            <div class="card-body text-center">
                                <i class="bi bi-exclamation-triangle text-warning display-1 mb-3"></i>
                                <h4>Impossible de charger les informations</h4>
                                <p class="text-muted"><?php echo htmlspecialchars($error_message); ?></p>
                                <a href="school.php" class="btn btn-primary">
                                    <i class="bi bi-arrow-left me-2"></i>Retour à la Configuration
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        // Validation côté client
        document.getElementById('editSchoolForm').addEventListener('submit', function(e) {
            // Vérifier qu'au moins un type d'enseignement est sélectionné
            const typeEnseignement = document.querySelectorAll('input[name="type_enseignement[]"]:checked');
            if (typeEnseignement.length === 0) {
                e.preventDefault();
                alert('Veuillez sélectionner au moins un type d\'enseignement.');
                return false;
            }
            
            // Vérifier qu'au moins une langue d'enseignement est sélectionnée
            const langueEnseignement = document.querySelectorAll('input[name="langue_enseignement[]"]:checked');
            if (langueEnseignement.length === 0) {
                e.preventDefault();
                alert('Veuillez sélectionner au moins une langue d\'enseignement.');
                return false;
            }
        });
        
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('show')) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            });
        }, 5000);
    </script>
</body>
</html>
