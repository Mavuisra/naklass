<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'caissier']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Récupérer les paramètres de filtrage
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Premier jour du mois
$date_fin = $_GET['date_fin'] ?? date('Y-m-d'); // Aujourd'hui
$classe_id = intval($_GET['classe_id'] ?? 0);
$mode_paiement = sanitize($_GET['mode_paiement'] ?? '');
$statut = sanitize($_GET['statut'] ?? '');
$type_rapport = sanitize($_GET['type_rapport'] ?? 'resume');

// Récupérer la liste des classes pour le filtre
$classes_query = "SELECT id, nom_classe, niveau, cycle FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY niveau, nom_classe";
$classes_stmt = $db->prepare($classes_query);
$classes_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Construction de la requête avec filtres
$where_conditions = ["e.ecole_id = :ecole_id"];
$params = ['ecole_id' => $_SESSION['ecole_id']];

if (!empty($date_debut)) {
    $where_conditions[] = "DATE(p.date_paiement) >= :date_debut";
    $params['date_debut'] = $date_debut;
}

if (!empty($date_fin)) {
    $where_conditions[] = "DATE(p.date_paiement) <= :date_fin";
    $params['date_fin'] = $date_fin;
}

if ($classe_id > 0) {
    $where_conditions[] = "i.classe_id = :classe_id";
    $params['classe_id'] = $classe_id;
}

if (!empty($mode_paiement)) {
    $where_conditions[] = "p.mode_paiement = :mode_paiement";
    $params['mode_paiement'] = $mode_paiement;
}

if (!empty($statut)) {
    $where_conditions[] = "p.statut = :statut";
    $params['statut'] = $statut;
}

$where_clause = implode(' AND ', $where_conditions);

// 1. STATISTIQUES GÉNÉRALES
$stats_query = "SELECT 
    COUNT(p.id) as nb_paiements,
    COALESCE(SUM(CASE WHEN p.statut = 'confirmé' THEN p.montant_total ELSE 0 END), 0) as total_confirme,
    COALESCE(SUM(CASE WHEN p.statut = 'en_attente' THEN p.montant_total ELSE 0 END), 0) as total_attente,
    COALESCE(SUM(CASE WHEN p.statut = 'annulé' THEN p.montant_total ELSE 0 END), 0) as total_annule,
    COALESCE(SUM(p.montant_total), 0) as total_general,
    COUNT(DISTINCT p.eleve_id) as nb_eleves_payeurs,
    p.monnaie
FROM paiements p
JOIN eleves e ON p.eleve_id = e.id
LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut IN ('validée', 'en_cours')
WHERE $where_clause
GROUP BY p.monnaie
ORDER BY total_confirme DESC";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute($params);
$statistiques = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. RÉPARTITION PAR MODE DE PAIEMENT
$modes_query = "SELECT 
    p.mode_paiement,
    COUNT(p.id) as nb_paiements,
    COALESCE(SUM(CASE WHEN p.statut = 'confirmé' THEN p.montant_total ELSE 0 END), 0) as total_montant,
    p.monnaie
FROM paiements p
JOIN eleves e ON p.eleve_id = e.id
LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut IN ('validée', 'en_cours')
WHERE $where_clause
GROUP BY p.mode_paiement, p.monnaie
ORDER BY total_montant DESC";

$modes_stmt = $db->prepare($modes_query);
$modes_stmt->execute($params);
$repartition_modes = $modes_stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. RÉPARTITION PAR TYPE DE FRAIS
$frais_query = "SELECT 
    tf.libelle as type_frais,
    tf.code,
    COUNT(pl.id) as nb_lignes,
    COALESCE(SUM(pl.montant_ligne), 0) as total_montant,
    COUNT(DISTINCT p.eleve_id) as nb_eleves
FROM paiement_lignes pl
JOIN paiements p ON pl.paiement_id = p.id
JOIN types_frais tf ON pl.type_frais_id = tf.id
JOIN eleves e ON p.eleve_id = e.id
LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut IN ('validée', 'en_cours')
WHERE $where_clause AND p.statut = 'confirmé'
GROUP BY tf.id, tf.libelle, tf.code
ORDER BY total_montant DESC";

$frais_stmt = $db->prepare($frais_query);
$frais_stmt->execute($params);
$repartition_frais = $frais_stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. ÉVOLUTION QUOTIDIENNE (derniers 30 jours si pas de filtre de date)
$evolution_debut = !empty($date_debut) ? $date_debut : date('Y-m-d', strtotime('-30 days'));
$evolution_fin = !empty($date_fin) ? $date_fin : date('Y-m-d');

$evolution_query = "SELECT 
    DATE(p.date_paiement) as date_paiement,
    COUNT(p.id) as nb_paiements,
    COALESCE(SUM(CASE WHEN p.statut = 'confirmé' THEN p.montant_total ELSE 0 END), 0) as total_jour
