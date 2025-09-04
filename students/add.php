<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

/**
 * Traite l'importation Excel des élèves
 */
function processExcelImport($db, $file, $classe_id, $strategy) {
    $result = ['success' => false, 'message' => '', 'errors' => []];
    
    try {
        // VÉRIFICATION CRITIQUE : S'assurer que la classe appartient à l'école de l'utilisateur connecté
        if (!isset($_SESSION['ecole_id']) || !isset($_SESSION['user_id'])) {
            $result['errors'][] = "Session utilisateur invalide";
            return $result;
        }
        
        // Vérifier que la classe appartient à l'école de l'utilisateur
        $classe_ecole_check = "SELECT id FROM classes WHERE id = :classe_id AND ecole_id = :ecole_id AND statut = 'actif'";
        $stmt = $db->prepare($classe_ecole_check);
        $stmt->execute([
            'classe_id' => $classe_id,
            'ecole_id' => $_SESSION['ecole_id']
        ]);
        
        if (!$stmt->fetch()) {
            $result['errors'][] = "Classe non autorisée ou inexistante pour cette école";
            return $result;
        }
        // Vérifier le fichier
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = "Erreur lors du téléchargement du fichier.";
            return $result;
        }
        
        // Vérifier l'extension
        $allowed_extensions = ['xlsx', 'xls'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $result['errors'][] = "Format de fichier non supporté. Utilisez .xlsx ou .xls";
            return $result;
        }
        
        // Vérifier la taille (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $result['errors'][] = "Le fichier est trop volumineux. Maximum 5MB autorisé.";
            return $result;
        }
        
        // Lire le fichier Excel
        require_once '../vendor/autoload.php';
        
        if ($file_extension === 'xlsx') {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        } else {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        }
        
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        // Vérifier l'en-tête
        $expected_headers = [
            'Matricule', 'Nom', 'Post-nom', 'Prénom', 'Sexe', 'Date de naissance', 
            'Lieu de naissance', 'Nationalité', 'Téléphone', 'Email', 'Adresse', 'Quartier'
        ];
        
        $headers = array_map('trim', $rows[0]);
        
        if (count(array_diff($expected_headers, $headers)) > 0) {
            $result['errors'][] = "Structure du fichier incorrecte. Vérifiez les en-têtes de colonnes.";
            return $result;
        }
        
        // Traiter les données
        $db->beginTransaction();
        
        $imported = 0;
        $updated = 0;
        $ignored = 0;
        $errors_import = [];
        
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // Ignorer les lignes vides
            if (empty(array_filter($row))) continue;
            
            try {
                $eleve_data = [
                    'matricule' => trim($row[0]),
                    'nom' => trim($row[1]),
                    'postnom' => trim($row[2]),
                    'prenom' => trim($row[3]),
                    'sexe' => strtoupper(trim($row[4])),
                    'date_naissance' => trim($row[5]),
                    'lieu_naissance' => trim($row[6]),
                    'nationalite' => trim($row[7]) ?: 'Congolaise',
                    'telephone' => trim($row[8]),
                    'email' => trim($row[9]),
                    'adresse_complete' => trim($row[10]),
                    'quartier' => trim($row[11])
                ];
                
                // Validation des données
                if (empty($eleve_data['nom']) || empty($eleve_data['prenom']) || empty($eleve_data['sexe'])) {
                    $errors_import[] = "Ligne " . ($i + 1) . ": Données manquantes (nom, prénom ou sexe)";
                    continue;
                }
                
                // Validation du matricule (doit être unique dans cette école)
                if (empty($eleve_data['matricule'])) {
                    $errors_import[] = "Ligne " . ($i + 1) . ": Matricule obligatoire";
                    continue;
                }
                
                // Vérifier que le matricule n'existe pas déjà dans cette école
                $matricule_check = "SELECT id FROM eleves WHERE matricule = :matricule AND ecole_id = :ecole_id";
                $stmt = $db->prepare($matricule_check);
                $stmt->execute([
                    'matricule' => $eleve_data['matricule'],
                    'ecole_id' => $_SESSION['ecole_id']
                ]);
                
                if ($stmt->fetch() && !$existing) {
                    $errors_import[] = "Ligne " . ($i + 1) . ": Matricule déjà utilisé dans cette école";
                    continue;
                }
                
                // VÉRIFICATION CRITIQUE : S'assurer que la classe appartient à l'école de l'utilisateur
                $classe_verification = "SELECT id, annee_scolaire FROM classes WHERE id = :classe_id AND ecole_id = :ecole_id AND statut = 'actif'";
                $stmt = $db->prepare($classe_verification);
                $stmt->execute([
                    'classe_id' => $classe_id,
                    'ecole_id' => $_SESSION['ecole_id']
                ]);
                $classe_verifiee = $stmt->fetch();
                
                if (!$classe_verifiee) {
                    $errors_import[] = "Ligne " . ($i + 1) . ": Classe non autorisée ou inexistante pour cette école";
                    continue;
                }
                
                // Vérifier si l'élève existe déjà dans CETTE école
                $existing_query = "SELECT id FROM eleves WHERE matricule = :matricule AND ecole_id = :ecole_id";
                $stmt = $db->prepare($existing_query);
                $stmt->execute([
                    'matricule' => $eleve_data['matricule'],
                    'ecole_id' => $_SESSION['ecole_id']
                ]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Élève existe déjà
                    if ($strategy === 'ignore') {
                        $ignored++;
                        continue;
                    } elseif ($strategy === 'update') {
                        // Mettre à jour l'élève existant
                        $update_query = "UPDATE eleves SET 
                            nom = :nom, postnom = :postnom, prenom = :prenom, sexe = :sexe,
                            date_naissance = :date_naissance, lieu_naissance = :lieu_naissance,
                            nationalite = :nationalite, telephone = :telephone, email = :email,
                            adresse_complete = :adresse_complete, quartier = :quartier,
                            updated_at = NOW()
                            WHERE id = :id";
                        
                        $stmt = $db->prepare($update_query);
                        $stmt->execute(array_merge($eleve_data, ['id' => $existing['id']]));
                        
                        $updated++;
                    }
                } else {
                    // Nouvel élève
                    $insert_query = "INSERT INTO eleves (
                        ecole_id, matricule, nom, postnom, prenom, sexe, date_naissance, lieu_naissance,
                        nationalite, telephone, email, adresse_complete, quartier, date_premiere_inscription, created_by
                    ) VALUES (
                        :ecole_id, :matricule, :nom, :postnom, :prenom, :sexe, :date_naissance, :lieu_naissance,
                        :nationalite, :telephone, :email, :adresse_complete, :quartier, CURRENT_DATE, :created_by
                    )";
                    
                    $stmt = $db->prepare($insert_query);
                    $stmt->execute(array_merge($eleve_data, [
                        'ecole_id' => $_SESSION['ecole_id'],
                        'created_by' => $_SESSION['user_id']
                    ]));
                    
                    $eleve_id = $db->lastInsertId();
                    
                    // Créer l'inscription dans la classe (déjà vérifiée)
                    if ($classe_verifiee && $classe_verifiee['annee_scolaire']) {
                        $inscription_query = "INSERT INTO inscriptions (
                            eleve_id, classe_id, annee_scolaire, date_inscription, statut_inscription, created_by
                        ) VALUES (
                            :eleve_id, :classe_id, :annee_scolaire, CURRENT_DATE, 'validée', :created_by
                        )";
                        
                        $stmt = $db->prepare($inscription_query);
                        $stmt->execute([
                            'eleve_id' => $eleve_id,
                            'classe_id' => $classe_id,
                            'annee_scolaire' => $classe['annee_scolaire'],
                            'created_by' => $_SESSION['user_id']
                        ]);
                        
                        // Mettre à jour l'effectif de la classe
                        $update_effectif = "UPDATE classes SET effectif_actuel = effectif_actuel + 1 WHERE id = :classe_id";
                        $stmt = $db->prepare($update_effectif);
                        $stmt->execute(['classe_id' => $classe_id]);
                    }
                    
                    $imported++;
                }
                
            } catch (Exception $e) {
                $errors_import[] = "Ligne " . ($i + 1) . ": " . $e->getMessage();
            }
        }
        
        $db->commit();
        
        // Préparer le message de succès
        $message = "Importation terminée : ";
        if ($imported > 0) $message .= "$imported élève(s) importé(s), ";
        if ($updated > 0) $message .= "$updated élève(s) mis à jour, ";
        if ($ignored > 0) $message .= "$ignored élève(s) ignoré(s)";
        
        $result['success'] = true;
        $result['message'] = $message;
        
        if (!empty($errors_import)) {
            $result['errors'] = $errors_import;
        }
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $result['errors'][] = "Erreur lors de l'importation : " . $e->getMessage();
    }
    
    return $result;
}

