<?php
/**
 * Page de profil utilisateur
 * Affiche et permet de modifier les informations personnelles
 */

require_once '../includes/functions.php';

// Vérifier l'authentification
requireAuth();

// Vérifier la configuration de l'école
requireSchoolSetup();

// Récupération des informations utilisateur
$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT u.*, r.libelle as role_nom, r.code as role_code, e.nom_ecole as ecole_nom, e.adresse as ecole_adresse
              FROM utilisateurs u
              JOIN roles r ON u.role_id = r.id
              JOIN ecoles e ON u.ecole_id = e.id
              WHERE u.id = :user_id AND u.statut = 'actif'";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('error', 'Utilisateur introuvable.');
        redirect('../auth/logout.php');
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors du chargement du profil: ' . $e->getMessage());
    redirect('../auth/dashboard.php');
}

// Récupération des statistiques d'activité (si disponible)
$activity_stats = [];
try {
    // Dernières connexions
    if (columnExists('utilisateurs', 'derniere_connexion_at', $db)) {
        $last_login_query = "SELECT derniere_connexion_at, tentatives_connexion FROM utilisateurs WHERE id = :user_id";
        $last_login_stmt = $db->prepare($last_login_query);
        $last_login_stmt->execute(['user_id' => $_SESSION['user_id']]);
        $login_data = $last_login_stmt->fetch();
        
        if ($login_data) {
            $activity_stats['derniere_connexion'] = $login_data['derniere_connexion_at'];
            $activity_stats['tentatives_connexion'] = $login_data['tentatives_connexion'] ?? 0;
        }
    }
    
} catch (Exception $e) {
    // Ignorer les erreurs de statistiques
    error_log("Erreur stats profil: " . $e->getMessage());
}

$page_title = "Mon Profil";
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
                            <p class="text-muted mb-0">Gérez vos informations personnelles</p>
                        </div>
                        <div class="btn-group">
                            <a href="edit.php" class="btn btn-naklass">
                                <i class="bi bi-pencil-square"></i> Modifier
                            </a>
                            <a href="change-password.php" class="btn btn-outline-naklass">
                                <i class="bi bi-shield-lock"></i> Mot de passe
                            </a>
                        </div>
                    </div>

                    <!-- Messages flash -->
                    <?php if ($flash = getFlashMessage()): ?>
                        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($flash['message']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Informations principales -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-naklass-primary text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-person-circle"></i> Informations Personnelles
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Nom complet</label>
                                            <div class="form-control-plaintext">
                                                <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Email</label>
                                            <div class="form-control-plaintext">
                                                <i class="bi bi-envelope text-naklass-primary"></i>
                                                <?= htmlspecialchars($user['email']) ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Téléphone</label>
                                            <div class="form-control-plaintext">
                                                <i class="bi bi-telephone text-naklass-primary"></i>
                                                <?= $user['telephone'] ? htmlspecialchars($user['telephone']) : '<em class="text-muted">Non renseigné</em>' ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Rôle</label>
                                            <div class="form-control-plaintext">
                                                <span class="badge bg-naklass-primary fs-6">
                                                    <i class="bi bi-shield-check"></i> <?= htmlspecialchars($user['role_nom']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Informations établissement -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-building"></i> Établissement
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">École</label>
                                            <div class="form-control-plaintext">
                                                <strong><?= htmlspecialchars($user['ecole_nom']) ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Adresse</label>
                                            <div class="form-control-plaintext">
                                                <i class="bi bi-geo-alt text-naklass-primary"></i>
                                                <?= $user['ecole_adresse'] ? htmlspecialchars($user['ecole_adresse']) : '<em class="text-muted">Non renseignée</em>' ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Informations système -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-info-circle"></i> Informations du Compte
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Compte créé le</label>
                                            <div class="form-control-plaintext">
                                                <i class="bi bi-calendar-plus text-naklass-primary"></i>
                                                <?= date('d/m/Y à H:i', strtotime($user['created_at'])) ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Dernière modification</label>
                                            <div class="form-control-plaintext">
                                                <i class="bi bi-clock-history text-naklass-primary"></i>
                                                <?= date('d/m/Y à H:i', strtotime($user['updated_at'])) ?>
                                            </div>
                                        </div>
                                        <?php if (isset($activity_stats['derniere_connexion'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Dernière connexion</label>
                                            <div class="form-control-plaintext">
                                                <i class="bi bi-box-arrow-in-right text-success"></i>
                                                <?= date('d/m/Y à H:i', strtotime($activity_stats['derniere_connexion'])) ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-muted">Statut du compte</label>
                                            <div class="form-control-plaintext">
                                                <span class="badge bg-success fs-6">
                                                    <i class="bi bi-check-circle"></i> Actif
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar droite -->
                        <div class="col-lg-4">
                            <!-- Photo de profil -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body text-center">
                                    <div class="profile-photo-container mb-3">
                                        <?php $photo_url = getProfilePhotoUrl($user['photo_path'] ?? ''); ?>
                                        <?php if ($photo_url): ?>
                                            <img src="<?= $photo_url ?>" 
                                                 alt="Photo de profil" class="profile-photo">
                                        <?php else: ?>
                                            <div class="profile-photo-placeholder">
                                                <i class="bi bi-person-circle"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="card-title"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h5>
                                    <p class="text-muted"><?= htmlspecialchars($user['role_nom']) ?></p>
                                    <a href="edit.php#photo" class="btn btn-outline-naklass btn-sm">
                                        <i class="bi bi-camera"></i> Changer la photo
                                    </a>
                                </div>
                            </div>

                            <!-- Actions rapides -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="card-title mb-0">Actions Rapides</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="edit.php" class="btn btn-outline-naklass btn-sm">
                                            <i class="bi bi-pencil-square"></i> Modifier le profil
                                        </a>
                                        <a href="change-password.php" class="btn btn-outline-naklass btn-sm">
                                            <i class="bi bi-shield-lock"></i> Changer le mot de passe
                                        </a>
                                        <a href="../auth/dashboard.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-house"></i> Retour au tableau de bord
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Sécurité -->
                            <?php if (isset($activity_stats['tentatives_connexion'])): ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-shield-check"></i> Sécurité
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted">Tentatives échouées</span>
                                        <span class="badge bg-<?= $activity_stats['tentatives_connexion'] > 0 ? 'warning' : 'success' ?>">
                                            <?= $activity_stats['tentatives_connexion'] ?>
                                        </span>
                                    </div>
                                    <?php if ($activity_stats['tentatives_connexion'] > 0): ?>
                                        <small class="text-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Des tentatives de connexion échouées ont été détectées.
                                        </small>
                                    <?php else: ?>
                                        <small class="text-success">
                                            <i class="bi bi-check-circle"></i>
                                            Aucune tentative suspecte détectée.
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
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
</body>
</html>
