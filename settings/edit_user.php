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
$user = null;

// Récupérer l'ID de l'utilisateur à modifier
$user_id = intval($_GET['id'] ?? 0);

if (!$user_id) {
    redirect('users.php');
}

// Récupérer les informations de l'utilisateur
try {
    $user_query = "SELECT u.*, r.code as role_code, r.libelle as role_libelle 
                   FROM utilisateurs u 
                   JOIN roles r ON u.role_id = r.id 
                   WHERE u.id = :user_id AND u.ecole_id = :ecole_id AND u.statut != 'supprimé_logique'";
    $stmt = $db->prepare($user_query);
    $stmt->execute(['user_id' => $user_id, 'ecole_id' => $_SESSION['ecole_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = "Utilisateur non trouvé ou non autorisé.";
        redirect('users.php');
    }
    
    // Empêcher la modification de son propre compte
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "Vous ne pouvez pas modifier votre propre compte depuis cette interface.";
        redirect('users.php');
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur lors de la récupération de l'utilisateur: " . $e->getMessage();
    redirect('users.php');
}

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $nom = sanitize($_POST['nom'] ?? '');
        $prenom = sanitize($_POST['prenom'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $telephone = sanitize($_POST['telephone'] ?? '');
        $role_id = intval($_POST['role_id'] ?? 0);
        $actif = isset($_POST['actif']) ? 1 : 0;
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Validation des champs obligatoires
        if (empty($nom) || empty($prenom) || empty($email) || empty($role_id)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Validation de l'email
        if (!validateEmail($email)) {
            throw new Exception("L'adresse email n'est pas valide.");
        }
        
        // Vérifier que l'email n'existe pas déjà (sauf pour cet utilisateur)
        $check_email = "SELECT COUNT(*) FROM utilisateurs WHERE email = :email AND ecole_id = :ecole_id AND id != :user_id";
        $stmt = $db->prepare($check_email);
        $stmt->execute(['email' => $email, 'ecole_id' => $_SESSION['ecole_id'], 'user_id' => $user_id]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Cette adresse email est déjà utilisée par un autre utilisateur dans votre école.");
        }
        
        // Vérifier que le rôle existe et est valide
        $check_role = "SELECT * FROM roles WHERE id = :role_id AND statut = 'actif'";
        $stmt = $db->prepare($check_role);
        $stmt->execute(['role_id' => $role_id]);
        $role = $stmt->fetch();
        
        if (!$role) {
            throw new Exception("Le rôle sélectionné n'est pas valide.");
        }
        
        // Mettre à jour l'utilisateur
        $db->beginTransaction();
        
        $update_user = "UPDATE utilisateurs SET 
            nom = :nom, prenom = :prenom, email = :email, telephone = :telephone,
            role_id = :role_id, actif = :actif, notes_internes = :notes,
            updated_at = NOW(), updated_by = :updated_by 
            WHERE id = :user_id";
        
        $stmt = $db->prepare($update_user);
        $result = $stmt->execute([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'role_id' => $role_id,
            'actif' => $actif,
            'notes' => $notes,
            'updated_by' => $_SESSION['user_id'],
            'user_id' => $user_id
        ]);
        
        if ($result) {
            // Log de l'action
            logUserAction('UPDATE_USER', "Modification de l'utilisateur: $prenom $nom ($email)");
            
            $db->commit();
            
            $success = "Utilisateur modifié avec succès !";
            
            // Mettre à jour les données affichées
            $user['nom'] = $nom;
            $user['prenom'] = $prenom;
            $user['email'] = $email;
            $user['telephone'] = $telephone;
            $user['role_id'] = $role_id;
            $user['actif'] = $actif;
            $user['notes_internes'] = $notes;
            $user['role_libelle'] = $role['libelle'];
            
        } else {
            throw new Exception("Erreur lors de la modification de l'utilisateur.");
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

$page_title = "Modifier l'Utilisateur";
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
        .user-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .role-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }
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
                <h1><i class="bi bi-person-gear me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Modifiez les informations de l'utilisateur</p>
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
                <!-- Informations actuelles de l'utilisateur -->
                <div class="col-lg-4 mb-4">
                    <div class="card user-info-card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="bi bi-person-circle display-1"></i>
                            </div>
                            <h4><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h4>
                            <p class="mb-2">
                                <span class="badge bg-light text-dark role-badge">
                                    <?php echo htmlspecialchars($user['role_libelle']); ?>
                                </span>
                            </p>
                            <p class="mb-2">
                                <span class="status-indicator <?php echo $user['actif'] ? 'status-active' : 'status-inactive'; ?>"></span>
                                <?php echo $user['actif'] ? 'Compte actif' : 'Compte inactif'; ?>
                            </p>
                            <hr class="border-light">
                            <div class="text-start">
                                <p class="mb-1">
                                    <i class="bi bi-envelope me-2"></i>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </p>
                                <?php if ($user['telephone']): ?>
                                    <p class="mb-1">
                                        <i class="bi bi-telephone me-2"></i>
                                        <?php echo htmlspecialchars($user['telephone']); ?>
                                    </p>
                                <?php endif; ?>
                                <p class="mb-1">
                                    <i class="bi bi-calendar me-2"></i>
                                    Créé le <?php echo formatDate($user['created_at']); ?>
                                </p>
                                <?php if ($user['derniere_connexion_at']): ?>
                                    <p class="mb-1">
                                        <i class="bi bi-clock me-2"></i>
                                        Dernière connexion : <?php echo formatDateTime($user['derniere_connexion_at']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Actions rapides</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="?action=reset_password&user_id=<?php echo $user['id']; ?>" 
                                   class="btn btn-outline-info btn-sm"
                                   onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur ?')">
                                    <i class="bi bi-key me-2"></i>Réinitialiser mot de passe
                                </a>
                                
                                <a href="?action=toggle_status&user_id=<?php echo $user['id']; ?>" 
                                   class="btn btn-outline-<?php echo $user['actif'] ? 'warning' : 'success'; ?> btn-sm"
                                   onclick="return confirm('Êtes-vous sûr de vouloir <?php echo $user['actif'] ? 'désactiver' : 'activer'; ?> cet utilisateur ?')">
                                    <i class="bi bi-<?php echo $user['actif'] ? 'pause' : 'play'; ?> me-2"></i>
                                    <?php echo $user['actif'] ? 'Désactiver' : 'Activer'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Formulaire de modification -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Modifier les informations</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="editUserForm">
                                <div class="row">
                                    <!-- Nom -->
                                    <div class="col-md-6 mb-3">
                                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="nom" name="nom" 
                                               value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                                    </div>
                                    
                                    <!-- Prénom -->
                                    <div class="col-md-6 mb-3">
                                        <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" 
                                               value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Email -->
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        <div class="form-text">L'utilisateur utilisera cet email pour se connecter</div>
                                    </div>
                                    
                                    <!-- Téléphone -->
                                    <div class="col-md-6 mb-3">
                                        <label for="telephone" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                                               value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- Sélection du rôle -->
                                <div class="mb-4">
                                    <label for="role_id" class="form-label">Rôle <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role_id" name="role_id" required>
                                        <option value="">Sélectionnez un rôle</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>" 
                                                    <?php echo $user['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role['libelle']); ?> 
                                                - <?php echo htmlspecialchars($role['description'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Statut du compte -->
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="actif" name="actif" 
                                               <?php echo $user['actif'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="actif">
                                            Compte actif
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Un compte inactif ne peut pas se connecter à la plateforme
                                    </div>
                                </div>
                                
                                <!-- Notes -->
                                <div class="mb-4">
                                    <label for="notes" class="form-label">Notes internes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Informations supplémentaires sur cet utilisateur..."><?php echo htmlspecialchars($user['notes_internes'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Boutons d'action -->
                                <div class="d-flex justify-content-between">
                                    <a href="users.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-2"></i>Enregistrer les modifications
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>

