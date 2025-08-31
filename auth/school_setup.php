<?php
/**
 * Configuration initiale d'une nouvelle école
 * Cette page doit être complétée avant l'accès au système
 */
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('login.php');
}

// Vérifier si l'utilisateur a une école
if (!isset($_SESSION['ecole_id'])) {
    setFlashMessage('error', 'Aucune école associée à votre compte.');
    redirect('login.php');
}

// Vérifier si l'utilisateur a les droits admin
if ($_SESSION['user_role'] !== 'admin') {
    setFlashMessage('error', 'Vous devez être administrateur pour configurer l\'école.');
    redirect('dashboard.php');
}

// Vérifier si la configuration est déjà complète et validée par le super admin
$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT * FROM ecoles WHERE id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $ecole = $stmt->fetch();
    
    if (!$ecole) {
        setFlashMessage('error', 'École non trouvée.');
        redirect('dashboard.php');
    }
    
    // Vérifier si l'école est configurée et validée
    $config_complete = false;
    $super_admin_validated = false;
    
    if (array_key_exists('configuration_complete', $ecole)) {
        $config_complete = $ecole['configuration_complete'];
    }
    if (array_key_exists('super_admin_validated', $ecole)) {
        $super_admin_validated = $ecole['super_admin_validated'];
    }
    
    // Si l'école est configurée et validée, rediriger vers le dashboard
    if ($config_complete && $super_admin_validated) {
        setFlashMessage('info', 'La configuration de votre école est déjà complète et validée.');
        redirect('dashboard.php');
    }
    
    // Si l'école est configurée mais pas encore validée par le super admin
    if ($config_complete && !$super_admin_validated) {
        setFlashMessage('info', 'Votre école est configurée et en attente de validation par le super administrateur.');
        redirect('dashboard.php');
    }
    
} catch (Exception $e) {
    // En cas d'erreur, continuer sans redirection pour éviter les boucles
    error_log('Erreur lors de la vérification de l\'école: ' . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $nom = sanitize($_POST['nom_ecole '] ?? '');
    $sigle = sanitize($_POST['sigle'] ?? '');
    $adresse = sanitize($_POST['adresse'] ?? '');
    $telephone = sanitize($_POST['telephone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $site_web = sanitize($_POST['site_web'] ?? '');
    $fax = sanitize($_POST['fax'] ?? '');
    $bp = sanitize($_POST['bp'] ?? '');
    $regime = sanitize($_POST['regime'] ?? '');
    $type_enseignement = $_POST['type_enseignement'] ?? [];
    $langue_enseignement = $_POST['langue_enseignement'] ?? [];
    $devise_principale = sanitize($_POST['devise_principale'] ?? '');
    $directeur_nom = sanitize($_POST['directeur_nom'] ?? '');
    $directeur_telephone = sanitize($_POST['directeur_telephone'] ?? '');
    $directeur_email = sanitize($_POST['directeur_email'] ?? '');
    $numero_autorisation = sanitize($_POST['numero_autorisation'] ?? '');
    $date_autorisation = $_POST['date_autorisation'] ?? '';
    $description_etablissement = sanitize($_POST['description_etablissement'] ?? '');
    
    // Validation des champs obligatoires
    if (empty($nom) || empty($sigle) || empty($adresse) || empty($telephone) || 
        empty($email) || empty($regime) || empty($type_enseignement) || 
        empty($langue_enseignement) || empty($devise_principale) || 
        empty($directeur_nom) || empty($directeur_telephone)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || 
              (!empty($directeur_email) && !filter_var($directeur_email, FILTER_VALIDATE_EMAIL))) {
        $error = 'Veuillez entrer des adresses email valides.';
    } else {
        try {
            // Mise à jour des informations de l'école
            $updateQuery = "UPDATE ecoles SET 
                            nom_ecole = :nom,
                            sigle = :sigle,
                            adresse = :adresse,
                            telephone = :telephone,
                            email = :email,
                            site_web = :site_web,
                            fax = :fax,
                            bp = :bp,
                            regime = :regime,
                            type_enseignement = :type_enseignement,
                            langue_enseignement = :langue_enseignement,
                            devise_principale = :devise_principale,
                            directeur_nom = :directeur_nom,
                            directeur_telephone = :directeur_telephone,
                            directeur_email = :directeur_email,
                            numero_autorisation = :numero_autorisation,
                            date_autorisation = :date_autorisation,
                            description_etablissement = :description_etablissement,
                            configuration_complete = TRUE,
                            date_configuration = CURDATE(),
                            updated_at = NOW(),
                            updated_by = :updated_by
                            WHERE id = :ecole_id";
            
            $stmt = $db->prepare($updateQuery);
            $result = $stmt->execute([
                'nom_ecole' => $nom,
                'sigle' => $sigle,
                'adresse' => $adresse,
                'telephone' => $telephone,
                'email' => $email,
                'site_web' => $site_web ?: null,
                'fax' => $fax ?: null,
                'bp' => $bp ?: null,
                'regime' => $regime,
                'type_enseignement' => implode(',', $type_enseignement),
                'langue_enseignement' => implode(',', $langue_enseignement),
                'devise_principale' => $devise_principale,
                'directeur_nom' => $directeur_nom,
                'directeur_telephone' => $directeur_telephone,
                'directeur_email' => $directeur_email ?: null,
                'numero_autorisation' => $numero_autorisation ?: null,
                'date_autorisation' => $date_autorisation ?: null,
                'description_etablissement' => $description_etablissement ?: null,
                'updated_by' => $_SESSION['user_id'],
                'ecole_id' => $_SESSION['ecole_id']
            ]);
            
            if ($result) {
                // Mettre à jour la session avec le nouveau nom de l'école
                $_SESSION['ecole_nom'] = $nom;
                
                // Log de l'action
                logUserAction('SCHOOL_SETUP', 'Configuration initiale de l\'école complétée');
                
                // Créer une notification pour le super admin
                $query = "INSERT INTO super_admin_notifications (type, ecole_id, message) 
                         VALUES ('school_validation', :ecole_id, :message)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'ecole_id' => $_SESSION['ecole_id'],
                    'message' => "École {$nom} configurée et en attente de validation"
                ]);
                
                // Enregistrer dans l'historique
                $query = "INSERT INTO school_validation_history (ecole_id, action, performed_by, notes) 
                         VALUES (:ecole_id, 'configured', :admin_id, 'Configuration de l\'école complétée')";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'ecole_id' => $_SESSION['ecole_id'],
                    'admin_id' => $_SESSION['user_id']
                ]);
                
                setFlashMessage('success', 'Configuration de votre école complétée avec succès ! 
                               Votre école est maintenant en attente de validation par le super administrateur. 
                               Vous recevrez une notification dès qu\'elle sera validée.');
                redirect('dashboard.php');
            } else {
                $error = 'Erreur lors de la sauvegarde. Veuillez réessayer.';
            }
        } catch (Exception $e) {
            $error = 'Erreur système : ' . $e->getMessage();
        }
    }
}

