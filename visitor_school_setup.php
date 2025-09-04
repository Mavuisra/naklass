<?php
/**
 * Configuration d'École Côté Visiteur
 * Détecte automatiquement les nouveaux cookies et redirige vers la configuration
 * Cette page est accessible sans authentification
 */

require_once 'config/database.php';

// Démarrer la session si pas encore démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détecter si c'est un nouveau visiteur (nouveau cookie)
$is_new_visitor = false;
$visitor_id = null;

// Vérifier si le visiteur a déjà un cookie
if (!isset($_COOKIE['naklass_visitor_id'])) {
    // Nouveau visiteur - créer un ID unique
    $visitor_id = 'visitor_' . time() . '_' . rand(1000, 9999);
    setcookie('naklass_visitor_id', $visitor_id, time() + (86400 * 365), '/'); // 1 an
    $is_new_visitor = true;
} else {
    $visitor_id = $_COOKIE['naklass_visitor_id'];
}

// Traitement du formulaire de création d'école
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Récupérer les données du formulaire
        $nom_ecole = sanitize($_POST['nom_ecole'] ?? '');
        $adresse = sanitize($_POST['adresse'] ?? '');
        $ville = sanitize($_POST['ville'] ?? '');
        $province = sanitize($_POST['province'] ?? '');
        $telephone = sanitize($_POST['telephone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $directeur_nom = sanitize($_POST['directeur_nom'] ?? '');
        $directeur_telephone = sanitize($_POST['directeur_telephone'] ?? '');
        $directeur_email = sanitize($_POST['directeur_email'] ?? '');
        
        // Validation des champs obligatoires
        if (empty($nom_ecole) || empty($adresse) || empty($ville) || empty($telephone) || empty($email)) {
            throw new Exception("Tous les champs marqués d'un * sont obligatoires.");
        }
        
        // Validation de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'adresse email n'est pas valide.");
        }
        
        // Vérifier si l'email existe déjà
        $check_query = "SELECT COUNT(*) FROM ecoles WHERE email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute(['email' => $email]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("Une école avec cet email existe déjà.");
        }
        
        // Générer un code d'école unique
        $code_ecole = 'ECOLE_' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nom_ecole), 0, 8)) . '_' . time();
        
        // Créer l'école
        $insert_query = "INSERT INTO ecoles (
            nom_ecole, code_ecole, adresse, ville, province, telephone, email,
            directeur_nom, directeur_telephone, directeur_email,
            type_etablissement, pays, regime, type_enseignement, langue_enseignement,
            devise_principale, fuseau_horaire, validation_status, created_by_visitor,
            date_creation_ecole
        ) VALUES (
            :nom_ecole, :code_ecole, :adresse, :ville, :province, :telephone, :email,
            :directeur_nom, :directeur_telephone, :directeur_email,
            'mixte', 'RD Congo', 'privé', 'primaire,secondaire', 'français',
            'CDF', 'Africa/Kinshasa', 'pending', :visitor_id, NOW()
        )";
        
        $stmt = $db->prepare($insert_query);
        $stmt->execute([
            'nom_ecole' => $nom_ecole,
            'code_ecole' => $code_ecole,
            'adresse' => $adresse,
            'ville' => $ville,
            'province' => $province,
            'telephone' => $telephone,
            'email' => $email,
            'directeur_nom' => $directeur_nom,
            'directeur_telephone' => $directeur_telephone,
            'directeur_email' => $directeur_email,
            'visitor_id' => $visitor_id
        ]);
        
        $ecole_id = $db->lastInsertId();
        
        // Préparer les données pour l'email
        $ecoleData = [
            'nom_ecole' => $nom_ecole,
            'code_ecole' => $code_ecole,
            'adresse' => $adresse,
            'ville' => $ville,
            'province' => $province,
            'telephone' => $telephone,
            'email' => $email,
            'directeur_nom' => $directeur_nom,
            'directeur_telephone' => $directeur_telephone,
            'directeur_email' => $directeur_email,
            'visitor_id' => $visitor_id,
            'ecole_id' => $ecole_id
        ];
        
        // Créer automatiquement un compte utilisateur pour le responsable
        $account_created = false;
        $account_data = null;
        $account_error = '';
        
        try {
            require_once 'includes/SchoolAccountManager.php';
            $accountManager = new SchoolAccountManager();
            
            // Valider les données avant création du compte
            $validation = $accountManager->validateSchoolData($ecoleData);
            if ($validation['valid']) {
                $accountResult = $accountManager->createSchoolAccount($ecoleData);
                if ($accountResult['success']) {
                    $account_created = true;
                    $account_data = $accountResult;
                } else {
                    $account_error = $accountResult['error'];
                }
            } else {
                $account_error = implode(', ', $validation['errors']);
            }
        } catch (Exception $e) {
            $account_error = $e->getMessage();
        }
        
        // Envoyer l'email de confirmation avec identifiants
        $email_sent = false;
        $email_error = '';
        
        try {
            require_once 'includes/EmailManager.php';
            $emailManager = new EmailManager();
            
            // Envoyer l'email de confirmation au visiteur avec identifiants
            if ($emailManager->sendSchoolCreationConfirmation($ecoleData, $account_data)) {
                $email_sent = true;
                
                // Envoyer la notification à l'administrateur
                $emailManager->sendAdminNotification($ecoleData);
            }
        } catch (Exception $e) {
            $email_error = $e->getMessage();
        }
        
        $success = "✅ Votre école '{$nom_ecole}' a été créée avec succès !";
        $success .= "<br><strong>Code d'école :</strong> {$code_ecole}";
        $success .= "<br><strong>ID :</strong> {$ecole_id}";
        
        // Informations du compte créé
        if ($account_created && $account_data) {
            $success .= "<br><br>🔐 <strong>Compte administrateur créé automatiquement :</strong>";
            $success .= "<br>• <strong>Email de connexion :</strong> " . htmlspecialchars($account_data['email']);
            $success .= "<br>• <strong>Mot de passe temporaire :</strong> " . htmlspecialchars($account_data['temp_password']);
            $success .= "<br>• <strong>Lien de connexion :</strong> <a href='" . htmlspecialchars($account_data['login_url']) . "' target='_blank'>Se connecter maintenant</a>";
            $success .= "<br><br>⚠️ <strong>Important :</strong> Utilisez votre email et le mot de passe temporaire pour vous connecter. Changez votre mot de passe lors de votre première connexion.";
        } else {
            $success .= "<br><br>⚠️ L'école a été créée mais le compte administrateur n'a pas pu être créé.";
            if (!empty($account_error)) {
                $success .= "<br><small class='text-muted'>Erreur compte : " . htmlspecialchars($account_error) . "</small>";
            }
        }
        
        // Statut de l'email
        if ($email_sent) {
            $success .= "<br><br>📧 Un email de confirmation avec vos identifiants a été envoyé à votre adresse email.";
        } else {
            $success .= "<br><br>⚠️ L'envoi de l'email a échoué.";
            if (!empty($email_error)) {
                $success .= "<br><small class='text-muted'>Erreur email : " . htmlspecialchars($email_error) . "</small>";
            }
        }
        
        $success .= "<br><br>🎉 <strong>Votre école est maintenant active !</strong> Vous pouvez commencer à configurer votre établissement.";
        
        // Marquer le visiteur comme ayant créé une école
        setcookie('naklass_ecole_created', $ecole_id, time() + (86400 * 365), '/');
        
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Fonction de nettoyage des données
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration d'École - Naklass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/auth.css" rel="stylesheet">
    <style>
        /* Styles spécifiques pour la page de configuration */
        .form-label.required::after {
            content: " *";
            color: #dc3545;
            font-weight: bold;
        }
        
        .visitor-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 4px solid #2196f3;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0 12px 12px 0;
            color: #1565c0;
        }
        
        .success-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: #155724;
        }
        
        .success-message strong {
            color: #0f5132;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 2.5rem;
            border-radius: 15px;
            margin-bottom: 2.5rem;
            border: 1px solid #e9ecef;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .form-section h4 {
            color: #0077b6;
            font-weight: 600;
            margin-bottom: 2rem;
            font-size: 1.4rem;
        }
        
        .form-section h4 i {
            color: #0077b6;
        }
        
        /* Amélioration de l'espacement des champs */
        .form-control {
            padding: 1rem 1.25rem;
            font-size: 1.1rem;
            border-radius: 10px;
            border: 2px solid #e1e5e9;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #0077b6;
            box-shadow: 0 0 0 0.3rem rgba(0, 119, 182, 0.15);
            transform: translateY(-1px);
        }
        
        .form-label {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
            color: #495057;
        }
        
        .mb-3 {
            margin-bottom: 1.5rem !important;
        }
        
        .mb-4 {
            margin-bottom: 2rem !important;
        }
        
        /* Amélioration des boutons */
        .btn-primary {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 10px;
        }
        
        .btn-back {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 10px;
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, #5a6268 0%, #3d4449 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
        }
        
        /* Amélioration de l'ergonomie générale */
        .auth-form h3 {
            font-size: 1.8rem;
            margin-bottom: 2.5rem;
        }
        
        .visitor-info {
            padding: 2rem;
            margin-bottom: 2.5rem;
        }
        
        .success-message {
            padding: 2.5rem;
            margin-bottom: 2.5rem;
        }
        
        .alert-info {
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 10px;
        }
        
        /* Amélioration de la zone de texte */
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Espacement du bouton de soumission */
        .btn-primary {
            margin-top: 1rem;
            margin-bottom: 2rem;
        }
        
        /* Logo en haut du formulaire */
        .auth-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        
        .auth-logo i {
            font-size: 3rem;
            color: #0077b6;
        }
        
        .auth-logo h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: #0077b6;
        }
        
        /* Responsive amélioré */
        @media (max-width: 768px) {
            .auth-form-container {
                max-width: 95% !important;
                padding: 1rem;
            }
            
            .auth-form {
                padding: 2rem 1.5rem;
            }
            
            .form-section {
                padding: 1.5rem;
            }
            
            .auth-logo i {
                font-size: 2.5rem;
            }
            
            .auth-logo h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="auth-body">
    <div class="container-fluid">
        <div class="row min-vh-100">
            <!-- Formulaire de configuration en pleine largeur -->
            <div class="col-12 d-flex align-items-center justify-content-center">
                <div class="auth-form-container" style="max-width: 900px; width: 100%;">

                    
                    <div class="auth-form">
                        <div class="text-center mb-5">
                            <div class="auth-logo mb-4">
                                <i class="bi bi-mortarboard-fill"></i>
                                <h2>Naklass</h2>
                            </div>
                            <h3 class="mb-3">
                                <i class="bi bi-building me-2"></i>
                                Configuration d'École
                            </h3>
                            <p class="lead text-muted">
                                Créez votre établissement scolaire en quelques étapes simples
                            </p>
                        </div>
                        
                        <?php if ($is_new_visitor): ?>
                        <div class="visitor-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong class="text-primary text-bold">Bienvenue sur Naklass !</strong> 
                            <!-- Nous avons créé un identifiant unique pour vous suivre.
                            <br><small class="text-muted">ID Visiteur : </small> -->
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                        <div class="success-message">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <?php echo $success; ?>
                            <hr>
                            <p class="mb-0">
                                <strong>Prochaines étapes :</strong>
                                <br>1. Attendez la validation par un super administrateur
                                <br>2. Vous recevrez un email de confirmation
                                <br>3. Vous pourrez alors créer votre compte administrateur
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($success)): ?>
                        <form method="POST" action="" id="schoolSetupForm">
                            <!-- Informations de l'École -->
                            <div class="form-section">
                                <h4>
                                    <i class="bi bi-building me-2"></i>
                                    Informations de l'École
                                </h4>
                                
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="nom_ecole" class="form-label required">Nom de l'école</label>
                                            <input type="text" class="form-control" id="nom_ecole" name="nom_ecole" 
                                                   value="<?php echo htmlspecialchars($_POST['nom_ecole'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="ville" class="form-label required">Ville</label>
                                            <input type="text" class="form-control" id="ville" name="ville" 
                                                   value="<?php echo htmlspecialchars($_POST['ville'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="province" class="form-label">Province</label>
                                            <input type="text" class="form-control" id="province" name="province" 
                                                   value="<?php echo htmlspecialchars($_POST['province'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="telephone" class="form-label required">Téléphone</label>
                                            <input type="tel" class="form-control" id="telephone" name="telephone" 
                                                   value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="adresse" class="form-label required">Adresse complète</label>
                                    <textarea class="form-control" id="adresse" name="adresse" rows="4" required
                                              placeholder="Rue, quartier, commune..."><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="email" class="form-label required">Email de contact</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <!-- Informations du Directeur -->
                            <div class="form-section">
                                <h4>
                                    <i class="bi bi-person-badge me-2"></i>
                                    Informations du Directeur
                                </h4>
                                
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="directeur_nom" class="form-label">Nom complet du directeur</label>
                                            <input type="text" class="form-control" id="directeur_nom" name="directeur_nom" 
                                                   value="<?php echo htmlspecialchars($_POST['directeur_nom'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="directeur_telephone" class="form-label">Téléphone du directeur</label>
                                            <input type="tel" class="form-control" id="directeur_telephone" name="directeur_telephone" 
                                                   value="<?php echo htmlspecialchars($_POST['directeur_telephone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="directeur_email" class="form-label">Email du directeur</label>
                                    <input type="email" class="form-control" id="directeur_email" name="directeur_email" 
                                           value="<?php echo htmlspecialchars($_POST['directeur_email'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Note :</strong> Votre demande sera examinée par un super administrateur. 
                                Une fois approuvée, vous recevrez un email avec les instructions pour créer votre compte administrateur.
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-check-circle me-2"></i>
                                Créer mon école
                            </button>
                            
                            <!-- Bouton de connexion -->
                            <div class="text-center mb-3">
                                <span class="text-muted me-3">ou</span>
                                <a href="auth/login.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-person-circle me-2"></i>
                                    Se connecter
                                </a>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="text-center">
                            <a href="index.php" class="btn btn-back btn-lg mb-3">
                                <i class="bi bi-house me-2"></i>
                                Retour à l'accueil
                            </a>
                            
                            <!-- Bouton de connexion pour les visiteurs existants -->
                            <div class="mt-3">
                                <a href="auth/login.php" class="btn btn-outline-primary">
                                    <i class="bi bi-person-circle me-2"></i>
                                    Se connecter
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <small class="text-muted">
                                Naklass v1.0<br>
                                © <?php echo date('Y'); ?> - Tous droits réservés
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Détection automatique de nouveau cookie
        document.addEventListener('DOMContentLoaded', function() {
            // Vérifier si c'est un nouveau visiteur
            if (!getCookie('naklass_visitor_id')) {
                console.log('Nouveau visiteur détecté - redirection automatique vers la configuration');
            }
        });
        
        // Fonction pour récupérer un cookie
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
        }
        
        // Validation du formulaire
        document.getElementById('schoolSetupForm')?.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
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
