<?php
require_once '../includes/functions.php';

// Si déjà connecté, rediriger vers le dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT u.*, r.code as role_code, e.nom_ecole as nom_ecole 
                  FROM utilisateurs u 
                  JOIN roles r ON u.role_id = r.id 
                  LEFT JOIN ecoles e ON u.ecole_id = e.id 
                  WHERE u.email = :email AND u.actif = 1 AND u.statut = 'actif'";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['mot_de_passe_hash'])) {
            // Mise à jour de la dernière connexion
            updateLoginAttempts($user['id'], true, $db);
            
            // Création de la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role_code'];
            $_SESSION['ecole_id'] = $user['ecole_id'];
            $_SESSION['ecole_nom'] = $user['nom_ecole'];
            $_SESSION['is_super_admin'] = isset($user['is_super_admin']) ? (bool)$user['is_super_admin'] : false;
            $_SESSION['niveau_acces'] = $user['niveau_acces'] ?? 'user';
            $_SESSION['last_activity'] = time();
            
            // Log de connexion
            logUserAction('LOGIN', 'Connexion réussie');
            
            // Redirection selon le rôle de l'utilisateur
            if ($user['role_code'] === 'enseignant') {
                // Les enseignants vont directement vers leur tableau de bord
                setFlashMessage('success', 'Connexion réussie ! Bienvenue ' . $user['prenom'] . ' ' . $user['nom']);
                redirect('../teachers/dashboard.php');
            } elseif ($user['role_code'] === 'admin' || $user['role_code'] === 'direction') {
                // Vérifier si l'utilisateur a déjà une école active
                if ($user['ecole_id']) {
                    try {
                        $ecole_query = "SELECT statut, super_admin_validated FROM ecoles WHERE id = :ecole_id";
                        $ecole_stmt = $db->prepare($ecole_query);
                        $ecole_stmt->execute(['ecole_id' => $user['ecole_id']]);
                        $ecole_data = $ecole_stmt->fetch();
                        
                        // Si l'école est active et validée, rediriger directement vers le dashboard
                        if ($ecole_data && $ecole_data['statut'] === 'actif' && $ecole_data['super_admin_validated']) {
                            setFlashMessage('success', 'Connexion réussie ! Bienvenue ' . $user['prenom'] . ' ' . $user['nom']);
                            redirect('dashboard.php');
                        }
                    } catch (Exception $e) {
                        // En cas d'erreur, continuer avec la redirection normale
                        error_log('Erreur lors de la vérification de l\'école: ' . $e->getMessage());
                    }
                }
                
                setFlashMessage('success', 'Connexion réussie ! Bienvenue ' . $user['prenom'] . ' ' . $user['nom']);
                redirect('dashboard.php');
            } else {
                // Autres rôles (secretaire, caissier, etc.) vont vers le dashboard général
                setFlashMessage('success', 'Connexion réussie ! Bienvenue ' . $user['prenom'] . ' ' . $user['nom']);
                redirect('dashboard.php');
            }
        } else {
            // Incrémenter les tentatives de connexion en cas d'échec
            if ($user) {
                updateLoginAttempts($user['id'], false, $db);
                logUserAction('LOGIN_FAILED', 'Tentative de connexion échouée', $user['id']);
            }
            
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Naklass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg,rgb(139, 145, 174) 0%,rgb(171, 168, 174) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
        }
        
        .login-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }
        
        .login-subtitle {
            opacity: 0.9;
            margin: 10px 0 0 0;
            font-size: 1rem;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-floating .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
        }
        
        .form-floating label {
            padding: 15px 20px;
            color: #6c757d;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            border-radius: 10px;
            padding: 15px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 123, 255, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .links-section {
            text-align: center;
            margin-top: 20px;
        }
        
        .links-section a {
            color: #6c757d;
            text-decoration: none;
            margin: 0 10px;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .links-section a:hover {
            color: #007bff;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .footer small {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- En-tête -->
            <div class="login-header">
                <div class="login-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <h1 class="login-title">Naklass</h1>
                <p class="login-subtitle">Connexion</p>
            </div>
            
            <!-- Corps du formulaire -->
            <div class="login-body">
                <!-- Messages d'erreur/succès -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Formulaire de connexion -->
                <form method="POST" action="">
                    <div class="form-floating">
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               placeholder="votre@email.com"
                               required>
                        <label for="email">Email</label>
                    </div>
                    
                    <div class="form-floating">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Mot de passe"
                               required>
                        <label for="password">Mot de passe</label>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                    </button>
                </form>

                <!-- Liens utiles -->
                <div class="links-section">
                    <a href="../visitor_school_setup.php">
                        <i class="bi bi-building me-1"></i>Nouvelle école
                    </a>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                        <i class="bi bi-key me-1"></i>Mot de passe oublié
                    </a>
                </div>
                
                <!-- Pied de page -->
                <div class="footer">
                    <small>
                        Naklass v1.0<br>
                        © <?php echo date('Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Mot de passe oublié -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-key me-2"></i>Réinitialiser le mot de passe
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Entrez votre adresse email pour recevoir un lien de réinitialisation :</p>
                    <form id="forgotPasswordForm">
                        <div class="mb-3">
                            <label for="resetEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="resetEmail" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="sendResetLink()">
                        <i class="bi bi-send me-2"></i>Envoyer le lien
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus sur le champ email
        document.getElementById('email').focus();
        
        // Fonction pour envoyer le lien de réinitialisation
        function sendResetLink() {
            const email = document.getElementById('resetEmail').value;
            if (!email) {
                alert('Veuillez entrer votre adresse email.');
                return;
            }
            
            // Ici, vous pouvez implémenter l'envoi du lien de réinitialisation
            alert('Si cette adresse email existe dans notre système, vous recevrez un lien de réinitialisation.');
            bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal')).hide();
        }
        
        // Auto-hide alerts après 5 secondes
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>
