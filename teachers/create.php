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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'matricule_enseignant' => sanitize($_POST['matricule_enseignant'] ?? ''),
        'nom' => sanitize($_POST['nom'] ?? ''),
        'prenom' => sanitize($_POST['prenom'] ?? ''),
        'sexe' => sanitize($_POST['sexe'] ?? ''),
        'date_naissance' => sanitize($_POST['date_naissance'] ?? ''),
        'lieu_naissance' => sanitize($_POST['lieu_naissance'] ?? ''),
        'nationalite' => sanitize($_POST['nationalite'] ?? 'Congolaise'),
        'telephone' => sanitize($_POST['telephone'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'adresse_complete' => sanitize($_POST['adresse_complete'] ?? ''),
        'specialites' => $_POST['specialites'] ?? [],
        'diplomes' => sanitize($_POST['diplomes'] ?? ''),
        'experience_annees' => intval($_POST['experience_annees'] ?? 0),
        'date_embauche' => sanitize($_POST['date_embauche'] ?? ''),
        'statut_record' => sanitize($_POST['statut_record'] ?? 'actif'),
        'notes_internes' => sanitize($_POST['notes_internes'] ?? ''),
        // Champs pour le compte utilisateur
        'create_user_account' => isset($_POST['create_user_account']),
        'user_email' => sanitize($_POST['user_email'] ?? ''),
        'user_password' => sanitize($_POST['user_password'] ?? ''),
        'user_username' => sanitize($_POST['user_username'] ?? '')
    ];
    
    // Validation
    if (empty($data['matricule_enseignant'])) {
        $errors[] = "Le matricule de l'enseignant est obligatoire.";
    }
    
    if (empty($data['nom'])) {
        $errors[] = "Le nom est obligatoire.";
    }
    
    if (empty($data['prenom'])) {
        $errors[] = "Le prénom est obligatoire.";
    }
    
    if (empty($data['sexe'])) {
        $errors[] = "Le sexe est obligatoire.";
    }
    
    if (!empty($data['email']) && !validateEmail($data['email'])) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    
    if ($data['experience_annees'] < 0) {
        $errors[] = "L'expérience ne peut pas être négative.";
    }
    
    // Validation pour le compte utilisateur
    if ($data['create_user_account']) {
        if (empty($data['user_email'])) {
            $errors[] = "L'email du compte utilisateur est obligatoire si vous créez un compte.";
        } elseif (!validateEmail($data['user_email'])) {
            $errors[] = "L'adresse email du compte utilisateur n'est pas valide.";
        }
        
        if (empty($data['user_password'])) {
            $errors[] = "Le mot de passe du compte utilisateur est obligatoire.";
        } elseif (strlen($data['user_password']) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
        }
        
        // Vérifier la confirmation du mot de passe
        $confirm_password = sanitize($_POST['confirm_password'] ?? '');
        if ($data['user_password'] !== $confirm_password) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }
        
        if (empty($data['user_username'])) {
            $errors[] = "Le nom d'utilisateur est obligatoire si vous créez un compte.";
        }
        
        // Vérifier l'unicité de l'email utilisateur
        if (!empty($data['user_email'])) {
            $check_email_query = "SELECT id FROM utilisateurs WHERE email = :email";
            $stmt = $db->prepare($check_email_query);
            $stmt->execute(['email' => $data['user_email']]);
            
            if ($stmt->fetch()) {
                $errors[] = "Cette adresse email est déjà utilisée par un autre utilisateur.";
            }
        }
        
        // Vérifier l'unicité du nom d'utilisateur
        if (!empty($data['user_username'])) {
            $check_username_query = "SELECT id FROM utilisateurs WHERE nom = :username AND ecole_id = :ecole_id";
            $stmt = $db->prepare($check_username_query);
            $stmt->execute([
                'username' => $data['user_username'],
                'ecole_id' => $_SESSION['ecole_id']
            ]);
            
            if ($stmt->fetch()) {
                $errors[] = "Ce nom d'utilisateur est déjà utilisé dans votre école.";
            }
        }
    }
    
    // Vérifier l'unicité du matricule
    if (!empty($data['matricule_enseignant'])) {
        $check_query = "SELECT id FROM enseignants WHERE matricule_enseignant = :matricule AND ecole_id = :ecole_id";
        $stmt = $db->prepare($check_query);
        $stmt->execute([
            'matricule' => $data['matricule_enseignant'],
            'ecole_id' => $_SESSION['ecole_id']
        ]);
        
        if ($stmt->fetch()) {
            $errors[] = "Ce matricule d'enseignant existe déjà.";
        }
    }
    
    // Traitement des spécialités
    if (!empty($data['specialites']) && is_array($data['specialites'])) {
        $data['specialites'] = array_filter($data['specialites']); // Supprimer les vides
        $data['specialites'] = json_encode($data['specialites']);
    } else {
        $data['specialites'] = json_encode([]);
    }
    
    // Traitement de la photo
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/teachers/';
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = $_FILES['photo'];
        $file_extension = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Validation du type de fichier
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Le format de fichier n'est pas supporté. Utilisez JPG, PNG ou GIF.";
        }
        
        // Validation de la taille (2MB max)
        if ($file_info['size'] > 2 * 1024 * 1024) {
            $errors[] = "La taille du fichier ne doit pas dépasser 2MB.";
        }
        
        // Validation de l'image
        $image_info = getimagesize($file_info['tmp_name']);
        if ($image_info === false) {
            $errors[] = "Le fichier n'est pas une image valide.";
        }
        
        if (empty($errors)) {
            // Générer un nom de fichier unique
            $filename = uniqid() . '_' . time() . '.' . $file_extension;
            $photo_path = $upload_dir . $filename;
            
            // Déplacer le fichier
            if (!move_uploaded_file($file_info['tmp_name'], $photo_path)) {
                $errors[] = "Erreur lors du téléchargement de la photo.";
            } else {
                // Convertir le chemin relatif pour la base de données
                $photo_path = 'uploads/teachers/' . $filename;
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $utilisateur_id = null;
            
            // Créer le compte utilisateur si demandé
            if ($data['create_user_account']) {
                $user_password_hash = password_hash($data['user_password'], PASSWORD_DEFAULT);
                
                $insert_user_query = "INSERT INTO utilisateurs (
                    ecole_id, role_id, nom, prenom, email, telephone, mot_de_passe_hash, actif, created_by
                ) VALUES (
                    :ecole_id, 3, :nom, :prenom, :email, :telephone, :mot_de_passe_hash, 1, :created_by
                )";
                
                $user_stmt = $db->prepare($insert_user_query);
                $user_stmt->execute([
                    'ecole_id' => $_SESSION['ecole_id'],
                    'nom' => $data['user_username'],
                    'prenom' => $data['prenom'],
                    'email' => $data['user_email'],
                    'telephone' => $data['telephone'] ?: null,
                    'mot_de_passe_hash' => $user_password_hash,
                    'created_by' => $_SESSION['user_id']
                ]);
                
                $utilisateur_id = $db->lastInsertId();
            }
            
            // Créer l'enseignant
            $insert_query = "INSERT INTO enseignants (
                ecole_id, utilisateur_id, matricule_enseignant, nom, prenom, sexe, date_naissance, lieu_naissance,
                nationalite, telephone, email, adresse_complete, specialites, diplomes,
                experience_annees, date_embauche, statut_record, notes_internes, photo_path, created_by
            ) VALUES (
                :ecole_id, :utilisateur_id, :matricule_enseignant, :nom, :prenom, :sexe, :date_naissance, :lieu_naissance,
                :nationalite, :telephone, :email, :adresse_complete, :specialites, :diplomes,
                :experience_annees, :date_embauche, :statut_record, :notes_internes, :photo_path, :created_by
            )";
            
            $stmt = $db->prepare($insert_query);
            $stmt->execute([
                'ecole_id' => $_SESSION['ecole_id'],
                'utilisateur_id' => $utilisateur_id,
                'matricule_enseignant' => $data['matricule_enseignant'],
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'sexe' => $data['sexe'],
                'date_naissance' => $data['date_naissance'] ?: null,
                'lieu_naissance' => $data['lieu_naissance'] ?: null,
                'nationalite' => $data['nationalite'],
                'telephone' => $data['telephone'] ?: null,
                'email' => $data['email'] ?: null,
                'adresse_complete' => $data['adresse_complete'] ?: null,
                'specialites' => $data['specialites'],
                'diplomes' => $data['diplomes'] ?: null,
                'experience_annees' => $data['experience_annees'] ?: null,
                'date_embauche' => $data['date_embauche'] ?: null,
                'statut_record' => $data['statut_record'],
                'notes_internes' => $data['notes_internes'] ?: null,
                'photo_path' => $photo_path,
                'created_by' => $_SESSION['user_id']
            ]);
            
            $db->commit();
            
            $success_message = "L'enseignant '{$data['prenom']} {$data['nom']}' a été créé avec succès.";
            if ($data['create_user_account']) {
                $success_message .= " Un compte utilisateur a également été créé avec l'email : {$data['user_email']}";
            }
            
            setFlashMessage('success', $success_message);
            redirect('index.php');
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Erreur lors de la création: " . $e->getMessage();
        }
    }
}

