<?php
/**
 * Édition d'école - Interface Super Admin
 * Permet de modifier les informations d'une école
 */
require_once '../../includes/functions.php';

// Vérifier l'authentification et les droits Super Admin
requireAuth();
requireSuperAdmin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Récupérer l'ID de l'école depuis l'URL
$ecole_id = $_GET['id'] ?? 0;

if (!$ecole_id) {
    header('Location: index.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $nom_ecole = trim($_POST['nom_ecole'] ?? '');
        $code_ecole = trim($_POST['code_ecole'] ?? '');
        $sigle = trim($_POST['sigle'] ?? '');
        $type_etablissement = trim($_POST['type_etablissement'] ?? '');
        $regime = trim($_POST['regime'] ?? '');
        $type_enseignement = trim($_POST['type_enseignement'] ?? '');
        $langue_enseignement = trim($_POST['langue_enseignement'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $fax = trim($_POST['fax'] ?? '');
        $site_web = trim($_POST['site_web'] ?? '');
        $directeur_nom = trim($_POST['directeur_nom'] ?? '');
        $directeur_contact = trim($_POST['directeur_contact'] ?? '');
        $directeur_telephone = trim($_POST['directeur_telephone'] ?? '');
        $directeur_email = trim($_POST['directeur_email'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $pays = trim($_POST['pays'] ?? '');
        $bp = trim($_POST['bp'] ?? '');
        $devise_principale = trim($_POST['devise_principale'] ?? '');
        $fuseau_horaire = trim($_POST['fuseau_horaire'] ?? '');
        $numero_autorisation = trim($_POST['numero_autorisation'] ?? '');
        $date_autorisation = trim($_POST['date_autorisation'] ?? '');
        $description_etablissement = trim($_POST['description_etablissement'] ?? '');
        $notes_internes = trim($_POST['notes_internes'] ?? '');
        $validation_status = $_POST['validation_status'] ?? 'pending';
        $statut = $_POST['statut'] ?? 'actif';

        // Validation des champs obligatoires
        if (empty($nom_ecole)) {
            throw new Exception("Le nom de l'école est obligatoire");
        }

        if (empty($code_ecole)) {
            throw new Exception("Le code de l'école est obligatoire");
        }

        // Vérifier l'unicité du code école (sauf pour l'école en cours d'édition)
        $query = "SELECT COUNT(*) as total FROM ecoles WHERE code_ecole = :code_ecole AND id != :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['code_ecole' => $code_ecole, 'ecole_id' => $ecole_id]);
        if ($stmt->fetch()['total'] > 0) {
            throw new Exception("Ce code d'école est déjà utilisé par une autre école");
        }

        // Vérifier l'unicité de l'email si renseigné
        if (!empty($email)) {
            $query = "SELECT COUNT(*) as total FROM ecoles WHERE email = :email AND id != :ecole_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['email' => $email, 'ecole_id' => $ecole_id]);
            if ($stmt->fetch()['total'] > 0) {
                throw new Exception("Cet email est déjà utilisé par une autre école");
            }
        }

        // Mise à jour de l'école
        $query = "UPDATE ecoles SET 
                    nom_ecole = :nom_ecole,
                    code_ecole = :code_ecole,
                    sigle = :sigle,
                    type_etablissement = :type_etablissement,
                    regime = :regime,
                    type_enseignement = :type_enseignement,
                    langue_enseignement = :langue_enseignement,
                    email = :email,
                    telephone = :telephone,
                    fax = :fax,
                    site_web = :site_web,
                    directeur_nom = :directeur_nom,
                    directeur_contact = :directeur_contact,
                    directeur_telephone = :directeur_telephone,
                    directeur_email = :directeur_email,
                    adresse = :adresse,
                    ville = :ville,
                    province = :province,
                    pays = :pays,
                    bp = :bp,
                    devise_principale = :devise_principale,
                    fuseau_horaire = :fuseau_horaire,
                    numero_autorisation = :numero_autorisation,
                    date_autorisation = :date_autorisation,
                    description_etablissement = :description_etablissement,
                    notes_internes = :notes_internes,
                    validation_status = :validation_status,
                    statut = :statut,
                    updated_at = NOW(),
                    updated_by = :updated_by
                  WHERE id = :ecole_id";

        $stmt = $db->prepare($query);
        $stmt->execute([
            'nom_ecole' => $nom_ecole,
            'code_ecole' => $code_ecole,
            'sigle' => $sigle,
            'type_etablissement' => $type_etablissement,
            'regime' => $regime,
            'type_enseignement' => $type_enseignement,
            'langue_enseignement' => $langue_enseignement,
            'email' => $email ?: null,
            'telephone' => $telephone ?: null,
            'fax' => $fax ?: null,
            'site_web' => $site_web ?: null,
            'directeur_nom' => $directeur_nom ?: null,
            'directeur_contact' => $directeur_contact ?: null,
            'directeur_telephone' => $directeur_telephone ?: null,
            'directeur_email' => $directeur_email ?: null,
            'adresse' => $adresse ?: null,
            'ville' => $ville,
            'province' => $province ?: null,
            'pays' => $pays,
            'bp' => $bp ?: null,
            'devise_principale' => $devise_principale,
            'fuseau_horaire' => $fuseau_horaire,
            'numero_autorisation' => $numero_autorisation ?: null,
            'date_autorisation' => $date_autorisation ?: null,
            'description_etablissement' => $description_etablissement ?: null,
            'notes_internes' => $notes_internes ?: null,
            'validation_status' => $validation_status,
            'statut' => $statut,
            'updated_by' => $_SESSION['user_id'],
            'ecole_id' => $ecole_id
        ]);

        $message = "✅ École mise à jour avec succès !";
        $message_type = 'success';

        // Rediriger vers la page de visualisation après 2 secondes
        header("refresh:2;url=view.php?id=$ecole_id");

    } catch (Exception $e) {
        $message = "❌ Erreur lors de la mise à jour : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Récupérer les informations actuelles de l'école
try {
    $query = "SELECT * FROM ecoles WHERE id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['ecole_id' => $ecole_id]);
    $ecole = $stmt->fetch();

    if (!$ecole) {
        $message = "❌ École non trouvée !";
        $message_type = 'danger';
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    $message = "❌ Erreur lors de la récupération de l'école : " . $e->getMessage();
    $message_type = 'danger';
    $ecole = null;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'École - Super Admin - <?php echo APP_NAME; ?></title>
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
                <h1><i class="bi bi-pencil me-2"></i>Modifier l'École</h1>
                <p class="text-muted">
                    <?php if ($ecole): ?>
                        <?php echo htmlspecialchars($ecole['nom_ecole']); ?>
                    <?php else: ?>
                        École non trouvée
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="topbar-actions">
                <a href="view.php?id=<?php echo $ecole_id; ?>" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-eye"></i>
                    <span>Voir</span>
                </a>
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i>
                    <span>Retour à la liste</span>
                </a>
            </div>
        </header>
        
        <!-- Contenu du formulaire -->
        <div class="content-area">
            <!-- Messages de notification -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($ecole): ?>
                <form method="POST" class="needs-validation" novalidate>
                    <!-- Informations de base -->
                    <div class="form-section">
                        <h6><i class="bi bi-info-circle me-2"></i>Informations de base</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nom_ecole" class="form-label required-field">Nom de l'école</label>
                                <input type="text" class="form-control" id="nom_ecole" name="nom_ecole" 
                                       value="<?php echo htmlspecialchars($ecole['nom_ecole']); ?>" required>
                                <div class="invalid-feedback">Le nom de l'école est obligatoire</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="code_ecole" class="form-label required-field">Code école</label>
                                <input type="text" class="form-control" id="code_ecole" name="code_ecole" 
                                       value="<?php echo htmlspecialchars($ecole['code_ecole']); ?>" required>
                                <div class="invalid-feedback">Le code école est obligatoire</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="sigle" class="form-label optional-field">Sigle</label>
                                <input type="text" class="form-control" id="sigle" name="sigle" 
                                       value="<?php echo htmlspecialchars($ecole['sigle'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="type_etablissement" class="form-label optional-field">Type d'établissement</label>
                                <select class="form-select" id="type_etablissement" name="type_etablissement">
                                    <option value="">Sélectionner...</option>
                                    <option value="mixte" <?php echo ($ecole['type_etablissement'] ?? '') === 'mixte' ? 'selected' : ''; ?>>Mixte</option>
                                    <option value="masculin" <?php echo ($ecole['type_etablissement'] ?? '') === 'masculin' ? 'selected' : ''; ?>>Masculin</option>
                                    <option value="feminin" <?php echo ($ecole['type_etablissement'] ?? '') === 'feminin' ? 'selected' : ''; ?>>Féminin</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Caractéristiques pédagogiques -->
                    <div class="form-section">
                        <h6><i class="bi bi-book me-2"></i>Caractéristiques pédagogiques</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="regime" class="form-label optional-field">Régime</label>
                                <select class="form-select" id="regime" name="regime">
                                    <option value="">Sélectionner...</option>
                                    <option value="public" <?php echo ($ecole['regime'] ?? '') === 'public' ? 'selected' : ''; ?>>Public</option>
                                    <option value="privé" <?php echo ($ecole['regime'] ?? '') === 'privé' ? 'selected' : ''; ?>>Privé</option>
                                    <option value="confessionnel" <?php echo ($ecole['regime'] ?? '') === 'confessionnel' ? 'selected' : ''; ?>>Confessionnel</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="type_enseignement" class="form-label optional-field">Type d'enseignement</label>
                                <input type="text" class="form-control" id="type_enseignement" name="type_enseignement" 
                                       value="<?php echo htmlspecialchars($ecole['type_enseignement'] ?? ''); ?>"
                                       placeholder="ex: primaire, secondaire">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="langue_enseignement" class="form-label optional-field">Langue d'enseignement</label>
                                <select class="form-select" id="langue_enseignement" name="langue_enseignement">
                                    <option value="">Sélectionner...</option>
                                    <option value="français" <?php echo ($ecole['langue_enseignement'] ?? '') === 'français' ? 'selected' : ''; ?>>Français</option>
                                    <option value="anglais" <?php echo ($ecole['langue_enseignement'] ?? '') === 'anglais' ? 'selected' : ''; ?>>Anglais</option>
                                    <option value="lingala" <?php echo ($ecole['langue_enseignement'] ?? '') === 'lingala' ? 'selected' : ''; ?>>Lingala</option>
                                    <option value="swahili" <?php echo ($ecole['langue_enseignement'] ?? '') === 'swahili' ? 'selected' : ''; ?>>Swahili</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="devise_principale" class="form-label optional-field">Devise</label>
                                <select class="form-select" id="devise_principale" name="devise_principale">
                                    <option value="">Sélectionner...</option>
                                    <option value="CDF" <?php echo ($ecole['devise_principale'] ?? '') === 'CDF' ? 'selected' : ''; ?>>CDF (Franc congolais)</option>
                                    <option value="USD" <?php echo ($ecole['devise_principale'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD (Dollar américain)</option>
                                    <option value="EUR" <?php echo ($ecole['devise_principale'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Contact et communication -->
                    <div class="form-section">
                        <h6><i class="bi bi-telephone me-2"></i>Contact et communication</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label optional-field">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($ecole['email'] ?? ''); ?>">
                                <div class="invalid-feedback">Format d'email invalide</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="telephone" class="form-label optional-field">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" 
                                       value="<?php echo htmlspecialchars($ecole['telephone'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="fax" class="form-label optional-field">Fax</label>
                                <input type="tel" class="form-control" id="fax" name="fax" 
                                       value="<?php echo htmlspecialchars($ecole['fax'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="site_web" class="form-label optional-field">Site web</label>
                                <input type="url" class="form-control" id="site_web" name="site_web" 
                                       value="<?php echo htmlspecialchars($ecole['site_web'] ?? ''); ?>">
                                <div class="invalid-feedback">Format d'URL invalide</div>
                            </div>
                        </div>
                    </div>

                    <!-- Direction -->
                    <div class="form-section">
                        <h6><i class="bi bi-person-badge me-2"></i>Direction</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="directeur_nom" class="form-label optional-field">Nom du directeur</label>
                                <input type="text" class="form-control" id="directeur_nom" name="directeur_nom" 
                                       value="<?php echo htmlspecialchars($ecole['directeur_nom'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="directeur_contact" class="form-label optional-field">Contact directeur</label>
                                <input type="text" class="form-control" id="directeur_contact" name="directeur_contact" 
                                       value="<?php echo htmlspecialchars($ecole['directeur_contact'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="directeur_telephone" class="form-label optional-field">Téléphone directeur</label>
                                <input type="tel" class="form-control" id="directeur_telephone" name="directeur_telephone" 
                                       value="<?php echo htmlspecialchars($ecole['directeur_telephone'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="directeur_email" class="form-label optional-field">Email directeur</label>
                                <input type="email" class="form-control" id="directeur_email" name="directeur_email" 
                                       value="<?php echo htmlspecialchars($ecole['directeur_email'] ?? ''); ?>">
                                <div class="invalid-feedback">Format d'email invalide</div>
                            </div>
                        </div>
                    </div>

                    <!-- Localisation -->
                    <div class="form-section">
                        <h6><i class="bi bi-geo-alt me-2"></i>Localisation</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="adresse" class="form-label optional-field">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="3"><?php echo htmlspecialchars($ecole['adresse'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="ville" class="form-label required-field">Ville</label>
                                <input type="text" class="form-control" id="ville" name="ville" 
                                       value="<?php echo htmlspecialchars($ecole['ville'] ?? ''); ?>" required>
                                <div class="invalid-feedback">La ville est obligatoire</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="province" class="form-label optional-field">Province</label>
                                <input type="text" class="form-control" id="province" name="province" 
                                       value="<?php echo htmlspecialchars($ecole['province'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="pays" class="form-label required-field">Pays</label>
                                <input type="text" class="form-control" id="pays" name="pays" 
                                       value="<?php echo htmlspecialchars($ecole['pays'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Le pays est obligatoire</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="bp" class="form-label optional-field">Boîte postale</label>
                                <input type="text" class="form-control" id="bp" name="bp" 
                                       value="<?php echo htmlspecialchars($ecole['bp'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="fuseau_horaire" class="form-label optional-field">Fuseau horaire</label>
                                <select class="form-select" id="fuseau_horaire" name="fuseau_horaire">
                                    <option value="">Sélectionner...</option>
                                    <option value="Africa/Kinshasa" <?php echo ($ecole['fuseau_horaire'] ?? '') === 'Africa/Kinshasa' ? 'selected' : ''; ?>>Africa/Kinshasa (UTC+1)</option>
                                    <option value="Africa/Lubumbashi" <?php echo ($ecole['fuseau_horaire'] ?? '') === 'Africa/Lubumbashi' ? 'selected' : ''; ?>>Africa/Lubumbashi (UTC+2)</option>
                                    <option value="UTC" <?php echo ($ecole['fuseau_horaire'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Autorisation et description -->
                    <div class="form-section">
                        <h6><i class="bi bi-file-text me-2"></i>Autorisation et description</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="numero_autorisation" class="form-label optional-field">Numéro d'autorisation</label>
                                <input type="text" class="form-control" id="numero_autorisation" name="numero_autorisation" 
                                       value="<?php echo htmlspecialchars($ecole['numero_autorisation'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="date_autorisation" class="form-label optional-field">Date d'autorisation</label>
                                <input type="date" class="form-control" id="date_autorisation" name="date_autorisation" 
                                       value="<?php echo $ecole['date_autorisation'] ?? ''; ?>">
                            </div>
                            
                            <div class="col-md-12">
                                <label for="description_etablissement" class="form-label optional-field">Description de l'établissement</label>
                                <textarea class="form-control" id="description_etablissement" name="description_etablissement" rows="4"><?php echo htmlspecialchars($ecole['description_etablissement'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Statuts et configuration -->
                    <div class="form-section">
                        <h6><i class="bi bi-gear me-2"></i>Statuts et configuration</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="validation_status" class="form-label required-field">Statut de validation</label>
                                <select class="form-select" id="validation_status" name="validation_status" required>
                                    <option value="pending" <?php echo ($ecole['validation_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                    <option value="approved" <?php echo ($ecole['validation_status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approuvée</option>
                                    <option value="rejected" <?php echo ($ecole['validation_status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejetée</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="statut" class="form-label required-field">Statut d'activation</label>
                                <select class="form-select" id="statut" name="statut" required>
                                    <option value="actif" <?php echo ($ecole['statut'] ?? '') === 'actif' ? 'selected' : ''; ?>>Actif</option>
                                    <option value="inactif" <?php echo ($ecole['statut'] ?? '') === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                                    <option value="suspendu" <?php echo ($ecole['statut'] ?? '') === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                                </select>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="notes_internes" class="form-label optional-field">Notes internes</label>
                                <textarea class="form-control" id="notes_internes" name="notes_internes" rows="3" 
                                          placeholder="Notes privées pour les Super Admins..."><?php echo htmlspecialchars($ecole['notes_internes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="d-flex gap-3 justify-content-end mb-4">
                        <a href="view.php?id=<?php echo $ecole_id; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
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
    </script>
</body>
</html>
