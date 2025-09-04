<?php
/**
 * Configuration initiale d'une nouvelle école
 * Cette page peut être utilisée par les visiteurs pour créer une école
 * ou par les administrateurs pour configurer leur école existante
 */
require_once '../config/database.php';
require_once '../includes/functions.php';

// Démarrer la session si pas encore démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détecter si c'est un visiteur
$is_visitor = isset($_GET['visitor']) && $_GET['visitor'] == '1';
$visitor_id = null;

if ($is_visitor) {
    // Détecter le visiteur
    if (!isset($_COOKIE['naklass_visitor_id'])) {
        $visitor_id = 'visitor_' . time() . '_' . rand(1000, 9999);
        setcookie('naklass_visitor_id', $visitor_id, time() + (86400 * 365), '/');
    } else {
        $visitor_id = $_COOKIE['naklass_visitor_id'];
    }
} else {
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
}

// Vérifier si la configuration est déjà complète et validée par le super admin
$database = new Database();
$db = $database->getConnection();
$ecole = null;

if (!$is_visitor) {
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
}

$error = '';
$success = '';
$school_created = false;
$admin_credentials = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $nom = sanitize($_POST['nom'] ?? '');
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
    $directeur_prenom = sanitize($_POST['directeur_prenom'] ?? '');
    $directeur_telephone = sanitize($_POST['directeur_telephone'] ?? '');
    $directeur_email = sanitize($_POST['directeur_email'] ?? '');
    $numero_autorisation = sanitize($_POST['numero_autorisation'] ?? '');
    $date_autorisation = $_POST['date_autorisation'] ?? '';
    $description_etablissement = sanitize($_POST['description_etablissement'] ?? '');
    
    // Pour les visiteurs, ajouter le mot de passe administrateur
    $admin_password = '';
    if ($is_visitor) {
        $admin_password = sanitize($_POST['admin_password'] ?? '');
    }
    
    // Validation des champs obligatoires
    $required_fields = [
        'nom' => 'Nom de l\'école',
        'sigle' => 'Sigle',
        'adresse' => 'Adresse',
        'telephone' => 'Téléphone',
        'email' => 'Email',
        'regime' => 'Régime',
        'type_enseignement' => 'Type d\'enseignement',
        'langue_enseignement' => 'Langue d\'enseignement',
        'devise_principale' => 'Devise principale',
        'directeur_nom' => 'Nom du directeur',
        'directeur_telephone' => 'Téléphone du directeur'
    ];
    
    // Pour les visiteurs, ajouter des champs supplémentaires
    if ($is_visitor) {
        $required_fields['directeur_prenom'] = 'Prénom du directeur';
        $required_fields['directeur_email'] = 'Email du directeur';
        $required_fields['admin_password'] = 'Mot de passe administrateur';
    }
    
    foreach ($required_fields as $field => $label) {
        if ($field === 'type_enseignement' || $field === 'langue_enseignement') {
            if (empty($$field)) {
                $error = "Le champ '$label' est obligatoire.";
                break;
            }
        } elseif ($field === 'admin_password') {
            if (strlen($$field) < 6) {
                $error = "Le mot de passe doit contenir au moins 6 caractères.";
                break;
            }
        } else {
            if (empty($$field)) {
                $error = "Le champ '$label' est obligatoire.";
                break;
            }
        }
    }
    
    // Validation des emails
    if (empty($error)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "L'adresse email de l'école n'est pas valide.";
        } elseif (!empty($directeur_email) && !filter_var($directeur_email, FILTER_VALIDATE_EMAIL)) {
            $error = "L'adresse email du directeur n'est pas valide.";
        }
    }
    
    // Vérifier l'unicité des emails pour les visiteurs
    if (empty($error) && $is_visitor) {
        try {
            // Vérifier si l'email de l'école existe déjà
            $check_query = "SELECT COUNT(*) FROM ecoles WHERE email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute(['email' => $email]);
            if ($check_stmt->fetchColumn() > 0) {
                $error = "Une école avec cet email existe déjà.";
            }
            
            // Vérifier si l'email du directeur existe déjà comme utilisateur
            if (empty($error) && !empty($directeur_email)) {
                $check_user_query = "SELECT COUNT(*) FROM utilisateurs WHERE email = :email";
                $check_user_stmt = $db->prepare($check_user_query);
                $check_user_stmt->execute(['email' => $directeur_email]);
                if ($check_user_stmt->fetchColumn() > 0) {
                    $error = "Un utilisateur avec cet email existe déjà.";
                }
            }
        } catch (Exception $e) {
            $error = "Erreur lors de la vérification des emails : " . $e->getMessage();
        }
    }
    
    if (empty($error)) {
        try {
            $db->beginTransaction();
            
            if ($is_visitor) {
                // Créer une nouvelle école pour le visiteur
                $code_ecole = 'ECOLE_' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nom), 0, 8)) . '_' . time();
                
                $insert_ecole_query = "INSERT INTO ecoles (
                    nom_ecole, code_ecole, sigle, adresse, telephone, email, site_web, fax, bp,
                    regime, type_enseignement, langue_enseignement, devise_principale,
                    directeur_nom, directeur_telephone, directeur_email,
                    numero_autorisation, date_autorisation, description_etablissement,
                    type_etablissement, pays, fuseau_horaire, validation_status,
                    created_by_visitor, date_creation_ecole, configuration_complete, date_configuration
                ) VALUES (
                    :nom_ecole, :code_ecole, :sigle, :adresse, :telephone, :email, :site_web, :fax, :bp,
                    :regime, :type_enseignement, :langue_enseignement, :devise_principale,
                    :directeur_nom, :directeur_telephone, :directeur_email,
                    :numero_autorisation, :date_autorisation, :description_etablissement,
                    'mixte', 'RD Congo', 'Africa/Kinshasa', 'approved',
                    :visitor_id, NOW(), TRUE, NOW()
                )";
                
                $stmt = $db->prepare($insert_ecole_query);
                $result = $stmt->execute([
                    'nom_ecole' => $nom,
                    'code_ecole' => $code_ecole,
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
                    'directeur_email' => $directeur_email,
                    'numero_autorisation' => $numero_autorisation ?: null,
                    'date_autorisation' => $date_autorisation ?: null,
                    'description_etablissement' => $description_etablissement ?: null,
                    'visitor_id' => $visitor_id
                ]);
                
                $ecole_id = $db->lastInsertId();
                
                // Créer le compte administrateur
                $admin_username = 'admin_' . strtolower(substr(preg_replace('/[^A-Za-z0-9]/', '', $nom), 0, 6));
                $admin_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                
                $insert_admin_query = "INSERT INTO utilisateurs (
                    ecole_id, role_id, nom, prenom, email, telephone, mot_de_passe_hash, actif
                ) VALUES (
                    :ecole_id, 1, :nom, :prenom, :email, :telephone, :mot_de_passe_hash, 1
                )";
                
                $admin_stmt = $db->prepare($insert_admin_query);
                $admin_stmt->execute([
                    'ecole_id' => $ecole_id,
                    'nom' => $directeur_nom,
                    'prenom' => $directeur_prenom,
                    'email' => $directeur_email,
                    'telephone' => $directeur_telephone,
                    'mot_de_passe_hash' => $admin_password_hash
                ]);
                
                $admin_id = $db->lastInsertId();
                
                // Créer les niveaux par défaut
                $niveaux_default = [
                    ['nom' => 'Maternelle', 'description' => 'Petite section, Moyenne section, Grande section', 'ordre' => 1],
                    ['nom' => 'Primaire', 'description' => 'CP, CE1, CE2, CM1, CM2', 'ordre' => 2],
                    ['nom' => 'Collège', 'description' => '6ème, 5ème, 4ème, 3ème', 'ordre' => 3],
                    ['nom' => 'Lycée', 'description' => 'Seconde, Première, Terminale', 'ordre' => 4]
                ];
                
                foreach ($niveaux_default as $niveau) {
                    $insert_niveau_query = "INSERT INTO niveaux (ecole_id, nom, description, ordre) VALUES (:ecole_id, :nom, :description, :ordre)";
                    $niveau_stmt = $db->prepare($insert_niveau_query);
                    $niveau_stmt->execute([
                        'ecole_id' => $ecole_id,
                        'nom' => $niveau['nom'],
                        'description' => $niveau['description'],
                        'ordre' => $niveau['ordre']
                    ]);
                }
                
                // Préparer les identifiants pour l'email
                $admin_credentials = [
                    'nom_utilisateur' => $admin_username,
                    'mot_de_passe' => $admin_password,
                    'email' => $directeur_email,
                    'nom_ecole' => $nom,
                    'code_ecole' => $code_ecole,
                    'ecole_id' => $ecole_id
                ];
                
            } else {
                // Mise à jour des informations de l'école existante
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
                    'nom' => $nom,
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
            }
            
            if ($result) {
                $db->commit();
                
                if ($is_visitor) {
                    // Envoyer l'email avec les identifiants pour les visiteurs
                    try {
                        require_once '../includes/EmailManager.php';
                        $emailManager = new EmailManager();
                        
                        if ($emailManager->sendSchoolCreationWithCredentials($admin_credentials)) {
                            $success = "✅ École créée avec succès !";
                            $success .= "✅ Compte administrateur créé !";
                            $success .= "✅ Identifiants envoyés par email !";
                            $school_created = true;
                            
                            // Marquer le cookie pour indiquer qu'une école a été créée
                            setcookie('naklass_ecole_created', 'true', time() + (86400 * 365), '/');
                        } else {
                            $success = "✅ École créée avec succès !";
                            $success .= "✅ Compte administrateur créé !";
                            $success .= "⚠️ Problème d'envoi d'email, mais vos identifiants sont affichés ci-dessous.";
                            $school_created = true;
                        }
                    } catch (Exception $email_error) {
                        $success = "✅ École créée avec succès !";
                        $success .= "✅ Compte administrateur créé !";
                        $success .= "⚠️ Problème d'envoi d'email : " . $email_error->getMessage();
                        $school_created = true;
                    }
                } else {
                    // Log de l'action pour les utilisateurs connectés
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
                }
            } else {
                $db->rollback();
                $error = 'Erreur lors de la sauvegarde. Veuillez réessayer.';
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Erreur système : ' . $e->getMessage();
        }
    }
}

