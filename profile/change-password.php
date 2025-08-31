<?php
/**
 * Page de changement de mot de passe
 * Permet à l'utilisateur de modifier son mot de passe de manière sécurisée
 */

require_once '../includes/functions.php';

// Vérifier l'authentification
requireAuth();

$database = new Database();
$db = $database->getConnection();
$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Les nouveaux mots de passe ne correspondent pas.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
        $error = 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.';
    } else {
        try {
            // Récupérer le mot de passe actuel de l'utilisateur
            $query = "SELECT mot_de_passe_hash FROM utilisateurs WHERE id = :user_id AND statut = 'actif'";
            $stmt = $db->prepare($query);
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = 'Utilisateur introuvable.';
            } elseif (!verifyPassword($current_password, $user['mot_de_passe_hash'])) {
                $error = 'Le mot de passe actuel est incorrect.';
                
                // Log de tentative de changement de mot de passe avec mauvais mot de passe
                logUserAction('PASSWORD_CHANGE_FAILED', 'Tentative de changement de mot de passe avec mot de passe actuel incorrect');
                
                // Incrémenter les tentatives si la colonne existe
                if (columnExists('utilisateurs', 'tentatives_connexion', $db)) {
                    try {
                        $fail_query = "UPDATE utilisateurs SET tentatives_connexion = COALESCE(tentatives_connexion, 0) + 1 WHERE id = :user_id";
                        $fail_stmt = $db->prepare($fail_query);
                        $fail_stmt->execute(['user_id' => $_SESSION['user_id']]);
                    } catch (Exception $e) {
                        error_log("Erreur lors de l'incrémentation des tentatives: " . $e->getMessage());
                    }
                }
            } else {
                // Mot de passe actuel correct, procéder au changement
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_query = "UPDATE utilisateurs SET 
                               mot_de_passe_hash = :new_password_hash,
                               updated_at = NOW()
                               WHERE id = :user_id";
                
                $update_stmt = $db->prepare($update_query);
                $result = $update_stmt->execute([
                    'new_password_hash' => $new_password_hash,
                    'user_id' => $_SESSION['user_id']
                ]);
                
                if ($result) {
                    // Réinitialiser les tentatives de connexion si la colonne existe
                    if (columnExists('utilisateurs', 'tentatives_connexion', $db)) {
                        try {
                            $reset_query = "UPDATE utilisateurs SET tentatives_connexion = 0 WHERE id = :user_id";
                            $reset_stmt = $db->prepare($reset_query);
                            $reset_stmt->execute(['user_id' => $_SESSION['user_id']]);
                        } catch (Exception $e) {
                            error_log("Erreur lors de la réinitialisation des tentatives: " . $e->getMessage());
                        }
                    }
                    
                    logUserAction('PASSWORD_CHANGE_SUCCESS', 'Mot de passe modifié avec succès');
                    $success = 'Mot de passe modifié avec succès. Votre compte est maintenant plus sécurisé.';
                    
                    // Effacer les champs du formulaire
                    $_POST = [];
                } else {
                    $error = 'Erreur lors de la mise à jour du mot de passe.';
                }
            }
            
        } catch (Exception $e) {
            $error = 'Erreur lors du changement de mot de passe: ' . $e->getMessage();
            error_log("Erreur changement mot de passe: " . $e->getMessage());
        }
    }
}

// Récupérer les informations utilisateur pour l'affichage
try {
    $user_query = "SELECT u.nom, u.prenom, u.email, r.libelle as role_nom 
                   FROM utilisateurs u 
                   LEFT JOIN roles r ON u.role_id = r.id 
                   WHERE u.id = :user_id AND u.statut = 'actif'";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user_info = $user_stmt->fetch();
} catch (Exception $e) {
    $user_info = null;
}

