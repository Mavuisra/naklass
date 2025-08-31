<?php
require_once '../includes/functions.php';
require_once '../config/photo_config.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Récupérer l'ID de l'élève
$student_id = intval($_GET['id'] ?? 0);

if (!$student_id) {
    setFlashMessage('error', 'Identifiant d\'élève invalide.');
    redirect('index.php');
}

$errors = [];
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Validation des données
        $data = [
            'nom' => sanitize($_POST['nom'] ?? ''),
            'postnom' => sanitize($_POST['postnom'] ?? ''),
            'prenom' => sanitize($_POST['prenom'] ?? ''),
            'sexe' => sanitize($_POST['sexe'] ?? ''),
            'date_naissance' => sanitize($_POST['date_naissance'] ?? ''),
            'lieu_naissance' => sanitize($_POST['lieu_naissance'] ?? ''),
            'nationalite' => sanitize($_POST['nationalite'] ?? 'Congolaise'),
            'adresse' => sanitize($_POST['adresse'] ?? ''),
            'quartier' => sanitize($_POST['quartier'] ?? ''),
            'commune' => sanitize($_POST['commune'] ?? ''),
            'ville' => sanitize($_POST['ville'] ?? ''),
            'province' => sanitize($_POST['province'] ?? ''),
            'telephone' => sanitize($_POST['telephone'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'groupe_sanguin' => sanitize($_POST['groupe_sanguin'] ?? ''),
            'allergies' => sanitize($_POST['allergies'] ?? ''),
            'handicap' => sanitize($_POST['handicap'] ?? ''),
            'dernier_etablissement' => sanitize($_POST['dernier_etablissement'] ?? ''),
            'doublant' => isset($_POST['doublant']) ? 1 : 0,
            'bourse_active' => isset($_POST['bourse_active']) ? 1 : 0,
            'statut_scolaire' => sanitize($_POST['statut_scolaire'] ?? 'inscrit')
        ];
        
        // Validation
        $errors = validateStudentData($data);
        
        // Vérifier l'unicité de l'email si fourni et différent de l'actuel
        if (!empty($data['email'])) {
            $email_check = "SELECT id FROM eleves WHERE email = :email AND ecole_id = :ecole_id AND id != :student_id";
            $email_stmt = $db->prepare($email_check);
            $email_stmt->execute(['email' => $data['email'], 'ecole_id' => $_SESSION['ecole_id'], 'student_id' => $student_id]);
            if ($email_stmt->fetch()) {
                $errors[] = "Cette adresse email est déjà utilisée par un autre élève.";
            }
        }
        
        if (empty($errors)) {
            // Gérer l'upload de photo si présent
            $photo_path = null;
            if (!empty($_FILES['photo']['name'])) {
                $upload_result = handleStudentPhotoUpload($_FILES['photo'], $student_id);
                if ($upload_result['success']) {
                    $photo_path = $upload_result['filename'];
                } else {
                    $errors[] = $upload_result['message'];
                }
            }
            
            if (empty($errors)) {
                // Mettre à jour l'élève
                $update_fields = [];
                $update_values = [];
                
                foreach ($data as $key => $value) {
                    $update_fields[] = "$key = :$key";
                    $update_values[$key] = $value;
                }
                
                if ($photo_path) {
                    $update_fields[] = "photo_path = :photo_path";
                    $update_values['photo_path'] = $photo_path;
                }
                
                $update_fields[] = "updated_by = :updated_by";
                $update_fields[] = "version = version + 1";
                $update_values['updated_by'] = $_SESSION['user_id'];
                $update_values['student_id'] = $student_id;
                $update_values['ecole_id'] = $_SESSION['ecole_id'];
                
                $update_query = "UPDATE eleves SET " . implode(', ', $update_fields) . 
                               " WHERE id = :student_id AND ecole_id = :ecole_id";
                
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute($update_values);
                
                // Traiter les tuteurs si fournis
                if (isset($_POST['tuteurs']) && is_array($_POST['tuteurs'])) {
                    // Supprimer les anciennes liaisons (soft delete)
                    $delete_liaisons = "UPDATE eleves_tuteurs SET statut = 'supprimé_logique', updated_by = :updated_by 
                                       WHERE eleve_id = :student_id AND statut = 'actif'";
                    $delete_stmt = $db->prepare($delete_liaisons);
                    $delete_stmt->execute(['updated_by' => $_SESSION['user_id'], 'student_id' => $student_id]);
                    
                    // Ajouter les nouveaux tuteurs
                    foreach ($_POST['tuteurs'] as $index => $tuteur_data) {
                        if (empty($tuteur_data['nom']) || empty($tuteur_data['prenom'])) continue;
                        
                        $tuteur_id = null;
                        
                        // Vérifier si le tuteur existe déjà (par téléphone et nom)
                        if (!empty($tuteur_data['telephone'])) {
                            $existing_tuteur = "SELECT id FROM tuteurs WHERE telephone = :telephone AND nom = :nom AND prenom = :prenom AND ecole_id = :ecole_id AND statut = 'actif'";
                            $existing_stmt = $db->prepare($existing_tuteur);
                            $existing_stmt->execute([
                                'telephone' => sanitize($tuteur_data['telephone']),
                                'nom' => sanitize($tuteur_data['nom']),
                                'prenom' => sanitize($tuteur_data['prenom']),
                                'ecole_id' => $_SESSION['ecole_id']
                            ]);
                            $existing_result = $existing_stmt->fetch();
                            if ($existing_result) {
                                $tuteur_id = $existing_result['id'];
                            }
                        }
                        
                        // Si le tuteur n'existe pas, le créer
                        if (!$tuteur_id) {
                            $tuteur_query = "INSERT INTO tuteurs (
                                ecole_id, nom, prenom, lien_parente, telephone, email, adresse, profession,
                                personne_contact_urgence, created_by
                            ) VALUES (
                                :ecole_id, :nom, :prenom, :lien_parente, :telephone, :email, :adresse, :profession,
                                :personne_contact_urgence, :created_by
                            )";
                            
                            $tuteur_stmt = $db->prepare($tuteur_query);
                            $tuteur_stmt->execute([
                                'ecole_id' => $_SESSION['ecole_id'],
                                'nom' => sanitize($tuteur_data['nom']),
                                'prenom' => sanitize($tuteur_data['prenom']),
                                'lien_parente' => sanitize($tuteur_data['lien_parente'] ?? 'autre'),
                                'telephone' => sanitize($tuteur_data['telephone'] ?? ''),
                                'email' => sanitize($tuteur_data['email'] ?? ''),
                                'adresse' => sanitize($tuteur_data['adresse'] ?? ''),
                                'profession' => sanitize($tuteur_data['profession'] ?? ''),
                                'personne_contact_urgence' => isset($tuteur_data['personne_contact_urgence']) ? 1 : 0,
                                'created_by' => $_SESSION['user_id']
                            ]);
                            
                            $tuteur_id = $db->lastInsertId();
                        }
                        
                        // Vérifier si la liaison existe déjà (même avec statut supprimé)
                        $check_liaison = "SELECT id FROM eleves_tuteurs WHERE eleve_id = :eleve_id AND tuteur_id = :tuteur_id";
                        $check_stmt = $db->prepare($check_liaison);
                        $check_stmt->execute(['eleve_id' => $student_id, 'tuteur_id' => $tuteur_id]);
                        $existing_liaison = $check_stmt->fetch();
                        
                        if ($existing_liaison) {
                            // Mettre à jour la liaison existante
                            $update_liaison = "UPDATE eleves_tuteurs SET 
                                              tuteur_principal = :tuteur_principal,
                                              autorisation_sortie = :autorisation_sortie,
                                              statut = 'actif',
                                              updated_by = :updated_by,
                                              updated_at = CURRENT_TIMESTAMP
                                              WHERE id = :liaison_id";
                            $update_stmt = $db->prepare($update_liaison);
                            $update_stmt->execute([
                                'tuteur_principal' => ($index == 0) ? 1 : 0,
                                'autorisation_sortie' => isset($tuteur_data['autorisation_sortie']) ? 1 : 0,
                                'updated_by' => $_SESSION['user_id'],
                                'liaison_id' => $existing_liaison['id']
                            ]);
                        } else {
                            // Créer une nouvelle liaison élève-tuteur
                            $liaison_query = "INSERT INTO eleves_tuteurs (
                                eleve_id, tuteur_id, tuteur_principal, autorisation_sortie, created_by
                            ) VALUES (
                                :eleve_id, :tuteur_id, :tuteur_principal, :autorisation_sortie, :created_by
                            )";
                            
                            $liaison_stmt = $db->prepare($liaison_query);
                            $liaison_stmt->execute([
                                'eleve_id' => $student_id,
                                'tuteur_id' => $tuteur_id,
                                'tuteur_principal' => ($index == 0) ? 1 : 0, // Premier tuteur = principal
                                'autorisation_sortie' => isset($tuteur_data['autorisation_sortie']) ? 1 : 0,
                                'created_by' => $_SESSION['user_id']
                            ]);
                        }
                    }
                }
                
                $db->commit();
                
                // Log de l'action
                logUserAction('UPDATE_STUDENT', "Modification de l'élève ID: $student_id - {$data['nom']} {$data['prenom']}");
                
                setFlashMessage('success', "Les informations de l'élève {$data['prenom']} {$data['nom']} ont été mises à jour avec succès.");
                redirect('view.php?id=' . $student_id);
            }
        }
        
        if (!empty($errors)) {
            $db->rollBack();
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}

