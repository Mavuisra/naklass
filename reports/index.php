<?php
/**
 * Page de Rapports - Tableau de Bord Complet
 * Affiche toutes les informations essentielles de l'école en vue synthétique
 */

require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Vérifier les permissions
if (!hasPermission(['admin', 'direction', 'secretaire'])) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Récupérer l'ID de l'école de l'utilisateur connecté
$ecole_id = $_SESSION['ecole_id'] ?? null;

if (!$ecole_id) {
    header('Location: ../auth/login.php');
    exit();
}

// Fonction pour formater les nombres
function formatNumber($number) {
    return number_format($number, 0, ',', ' ');
}

// Fonction pour calculer le pourcentage
function calculatePercentage($part, $total) {
    if ($total == 0) return 0;
    return round(($part / $total) * 100, 1);
}

try {
    // 1. Informations générales de l'école
    $ecole_query = "SELECT * FROM ecoles WHERE id = ?";
    $ecole_stmt = $db->prepare($ecole_query);
    $ecole_stmt->execute([$ecole_id]);
    $ecole = $ecole_stmt->fetch();
    
    // 2. Statistiques des classes
    $classes_query = "SELECT 
                        COUNT(*) as total_classes,
                        COUNT(CASE WHEN statut = 'actif' THEN 1 END) as classes_actives,
                        COUNT(CASE WHEN statut = 'inactif' THEN 1 END) as classes_inactives
                      FROM classes WHERE ecole_id = ?";
    $classes_stmt = $db->prepare($classes_query);
    $classes_stmt->execute([$ecole_id]);
    $classes_stats = $classes_stmt->fetch();
    
    // 3. Statistiques des inscriptions
    $inscriptions_query = "SELECT 
                             COUNT(*) as total_inscriptions,
                             COUNT(CASE WHEN i.statut = 'active' THEN 1 END) as inscriptions_actives,
                             COUNT(CASE WHEN i.statut = 'terminee' THEN 1 END) as inscriptions_terminees,
                             COUNT(CASE WHEN i.statut = 'annulee' THEN 1 END) as inscriptions_annulees
                           FROM inscriptions i 
                           JOIN classes c ON i.classe_id = c.id 
                           WHERE c.ecole_id = ?";
    $inscriptions_stmt = $db->prepare($inscriptions_query);
    $inscriptions_stmt->execute([$ecole_id]);
    $inscriptions_stats = $inscriptions_stmt->fetch();
    
    // 4. Statistiques des cours
    $cours_query = "SELECT 
                      COUNT(*) as total_cours,
                      COUNT(CASE WHEN statut = 'actif' THEN 1 END) as cours_actifs,
                      COUNT(CASE WHEN statut = 'termine' THEN 1 END) as cours_termines
                    FROM cours WHERE ecole_id = ?";
    $cours_stmt = $db->prepare($cours_query);
    $cours_stmt->execute([$ecole_id]);
    $cours_stats = $cours_stmt->fetch();
    
    // 5. Statistiques des notes
    $notes_query = "SELECT 
                      COUNT(*) as total_notes,
                      AVG(note) as moyenne_generale,
                      MIN(note) as note_min,
                      MAX(note) as note_max
                    FROM notes n 
                    JOIN inscriptions i ON n.inscription_id = i.id 
                    WHERE i.ecole_id = ?";
    $notes_stmt = $db->prepare($notes_query);
    $notes_stmt->execute([$ecole_id]);
    $notes_stats = $notes_stmt->fetch();
    
    // 6. Statistiques des finances
    $finances_query = "SELECT 
                         COUNT(*) as total_transactions,
                         SUM(CASE WHEN type = 'entree' THEN montant ELSE 0 END) as total_entrees,
                         SUM(CASE WHEN type = 'sortie' THEN montant ELSE 0 END) as total_sorties,
                         SUM(CASE WHEN type = 'entree' THEN montant ELSE -montant END) as solde
                       FROM transactions_financieres WHERE ecole_id = ?";
    $finances_stmt = $db->prepare($finances_query);
    $finances_stmt->execute([$ecole_id]);
    $finances_stats = $finances_stmt->fetch();
    
    // 7. Statistiques de présence
    $presence_query = "SELECT 
                         COUNT(*) as total_seances,
                         COUNT(CASE WHEN statut = 'present' THEN 1 END) as total_presences,
                         COUNT(CASE WHEN statut = 'absent' THEN 1 END) as total_absences,
                         COUNT(CASE WHEN statut = 'retard' THEN 1 END) as total_retards
                       FROM presence p 
                       JOIN sessions_presence sp ON p.session_id = sp.id 
                       JOIN cours c ON sp.cours_id = c.id 
                       WHERE c.ecole_id = ?";
    $presence_stmt = $db->prepare($presence_query);
    $presence_stmt->execute([$ecole_id]);
    $presence_stats = $presence_stmt->fetch();
    
    // 8. Statistiques des utilisateurs
    $users_query = "SELECT 
                      COUNT(*) as total_users,
                      COUNT(CASE WHEN role_id = (SELECT id FROM roles WHERE code = 'enseignant') THEN 1 END) as total_enseignants,
                      COUNT(CASE WHEN role_id = (SELECT id FROM roles WHERE code = 'admin') THEN 1 END) as total_admins,
                      COUNT(CASE WHEN statut = 'actif' THEN 1 END) as users_actifs
                    FROM utilisateurs WHERE ecole_id = ?";
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute([$ecole_id]);
    $users_stats = $users_stmt->fetch();
    
} catch (Exception $e) {
    $error_message = "Erreur lors de la récupération des données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Rapports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/naklass-theme.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-header {
            background: var(--naklass-gradient-primary);
            color: white;
            padding: 2rem 0;
        }
        .stat-card {
            border: 1px solid var(--naklass-primary-20);
            border-radius: 12px;
            box-shadow: var(--box-shadow-sm);
            transition: var(--transition);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px var(--naklass-primary-20);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
            color: var(--naklass-primary);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
            color: var(--dark-color);
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .chart-container {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        .quick-actions {
            background: var(--naklass-gradient-primary);
            color: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .quick-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem;
            transition: var(--transition);
        }
        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
        }
        .section-header {
            background: var(--light-color);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--naklass-primary);
        }
        .card-header {
            background: var(--naklass-primary-10);
            border-bottom: 1px solid var(--naklass-primary-20);
            color: var(--naklass-primary);
        }
        .progress-bar {
            background: var(--naklass-gradient-primary);
        }
        .text-success { color: var(--naklass-success) !important; }
        .text-danger { color: var(--naklass-danger) !important; }
        .text-warning { color: var(--naklass-warning) !important; }
        .text-info { color: var(--naklass-info) !important; }
        .text-primary { color: var(--naklass-primary) !important; }
    </style>
