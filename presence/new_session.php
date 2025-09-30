<?php
/**
 * Page de création d'une nouvelle session de présence
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $cours_id = (int)($_POST['cours_id'] ?? 0);
    $date_session = sanitize($_POST['date_session'] ?? '');
    $heure_debut = sanitize($_POST['heure_debut'] ?? '');
    $heure_fin = sanitize($_POST['heure_fin'] ?? '');
    $remarques = sanitize($_POST['remarques'] ?? '');
    
    // Validation
    if (!$classe_id || !$cours_id || !$date_session) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        try {
            // Vérifier que la classe existe et appartient à l'école
            $classe = validateClassAccess($classe_id, $db);
            if (!$classe) {
                $error = 'Classe non trouvée ou accès non autorisé.';
            } else {
                // Vérifier que le cours existe et est assigné à cette classe
                $cours_query = "SELECT c.*, cc.enseignant_id, e.prenom as enseignant_prenom, e.nom as enseignant_nom
                               FROM cours c
                               JOIN classe_cours cc ON c.id = cc.cours_id
                               LEFT JOIN enseignants e ON cc.enseignant_id = e.id
                               WHERE c.id = :cours_id AND cc.classe_id = :classe_id";
                
                $stmt = $db->prepare($cours_query);
                $stmt->execute(['cours_id' => $cours_id, 'classe_id' => $classe_id]);
                $cours = $stmt->fetch();
                
                if (!$cours) {
                    $error = 'Cours non trouvé ou non assigné à cette classe.';
                } else {
                    // Vérifier qu'il n'y a pas déjà une session pour cette date et ce cours
                    $existing_session = "SELECT id FROM sessions_presence 
                                       WHERE classe_id = :classe_id AND cours_id = :cours_id AND date_session = :date_session";
                    
                    $stmt = $db->prepare($existing_session);
                    $stmt->execute([
                        'classe_id' => $classe_id,
                        'cours_id' => $cours_id,
                        'date_session' => $date_session
                    ]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Une session de présence existe déjà pour cette date et ce cours.';
                    } else {
                        // Créer la session de présence
                        $db->beginTransaction();
                        
                        $session_query = "INSERT INTO sessions_presence 
                                         (classe_id, cours_id, date_session, heure_debut, heure_fin, remarques, created_by, created_at)
                                         VALUES (:classe_id, :cours_id, :date_session, :heure_debut, :heure_fin, :remarques, :created_by, NOW())";
                        
                        $stmt = $db->prepare($session_query);
                        $stmt->execute([
                            'classe_id' => $classe_id,
                            'cours_id' => $cours_id,
                            'date_session' => $date_session,
                            'heure_debut' => $heure_debut ?: null,
                            'heure_fin' => $heure_fin ?: null,
                            'remarques' => $remarques ?: null,
                            'created_by' => $_SESSION['user_id']
                        ]);
                        
                        $session_id = $db->lastInsertId();
                        
                        // Récupérer les élèves de la classe
                        $eleves = getClassStudents($classe_id, $db);
                        
                        // Créer les enregistrements de présence pour chaque élève (par défaut absent)
                        foreach ($eleves as $eleve) {
                            $presence_query = "INSERT INTO presences (session_id, eleve_id, statut, created_by, created_at)
                                             VALUES (:session_id, :eleve_id, 'absent', :created_by, NOW())";
                            
                            $stmt = $db->prepare($presence_query);
                            $stmt->execute([
                                'session_id' => $session_id,
                                'eleve_id' => $eleve['id'],
                                'created_by' => $_SESSION['user_id']
                            ]);
                        }
                        
                        $db->commit();
                        
                        // Rediriger vers la page de saisie de présence
                        redirect("session.php?classe_id=$classe_id&cours_id=$cours_id&session_id=$session_id");
                    }
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Erreur lors de la création de la session: ' . $e->getMessage();
        }
    }
}

// Récupérer les classes de l'école
try {
    $classes_query = "SELECT c.* FROM classes c 
                      WHERE c.ecole_id = :ecole_id 
                      AND c.statut = 'actif'
                      ORDER BY c.cycle ASC, c.niveau ASC, c.nom_classe ASC";
    
    $stmt = $db->prepare($classes_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classes = $stmt->fetchAll();
    
} catch (Exception $e) {
    $classes = [];
    $error = 'Erreur lors de la récupération des classes: ' . $e->getMessage();
}

$page_title = "Nouvelle Session de Présence";
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
                <h1><i class="bi bi-plus-circle me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Créez une nouvelle session de présence pour une classe et un cours</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Créer une Session de Présence</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="newSessionForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="classe_id" class="form-label">Classe <span class="text-danger">*</span></label>
                                        <select class="form-select" id="classe_id" name="classe_id" required>
                                            <option value="">Sélectionner une classe</option>
                                            <?php foreach ($classes as $classe): ?>
                                                <option value="<?php echo $classe['id']; ?>" 
                                                        data-cycle="<?php echo htmlspecialchars($classe['cycle']); ?>"
                                                        data-niveau="<?php echo htmlspecialchars($classe['niveau']); ?>">
                                                    <?php echo htmlspecialchars($classe['nom_classe']); ?> 
                                                    (<?php echo htmlspecialchars($classe['cycle']); ?> - <?php echo htmlspecialchars($classe['niveau']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="cours_id" class="form-label">Cours <span class="text-danger">*</span></label>
                                        <select class="form-select" id="cours_id" name="cours_id" required disabled>
                                            <option value="">Sélectionner d'abord une classe</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="date_session" class="form-label">Date de la Session <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="date_session" name="date_session" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="heure_debut" class="form-label">Heure de Début</label>
                                        <input type="time" class="form-control" id="heure_debut" name="heure_debut">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="heure_fin" class="form-label">Heure de Fin</label>
                                        <input type="time" class="form-control" id="heure_fin" name="heure_fin">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="remarques" class="form-label">Remarques</label>
                                    <textarea class="form-control" id="remarques" name="remarques" rows="3" 
                                              placeholder="Remarques sur la session (optionnel)"></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="index.php" class="btn btn-outline-secondary me-md-2">
                                        <i class="bi bi-x-circle me-2"></i>Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-2"></i>Créer la Session
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">
                                <strong>Étapes :</strong>
                            </p>
                            <ol class="small text-muted">
                                <li>Sélectionnez une classe</li>
                                <li>Choisissez un cours assigné à cette classe</li>
                                <li>Définissez la date et l'heure de la session</li>
                                <li>Ajoutez des remarques si nécessaire</li>
                                <li>Créez la session et saisissez les présences</li>
                            </ol>
                            
                            <hr>
                            
                            <p class="text-muted small">
                                <strong>Note :</strong> Une fois la session créée, vous pourrez saisir les présences des élèves.
                            </p>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-building me-2"></i>Classes Disponibles</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($classes)): ?>
                                <p class="text-muted small">Aucune classe disponible</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($classes as $classe): ?>
                                        <div class="list-group-item px-0 py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong class="small"><?php echo htmlspecialchars($classe['nom_classe']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($classe['cycle']); ?> - <?php echo htmlspecialchars($classe['niveau']); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-primary"><?php echo getClassStudentCount($classe['id'], $db, true); ?> élèves</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
    // Charger les cours quand une classe est sélectionnée
    document.getElementById('classe_id').addEventListener('change', function() {
        const classeId = this.value;
        const coursSelect = document.getElementById('cours_id');
        
        if (classeId) {
            // Activer le select des cours
            coursSelect.disabled = false;
            
            // Charger les cours via AJAX
            fetch(`get_cours_for_classe.php?classe_id=${classeId}`)
                .then(response => response.json())
                .then(data => {
                    coursSelect.innerHTML = '<option value="">Sélectionner un cours</option>';
                    
                    if (data.success && data.cours) {
                        data.cours.forEach(cours => {
                            const option = document.createElement('option');
                            option.value = cours.id;
                            option.textContent = `${cours.nom_cours} (${cours.code_cours})`;
                            coursSelect.appendChild(option);
                        });
                    } else {
                        coursSelect.innerHTML = '<option value="">Aucun cours trouvé</option>';
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    coursSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                });
        } else {
            coursSelect.disabled = true;
            coursSelect.innerHTML = '<option value="">Sélectionner d\'abord une classe</option>';
        }
    });
    
    // Validation du formulaire
    document.getElementById('newSessionForm').addEventListener('submit', function(e) {
        const classeId = document.getElementById('classe_id').value;
        const coursId = document.getElementById('cours_id').value;
        const dateSession = document.getElementById('date_session').value;
        
        if (!classeId || !coursId || !dateSession) {
            e.preventDefault();
            alert('Veuillez remplir tous les champs obligatoires.');
            return false;
        }
        
        // Vérifier que la date n'est pas dans le futur de plus de 7 jours
        const today = new Date();
        const selectedDate = new Date(dateSession);
        const diffTime = selectedDate - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays > 7) {
            e.preventDefault();
            alert('La date de la session ne peut pas être dans plus de 7 jours.');
            return false;
        }
    });
    </script>
</body>
</html>



