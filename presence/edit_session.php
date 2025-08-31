<?php
/**
 * Page de modification d'une session de présence
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier l'ID de la session
if (!isset($_GET['session_id']) || !is_numeric($_GET['session_id'])) {
    setFlashMessage('error', 'ID de session invalide.');
    redirect('index.php');
}

$session_id = (int)$_GET['session_id'];
$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la session
try {
    $session_query = "SELECT sp.*, 
                             c.nom_cours, c.code_cours, c.coefficient,
                             cl.nom_classe, cl.niveau, cl.cycle
                      FROM sessions_presence sp
                      JOIN cours c ON sp.cours_id = c.id
                      JOIN classes cl ON sp.classe_id = cl.id
                      WHERE sp.id = :session_id";
    
    $stmt = $db->prepare($session_query);
    $stmt->execute(['session_id' => $session_id]);
    $session = $stmt->fetch();
    
    if (!$session) {
        setFlashMessage('error', 'Session de présence non trouvée.');
        redirect('index.php');
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération de la session: ' . $e->getMessage());
    redirect('index.php');
}

// Récupérer la liste des étudiants de la classe
$students = getClassStudents($session['classe_id'], $db);

// Récupérer les présences existantes
try {
    $presences_query = "SELECT * FROM presences WHERE session_id = :session_id";
    $stmt = $db->prepare($presences_query);
    $stmt->execute(['session_id' => $session_id]);
    $existing_presences = $stmt->fetchAll();
    
    // Créer un tableau associatif pour un accès rapide
    $presences_by_student = [];
    foreach ($existing_presences as $presence) {
        $presences_by_student[$presence['eleve_id']] = $presence;
    }
    
} catch (Exception $e) {
    $existing_presences = [];
    $presences_by_student = [];
    error_log("Erreur lors de la récupération des présences: " . $e->getMessage());
}

// Traitement de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'update_session') {
                // Mise à jour des détails de la session
                $date_session = sanitize($_POST['date_session']);
                $heure_debut = sanitize($_POST['heure_debut']);
                $heure_fin = sanitize($_POST['heure_fin']);
                $remarques = sanitize($_POST['remarques']);
                
                $update_query = "UPDATE sessions_presence 
                                SET date_session = :date_session,
                                    heure_debut = :heure_debut,
                                    heure_fin = :heure_fin,
                                    remarques = :remarques,
                                    updated_at = NOW()
                                WHERE id = :session_id";
                
                $stmt = $db->prepare($update_query);
                $stmt->execute([
                    'date_session' => $date_session,
                    'heure_debut' => $heure_debut ?: null,
                    'heure_fin' => $heure_fin ?: null,
                    'remarques' => $remarques,
                    'session_id' => $session_id
                ]);
                
                setFlashMessage('success', 'Session mise à jour avec succès.');
                redirect("edit_session.php?session_id={$session_id}");
                
            } elseif ($_POST['action'] === 'update_presences') {
                // Mise à jour des présences
                $db->beginTransaction();
                
                foreach ($_POST['presences'] as $eleve_id => $presence_data) {
                    $statut = sanitize($presence_data['statut']);
                    $justification = sanitize($presence_data['justification'] ?? '');
                    
                    if (isset($presences_by_student[$eleve_id])) {
                        // Mettre à jour la présence existante
                        $update_presence = "UPDATE presences 
                                           SET statut = :statut,
                                               justification = :justification,
                                               updated_at = NOW()
                                           WHERE session_id = :session_id AND eleve_id = :eleve_id";
                        
                        $stmt = $db->prepare($update_presence);
                        $stmt->execute([
                            'statut' => $statut,
                            'justification' => $justification,
                            'session_id' => $session_id,
                            'eleve_id' => $eleve_id
                        ]);
                    } else {
                        // Créer une nouvelle présence
                        $insert_presence = "INSERT INTO presences 
                                           (session_id, eleve_id, statut, justification, created_by, created_at)
                                           VALUES (:session_id, :eleve_id, :statut, :justification, :created_by, NOW())";
                        
                        $stmt = $db->prepare($insert_presence);
                        $stmt->execute([
                            'session_id' => $session_id,
                            'eleve_id' => $eleve_id,
                            'statut' => $statut,
                            'justification' => $justification,
                            'created_by' => $_SESSION['user_id'] ?? 1
                        ]);
                    }
                }
                
                $db->commit();
                setFlashMessage('success', 'Présences mises à jour avec succès.');
                redirect("edit_session.php?session_id={$session_id}");
            }
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            setFlashMessage('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
        }
    }
}

$page_title = "Modifier Session - " . $session['nom_classe'] . " - " . $session['nom_cours'];
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
                <p class="text-muted">Modifier les détails de la session et les présences</p>
            </div>
            
            <div class="topbar-actions">
                <a href="detail_session.php?session_id=<?php echo $session_id; ?>" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-2"></i>Retour aux détails
                </a>
                <a href="historique.php?classe_id=<?php echo $session['classe_id']; ?>" class="btn btn-outline-primary">
                    <i class="bi bi-clock-history me-2"></i>Historique
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            <?php endforeach; ?>
            
            <div class="row">
                <!-- Formulaire de modification de la session -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Modifier la Session</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_session">
                                
                                <div class="mb-3">
                                    <label for="classe" class="form-label">Classe</label>
                                    <input type="text" class="form-control" id="classe" 
                                           value="<?php echo htmlspecialchars($session['nom_classe']); ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="cours" class="form-label">Cours</label>
                                    <input type="text" class="form-control" id="cours" 
                                           value="<?php echo htmlspecialchars($session['nom_cours']); ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="date_session" class="form-label">Date de la session *</label>
                                    <input type="date" class="form-control" id="date_session" name="date_session" 
                                           value="<?php echo htmlspecialchars($session['date_session']); ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="heure_debut" class="form-label">Heure de début</label>
                                            <input type="time" class="form-control" id="heure_debut" name="heure_debut" 
                                                   value="<?php echo htmlspecialchars($session['heure_debut'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="heure_fin" class="form-label">Heure de fin</label>
                                            <input type="time" class="form-control" id="heure_fin" name="heure_fin" 
                                                   value="<?php echo htmlspecialchars($session['heure_fin'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="remarques" class="form-label">Remarques</label>
                                    <textarea class="form-control" id="remarques" name="remarques" rows="3"
                                              placeholder="Remarques sur la session..."><?php echo htmlspecialchars($session['remarques'] ?? ''); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-check-circle me-2"></i>Mettre à jour la session
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Gestion des présences -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Gérer les Présences</h5>
                            <small class="text-muted"><?php echo count($students); ?> étudiants inscrits</small>
                        </div>
                        <div class="card-body">
                            <?php if (empty($students)): ?>
                                <div class="text-center p-5">
                                    <i class="bi bi-exclamation-triangle display-1 text-muted mb-3"></i>
                                    <h5>Aucun étudiant inscrit</h5>
                                    <p class="text-muted">Aucun étudiant n'est inscrit dans cette classe.</p>
                                </div>
                            <?php else: ?>
                                <form method="POST" id="presencesForm">
                                    <input type="hidden" name="action" value="update_presences">
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Étudiant</th>
                                                    <th>Matricule</th>
                                                    <th>Statut</th>
                                                    <th>Justification</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($students as $student): ?>
                                                    <?php 
                                                    $existing_presence = $presences_by_student[$student['eleve_id']] ?? null;
                                                    $current_statut = $existing_presence['statut'] ?? 'present';
                                                    $current_justification = $existing_presence['justification'] ?? '';
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if ($student['photo']): ?>
                                                                    <img src="<?php echo htmlspecialchars($student['photo']); ?>" 
                                                                         class="rounded-circle me-3" width="40" height="40" 
                                                                         alt="Photo de l'étudiant">
                                                                <?php else: ?>
                                                                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                                         style="width: 40px; height: 40px;">
                                                                        <i class="bi bi-person text-white"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars($student['nom'] . ' ' . $student['prenom']); ?></strong>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <code><?php echo htmlspecialchars($student['matricule']); ?></code>
                                                        </td>
                                                        <td>
                                                            <select class="form-select form-select-sm" 
                                                                    name="presences[<?php echo $student['eleve_id']; ?>][statut]">
                                                                <option value="present" <?php echo $current_statut === 'present' ? 'selected' : ''; ?>>
                                                                    ✅ Présent
                                                                </option>
                                                                <option value="absent" <?php echo $current_statut === 'absent' ? 'selected' : ''; ?>>
                                                                    ❌ Absent
                                                                </option>
                                                                <option value="retard" <?php echo $current_statut === 'retard' ? 'selected' : ''; ?>>
                                                                    ⏰ Retard
                                                                </option>
                                                                <option value="justifie" <?php echo $current_statut === 'justifie' ? 'selected' : ''; ?>>
                                                                    📄 Justifié
                                                                </option>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm" 
                                                                   name="presences[<?php echo $student['eleve_id']; ?>][justification]"
                                                                   value="<?php echo htmlspecialchars($current_justification); ?>"
                                                                   placeholder="Justification...">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <button type="button" class="btn btn-outline-secondary me-2" onclick="setAllPresent()">
                                                <i class="bi bi-check-all me-2"></i>Tous présents
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="setAllAbsent()">
                                                <i class="bi bi-x-all me-2"></i>Tous absents
                                            </button>
                                        </div>
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-save me-2"></i>Enregistrer les présences
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setAllPresent() {
            const statutSelects = document.querySelectorAll('select[name*="[statut]"]');
            statutSelects.forEach(select => {
                select.value = 'present';
            });
        }
        
        function setAllAbsent() {
            const statutSelects = document.querySelectorAll('select[name*="[statut]"]');
            statutSelects.forEach(select => {
                select.value = 'absent';
            });
        }
        
        // Validation du formulaire
        document.getElementById('presencesForm').addEventListener('submit', function(e) {
            const statutSelects = document.querySelectorAll('select[name*="[statut]"]');
            let hasSelection = false;
            
            statutSelects.forEach(select => {
                if (select.value) {
                    hasSelection = true;
                }
            });
            
            if (!hasSelection) {
                e.preventDefault();
                alert('Veuillez sélectionner au moins un statut de présence.');
                return false;
            }
        });
    </script>
</body>
</html>