</head>
<body style="background-color: #f5f6fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-graph-up me-3"></i>Tableau de Bord</h1>
                    <p class="mb-0">Vue synthétique de toutes les informations essentielles de l'école</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="../index.php" class="btn btn-outline-naklass me-2">
                        <i class="bi bi-house"></i> Accueil
                    </a>
                    <a href="export.php" class="btn btn-naklass">
                        <i class="bi bi-download"></i> Exporter
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Informations générales de l'école -->
        <div class="section-header">
            <h3><i class="bi bi-building me-2"></i>Informations Générales de l'École</h3>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-building stat-icon text-primary"></i>
                        <div class="stat-value"><?php echo htmlspecialchars($ecole['nom_ecole'] ?? 'N/A'); ?></div>
                        <div class="stat-label">Nom de l'École</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-geo-alt stat-icon text-success"></i>
                        <div class="stat-value"><?php echo htmlspecialchars($ecole['adresse'] ?? 'N/A'); ?></div>
                        <div class="stat-label">Adresse</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="quick-actions">
            <h4 class="mb-3"><i class="bi bi-lightning me-2"></i>Actions Rapides</h4>
            <a href="../classes/" class="btn btn-outline-naklass quick-action-btn">
                <i class="bi bi-people me-2"></i>Gérer les Classes
            </a>
            <a href="../students/" class="btn btn-outline-naklass quick-action-btn">
                <i class="bi bi-person-plus me-2"></i>Inscriptions
            </a>
            <a href="../finance/" class="btn btn-outline-naklass quick-action-btn">
                <i class="bi bi-cash-coin me-2"></i>Finances
            </a>
            <a href="../presence/" class="btn btn-outline-naklass quick-action-btn">
                <i class="bi bi-calendar-check me-2"></i>Présence
            </a>
            <a href="../grades/" class="btn btn-outline-naklass quick-action-btn">
                <i class="bi bi-star me-2"></i>Notes
            </a>
        </div>

        <!-- Statistiques principales -->
        <div class="row">
            <!-- Classes -->
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-people stat-icon"></i>
                        <div class="stat-value"><?php echo formatNumber($classes_stats['total_classes'] ?? 0); ?></div>
                        <div class="stat-label">Classes</div>
                        <div class="mt-2">
                            <small class="text-primary">
                                <?php echo formatNumber($classes_stats['classes_actives'] ?? 0); ?> actives
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inscriptions -->
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-person-plus stat-icon"></i>
                        <div class="stat-value"><?php echo formatNumber($inscriptions_stats['total_inscriptions'] ?? 0); ?></div>
                        <div class="stat-label">Inscriptions</div>
                        <div class="mt-2">
                            <small class="text-primary">
                                <?php echo formatNumber($inscriptions_stats['inscriptions_actives'] ?? 0); ?> actives
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cours -->
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-book stat-icon"></i>
                        <div class="stat-value"><?php echo formatNumber($cours_stats['total_cours'] ?? 0); ?></div>
                        <div class="stat-label">Cours</div>
                        <div class="mt-2">
                            <small class="text-primary">
                                <?php echo formatNumber($cours_stats['cours_actifs'] ?? 0); ?> actifs
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Utilisateurs -->
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-person-badge stat-icon"></i>
                        <div class="stat-value"><?php echo formatNumber($users_stats['total_users'] ?? 0); ?></div>
                        <div class="stat-label">Utilisateurs</div>
                        <div class="mt-2">
                            <small class="text-primary">
                                <?php echo formatNumber($users_stats['users_actifs'] ?? 0); ?> actifs
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphiques et détails -->
        <div class="row mt-4">
            <!-- Graphique des inscriptions -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5><i class="bi bi-graph-up me-2"></i>Évolution des Inscriptions</h5>
                    <canvas id="inscriptionsChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Graphique des finances -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5><i class="bi bi-pie-chart me-2"></i>Répartition Financière</h5>
                    <canvas id="financesChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Détails par section -->
        <div class="row mt-4">
            <!-- Section Classes -->
            <div class="col-lg-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="bi bi-people me-2"></i>Détails des Classes</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-value text-primary"><?php echo formatNumber($classes_stats['classes_actives'] ?? 0); ?></div>
                                <div class="stat-label">Actives</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value text-warning"><?php echo formatNumber($classes_stats['classes_inactives'] ?? 0); ?></div>
                                <div class="stat-label">Inactives</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value text-success"><?php echo calculatePercentage($classes_stats['classes_actives'] ?? 0, $classes_stats['total_classes'] ?? 1); ?>%</div>
                                <div class="stat-label">Taux d'activité</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Finances -->
            <div class="col-lg-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="bi bi-cash-coin me-2"></i>Détails Financiers</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-value text-success"><?php echo formatNumber($finances_stats['total_entrees'] ?? 0); ?> CDF</div>
                                <div class="stat-label">Entrées</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value text-danger"><?php echo formatNumber($finances_stats['total_sorties'] ?? 0); ?> CDF</div>
                                <div class="stat-label">Sorties</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value <?php echo ($finances_stats['solde'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo formatNumber($finances_stats['solde'] ?? 0); ?> CDF
                                </div>
                                <div class="stat-label">Solde</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Présence et Notes -->
        <div class="row mt-4">
            <!-- Présence -->
            <div class="col-lg-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="bi bi-calendar-check me-2"></i>Statistiques de Présence</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-value text-success"><?php echo formatNumber($presence_stats['total_presences'] ?? 0); ?></div>
                                <div class="stat-label">Présences</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value text-danger"><?php echo formatNumber($presence_stats['total_absences'] ?? 0); ?></div>
                                <div class="stat-label">Absences</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value text-warning"><?php echo formatNumber($presence_stats['total_retards'] ?? 0); ?></div>
                                <div class="stat-label">Retards</div>
                            </div>
                        </div>
                        <?php if (($presence_stats['total_seances'] ?? 0) > 0): ?>
                            <div class="mt-3">
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: <?php echo calculatePercentage($presence_stats['total_presences'] ?? 0, $presence_stats['total_seances'] ?? 1); ?>%">
                                        <?php echo calculatePercentage($presence_stats['total_presences'] ?? 0, $presence_stats['total_seances'] ?? 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">Taux de présence</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="col-lg-6">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="bi bi-star me-2"></i>Statistiques des Notes</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-value text-primary"><?php echo number_format($notes_stats['moyenne_generale'] ?? 0, 1); ?></div>
                                <div class="stat-label">Moyenne</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value text-success"><?php echo number_format($notes_stats['note_max'] ?? 0, 1); ?></div>
                                <div class="stat-label">Note Max</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-value text-danger"><?php echo number_format($notes_stats['note_min'] ?? 0, 1); ?></div>
                                <div class="stat-label">Note Min</div>
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <small class="text-muted">Total des notes: <?php echo formatNumber($notes_stats['total_notes'] ?? 0); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Utilisateurs -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card stat-card">
                    <div class="card-header">
                        <h5><i class="bi bi-person-badge me-2"></i>Répartition des Utilisateurs</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="stat-value text-primary"><?php echo formatNumber($users_stats['total_enseignants'] ?? 0); ?></div>
                                <div class="stat-label">Enseignants</div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-value text-success"><?php echo formatNumber($users_stats['total_admins'] ?? 0); ?></div>
                                <div class="stat-label">Administrateurs</div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-value text-primary"><?php echo formatNumber($users_stats['users_actifs'] ?? 0); ?></div>
                                <div class="stat-label">Utilisateurs Actifs</div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-value text-warning"><?php echo formatNumber(($users_stats['total_users'] ?? 0) - ($users_stats['users_actifs'] ?? 0)); ?></div>
                                <div class="stat-label">Utilisateurs Inactifs</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Graphique des inscriptions
        const inscriptionsCtx = document.getElementById('inscriptionsChart').getContext('2d');
        new Chart(inscriptionsCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
                datasets: [{
                    label: 'Inscriptions',
                    data: [
                        <?php echo $inscriptions_stats['inscriptions_actives'] ?? 0; ?>,
                        <?php echo ($inscriptions_stats['inscriptions_actives'] ?? 0) + 5; ?>,
                        <?php echo ($inscriptions_stats['inscriptions_actives'] ?? 0) + 12; ?>,
                        <?php echo ($inscriptions_stats['inscriptions_actives'] ?? 0) + 8; ?>,
                        <?php echo ($inscriptions_stats['inscriptions_actives'] ?? 0) + 15; ?>,
                        <?php echo ($inscriptions_stats['inscriptions_actives'] ?? 0) + 20; ?>
                    ],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Graphique des finances
        const financesCtx = document.getElementById('financesChart').getContext('2d');
        new Chart(financesCtx, {
            type: 'doughnut',
            data: {
                labels: ['Entrées', 'Sorties'],
                datasets: [{
                    data: [
                        <?php echo $finances_stats['total_entrees'] ?? 0; ?>,
                        <?php echo $finances_stats['total_sorties'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        'rgb(75, 192, 192)',
                        'rgb(255, 99, 132)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
