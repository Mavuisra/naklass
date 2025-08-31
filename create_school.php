<?php
/**
 * Page de création d'école pour les visiteurs
 * Permet de créer une nouvelle école et un compte administrateur
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $ecole_nom = sanitize($_POST['nom_ecole'] ?? '');
    $ecole_adresse = sanitize($_POST['ecole_adresse'] ?? '');
    $ecole_telephone = sanitize($_POST['ecole_telephone'] ?? '');
    $ecole_email = sanitize($_POST['ecole_email'] ?? '');
    
    $admin_nom = sanitize($_POST['admin_nom'] ?? '');
    $admin_prenom = sanitize($_POST['admin_prenom'] ?? '');
    $admin_email = sanitize($_POST['admin_email'] ?? '');
    $admin_telephone = sanitize($_POST['admin_telephone'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_password_confirm = $_POST['admin_password_confirm'] ?? '';
    
    // Validation des champs obligatoires
    if (empty($ecole_nom) || empty($ecole_adresse) || empty($ecole_telephone) || 
        empty($ecole_email) || empty($admin_nom) || empty($admin_prenom) || 
        empty($admin_email) || empty($admin_telephone) || empty($admin_password)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($ecole_email, FILTER_VALIDATE_EMAIL) || 
              !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer des adresses email valides.';
    } elseif ($admin_password !== $admin_password_confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($admin_password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Vérifier si l'email de l'école existe déjà
            $query = "SELECT id FROM ecoles WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->execute(['email' => $ecole_email]);
            if ($stmt->fetch()) {
                $error = 'Une école avec cet email existe déjà.';
            } else {
                // Vérifier si l'email de l'admin existe déjà
                $query = "SELECT id FROM utilisateurs WHERE email = :email";
                $stmt = $db->prepare($query);
                $stmt->execute(['email' => $admin_email]);
                if ($stmt->fetch()) {
                    $error = 'Un utilisateur avec cet email existe déjà.';
                } else {
                    // Commencer la transaction
                    $db->beginTransaction();
                    
                    try {
                        // 1. Créer l'école
                        $query = "INSERT INTO ecoles (nom_ecole, adresse, telephone, email, directeur_nom, 
                                                   validation_status, super_admin_validated, date_creation_ecole) 
                                 VALUES (:nom_ecole, :adresse, :telephone, :email, :directeur_nom, 
                                         'pending', FALSE, NOW())";
                        
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            'nom_ecole' => $ecole_nom,
                            'adresse' => $ecole_adresse,
                            'telephone' => $ecole_telephone,
                            'email' => $ecole_email,
                            'directeur_nom' => $admin_nom . ' ' . $admin_prenom
                        ]);
                        
                        $ecole_id = $db->lastInsertId();
                        
                        // 2. Créer le rôle admin s'il n'existe pas
                        $query = "SELECT id FROM roles WHERE code = 'admin' LIMIT 1";
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        $role = $stmt->fetch();
                        
                        if (!$role) {
                            $query = "INSERT INTO roles (code, libelle, permissions) VALUES ('admin', 'Administrateur', '{\"all\": true}')";
                            $db->exec($query);
                            $role_id = $db->lastInsertId();
                        } else {
                            $role_id = $role['id'];
                        }
                        
                        // 3. Créer l'utilisateur admin
                        $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                        $query = "INSERT INTO utilisateurs (ecole_id, nom, prenom, email, telephone, 
                                                          mot_de_passe_hash, role_id, created_at) 
                                 VALUES (:ecole_id, :nom, :prenom, :email, :telephone, 
                                         :password_hash, :role_id, NOW())";
                        
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            'ecole_id' => $ecole_id,
                            'nom' => $admin_nom,
                            'prenom' => $admin_prenom,
                            'email' => $admin_email,
                            'telephone' => $admin_telephone,
                            'password_hash' => $password_hash,
                            'role_id' => $role_id
                        ]);
                        
                        $admin_id = $db->lastInsertId();
                        
                        // 4. Mettre à jour l'école avec l'ID du créateur
                        $query = "UPDATE ecoles SET created_by_visitor = :admin_id WHERE id = :ecole_id";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            'admin_id' => $admin_id,
                            'ecole_id' => $ecole_id
                        ]);
                        
                        // 5. Créer la notification pour le super admin
                        $query = "INSERT INTO super_admin_notifications (type, ecole_id, message) 
                                 VALUES ('new_school', :ecole_id, :message)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            'ecole_id' => $ecole_id,
                            'message' => "Nouvelle école créée : {$ecole_nom} par {$admin_nom} {$admin_prenom}"
                        ]);
                        
                        // 6. Enregistrer dans l'historique
                        $query = "INSERT INTO school_validation_history (ecole_id, action, performed_by, notes) 
                                 VALUES (:ecole_id, 'created', :admin_id, 'Création de l\'école par visiteur')";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            'ecole_id' => $ecole_id,
                            'admin_id' => $admin_id
                        ]);
                        
                        // Valider la transaction
                        $db->commit();
                        
                        $success = "Votre école a été créée avec succès ! Un compte administrateur a été créé pour vous. 
                                   Votre école est maintenant en attente de validation par le super administrateur. 
                                   Vous recevrez un email dès qu'elle sera validée.";
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Erreur système : ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une nouvelle école - Naklass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .create-school-container { 
            max-width: 800px; 
            margin: 50px auto; 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
            overflow: hidden; 
        }
        .create-school-header { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
            color: white; 
            padding: 40px; 
            text-align: center; 
        }
        .create-school-body { padding: 40px; }
        .form-section { 
            background: #f8f9fa; 
            padding: 25px; 
            border-radius: 10px; 
            margin-bottom: 25px; 
        }
        .form-section h4 { 
            color: #495057; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #dee2e6; 
            padding-bottom: 10px; 
        }
        .required-label::after { content: " *"; color: #dc3545; }
        .btn-create-school { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
            border: none; 
            padding: 12px 30px; 
            font-size: 18px; 
            border-radius: 25px; 
        }
        .btn-create-school:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4); 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="create-school-container">
            <div class="create-school-header">
                <div class="mb-3">
                    <i class="bi bi-building-add" style="font-size: 4rem;"></i>
                </div>
                <h1>Créer une nouvelle école</h1>
                <p class="lead mb-0">
                    Rejoignez Naklass et gérez votre établissement scolaire en toute simplicité
                </p>
            </div>

            <div class="create-school-body">
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

                <form method="POST" action="" id="createSchoolForm">
                    <!-- Informations de l'école -->
                    <div class="form-section">
                        <h4><i class="bi bi-building me-2"></i>Informations de l'école</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ecole_nom" class="form-label required-label">Nom de l'école</label>
                                    <input type="text" class="form-control" id="ecole_nom" name="nom_ecole" 
                                           value="<?php echo htmlspecialchars($_POST['nom_ecole'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ecole_email" class="form-label required-label">Email de l'école</label>
                                    <input type="email" class="form-control" id="ecole_email" name="ecole_email" 
                                           value="<?php echo htmlspecialchars($_POST['ecole_email'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ecole_adresse" class="form-label required-label">Adresse complète</label>
                            <textarea class="form-control" id="ecole_adresse" name="ecole_adresse" 
                                      rows="3" required><?php echo htmlspecialchars($_POST['ecole_adresse'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ecole_telephone" class="form-label required-label">Téléphone</label>
                            <input type="tel" class="form-control" id="ecole_telephone" name="ecole_telephone" 
                                   value="<?php echo htmlspecialchars($_POST['ecole_telephone'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Compte administrateur -->
                    <div class="form-section">
                        <h4><i class="bi bi-person-badge me-2"></i>Votre compte administrateur</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="admin_nom" class="form-label required-label">Nom de famille</label>
                                    <input type="text" class="form-control" id="admin_nom" name="admin_nom" 
                                           value="<?php echo htmlspecialchars($_POST['admin_nom'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="admin_prenom" class="form-label required-label">Prénom</label>
                                    <input type="text" class="form-control" id="admin_prenom" name="admin_prenom" 
                                           value="<?php echo htmlspecialchars($_POST['admin_prenom'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="admin_email" class="form-label required-label">Email personnel</label>
                                    <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                           value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="admin_telephone" class="form-label required-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="admin_telephone" name="admin_telephone" 
                                           value="<?php echo htmlspecialchars($_POST['admin_telephone'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="admin_password" class="form-label required-label">Mot de passe</label>
                                    <input type="password" class="form-control" id="admin_password" name="admin_password" 
                                           minlength="8" required>
                                    <div class="form-text">Minimum 8 caractères</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="admin_password_confirm" class="form-label required-label">Confirmer le mot de passe</label>
                                    <input type="password" class="form-control" id="admin_password_confirm" name="admin_password_confirm" 
                                           minlength="8" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informations importantes -->
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Important :</strong> Après la création de votre école, vous devrez compléter sa configuration 
                        et attendre la validation par le super administrateur avant de pouvoir utiliser le système.
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-create-school">
                            <i class="bi bi-building-add me-2"></i>Créer mon école
                        </button>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <p class="text-muted">
                        Déjà une école sur Naklass ? 
                        <a href="auth/login.php" class="text-decoration-none">Se connecter</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation côté client
        document.getElementById('createSchoolForm').addEventListener('submit', function(e) {
            const password = document.getElementById('admin_password').value;
            const confirmPassword = document.getElementById('admin_password_confirm').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères.');
                return false;
            }
        });
    </script>
</body>
</html>
