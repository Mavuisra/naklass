<?php
/**
 * Page de détail d'une session de présence
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
                             cl.nom_classe, cl.niveau, cl.cycle,
                             u.prenom as created_by_prenom, u.nom as created_by_nom
                      FROM sessions_presence sp
                      JOIN cours c ON sp.cours_id = c.id
                      JOIN classes cl ON sp.classe_id = cl.id
                      LEFT JOIN utilisateurs u ON sp.created_by = u.id
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

// Récupérer la liste des présences des étudiants
try {
    $presences_query = "SELECT p.*, 
                               el.prenom, el.nom, el.matricule, el.photo_path,
                               u.prenom as created_by_prenom, u.nom as created_by_nom
                        FROM presences p
                        JOIN eleves el ON p.eleve_id = el.id
                        LEFT JOIN utilisateurs u ON p.created_by = u.id
                        WHERE p.session_id = :session_id
                        ORDER BY el.nom ASC, el.prenom ASC";
    
    $stmt = $db->prepare($presences_query);
    $stmt->execute(['session_id' => $session_id]);
    $presences = $stmt->fetchAll();
    
} catch (Exception $e) {
    $presences = [];
    error_log("Erreur lors de la récupération des présences: " . $e->getMessage());
}

// Calculer les statistiques de présence
$total_eleves = count($presences);
$presents = array_filter($presences, function($p) { return $p['statut'] === 'present'; });
$absents = array_filter($presences, function($p) { return $p['statut'] === 'absent'; });
$retards = array_filter($presences, function($p) { return $p['statut'] === 'retard'; });
$justifies = array_filter($presences, function($p) { return $p['statut'] === 'justifie'; });

$stats = [
    'total' => $total_eleves,
    'presents' => count($presents),
    'absents' => count($absents),
    'retards' => count($retards),
    'justifies' => count($justifies)
];

$page_title = "Détail Session - " . $session['nom_classe'] . " - " . $session['nom_cours'];
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
                <h1><i class="bi bi-eye me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Détails complets de la session de présence</p>
            </div>
            
            <div class="topbar-actions">
                <a href="historique.php?classe_id=<?php echo $session['classe_id']; ?>" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-2"></i>Retour à l'historique
                </a>
                <a href="edit_session.php?session_id=<?php echo $session_id; ?>" class="btn btn-warning me-2">
                    <i class="bi bi-pencil me-2"></i>Modifier
                </a>
                <button type="button" class="btn btn-danger" onclick="deleteSession(<?php echo $session_id; ?>)">
                    <i class="bi bi-trash me-2"></i>Supprimer
                </button>
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
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations de la Session</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6 class="text-muted">Classe</h6>
                                <p class="h5 mb-1"><?php echo htmlspecialchars($session['nom_classe']); ?></p>
                                <small class="text-muted"><?php echo htmlspecialchars($session['niveau']); ?> - <?php echo htmlspecialchars($session['cycle']); ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="text-muted">Cours</h6>
                                <p class="h5 mb-1"><?php echo htmlspecialchars($session['nom_cours']); ?></p>
                                <small class="text-muted"><?php echo htmlspecialchars($session['code_cours']); ?> (Coef: <?php echo $session['coefficient']; ?>)</small>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="text-muted">Date</h6>
                                <p class="h5 mb-1"><?php echo date('d/m/Y', strtotime($session['date_session'])); ?></p>
                                <small class="text-muted"><?php echo date('l', strtotime($session['date_session'])); ?></small>
                            </div>
                            
                            <?php if ($session['heure_debut'] && $session['heure_fin']): ?>
                            <div class="mb-3">
                                <h6 class="text-muted">Horaires</h6>
                                <p class="h5 mb-1">
                                    <i class="bi bi-clock text-success me-2"></i>
                                    <?php echo substr($session['heure_debut'], 0, 5); ?> - <?php echo substr($session['heure_fin'], 0, 5); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($session['remarques']): ?>
                            <div class="mb-3">
                                <h6 class="text-muted">Remarques</h6>
                                <p class="mb-1"><?php echo nl2br(htmlspecialchars($session['remarques'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <h6 class="text-muted">Créé par</h6>
                                <p class="mb-1">
                                    <i class="bi bi-person text-primary me-2"></i>
                                    <?php echo htmlspecialchars($session['created_by_prenom'] . ' ' . $session['created_by_nom']); ?>
                                </p>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($session['created_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiques de présence -->
                <div class="col-lg-8 mb-4">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-people display-4 mb-2"></i>
                                    <h4 class="mb-0"><?php echo $stats['total']; ?></h4>
                                    <p class="mb-0">Total</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-check-circle display-4 mb-2"></i>
                                    <h4 class="mb-0"><?php echo $stats['presents']; ?></h4>
                                    <p class="mb-0">Présents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-x-circle display-4 mb-2"></i>
                                    <h4 class="mb-0"><?php echo $stats['absents']; ?></h4>
                                    <p class="mb-0">Absents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-exclamation-triangle display-4 mb-2"></i>
                                    <h4 class="mb-0"><?php echo $stats['retards'] + $stats['justifies']; ?></h4>
                                    <p class="mb-0">Autres</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Graphique circulaire simple -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Répartition des Présences</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <div class="bg-success rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 20px; height: 20px;">
                                            <i class="bi bi-check text-white"></i>
                                        </div>
                                        <span>Présents: <?php echo $stats['presents']; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <div class="bg-danger rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 20px; height: 20px;">
                                            <i class="bi bi-x text-white"></i>
                                        </div>
                                        <span>Absents: <?php echo $stats['absents']; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 20px; height: 20px;">
                                            <i class="bi bi-clock text-white"></i>
                                        </div>
                                        <span>Retards: <?php echo $stats['retards']; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <div class="bg-info rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 20px; height: 20px;">
                                            <i class="bi bi-file-text text-white"></i>
                                        </div>
                                        <span>Justifiés: <?php echo $stats['justifies']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Liste des présences -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Détail des Présences</h5>
                            <span class="badge bg-primary"><?php echo count($presences); ?> étudiants</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($presences)): ?>
                                <div class="text-center p-5">
                                    <i class="bi bi-exclamation-triangle display-1 text-muted mb-3"></i>
                                    <h5>Aucune présence enregistrée</h5>
                                    <p class="text-muted">Aucune présence n'a été enregistrée pour cette session.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Étudiant</th>
                                                <th>Matricule</th>
                                                <th>Statut</th>
                                                <th>Justification</th>
                                                <th>Enregistré par</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($presences as $presence): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($presence['photo_path']): ?>
                                                                <img src="<?php echo htmlspecialchars($presence['photo_path']); ?>" 
                                                                     class="rounded-circle me-3" width="40" height="40" 
                                                                     alt="Photo de l'étudiant">
                                                            <?php else: ?>
                                                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                                     style="width: 40px; height: 40px;">
                                                                    <i class="bi bi-person text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($presence['nom'] . ' ' . $presence['prenom']); ?></strong>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <code><?php echo htmlspecialchars($presence['matricule']); ?></code>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_badges = [
                                                            'present' => 'bg-success',
                                                            'absent' => 'bg-danger',
                                                            'retard' => 'bg-warning',
                                                            'justifie' => 'bg-info'
                                                        ];
                                                        $status_labels = [
                                                            'present' => 'Présent',
                                                            'absent' => 'Absent',
                                                            'retard' => 'Retard',
                                                            'justifie' => 'Justifié'
                                                        ];
                                                        $badge_class = $status_badges[$presence['statut']] ?? 'bg-secondary';
                                                        $status_label = $status_labels[$presence['statut']] ?? 'Inconnu';
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php echo $status_label; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($presence['justification']): ?>
                                                            <span class="text-muted"><?php echo htmlspecialchars($presence['justification']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($presence['created_by_prenom']): ?>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($presence['created_by_prenom'] . ' ' . $presence['created_by_nom']); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y H:i', strtotime($presence['created_at'])); ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer cette session de présence ?</p>
                    <p class="text-danger"><strong>Cette action est irréversible et supprimera toutes les présences associées.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Supprimer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteSession(sessionId) {
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
        document.getElementById('confirmDelete').addEventListener('click', function() {
            window.location.href = `delete_session.php?session_id=<?php echo $session_id; ?>`;
        });
    </script>
</body>
</html>