$page_title = "Changer le Mot de Passe";
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
                            <h1 class="h3 mb-0">
                                <i class="bi bi-shield-lock text-naklass-primary"></i> <?= $page_title ?>
                            </h1>
                            <p class="text-muted mb-0">Modifiez votre mot de passe pour sécuriser votre compte</p>
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

                    <div class="row justify-content-center">
                        <!-- Formulaire de changement de mot de passe -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-naklass-primary text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-key"></i> Nouveau Mot de Passe
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($user_info): ?>
                                    <div class="alert alert-info" role="alert">
                                        <i class="bi bi-info-circle"></i>
                                        <strong>Utilisateur:</strong> <?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?>
                                        (<?= htmlspecialchars($user_info['email']) ?>)
                                    </div>
                                    <?php endif; ?>

                                    <form method="POST" class="needs-validation" novalidate id="password-form">
                                        <div class="mb-4">
                                            <label for="current_password" class="form-label">
                                                Mot de passe actuel <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-lock"></i>
                                                </span>
                                                <input type="password" class="form-control" id="current_password" 
                                                       name="current_password" required 
                                                       placeholder="Saisissez votre mot de passe actuel">
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="togglePassword('current_password')">
                                                    <i class="bi bi-eye" id="current_password_icon"></i>
                                                </button>
                                                <div class="invalid-feedback">
                                                    Veuillez saisir votre mot de passe actuel.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="new_password" class="form-label">
                                                Nouveau mot de passe <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-shield-plus"></i>
                                                </span>
                                                <input type="password" class="form-control" id="new_password" 
                                                       name="new_password" required minlength="8"
                                                       placeholder="Nouveau mot de passe (min. 8 caractères)"
                                                       onkeyup="checkPasswordStrength()">
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="togglePassword('new_password')">
                                                    <i class="bi bi-eye" id="new_password_icon"></i>
                                                </button>
                                                <div class="invalid-feedback">
                                                    Le mot de passe doit contenir au moins 8 caractères.
                                                </div>
                                            </div>
                                            <!-- Indicateur de force du mot de passe -->
                                            <div class="mt-2">
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar" id="password-strength-bar" 
                                                         role="progressbar" style="width: 0%"></div>
                                                </div>
                                                <small id="password-strength-text" class="form-text text-muted">
                                                    Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.
                                                </small>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="confirm_password" class="form-label">
                                                Confirmer le nouveau mot de passe <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="bi bi-shield-check"></i>
                                                </span>
                                                <input type="password" class="form-control" id="confirm_password" 
                                                       name="confirm_password" required 
                                                       placeholder="Confirmez le nouveau mot de passe"
                                                       onkeyup="checkPasswordMatch()">
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="togglePassword('confirm_password')">
                                                    <i class="bi bi-eye" id="confirm_password_icon"></i>
                                                </button>
                                                <div class="invalid-feedback">
                                                    Les mots de passe ne correspondent pas.
                                                </div>
                                            </div>
                                            <small id="password-match-text" class="form-text"></small>
                                        </div>

                                        <!-- Conseils de sécurité -->
                                        <div class="alert alert-info" role="alert">
                                            <h6><i class="bi bi-lightbulb"></i> Conseils pour un mot de passe sécurisé:</h6>
                                            <ul class="mb-0 small">
                                                <li>Au moins 8 caractères</li>
                                                <li>Une combinaison de lettres majuscules et minuscules</li>
                                                <li>Au moins un chiffre</li>
                                                <li>Évitez les mots du dictionnaire</li>
                                                <li>N'utilisez pas d'informations personnelles</li>
                                            </ul>
                                        </div>

                                        <div class="d-flex justify-content-between">
                                            <button type="submit" class="btn btn-naklass" id="submit-btn" disabled>
                                                <i class="bi bi-shield-check"></i> Changer le mot de passe
                                            </button>
                                            <a href="index.php" class="btn btn-outline-secondary">
                                                <i class="bi bi-x-lg"></i> Annuler
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Informations de sécurité -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-shield-exclamation"></i> Sécurité du Compte
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex align-items-start mb-3">
                                        <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                                        <div>
                                            <small class="fw-bold">Authentification à deux facteurs</small>
                                            <br><small class="text-muted">Recommandé pour une sécurité maximale</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start mb-3">
                                        <i class="bi bi-clock-history text-info me-2 mt-1"></i>
                                        <div>
                                            <small class="fw-bold">Changement régulier</small>
                                            <br><small class="text-muted">Changez votre mot de passe tous les 3-6 mois</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start">
                                        <i class="bi bi-exclamation-triangle text-warning me-2 mt-1"></i>
                                        <div>
                                            <small class="fw-bold">Ne partagez jamais</small>
                                            <br><small class="text-muted">Gardez votre mot de passe confidentiel</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mt-4">
                                <div class="card-header bg-light">
                                    <h6 class="card-title mb-0">Actions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="edit.php" class="btn btn-outline-naklass btn-sm">
                                            <i class="bi bi-pencil-square"></i> Modifier le profil
                                        </a>
                                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-person-circle"></i> Voir le profil
                                        </a>
                                        <a href="../auth/dashboard.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-house"></i> Tableau de bord
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

        // Basculer l'affichage du mot de passe
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        // Vérifier la force du mot de passe
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const progressBar = document.getElementById('password-strength-bar');
            const strengthText = document.getElementById('password-strength-text');
            
            let strength = 0;
            let messages = [];
            
            // Longueur
            if (password.length >= 8) {
                strength += 20;
            } else {
                messages.push('Au moins 8 caractères');
            }
            
            // Minuscule
            if (/[a-z]/.test(password)) {
                strength += 20;
            } else {
                messages.push('Une lettre minuscule');
            }
            
            // Majuscule
            if (/[A-Z]/.test(password)) {
                strength += 20;
            } else {
                messages.push('Une lettre majuscule');
            }
            
            // Chiffre
            if (/\d/.test(password)) {
                strength += 20;
            } else {
                messages.push('Un chiffre');
            }
            
            // Caractère spécial
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                strength += 20;
            }
            
            // Mise à jour de la barre de progression
            progressBar.style.width = strength + '%';
            
            if (strength < 40) {
                progressBar.className = 'progress-bar bg-danger';
                strengthText.textContent = 'Mot de passe faible. Manque: ' + messages.join(', ');
                strengthText.className = 'form-text text-danger';
            } else if (strength < 80) {
                progressBar.className = 'progress-bar bg-warning';
                strengthText.textContent = 'Mot de passe moyen. ' + (messages.length > 0 ? 'Manque: ' + messages.join(', ') : '');
                strengthText.className = 'form-text text-warning';
            } else {
                progressBar.className = 'progress-bar bg-success';
                strengthText.textContent = 'Mot de passe fort !';
                strengthText.className = 'form-text text-success';
            }
            
            checkFormValidity();
        }

        // Vérifier la correspondance des mots de passe
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('password-match-text');
            const confirmField = document.getElementById('confirm_password');
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    matchText.textContent = '✓ Les mots de passe correspondent';
                    matchText.className = 'form-text text-success';
                    confirmField.setCustomValidity('');
                } else {
                    matchText.textContent = '✗ Les mots de passe ne correspondent pas';
                    matchText.className = 'form-text text-danger';
                    confirmField.setCustomValidity('Les mots de passe ne correspondent pas');
                }
            } else {
                matchText.textContent = '';
                confirmField.setCustomValidity('');
            }
            
            checkFormValidity();
        }

        // Vérifier la validité globale du formulaire
        function checkFormValidity() {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submit-btn');
            
            const isValid = currentPassword.length > 0 && 
                          newPassword.length >= 8 && 
                          /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(newPassword) &&
                          newPassword === confirmPassword;
            
            submitBtn.disabled = !isValid;
        }

        // Événements sur les champs
        document.getElementById('current_password').addEventListener('input', checkFormValidity);
        document.getElementById('new_password').addEventListener('input', checkPasswordStrength);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html>