// Récupérer les informations actuelles de l'élève
$student_query = "SELECT * FROM eleves WHERE id = :student_id AND ecole_id = :ecole_id AND statut = 'actif'";
$stmt = $db->prepare($student_query);
$stmt->execute(['student_id' => $student_id, 'ecole_id' => $_SESSION['ecole_id']]);
$student = $stmt->fetch();

if (!$student) {
    setFlashMessage('error', 'Élève introuvable ou vous n\'avez pas les permissions pour le modifier.');
    redirect('index.php');
}

// Récupérer les tuteurs actuels - REQUÊTE CORRIGÉE AVEC COALESCE
$tuteurs_query = "SELECT t.id, t.nom, t.prenom, t.telephone, t.email, 
                         COALESCE(t.lien_parente, 'tuteur') as lien_parente
                  FROM tuteurs t
                  JOIN eleves_tuteurs et ON t.id = et.tuteur_id
                  WHERE et.eleve_id = :student_id AND t.statut = 'actif' AND et.statut = 'actif'
                  ORDER BY COALESCE(et.tuteur_principal, 0) DESC, t.nom, t.prenom";

$tuteurs_stmt = $db->prepare($tuteurs_query);
$tuteurs_stmt->execute(['student_id' => $student_id]);
$tuteurs = $tuteurs_stmt->fetchAll();

