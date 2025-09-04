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
        'annee_scolaire' => sanitize($_POST['annee_scolaire'] ?? ''),
        'enseignant_id' => intval($_POST['enseignant_id'] ?? 0),
        'classe_id' => intval($_POST['classe_id'] ?? 0),
        'cours_id' => intval($_POST['cours_id'] ?? 0),
        'jour_semaine' => sanitize($_POST['jour_semaine'] ?? ''),
        'heure_debut' => sanitize($_POST['heure_debut'] ?? ''),
        'heure_fin' => sanitize($_POST['heure_fin'] ?? ''),
        'salle' => sanitize($_POST['salle'] ?? ''),
        'notes' => sanitize($_POST['notes'] ?? '')
    ];
    
    // Validation
    if (empty($data['annee_scolaire'])) {
        $errors[] = "L'année scolaire est obligatoire.";
    }
    
    if (empty($data['enseignant_id'])) {
        $errors[] = "L'enseignant est obligatoire.";
    }
    
    if (empty($data['classe_id'])) {
        $errors[] = "La classe est obligatoire.";
    }
    
    if (empty($data['cours_id'])) {
        $errors[] = "Le cours/matière est obligatoire.";
    }
    
    if (empty($data['jour_semaine'])) {
        $errors[] = "Le jour de la semaine est obligatoire.";
    }
    
    if (empty($data['heure_debut'])) {
        $errors[] = "L'heure de début est obligatoire.";
    }
    
    if (empty($data['heure_fin'])) {
        $errors[] = "L'heure de fin est obligatoire.";
    }
    
    // Validation des heures
    if (!empty($data['heure_debut']) && !empty($data['heure_fin'])) {
        $debut = strtotime($data['heure_debut']);
        $fin = strtotime($data['heure_fin']);
        
        if ($fin <= $debut) {
            $errors[] = "L'heure de fin doit être postérieure à l'heure de début.";
        }
    }
    
    // Vérifier les conflits d'horaires pour l'enseignant
    if (empty($errors)) {
        $conflict_query = "SELECT id FROM emploi_du_temps 
                          WHERE enseignant_id = :enseignant_id 
                          AND jour_semaine = :jour_semaine 
                          AND statut = 'actif'
                          AND ecole_id = :ecole_id
                          AND (
                              (heure_debut <= :heure_debut1 AND heure_fin > :heure_debut2) OR
                              (heure_debut < :heure_fin1 AND heure_fin >= :heure_fin2) OR
                              (heure_debut >= :heure_debut3 AND heure_fin <= :heure_fin3)
                          )";
        
        $stmt = $db->prepare($conflict_query);
        $stmt->execute([
            'enseignant_id' => $data['enseignant_id'],
            'jour_semaine' => $data['jour_semaine'],
            'ecole_id' => $_SESSION['ecole_id'],
            'heure_debut1' => $data['heure_debut'],
            'heure_debut2' => $data['heure_debut'],
            'heure_debut3' => $data['heure_debut'],
            'heure_fin1' => $data['heure_fin'],
            'heure_fin2' => $data['heure_fin'],
            'heure_fin3' => $data['heure_fin']
        ]);
        
        if ($stmt->fetch()) {
            $errors[] = "L'enseignant a déjà un cours programmé à cette heure ce jour-là.";
        }
    }
    
    // Vérifier les conflits d'horaires pour la classe
    if (empty($errors)) {
        $conflict_query = "SELECT id FROM emploi_du_temps 
                          WHERE classe_id = :classe_id 
                          AND jour_semaine = :jour_semaine 
                          AND statut = 'actif'
                          AND ecole_id = :ecole_id
                          AND (
                              (heure_debut <= :heure_debut1 AND heure_fin > :heure_debut2) OR
                              (heure_debut < :heure_fin1 AND heure_fin >= :heure_fin2) OR
                              (heure_debut >= :heure_debut3 AND heure_fin <= :heure_fin3)
                          )";
        
        $stmt = $db->prepare($conflict_query);
        $stmt->execute([
            'classe_id' => $data['classe_id'],
            'jour_semaine' => $data['jour_semaine'],
            'ecole_id' => $_SESSION['ecole_id'],
            'heure_debut1' => $data['heure_debut'],
            'heure_debut2' => $data['heure_debut'],
            'heure_debut3' => $data['heure_debut'],
            'heure_fin1' => $data['heure_fin'],
            'heure_fin2' => $data['heure_fin'],
            'heure_fin3' => $data['heure_fin']
        ]);
        
        if ($stmt->fetch()) {
            $errors[] = "La classe a déjà un cours programmé à cette heure ce jour-là.";
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $insert_query = "INSERT INTO emploi_du_temps (
                ecole_id, annee_scolaire, enseignant_id, classe_id, cours_id, jour_semaine,
                heure_debut, heure_fin, salle, notes, created_by
            ) VALUES (
                :ecole_id, :annee_scolaire, :enseignant_id, :classe_id, :cours_id, :jour_semaine,
                :heure_debut, :heure_fin, :salle, :notes, :created_by
            )";
            
            $stmt = $db->prepare($insert_query);
            $stmt->execute([
                'ecole_id' => $_SESSION['ecole_id'],
                'annee_scolaire' => $data['annee_scolaire'],
                'enseignant_id' => $data['enseignant_id'],
                'classe_id' => $data['classe_id'],
                'cours_id' => $data['cours_id'],
                'jour_semaine' => $data['jour_semaine'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'salle' => $data['salle'] ?: null,
                'notes' => $data['notes'] ?: null,
                'created_by' => $_SESSION['user_id']
            ]);
            
            $db->commit();
            
            setFlashMessage('success', "L'emploi du temps a été configuré avec succès.");
            redirect('index.php');
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Erreur lors de la configuration: " . $e->getMessage();
        }
    }
}

