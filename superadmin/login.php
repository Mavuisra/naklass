<?php
/**
 * Page de connexion dédiée au Super Administrateur
 * Interface spécialisée pour l'accès Super Admin
 */
require_once '../includes/functions.php';

// Si déjà connecté et Super Admin, rediriger vers l'interface Super Admin
if (isLoggedIn() && isSuperAdmin()) {
    redirect('index.php');
}

// Si connecté mais pas Super Admin, rediriger vers le dashboard normal
if (isLoggedIn()) {
    redirect('../auth/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Vérifier d'abord si la colonne is_super_admin existe
        $check_column_query = "SHOW COLUMNS FROM utilisateurs LIKE 'is_super_admin'";
        $check_stmt = $db->prepare($check_column_query);
        $check_stmt->execute();
        $column_exists = $check_stmt->fetch();
        
        // Vérifier si le rôle super_admin existe
        $check_role_query = "SELECT id FROM roles WHERE code = 'super_admin'";
        $check_role_stmt = $db->prepare($check_role_query);
        $check_role_stmt->execute();
        $super_admin_role_exists = $check_role_stmt->fetch();
        
        if ($super_admin_role_exists) {
            // Priorité 1: Vérification par rôle super_admin
            $query = "SELECT u.*, r.code as role_code, r.niveau_hierarchie 
                      FROM utilisateurs u 
                      JOIN roles r ON u.role_id = r.id 
                      WHERE u.email = :email 
                      AND u.actif = 1 
                      AND u.statut = 'actif' 
                      AND r.code = 'super_admin'";
        } elseif ($column_exists) {
            // Priorité 2: Vérification par colonne is_super_admin
            $query = "SELECT u.*, r.code as role_code, r.niveau_hierarchie 
                      FROM utilisateurs u 
                      JOIN roles r ON u.role_id = r.id 
                      WHERE u.email = :email 
                      AND u.actif = 1 
                      AND u.statut = 'actif' 
                      AND u.is_super_admin = TRUE";
        } else {
            // Priorité 3: Fallback - accepter les comptes admin existants
            $query = "SELECT u.*, r.code as role_code, r.niveau_hierarchie 
                      FROM utilisateurs u 
                      JOIN roles r ON u.role_id = r.id 
                      WHERE u.email = :email 
                      AND u.actif = 1 
                      AND u.statut = 'actif' 
                      AND (r.code = 'admin' OR r.code = 'super_admin')";
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['mot_de_passe_hash'])) {
            // Mise à jour de la dernière connexion
            updateLoginAttempts($user['id'], true, $db);
            
            // Création de la session Super Admin
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role_code'];
            $_SESSION['ecole_id'] = $column_exists ? null : $user['ecole_id']; // Super Admin n'appartient à aucune école spécifique
            $_SESSION['ecole_nom'] = $column_exists ? 'Super Administration' : ($user['nom_ecole'] ?? 'Administration');
            $_SESSION['is_super_admin'] = $column_exists ? (isset($user['is_super_admin']) ? (bool)$user['is_super_admin'] : true) : true;
            $_SESSION['niveau_acces'] = $column_exists ? ($user['niveau_acces'] ?? 'super_admin') : 'super_admin';
            $_SESSION['last_activity'] = time();
            
            // Log de connexion
            logUserAction('SUPER_ADMIN_LOGIN', 'Connexion Super Administrateur réussie');
            
            setFlashMessage('success', 'Connexion réussie ! Bienvenue Super Administrateur ' . $user['prenom'] . ' ' . $user['nom']);
            redirect('index.php');
        } else {
            // Log des tentatives de connexion échouées
            if ($user) {
                updateLoginAttempts($user['id'], false, $db);
                logUserAction('SUPER_ADMIN_LOGIN_FAILED', 'Tentative de connexion Super Admin échouée', $user['id']);
            } else {
                // Log des tentatives avec email inexistant
                logUserAction('SUPER_ADMIN_LOGIN_FAILED', 'Tentative de connexion Super Admin avec email: ' . $email);
            }
            
            $error = 'Identifiants Super Administrateur incorrects.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg,rgb(172, 198, 201) 0%,rgb(173, 169, 178) 100%);
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
            background: linear-gradient(135deg,rgb(14, 89, 219) 0%,rgb(4, 5, 33) 100%);
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
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
        }
        
        .form-floating label {
            padding: 15px 20px;
            color: #6c757d;
        }
        
        .btn-login {
            background: linear-gradient(135deg,rgb(4, 64, 30) 0%,rgb(8, 44, 19) 100%);
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
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
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
        }
        
        .info-box h6 {
            color: #17a2b8;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .info-box .small {
            color: #6c757d;
            line-height: 1.4;
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
            color: #dc3545;
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
                    <i class="bi bi-shield-check"></i>
                </div>
                <h1 class="login-title">Super Admin</h1>
                <p class="login-subtitle">Interface de gestion centralisée</p>
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

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Informations de connexion -->
                <div class="info-box">
                    <h6><i class="bi bi-info-circle me-2"></i>Identifiants par défaut</h6>
                    <div class="small">
                        <strong>Email :</strong> admin@naklass.cd<br>
                        <strong>Mot de passe :</strong> password
                    </div>
                </div>

                <!-- Formulaire de connexion -->
                <form method="POST" action="">
                    <div class="form-floating">
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? 'admin@naklass.cd'); ?>" 
                               placeholder="admin@naklass.cd"
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
                        <i class="bi bi-shield-check me-2"></i>Se connecter
                    </button>
                </form>

                <!-- Liens utiles -->
                <div class="links-section">
                    <a href="../auth/login.php">
                        <i class="bi bi-arrow-left me-1"></i>Connexion normale
                    </a>
                    <a href="../index.php">
                        <i class="bi bi-house me-1"></i>Accueil
                    </a>
                </div>
                
                <!-- Pied de page -->
                <div class="footer">
                    <small>
                        Naklass v1.0 - Super Administration<br>
                        © <?php echo date('Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus sur le champ email
        document.getElementById('email').focus();
        
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