// Préremplir les données existantes si disponibles
if ($ecole) {
    $current_data = [
        'nom' => $ecole['nom'] ?? '',
        'sigle' => $ecole['sigle'] ?? '',
        'adresse' => $ecole['adresse'] ?? '',
        'telephone' => $ecole['telephone'] ?? '',
        'email' => $ecole['email'] ?? '',
        'site_web' => $ecole['site_web'] ?? '',
        'fax' => $ecole['fax'] ?? '',
        'bp' => $ecole['bp'] ?? '',
        'regime' => $ecole['regime'] ?? '',
        'type_enseignement' => !empty($ecole['type_enseignement']) ? explode(',', $ecole['type_enseignement']) : [],
        'langue_enseignement' => !empty($ecole['langue_enseignement']) ? explode(',', $ecole['langue_enseignement']) : [],
        'devise_principale' => $ecole['devise_principale'] ?? 'CDF',
        'directeur_nom' => $ecole['directeur_nom'] ?? '',
        'directeur_telephone' => $ecole['directeur_telephone'] ?? '',
        'directeur_email' => $ecole['directeur_email'] ?? '',
        'numero_autorisation' => $ecole['numero_autorisation'] ?? '',
        'date_autorisation' => $ecole['date_autorisation'] ?? '',
        'description_etablissement' => $ecole['description_etablissement'] ?? ''
    ];
} else {
    $current_data = array_fill_keys([
        'nom', 'sigle', 'adresse', 'telephone', 'email', 'site_web', 'fax', 'bp',
        'regime', 'devise_principale', 'directeur_nom', 'directeur_telephone',
        'directeur_email', 'numero_autorisation', 'date_autorisation', 'description_etablissement'
    ], '');
    $current_data['type_enseignement'] = [];
    $current_data['langue_enseignement'] = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration de l'École - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/auth.css" rel="stylesheet">
    <style>
        .setup-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .setup-header {
            text-align: center;
            margin-bottom: 2rem;
            color: #2c3e50;
        }
        .setup-step {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .required-label::after {
            content: ' *';
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="setup-container">
            <div class="setup-header">
                <div class="mb-3">
                    <i class="bi bi-mortarboard-fill text-primary" style="font-size: 3rem;"></i>
                </div>
                <h2>Configuration de votre École</h2>
                <p class="lead text-muted">
                    Bienvenue dans Naklass ! Pour commencer à utiliser le système, 
                    veuillez compléter les informations de votre établissement.
                </p>
                <?php if (!array_key_exists('configuration_complete', $ecole ?: [])): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Première installation détectée :</strong> Il semble que ce soit votre première utilisation du système multi-écoles. 
                    Si vous rencontrez des erreurs, exécutez d'abord le script de mise à jour : 
                    <a href="../setup_school_management.php" class="alert-link">setup_school_management.php</a>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="schoolSetupForm">
                <!-- Informations générales -->
                <div class="setup-step">
                    <h4 class="mb-3">
                        <i class="bi bi-building me-2"></i>Informations générales de l'établissement
                    </h4>
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
                                <label for="sigle" class="form-label required-label">Sigle</label>
                                <input type="text" class="form-control" id="sigle" name="sigle" 
                                       value="<?php echo htmlspecialchars($current_data['sigle']); ?>" 
                                       maxlength="10" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description_etablissement" class="form-label">Description de l'établissement</label>
                        <textarea class="form-control" id="description_etablissement" name="description_etablissement" 
                                  rows="3"><?php echo htmlspecialchars($current_data['description_etablissement']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="regime" class="form-label required-label">Régime</label>
                                <select class="form-control" id="regime" name="regime" required>
                                    <option value="">Choisir...</option>
                                    <option value="public" <?php echo $current_data['regime'] === 'public' ? 'selected' : ''; ?>>Public</option>
                                    <option value="privé" <?php echo $current_data['regime'] === 'privé' ? 'selected' : ''; ?>>Privé</option>
                                    <option value="conventionné" <?php echo $current_data['regime'] === 'conventionné' ? 'selected' : ''; ?>>Conventionné</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="devise_principale" class="form-label required-label">Devise principale</label>
                                <select class="form-control" id="devise_principale" name="devise_principale" required>
                                    <option value="CDF" <?php echo $current_data['devise_principale'] === 'CDF' ? 'selected' : ''; ?>>Franc Congolais (CDF)</option>
                                    <option value="USD" <?php echo $current_data['devise_principale'] === 'USD' ? 'selected' : ''; ?>>Dollar Américain (USD)</option>
                                    <option value="EUR" <?php echo $current_data['devise_principale'] === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Coordonnées -->
                <div class="setup-step">
                    <h4 class="mb-3">
                        <i class="bi bi-geo-alt me-2"></i>Coordonnées de l'établissement
                    </h4>
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
                <div class="setup-step">
                    <h4 class="mb-3">
                        <i class="bi bi-book me-2"></i>Configuration pédagogique
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label required-label">Types d'enseignement proposés</label>
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
                                <label class="form-label required-label">Langues d'enseignement</label>
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
                <div class="setup-step">
                    <h4 class="mb-3">
                        <i class="bi bi-person-badge me-2"></i>Informations du directeur
                    </h4>
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
                                <label for="directeur_telephone" class="form-label required-label">Téléphone du directeur</label>
                                <input type="tel" class="form-control" id="directeur_telephone" name="directeur_telephone" 
                                       value="<?php echo htmlspecialchars($current_data['directeur_telephone']); ?>" required>
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
                <div class="setup-step">
                    <h4 class="mb-3">
                        <i class="bi bi-file-earmark-text me-2"></i>Autorisation officielle (optionnel)
                    </h4>
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
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-check-circle me-2"></i>Terminer la configuration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation côté client
        document.getElementById('schoolSetupForm').addEventListener('submit', function(e) {
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
