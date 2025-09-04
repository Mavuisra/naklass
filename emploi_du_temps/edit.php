<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Récupérer l'ID de l'emploi du temps depuis l'URL
$emploi_id = $_GET['id'] ?? 0;

if (!$emploi_id) {
    header('Location: index.php');
    exit;
}

// Récupérer les données de l'emploi du temps
try {
    $query = "SELECT * FROM emploi_du_temps WHERE id = :id AND ecole_id = :ecole_id";
    $stmt = $db->prepare($query);
    $stmt->execute([
        'id' => $emploi_id,
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    $emploi = $stmt->fetch();
    
    if (!$emploi) {
        $message = "❌ Emploi du temps non trouvé !";
        $message_type = 'danger';
    }
} catch (Exception $e) {
    $message = "❌ Erreur lors de la récupération de l'emploi du temps : " . $e->getMessage();
    $message_type = 'danger';
    $emploi = null;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $emploi) {
    $data = [
        'annee_scolaire' => sanitize($_POST['annee_scolaire'] ?? ''),
        'enseignant_id' => intval($_POST['enseignant_id'] ?? 0),
        'classe_id' => intval($_POST['classe_id'] ?? 0),
        'cours_id' => intval($_POST['cours_id'] ?? 0),
        'jour_semaine' => sanitize($_POST['jour_semaine'] ?? ''),
        'heure_debut' => sanitize($_POST['heure_debut'] ?? ''),
        'heure_fin' => sanitize($_POST['heure_fin'] ?? ''),
        'salle' => sanitize($_POST['salle'] ?? ''),
        'notes' => sanitize($_POST['notes'] ?? ''),
        'statut' => sanitize($_POST['statut'] ?? 'actif')
    ];
    
    $errors = [];
    
    // Validation
    if (empty($data['annee_scolaire'])) $errors[] = "L'année scolaire est requise.";
    if (empty($data['enseignant_id'])) $errors[] = "L'enseignant est requis.";
    if (empty($data['classe_id'])) $errors[] = "La classe est requise.";
    if (empty($data['cours_id'])) $errors[] = "Le cours est requis.";
    if (empty($data['jour_semaine'])) $errors[] = "Le jour de la semaine est requis.";
    if (empty($data['heure_debut'])) $errors[] = "L'heure de début est requise.";
    if (empty($data['heure_fin'])) $errors[] = "L'heure de fin est requise.";
    
    if ($data['heure_debut'] >= $data['heure_fin']) {
        $errors[] = "L'heure de début doit être antérieure à l'heure de fin.";
    }
    
    // Vérifier les conflits d'horaires pour l'enseignant (exclure l'emploi actuel)
    if (empty($errors)) {
        $conflict_query = "SELECT id FROM emploi_du_temps 
                          WHERE enseignant_id = :enseignant_id 
                          AND jour_semaine = :jour_semaine 
                          AND statut = 'actif'
                          AND ecole_id = :ecole_id
                          AND id != :emploi_id
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
            'emploi_id' => $emploi_id,
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
    
    // Vérifier les conflits d'horaires pour la classe (exclure l'emploi actuel)
    if (empty($errors)) {
        $conflict_query = "SELECT id FROM emploi_du_temps 
                          WHERE classe_id = :classe_id 
                          AND jour_semaine = :jour_semaine 
                          AND statut = 'actif'
                          AND ecole_id = :ecole_id
                          AND id != :emploi_id
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
            'emploi_id' => $emploi_id,
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
    
    // Mise à jour si pas d'erreurs
    if (empty($errors)) {
        try {
            $update_query = "UPDATE emploi_du_temps SET 
                            annee_scolaire = :annee_scolaire,
                            enseignant_id = :enseignant_id,
                            classe_id = :classe_id,
                            cours_id = :cours_id,
                            jour_semaine = :jour_semaine,
                            heure_debut = :heure_debut,
                            heure_fin = :heure_fin,
                            salle = :salle,
                            notes = :notes,
                            statut = :statut,
                            updated_at = CURRENT_TIMESTAMP
                            WHERE id = :id AND ecole_id = :ecole_id";
            
            $stmt = $db->prepare($update_query);
            $stmt->execute([
                'annee_scolaire' => $data['annee_scolaire'],
                'enseignant_id' => $data['enseignant_id'],
                'classe_id' => $data['classe_id'],
                'cours_id' => $data['cours_id'],
                'jour_semaine' => $data['jour_semaine'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'salle' => $data['salle'] ?: null,
                'notes' => $data['notes'] ?: null,
                'statut' => $data['statut'],
                'id' => $emploi_id,
                'ecole_id' => $_SESSION['ecole_id']
            ]);
            
            $message = "✅ Emploi du temps modifié avec succès !";
            $message_type = 'success';
            
            // Recharger les données
            $query = "SELECT * FROM emploi_du_temps WHERE id = :id AND ecole_id = :ecole_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                'id' => $emploi_id,
                'ecole_id' => $_SESSION['ecole_id']
            ]);
            $emploi = $stmt->fetch();
            
        } catch (Exception $e) {
            $message = "❌ Erreur lors de la modification : " . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = "❌ " . implode('<br>', $errors);
        $message_type = 'danger';
    }
}

// Récupérer les données pour les listes déroulantes
$enseignants = [];
$classes = [];
$cours = [];

if ($emploi) {
    try {
        // Enseignants
        $query = "SELECT id, nom, prenom FROM enseignants WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom, prenom";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Classes
        $query = "SELECT id, nom_classe as nom FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom_classe";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cours
        $query = "SELECT id, nom_cours as nom FROM cours WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom_cours";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $cours = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $message = "❌ Erreur lors du chargement des données : " . $e->getMessage();
        $message_type = 'danger';
    }
}

$page_title = "Modifier l'Emploi du Temps";
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
        .required-field::after {
            content: " *";
            color: red;
        }
        .time-input {
            font-family: monospace;
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
                <h1><i class="bi bi-pencil me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Modifiez les informations de l'emploi du temps</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour à la liste
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($emploi): ?>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-calendar-week me-2"></i>
                                    Informations de l'emploi du temps
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="annee_scolaire" class="form-label required-field">Année scolaire</label>
                                            <input type="text" class="form-control" id="annee_scolaire" name="annee_scolaire" 
                                                   value="<?php echo htmlspecialchars($emploi['annee_scolaire']); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="jour_semaine" class="form-label required-field">Jour de la semaine</label>
                                            <select class="form-select" id="jour_semaine" name="jour_semaine" required>
                                                <option value="">Sélectionner un jour</option>
                                                <option value="Lundi" <?php echo $emploi['jour_semaine'] === 'Lundi' ? 'selected' : ''; ?>>Lundi</option>
                                                <option value="Mardi" <?php echo $emploi['jour_semaine'] === 'Mardi' ? 'selected' : ''; ?>>Mardi</option>
                                                <option value="Mercredi" <?php echo $emploi['jour_semaine'] === 'Mercredi' ? 'selected' : ''; ?>>Mercredi</option>
                                                <option value="Jeudi" <?php echo $emploi['jour_semaine'] === 'Jeudi' ? 'selected' : ''; ?>>Jeudi</option>
                                                <option value="Vendredi" <?php echo $emploi['jour_semaine'] === 'Vendredi' ? 'selected' : ''; ?>>Vendredi</option>
                                                <option value="Samedi" <?php echo $emploi['jour_semaine'] === 'Samedi' ? 'selected' : ''; ?>>Samedi</option>
                                                <option value="Dimanche" <?php echo $emploi['jour_semaine'] === 'Dimanche' ? 'selected' : ''; ?>>Dimanche</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="heure_debut" class="form-label required-field">Heure de début</label>
                                            <input type="time" class="form-control time-input" id="heure_debut" name="heure_debut" 
                                                   value="<?php echo htmlspecialchars($emploi['heure_debut']); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="heure_fin" class="form-label required-field">Heure de fin</label>
                                            <input type="time" class="form-control time-input" id="heure_fin" name="heure_fin" 
                                                   value="<?php echo htmlspecialchars($emploi['heure_fin']); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="statut" class="form-label">Statut</label>
                                            <select class="form-select" id="statut" name="statut">
                                                <option value="actif" <?php echo $emploi['statut'] === 'actif' ? 'selected' : ''; ?>>Actif</option>
                                                <option value="suspendu" <?php echo $emploi['statut'] === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                                                <option value="archivé" <?php echo $emploi['statut'] === 'archivé' ? 'selected' : ''; ?>>Archivé</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="enseignant_id" class="form-label required-field">Enseignant</label>
                                            <select class="form-select" id="enseignant_id" name="enseignant_id" required>
                                                <option value="">Sélectionner un enseignant</option>
                                                <?php foreach ($enseignants as $enseignant): ?>
                                                    <option value="<?php echo $enseignant['id']; ?>" 
                                                            <?php echo $emploi['enseignant_id'] == $enseignant['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="classe_id" class="form-label required-field">Classe</label>
                                            <select class="form-select" id="classe_id" name="classe_id" required>
                                                <option value="">Sélectionner une classe</option>
                                                <?php foreach ($classes as $classe): ?>
                                                    <option value="<?php echo $classe['id']; ?>" 
                                                            <?php echo $emploi['classe_id'] == $classe['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($classe['nom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="cours_id" class="form-label required-field">Cours</label>
                                            <select class="form-select" id="cours_id" name="cours_id" required>
                                                <option value="">Sélectionner un cours</option>
                                                <?php foreach ($cours as $cour): ?>
                                                    <option value="<?php echo $cour['id']; ?>" 
                                                            <?php echo $emploi['cours_id'] == $cour['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cour['nom']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="salle" class="form-label">Salle</label>
                                            <input type="text" class="form-control" id="salle" name="salle" 
                                                   value="<?php echo htmlspecialchars($emploi['salle']); ?>" 
                                                   placeholder="Ex: Salle A, Laboratoire 1">
                                        </div>
                                        
                                        <div class="col-12">
                                            <label for="notes" class="form-label">Notes</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                      placeholder="Notes supplémentaires..."><?php echo htmlspecialchars($emploi['notes']); ?></textarea>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-check-lg me-2"></i>Enregistrer les modifications
                                                </button>
                                                <a href="index.php" class="btn btn-outline-secondary">
                                                    <i class="bi bi-x-lg me-2"></i>Annuler
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Informations
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">
                                    <i class="bi bi-lightbulb me-2"></i>
                                    <strong>Conseil :</strong> Vérifiez qu'il n'y a pas de conflits d'horaires avec d'autres cours.
                                </p>
                                
                                <p class="text-muted">
                                    <i class="bi bi-clock me-2"></i>
                                    <strong>Heures :</strong> L'heure de début doit être antérieure à l'heure de fin.
                                </p>
                                
                                <p class="text-muted">
                                    <i class="bi bi-person-check me-2"></i>
                                    <strong>Enseignant :</strong> Un enseignant ne peut pas avoir deux cours en même temps.
                                </p>
                                
                                <p class="text-muted">
                                    <i class="bi bi-building me-2"></i>
                                    <strong>Classe :</strong> Une classe ne peut pas avoir deux cours en même temps.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-exclamation-triangle fs-1 text-warning mb-3"></i>
                        <h5 class="text-muted">Emploi du temps non trouvé</h5>
                        <p class="text-muted">L'emploi du temps que vous recherchez n'existe pas ou a été supprimé.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left me-2"></i>Retour à la liste
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
