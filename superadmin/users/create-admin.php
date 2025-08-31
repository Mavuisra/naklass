<?php
/**
 * Création d'un administrateur d'école par le Super Admin
 */
require_once '../../includes/functions.php';

// Vérifier l'authentification et les droits Super Admin
requireAuth();
requireSuperAdmin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$ecole_id = $_GET['ecole_id'] ?? null;
$ecole = null; // Initialiser la variable $ecole
$ecoles_sans_admin = []; // Initialiser la variable $ecoles_sans_admin

// Vérifier que l'école existe
if ($ecole_id) {
    $query = "SELECT * FROM ecoles WHERE id = :ecole_id AND statut = 'actif'";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $ecole_id]);
    $ecole = $stmt->fetch();
    
    if (!$ecole) {
        $error = 'École introuvable.';
        $ecole = null; // Réinitialiser si l'école n'est pas trouvée
        // redirect('../index.php');
    } else {
        // Vérifier s'il y a déjà un admin pour cette école
        $query = "SELECT * FROM utilisateurs WHERE ecole_id = :ecole_id AND role_id = 1 AND actif = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $ecole_id]);
        $existing_admin = $stmt->fetch();
        
        if ($existing_admin) {
            $error = 'Cette école a déjà un administrateur.';
            $ecole = null; // Réinitialiser si l'école a déjà un admin
            // redirect('../schools/view.php?id=' . $ecole_id);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = htmlspecialchars(trim($_POST['nom'] ?? ''), ENT_QUOTES, 'UTF-8');
    $prenom = htmlspecialchars(trim($_POST['prenom'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $telephone = htmlspecialchars(trim($_POST['telephone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $confirmer_mot_de_passe = $_POST['confirmer_mot_de_passe'] ?? '';
    $ecole_id = $_POST['ecole_id'] ?? '';
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($mot_de_passe) || empty($ecole_id)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($mot_de_passe) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif ($mot_de_passe !== $confirmer_mot_de_passe) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            // Vérifier que l'email n'existe pas déjà
            $query = "SELECT id FROM utilisateurs WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->execute(['email' => $email]);
            
            if ($stmt->fetch()) {
                $error = 'Cette adresse email est déjà utilisée.';
            } else {
                // Récupérer l'ID du rôle admin
                $query = "SELECT id FROM roles WHERE code = 'admin'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $role_admin = $stmt->fetch();
                
                if (!$role_admin) {
                    $error = 'Rôle administrateur introuvable dans le système.';
                } else {
                    // Créer l'utilisateur administrateur
                    $password_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                    
                    $query = "INSERT INTO utilisateurs (
                        ecole_id, nom, prenom, email, telephone, mot_de_passe_hash, 
                        role_id, actif, created_by
                    ) VALUES (
                        :ecole_id, :nom, :prenom, :email, :telephone, :mot_de_passe_hash,
                        :role_id, TRUE, :created_by
                    )";
                    
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        'ecole_id' => $ecole_id,
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'email' => $email,
                        'telephone' => $telephone,
                        'mot_de_passe_hash' => $password_hash,
                        'role_id' => $role_admin['id'],
                        'created_by' => $_SESSION['user_id']
                    ]);
                    
                    if ($result) {
                        // Activer l'école si elle ne l'est pas déjà
                        $query = "UPDATE ecoles SET statut = 'actif', updated_at = NOW(), updated_by = :super_admin_id WHERE id = :ecole_id";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            'super_admin_id' => $_SESSION['user_id'],
                            'ecole_id' => $ecole_id
                        ]);
                        
                        // Log de l'action (à implémenter plus tard)
                        // logUserAction('CREATE_SCHOOL_ADMIN', "Création de l'administrateur pour l'école ID: $ecole_id");
                        
                        $success_message = 'Administrateur créé avec succès ! L\'école est maintenant active.';
                        header('Location: ../schools/view.php?id=' . $ecole_id);
                        exit;
                    } else {
                        $error = 'Erreur lors de la création de l\'administrateur.';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Erreur système : ' . $e->getMessage();
        }
    }
}

// Récupérer la liste des écoles sans administrateur si aucune école spécifiée
if (!$ecole_id) {
    $query = "SELECT e.* FROM ecoles e 
              LEFT JOIN utilisateurs u ON e.id = u.ecole_id AND u.role_id = 1 AND u.actif = TRUE
              WHERE e.statut = 'actif' AND u.id IS NULL
              ORDER BY e.nom_ecole";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ecoles_sans_admin = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Administrateur d'École - Super Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/dashboard.css" rel="stylesheet">
    <link href="../../assets/css/common.css" rel="stylesheet">
    <style>
        .sidebar-logo i { color: #ffd700; }
        .user-avatar i { color: #ffd700; }
        .menu-item.active .menu-link { border-left-color: #ffd700; }
        .menu-link:hover { border-left-color: #ffd700; }
        .form-section { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .form-section h6 { color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; }
        .required-field::after { content: " *"; color: #dc3545; }
        .optional-field::after { content: " (optionnel)"; color: #6c757d; font-size: 0.875em; }
        .info-card { transition: transform 0.2s, box-shadow 0.2s; }
        .info-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
    </style>
</head>
<body>
    <!-- Navigation latérale -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="bi bi-shield-check"></i>
                <span>Super Admin</span>
            </div>
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="bi bi-person-badge"></i>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?></div>
                <div class="user-role">Super Administrateur</div>
                <div class="user-school">Gestion Multi-Écoles</div>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="../index.php" class="menu-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../schools/index.php" class="menu-link">
                    <i class="bi bi-building"></i>
                    <span>Écoles</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../schools/requests.php" class="menu-link">
                    <i class="bi bi-envelope"></i>
                    <span>Demandes</span>
                </a>
            </li>
            
            <li class="menu-item active">
                <a href="create-admin.php" class="menu-link">
                    <i class="bi bi-person-plus"></i>
                    <span>Créer Admin</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="super-admins.php" class="menu-link">
                    <i class="bi bi-shield"></i>
                    <span>Super Admins</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../../auth/logout.php" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </nav>
    
    <!-- Contenu principal -->
    <main class="main-content">
        <!-- Barre supérieure -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-person-plus me-2"></i>Créer un Administrateur d'École</h1>
                <p class="text-muted">Attribuer un administrateur à une école existante</p>
            </div>
            
            <div class="topbar-actions">
                <a href="../schools/index.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i>
                    <span>Retour aux écoles</span>
                </a>
            </div>
        </header>
        
        <!-- Contenu du formulaire -->
        <div class="content-area">
                        <!-- Messages de notification -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($ecole): ?>
                <div class="alert alert-info">
                    <i class="bi bi-building me-2"></i>
                    <strong>École sélectionnée :</strong> <?php echo htmlspecialchars($ecole['nom_ecole']); ?>
                    <?php if ($ecole['sigle']): ?>
                        (<?php echo htmlspecialchars($ecole['sigle']); ?>)
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire de création d'admin -->
            <div class="card info-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person-plus me-2"></i>Informations de l'administrateur
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <?php if ($ecole_id): ?>
                            <input type="hidden" name="ecole_id" value="<?php echo htmlspecialchars($ecole_id); ?>">
                        <?php else: ?>
                            <div class="form-section">
                                <h6><i class="bi bi-building me-2"></i>Sélection de l'école</h6>
                                <div class="mb-3">
                                    <label for="ecole_id" class="form-label required-field">École</label>
                                    <select class="form-select" id="ecole_id" name="ecole_id" required>
                                        <option value="">Choisir une école...</option>
                                        <?php foreach ($ecoles_sans_admin as $ecole_option): ?>
                                            <option value="<?php echo $ecole_option['id']; ?>">
                                                <?php echo htmlspecialchars($ecole_option['nom_ecole']); ?>
                                                <?php if ($ecole_option['sigle']): ?>
                                                    (<?php echo htmlspecialchars($ecole_option['sigle']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($ecoles_sans_admin)): ?>
                                        <div class="form-text text-warning">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            Toutes les écoles ont déjà un administrateur.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Informations personnelles -->
                        <div class="form-section">
                            <h6><i class="bi bi-person me-2"></i>Informations personnelles</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="prenom" class="form-label required-field">Prénom</label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" 
                                           value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Le prénom est obligatoire</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="nom" class="form-label required-field">Nom</label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Le nom est obligatoire</div>
                                </div>
                            </div>
                        </div>

                        <!-- Informations de contact -->
                        <div class="form-section">
                            <h6><i class="bi bi-envelope me-2"></i>Informations de contact</h6>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="email" class="form-label required-field">Adresse email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Cette adresse sera utilisée pour la connexion à l'application.
                                    </div>
                                    <div class="invalid-feedback">Format d'email invalide</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="telephone" class="form-label optional-field">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                                           value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Sécurité -->
                        <div class="form-section">
                            <h6><i class="bi bi-shield-lock me-2"></i>Sécurité</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="mot_de_passe" class="form-label required-field">Mot de passe</label>
                                    <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Minimum 8 caractères recommandés.
                                    </div>
                                    <div class="invalid-feedback">Le mot de passe est obligatoire</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirmer_mot_de_passe" class="form-label required-field">Confirmer le mot de passe</label>
                                    <input type="password" class="form-control" id="confirmer_mot_de_passe" name="confirmer_mot_de_passe" required>
                                    <div class="invalid-feedback">Les mots de passe ne correspondent pas</div>
                                </div>
                            </div>
                        </div>

                        <!-- Informations importantes -->
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>Informations importantes :</h6>
                            <ul class="mb-0">
                                <li>L'administrateur créé aura accès complet à la gestion de son école</li>
                                <li>Il pourra configurer l'école, gérer les utilisateurs et accéder à toutes les fonctionnalités</li>
                                <li>L'école sera automatiquement activée après la création de l'administrateur</li>
                                <li>L'administrateur recevra ses identifiants et pourra changer son mot de passe lors de la première connexion</li>
                            </ul>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="d-flex gap-3 justify-content-end">
                            <a href="../schools/index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-primary" 
                                    <?php echo (!$ecole_id && empty($ecoles_sans_admin)) ? 'disabled' : ''; ?>>
                                <i class="bi bi-person-plus me-2"></i>Créer l'Administrateur
                            </button>
                        </div>
                    </form>
                </div>
            </div>

                        <!-- Liste des écoles sans administrateur -->
            <?php if (!$ecole_id && !empty($ecoles_sans_admin)): ?>
                <div class="card info-card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-building me-2"></i>Écoles sans administrateur
                        </h5>
                        <small class="text-muted">Sélectionnez une école pour créer son administrateur</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="bi bi-building me-1"></i>École</th>
                                        <th><i class="bi bi-geo-alt me-1"></i>Localisation</th>
                                        <th><i class="bi bi-telephone me-1"></i>Contact</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ecoles_sans_admin as $ecole_item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <i class="bi bi-building text-primary fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <strong class="d-block"><?php echo htmlspecialchars($ecole_item['nom_ecole']); ?></strong>
                                                        <?php if ($ecole_item['sigle']): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars($ecole_item['sigle']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php if ($ecole_item['ville']): ?>
                                                        <strong><?php echo htmlspecialchars($ecole_item['ville']); ?></strong>
                                                        <?php if ($ecole_item['pays']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($ecole_item['pays']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Non renseigné</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($ecole_item['telephone']): ?>
                                                    <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($ecole_item['telephone']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Non renseigné</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="?ecole_id=<?php echo $ecole_item['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-person-plus me-1"></i>Créer Admin
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // Validation Bootstrap
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Validation côté client pour la confirmation du mot de passe
        document.getElementById('confirmer_mot_de_passe').addEventListener('input', function() {
            const password = document.getElementById('mot_de_passe').value;
            const confirm = this.value;
            
            if (password !== confirm) {
                this.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