// VÉRIFICATION OBLIGATOIRE : S'assurer qu'au moins une classe existe
$check_classes = "SELECT COUNT(*) as total FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif'";
$stmt = $db->prepare($check_classes);
$stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$result = $stmt->fetch();

if ($result['total'] == 0) {
    // Aucune classe n'existe, rediriger vers la création de classe
    setFlashMessage('warning', 'Vous devez créer au moins une classe avant de pouvoir inscrire des élèves.');
    redirect('../classes/create.php');
}

// Récupérer les classes disponibles pour le formulaire
$classes_query = "SELECT id, nom_classe, niveau, cycle, annee_scolaire, capacite_max, effectif_actuel 
                  FROM classes 
                  WHERE ecole_id = :ecole_id AND statut = 'actif' 
                  ORDER BY niveau, nom_classe";
$stmt = $db->prepare($classes_query);
$stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$classes_disponibles = $stmt->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier si c'est une importation Excel
    if (isset($_POST['action']) && $_POST['action'] === 'import_excel') {
        // Traitement de l'importation Excel
        $import_result = processExcelImport($db, $_FILES['excel_file'], $_POST['classe_import'], $_POST['import_strategy']);
        
        if ($import_result['success']) {
            setFlashMessage('success', $import_result['message']);
            redirect('index.php');
        } else {
            $errors = $import_result['errors'];
        }
    } else {
        // Validation des données simplifiées pour inscription manuelle
        $data = [
            'nom' => sanitize($_POST['nom'] ?? ''),
            'postnom' => sanitize($_POST['postnom'] ?? ''),
            'prenom' => sanitize($_POST['prenom'] ?? ''),
            'sexe' => sanitize($_POST['sexe'] ?? ''),
            'date_naissance' => sanitize($_POST['date_naissance'] ?? ''),
            'lieu_naissance' => sanitize($_POST['lieu_naissance'] ?? ''),
            'nationalite' => sanitize($_POST['nationalite'] ?? 'Congolaise'),
            'telephone' => sanitize($_POST['telephone'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'adresse_complete' => sanitize($_POST['adresse_complete'] ?? ''),
            'quartier' => sanitize($_POST['quartier'] ?? ''),
            'classe_id' => sanitize($_POST['classe_id'] ?? '')
        ];
        
        // Validation des champs obligatoires
        $errors = [];
        if (empty($data['nom'])) $errors[] = "Le nom est obligatoire.";
        if (empty($data['prenom'])) $errors[] = "Le prénom est obligatoire.";
        if (empty($data['sexe'])) $errors[] = "Le sexe est obligatoire.";
        if (empty($data['date_naissance'])) $errors[] = "La date de naissance est obligatoire.";
        if (empty($data['classe_id'])) $errors[] = "La classe est obligatoire.";
        
        // Vérifier l'unicité de l'email si fourni
        if (!empty($data['email'])) {
            $email_check = "SELECT id FROM eleves WHERE email = :email AND ecole_id = :ecole_id";
            $email_stmt = $db->prepare($email_check);
            $email_stmt->execute(['email' => $data['email'], 'ecole_id' => $_SESSION['ecole_id']]);
            if ($email_stmt->fetch()) {
                $errors[] = "Cette adresse email est déjà utilisée.";
            }
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
            
            // Générer le matricule
            $matricule = generateMatricule('EL', $db);
            
            // Insérer l'élève avec les champs simplifiés
            $insert_query = "INSERT INTO eleves (
                ecole_id, matricule, nom, postnom, prenom, sexe, date_naissance, lieu_naissance,
                nationalite, telephone, email, adresse_complete, quartier, date_premiere_inscription, created_by
            ) VALUES (
                :ecole_id, :matricule, :nom, :postnom, :prenom, :sexe, :date_naissance, :lieu_naissance,
                :nationalite, :telephone, :email, :adresse_complete, :quartier, CURRENT_DATE, :created_by
            )";
            
            $insert_stmt = $db->prepare($insert_query);
            
            // Filtrer $data pour ne garder que les paramètres nécessaires à cette requête
            $eleve_data = [
                'nom' => $data['nom'],
                'postnom' => $data['postnom'],
                'prenom' => $data['prenom'],
                'sexe' => $data['sexe'],
                'date_naissance' => $data['date_naissance'],
                'lieu_naissance' => $data['lieu_naissance'],
                'nationalite' => $data['nationalite'],
                'telephone' => $data['telephone'],
                'email' => $data['email'],
                'adresse_complete' => $data['adresse_complete'],
                'quartier' => $data['quartier']
            ];
            
            $insert_data = array_merge($eleve_data, [
                'ecole_id' => $_SESSION['ecole_id'],
                'matricule' => $matricule,
                'created_by' => $_SESSION['user_id']
            ]);
            
            $insert_stmt->execute($insert_data);
            $eleve_id = $db->lastInsertId();
            
            // Traiter les tuteurs
            if (!empty($_POST['tuteurs'])) {
                foreach ($_POST['tuteurs'] as $index => $tuteur_data) {
                    if (empty($tuteur_data['nom']) || empty($tuteur_data['prenom'])) continue;
                    
                    // Insérer le tuteur (sans lien_parente)
                    $tuteur_query = "INSERT INTO tuteurs (
                        ecole_id, nom, prenom, telephone_principal, email, adresse_complete, profession,
                        personne_contact_urgence, created_by
                    ) VALUES (
                        :ecole_id, :nom, :prenom, :telephone_principal, :email, :adresse_complete, :profession,
                        :personne_contact_urgence, :created_by
                    )";
                    
                    $tuteur_stmt = $db->prepare($tuteur_query);
                    $tuteur_stmt->execute([
                        'ecole_id' => $_SESSION['ecole_id'],
                        'nom' => sanitize($tuteur_data['nom']),
                        'prenom' => sanitize($tuteur_data['prenom']),
                        'telephone_principal' => sanitize($tuteur_data['telephone']),
                        'email' => sanitize($tuteur_data['email'] ?? ''),
                        'adresse_complete' => sanitize($tuteur_data['adresse'] ?? ''),
                        'profession' => sanitize($tuteur_data['profession'] ?? ''),
                        'personne_contact_urgence' => 0, // Par défaut, pas de contact d'urgence
                        'created_by' => $_SESSION['user_id']
                    ]);
                    
                    $tuteur_id = $db->lastInsertId();
                    
                    // Lier l'élève au tuteur avec lien_parente
                    $liaison_query = "INSERT INTO eleve_tuteurs (
                        eleve_id, tuteur_id, lien_parente, tuteur_principal, autorise_recuperer, created_by
                    ) VALUES (
                        :eleve_id, :tuteur_id, :lien_parente, :tuteur_principal, :autorise_recuperer, :created_by
                    )";
                    
                    $liaison_stmt = $db->prepare($liaison_query);
                    $liaison_stmt->execute([
                        'eleve_id' => $eleve_id,
                        'tuteur_id' => $tuteur_id,
                        'lien_parente' => sanitize($tuteur_data['lien_parente']),
                        'tuteur_principal' => ($index == 0) ? 1 : 0, // Premier tuteur = principal
                        'autorise_recuperer' => 1, // Par défaut, autoriser la récupération
                        'created_by' => $_SESSION['user_id']
                    ]);
                }
            }
            
            // Créer l'inscription dans la classe
            $inscription_query = "INSERT INTO inscriptions (
                eleve_id, classe_id, annee_scolaire, date_inscription, statut_inscription, created_by
            ) VALUES (
                 :eleve_id, :classe_id, :annee_scolaire, CURRENT_DATE, 'validée', :created_by
            )";
            
            // Récupérer l'année scolaire de la classe
            $classe_info = "SELECT annee_scolaire FROM classes WHERE id = :classe_id";
            $stmt = $db->prepare($classe_info);
            $stmt->execute(['classe_id' => $data['classe_id']]);
            $classe = $stmt->fetch();
            
            // Vérifier que la classe existe et a une année scolaire
            if (!$classe || empty($classe['annee_scolaire'])) {
                throw new Exception("Impossible de récupérer l'année scolaire de la classe sélectionnée.");
            }
            
            $inscription_stmt = $db->prepare($inscription_query);
            $inscription_stmt->execute([
                'eleve_id' => $eleve_id,
                'classe_id' => $data['classe_id'],
                'annee_scolaire' => $classe['annee_scolaire'],
                'created_by' => $_SESSION['user_id']
            ]);
            
            // Mettre à jour l'effectif de la classe
            $update_effectif = "UPDATE classes SET effectif_actuel = effectif_actuel + 1 WHERE id = :classe_id";
            $stmt = $db->prepare($update_effectif);
            $stmt->execute(['classe_id' => $data['classe_id']]);
            
            $db->commit();
            
            // Log de l'action
            logUserAction('CREATE_STUDENT', "Inscription de l'élève: $matricule - {$data['nom']} {$data['prenom']}");
            
            setFlashMessage('success', "Inscription réussie ! L'élève {$data['prenom']} {$data['nom']} a été inscrit avec succès. Matricule: $matricule");
            
            // Rediriger vers la page de confirmation avec génération de carte
            redirect('inscription_success.php?id=' . $eleve_id . '&matricule=' . urlencode($matricule));
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Erreur lors de l'enregistrement: " . $e->getMessage();
        }
            } // Fin du if (empty($errors))
        } // Fin du else
    } // Fin du if ($_SERVER['REQUEST_METHOD'] === 'POST')

