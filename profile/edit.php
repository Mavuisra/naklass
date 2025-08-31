<?php
/**
 * Page de modification du profil utilisateur
 * Permet de modifier les informations personnelles et la photo de profil
 */

require_once '../includes/functions.php';

// Vérifier l'authentification
requireAuth();

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();
$error = '';
$success = '';

// Récupération des informations utilisateur actuelles
try {
    $query = "SELECT * FROM utilisateurs WHERE id = :user_id AND statut = 'actif'";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('error', 'Utilisateur introuvable.');
        redirect('../auth/logout.php');
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors du chargement du profil: ' . $e->getMessage());
    redirect('index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Validation et mise à jour des informations personnelles
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        
        // Validation
        if (empty($nom) || empty($prenom) || empty($email)) {
            $error = 'Le nom, prénom et email sont obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format d\'email invalide.';
        } else {
            try {
                // Vérifier que l'email n'est pas déjà utilisé par un autre utilisateur
                $check_email_query = "SELECT id FROM utilisateurs WHERE email = :email AND id != :user_id";
                $check_stmt = $db->prepare($check_email_query);
                $check_stmt->execute(['email' => $email, 'user_id' => $_SESSION['user_id']]);
                
                if ($check_stmt->fetch()) {
                    $error = 'Cet email est déjà utilisé par un autre utilisateur.';
                } else {
                    // Mise à jour
                    $update_query = "UPDATE utilisateurs SET 
                                   nom = :nom, 
                                   prenom = :prenom, 
                                   email = :email, 
                                   telephone = :telephone,
                                   updated_at = NOW()
                                   WHERE id = :user_id";
                    
                    $update_stmt = $db->prepare($update_query);
                    $result = $update_stmt->execute([
                        'nom' => $nom,
                        'prenom' => $prenom,
                        'email' => $email,
                        'telephone' => $telephone,
                        'user_id' => $_SESSION['user_id']
                    ]);
                    
                    if ($result) {
                        // Mettre à jour les variables de session
                        $_SESSION['user_nom'] = $nom;
                        $_SESSION['user_prenom'] = $prenom;
                        $_SESSION['user_email'] = $email;
                        
                        // Recharger les données utilisateur
                        $stmt->execute(['user_id' => $_SESSION['user_id']]);
                        $user = $stmt->fetch();
                        
                        logUserAction('PROFILE_UPDATE', 'Mise à jour du profil');
                        $success = 'Profil mis à jour avec succès.';
                    } else {
                        $error = 'Erreur lors de la mise à jour du profil.';
                    }
                }
                
            } catch (Exception $e) {
                $error = 'Erreur lors de la mise à jour: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'upload_photo') {
        // Gestion de l'upload de photo avec la fonction uploadFile()
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            try {
                // Utiliser la fonction uploadFile() existante
                $upload_result = uploadFile($_FILES['photo'], 'profile', ['jpg', 'jpeg', 'png', 'gif']);
                
                if ($upload_result['success']) {
                    // Supprimer l'ancienne photo si elle existe
                    if (!empty($user['photo_path'])) {
                        $old_photo_path = UPLOAD_PATH . 'profile/' . $user['photo_path'];
                        if (file_exists($old_photo_path)) {
                            unlink($old_photo_path);
                        }
                    }
                    
                    // Mettre à jour la base de données
                    if (columnExists('utilisateurs', 'photo_path', $db)) {
                        $photo_query = "UPDATE utilisateurs SET photo_path = :photo_path, updated_at = NOW() WHERE id = :user_id";
                        $photo_stmt = $db->prepare($photo_query);
                        $photo_result = $photo_stmt->execute([
                            'photo_path' => $upload_result['filename'],
                            'user_id' => $_SESSION['user_id']
                        ]);
                        
                        if ($photo_result) {
                            // Recharger les données utilisateur
                            $stmt->execute(['user_id' => $_SESSION['user_id']]);
                            $user = $stmt->fetch();
                            
                            logUserAction('PROFILE_PHOTO_UPDATE', 'Mise à jour de la photo de profil: ' . $upload_result['filename']);
                            $success = 'Photo de profil mise à jour avec succès.';
                        } else {
                            // Supprimer le fichier en cas d'erreur DB
                            if (file_exists($upload_result['filepath'])) {
                                unlink($upload_result['filepath']);
                            }
                            $error = 'Erreur lors de la sauvegarde en base de données.';
                        }
                    } else {
                        // Supprimer le fichier si la colonne n'existe pas
                        if (file_exists($upload_result['filepath'])) {
                            unlink($upload_result['filepath']);
                        }
                        $error = 'La gestion des photos n\'est pas encore activée sur ce système. Exécutez update_database_structure.php';
                    }
                } else {
                    $error = $upload_result['message'];
                }
                
            } catch (Exception $e) {
                $error = 'Erreur lors de l\'upload: ' . $e->getMessage();
                error_log("Erreur upload photo profil: " . $e->getMessage());
            }
        } else {
            $upload_error_messages = [
                UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille autorisée par le serveur.',
                UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille autorisée par le formulaire.',
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé.',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé.',
                UPLOAD_ERR_NO_TMP_DIR => 'Le dossier temporaire est manquant.',
                UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque.',
                UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'envoi du fichier.'
            ];
            
            $upload_error = $_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE;
            $error = $upload_error_messages[$upload_error] ?? 'Erreur inconnue lors de l\'upload.';
        }
    }
    
    elseif ($action === 'delete_photo') {
        // Suppression de la photo de profil
        try {
            if (!empty($user['photo_path'])) {
                $photo_path = UPLOAD_PATH . 'profile/' . $user['photo_path'];
                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
            }
            
            if (columnExists('utilisateurs', 'photo_path', $db)) {
                $delete_photo_query = "UPDATE utilisateurs SET photo_path = NULL, updated_at = NOW() WHERE id = :user_id";
                $delete_stmt = $db->prepare($delete_photo_query);
                $delete_result = $delete_stmt->execute(['user_id' => $_SESSION['user_id']]);
                
                if ($delete_result) {
                    // Recharger les données utilisateur
                    $stmt->execute(['user_id' => $_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    
                    logUserAction('PROFILE_PHOTO_DELETE', 'Suppression de la photo de profil');
                    $success = 'Photo de profil supprimée avec succès.';
                } else {
                    $error = 'Erreur lors de la suppression de la photo.';
                }
            }
            
        } catch (Exception $e) {
            $error = 'Erreur lors de la suppression: ' . $e->getMessage();
            error_log("Erreur suppression photo profil: " . $e->getMessage());
        }
    }
}

$page_title = "Modifier le Profil";
$current_page = "profile";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Naklass</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- CSS Personnalisé -->
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/naklass-theme.css" rel="stylesheet">
    <link href="profile.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <?php include '../includes/sidebar.php'; ?>
            </div>
            
            <!-- Contenu principal -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- En-tête -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0"><?= $page_title ?></h1>
                            <p class="text-muted mb-0">Modifiez vos informations personnelles</p>
                        </div>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Retour au profil
                        </a>
                    </div>

                    <!-- Messages de retour -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Formulaire principal -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-naklass-primary text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-person-gear"></i> Informations Personnelles
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="needs-validation" novalidate>
                                        <input type="hidden" name="action" value="update_profile">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="prenom" name="prenom" 
                                                       value="<?= htmlspecialchars($user['prenom']) ?>" required>
                                                <div class="invalid-feedback">
                                                    Veuillez saisir votre prénom.
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="nom" name="nom" 
                                                       value="<?= htmlspecialchars($user['nom']) ?>" required>
                                                <div class="invalid-feedback">
                                                    Veuillez saisir votre nom.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                <div class="invalid-feedback">
                                                    Veuillez saisir un email valide.
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="telephone" class="form-label">Téléphone</label>
                                                <input type="tel" class="form-control" id="telephone" name="telephone" 
                                                       value="<?= htmlspecialchars($user['telephone'] ?? '') ?>">
                                                <div class="form-text">Format: +243 XXX XXX XXX</div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <button type="submit" class="btn btn-naklass">
                                                <i class="bi bi-check-lg"></i> Enregistrer les modifications
                                            </button>
                                            <a href="index.php" class="btn btn-outline-secondary">
                                                <i class="bi bi-x-lg"></i> Annuler
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Gestion de la photo -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm" id="photo">
                                <div class="card-header bg-light">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-camera"></i> Photo de Profil
                                    </h6>
                                </div>
                                <div class="card-body text-center">
                                    <!-- Aperçu de la photo actuelle -->
                                    <div class="profile-photo-container mb-3">
                                        <?php $photo_url = getProfilePhotoUrl($user['photo_path'] ?? ''); ?>
                                        <?php if ($photo_url): ?>
                                            <img src="<?= $photo_url ?>" 
                                                 alt="Photo de profil" class="profile-photo" id="current-photo">
                                        <?php else: ?>
                                            <div class="profile-photo-placeholder" id="photo-placeholder">
                                                <i class="bi bi-person-circle"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Formulaire d'upload -->
                                    <form method="POST" enctype="multipart/form-data" class="mb-3">
                                        <input type="hidden" name="action" value="upload_photo">
                                        <div class="mb-3">
                                            <input type="file" class="form-control" name="photo" accept="image/*" 
                                                   id="photo-input" onchange="previewPhoto(this)">
                                            <div class="form-text">
                                                JPG, PNG ou GIF (max 5MB)
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-naklass btn-sm">
                                            <i class="bi bi-upload"></i> Télécharger
                                        </button>
                                    </form>
                                    
                                    <!-- Bouton de suppression -->
                                    <?php if (!empty($user['photo_path'])): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette photo ?')">
                                        <input type="hidden" name="action" value="delete_photo">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash"></i> Supprimer
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Actions rapides -->
                            <div class="card border-0 shadow-sm mt-4">
                                <div class="card-header bg-light">
                                    <h6 class="card-title mb-0">Actions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="change-password.php" class="btn btn-outline-naklass btn-sm">
                                            <i class="bi bi-shield-lock"></i> Changer le mot de passe
                                        </a>
                                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-eye"></i> Voir le profil
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- JavaScript personnalisé -->
    <script src="profile.js"></script>
    
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

        // Prévisualisation de la photo
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const currentPhoto = document.getElementById('current-photo');
                    const placeholder = document.getElementById('photo-placeholder');
                    
                    if (currentPhoto) {
                        currentPhoto.src = e.target.result;
                    } else if (placeholder) {
                        placeholder.outerHTML = `<img src="${e.target.result}" alt="Aperçu" class="profile-photo" id="current-photo">`;
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
