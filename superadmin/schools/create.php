<?php
/**
 * Création d'école - Interface Super Admin
 * Permet au Super Admin de créer une école directement
 */
require_once '../../includes/functions.php';

// Vérifier l'authentification et les droits Super Admin
requireAuth();
requireSuperAdmin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_ecole = sanitize($_POST['nom_ecole'] ?? '');
    $code_ecole = sanitize($_POST['code_ecole'] ?? '');
    $adresse = sanitize($_POST['adresse'] ?? '');
    $telephone = sanitize($_POST['telephone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $directeur_nom = sanitize($_POST['directeur_nom'] ?? '');
    
    // Validation
    if (empty($nom_ecole)) {
        $error = 'Le nom de l\'école est obligatoire.';
    } elseif (empty($code_ecole)) {
        $error = 'Le code de l\'école est obligatoire.';
    } else {
        try {
            // Vérifier si le code école existe déjà
            $query = "SELECT id FROM ecoles WHERE code_ecole = :code_ecole";
            $stmt = $db->prepare($query);
            $stmt->execute(['code_ecole' => $code_ecole]);
            if ($stmt->fetch()) {
                $error = 'Une école avec ce code existe déjà.';
            }
            
            // Vérifier si l'email existe déjà (seulement si fourni)
            if (empty($error) && !empty($email)) {
                $query = "SELECT id FROM ecoles WHERE email = :email";
                $stmt = $db->prepare($query);
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                    $error = 'Une école avec cet email existe déjà.';
                }
            }
            
            if (empty($error)) {
                // Créer l'école avec TOUS les champs requis
                $query = "INSERT INTO ecoles (nom_ecole, code_ecole, adresse, telephone, email, directeur_nom, 
                                           type_etablissement, pays, regime, type_enseignement, langue_enseignement,
                                           devise_principale, fuseau_horaire, validation_status, super_admin_validated, 
                                           date_creation_ecole, created_by) 
                         VALUES (:nom_ecole, :code_ecole, :adresse, :telephone, :email, :directeur_nom,
                                 'mixte', 'RD Congo', 'privé', 'primaire,secondaire', 'français',
                                 'CDF', 'Africa/Kinshasa', 'approved', TRUE, NOW(), :created_by)";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    'nom_ecole' => $nom_ecole,
                    'code_ecole' => $code_ecole,
                    'adresse' => $adresse ?: NULL,
                    'telephone' => $telephone ?: NULL,
                    'email' => $email ?: NULL,
                    'directeur_nom' => $directeur_nom ?: NULL,
                    'created_by' => $_SESSION['user_id']
                ]);
                
                $ecole_id = $db->lastInsertId();
                
                $message = "✅ École '{$nom_ecole}' créée avec succès ! ID: {$ecole_id}, Code: {$code_ecole}";
                $message_type = 'success';
                
                // Réinitialiser le formulaire
                $_POST = [];
            }
        } catch (Exception $e) {
            $error = 'Erreur système : ' . $e->getMessage();
        }
    }
    
    if (!empty($error)) {
        $message = $error;
        $message_type = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une École - Super Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/dashboard.css" rel="stylesheet">
    <link href="../../assets/css/common.css" rel="stylesheet">
    <style>
        .sidebar-logo i { color: #ffd700; }
        .user-avatar i { color: #ffd700; }
        .menu-item.active .menu-link { border-left-color: #ffd700; }
        .menu-link:hover { border-left-color: #ffd700; }
        .create-form { max-width: 600px; margin: 0 auto; }
        .required-label::after { content: " *"; color: #dc3545; }
        .code-preview { font-family: monospace; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; }
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
            
            <li class="menu-item active">
                <a href="index.php" class="menu-link">
                    <i class="bi bi-building"></i>
                    <span>Écoles</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="requests.php" class="menu-link">
                    <i class="bi bi-envelope"></i>
                    <span>Demandes</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../users/create-admin.php" class="menu-link">
                    <i class="bi bi-person-plus"></i>
                    <span>Créer Admin</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../users/super-admins.php" class="menu-link">
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
                <h1><i class="bi bi-plus-circle me-2"></i>Créer une École</h1>
                <p class="text-muted">Ajouter une nouvelle école au système Naklass</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i>
                    <span>Retour à la liste</span>
                </a>
            </div>
        </header>
        
        <!-- Contenu du tableau de bord -->
        <div class="content-area">
            <!-- Messages de notification -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Formulaire de création -->
            <div class="card create-form">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-building-add me-2"></i>
                        Informations de l'école
                    </h5>
                </div>
                
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="nom_ecole" class="form-label required-label">Nom de l'école</label>
                            <input type="text" class="form-control" id="nom_ecole" name="nom_ecole" 
                                   value="<?php echo htmlspecialchars($_POST['nom_ecole'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="code_ecole" class="form-label required-label">Code de l'école</label>
                            <input type="text" class="form-control" id="code_ecole" name="code_ecole" 
                                   value="<?php echo htmlspecialchars($_POST['code_ecole'] ?? ''); ?>" 
                                   placeholder="Ex: ECOLE_SAINTE_MARIE" required
                                   pattern="[A-Z0-9_]+" title="Lettres majuscules, chiffres et underscores uniquement">
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Code unique pour identifier l'école. Format recommandé : <span class="code-preview">ECOLE_NOM</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="3"
                                      placeholder="Adresse complète de l'école"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                                           value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>"
                                           placeholder="+243 XXX XXX XXX">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           placeholder="contact@ecole.edu">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="directeur_nom" class="form-label">Nom du directeur</label>
                            <input type="text" class="form-control" id="directeur_nom" name="directeur_nom" 
                                   value="<?php echo htmlspecialchars($_POST['directeur_nom'] ?? ''); ?>"
                                   placeholder="Nom complet du directeur">
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note :</strong> L'école sera créée avec le statut "Approuvée" et validée par le Super Admin.
                            Vous pourrez ensuite créer son administrateur depuis la liste des écoles.
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Créer l'école
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-2"></i>Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    
    <script>
        // Génération automatique du code école basé sur le nom
        document.getElementById('nom_ecole').addEventListener('input', function() {
            const nom = this.value;
            const codeField = document.getElementById('code_ecole');
            
            if (nom && !codeField.value) {
                // Générer un code basé sur le nom
                let code = nom.toUpperCase()
                    .replace(/[^A-Z0-9]/g, '_')
                    .replace(/_+/g, '_')
                    .replace(/^_|_$/g, '');
                
                // Limiter la longueur
                if (code.length > 20) {
                    code = code.substring(0, 20);
                }
                
                codeField.value = code;
            }
        });
    </script>
</body>
</html>