$page_title = "Modifier " . $student['prenom'] . " " . $student['nom'];
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
                <h1><i class="bi bi-pencil-square me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Modifiez les informations de l'élève</p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../auth/dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Élèves</a></li>
                        <li class="breadcrumb-item"><a href="view.php?id=<?php echo $student['id']; ?>"><?php echo $student['prenom'] . ' ' . $student['nom']; ?></a></li>
                        <li class="breadcrumb-item active">Modifier</li>
                    </ol>
                </nav>
            </div>
            
            <div class="topbar-actions">
                <a href="view.php?id=<?php echo $student['id']; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-eye me-2"></i>Voir le profil
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour à la liste
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="studentEditForm">
                <div class="row g-4">
                    <!-- Informations personnelles -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-person me-2"></i>Informations personnelles</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="nom" class="form-label">Nom *</label>
                                        <input type="text" class="form-control" id="nom" name="nom" 
                                               value="<?php echo htmlspecialchars($student['nom']); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="prenom" class="form-label">Prénom *</label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" 
                                               value="<?php echo htmlspecialchars($student['prenom']); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="postnom" class="form-label">Post-nom</label>
                                        <input type="text" class="form-control" id="postnom" name="postnom" 
                                               value="<?php echo htmlspecialchars($student['postnom']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="sexe" class="form-label">Sexe *</label>
                                        <select class="form-select" id="sexe" name="sexe" required>
                                            <option value="">Choisir...</option>
                                            <option value="M" <?php echo $student['sexe'] == 'M' ? 'selected' : ''; ?>>Masculin</option>
                                            <option value="F" <?php echo $student['sexe'] == 'F' ? 'selected' : ''; ?>>Féminin</option>
                                            <option value="Autre" <?php echo $student['sexe'] == 'Autre' ? 'selected' : ''; ?>>Autre</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="date_naissance" class="form-label">Date de naissance *</label>
                                        <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                                               value="<?php echo htmlspecialchars($student['date_naissance']); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="lieu_naissance" class="form-label">Lieu de naissance</label>
                                        <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance" 
                                               value="<?php echo htmlspecialchars($student['lieu_naissance']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="nationalite" class="form-label">Nationalité</label>
                                        <input type="text" class="form-control" id="nationalite" name="nationalite" 
                                               value="<?php echo htmlspecialchars($student['nationalite']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="groupe_sanguin" class="form-label">Groupe sanguin</label>
                                        <select class="form-select" id="groupe_sanguin" name="groupe_sanguin">
                                            <option value="">Non spécifié</option>
                                            <?php 
                                            $groupes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                            foreach ($groupes as $groupe): 
                                            ?>
                                                <option value="<?php echo $groupe; ?>" <?php echo $student['groupe_sanguin'] == $groupe ? 'selected' : ''; ?>>
                                                    <?php echo $groupe; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact et adresse -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-house me-2"></i>Contact et adresse</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="telephone" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                                               value="<?php echo htmlspecialchars($student['telephone']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($student['email']); ?>">
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="adresse" class="form-label">Adresse complète</label>
                                        <textarea class="form-control" id="adresse" name="adresse" rows="2"><?php echo htmlspecialchars($student['adresse']); ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="quartier" class="form-label">Quartier</label>
                                        <input type="text" class="form-control" id="quartier" name="quartier" 
                                               value="<?php echo htmlspecialchars($student['quartier']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="commune" class="form-label">Commune</label>
                                        <input type="text" class="form-control" id="commune" name="commune" 
                                               value="<?php echo htmlspecialchars($student['commune']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="ville" class="form-label">Ville</label>
                                        <input type="text" class="form-control" id="ville" name="ville" 
                                               value="<?php echo htmlspecialchars($student['ville']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="province" class="form-label">Province</label>
                                        <input type="text" class="form-control" id="province" name="province" 
                                               value="<?php echo htmlspecialchars($student['province']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informations médicales et scolaires -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-heart-pulse me-2"></i>Informations médicales et scolaires</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="allergies" class="form-label">Allergies connues</label>
                                        <textarea class="form-control" id="allergies" name="allergies" rows="2"><?php echo htmlspecialchars($student['allergies']); ?></textarea>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="handicap" class="form-label">Handicap ou besoins spéciaux</label>
                                        <textarea class="form-control" id="handicap" name="handicap" rows="2"><?php echo htmlspecialchars($student['handicap']); ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <label for="dernier_etablissement" class="form-label">Dernier établissement fréquenté</label>
                                        <input type="text" class="form-control" id="dernier_etablissement" name="dernier_etablissement" 
                                               value="<?php echo htmlspecialchars($student['dernier_etablissement']); ?>">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Statut</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="doublant" name="doublant" value="1"
                                                   <?php echo $student['doublant'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="doublant">
                                                Élève doublant
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="bourse_active" name="bourse_active" value="1"
                                                   <?php echo $student['bourse_active'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="bourse_active">
                                                Bourse active
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <?php if (hasRole(['admin', 'direction'])): ?>
                                    <div class="col-md-6">
                                        <label for="statut_scolaire" class="form-label">Statut scolaire</label>
                                        <select class="form-select" id="statut_scolaire" name="statut_scolaire">
                                            <option value="inscrit" <?php echo $student['statut_scolaire'] == 'inscrit' ? 'selected' : ''; ?>>Inscrit</option>
                                            <option value="suspendu" <?php echo $student['statut_scolaire'] == 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                                            <option value="exclu" <?php echo $student['statut_scolaire'] == 'exclu' ? 'selected' : ''; ?>>Exclu</option>
                                            <option value="diplômé" <?php echo $student['statut_scolaire'] == 'diplômé' ? 'selected' : ''; ?>>Diplômé</option>
                                            <option value="abandonné" <?php echo $student['statut_scolaire'] == 'abandonné' ? 'selected' : ''; ?>>Abandonné</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar avec photo et tuteurs -->
                    <div class="col-lg-4">
                        <!-- Photo -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-camera me-2"></i>Photo de l'élève</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="photo-preview mb-3">
                                                            <?php if (!empty($student['photo_path'])): ?>
                            <img id="photoPreview" src="<?php echo getPhotoUrl($student['photo_path']); ?>" 
                                             alt="Photo de l'élève" class="rounded-circle" width="150" height="150">
                                    <?php else: ?>
                                        <img id="photoPreview" src="../assets/images/default-avatar.png" 
                                             alt="Photo de l'élève" class="rounded-circle" width="150" height="150">
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="form-control" id="photo" name="photo" 
                                       accept="image/*" onchange="previewPhoto(this)">
                                <div class="form-text">Format accepté: JPG, PNG (max 5MB)</div>
                            </div>
                        </div>
                        
                        <!-- Informations système -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-info-circle me-2"></i>Informations</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong>Matricule:</strong><br>
                                    <span class="badge bg-light text-dark font-monospace"><?php echo htmlspecialchars($student['matricule']); ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong>Première inscription:</strong><br>
                                    <small><?php echo $student['date_premiere_inscription'] ? date('d/m/Y', strtotime($student['date_premiere_inscription'])) : 'Non définie'; ?></small>
                                </div>
                                <div class="mb-2">
                                    <strong>Créé le:</strong><br>
                                    <small><?php echo date('d/m/Y à H:i', strtotime($student['created_at'])); ?></small>
                                </div>
                                <div class="mb-2">
                                    <strong>Modifié le:</strong><br>
                                    <small><?php echo date('d/m/Y à H:i', strtotime($student['updated_at'])); ?></small>
                                </div>
                                <div>
                                    <strong>Version:</strong> <?php echo $student['version']; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tuteurs -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="bi bi-people me-2"></i>Tuteurs/Parents</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTuteur()">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="tuteursContainer">
                                    <!-- Les tuteurs seront ajoutés ici dynamiquement -->
                                </div>
                                <div class="text-muted small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Les modifications de tuteurs remplacent complètement la liste existante.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons d'action -->
                <div class="card mt-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="view.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Annuler
                            </a>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Enregistrer les modifications
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        let tuteurCount = 0;
        
        // Prévisualisation de la photo
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photoPreview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Ajouter un tuteur
        function addTuteur(tuteurData = {}) {
            const container = document.getElementById('tuteursContainer');
            const tuteurDiv = document.createElement('div');
            tuteurDiv.className = 'tuteur-item border rounded p-3 mb-3';
            tuteurDiv.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Tuteur ${tuteurCount + 1}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTuteur(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                
                <div class="mb-2">
                    <label class="form-label form-label-sm">Nom *</label>
                    <input type="text" class="form-control form-control-sm" name="tuteurs[${tuteurCount}][nom]" 
                           value="${tuteurData.nom || ''}" required>
                </div>
                
                <div class="mb-2">
                    <label class="form-label form-label-sm">Prénom *</label>
                    <input type="text" class="form-control form-control-sm" name="tuteurs[${tuteurCount}][prenom]" 
                           value="${tuteurData.prenom || ''}" required>
                </div>
                
                <div class="mb-2">
                    <label class="form-label form-label-sm">Lien de parenté</label>
                    <select class="form-select form-select-sm" name="tuteurs[${tuteurCount}][lien_parente]">
                        <option value="père" ${tuteurData.lien_parente === 'père' ? 'selected' : ''}>Père</option>
                        <option value="mère" ${tuteurData.lien_parente === 'mère' ? 'selected' : ''}>Mère</option>
                        <option value="tuteur_legal" ${tuteurData.lien_parente === 'tuteur_legal' ? 'selected' : ''}>Tuteur légal</option>
                        <option value="autre" ${tuteurData.lien_parente === 'autre' ? 'selected' : ''}>Autre</option>
                    </select>
                </div>
                
                <div class="mb-2">
                    <label class="form-label form-label-sm">Téléphone *</label>
                    <input type="tel" class="form-control form-control-sm" name="tuteurs[${tuteurCount}][telephone]" 
                           value="${tuteurData.telephone || ''}" required>
                </div>
                
                <div class="mb-2">
                    <label class="form-label form-label-sm">Email</label>
                    <input type="email" class="form-control form-control-sm" name="tuteurs[${tuteurCount}][email]" 
                           value="${tuteurData.email || ''}">
                </div>
                
                <div class="mb-2">
                    <label class="form-label form-label-sm">Profession</label>
                    <input type="text" class="form-control form-control-sm" name="tuteurs[${tuteurCount}][profession]" 
                           value="${tuteurData.profession || ''}">
                </div>
                
                <div class="form-check form-check-sm">
                    <input class="form-check-input" type="checkbox" name="tuteurs[${tuteurCount}][personne_contact_urgence]" 
                           value="1" ${tuteurData.personne_contact_urgence ? 'checked' : ''}>
                    <label class="form-check-label">Personne à contacter en urgence</label>
                </div>
                
                <div class="form-check form-check-sm">
                    <input class="form-check-input" type="checkbox" name="tuteurs[${tuteurCount}][autorisation_sortie]" 
                           value="1" ${tuteurData.autorisation_sortie ? 'checked' : ''}>
                    <label class="form-check-label">Autorisé à récupérer l'élève</label>
                </div>
            `;
            
            container.appendChild(tuteurDiv);
            tuteurCount++;
        }
        
        // Supprimer un tuteur
        function removeTuteur(button) {
            button.closest('.tuteur-item').remove();
        }
        
        // Charger les tuteurs existants au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            const existingTuteurs = <?php echo json_encode($tuteurs); ?>;
            
            if (existingTuteurs.length > 0) {
                existingTuteurs.forEach(tuteur => {
                    addTuteur({
                        nom: tuteur.nom,
                        prenom: tuteur.prenom,
                        lien_parente: tuteur.lien_parente,
                        telephone: tuteur.telephone,
                        email: tuteur.email,
                        profession: tuteur.profession,
                        personne_contact_urgence: tuteur.personne_contact_urgence,
                        autorisation_sortie: tuteur.autorisation_sortie
                    });
                });
            } else {
                // Ajouter un tuteur vide si aucun n'existe
                addTuteur();
            }
        });
        
        // Validation du formulaire
        document.getElementById('studentEditForm').addEventListener('submit', function(e) {
            const tuteurs = document.querySelectorAll('.tuteur-item');
            if (tuteurs.length === 0) {
                e.preventDefault();
                NaklassUtils.showToast('Veuillez ajouter au moins un tuteur/parent.', 'warning');
                return;
            }
            
            // Validation des champs obligatoires des tuteurs
            let valid = true;
            tuteurs.forEach(tuteur => {
                const nom = tuteur.querySelector('input[name*="[nom]"]').value;
                const prenom = tuteur.querySelector('input[name*="[prenom]"]').value;
                const telephone = tuteur.querySelector('input[name*="[telephone]"]').value;
                
                if (!nom || !prenom || !telephone) {
                    valid = false;
                }
            });
            
            if (!valid) {
                e.preventDefault();
                NaklassUtils.showToast('Veuillez remplir tous les champs obligatoires des tuteurs.', 'warning');
            }
        });
    </script>
</body>
</html>