$page_title = "Inscription d'un Élève";
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
                <h1><i class="bi bi-person-plus-fill me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Complétez le formulaire simplifié pour inscrire un nouvel élève</p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../auth/dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Élèves</a></li>
                        <li class="breadcrumb-item active">Inscription</li>
                    </ol>
                </nav>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Retour
                </a>
            </div>
        </header>
        
        <!-- Contenu principal -->
        <div class="container-fluid">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5><i class="bi bi-exclamation-triangle me-2"></i>Erreurs de validation</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Onglets d'inscription -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" id="inscriptionTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab">
                                        <i class="bi bi-person-plus me-2"></i>Inscription Manuelle
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="excel-tab" data-bs-toggle="tab" data-bs-target="#excel" type="button" role="tab">
                                        <i class="bi bi-file-earmark-excel me-2"></i>Importation Excel
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="inscriptionTabsContent">
                                <!-- Onglet Inscription Manuelle -->
                                <div class="tab-pane fade show active" id="manual" role="tabpanel">
                                    <form method="POST" enctype="multipart/form-data">
                                <!-- Informations personnelles -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary border-bottom pb-2 mb-3">
                                            <i class="bi bi-person me-2"></i>Informations personnelles
                                        </h6>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="nom" class="form-label">Nom *</label>
                                        <input type="text" class="form-control" id="nom" name="nom" 
                                               value="<?php echo $_POST['nom'] ?? ''; ?>" required>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="postnom" class="form-label">Post-nom</label>
                                        <input type="text" class="form-control" id="postnom" name="postnom" 
                                               value="<?php echo $_POST['postnom'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="prenom" class="form-label">Prénom *</label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" 
                                               value="<?php echo $_POST['prenom'] ?? ''; ?>" required>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="sexe" class="form-label">Sexe *</label>
                                        <select class="form-select" id="sexe" name="sexe" required>
                                            <option value="">Sélectionner...</option>
                                            <option value="M" <?php echo ($_POST['sexe'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                            <option value="F" <?php echo ($_POST['sexe'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                                            <option value="Autre" <?php echo ($_POST['sexe'] ?? '') === 'Autre' ? 'selected' : ''; ?>>Autre</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="date_naissance" class="form-label">Date de naissance *</label>
                                        <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                                               value="<?php echo $_POST['date_naissance'] ?? ''; ?>" required>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="lieu_naissance" class="form-label">Lieu de naissance</label>
                                        <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance" 
                                               value="<?php echo $_POST['lieu_naissance'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="nationalite" class="form-label">Nationalité</label>
                                        <input type="text" class="form-control" id="nationalite" name="nationalite" 
                                               value="<?php echo $_POST['nationalite'] ?? 'Congolaise'; ?>">
                            </div>
                        </div>
                        
                        <!-- Contact et adresse -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary border-bottom pb-2 mb-3">
                                            <i class="bi bi-geo-alt me-2"></i>Contact et adresse
                                        </h6>
                            </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="telephone" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                                               value="<?php echo $_POST['telephone'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo $_POST['email'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="adresse_complete" class="form-label">Adresse complète</label>
                                        <input type="text" class="form-control" id="adresse_complete" name="adresse_complete" 
                                               value="<?php echo $_POST['adresse_complete'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="quartier" class="form-label">Quartier</label>
                                        <input type="text" class="form-control" id="quartier" name="quartier" 
                                               value="<?php echo $_POST['quartier'] ?? ''; ?>">
                                    </div>
                                    </div>
                                    
                                <!-- Photo de l'élève -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary border-bottom pb-2 mb-3">
                                            <i class="bi bi-camera me-2"></i>Photo de l'élève
                                        </h6>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="photo" class="form-label">Photo (optionnel)</label>
                                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                        <div class="form-text">Formats acceptés : JPG, PNG, GIF. Taille max : 2MB</div>
                            </div>
                        </div>
                        
                                <!-- Tuteurs/Parents et Classe -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary border-bottom pb-2 mb-3">
                                            <i class="bi bi-people me-2"></i>Tuteurs/Parents et Classe
                                        </h6>
                                    </div>
                                    
                                    <!-- Sélection de la classe -->
                                    <div class="col-md-6 mb-3">
                                        <label for="classe_id" class="form-label">Classe *</label>
                                        <select class="form-select" id="classe_id" name="classe_id" required>
                                            <option value="">Sélectionner une classe...</option>
                                            <?php foreach ($classes_disponibles as $classe): ?>
                                                <?php 
                                                $disponibilite = $classe['capacite_max'] - $classe['effectif_actuel'];
                                                $disabled = $disponibilite <= 0 ? 'disabled' : '';
                                                $selected = ($_POST['classe_id'] ?? '') == $classe['id'] ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo $classe['id']; ?>" <?php echo $selected . ' ' . $disabled; ?>>
                                                    <?php echo $classe['nom_classe']; ?> (<?php echo $classe['niveau']; ?>) - 
                                                    <?php echo $classe['annee_scolaire']; ?> - 
                                                    Disponibilité: <?php echo $disponibilite; ?>/<?php echo $classe['capacite_max']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Sélectionnez la classe où inscrire l'élève</div>
                                    </div>
                                    
                                    <!-- Tuteurs -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tuteurs/Parents</label>
                                        <div id="tuteurs-container">
                                            <div class="tuteur-item border rounded p-3 mb-2">
                                                <div class="row">
                                                    <div class="col-md-6 mb-2">
                                                        <input type="text" class="form-control form-control-sm" 
                                                               name="tuteurs[0][nom]" placeholder="Nom du tuteur" required>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <input type="text" class="form-control form-control-sm" 
                                                               name="tuteurs[0][prenom]" placeholder="Prénom du tuteur" required>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <select class="form-select form-select-sm" name="tuteurs[0][lien_parente]">
                                                            <option value="père">Père</option>
                                                            <option value="mère">Mère</option>
                                                            <option value="tuteur_legal">Tuteur légal</option>
                                                            <option value="autre">Autre</option>
                                                        </select>
                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <input type="tel" class="form-control form-control-sm" 
                                                               name="tuteurs[0][telephone]" placeholder="Téléphone">
                                        </div>
                                                    <div class="col-md-6 mb-2">
                                                        <input type="email" class="form-control form-control-sm" 
                                                               name="tuteurs[0][email]" placeholder="Email">
                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <input type="text" class="form-control form-control-sm" 
                                                               name="tuteurs[0][profession]" placeholder="Profession">
                                </div>
                            </div>
                        </div>
                    </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="ajouterTuteur()">
                                            <i class="bi bi-plus"></i> Ajouter un autre tuteur
                                        </button>
                            </div>
                        </div>
                        
                                <!-- Boutons d'action -->
                                <div class="row">
                                    <div class="col-12 text-center">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-check-circle me-2"></i>Inscrire l'élève
                                        </button>
                                        <a href="index.php" class="btn btn-secondary btn-lg ms-2">
                                            <i class="bi bi-x-circle me-2"></i>Annuler
                                        </a>
                                    </div>
                                </div>
                            </form>
                                </div>
                                
                                <!-- Onglet Importation Excel -->
                                <div class="tab-pane fade" id="excel" role="tabpanel">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <h6><i class="bi bi-info-circle me-2"></i>Instructions d'importation</h6>
                                                <p class="mb-2">Téléchargez le modèle Excel, remplissez-le avec vos données, puis importez-le ici.</p>
                                                <ul class="mb-0">
                                                    <li>Format accepté : <strong>.xlsx</strong> ou <strong>.xls</strong></li>
                                                    <li>Maximum : <strong>100 élèves</strong> par importation</li>
                                                    <li>Les élèves existants seront <strong>ignorés</strong> ou <strong>mis à jour</strong> selon vos choix</li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="card border-primary">
                                                <div class="card-header bg-primary text-white">
                                                    <h6 class="mb-0"><i class="bi bi-download me-2"></i>Télécharger le modèle</h6>
                                                </div>
                                                <div class="card-body text-center">
                                                    <p class="mb-3">Téléchargez le fichier modèle avec la structure requise</p>
                                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                                        <a href="download_template.php" class="btn btn-primary">
                                                            <i class="bi bi-file-earmark-excel me-2"></i>Modèle Excel
                                                        </a>
                                                        <a href="download_template_csv.php" class="btn btn-success">
                                                            <i class="bi bi-file-earmark-text me-2"></i>Modèle CSV
                                                        </a>
                                                    </div>
                                                    <small class="text-muted mt-2 d-block">
                                                        <strong>Recommandé :</strong> Utilisez le modèle CSV si PhpSpreadsheet n'est pas installé
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="card border-success">
                                                <div class="card-header bg-success text-white">
                                                    <h6 class="mb-0"><i class="bi bi-upload me-2"></i>Importer des élèves</h6>
                                                </div>
                                                <div class="card-body">
                                                    <form method="POST" enctype="multipart/form-data" id="excelForm">
                                                        <input type="hidden" name="action" value="import_excel">
                                                        
                                                        <div class="mb-3">
                                                            <label for="excel_file" class="form-label">Fichier Excel *</label>
                                                            <input type="file" class="form-control" id="excel_file" name="excel_file" 
                                                                   accept=".xlsx,.xls" required>
                                                            <div class="form-text">Sélectionnez votre fichier Excel rempli</div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="classe_import" class="form-label">Classe par défaut *</label>
                                                            <select class="form-select" id="classe_import" name="classe_import" required>
                                                                <option value="">Choisir une classe...</option>
                                                                <?php foreach ($classes_disponibles as $classe): ?>
                                                                    <?php 
                                                                        $disponibilite = $classe['capacite_max'] - $classe['effectif_actuel'];
                                                                        $selected = ($_POST['classe_import'] ?? '') == $classe['id'] ? 'selected' : '';
                                                                        $disabled = $disponibilite <= 0 ? 'disabled' : '';
                                                                    ?>
                                                                    <option value="<?php echo $classe['id']; ?>" <?php echo $selected . ' ' . $disabled; ?>>
                                                                        <?php echo $classe['nom_classe']; ?> (<?php echo $classe['niveau']; ?>) - 
                                                                        <?php echo $classe['annee_scolaire']; ?> - 
                                                                        Disponibilité: <?php echo $disponibilite; ?>/<?php echo $classe['capacite_max']; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="import_strategy" class="form-label">Stratégie pour les doublons</label>
                                                            <select class="form-select" id="import_strategy" name="import_strategy">
                                                                <option value="ignore">Ignorer les élèves existants</option>
                                                                <option value="update">Mettre à jour les informations</option>
                                                                <option value="ask">Demander pour chaque doublon</option>
                                                            </select>
                                                            <div class="form-text">Que faire si un élève existe déjà ?</div>
                                                        </div>
                                                        
                                                        <div class="text-center">
                                                            <button type="submit" class="btn btn-success btn-lg">
                                                                <i class="bi bi-upload me-2"></i>Importer les élèves
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Aperçu des données importées -->
                                    <div class="row mt-4" id="previewSection" style="display: none;">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0"><i class="bi bi-eye me-2"></i>Aperçu des données</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div id="previewContent"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialisation des onglets Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            // Vérifier que Bootstrap est chargé
            if (typeof bootstrap !== 'undefined') {
                // Activer les onglets
                var triggerTabList = [].slice.call(document.querySelectorAll('#inscriptionTabs button'))
                triggerTabList.forEach(function (triggerEl) {
                    var tabTrigger = new bootstrap.Tab(triggerEl)
                });
                
                console.log('Onglets Bootstrap initialisés');
            } else {
                console.error('Bootstrap non chargé');
            }
        });
        
        let tuteurCount = 1;
        
        function ajouterTuteur() {
            const container = document.getElementById('tuteurs-container');
            const newTuteur = document.createElement('div');
            newTuteur.className = 'tuteur-item border rounded p-3 mb-2';
            newTuteur.innerHTML = `
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control form-control-sm" 
                               name="tuteurs[${tuteurCount}][nom]" placeholder="Nom du tuteur" required>
                </div>
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control form-control-sm" 
                               name="tuteurs[${tuteurCount}][prenom]" placeholder="Prénom du tuteur" required>
                </div>
                    <div class="col-md-6 mb-2">
                    <select class="form-select form-select-sm" name="tuteurs[${tuteurCount}][lien_parente]">
                        <option value="père">Père</option>
                        <option value="mère">Mère</option>
                        <option value="tuteur_legal">Tuteur légal</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                    <div class="col-md-6 mb-2">
                        <input type="tel" class="form-control form-control-sm" 
                               name="tuteurs[${tuteurCount}][telephone]" placeholder="Téléphone">
                </div>
                    <div class="col-md-6 mb-2">
                        <input type="email" class="form-control form-control-sm" 
                               name="tuteurs[${tuteurCount}][email]" placeholder="Email">
                </div>
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control form-control-sm" 
                               name="tuteurs[${tuteurCount}][profession]" placeholder="Profession">
                </div>
                    <div class="col-12 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="supprimerTuteur(this)">
                            <i class="bi bi-trash"></i> Supprimer
                        </button>
                </div>
                </div>
            `;
            container.appendChild(newTuteur);
            tuteurCount++;
        }
        
        function supprimerTuteur(button) {
            button.closest('.tuteur-item').remove();
        }
    </script>
</body>
</html>