// Récupérer les spécialités disponibles
$specialites_disponibles = [
    'Mathématiques', 'Physique', 'Chimie', 'Biologie', 'Histoire', 'Géographie',
    'Français', 'Anglais', 'Espagnol', 'Allemand', 'Latin', 'Grec',
    'Philosophie', 'Sciences économiques', 'Sciences sociales', 'Informatique',
    'Technologie', 'Arts plastiques', 'Musique', 'Éducation physique',
    'Sciences de la vie et de la terre', 'Sciences physiques', 'Économie',
    'Gestion', 'Comptabilité', 'Marketing', 'Droit', 'Psychologie'
];

$page_title = "Nouvel Enseignant";
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
        .form-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .specialite-tag {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
            border-radius: 15px;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .specialite-tag:hover {
            background: #bbdefb;
            transform: translateY(-2px);
        }
        
        .specialite-tag.selected {
            background: #1976d2;
            color: white;
            border-color: #1976d2;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
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
                <p class="text-muted">Ajoutez un nouvel enseignant à votre établissement</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <?php
            $flash_messages = getFlashMessages();
            foreach ($flash_messages as $type => $message):
            ?>
                <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            
            <!-- Messages d'erreur -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <!-- Informations personnelles -->
                <div class="form-section p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-person me-2"></i>Informations personnelles
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="matricule_enseignant" class="form-label required-field">Matricule</label>
                            <input type="text" class="form-control" id="matricule_enseignant" name="matricule_enseignant" 
                                   value="<?php echo htmlspecialchars($_POST['matricule_enseignant'] ?? ''); ?>" required>
                            <div class="form-text">Identifiant unique de l'enseignant</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="sexe" class="form-label required-field">Sexe</label>
                            <select class="form-select" id="sexe" name="sexe" required>
                                <option value="">Sélectionnez...</option>
                                <option value="M" <?php echo ($_POST['sexe'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?php echo ($_POST['sexe'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                                <option value="Autre" <?php echo ($_POST['sexe'] ?? '') === 'Autre' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="nom" class="form-label required-field">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" 
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="prenom" class="form-label required-field">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" 
                                   value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="date_naissance" class="form-label">Date de naissance</label>
                            <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="lieu_naissance" class="form-label">Lieu de naissance</label>
                            <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['lieu_naissance'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="nationalite" class="form-label">Nationalité</label>
                            <input type="text" class="form-control" id="nationalite" name="nationalite" 
                                   value="<?php echo htmlspecialchars($_POST['nationalite'] ?? 'Congolaise'); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Informations de contact -->
                <div class="form-section p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-envelope me-2"></i>Informations de contact
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" 
                                   value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-12">
                            <label for="adresse_complete" class="form-label">Adresse complète</label>
                            <textarea class="form-control" id="adresse_complete" name="adresse_complete" rows="3"><?php echo htmlspecialchars($_POST['adresse_complete'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Informations professionnelles -->
                <div class="form-section p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-briefcase me-2"></i>Informations professionnelles
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Spécialités</label>
                            <div class="specialites-container mb-3">
                                <?php foreach ($specialites_disponibles as $specialite): ?>
                                    <span class="specialite-tag" data-specialite="<?php echo htmlspecialchars($specialite); ?>">
                                        <?php echo htmlspecialchars($specialite); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="specialites[]" id="specialites_input">
                            <div class="form-text">Cliquez sur les spécialités pour les sélectionner</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="diplomes" class="form-label">Diplômes</label>
                            <textarea class="form-control" id="diplomes" name="diplomes" rows="3"><?php echo htmlspecialchars($_POST['diplomes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="experience_annees" class="form-label">Années d'expérience</label>
                            <input type="number" class="form-control" id="experience_annees" name="experience_annees" 
                                   value="<?php echo htmlspecialchars($_POST['experience_annees'] ?? ''); ?>" min="0" max="50">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="date_embauche" class="form-label">Date d'embauche</label>
                            <input type="date" class="form-control" id="date_embauche" name="date_embauche" 
                                   value="<?php echo htmlspecialchars($_POST['date_embauche'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="statut_record" class="form-label">Statut</label>
                            <select class="form-select" id="statut_record" name="statut_record">
                                <option value="actif" <?php echo ($_POST['statut_record'] ?? 'actif') === 'actif' ? 'selected' : ''; ?>>Actif</option>
                                <option value="suspendu" <?php echo ($_POST['statut_record'] ?? '') === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                                <option value="congé" <?php echo ($_POST['statut_record'] ?? '') === 'congé' ? 'selected' : ''; ?>>Congé</option>
                                <option value="retraité" <?php echo ($_POST['statut_record'] ?? '') === 'retraité' ? 'selected' : ''; ?>>Retraité</option>
                                <option value="démissionné" <?php echo ($_POST['statut_record'] ?? '') === 'démissionné' ? 'selected' : ''; ?>>Démissionné</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label for="notes_internes" class="form-label">Notes internes</label>
                            <textarea class="form-control" id="notes_internes" name="notes_internes" rows="3"><?php echo htmlspecialchars($_POST['notes_internes'] ?? ''); ?></textarea>
                            <div class="form-text">Informations confidentielles sur l'enseignant</div>
                        </div>
                        
                        <div class="col-12">
                            <label for="photo" class="form-label">Photo de l'enseignant</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            <div class="form-text">Formats acceptés : JPG, PNG, GIF. Taille maximale : 2MB</div>
                            <div id="photo-preview" class="mt-2" style="display: none;">
                                <img id="preview-img" src="" alt="Aperçu" style="max-width: 150px; max-height: 150px; border-radius: 8px;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Compte utilisateur -->
                <div class="form-section p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-person-check me-2"></i>Compte utilisateur
                    </h4>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="create_user_account" name="create_user_account" 
                               <?php echo isset($_POST['create_user_account']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="create_user_account">
                            <strong>Créer un compte utilisateur pour cet enseignant</strong>
                        </label>
                        <div class="form-text">Permet à l'enseignant de se connecter au système</div>
                    </div>
                    
                    <div id="user_account_fields" style="display: none;">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="user_username" class="form-label required-field">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="user_username" name="user_username" 
                                       value="<?php echo htmlspecialchars($_POST['user_username'] ?? ''); ?>">
                                <div class="form-text">Nom d'utilisateur pour la connexion</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="user_email" class="form-label required-field">Email de connexion</label>
                                <input type="email" class="form-control" id="user_email" name="user_email" 
                                       value="<?php echo htmlspecialchars($_POST['user_email'] ?? ''); ?>">
                                <div class="form-text">Email utilisé pour se connecter au système</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="user_password" class="form-label required-field">Mot de passe</label>
                                <input type="password" class="form-control" id="user_password" name="user_password" 
                                       minlength="6">
                                <div class="form-text">Minimum 6 caractères</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label required-field">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       minlength="6">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons d'action -->
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Annuler
                    </a>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i>Créer l'enseignant
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion des spécialités
        const specialiteTags = document.querySelectorAll('.specialite-tag');
        const specialitesInput = document.getElementById('specialites_input');
        let selectedSpecialites = [];
        
        specialiteTags.forEach(tag => {
            tag.addEventListener('click', function() {
                const specialite = this.dataset.specialite;
                
                if (this.classList.contains('selected')) {
                    // Désélectionner
                    this.classList.remove('selected');
                    selectedSpecialites = selectedSpecialites.filter(s => s !== specialite);
                } else {
                    // Sélectionner
                    this.classList.add('selected');
                    selectedSpecialites.push(specialite);
                }
                
                // Mettre à jour l'input caché
                specialitesInput.value = JSON.stringify(selectedSpecialites);
            });
        });
        
        // Validation du formulaire
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
        
        // Auto-complétion de la date d'embauche
        document.getElementById('date_embauche').addEventListener('change', function() {
            if (this.value) {
                const embaucheDate = new Date(this.value);
                const today = new Date();
                const experience = today.getFullYear() - embaucheDate.getFullYear();
                
                if (experience >= 0) {
                    document.getElementById('experience_annees').value = experience;
                }
            }
        });
        
        // Gestion de l'affichage des champs du compte utilisateur
        const createUserAccountCheckbox = document.getElementById('create_user_account');
        const userAccountFields = document.getElementById('user_account_fields');
        
        function toggleUserAccountFields() {
            if (createUserAccountCheckbox.checked) {
                userAccountFields.style.display = 'block';
                // Rendre les champs obligatoires
                document.getElementById('user_username').required = true;
                document.getElementById('user_email').required = true;
                document.getElementById('user_password').required = true;
                document.getElementById('confirm_password').required = true;
            } else {
                userAccountFields.style.display = 'none';
                // Rendre les champs optionnels
                document.getElementById('user_username').required = false;
                document.getElementById('user_email').required = false;
                document.getElementById('user_password').required = false;
                document.getElementById('confirm_password').required = false;
            }
        }
        
        createUserAccountCheckbox.addEventListener('change', toggleUserAccountFields);
        
        // Initialiser l'état au chargement de la page
        toggleUserAccountFields();
        
        // Validation de la confirmation du mot de passe
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('user_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Auto-remplissage du nom d'utilisateur basé sur le prénom et nom
        document.getElementById('prenom').addEventListener('input', function() {
            if (createUserAccountCheckbox.checked && !document.getElementById('user_username').value) {
                const prenom = this.value.toLowerCase();
                const nom = document.getElementById('nom').value.toLowerCase();
                if (prenom && nom) {
                    document.getElementById('user_username').value = prenom + '.' + nom;
                }
            }
        });
        
        document.getElementById('nom').addEventListener('input', function() {
            if (createUserAccountCheckbox.checked && !document.getElementById('user_username').value) {
                const prenom = document.getElementById('prenom').value.toLowerCase();
                const nom = this.value.toLowerCase();
                if (prenom && nom) {
                    document.getElementById('user_username').value = prenom + '.' + nom;
                }
            }
        });
        
        // Gestion de l'aperçu de la photo
        document.getElementById('photo').addEventListener('change', function() {
            const file = this.files[0];
            const preview = document.getElementById('photo-preview');
            const previewImg = document.getElementById('preview-img');
            
            if (file) {
                // Vérifier le type de fichier
                if (!file.type.startsWith('image/')) {
                    alert('Veuillez sélectionner un fichier image.');
                    this.value = '';
                    return;
                }
                
                // Vérifier la taille (2MB max)
                if (file.size > 2 * 1024 * 1024) {
                    alert('La taille du fichier ne doit pas dépasser 2MB.');
                    this.value = '';
                    return;
                }
                
                // Afficher l'aperçu
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>

