<?php
/**
 * Modification des informations de l'école
 * Permet de mettre à jour les informations de base de l'école
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Vérifier si l'utilisateur a une école
if (!isset($_SESSION['ecole_id'])) {
    setFlashMessage('error', 'Aucune école associée à votre compte.');
    redirect('../auth/login.php');
}

// Vérifier si l'utilisateur a les droits admin ou direction
if (!hasRole(['admin', 'direction'])) {
    setFlashMessage('error', 'Vous devez être administrateur ou membre de la direction pour accéder à cette page.');
    redirect('../auth/dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

try {
    // Récupérer les informations actuelles de l'école
    $ecole_query = "SELECT * FROM ecoles WHERE id = :ecole_id";
    $ecole_stmt = $db->prepare($ecole_query);
    $ecole_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $ecole = $ecole_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ecole) {
        setFlashMessage('error', 'École non trouvée.');
        redirect('index.php');
    }
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validation des données
        $nom_ecole = sanitize($_POST['nom_ecole'] ?? '');
        $sigle = sanitize($_POST['sigle'] ?? '');
        $adresse = sanitize($_POST['adresse'] ?? '');
        $telephone = sanitize($_POST['telephone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $site_web = sanitize($_POST['site_web'] ?? '');
        $fax = sanitize($_POST['fax'] ?? '');
        $bp = sanitize($_POST['bp'] ?? '');
        $regime = sanitize($_POST['regime'] ?? '');
        $type_enseignement = $_POST['type_enseignement'] ?? [];
        $langue_enseignement = $_POST['langue_enseignement'] ?? [];
        $devise_principale = sanitize($_POST['devise_principale'] ?? '');
        $directeur_nom = sanitize($_POST['directeur_nom'] ?? '');
        $directeur_telephone = sanitize($_POST['directeur_telephone'] ?? '');
        $directeur_email = sanitize($_POST['directeur_email'] ?? '');
        $numero_autorisation = sanitize($_POST['numero_autorisation'] ?? '');
        $date_autorisation = $_POST['date_autorisation'] ?? '';
        $description_etablissement = sanitize($_POST['description_etablissement'] ?? '');
        
        // Gestion de l'upload du logo
        $logo_path = $ecole['logo_path'] ?? null;
        
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/logos/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['logo']['name']);
            $extension = strtolower($file_info['extension']);
            
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif']) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
                $new_filename = 'logo_' . $_SESSION['ecole_id'] . '_' . time() . '.' . $extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                    $logo_path = 'uploads/logos/' . $new_filename;
                    
                    if ($ecole['logo_path'] && file_exists('../' . $ecole['logo_path'])) {
                        unlink('../' . $ecole['logo_path']);
                    }
                }
            }
        }
        
        // Validation des champs obligatoires
        if (empty($nom_ecole) || empty($adresse) || empty($telephone) || empty($email)) {
            $error = 'Veuillez remplir tous les champs obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || 
                  (!empty($directeur_email) && !filter_var($directeur_email, FILTER_VALIDATE_EMAIL))) {
            $error = 'Veuillez saisir des adresses email valides.';
        } else {
            try {
                // Préparer la requête de mise à jour
                $update_query = "UPDATE ecoles SET 
                    nom_ecole = :nom_ecole,
                    sigle = :sigle,
                    adresse = :adresse,
                    telephone = :telephone,
                    email = :email,
                    site_web = :site_web,
                    fax = :fax,
                    bp = :bp,
                    regime = :regime,
                    type_enseignement = :type_enseignement,
                    langue_enseignement = :langue_enseignement,
                    devise_principale = :devise_principale,
                    directeur_nom = :directeur_nom,
                    directeur_telephone = :directeur_telephone,
                    directeur_email = :directeur_email,
                    numero_autorisation = :numero_autorisation,
                    date_autorisation = :date_autorisation,
                    description_etablissement = :description_etablissement,
                    logo_path = :logo_path,
                    updated_at = CURRENT_TIMESTAMP,
                    updated_by = :updated_by
                    WHERE id = :ecole_id";
                
                $update_stmt = $db->prepare($update_query);
                
                // Convertir les tableaux en chaînes pour la base de données
                $type_enseignement_str = implode(',', $type_enseignement);
                $langue_enseignement_str = implode(',', $langue_enseignement);
                
                $update_stmt->execute([
                    'nom_ecole' => $nom_ecole,
                    'sigle' => $sigle,
                    'adresse' => $adresse,
                    'telephone' => $telephone,
                    'email' => $email,
                    'site_web' => $site_web,
                    'fax' => $fax,
                    'bp' => $bp,
                    'regime' => $regime,
                    'type_enseignement' => $type_enseignement_str,
                    'langue_enseignement' => $langue_enseignement_str,
                    'devise_principale' => $devise_principale,
                    'directeur_nom' => $directeur_nom,
                    'directeur_telephone' => $directeur_telephone,
                    'directeur_email' => $directeur_email,
                    'numero_autorisation' => $numero_autorisation,
                    'date_autorisation' => $date_autorisation ?: null,
                    'description_etablissement' => $description_etablissement,
                    'logo_path' => $logo_path,
                    'updated_by' => $_SESSION['user_id'],
                    'ecole_id' => $_SESSION['ecole_id']
                ]);
                
                $success = 'Les informations de l\'école ont été mises à jour avec succès.';
                
                // Mettre à jour les données affichées
                $ecole = array_merge($ecole, [
                    'nom_ecole' => $nom_ecole,
                    'sigle' => $sigle,
                    'adresse' => $adresse,
                    'telephone' => $telephone,
                    'email' => $email,
                    'site_web' => $site_web,
                    'fax' => $fax,
                    'bp' => $bp,
                    'regime' => $regime,
                    'type_enseignement' => $type_enseignement_str,
                    'langue_enseignement' => $langue_enseignement_str,
                    'devise_principale' => $devise_principale,
                    'directeur_nom' => $directeur_nom,
                    'directeur_telephone' => $directeur_telephone,
                    'directeur_email' => $directeur_email,
                    'numero_autorisation' => $numero_autorisation,
                    'date_autorisation' => $date_autorisation,
                    'description_etablissement' => $description_etablissement,
                    'logo_path' => $logo_path
                ]);
                
            } catch (Exception $e) {
                error_log('Erreur lors de la mise à jour de l\'école: ' . $e->getMessage());
                $error = 'Erreur lors de la mise à jour des informations.';
            }
        }
    }
    
} catch (Exception $e) {
    error_log('Erreur lors de la récupération des données de l\'école: ' . $e->getMessage());
    setFlashMessage('error', 'Erreur lors du chargement des données.');
    redirect('index.php');
}

$page_title = "Modifier l'École - " . ($ecole['nom_ecole'] ?? 'École');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Naklass</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="content-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col">
                            <h1 class="page-title">
                                <i class="bi bi-pencil"></i>
                                Modifier l'École
                            </h1>
                        </div>
                        <div class="col-auto">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i>
                                Retour
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <div class="content">
                <div class="container-fluid">
                    <!-- Flash Messages -->
                    <?php include '../includes/flash_messages.php'; ?>
                    
                    <!-- Messages d'erreur et de succès -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Formulaire de modification -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-building"></i>
                                Informations de l'École
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="row">
                                    <!-- Informations de base -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Informations de base</h6>
                                        
                                        <div class="mb-3">
                                            <label for="nom_ecole" class="form-label">Nom de l'école *</label>
                                            <input type="text" class="form-control" id="nom_ecole" name="nom_ecole" 
                                                   value="<?php echo htmlspecialchars($ecole['nom_ecole'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="sigle" class="form-label">Sigle</label>
                                            <input type="text" class="form-control" id="sigle" name="sigle" 
                                                   value="<?php echo htmlspecialchars($ecole['sigle'] ?? ''); ?>" maxlength="10">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="adresse" class="form-label">Adresse *</label>
                                            <textarea class="form-control" id="adresse" name="adresse" rows="3" required><?php echo htmlspecialchars($ecole['adresse'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="telephone" class="form-label">Téléphone *</label>
                                            <input type="tel" class="form-control" id="telephone" name="telephone" 
                                                   value="<?php echo htmlspecialchars($ecole['telephone'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($ecole['email'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="site_web" class="form-label">Site web</label>
                                            <input type="url" class="form-control" id="site_web" name="site_web" 
                                                   value="<?php echo htmlspecialchars($ecole['site_web'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <!-- Informations supplémentaires -->
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Informations supplémentaires</h6>
                                        
                                        <div class="mb-3">
                                            <label for="fax" class="form-label">Fax</label>
                                            <input type="tel" class="form-control" id="fax" name="fax" 
                                                   value="<?php echo htmlspecialchars($ecole['fax'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="bp" class="form-label">Boîte postale</label>
                                            <input type="text" class="form-control" id="bp" name="bp" 
                                                   value="<?php echo htmlspecialchars($ecole['bp'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="regime" class="form-label">Régime</label>
                                            <select class="form-select" id="regime" name="regime">
                                                <option value="">Sélectionner...</option>
                                                <option value="public" <?php echo ($ecole['regime'] ?? '') === 'public' ? 'selected' : ''; ?>>Public</option>
                                                <option value="privé" <?php echo ($ecole['regime'] ?? '') === 'privé' ? 'selected' : ''; ?>>Privé</option>
                                                <option value="conventionné" <?php echo ($ecole['regime'] ?? '') === 'conventionné' ? 'selected' : ''; ?>>Conventionné</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="devise_principale" class="form-label">Devise principale</label>
                                            <select class="form-select" id="devise_principale" name="devise_principale">
                                                <option value="CDF" <?php echo ($ecole['devise_principale'] ?? '') === 'CDF' ? 'selected' : ''; ?>>CDF (Franc congolais)</option>
                                                <option value="USD" <?php echo ($ecole['devise_principale'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD (Dollar américain)</option>
                                                <option value="EUR" <?php echo ($ecole['devise_principale'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="numero_autorisation" class="form-label">Numéro d'autorisation</label>
                                            <input type="text" class="form-control" id="numero_autorisation" name="numero_autorisation" 
                                                   value="<?php echo htmlspecialchars($ecole['numero_autorisation'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="date_autorisation" class="form-label">Date d'autorisation</label>
                                            <input type="date" class="form-control" id="date_autorisation" name="date_autorisation" 
                                                   value="<?php echo $ecole['date_autorisation'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Types d'enseignement et langues -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Types d'enseignement</h6>
                                        <div class="mb-3">
                                            <?php
                                            $types_actuels = !empty($ecole['type_enseignement']) ? explode(',', $ecole['type_enseignement']) : [];
                                            $types_disponibles = ['maternelle', 'primaire', 'secondaire', 'technique', 'professionnel', 'université'];
                                            ?>
                                            <?php foreach ($types_disponibles as $type): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="type_enseignement[]" 
                                                           value="<?php echo $type; ?>" id="type_<?php echo $type; ?>"
                                                           <?php echo in_array($type, $types_actuels) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="type_<?php echo $type; ?>">
                                                        <?php echo ucfirst($type); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Langues d'enseignement</h6>
                                        <div class="mb-3">
                                            <?php
                                            $langues_actuelles = !empty($ecole['langue_enseignement']) ? explode(',', $ecole['langue_enseignement']) : [];
                                            $langues_disponibles = ['français', 'anglais', 'lingala', 'kikongo', 'tshiluba', 'swahili'];
                                            ?>
                                            <?php foreach ($langues_disponibles as $langue): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="langue_enseignement[]" 
                                                           value="<?php echo $langue; ?>" id="langue_<?php echo $langue; ?>"
                                                           <?php echo in_array($langue, $langues_actuelles) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="langue_<?php echo $langue; ?>">
                                                        <?php echo ucfirst($langue); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Direction -->
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h6 class="text-primary mb-3">Direction</h6>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="directeur_nom" class="form-label">Nom du directeur</label>
                                            <input type="text" class="form-control" id="directeur_nom" name="directeur_nom" 
                                                   value="<?php echo htmlspecialchars($ecole['directeur_nom'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="directeur_telephone" class="form-label">Téléphone du directeur</label>
                                            <input type="tel" class="form-control" id="directeur_telephone" name="directeur_telephone" 
                                                   value="<?php echo htmlspecialchars($ecole['directeur_telephone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="directeur_email" class="form-label">Email du directeur</label>
                                            <input type="email" class="form-control" id="directeur_email" name="directeur_email" 
                                                   value="<?php echo htmlspecialchars($ecole['directeur_email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Description -->
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h6 class="text-primary mb-3">Description</h6>
                                        <div class="mb-3">
                                            <label for="description_etablissement" class="form-label">Description de l'établissement</label>
                                            <textarea class="form-control" id="description_etablissement" name="description_etablissement" 
                                                      rows="4"><?php echo htmlspecialchars($ecole['description_etablissement'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Boutons d'action -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between">
                                            <a href="index.php" class="btn btn-secondary">
                                                <i class="bi bi-arrow-left"></i>
                                                Annuler
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i>
                                                Enregistrer les modifications
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