// Préremplir les données existantes si disponibles
if ($ecole) {
    $current_data = [
        'nom' => $ecole['nom_ecole'] ?? '',
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
                <?php if ($is_visitor): ?>
                    <h2>Créer votre École</h2>
                    <p class="lead text-muted">
                        Bienvenue dans Naklass ! Créez votre établissement scolaire et obtenez immédiatement 
                        vos identifiants d'administration.
                    </p>
                <?php else: ?>
                    <h2>Configuration de votre École</h2>
                    <p class="lead text-muted">
                        Bienvenue dans Naklass ! Pour commencer à utiliser le système, 
                        veuillez compléter les informations de votre établissement.
                    </p>
                <?php endif; ?>
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
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($school_created && $admin_credentials): ?>
                <div class="alert alert-success">
                    <h5><i class="bi bi-key me-2"></i>Vos Identifiants d'Administration</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Nom d'utilisateur :</strong><br>
                            <code><?php echo htmlspecialchars($admin_credentials['nom_utilisateur']); ?></code>
                        </div>
                        <div class="col-md-4">
                            <strong>Mot de passe :</strong><br>
                            <code><?php echo htmlspecialchars($admin_credentials['mot_de_passe']); ?></code>
                        </div>
                        <div class="col-md-4">
                            <strong>Code École :</strong><br>
                            <code><?php echo htmlspecialchars($admin_credentials['code_ecole']); ?></code>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter maintenant
                        </a>
                    </div>
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
                                <label for="directeur_nom" class="form-label required-label">Nom du directeur</label>
                                <input type="text" class="form-control" id="directeur_nom" name="directeur_nom" 
                                       value="<?php echo htmlspecialchars($current_data['directeur_nom']); ?>" required>
                            </div>
                        </div>
                        <?php if ($is_visitor): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="directeur_prenom" class="form-label required-label">Prénom du directeur</label>
                                <input type="text" class="form-control" id="directeur_prenom" name="directeur_prenom" 
                                       value="<?php echo htmlspecialchars($current_data['directeur_prenom'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="directeur_telephone" class="form-label required-label">Téléphone du directeur</label>
                                <input type="tel" class="form-control" id="directeur_telephone" name="directeur_telephone" 
                                       value="<?php echo htmlspecialchars($current_data['directeur_telephone']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="directeur_email" class="form-label <?php echo $is_visitor ? 'required-label' : ''; ?>">Email du directeur</label>
                                <input type="email" class="form-control" id="directeur_email" name="directeur_email" 
                                       value="<?php echo htmlspecialchars($current_data['directeur_email']); ?>" 
                                       <?php echo $is_visitor ? 'required' : ''; ?>>
                                <?php if ($is_visitor): ?>
                                <div class="form-text">Cet email recevra les identifiants d'administration</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($is_visitor): ?>
                    <div class="mb-3">
                        <label for="admin_password" class="form-label required-label">Mot de passe administrateur</label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password" 
                               minlength="6" required>
                        <div class="form-text">Minimum 6 caractères</div>
                    </div>
                    <?php endif; ?>
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
                    <?php if ($is_visitor): ?>
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="bi bi-building-add me-2"></i>Créer mon École
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="bi bi-check-circle me-2"></i>Terminer la configuration
                        </button>
                    <?php endif; ?>
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
            
            // Pour les visiteurs, vérifier le mot de passe
            <?php if ($is_visitor): ?>
            const adminPassword = document.getElementById('admin_password');
            if (adminPassword && adminPassword.value.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères.');
                return false;
            }
            <?php endif; ?>
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
