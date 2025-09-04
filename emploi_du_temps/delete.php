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
    $query = "SELECT e.*, 
                     en.nom as enseignant_nom, en.prenom as enseignant_prenom,
                     c.nom_classe as classe_nom,
                     co.nom_cours as cours_nom
              FROM emploi_du_temps e
              LEFT JOIN enseignants en ON e.enseignant_id = en.id
              LEFT JOIN classes c ON e.classe_id = c.id
              LEFT JOIN cours co ON e.cours_id = co.id
              WHERE e.id = :id AND e.ecole_id = :ecole_id";
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

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $emploi) {
    if (isset($_POST['confirm_delete'])) {
        try {
            $delete_query = "DELETE FROM emploi_du_temps WHERE id = :id AND ecole_id = :ecole_id";
            $stmt = $db->prepare($delete_query);
            $stmt->execute([
                'id' => $emploi_id,
                'ecole_id' => $_SESSION['ecole_id']
            ]);
            
            if ($stmt->rowCount() > 0) {
                $message = "✅ Emploi du temps supprimé avec succès !";
                $message_type = 'success';
                
                // Rediriger vers la liste après 2 secondes
                header("refresh:2;url=index.php");
            } else {
                $message = "❌ Aucun emploi du temps n'a été supprimé.";
                $message_type = 'danger';
            }
            
        } catch (Exception $e) {
            $message = "❌ Erreur lors de la suppression : " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

$page_title = "Supprimer l'Emploi du Temps";
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
                <h1><i class="bi bi-trash me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Confirmez la suppression de l'emploi du temps</p>
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
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Confirmation de suppression
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Attention !</strong> Cette action est irréversible. L'emploi du temps sera définitivement supprimé.
                                </div>
                                
                                <h6>Informations de l'emploi du temps à supprimer :</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <th width="30%">Jour</th>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo $emploi['jour_semaine']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Heure</th>
                                                <td>
                                                    <strong><?php echo date('H:i', strtotime($emploi['heure_debut'])); ?></strong> - 
                                                    <?php echo date('H:i', strtotime($emploi['heure_fin'])); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Cours</th>
                                                <td><?php echo htmlspecialchars($emploi['cours_nom']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Enseignant</th>
                                                <td><?php echo htmlspecialchars($emploi['enseignant_prenom'] . ' ' . $emploi['enseignant_nom']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Classe</th>
                                                <td><?php echo htmlspecialchars($emploi['classe_nom']); ?></td>
                                            </tr>
                                            <?php if (!empty($emploi['salle'])): ?>
                                                <tr>
                                                    <th>Salle</th>
                                                    <td><?php echo htmlspecialchars($emploi['salle']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <th>Année scolaire</th>
                                                <td><?php echo htmlspecialchars($emploi['annee_scolaire']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Statut</th>
                                                <td>
                                                    <span class="badge bg-<?php echo $emploi['statut'] === 'actif' ? 'success' : ($emploi['statut'] === 'suspendu' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo ucfirst($emploi['statut']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php if (!empty($emploi['notes'])): ?>
                                                <tr>
                                                    <th>Notes</th>
                                                    <td><?php echo htmlspecialchars($emploi['notes']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <form method="POST" class="mt-4">
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="confirm_delete" class="btn btn-danger">
                                            <i class="bi bi-trash me-2"></i>Confirmer la suppression
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-lg me-2"></i>Annuler
                                        </a>
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
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Attention :</strong> La suppression est définitive et ne peut pas être annulée.
                                </p>
                                
                                <p class="text-muted">
                                    <i class="bi bi-arrow-clockwise me-2"></i>
                                    <strong>Alternative :</strong> Vous pouvez suspendre l'emploi du temps au lieu de le supprimer.
                                </p>
                                
                                <p class="text-muted">
                                    <i class="bi bi-archive me-2"></i>
                                    <strong>Archivage :</strong> Vous pouvez aussi archiver l'emploi du temps pour le conserver.
                                </p>
                                
                                <div class="mt-3">
                                    <a href="edit.php?id=<?php echo $emploi['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-pencil me-1"></i>Modifier au lieu de supprimer
                                    </a>
                                </div>
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