FROM paiements p
JOIN eleves e ON p.eleve_id = e.id
WHERE e.ecole_id = :ecole_id 
AND DATE(p.date_paiement) BETWEEN :date_debut AND :date_fin
GROUP BY DATE(p.date_paiement)
ORDER BY DATE(p.date_paiement) ASC";

$evolution_stmt = $db->prepare($evolution_query);
$evolution_stmt->execute([
    'ecole_id' => $_SESSION['ecole_id'],
    'date_debut' => $evolution_debut,
    'date_fin' => $evolution_fin
]);
$evolution_quotidienne = $evolution_stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. TOP 10 ÉLÈVES PAR MONTANT PAYÉ
$top_eleves_query = "SELECT 
    e.nom,
    e.prenom,
    e.matricule,
    COALESCE(c.nom_classe) as classe_nom,
    COUNT(p.id) as nb_paiements,
    COALESCE(SUM(CASE WHEN p.statut = 'confirmé' THEN p.montant_total ELSE 0 END), 0) as total_paye
FROM eleves e
JOIN paiements p ON e.id = p.eleve_id
LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut IN ('validée', 'en_cours')
LEFT JOIN classes c ON i.classe_id = c.id
WHERE $where_clause
GROUP BY e.id, e.nom, e.prenom, e.matricule, c.nom_classe
HAVING total_paye > 0
ORDER BY total_paye DESC
LIMIT 10";

