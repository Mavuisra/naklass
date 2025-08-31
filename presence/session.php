<?php
/**
 * Page de saisie de présence pour une session spécifique
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier les paramètres
if (!isset($_GET['classe_id']) || !isset($_GET['cours_id']) || 
    !is_numeric($_GET['classe_id']) || !is_numeric($_GET['cours_id'])) {
    setFlashMessage('error', 'Paramètres invalides.');
    redirect('index.php');
}

$classe_id = (int)$_GET['classe_id'];
$cours_id = (int)$_GET['cours_id'];

$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la classe
$classe = validateClassAccess($classe_id, $db);
if (!$classe) {
    redirect('index.php');
}

// Récupérer les détails du cours
try {
    $cours_query = "SELECT c.*, cc.enseignant_id, e.prenom as enseignant_prenom, e.nom as enseignant_nom
                     FROM cours c
                     JOIN classe_cours cc ON c.id = cc.cours_id
                     LEFT JOIN enseignants e ON cc.enseignant_id = e.id
                     WHERE c.id = :cours_id AND cc.classe_id = :classe_id";
    
    $stmt = $db->prepare($cours_query);
    $stmt->execute(['cours_id' => $cours_id, 'classe_id' => $classe_id]);
    $cours = $stmt->fetch();
    
    if (!$cours) {
        setFlashMessage('error', 'Cours non trouvé ou non assigné à cette classe.');
        redirect('classe.php?id=' . $classe_id);
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération du cours: ' . $e->getMessage());
    redirect('classe.php?id=' . $classe_id);
}

// Récupérer les étudiants de la classe
$eleves = getClassStudents($classe_id, $db);

// Traitement de la soumission de présence
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_presence') {
        try {
            $date_session = $_POST['date_session'];
            $heure_debut = $_POST['heure_debut'];
            $heure_fin = $_POST['heure_fin'];
            $remarques = sanitize($_POST['remarques'] ?? '');
            
            // Vérifier si une session existe déjà pour cette date et ce cours
            $check_session = "SELECT id FROM sessions_presence 
                             WHERE classe_id = :classe_id 
                             AND cours_id = :cours_id 
                             AND date_session = :date_session";
            
            $stmt = $db->prepare($check_session);
            $stmt->execute([
                'classe_id' => $classe_id,
                'cours_id' => $cours_id,
                'date_session' => $date_session
            ]);
            
            if ($stmt->fetch()) {
                setFlashMessage('error', 'Une session de présence existe déjà pour cette date et ce cours.');
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
                    'heure_debut' => $heure_debut,
                    'heure_fin' => $heure_fin,
                    'remarques' => $remarques,
                    'created_by' => $_SESSION['user_id']
                ]);
                
                $session_id = $db->lastInsertId();
                
                // Enregistrer les présences pour chaque étudiant
                foreach ($eleves as $eleve) {
                    $statut = $_POST['presence_' . $eleve['eleve_id']] ?? 'absent';
                    $justification = sanitize($_POST['justification_' . $eleve['eleve_id']] ?? '');
                    
                    $presence_query = "INSERT INTO presences 
                                     (session_id, eleve_id, statut, justification, created_by, created_at)
                                     VALUES (:session_id, :eleve_id, :statut, :justification, :created_by, NOW())";
                    
                    $stmt = $db->prepare($presence_query);
                    $stmt->execute([
                        'session_id' => $session_id,
                        'eleve_id' => $eleve['eleve_id'],
                        'statut' => $statut,
                        'justification' => $justification,
                        'created_by' => $_SESSION['user_id']
                    ]);
                }
                
                $db->commit();
                setFlashMessage('success', 'Présence enregistrée avec succès !');
                redirect('classe.php?id=' . $classe_id);
                
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            setFlashMessage('error', 'Erreur lors de l\'enregistrement de la présence: ' . $e->getMessage());
        }
    }
}

$page_title = "Saisie de Présence - " . $classe['nom_classe'] . " - " . $cours['nom_cours'];
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
                <h1><i class="bi bi-clipboard-check me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Saisie de présence pour la session</p>
            </div>
            
            <div class="topbar-actions">
                <a href="classe.php?id=<?php echo $classe_id; ?>" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-2"></i>Retour à la Classe
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-house me-2"></i>Accueil Présence
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
            
            <div class="row">
                <!-- Informations de la session -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations de la Session</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="text-muted">Classe</h6>
                            <p class="h6 mb-3"><?php echo htmlspecialchars($classe['nom_classe']); ?></p>
                            
                            <h6 class="text-muted">Cours</h6>
                            <p class="mb-3"><?php echo htmlspecialchars($cours['nom_cours']); ?></p>
                            
                            <h6 class="text-muted">Code du cours</h6>
                            <p class="mb-3"><code><?php echo htmlspecialchars($cours['code_cours']); ?></code></p>
                            
                            <h6 class="text-muted">Enseignant</h6>
                            <p class="mb-3">
                                <?php if ($cours['enseignant_prenom']): ?>
                                    <?php echo htmlspecialchars($cours['enseignant_prenom'] . ' ' . $cours['enseignant_nom']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Non assigné</span>
                                <?php endif; ?>
                            </p>
                            
                            <h6 class="text-muted">Nombre d'élèves</h6>
                            <p class="mb-3">
                                <span class="badge bg-primary fs-6"><?php echo count($eleves); ?></span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Formulaire de présence -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Saisie de Présence</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="save_presence">
                                
                                <!-- Informations de la session -->
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <label for="date_session" class="form-label">Date de la session *</label>
                                        <input type="date" class="form-control" id="date_session" name="date_session" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="heure_debut" class="form-label">Heure de début</label>
                                        <input type="time" class="form-control" id="heure_debut" name="heure_debut" 
                                               value="08:00">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="heure_fin" class="form-label">Heure de fin</label>
                                        <input type="time" class="form-control" id="heure_fin" name="heure_fin" 
                                               value="09:00">
                                    </div>
                                </div>
                                
                                <!-- Liste des étudiants -->
                                <div class="mb-4">
                                    <h6 class="mb-3">État de présence des étudiants</h6>
                                    
                                    <?php if (empty($eleves)): ?>
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            Aucun étudiant inscrit dans cette classe.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Étudiant</th>
                                                        <th>Matricule</th>
                                                        <th>Présence</th>
                                                        <th>Justification</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($eleves as $eleve): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <?php if ($eleve['photo']): ?>
                                                                        <img src="<?php echo htmlspecialchars($eleve['photo']); ?>" 
                                                                             alt="Photo" class="rounded-circle me-2" 
                                                                             width="32" height="32">
                                                                    <?php else: ?>
                                                                        <div class="bg-secondary rounded-circle me-2 d-flex align-items-center justify-content-center" 
                                                                             style="width: 32px; height: 32px;">
                                                                            <i class="bi bi-person text-white"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div>
                                                                        <strong><?php echo htmlspecialchars($eleve['nom']); ?></strong><br>
                                                                        <small class="text-muted"><?php echo htmlspecialchars($eleve['prenom']); ?></small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td><code><?php echo htmlspecialchars($eleve['matricule']); ?></code></td>
                                                            <td>
                                                                <select class="form-select form-select-sm" 
                                                                        name="presence_<?php echo $eleve['eleve_id']; ?>" 
                                                                        onchange="toggleJustification(<?php echo $eleve['eleve_id']; ?>)">
                                                                    <option value="present">Présent</option>
                                                                    <option value="absent">Absent</option>
                                                                    <option value="retard">Retard</option>
                                                                    <option value="justifie">Absence justifiée</option>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm justification-field" 
                                                                       name="justification_<?php echo $eleve['eleve_id']; ?>" 
                                                                       placeholder="Justification (optionnel)" 
                                                                       style="display: none;">
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Remarques générales -->
                                <div class="mb-4">
                                    <label for="remarques" class="form-label">Remarques générales</label>
                                    <textarea class="form-control" id="remarques" name="remarques" rows="3" 
                                              placeholder="Observations générales sur la session..."></textarea>
                                </div>
                                
                                <!-- Boutons d'action -->
                                <div class="d-flex justify-content-between">
                                    <a href="classe.php?id=<?php echo $classe_id; ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary" <?php echo empty($eleves) ? 'disabled' : ''; ?>>
                                        <i class="bi bi-save me-2"></i>Enregistrer la Présence
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction pour afficher/masquer le champ de justification
        function toggleJustification(eleveId) {
            const select = document.querySelector(`select[name="presence_${eleveId}"]`);
            const justificationField = document.querySelector(`input[name="justification_${eleveId}"]`);
            
            if (select.value === 'justifie' || select.value === 'retard') {
                justificationField.style.display = 'block';
                justificationField.required = true;
            } else {
                justificationField.style.display = 'none';
                justificationField.required = false;
                justificationField.value = '';
            }
        }
        
        // Initialiser l'état des champs de justification au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const selects = document.querySelectorAll('select[name^="presence_"]');
            selects.forEach(select => {
                const eleveId = select.name.replace('presence_', '');
                toggleJustification(eleveId);
            });
        });
    </script>
</body>
</html>
