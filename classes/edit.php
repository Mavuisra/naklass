<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier l'ID de la classe
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de classe invalide.');
    redirect('index.php');
}

$class_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Initialiser la variable errors
$errors = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = sanitize($_POST['nom']);
    $niveau = sanitize($_POST['niveau'] ?? '');
    $cycle = sanitize($_POST['cycle'] ?? '');
    $niveau_detaille = sanitize($_POST['niveau_detaille'] ?? '');
    $option_section = sanitize($_POST['option_section'] ?? '');
    $description = sanitize($_POST['description']);
    $capacite_max = !empty($_POST['capacite_max']) ? (int)$_POST['capacite_max'] : null;
    $enseignant_principal_id = !empty($_POST['enseignant_principal_id']) ? (int)$_POST['enseignant_principal_id'] : null;
    $salle = sanitize($_POST['salle']);
    $horaire_debut = !empty($_POST['horaire_debut']) ? $_POST['horaire_debut'] : null;
    $horaire_fin = !empty($_POST['horaire_fin']) ? $_POST['horaire_fin'] : null;
    $statut = $_POST['statut'];
    
    // Validation
    $errors = [];
    
    if (empty($nom)) {
        $errors[] = "Le nom de la classe est requis.";
    }
    
    if (empty($niveau)) {
        $errors[] = "Le niveau scolaire est obligatoire.";
    }
    
    if (empty($cycle)) {
        $errors[] = "Le cycle est obligatoire.";
    }
    
    // Validation du niveau détaillé si le cycle le requiert
    if (in_array($cycle, ['primaire', 'secondaire', 'maternelle']) && !$niveau_detaille) {
        $errors[] = "Veuillez sélectionner un niveau détaillé.";
    }
    
    // Validation des options/sections pour les humanités
    if ($cycle === 'secondaire' && $niveau_detaille && 
        strpos($niveau_detaille, 'humanites') !== false && !$option_section) {
        $errors[] = "Veuillez sélectionner une option/section pour le cycle Humanités.";
    }
    
    if ($capacite_max !== null && $capacite_max <= 0) {
        $errors[] = "La capacité maximale doit être supérieure à 0.";
    }
    
    if ($horaire_debut && $horaire_fin && $horaire_debut >= $horaire_fin) {
        $errors[] = "L'heure de début doit être antérieure à l'heure de fin.";
    }
    
    // Vérifier l'unicité du nom dans l'école
    if (empty($errors)) {
        try {
            $check_query = "SELECT id FROM classes WHERE nom_classe = :nom_classe AND ecole_id = :ecole_id AND id != :class_id";
            $stmt = $db->prepare($check_query);
            $stmt->execute([
                'nom_classe' => $nom,
                'ecole_id' => $_SESSION['ecole_id'],
                'class_id' => $class_id
            ]);
            
            if ($stmt->fetch()) {
                $errors[] = "Une classe avec ce nom existe déjà dans votre école.";
            }
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la vérification de l'unicité du nom.";
        }
    }
    
    // Mise à jour si pas d'erreurs
    if (empty($errors)) {
        try {
            // Créer une description complète du cycle pour l'affichage
            $cycle_complet = '';
            if (!empty($cycle)) {
                $cycle_labels = [
                    'maternelle' => 'Maternelle (3 ans)',
                    'primaire' => 'Primaire (6 ans)',
                    'secondaire' => 'Secondaire (6 ans)',
                    'supérieur' => 'Supérieur'
                ];
                $cycle_complet = $cycle_labels[$cycle] ?? $cycle;
                
                // Ajouter le niveau détaillé
                if (!empty($niveau_detaille)) {
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
                    $cycle_complet .= ' - ' . ($niveau_labels[$niveau_detaille] ?? $niveau_detaille);
                }
                
                // Ajouter l'option/section si disponible
                if (!empty($option_section)) {
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
                    $cycle_complet .= ' (' . ($option_labels[$option_section] ?? $option_section) . ')';
                }
            }
            
            $update_query = "UPDATE classes SET 
                                nom_classe = :nom_classe,
                                niveau = :niveau,
                                cycle = :cycle,
                                niveau_detaille = :niveau_detaille,
                                option_section = :option_section,
                                cycle_complet = :cycle_complet,
                                capacite_max = :capacite_max,
                                professeur_principal_id = :professeur_principal_id,
                                salle_classe = :salle_classe,
                                statut = :statut,
                                updated_at = CURRENT_TIMESTAMP
                            WHERE id = :class_id AND ecole_id = :ecole_id";
            
            $stmt = $db->prepare($update_query);
            $result = $stmt->execute([
                'nom_classe' => $nom,
                'niveau' => $niveau,
                'cycle' => $cycle,
                'niveau_detaille' => $niveau_detaille,
                'option_section' => $option_section,
                'cycle_complet' => $cycle_complet,
                'capacite_max' => $capacite_max,
                'professeur_principal_id' => $enseignant_principal_id,
                'salle_classe' => $salle,
                'statut' => $statut,
                'class_id' => $class_id,
                'ecole_id' => $_SESSION['ecole_id']
            ]);
            
            if ($result) {
                setFlashMessage('success', 'Classe mise à jour avec succès.');
                redirect("view.php?id={$class_id}");
            } else {
                $errors[] = "Erreur lors de la mise à jour de la classe.";
            }
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la mise à jour de la classe : " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
        }
    }
}

