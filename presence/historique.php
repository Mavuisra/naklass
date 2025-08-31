<?php
/**
 * Page d'historique de présence pour une classe
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier l'ID de la classe
if (!isset($_GET['classe_id']) || !is_numeric($_GET['classe_id'])) {
    setFlashMessage('error', 'ID de classe invalide.');
    redirect('index.php');
}

$classe_id = (int)$_GET['classe_id'];
$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la classe
$classe = validateClassAccess($classe_id, $db);
if (!$classe) {
    redirect('index.php');
}

// Récupérer l'historique des sessions de présence
try {
    $historique_query = "SELECT sp.*, 
                                c.nom_cours, c.code_cours,
                                u.prenom as created_by_prenom, u.nom as created_by_nom,
                                COUNT(p.id) as nombre_presences
                         FROM sessions_presence sp
                         JOIN cours c ON sp.cours_id = c.id
                         LEFT JOIN utilisateurs u ON sp.created_by = u.id
                         LEFT JOIN presences p ON sp.id = p.session_id
                         WHERE sp.classe_id = :classe_id
                         GROUP BY sp.id
                         ORDER BY sp.date_session DESC, sp.created_at DESC";
    
    $stmt = $db->prepare($historique_query);
    $stmt->execute(['classe_id' => $classe_id]);
    $sessions = $stmt->fetchAll();
    
} catch (Exception $e) {
    $sessions = [];
    error_log("Erreur lors de la récupération de l'historique: " . $e->getMessage());
}

// Récupérer les statistiques globales
try {
    $stats_query = "SELECT 
                        COUNT(DISTINCT sp.id) as total_sessions,
                        COUNT(DISTINCT sp.cours_id) as total_cours,
                        MIN(sp.date_session) as premiere_session,
                        MAX(sp.date_session) as derniere_session
                    FROM sessions_presence sp
                    WHERE sp.classe_id = :classe_id";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute(['classe_id' => $classe_id]);
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    $stats = [
        'total_sessions' => 0,
        'total_cours' => 0,
        'premiere_session' => null,
        'derniere_session' => null
    ];
}

$page_title = "Historique de Présence - " . $classe['nom_classe'];
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
                <h1><i class="bi bi-clock-history me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Historique complet des sessions de présence</p>
            </div>
            
            <div class="topbar-actions">
                <a href="classe.php?id=<?php echo $classe_id; ?>" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
                <a href="session.php?classe_id=<?php echo $classe_id; ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nouvelle Session
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
                <!-- Statistiques globales -->
                <div class="col-lg-12 mb-4">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-calendar-check display-4 mb-2"></i>
                                    <h4 class="mb-0"><?php echo $stats['total_sessions']; ?></h4>
                                    <p class="mb-0">Sessions Total</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-book display-4 mb-2"></i>
                                    <h4 class="mb-0"><?php echo $stats['total_cours']; ?></h4>
                                    <p class="mb-0">Cours Différents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-calendar-plus display-4 mb-2"></i>
                                    <h6 class="mb-0">
                                        <?php echo $stats['premiere_session'] ? date('d/m/Y', strtotime($stats['premiere_session'])) : 'N/A'; ?>
                                    </h6>
                                    <p class="mb-0">Première Session</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-calendar-event display-4 mb-2"></i>
                                    <h6 class="mb-0">
                                        <?php echo $stats['derniere_session'] ? date('d/m/Y', strtotime($stats['derniere_session'])) : 'N/A'; ?>
                                    </h6>
                                    <p class="mb-0">Dernière Session</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Historique des sessions -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Historique des Sessions</h5>
                            <span class="badge bg-primary"><?php echo count($sessions); ?> sessions</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($sessions)): ?>
                                <div class="text-center p-5">
                                    <i class="bi bi-clock-history display-1 text-muted mb-3"></i>
                                    <h5>Aucune session de présence</h5>
                                    <p class="text-muted">Aucune session de présence n'a encore été créée pour cette classe.</p>
                                    <a href="session.php?classe_id=<?php echo $classe_id; ?>" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-2"></i>Créer la Première Session
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Date</th>
                                                <th>Cours</th>
                                                <th>Horaires</th>
                                                <th>Présences</th>
                                                <th>Créé par</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sessions as $session): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-calendar-date text-primary me-2"></i>
                                                            <div>
                                                                <strong><?php echo date('d/m/Y', strtotime($session['date_session'])); ?></strong>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <?php echo date('l', strtotime($session['date_session'])); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($session['nom_cours']); ?></strong>
                                                            <br>
                                                            <code class="text-muted"><?php echo htmlspecialchars($session['code_cours']); ?></code>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($session['heure_debut'] && $session['heure_fin']): ?>
                                                            <div class="text-center">
                                                                <i class="bi bi-clock text-success me-1"></i>
                                                                <?php echo substr($session['heure_debut'], 0, 5); ?> - <?php echo substr($session['heure_fin'], 0, 5); ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">Non spécifié</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo $session['nombre_presences']; ?> présences
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($session['created_by_prenom']): ?>
                                                            <div class="d-flex align-items-center">
                                                                <i class="bi bi-person text-primary me-2"></i>
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars($session['created_by_prenom'] . ' ' . $session['created_by_nom']); ?></strong>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        <?php echo date('d/m/Y H:i', strtotime($session['created_at'])); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">Inconnu</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="detail_session.php?session_id=<?php echo $session['id']; ?>" 
                                                               class="btn btn-outline-primary" title="Voir les détails">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="edit_session.php?session_id=<?php echo $session['id']; ?>" 
                                                               class="btn btn-outline-warning" title="Modifier">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="deleteSession(<?php echo $session['id']; ?>)" title="Supprimer">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
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
        let sessionToDelete = null;
        
        function deleteSession(sessionId) {
            sessionToDelete = sessionId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
        document.getElementById('confirmDelete').addEventListener('click', function() {
            if (sessionToDelete) {
                // Rediriger vers la page de suppression
                window.location.href = `delete_session.php?session_id=${sessionToDelete}`;
            }
        });
    </script>
</body>
</html>


