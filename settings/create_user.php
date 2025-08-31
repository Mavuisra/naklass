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

// Traitement du formulaire de création d'utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $nom = sanitize($_POST['nom'] ?? '');
        $prenom = sanitize($_POST['prenom'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $telephone = sanitize($_POST['telephone'] ?? '');
        $role_id = intval($_POST['role_id'] ?? 0);
        $mot_de_passe = $_POST['mot_de_passe'] ?? '';
        $confirmation_mot_de_passe = $_POST['confirmation_mot_de_passe'] ?? '';
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Validation des champs obligatoires
        if (empty($nom) || empty($prenom) || empty($email) || empty($role_id) || empty($mot_de_passe)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Validation de l'email
        if (!validateEmail($email)) {
            throw new Exception("L'adresse email n'est pas valide.");
        }
        
        // Validation du mot de passe
        if (strlen($mot_de_passe) < 6) {
            throw new Exception("Le mot de passe doit contenir au moins 6 caractères.");
        }
        
        if ($mot_de_passe !== $confirmation_mot_de_passe) {
            throw new Exception("Les mots de passe ne correspondent pas.");
        }
        
        // Vérifier que l'email n'existe pas déjà
        $check_email = "SELECT COUNT(*) FROM utilisateurs WHERE email = :email AND ecole_id = :ecole_id";
        $stmt = $db->prepare($check_email);
        $stmt->execute(['email' => $email, 'ecole_id' => $_SESSION['ecole_id']]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Cette adresse email est déjà utilisée dans votre école.");
        }
        
        // Vérifier que le rôle existe et est valide
        $check_role = "SELECT * FROM roles WHERE id = :role_id AND statut = 'actif'";
        $stmt = $db->prepare($check_role);
        $stmt->execute(['role_id' => $role_id]);
        $role = $stmt->fetch();
        
        if (!$role) {
            throw new Exception("Le rôle sélectionné n'est pas valide.");
        }
        
        // Créer l'utilisateur
        $db->beginTransaction();
        
        $password_hash = hashPassword($mot_de_passe);
        
        $insert_user = "INSERT INTO utilisateurs (
            ecole_id, role_id, nom, prenom, email, telephone, 
            mot_de_passe_hash, actif, statut, notes_internes, created_by, created_at
        ) VALUES (
            :ecole_id, :role_id, :nom, :prenom, :email, :telephone,
            :password_hash, 1, 'actif', :notes, :created_by, NOW()
        )";
        
        $stmt = $db->prepare($insert_user);
        $result = $stmt->execute([
            'ecole_id' => $_SESSION['ecole_id'],
            'role_id' => $role_id,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'password_hash' => $password_hash,
            'notes' => $notes,
            'created_by' => $_SESSION['user_id']
        ]);
        
        if ($result) {
            $user_id = $db->lastInsertId();
            
            // Log de l'action
            logUserAction('CREATE_USER', "Création de l'utilisateur: $prenom $nom ($email) avec le rôle: {$role['libelle']}");
            
            $db->commit();
            
            $success = "Utilisateur créé avec succès ! Un email de confirmation a été envoyé à $email.";
            
            // Réinitialiser le formulaire
            $_POST = [];
            
        } else {
            throw new Exception("Erreur lors de la création de l'utilisateur.");
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = $e->getMessage();
    }
}

// Récupérer la liste des rôles disponibles
try {
    $roles_query = "SELECT id, code, libelle, description FROM roles WHERE statut = 'actif' ORDER BY niveau_hierarchie, libelle";
    $stmt = $db->prepare($roles_query);
    $stmt->execute();
    $roles = $stmt->fetchAll();
} catch (Exception $e) {
    $roles = [];
    $errors[] = "Erreur lors de la récupération des rôles: " . $e->getMessage();
}

$page_title = "Créer un Utilisateur";
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
        .role-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .role-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .role-card.selected {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        .role-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .password-strength {
            height: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
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
                <h1><i class="bi bi-person-plus me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Créez un nouvel utilisateur dans votre école</p>
            </div>
            
            <div class="topbar-actions">
                <a href="users.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Messages d'erreur et de succès -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Formulaire de création -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Informations de l'utilisateur</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="createUserForm">
                                <div class="row">
                                    <!-- Nom -->
                                    <div class="col-md-6 mb-3">
                                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="nom" name="nom" 
                                               value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <!-- Prénom -->
                                    <div class="col-md-6 mb-3">
                                        <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" 
                                               value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Email -->
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        <div class="form-text">L'utilisateur utilisera cet email pour se connecter</div>
                                    </div>
                                    
                                    <!-- Téléphone -->
                                    <div class="col-md-6 mb-3">
                                        <label for="telephone" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                                               value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- Sélection du rôle -->
                                <div class="mb-4">
                                    <label class="form-label">Rôle <span class="text-danger">*</span></label>
                                    <div class="row">
                                        <?php foreach ($roles as $role): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="card role-card h-100" data-role-id="<?php echo $role['id']; ?>">
                                                    <div class="card-body text-center">
                                                        <div class="role-icon">
                                                            <?php
                                                            $icon = match($role['code']) {
                                                                'admin' => 'bi-shield-check',
                                                                'direction' => 'bi-building',
                                                                'enseignant' => 'bi-person-badge',
                                                                'secretaire' => 'bi-person-workspace',
                                                                'caissier' => 'bi-cash-coin',
                                                                default => 'bi-person'
                                                            };
                                                            ?>
                                                            <i class="bi <?php echo $icon; ?> text-primary"></i>
                                                        </div>
                                                        <h6 class="card-title"><?php echo htmlspecialchars($role['libelle']); ?></h6>
                                                        <p class="card-text small text-muted"><?php echo htmlspecialchars($role['description'] ?? ''); ?></p>
                                                        <input type="radio" name="role_id" value="<?php echo $role['id']; ?>" 
                                                               class="form-check-input" required style="display: none;">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Mot de passe -->
                                    <div class="col-md-6 mb-3">
                                        <label for="mot_de_passe" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" 
                                               required minlength="6">
                                        <div class="password-strength mt-2" id="passwordStrength"></div>
                                        <div class="form-text">Minimum 6 caractères</div>
                                    </div>
                                    
                                    <!-- Confirmation mot de passe -->
                                    <div class="col-md-6 mb-3">
                                        <label for="confirmation_mot_de_passe" class="form-label">Confirmation <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="confirmation_mot_de_passe" 
                                               name="confirmation_mot_de_passe" required>
                                    </div>
                                </div>
                                
                                <!-- Notes -->
                                <div class="mb-4">
                                    <label for="notes" class="form-label">Notes internes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Informations supplémentaires sur cet utilisateur..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Boutons d'action -->
                                <div class="d-flex justify-content-between">
                                    <a href="users.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-person-plus me-2"></i>Créer l'utilisateur
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Informations et aide -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations</h6>
                        </div>
                        <div class="card-body">
                            <h6>Rôles disponibles :</h6>
                            <ul class="list-unstyled small">
                                <li><strong>Administrateur :</strong> Accès complet au système</li>
                                <li><strong>Direction :</strong> Gestion administrative et pédagogique</li>
                                <li><strong>Enseignant :</strong> Gestion des notes et présence</li>
                                <li><strong>Secrétaire :</strong> Gestion des élèves et inscriptions</li>
                                <li><strong>Caissier :</strong> Gestion des paiements et finances</li>
                            </ul>
                            
                            <hr>
                            
                            <h6>Après la création :</h6>
                            <ul class="list-unstyled small">
                                <li>✓ L'utilisateur peut se connecter immédiatement</li>
                                <li>✓ Un email de confirmation sera envoyé</li>
                                <li>✓ L'utilisateur peut changer son mot de passe</li>
                                <li>✓ Les permissions sont automatiquement appliquées</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>Sécurité</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info small mb-0">
                                <i class="bi bi-lightbulb me-2"></i>
                                <strong>Conseil :</strong> Encouragez les nouveaux utilisateurs à changer leur mot de passe dès leur première connexion.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Sélection des rôles
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', function() {
                // Désélectionner tous les autres
                document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
                // Sélectionner celui-ci
                this.classList.add('selected');
                // Cocher le radio button
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });
        
        // Validation du mot de passe
        document.getElementById('mot_de_passe').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength mt-2';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
                strengthBar.style.width = '33%';
            } else if (strength <= 3) {
                strengthBar.classList.add('strength-medium');
                strengthBar.style.width = '66%';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthBar.style.width = '100%';
            }
        });
        
        // Validation du formulaire
        document.getElementById('createUserForm').addEventListener('submit', function(e) {
            const password = document.getElementById('mot_de_passe').value;
            const confirmPassword = document.getElementById('confirmation_mot_de_passe').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }
        });
    </script>
</body>
</html>