$top_eleves_stmt = $db->prepare($top_eleves_query);
$top_eleves_stmt->execute($params);
$top_eleves = $top_eleves_stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. RAPPORT DÉTAILLÉ
if ($type_rapport === 'detaille') {
    $details_query = "SELECT 
        p.*,
        e.nom as eleve_nom,
        e.prenom as eleve_prenom,
        e.matricule,
        COALESCE(c.nom_classe) as classe_nom,
        u.nom as caissier_nom,
        u.prenom as caissier_prenom
    FROM paiements p
    JOIN eleves e ON p.eleve_id = e.id
    LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut IN ('validée', 'en_cours')
    LEFT JOIN classes c ON i.classe_id = c.id
    LEFT JOIN utilisateurs u ON p.caissier_id = u.id
    WHERE $where_clause
    ORDER BY p.date_paiement DESC";
    
    $details_stmt = $db->prepare($details_query);
    $details_stmt->execute($params);
    $paiements_detailles = $details_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculer quelques métriques supplémentaires
$total_global = array_sum(array_column($statistiques, 'total_confirme'));
$nb_total_paiements = array_sum(array_column($statistiques, 'nb_paiements'));

// Préparer les données pour les graphiques
$evolution_dates = array_column($evolution_quotidienne, 'date_paiement');
$evolution_montants = array_column($evolution_quotidienne, 'total_jour');

$page_title = "Rapports Financiers";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Naklass</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- CSS personnalisés -->
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/naklass-theme.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- En-tête -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-graph-up me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
                <p class="text-muted">Analyse et rapports des transactions financières</p>
            </div>
            
            <div class="topbar-actions">
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Imprimer
                </button>
                <button type="button" class="btn btn-success" onclick="exportToCSV()">
                    <i class="bi bi-download me-1"></i>Exporter CSV
                </button>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Filtres -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2"></i>Filtres et Paramètres
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="date_debut" class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?php echo htmlspecialchars($date_debut); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_fin" class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                   value="<?php echo htmlspecialchars($date_fin); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="classe_id" class="form-label">Classe</label>
                            <select class="form-select" id="classe_id" name="classe_id">
                                <option value="">Toutes les classes</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                            <?php echo $classe_id == $classe['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(($classe['nom_classe'] ?? '') . ' - ' . ($classe['niveau'] ?? $classe['cycle'] ?? '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="mode_paiement" class="form-label">Mode</label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement">
                                <option value="">Tous les modes</option>
                                <option value="espèces" <?php echo $mode_paiement === 'espèces' ? 'selected' : ''; ?>>Espèces</option>
                                <option value="mobile_money" <?php echo $mode_paiement === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                                <option value="carte" <?php echo $mode_paiement === 'carte' ? 'selected' : ''; ?>>Carte</option>
                                <option value="virement" <?php echo $mode_paiement === 'virement' ? 'selected' : ''; ?>>Virement</option>
                                <option value="chèque" <?php echo $mode_paiement === 'chèque' ? 'selected' : ''; ?>>Chèque</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="">Tous les statuts</option>
                                <option value="confirmé" <?php echo $statut === 'confirmé' ? 'selected' : ''; ?>>Confirmé</option>
                                <option value="en_attente" <?php echo $statut === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="annulé" <?php echo $statut === 'annulé' ? 'selected' : ''; ?>>Annulé</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Filtrer
                            </button>
                            <a href="reports.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Réinitialiser
                            </a>
                            <div class="btn-group ms-3">
                                <input type="radio" class="btn-check" name="type_rapport" id="resume" value="resume" 
                                       <?php echo $type_rapport === 'resume' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-info" for="resume">Résumé</label>
                                
                                <input type="radio" class="btn-check" name="type_rapport" id="detaille" value="detaille"
                                       <?php echo $type_rapport === 'detaille' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-info" for="detaille">Détaillé</label>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Résumé des statistiques -->
            <div class="row g-4 mb-4">
                <?php if (!empty($statistiques)): ?>
                    <?php foreach ($statistiques as $stat): ?>
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon bg-primary">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo formatAmount($stat['total_confirme'], $stat['monnaie']); ?></h3>
                                    <p>Total confirmé (<?php echo $stat['monnaie']; ?>)</p>
                                    <small class="text-muted"><?php echo $stat['nb_paiements']; ?> paiement(s)</small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucune donnée trouvée pour la période sélectionnée.
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo array_sum(array_column($statistiques, 'nb_eleves_payeurs')); ?></h3>
                            <p>Élèves payeurs</p>
                            <small class="text-muted">Élèves uniques</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $nb_total_paiements; ?></h3>
                            <p>Transactions totales</p>
                            <small class="text-muted">Toutes monnaies</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($type_rapport === 'resume'): ?>
                <!-- Graphiques et analyses -->
                <div class="row mb-4">
                    <!-- Évolution quotidienne -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-graph-up me-2"></i>Évolution des paiements
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="evolutionChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Répartition par mode -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-pie-chart me-2"></i>Modes de paiement
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="modesChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableaux de répartition -->
                <div class="row mb-4">
                    <!-- Répartition par type de frais -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-tags me-2"></i>Répartition par type de frais
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($repartition_frais)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Type de frais</th>
                                                    <th class="text-center">Nb. lignes</th>
                                                    <th class="text-end">Montant total</th>
                                                    <th class="text-center">Élèves</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($repartition_frais as $frais): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($frais['type_frais']); ?></strong>
                                                            <?php if (!empty($frais['code'])): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($frais['code']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-secondary"><?php echo $frais['nb_lignes']; ?></span>
                                                        </td>
                                                        <td class="text-end">
                                                            <strong><?php echo formatAmount($frais['total_montant'], 'CDF'); ?></strong>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php echo $frais['nb_eleves']; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="bi bi-inbox fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">Aucun frais payé</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Top 10 élèves -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-trophy me-2"></i>Top 10 élèves payeurs
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_eleves)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Élève</th>
                                                    <th>Classe</th>
                                                    <th class="text-end">Total payé</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_eleves as $index => $eleve): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                                                <?php echo $index + 1; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($eleve['matricule']); ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-light text-dark">
                                                                <?php echo htmlspecialchars($eleve['classe_nom'] ?? 'N/A'); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <strong><?php echo formatAmount($eleve['total_paye'], 'CDF'); ?></strong>
                                                            <br><small class="text-muted"><?php echo $eleve['nb_paiements']; ?> paiement(s)</small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="bi bi-person-x fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">Aucun élève payeur</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($type_rapport === 'detaille' && isset($paiements_detailles)): ?>
                <!-- Rapport détaillé -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul me-2"></i>Rapport détaillé des paiements
                        </h5>
                        <span class="badge bg-primary"><?php echo count($paiements_detailles); ?> paiement(s)</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($paiements_detailles)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="detailsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Reçu N°</th>
                                            <th>Élève</th>
                                            <th>Classe</th>
                                            <th>Mode</th>
                                            <th>Montant</th>
                                            <th>Statut</th>
                                            <th>Caissier</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($paiements_detailles as $paiement): 
                                            $numero_recu = $paiement['recu_numero'] ?? $paiement['numero_recu'] ?? '';
                                            $mode = $paiement['mode'] ?? $paiement['mode_paiement'] ?? '';
                                        ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo formatDate($paiement['date_paiement'], 'd/m/Y'); ?></strong>
                                                        <br><small class="text-muted"><?php echo formatDate($paiement['date_paiement'], 'H:i'); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark font-monospace">
                                                        <?php echo htmlspecialchars($numero_recu); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($paiement['eleve_nom'] . ' ' . $paiement['eleve_prenom']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($paiement['matricule']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($paiement['classe_nom'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $mode_icons = [
                                                        'espèces' => 'bi-cash-coin text-success',
                                                        'mobile_money' => 'bi-phone text-primary',
                                                        'carte' => 'bi-credit-card text-info',
                                                        'virement' => 'bi-bank text-warning',
                                                        'chèque' => 'bi-journal-check text-secondary'
                                                    ];
                                                    $icon_class = $mode_icons[$mode] ?? 'bi-question-circle text-muted';
                                                    ?>
                                                    <i class="bi <?php echo $icon_class; ?> me-1"></i>
                                                    <?php echo ucfirst($mode); ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo formatAmount($paiement['montant_total'], $paiement['monnaie']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = [
                                                        'confirmé' => 'bg-success',
                                                        'en_attente' => 'bg-warning text-dark',
                                                        'annulé' => 'bg-danger'
                                                    ];
                                                    ?>
                                                    <span class="badge <?php echo $status_class[$paiement['statut']] ?? 'bg-secondary'; ?>">
                                                        <?php echo ucfirst($paiement['statut']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($paiement['caissier_nom'])): ?>
                                                        <?php echo htmlspecialchars($paiement['caissier_nom'] . ' ' . $paiement['caissier_prenom']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <h5 class="text-muted mt-3">Aucun paiement trouvé</h5>
                                <p class="text-muted">Essayez de modifier les filtres de recherche.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div> <!-- End content-area -->
    </div> <!-- End main-content -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JS personnalisés -->
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        // Données pour les graphiques
        const evolutionDates = <?php echo json_encode($evolution_dates); ?>;
        const evolutionMontants = <?php echo json_encode($evolution_montants); ?>;
        const modesData = <?php echo json_encode($repartition_modes); ?>;

        // Graphique d'évolution
        const ctxEvolution = document.getElementById('evolutionChart');
        if (ctxEvolution) {
            new Chart(ctxEvolution, {
                type: 'line',
                data: {
                    labels: evolutionDates,
                    datasets: [{
                        label: 'Montant quotidien',
                        data: evolutionMontants,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('fr-FR').format(value) + ' FC';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Montant: ' + new Intl.NumberFormat('fr-FR').format(context.raw) + ' FC';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graphique des modes de paiement
        const ctxModes = document.getElementById('modesChart');
        if (ctxModes && modesData.length > 0) {
            const modesLabels = modesData.map(item => item.mode.charAt(0).toUpperCase() + item.mode.slice(1));
            const modesMontants = modesData.map(item => parseFloat(item.total_montant));
            
            new Chart(ctxModes, {
                type: 'doughnut',
                data: {
                    labels: modesLabels,
                    datasets: [{
                        data: modesMontants,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF',
                            '#FF9F40'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + new Intl.NumberFormat('fr-FR').format(context.raw) + ' FC';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Fonction d'export CSV
        function exportToCSV() {
            <?php if ($type_rapport === 'detaille' && isset($paiements_detailles)): ?>
                const table = document.getElementById('detailsTable');
                let csv = [];
                
                // En-têtes
                const headers = [];
                table.querySelectorAll('thead th').forEach(th => {
                    headers.push('"' + th.textContent.trim() + '"');
                });
                csv.push(headers.join(','));
                
                // Données
                table.querySelectorAll('tbody tr').forEach(tr => {
                    const row = [];
                    tr.querySelectorAll('td').forEach(td => {
                        row.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
                    });
                    csv.push(row.join(','));
                });
                
                // Télécharger
                const csvContent = csv.join('\n');
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'rapport_financier_' + new Date().toISOString().slice(0, 10) + '.csv';
                link.click();
            <?php else: ?>
                alert('L\'export CSV n\'est disponible que pour le rapport détaillé.');
            <?php endif; ?>
        }

        // Améliorer l'affichage des filtres
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const radioButtons = form.querySelectorAll('input[name="type_rapport"]');
            
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    form.submit();
                });
            });
        });
    </script>

<style>
.main-content {
    margin-left: 250px;
    padding: 20px;
    min-height: 100vh;
    background-color: #f8f9fa;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
    display: flex;
    align-items: center;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    font-size: 24px;
    color: white;
}

.stat-content h3 {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
}

.stat-content p {
    margin: 0 0 4px 0;
    font-size: 14px;
    font-weight: 600;
    color: #7f8c8d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 12px 12px 0 0 !important;
    padding: 20px 24px;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
}

.topbar {
    background: white;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.topbar-title h1 {
    margin: 0 0 4px 0;
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
}

.topbar-title p {
    margin: 0;
    color: #7f8c8d;
}

.breadcrumb {
    background: none;
    padding: 0;
    margin-bottom: 0;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 8px 16px;
}

.badge {
    border-radius: 6px;
}

.table {
    border-radius: 8px;
    overflow: hidden;
}

@media print {
    .topbar-actions, .sidebar, .no-print {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
    }
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 10px;
    }
    
    .topbar {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .topbar-actions {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 991px) {
    .main-content {
        margin-left: 0;
    }
}
</style>

</body>
</html>
