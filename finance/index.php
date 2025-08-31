<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'caissier']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Paramètres de pagination et filtres
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$search = sanitize($_GET['search'] ?? '');
$statut_filter = sanitize($_GET['statut'] ?? '');
$mode_filter = sanitize($_GET['mode'] ?? '');
$date_debut = sanitize($_GET['date_debut'] ?? '');
$date_fin = sanitize($_GET['date_fin'] ?? '');

// Construction de la requête avec filtres
$where_conditions = ["e.ecole_id = :ecole_id"];
$params = ['ecole_id' => $_SESSION['ecole_id']];

if (!empty($search)) {
    $where_conditions[] = "(e.nom LIKE :search OR e.prenom LIKE :search OR e.matricule LIKE :search OR p.numero_recu LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($statut_filter)) {
    $where_conditions[] = "p.statut = :statut";
    $params['statut'] = $statut_filter;
}

if (!empty($mode_filter)) {
    $where_conditions[] = "p.mode_paiement = :mode";
    $params['mode'] = $mode_filter;
}

if (!empty($date_debut)) {
    $where_conditions[] = "DATE(p.date_paiement) >= :date_debut";
    $params['date_debut'] = $date_debut;
}

if (!empty($date_fin)) {
    $where_conditions[] = "DATE(p.date_paiement) <= :date_fin";
    $params['date_fin'] = $date_fin;
}

$where_clause = implode(' AND ', $where_conditions);

// Statistiques financières
try {
    // Total des paiements du mois
    $stats_query = "SELECT 
                      COUNT(*) as nb_paiements,
                      COALESCE(SUM(p.montant_total), 0) as total_montant,
                      COALESCE(SUM(CASE WHEN p.statut = 'confirmé' THEN p.montant_total ELSE 0 END), 0) as total_confirme,
                      COALESCE(SUM(CASE WHEN p.statut = 'en_attente' THEN p.montant_total ELSE 0 END), 0) as total_attente
                    FROM paiements p 
                    JOIN eleves e ON p.eleve_id = e.id 
                    WHERE e.ecole_id = :ecole_id 
                    AND MONTH(p.date_paiement) = MONTH(CURRENT_DATE()) 
                    AND YEAR(p.date_paiement) = YEAR(CURRENT_DATE())";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats = $stats_stmt->fetch();
    
    // Élèves en retard de paiement
    $retard_query = "SELECT COUNT(DISTINCT sf.eleve_id) as nb_retard
                     FROM situation_frais sf 
                     JOIN eleves e ON sf.eleve_id = e.id 
                     WHERE e.ecole_id = :ecole_id AND sf.en_retard = TRUE AND sf.reste > 0";
    
    $retard_stmt = $db->prepare($retard_query);
    $retard_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $retard_count = $retard_stmt->fetch()['nb_retard'];
    
} catch (Exception $e) {
    $stats = ['nb_paiements' => 0, 'total_montant' => 0, 'total_confirme' => 0, 'total_attente' => 0];
    $retard_count = 0;
}

// Compter le total de paiements avec filtres
$count_query = "SELECT COUNT(*) as total 
                FROM paiements p 
                JOIN eleves e ON p.eleve_id = e.id 
                WHERE $where_clause";

$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue(':' . $key, $value);
}
$count_stmt->execute();
$total_payments = $count_stmt->fetch()['total'];
$total_pages = ceil($total_payments / $limit);

// Vérifier si la colonne caissier_id existe
$check_caissier_column = "SHOW COLUMNS FROM paiements LIKE 'caissier_id'";
$check_stmt = $db->prepare($check_caissier_column);
$check_stmt->execute();
$caissier_column_exists = $check_stmt->fetch();

// Récupérer les paiements avec pagination
if ($caissier_column_exists) {
    $query = "SELECT p.*, 
                     e.matricule, e.nom, e.prenom,
                     u.nom as caissier_nom, u.prenom as caissier_prenom
              FROM paiements p 
              JOIN eleves e ON p.eleve_id = e.id 
              LEFT JOIN utilisateurs u ON p.caissier_id = u.id
              WHERE $where_clause
              ORDER BY p.date_paiement DESC
              LIMIT :limit OFFSET :offset";
} else {
    $query = "SELECT p.*, 
                     e.matricule, e.nom, e.prenom,
                     NULL as caissier_nom, NULL as caissier_prenom
              FROM paiements p 
              JOIN eleves e ON p.eleve_id = e.id 
              WHERE $where_clause
              ORDER BY p.date_paiement DESC
              LIMIT :limit OFFSET :offset";
}

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll();