// Récupérer les données pour les listes déroulantes
try {
    // Années scolaires
    $annees_query = "SELECT DISTINCT annee_scolaire FROM emploi_du_temps WHERE ecole_id = :ecole_id ORDER BY annee_scolaire DESC";
    $stmt = $db->prepare($annees_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annees_existantes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Ajouter l'année scolaire actuelle si elle n'existe pas
    $annee_actuelle = date('Y') . '-' . (date('Y') + 1);
    if (!in_array($annee_actuelle, $annees_existantes)) {
        $annees_existantes[] = $annee_actuelle;
    }
    
    // Enseignants
    $enseignants_query = "SELECT id, nom, prenom FROM enseignants WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom, prenom";
    $stmt = $db->prepare($enseignants_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Classes
    $classes_query = "SELECT id, nom_classe as nom FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom_classe";
    $stmt = $db->prepare($classes_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cours
    $cours_query = "SELECT id, nom_cours as nom FROM cours WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom_cours";
    $stmt = $db->prepare($cours_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $cours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $errors[] = "Erreur lors du chargement des données: " . $e->getMessage();
}

$page_title = "Configuration Emploi du Temps";
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
        
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        .time-input {
            max-width: 150px;
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
                <h1><i class="bi bi-calendar-week me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Configurez l'emploi du temps des enseignants</p>
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
                <!-- Informations générales -->
                <div class="form-section p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-info-circle me-2"></i>Informations générales
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="annee_scolaire" class="form-label required-field">Année scolaire</label>
                            <select class="form-select" id="annee_scolaire" name="annee_scolaire" required>
                                <option value="">Sélectionnez une année...</option>
                                <?php foreach ($annees_existantes as $annee): ?>
                                    <option value="<?php echo htmlspecialchars($annee); ?>" 
                                            <?php echo ($_POST['annee_scolaire'] ?? '') === $annee ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($annee); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="enseignant_id" class="form-label required-field">Enseignant</label>
                            <select class="form-select" id="enseignant_id" name="enseignant_id" required>
                                <option value="">Sélectionnez un enseignant...</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?php echo $enseignant['id']; ?>" 
                                            <?php echo ($_POST['enseignant_id'] ?? '') == $enseignant['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Cours et classe -->
                <div class="form-section p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-book me-2"></i>Cours et classe
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="classe_id" class="form-label required-field">Classe</label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionnez une classe...</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                            <?php echo ($_POST['classe_id'] ?? '') == $classe['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="cours_id" class="form-label required-field">Cours/Matière</label>
                            <select class="form-select" id="cours_id" name="cours_id" required>
                                <option value="">Sélectionnez un cours...</option>
                                <?php foreach ($cours as $cours_item): ?>
                                    <option value="<?php echo $cours_item['id']; ?>" 
                                            <?php echo ($_POST['cours_id'] ?? '') == $cours_item['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cours_item['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Horaire -->
                <div class="form-section p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-clock me-2"></i>Horaire
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="jour_semaine" class="form-label required-field">Jour de la semaine</label>
                            <select class="form-select" id="jour_semaine" name="jour_semaine" required>
                                <option value="">Sélectionnez un jour...</option>
                                <option value="Lundi" <?php echo ($_POST['jour_semaine'] ?? '') === 'Lundi' ? 'selected' : ''; ?>>Lundi</option>
                                <option value="Mardi" <?php echo ($_POST['jour_semaine'] ?? '') === 'Mardi' ? 'selected' : ''; ?>>Mardi</option>
                                <option value="Mercredi" <?php echo ($_POST['jour_semaine'] ?? '') === 'Mercredi' ? 'selected' : ''; ?>>Mercredi</option>
                                <option value="Jeudi" <?php echo ($_POST['jour_semaine'] ?? '') === 'Jeudi' ? 'selected' : ''; ?>>Jeudi</option>
                                <option value="Vendredi" <?php echo ($_POST['jour_semaine'] ?? '') === 'Vendredi' ? 'selected' : ''; ?>>Vendredi</option>
                                <option value="Samedi" <?php echo ($_POST['jour_semaine'] ?? '') === 'Samedi' ? 'selected' : ''; ?>>Samedi</option>
                                <option value="Dimanche" <?php echo ($_POST['jour_semaine'] ?? '') === 'Dimanche' ? 'selected' : ''; ?>>Dimanche</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="heure_debut" class="form-label required-field">Heure de début</label>
                            <input type="time" class="form-control time-input" id="heure_debut" name="heure_debut" 
                                   value="<?php echo htmlspecialchars($_POST['heure_debut'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="heure_fin" class="form-label required-field">Heure de fin</label>
                            <input type="time" class="form-control time-input" id="heure_fin" name="heure_fin" 
                                   value="<?php echo htmlspecialchars($_POST['heure_fin'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                
                <!-- Informations supplémentaires -->
                <div class="form-section p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-geo-alt me-2"></i>Informations supplémentaires
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="salle" class="form-label">Salle</label>
                            <input type="text" class="form-control" id="salle" name="salle" 
                                   value="<?php echo htmlspecialchars($_POST['salle'] ?? ''); ?>"
                                   placeholder="Ex: Salle 101, Laboratoire A, etc.">
                            <div class="form-text">Salle de classe (optionnel)</div>
                        </div>
                        
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Notes supplémentaires sur ce cours..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                            <div class="form-text">Informations complémentaires (optionnel)</div>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons d'action -->
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Annuler
                    </a>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i>Configurer l'emploi du temps
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
        
        // Validation des heures
        document.getElementById('heure_fin').addEventListener('change', function() {
            const heureDebut = document.getElementById('heure_debut').value;
            const heureFin = this.value;
            
            if (heureDebut && heureFin) {
                const debut = new Date('2000-01-01 ' + heureDebut);
                const fin = new Date('2000-01-01 ' + heureFin);
                
                if (fin <= debut) {
                    this.setCustomValidity('L\'heure de fin doit être postérieure à l\'heure de début');
                } else {
                    this.setCustomValidity('');
                }
            }
        });
        
        document.getElementById('heure_debut').addEventListener('change', function() {
            const heureFin = document.getElementById('heure_fin').value;
            if (heureFin) {
                document.getElementById('heure_fin').dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>