// Récupérer les détails de la classe
try {
    $class_query = "SELECT * FROM classes WHERE id = :class_id AND ecole_id = :ecole_id";
    $stmt = $db->prepare($class_query);
    $stmt->execute([
        'class_id' => $class_id,
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    $classe = $stmt->fetch();
    
    if (!$classe) {
        setFlashMessage('error', 'Classe non trouvée.');
        redirect('index.php');
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération des détails de la classe.');
    redirect('index.php');
}

// Récupérer les informations de l'école
try {
    $ecole_query = "SELECT * FROM ecoles WHERE id = :ecole_id";
    $stmt = $db->prepare($ecole_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $ecole = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ecole = [];
    $errors[] = "Erreur lors de la récupération des informations de l'école: " . $e->getMessage();
}

// Récupérer les enseignants disponibles
try {
    $enseignants_query = "SELECT * FROM enseignants WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom ASC, prenom ASC";
    $stmt = $db->prepare($enseignants_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $enseignants = $stmt->fetchAll();
} catch (Exception $e) {
    $enseignants = [];
}

$page_title = "Modifier la Classe : " . $classe['nom_classe'];
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
                <h1><i class="bi bi-pencil me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Modifier les informations de la classe</p>
            </div>
            
            <div class="topbar-actions">
                <a href="view.php?id=<?php echo $classe['id']; ?>" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-eye me-2"></i>Voir
                </a>
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
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-edit me-2"></i>Modifier la Classe</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="nom" class="form-label">Nom de la classe *</label>
                                            <input type="text" class="form-control" id="nom" name="nom" 
                                                   value="<?php echo htmlspecialchars($classe['nom_classe']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="statut" class="form-label">Statut</label>
                                            <select class="form-select" id="statut" name="statut">
                                                <option value="actif" <?php echo $classe['statut'] == 'actif' ? 'selected' : ''; ?>>Actif</option>
                                                <option value="archivé" <?php echo $classe['statut'] == 'archivé' ? 'selected' : ''; ?>>Archivé</option>
                                                <option value="supprimé_logique" <?php echo $classe['statut'] == 'supprimé_logique' ? 'selected' : ''; ?>>Supprimé logiquement</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="niveau" class="form-label">Niveau scolaire *</label>
                                            <input type="text" class="form-control" id="niveau" name="niveau" 
                                                   value="<?php echo htmlspecialchars($classe['niveau'] ?? ''); ?>" 
                                                   placeholder="Ex: 6ème, CE1, 1ère S..." required>
                                            <div class="form-text">
                                                <strong>💡 Conseil :</strong> Utilisez le champ "Niveau détaillé" ci-dessous pour une sélection précise selon le cycle choisi.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="cycle" class="form-label">Cycle *</label>
                                            <div class="input-group">
                                                <select class="form-select" id="cycle" name="cycle" required>
                                                    <option value="">Sélectionner un cycle</option>
                                                    <?php 
                                                    $selected_cycle = $classe['cycle'] ?? '';
                                                    $type_enseignement = $ecole['type_enseignement'] ?? '';
                                                    
                                                    // Convertir la chaîne SET en tableau
                                                    $types_array = !empty($type_enseignement) ? explode(',', $type_enseignement) : [];
                                                    
                                                    // Définir les correspondances entre les valeurs de la BD et les labels d'affichage
                                                    $cycle_labels = [
                                                        'maternelle' => 'Maternelle (3 ans) - 1ʳᵉ à 3ᵉ année',
                                                        'primaire' => 'Primaire (6 ans) - 1ᵉ à 6ᵉ année',
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
                                    </div>
                                </div>
                                
                                <!-- Niveau détaillé basé sur le cycle -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3" id="niveau_detaille_container">
                                            <label for="niveau_detaille" class="form-label">Niveau détaillé</label>
                                            <select class="form-select" id="niveau_detaille" name="niveau_detaille">
                                                <option value="">Sélectionner un niveau</option>
                                            </select>
                                            <div class="form-text">Niveau spécifique dans le cycle sélectionné</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Options et sections pour le cycle Humanités -->
                                    <div class="col-md-6">
                                        <div class="mb-3" id="options_sections_container">
                                            <label for="option_section" class="form-label">Option/Section</label>
                                            <div class="input-group">
                                                <select class="form-select" id="option_section" name="option_section">
                                                    <option value="">Sélectionner une option</option>
                                                    <optgroup label="Sections générales">
                                                        <option value="scientifique" <?php echo ($classe['option_section'] ?? '') == 'scientifique' ? 'selected' : ''; ?>>Scientifique</option>
                                                        <option value="litteraire" <?php echo ($classe['option_section'] ?? '') == 'litteraire' ? 'selected' : ''; ?>>Littéraire</option>
                                                        <option value="commerciale" <?php echo ($classe['option_section'] ?? '') == 'commerciale' ? 'selected' : ''; ?>>Commerciale</option>
                                                        <option value="pedagogique" <?php echo ($classe['option_section'] ?? '') == 'pedagogique' ? 'selected' : ''; ?>>Pédagogique</option>
                                                    </optgroup>
                                                    <optgroup label="Sections techniques">
                                                        <option value="technique_construction" <?php echo ($classe['option_section'] ?? '') == 'technique_construction' ? 'selected' : ''; ?>>Technique de Construction</option>
                                                        <option value="technique_electricite" <?php echo ($classe['option_section'] ?? '') == 'technique_electricite' ? 'selected' : ''; ?>>Technique Électricité</option>
                                                        <option value="technique_mecanique" <?php echo ($classe['option_section'] ?? '') == 'technique_mecanique' ? 'selected' : ''; ?>>Technique Mécanique</option>
                                                        <option value="technique_informatique" <?php echo ($classe['option_section'] ?? '') == 'technique_informatique' ? 'selected' : ''; ?>>Technique Informatique</option>
                                                    </optgroup>
                                                    <optgroup label="Sections professionnelles">
                                                        <option value="secretariat" <?php echo ($classe['option_section'] ?? '') == 'secretariat' ? 'selected' : ''; ?>>Secrétariat</option>
                                                        <option value="comptabilite" <?php echo ($classe['option_section'] ?? '') == 'comptabilite' ? 'selected' : ''; ?>>Comptabilité</option>
                                                        <option value="hotellerie" <?php echo ($classe['option_section'] ?? '') == 'hotellerie' ? 'selected' : ''; ?>>Hôtellerie</option>
                                                        <option value="couture" <?php echo ($classe['option_section'] ?? '') == 'couture' ? 'selected' : ''; ?>>Couture</option>
                                                    </optgroup>
                                                </select>
                                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                                                    <i class="bi bi-plus-circle"></i>
                                                </button>
                                            </div>
                                            <div class="form-text">Spécialisation pour le cycle Humanités</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="enseignant_principal_id" class="form-label">Enseignant principal</label>
                                            <select class="form-select" id="enseignant_principal_id" name="enseignant_principal_id">
                                                <option value="">Aucun enseignant assigné</option>
                                                <?php foreach ($enseignants as $enseignant): ?>
                                                    <option value="<?php echo $enseignant['id']; ?>" 
                                                            <?php echo $classe['enseignant_principal_id'] == $enseignant['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="capacite_max" class="form-label">Capacité maximale</label>
                                            <input type="number" class="form-control" id="capacite_max" name="capacite_max" 
                                                   value="<?php echo $classe['capacite_max']; ?>" min="1" 
                                                   placeholder="Illimitée si vide">
                                            <div class="form-text">Laissez vide pour une capacité illimitée</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="salle" class="form-label">Salle</label>
                                            <input type="text" class="form-control" id="salle" name="salle" 
                                                   value="<?php echo htmlspecialchars($classe['salle'] ?? ''); ?>" 
                                                   placeholder="Numéro ou nom de la salle">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="horaire_debut" class="form-label">Horaires</label>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <input type="time" class="form-control" id="horaire_debut" name="horaire_debut" 
                                                           value="<?php echo $classe['horaire_debut']; ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="time" class="form-control" id="horaire_fin" name="horaire_fin" 
                                                           value="<?php echo $classe['horaire_fin']; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" 
                                              placeholder="Description optionnelle de la classe"><?php echo htmlspecialchars($classe['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="view.php?id=<?php echo $classe['id']; ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-2"></i>Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-2"></i>Enregistrer les Modifications
                                    </button>
                                </div>
                            </form>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Éléments du formulaire
            const niveauInput = document.getElementById('niveau');
            const cycleSelect = document.getElementById('cycle');
            const niveauDetailleSelect = document.getElementById('niveau_detaille');
            const optionsSectionsSelect = document.getElementById('option_section');
            
            // Conteneurs pour afficher/masquer les champs
            const niveauDetailleContainer = document.getElementById('niveau_detaille_container');
            const optionsSectionsContainer = document.getElementById('options_sections_container');
            
            // Fonction pour gérer les niveaux détaillés selon le cycle
            function updateNiveauxDetaille() {
                const cycle = cycleSelect.value;
                niveauDetailleSelect.innerHTML = '<option value="">Sélectionner un niveau</option>';
                
                if (cycle === 'primaire') {
                    // Primaire : 1ᵉ à 6ᵉ année
                    for (let i = 1; i <= 6; i++) {
                        const option = document.createElement('option');
                        option.value = i + 'eme_primaire';
                        option.textContent = i + 'ᵉ année primaire';
                        if (option.value === '<?php echo $classe['niveau_detaille'] ?? ''; ?>') {
                            option.selected = true;
                        }
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
                        if (option.value === '<?php echo $classe['niveau_detaille'] ?? ''; ?>') {
                            option.selected = true;
                        }
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
                        if (option.value === '<?php echo $classe['niveau_detaille'] ?? ''; ?>') {
                            option.selected = true;
                        }
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
                        if (option.value === '<?php echo $classe['niveau_detaille'] ?? ''; ?>') {
                            option.selected = true;
                        }
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
            
            // Événements spécifiques pour les cycles et niveaux
            cycleSelect.addEventListener('change', function() {
                updateNiveauxDetaille();
                updateOptionsSections();
            });
            
            niveauDetailleSelect.addEventListener('change', function() {
                updateOptionsSections();
            });
            
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
                updateOptionsSections();
                
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