$page_title = "Gestion Financière";
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
                <h1><i class="bi bi-cash-coin me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Gérer les paiements et frais scolaires</p>
            </div>
            
            <div class="topbar-actions">
                <a href="payment.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nouveau paiement
                </a>
                
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-tools me-2"></i>Outils
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="types_frais.php"><i class="bi bi-tags me-2"></i>Types de frais</a></li>
                        <li><a class="dropdown-item" href="remises.php"><i class="bi bi-percent me-2"></i>Remises & Bourses</a></li>
                        <li><a class="dropdown-item" href="echeanciers.php"><i class="bi bi-calendar-week me-2"></i>Échéanciers</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="export.php"><i class="bi bi-download me-2"></i>Exporter</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Statistiques financières -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-cash"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatAmount($stats['total_montant']); ?></h3>
                            <p>Total du mois</p>
                            <small class="text-muted"><?php echo $stats['nb_paiements']; ?> paiement(s)</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatAmount($stats['total_confirme']); ?></h3>
                            <p>Confirmés</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatAmount($stats['total_attente']); ?></h3>
                            <p>En attente</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($retard_count); ?></h3>
                            <p>Élèves en retard</p>
                            <small><a href="retards.php" class="text-decoration-none">Voir détails</a></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <i class="bi bi-cash-coin display-4 text-primary mb-3"></i>
                            <h5>Nouveau paiement</h5>
                            <p class="text-muted">Enregistrer un nouveau paiement d'élève</p>
                            <a href="payment.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Ajouter
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <i class="bi bi-search display-4 text-info mb-3"></i>
                            <h5>Recherche élève</h5>
                            <p class="text-muted">Consulter la situation financière</p>
                            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#searchStudentModal">
                                <i class="bi bi-search me-2"></i>Rechercher
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <i class="bi bi-file-earmark-text display-4 text-warning mb-3"></i>
                            <h5>Rapports</h5>
                            <p class="text-muted">Générer des rapports financiers</p>
                            <a href="reports.php" class="btn btn-warning">
                                <i class="bi bi-graph-up me-2"></i>Rapports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtres et recherche -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Rechercher</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Nom, matricule, reçu..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="">Tous</option>
                                <option value="confirmé" <?php echo ($statut_filter == 'confirmé') ? 'selected' : ''; ?>>Confirmé</option>
                                <option value="en_attente" <?php echo ($statut_filter == 'en_attente') ? 'selected' : ''; ?>>En attente</option>
                                <option value="annulé" <?php echo ($statut_filter == 'annulé') ? 'selected' : ''; ?>>Annulé</option>
                                <option value="remboursé" <?php echo ($statut_filter == 'remboursé') ? 'selected' : ''; ?>>Remboursé</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="mode" class="form-label">Mode</label>
                            <select class="form-select" id="mode" name="mode">
                                <option value="">Tous</option>
                                <option value="espèces" <?php echo ($mode_filter == 'espèces') ? 'selected' : ''; ?>>Espèces</option>
                                <option value="mobile_money" <?php echo ($mode_filter == 'mobile_money') ? 'selected' : ''; ?>>Mobile Money</option>
                                <option value="carte" <?php echo ($mode_filter == 'carte') ? 'selected' : ''; ?>>Carte</option>
                                <option value="virement" <?php echo ($mode_filter == 'virement') ? 'selected' : ''; ?>>Virement</option>
                                <option value="chèque" <?php echo ($mode_filter == 'chèque') ? 'selected' : ''; ?>>Chèque</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_debut" class="form-label">Du</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?php echo htmlspecialchars($date_debut); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_fin" class="form-label">Au</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                   value="<?php echo htmlspecialchars($date_fin); ?>">
                        </div>
                        
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Liste des paiements -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>Historique des paiements
                        <span class="badge bg-primary ms-2"><?php echo number_format($total_payments); ?></span>
                    </h5>
                    
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-success" onclick="exportToExcel()">
                            <i class="bi bi-file-earmark-excel"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="exportToPDF()">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </button>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($payments)): ?>
                        <div class="text-center p-5">
                            <i class="bi bi-cash-coin display-1 text-muted mb-3"></i>
                            <h5>Aucun paiement trouvé</h5>
                            <p class="text-muted">Aucun paiement ne correspond à vos critères de recherche.</p>
                            <a href="payment.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Enregistrer le premier paiement
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Reçu N°</th>
                                        <th>Élève</th>
                                        <th>Montant</th>
                                        <th>Mode</th>
                                        <th>Statut</th>
                                        <th>Caissier</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo formatDate($payment['date_paiement'], 'd/m/Y'); ?></strong>
                                                    <br><small class="text-muted"><?php echo formatDate($payment['date_paiement'], 'H:i'); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark font-monospace">
                                                    <?php echo htmlspecialchars($payment['numero_recu']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($payment['nom'] . ' ' . $payment['prenom']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($payment['matricule']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <strong class="text-primary">
                                                    <?php echo formatAmount($payment['montant_total'], $payment['monnaie']); ?>
                                                </strong>
                                                <?php if ($payment['remise_appliquee'] > 0): ?>
                                                    <br><small class="text-success">
                                                        Remise: <?php echo formatAmount($payment['remise_appliquee'], $payment['monnaie']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $mode_icons = [
                                                    'espèces' => 'bi-cash',
                                                    'mobile_money' => 'bi-phone',
                                                    'carte' => 'bi-credit-card',
                                                    'virement' => 'bi-bank',
                                                    'chèque' => 'bi-receipt'
                                                ];
                                                $icon = $mode_icons[$payment['mode_paiement']] ?? 'bi-question-circle';
                                                ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="<?php echo $icon; ?> me-1"></i>
                                                    <?php echo ucfirst($payment['mode_paiement']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statut_classes = [
                                                    'confirmé' => 'bg-success',
                                                    'en_attente' => 'bg-warning',
                                                    'annulé' => 'bg-danger',
                                                    'remboursé' => 'bg-info',
                                                    'partiel' => 'bg-secondary'
                                                ];
                                                $class = $statut_classes[$payment['statut']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $class; ?>">
                                                    <?php echo ucfirst($payment['statut']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($payment['caissier_nom'])): ?>
                                                    <small>
                                                        <?php echo htmlspecialchars($payment['caissier_prenom'] . ' ' . $payment['caissier_nom']); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view_payment.php?id=<?php echo $payment['id']; ?>" 
                                                       class="btn btn-outline-info" title="Voir détails">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="print_receipt.php?id=<?php echo $payment['id']; ?>" 
                                                       class="btn btn-outline-primary" target="_blank" title="Imprimer reçu">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                    <?php if (hasRole(['admin', 'direction']) && $payment['statut'] != 'annulé'): ?>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="cancelPayment(<?php echo $payment['id']; ?>)" title="Annuler">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Navigation des pages">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    
                    <div class="text-center mt-2">
                        <small class="text-muted">
                            Page <?php echo $page; ?> sur <?php echo $total_pages; ?> 
                            (<?php echo number_format($total_payments); ?> paiement<?php echo $total_payments > 1 ? 's' : ''; ?> au total)
                        </small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Modal de recherche d'élève -->
    <div class="modal fade" id="searchStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-search me-2"></i>Rechercher un élève
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="studentSearch" 
                               placeholder="Tapez le nom, prénom ou matricule de l'élève...">
                    </div>
                    <div id="searchResults"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Recherche d'élèves en temps réel
        let searchTimeout;
        document.getElementById('studentSearch').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                document.getElementById('searchResults').innerHTML = '';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchStudents(query);
            }, 300);
        });
        
        function searchStudents(query) {
            const resultsDiv = document.getElementById('searchResults');
            resultsDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>';
            
            fetch('search_students.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ query: query })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.students.length > 0) {
                    let html = '<div class="list-group">';
                    data.students.forEach(student => {
                        html += `
                            <a href="student_finance.php?id=${student.id}" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">${student.nom} ${student.prenom}</h6>
                                        <small class="text-muted">Matricule: ${student.matricule}</small>
                                        ${student.classe ? `<br><small class="text-info">${student.classe}</small>` : ''}
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-${student.reste > 0 ? 'warning' : 'success'}">
                                            ${student.reste > 0 ? 'Reste: ' + student.reste + ' FC' : 'À jour'}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        `;
                    });
                    html += '</div>';
                    resultsDiv.innerHTML = html;
                } else {
                    resultsDiv.innerHTML = '<div class="text-center text-muted p-3">Aucun élève trouvé</div>';
                }
            })
            .catch(error => {
                resultsDiv.innerHTML = '<div class="text-center text-danger p-3">Erreur de recherche</div>';
            });
        }
        
        // Annuler un paiement
        function cancelPayment(id) {
            NaklassUtils.confirmAction(
                'Êtes-vous sûr de vouloir annuler ce paiement ? Cette action ne peut pas être annulée.',
                function() {
                    fetch('cancel_payment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            NaklassUtils.showToast(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            NaklassUtils.showToast(data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        NaklassUtils.showToast('Erreur lors de l\'annulation', 'danger');
                    });
                }
            );
        }
        
        // Export vers Excel
        function exportToExcel() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.open('export.php?' + params.toString(), '_blank');
        }
        
        // Export vers PDF
        function exportToPDF() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'pdf');
            window.open('export.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>
