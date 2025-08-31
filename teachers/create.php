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
        'notes_internes' => sanitize($_POST['notes_internes'] ?? '')
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
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $insert_query = "INSERT INTO enseignants (
                ecole_id, matricule_enseignant, nom, prenom, sexe, date_naissance, lieu_naissance,
                nationalite, telephone, email, adresse_complete, specialites, diplomes,
                experience_annees, date_embauche, statut_record, notes_internes, created_by
            ) VALUES (
                :ecole_id, :matricule_enseignant, :nom, :prenom, :sexe, :date_naissance, :lieu_naissance,
                :nationalite, :telephone, :email, :adresse_complete, :specialites, :diplomes,
                :experience_annees, :date_embauche, :statut_record, :notes_internes, :created_by
            )";
            
            $stmt = $db->prepare($insert_query);
            $stmt->execute([
                'ecole_id' => $_SESSION['ecole_id'],
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
                'created_by' => $_SESSION['user_id']
            ]);
            
            $db->commit();
            
            setFlashMessage('success', "L'enseignant '{$data['prenom']} {$data['nom']}' a été créé avec succès.");
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
            
            <form method="POST" class="needs-validation" novalidate>
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
    </script>
</body>
</html>

