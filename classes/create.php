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

// Récupérer les enseignants disponibles
try {
    $enseignants_query = "SELECT * FROM enseignants WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom ASC, prenom ASC";
    $stmt = $db->prepare($enseignants_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $enseignants = $stmt->fetchAll();
} catch (Exception $e) {
    $enseignants = [];
    $errors[] = "Erreur lors de la récupération des enseignants: " . $e->getMessage();
}

// Récupérer les informations de l'école
try {
    $ecole_query = "SELECT * FROM ecoles WHERE id = :ecole_id";
    $stmt = $db->prepare($ecole_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $ecole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer l'année scolaire active
    $annee_active_query = "SELECT * FROM annees_scolaires WHERE ecole_id = :ecole_id AND active = TRUE AND statut = 'actif' LIMIT 1";
    $stmt = $db->prepare($annee_active_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annee_active = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $ecole = [];
    $annee_active = [];
    $errors[] = "Erreur lors de la récupération des informations de l'école: " . $e->getMessage();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom_classe' => sanitize($_POST['nom_classe'] ?? ''),
        'niveau' => sanitize($_POST['niveau'] ?? ''),
        'cycle' => sanitize($_POST['cycle'] ?? ''),
        'niveau_detaille' => sanitize($_POST['niveau_detaille'] ?? ''),
        'option_section' => sanitize($_POST['option_section'] ?? ''),
        'annee_scolaire' => sanitize($_POST['annee_scolaire'] ?? ''),
        'capacite_max' => !empty($_POST['capacite_max']) ? intval($_POST['capacite_max']) : 50,
        'professeur_principal_id' => !empty($_POST['professeur_principal_id']) ? intval($_POST['professeur_principal_id']) : null,
        'salle_classe' => sanitize($_POST['salle_classe'] ?? ''),
        'statut' => sanitize($_POST['statut'] ?? 'actif')
    ];
    
    // Validation des données
    if (empty($data['nom_classe'])) {
        $errors[] = "Le nom de la classe est obligatoire.";
    }
    
    if (strlen($data['nom_classe']) < 2) {
        $errors[] = "Le nom de la classe doit contenir au moins 2 caractères.";
    }
    
    if (empty($data['niveau'])) {
        $errors[] = "Le niveau scolaire est obligatoire.";
    }
    
    if (empty($data['cycle'])) {
        $errors[] = "Le cycle est obligatoire.";
    }
    
    if (empty($data['annee_scolaire'])) {
        $errors[] = "L'année scolaire est obligatoire.";
    }
    
    if ($data['capacite_max'] <= 0) {
        $errors[] = "La capacité maximale doit être un nombre positif.";
    }
    
    if ($data['capacite_max'] > 200) {
        $errors[] = "La capacité maximale ne peut pas dépasser 200 élèves.";
    }
    
    // Vérifier l'unicité du nom de classe dans l'école pour l'année scolaire
    if (!empty($data['nom_classe']) && !empty($data['annee_scolaire'])) {
        $check_query = "SELECT id FROM classes WHERE nom_classe = :nom_classe AND ecole_id = :ecole_id AND annee_scolaire = :annee_scolaire";
        $stmt = $db->prepare($check_query);
        $stmt->execute([
            'nom_classe' => $data['nom_classe'], 
            'ecole_id' => $_SESSION['ecole_id'],
            'annee_scolaire' => $data['annee_scolaire']
        ]);
        if ($stmt->fetch()) {
            $errors[] = "Une classe avec ce nom existe déjà dans votre établissement pour cette année scolaire.";
        }
    }
    
    // Vérifier que l'enseignant existe et appartient à l'école (si spécifié)
    if ($data['professeur_principal_id'] !== null) {
        $enseignant_check = "SELECT id FROM enseignants WHERE id = :id AND ecole_id = :ecole_id AND statut = 'actif'";
        $stmt = $db->prepare($enseignant_check);
        $stmt->execute(['id' => $data['professeur_principal_id'], 'ecole_id' => $_SESSION['ecole_id']]);
        if (!$stmt->fetch()) {
            $errors[] = "Enseignant principal sélectionné invalide.";
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Créer une description complète du cycle pour l'affichage
            $cycle_complet = '';
            if (!empty($data['cycle'])) {
                $cycle_labels = [
                    'maternelle' => 'Maternelle (3 ans)',
                    'primaire' => 'Primaire (6 ans)',
                    'secondaire' => 'Secondaire (6 ans)',
                    'supérieur' => 'Supérieur'
                ];
                $cycle_complet = $cycle_labels[$data['cycle']] ?? $data['cycle'];
                
                // Ajouter le niveau détaillé
                if (!empty($data['niveau_detaille'])) {
                    $niveau_labels = [
                        '1ere_maternelle' => '1ʳᵉ année maternelle',
                        '2eme_maternelle' => '2ᵉ année maternelle',
                        '3eme_maternelle' => '3ᵉ année maternelle',
                        '1eme_primaire' => '1ᵉ année primaire',
                        '2eme_primaire' => '2ᵉ année primaire',
                        '3eme_primaire' => '3ᵉ année primaire',
                        '4eme_primaire' => '4ᵉ année primaire',
                        '5eme_primaire' => '5ᵉ année primaire',
                        '6eme_primaire' => '6ᵉ année primaire',
                        '7eme_secondaire' => '7ᵉ secondaire',
                        '8eme_secondaire' => '8ᵉ secondaire',
                        '1ere_humanites' => '1ʳᵉ humanités',
                        '2eme_humanites' => '2ᵉ humanités',
                        '3eme_humanites' => '3ᵉ humanités',
                        '4eme_humanites' => '4ᵉ humanités'
                    ];
                    $cycle_complet .= ' - ' . ($niveau_labels[$data['niveau_detaille']] ?? $data['niveau_detaille']);
                }
                
                // Ajouter l'option/section si disponible
                if (!empty($data['option_section'])) {
                    $option_labels = [
                        'scientifique' => 'Scientifique',
                        'litteraire' => 'Littéraire',
                        'commerciale' => 'Commerciale',
                        'pedagogique' => 'Pédagogique',
                        'technique_construction' => 'Technique de Construction',
                        'technique_electricite' => 'Technique Électricité',
                        'technique_mecanique' => 'Technique Mécanique',
                        'technique_informatique' => 'Technique Informatique',
                        'secretariat' => 'Secrétariat',
                        'comptabilite' => 'Comptabilité',
                        'hotellerie' => 'Hôtellerie',
                        'couture' => 'Couture'
                    ];
                    $cycle_complet .= ' (' . ($option_labels[$data['option_section']] ?? $data['option_section']) . ')';
                }
            }
            
            // Insérer la nouvelle classe
            $insert_query = "INSERT INTO classes (
                ecole_id, nom_classe, niveau, cycle, niveau_detaille, option_section, cycle_complet,
                annee_scolaire, capacite_max, professeur_principal_id, salle_classe, statut, created_by
            ) VALUES (
                :ecole_id, :nom_classe, :niveau, :cycle, :niveau_detaille, :option_section, :cycle_complet,
                :annee_scolaire, :capacite_max, :professeur_principal_id, :salle_classe, :statut, :created_by
            )";
            
            $stmt = $db->prepare($insert_query);
            $insert_data = array_merge($data, [
                'ecole_id' => $_SESSION['ecole_id'],
                'cycle_complet' => $cycle_complet,
                'created_by' => $_SESSION['user_id']
            ]);
            
            $stmt->execute($insert_data);
            $classe_id = $db->lastInsertId();
            
            $db->commit();
            
            // Log de l'action
            logUserAction('CREATE_CLASS', "Création de la classe: {$data['nom_classe']} (ID: $classe_id)");
            
            // 🔔 NOTIFICATION: Nouvelle classe créée
            try {
                require_once '../includes/NotificationManager.php';
                $notificationManager = new NotificationManager();
                
                // Notification pour l'administrateur qui a créé la classe
                require_once '../includes/notification_links.php';
                $notificationManager->createNotificationFromTemplate('course_assigned', [
                    'class_name' => $data['nom_classe'],
                    'cycle_complet' => $cycle_complet,
                    'professeur_name' => $data['professeur_principal_id'] ? 'Professeur assigné' : 'Aucun professeur assigné',
                    'capacite_max' => $data['capacite_max'],
                    'salle_classe' => $data['salle_classe'] ?: 'Non spécifiée',
                    'class_id' => $classe_id
                ], [
                    'priority' => 'normal',
                    'channels' => ['web'],
                    'action_url' => generateNotificationSecureLink('course_assigned', ['class_id' => $classe_id]),
                    'action_text' => getNotificationActionText('course_assigned')
                ]);
                
                // Notification pour le professeur principal si assigné
                if ($data['professeur_principal_id']) {
                    $prof_notification = new NotificationManager(null, $_SESSION['ecole_id']);
                    $prof_notification->createNotificationFromTemplate('teacher_assigned', [
                        'teacher_name' => 'Vous',
                        'class_name' => $data['nom_classe'],
                        'cycle_complet' => $cycle_complet,
                        'capacite_max' => $data['capacite_max'],
                        'class_id' => $classe_id
                    ], [
                        'priority' => 'normal',
                        'channels' => ['web', 'email'],
                        'action_url' => generateNotificationSecureLink('teacher_assigned', ['class_id' => $classe_id]),
                        'action_text' => getNotificationActionText('teacher_assigned')
                    ]);
                }
                
            } catch (Exception $e) {
                error_log("Erreur création notification classe: " . $e->getMessage());
                // Ne pas faire échouer la création de classe pour une erreur de notification
            }
            
            setFlashMessage('success', "La classe '{$data['nom_classe']}' a été créée avec succès.");
            redirect(createSecureLink('view.php', $classe_id, 'id'));
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Erreur lors de la création de la classe: " . $e->getMessage();
        }
    }
}

$page_title = "Créer une Classe";
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
    <style>
        .preview-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            transition: transform 0.3s ease;
        }
        
        .preview-card:hover {
            transform: scale(1.02);
        }
        
        .form-section {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 0 10px 10px 0;
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
                <h1><i class="bi bi-building me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Créez une nouvelle classe pour organiser vos élèves</p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Classes</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Créer</li>
                    </ol>
                </nav>
            </div>
        </header>
        
        <!-- Contenu principal -->
        <div class="container-fluid">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Erreurs de validation :</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Formulaire principal -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-plus-circle me-2"></i>Informations de la classe
                                <?php if ($ecole): ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($ecole['nom_ecole']); ?>
                                    </small>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="createClassForm">
                                <!-- Informations de base -->
                                <div class="form-section">
                                    <h5><i class="bi bi-info-circle me-2"></i>Informations de base</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="nom_classe" class="form-label">Nom de la classe *</label>
                                            <input type="text" class="form-control" id="nom_classe" name="nom_classe" 
                                                   value="<?php echo htmlspecialchars($_POST['nom_classe'] ?? ''); ?>" 
                                                   placeholder="Ex: 6ème A, CE1, 1ère S..." required>
                                            <div class="form-text">Nom ou identifiant de la classe</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="capacite_max" class="form-label">Capacité maximale</label>
                                            <input type="number" class="form-control" id="capacite_max" name="capacite_max" 
                                                   value="<?php echo htmlspecialchars($_POST['capacite_max'] ?? '50'); ?>" 
                                                   min="1" max="200" placeholder="50">
                                            <div class="form-text">Nombre maximum d'élèves autorisés</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Classification -->
                                <div class="form-section">
                                    <h5><i class="bi bi-diagram-3 me-2"></i>Classification</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="niveau" class="form-label">Niveau scolaire *</label>
                                            <input type="text" class="form-control" id="niveau" name="niveau" 
                                                   value="<?php echo htmlspecialchars($_POST['niveau'] ?? ''); ?>" 
                                                   placeholder="Ex: 6ème, CE1, 1ère S..." required>
                                            <div class="form-text">
                                                <?php if ($ecole): ?>
                                                    <strong>💡 Conseil :</strong> Utilisez le champ "Niveau détaillé" ci-dessous pour une sélection précise selon le cycle choisi.
                                                <?php else: ?>
                                                    Ex: 1ère, 2ème, 3ème... (utilisez le niveau détaillé pour plus de précision)
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="cycle" class="form-label">Cycle *</label>
                                            <div class="input-group">
                                                <select class="form-select" id="cycle" name="cycle" required>
                                                    <option value="">Sélectionner un cycle</option>
                                                    <?php 
                                                    $selected_cycle = $_POST['cycle'] ?? '';
                                                    $type_enseignement = $ecole['type_enseignement'] ?? '';
                                                    
                                                    // Convertir la chaîne SET en tableau
                                                    $types_array = !empty($type_enseignement) ? explode(',', $type_enseignement) : [];
                                                    
                                                    // Définir les correspondances entre les valeurs de la BD et les labels d'affichage
                                                    $cycle_labels = [
                                                        'maternelle' => 'Maternelle (3 ans)',
                                                        'primaire' => 'Primaire (6 ans) - 1ʳᵉ à 6ᵉ année',
                                                        'secondaire' => 'Secondaire (6 ans) - Tronc commun + Humanités',
                                                        'technique' => 'Technique',
                                                        'professionnel' => 'Professionnel',
                                                        'université' => 'Supérieur'
                                                    ];
                                                    
                                                    // Afficher dynamiquement les options basées sur les données de la BD
                                                    foreach ($types_array as $type) {
                                                        $type = trim($type); // Enlever les espaces
                                                        if (isset($cycle_labels[$type])) {
                                                            $value = ($type === 'université') ? 'supérieur' : $type;
                                                            $selected = ($selected_cycle == $value) ? 'selected' : '';
                                                            echo "<option value=\"{$value}\" {$selected}>{$cycle_labels[$type]}</option>";
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCycleModal">
                                                    <i class="bi bi-plus-circle"></i>
                                                </button>
                                            </div>
                                            <div class="form-text">
                                                <?php if ($ecole): ?>
                                                    Cycles disponibles: <strong><?php echo htmlspecialchars(str_replace(',', ', ', $ecole['type_enseignement'])); ?></strong>
                                                <?php else: ?>
                                                    Sélectionnez le cycle d'enseignement
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Niveau détaillé basé sur le cycle -->
                                        <div class="col-md-6" id="niveau_detaille_container" style="display: none;">
                                            <label for="niveau_detaille" class="form-label">Niveau détaillé</label>
                                            <select class="form-select" id="niveau_detaille" name="niveau_detaille">
                                                <option value="">Sélectionner un niveau</option>
                                            </select>
                                            <div class="form-text">Niveau spécifique dans le cycle sélectionné</div>
                                        </div>
                                        
                                        <!-- Options et sections pour le cycle Humanités -->
                                        <div class="col-md-6" id="options_sections_container" style="display: none;">
                                            <label for="option_section" class="form-label">Option/Section</label>
                                            <div class="input-group">
                                                <select class="form-select" id="option_section" name="option_section">
                                                    <option value="">Sélectionner une option</option>
                                                    <optgroup label="Sections générales">
                                                        <option value="scientifique">Scientifique</option>
                                                        <option value="litteraire">Littéraire</option>
                                                        <option value="commerciale">Commerciale</option>
                                                        <option value="pedagogique">Pédagogique</option>
                                                    </optgroup>
                                                    <optgroup label="Sections techniques">
                                                        <option value="technique_construction">Technique de Construction</option>
                                                        <option value="technique_electricite">Technique Électricité</option>
                                                        <option value="technique_mecanique">Technique Mécanique</option>
                                                        <option value="technique_informatique">Technique Informatique</option>
                                                    </optgroup>
                                                    <optgroup label="Sections professionnelles">
                                                        <option value="secretariat">Secrétariat</option>
                                                        <option value="comptabilite">Comptabilité</option>
                                                        <option value="hotellerie">Hôtellerie</option>
                                                        <option value="couture">Couture</option>
                                                    </optgroup>
                                                </select>
                                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                                                    <i class="bi bi-plus-circle"></i>
                                                </button>
                                            </div>
                                            <div class="form-text">Spécialisation pour le cycle Humanités</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="annee_scolaire" class="form-label">Année scolaire *</label>
                                            <input type="text" class="form-control" id="annee_scolaire" name="annee_scolaire" 
                                                   value="<?php echo htmlspecialchars($_POST['annee_scolaire'] ?? ($annee_active['libelle'] ?? date('Y') . '-' . (date('Y') + 1))); ?>" 
                                                   placeholder="Ex: 2024-2025" required>
                                            <div class="form-text">
                                                <?php if ($annee_active): ?>
                                                    Année active: <strong><?php echo htmlspecialchars($annee_active['libelle']); ?></strong>
                                                <?php else: ?>
                                                    Format: AAAA-AAAA
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Affectation et organisation -->
                                <div class="form-section">
                                    <h5><i class="bi bi-person-workspace me-2"></i>Affectation et organisation</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="professeur_principal_id" class="form-label">Enseignant principal</label>
                                            <select class="form-select" id="professeur_principal_id" name="professeur_principal_id">
                                                <option value="">Sélectionner un enseignant</option>
                                                <?php foreach ($enseignants as $enseignant): ?>
                                                    <option value="<?php echo $enseignant['id']; ?>" 
                                                            <?php echo ($_POST['professeur_principal_id'] ?? '') == $enseignant['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Enseignant responsable de la classe</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="salle_classe" class="form-label">Salle de classe</label>
                                            <input type="text" class="form-control" id="salle_classe" name="salle_classe" 
                                                   value="<?php echo htmlspecialchars($_POST['salle_classe'] ?? ''); ?>" 
                                                   placeholder="Ex: A101, Salle 15, Lab Sciences...">
                                            <div class="form-text">Numéro ou nom de la salle</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="statut" class="form-label">Statut de la classe</label>
                                            <select class="form-select" id="statut" name="statut">
                                                <option value="actif" <?php echo ($_POST['statut'] ?? 'actif') == 'actif' ? 'selected' : ''; ?>>
                                                    Active - Prête à recevoir des élèves
                                                </option>
                                                <option value="archivé" <?php echo ($_POST['statut'] ?? '') == 'archivé' ? 'selected' : ''; ?>>
                                                    Archivée - Classe terminée
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Boutons d'action -->
                                <div class="d-flex justify-content-between">
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Annuler
                                    </a>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-2"></i>Créer la Classe
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Aperçu de la classe -->
                <div class="col-lg-4">
                    <div class="sticky-top" style="top: 20px;">
                        <!-- Aperçu en temps réel -->
                        <div class="preview-card card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="bg-white bg-opacity-20 rounded-circle p-3">
                                            <i class="bi bi-building fs-2"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h4 class="text-white mb-1" id="preview-nom">Nouvelle Classe</h4>
                                        <p class="text-white-50 mb-0" id="preview-niveau">Configuration en cours...</p>
                                        <?php if ($ecole): ?>
                                            <small class="text-white-75">
                                                <i class="bi bi-info-circle me-1"></i>
                                                <?php echo htmlspecialchars($ecole['regime']); ?> • 
                                                <?php echo htmlspecialchars(str_replace(',', ', ', $ecole['type_enseignement'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="bg-white bg-opacity-10 rounded p-2">
                                            <small class="text-white-50">Capacité</small>
                                            <div class="text-white fw-bold" id="preview-capacite">50 élèves</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-white bg-opacity-10 rounded p-2">
                                            <small class="text-white-50">Statut</small>
                                            <div class="text-white fw-bold" id="preview-statut">Active</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="bg-white bg-opacity-10 rounded p-2">
                                            <small class="text-white-50">Enseignant principal</small>
                                            <div class="text-white fw-bold" id="preview-enseignant">Non assigné</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="bg-white bg-opacity-10 rounded p-2">
                                            <small class="text-white-50">Salle</small>
                                            <div class="text-white fw-bold" id="preview-salle">Non spécifiée</div>
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

    <!-- Modal pour ajouter un nouveau cycle -->
    <div class="modal fade" id="addCycleModal" tabindex="-1" aria-labelledby="addCycleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCycleModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Ajouter un nouveau cycle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addCycleForm">
                        <div class="mb-3">
                            <label for="newCycleName" class="form-label">Nom du cycle *</label>
                            <input type="text" class="form-control" id="newCycleName" name="newCycleName" 
                                   placeholder="Ex: technique, professionnel, université" required>
                            <div class="form-text">Nom en minuscules, sans accents ni espaces</div>
                        </div>
                        <div class="mb-3">
                            <label for="newCycleLabel" class="form-label">Libellé d'affichage *</label>
                            <input type="text" class="form-control" id="newCycleLabel" name="newCycleLabel" 
                                   placeholder="Ex: Technique, Professionnel, Université" required>
                            <div class="form-text">Nom complet pour l'affichage</div>
                        </div>
                        <div class="mb-3">
                            <label for="newCycleDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="newCycleDescription" name="newCycleDescription" rows="3" 
                                      placeholder="Description du cycle (optionnel)"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Annuler
                    </button>
                    <button type="button" class="btn btn-primary" id="saveCycleBtn">
                        <i class="bi bi-check-circle me-2"></i>Ajouter le cycle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour ajouter une nouvelle section -->
    <div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSectionModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Ajouter une nouvelle section
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addSectionForm">
                        <div class="mb-3">
                            <label for="newSectionCategory" class="form-label">Catégorie *</label>
                            <select class="form-select" id="newSectionCategory" name="newSectionCategory" required>
                                <option value="">Sélectionner une catégorie</option>
                                <option value="generale">Sections générales</option>
                                <option value="technique">Sections techniques</option>
                                <option value="professionnelle">Sections professionnelles</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="newSectionName" class="form-label">Nom de la section *</label>
                            <input type="text" class="form-control" id="newSectionName" name="newSectionName" 
                                   placeholder="Ex: informatique, gestion, marketing" required>
                            <div class="form-text">Nom en minuscules, sans accents ni espaces</div>
                        </div>
                        <div class="mb-3">
                            <label for="newSectionLabel" class="form-label">Libellé d'affichage *</label>
                            <input type="text" class="form-control" id="newSectionLabel" name="newSectionLabel" 
                                   placeholder="Ex: Informatique, Gestion, Marketing" required>
                            <div class="form-text">Nom complet pour l'affichage</div>
                        </div>
                        <div class="mb-3">
                            <label for="newSectionDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="newSectionDescription" name="newSectionDescription" rows="3" 
                                      placeholder="Description de la section (optionnel)"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Annuler
                    </button>
                    <button type="button" class="btn btn-primary" id="saveSectionBtn">
                        <i class="bi bi-check-circle me-2"></i>Ajouter la section
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Éléments du formulaire
            const nomInput = document.getElementById('nom_classe');
            const capaciteInput = document.getElementById('capacite_max');
            const niveauInput = document.getElementById('niveau');
            const cycleSelect = document.getElementById('cycle');
            const niveauDetailleSelect = document.getElementById('niveau_detaille');
            const optionsSectionsSelect = document.getElementById('option_section');
            const anneeInput = document.getElementById('annee_scolaire');
            const enseignantSelect = document.getElementById('professeur_principal_id');
            const statutSelect = document.getElementById('statut');
            const salleInput = document.getElementById('salle_classe');
            
            // Conteneurs pour afficher/masquer les champs
            const niveauDetailleContainer = document.getElementById('niveau_detaille_container');
            const optionsSectionsContainer = document.getElementById('options_sections_container');
            
            // Éléments de l'aperçu
            const previewNom = document.getElementById('preview-nom');
            const previewNiveau = document.getElementById('preview-niveau');
            const previewCapacite = document.getElementById('preview-capacite');
            const previewStatut = document.getElementById('preview-statut');
            const previewEnseignant = document.getElementById('preview-enseignant');
            const previewSalle = document.getElementById('preview-salle');
            
            // Fonction pour gérer les niveaux détaillés selon le cycle
            function updateNiveauxDetaille() {
                const cycle = cycleSelect.value;
                niveauDetailleSelect.innerHTML = '<option value="">Sélectionner un niveau</option>';
                
                if (cycle === 'primaire') {
                    // Primaire : 1ʳᵉ à 6ᵉ année
                    for (let i = 1; i <= 6; i++) {
                        const option = document.createElement('option');
                        option.value = i + 'eme_primaire';
                        option.textContent = i + 'ᵉ année primaire';
                        niveauDetailleSelect.appendChild(option);
                    }
                    niveauDetailleContainer.style.display = 'block';
                    optionsSectionsContainer.style.display = 'none';
                } else if (cycle === 'secondaire') {
                    // Secondaire : Tronc commun + Humanités
                    const troncCommun = ['7eme_secondaire', '8eme_secondaire'];
                    const humanites = ['1ere_humanites', '2eme_humanites', '3eme_humanites', '4eme_humanites'];
                    
                    // Ajouter le tronc commun
                    const troncGroup = document.createElement('optgroup');
                    troncGroup.label = 'Tronc commun';
                    troncCommun.forEach(niveau => {
                        const option = document.createElement('option');
                        option.value = niveau;
                        option.textContent = niveau.replace('eme_secondaire', 'ᵉ secondaire');
                        troncGroup.appendChild(option);
                    });
                    niveauDetailleSelect.appendChild(troncGroup);
                    
                    // Ajouter les humanités
                    const humanitesGroup = document.createElement('optgroup');
                    humanitesGroup.label = 'Humanités';
                    humanites.forEach(niveau => {
                        const option = document.createElement('option');
                        option.value = niveau;
                        option.textContent = niveau.replace('eme_humanites', 'ᵉ humanités');
                        humanitesGroup.appendChild(option);
                    });
                    niveauDetailleSelect.appendChild(humanitesGroup);
                    
                    niveauDetailleContainer.style.display = 'block';
                    optionsSectionsContainer.style.display = 'none';
                } else if (cycle === 'maternelle') {
                    // Maternelle : 1ʳᵉ à 3ᵉ année
                    for (let i = 1; i <= 3; i++) {
                        const option = document.createElement('option');
                        option.value = i + 'ere_maternelle';
                        option.textContent = i + 'ʳᵉ année maternelle';
                        niveauDetailleSelect.appendChild(option);
                    }
                    niveauDetailleContainer.style.display = 'block';
                    optionsSectionsContainer.style.display = 'none';
                } else {
                    // Autres cycles
                    niveauDetailleContainer.style.display = 'none';
                    optionsSectionsContainer.style.display = 'none';
                }
            }
            
            // Fonction pour gérer l'affichage des options/sections
            function updateOptionsSections() {
                const niveauDetaille = niveauDetailleSelect.value;
                const cycle = cycleSelect.value;
                
                if (cycle === 'secondaire' && niveauDetaille && niveauDetaille.includes('humanites')) {
                    optionsSectionsContainer.style.display = 'block';
                } else {
                    optionsSectionsContainer.style.display = 'none';
                }
            }
            
            // Fonction de mise à jour de l'aperçu
            function updatePreview() {
                // Nom
                previewNom.textContent = nomInput.value || 'Nouvelle Classe';
                
                // Niveau et cycle
                let niveauText = '';
                if (niveauInput.value) {
                    niveauText = niveauInput.value;
                }
                if (cycleSelect.value) {
                    const cycleText = cycleSelect.options[cycleSelect.selectedIndex].text;
                    niveauText += niveauText ? ' - ' + cycleText : cycleText;
                }
                
                // Ajouter le niveau détaillé si disponible
                if (niveauDetailleSelect.value) {
                    const niveauDetailleText = niveauDetailleSelect.options[niveauDetailleSelect.selectedIndex].text;
                    niveauText += ' - ' + niveauDetailleText;
                }
                
                // Ajouter l'option/section si disponible
                if (optionsSectionsSelect.value) {
                    const optionText = optionsSectionsSelect.options[optionsSectionsSelect.selectedIndex].text;
                    niveauText += ' (' + optionText + ')';
                }
                
                previewNiveau.textContent = niveauText || 'Configuration en cours...';
                
                // Capacité
                previewCapacite.textContent = capaciteInput.value ? capaciteInput.value + ' élèves' : '50 élèves';
                
                // Statut
                if (statutSelect.value) {
                    const statutText = statutSelect.options[statutSelect.selectedIndex].text.split(' - ')[0];
                    previewStatut.textContent = statutText;
                }
                
                // Enseignant
                if (enseignantSelect.value) {
                    previewEnseignant.textContent = enseignantSelect.options[enseignantSelect.selectedIndex].text;
                } else {
                    previewEnseignant.textContent = 'Non assigné';
                }
                
                // Salle
                previewSalle.textContent = salleInput.value || 'Non spécifiée';
            }
            
            // Événements de mise à jour
            [nomInput, capaciteInput, niveauInput, cycleSelect, anneeInput, enseignantSelect, statutSelect, salleInput].forEach(element => {
                element.addEventListener('input', updatePreview);
                element.addEventListener('change', updatePreview);
            });
            
            // Événements spécifiques pour les cycles et niveaux
            cycleSelect.addEventListener('change', function() {
                updateNiveauxDetaille();
                updatePreview();
            });
            
            niveauDetailleSelect.addEventListener('change', function() {
                updateOptionsSections();
                updatePreview();
            });
            
            optionsSectionsSelect.addEventListener('change', updatePreview);
            
            // Validation du formulaire
            document.getElementById('createClassForm').addEventListener('submit', function(e) {
                if (!nomInput.value.trim()) {
                    e.preventDefault();
                    alert('Le nom de la classe est obligatoire.');
                    nomInput.focus();
                    return;
                }
                
                if (!niveauInput.value.trim()) {
                    e.preventDefault();
                    alert('Le niveau scolaire est obligatoire.');
                    niveauInput.focus();
                    return;
                }
                
                if (!cycleSelect.value) {
                    e.preventDefault();
                    alert('Le cycle est obligatoire.');
                    cycleSelect.focus();
                    return;
                }
                
                // Validation du niveau détaillé si le cycle le requiert
                if (['primaire', 'secondaire', 'maternelle'].includes(cycleSelect.value) && !niveauDetailleSelect.value) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un niveau détaillé.');
                    niveauDetailleSelect.focus();
                    return;
                }
                
                // Validation des options/sections pour les humanités
                if (cycleSelect.value === 'secondaire' && niveauDetailleSelect.value && 
                    niveauDetailleSelect.value.includes('humanites') && !optionsSectionsSelect.value) {
                    e.preventDefault();
                    alert('Veuillez sélectionner une option/section pour le cycle Humanités.');
                    optionsSectionsSelect.focus();
                    return;
                }
                
                if (!anneeInput.value.trim()) {
                    e.preventDefault();
                    alert('L\'année scolaire est obligatoire.');
                    anneeInput.focus();
                    return;
                }
                
                if (capaciteInput.value && (capaciteInput.value <= 0 || capaciteInput.value > 200)) {
                    e.preventDefault();
                    alert('La capacité doit être entre 1 et 200 élèves.');
                    capaciteInput.focus();
                    return;
                }
            });
            
            // Mise à jour initiale
            updatePreview();
            
            // Initialiser les niveaux détaillés si un cycle est déjà sélectionné
            if (cycleSelect.value) {
                updateNiveauxDetaille();
                updateOptionsSections();
            }

            // Gestion de l'ajout de nouveaux cycles
            document.getElementById('saveCycleBtn').addEventListener('click', function() {
                const cycleName = document.getElementById('newCycleName').value.trim();
                const cycleLabel = document.getElementById('newCycleLabel').value.trim();
                const cycleDescription = document.getElementById('newCycleDescription').value.trim();
                
                if (!cycleName || !cycleLabel) {
                    alert('Veuillez remplir tous les champs obligatoires.');
                    return;
                }
                
                // Créer la nouvelle option
                const newOption = document.createElement('option');
                newOption.value = cycleName;
                newOption.textContent = cycleLabel + (cycleDescription ? ' - ' + cycleDescription : '');
                
                // Ajouter au select des cycles
                cycleSelect.appendChild(newOption);
                
                // Sélectionner automatiquement le nouveau cycle
                cycleSelect.value = cycleName;
                
                // Mettre à jour les niveaux détaillés
                updateNiveauxDetaille();
                updatePreview();
                
                // Fermer la modal et réinitialiser le formulaire
                const modal = bootstrap.Modal.getInstance(document.getElementById('addCycleModal'));
                modal.hide();
                document.getElementById('addCycleForm').reset();
                
                // Afficher un message de succès
                showNotification('Cycle ajouté avec succès !', 'success');
            });

            // Gestion de l'ajout de nouvelles sections
            document.getElementById('saveSectionBtn').addEventListener('click', function() {
                const category = document.getElementById('newSectionCategory').value;
                const sectionName = document.getElementById('newSectionName').value.trim();
                const sectionLabel = document.getElementById('newSectionLabel').value.trim();
                const sectionDescription = document.getElementById('newSectionDescription').value.trim();
                
                if (!category || !sectionName || !sectionLabel) {
                    alert('Veuillez remplir tous les champs obligatoires.');
                    return;
                }
                
                // Trouver le bon optgroup
                let targetOptgroup = null;
                const optgroups = optionsSectionsSelect.querySelectorAll('optgroup');
                
                for (let optgroup of optgroups) {
                    if (optgroup.label.toLowerCase().includes(category)) {
                        targetOptgroup = optgroup;
                        break;
                    }
                }
                
                if (targetOptgroup) {
                    // Créer la nouvelle option
                    const newOption = document.createElement('option');
                    newOption.value = sectionName;
                    newOption.textContent = sectionLabel + (sectionDescription ? ' - ' + sectionDescription : '');
                    
                    // Ajouter au bon optgroup
                    targetOptgroup.appendChild(newOption);
                    
                    // Sélectionner automatiquement la nouvelle section
                    optionsSectionsSelect.value = sectionName;
                    
                    // Fermer la modal et réinitialiser le formulaire
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addSectionModal'));
                    modal.hide();
                    document.getElementById('addSectionForm').reset();
                    
                    // Mettre à jour l'aperçu
                    updatePreview();
                    
                    // Afficher un message de succès
                    showNotification('Section ajoutée avec succès !', 'success');
                }
            });

            // Fonction pour afficher les notifications
            function showNotification(message, type = 'info') {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(alertDiv);
                
                // Auto-suppression après 5 secondes
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>
