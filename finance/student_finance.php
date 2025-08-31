<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'caissier']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Récupérer l'ID de l'élève
$student_id = intval($_GET['id'] ?? 0);

if (!$student_id) {
    setFlashMessage('error', 'ID d\'élève invalide.');
    redirect('../students/index.php');
}

// Récupérer les informations de l'élève
$student_query = "SELECT 
    e.*,
    c.nom_classe as classe_nom,
    c.niveau as classe_niveau,
    i.annee_scolaire,
    i.date_inscription
FROM eleves e
LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut_inscription IN ('validée', 'en_cours')
LEFT JOIN classes c ON i.classe_id = c.id
WHERE e.id = :student_id 
AND e.ecole_id = :ecole_id 
AND e.statut = 'actif'";

$student_stmt = $db->prepare($student_query);
$student_stmt->execute([
    'student_id' => $student_id,
    'ecole_id' => $_SESSION['ecole_id']
]);

$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    setFlashMessage('error', 'Élève introuvable.');
    redirect('../students/index.php');
}

// Récupérer l'historique des paiements
$payments_query = "SELECT 
    p.*,
    u.nom as caissier_nom,
    u.prenom as caissier_prenom
FROM paiements p
LEFT JOIN utilisateurs u ON p.caissier_id = u.id
WHERE p.eleve_id = :student_id 
AND p.statut_record = 'actif'
ORDER BY p.date_paiement DESC";

$payments_stmt = $db->prepare($payments_query);
$payments_stmt->execute(['student_id' => $student_id]);
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails des paiements avec les types de frais
$payment_details_query = "SELECT 
    pl.*,
    tf.libelle as type_frais_nom,
    tf.code as type_frais_code,
    p.date_paiement,
    p.statut as payment_statut
FROM paiement_lignes pl
JOIN paiements p ON pl.paiement_id = p.id
JOIN types_frais tf ON pl.type_frais_id = tf.id
WHERE p.eleve_id = :student_id 
AND p.statut_record = 'actif'
ORDER BY p.date_paiement DESC, tf.libelle";

$payment_details_stmt = $db->prepare($payment_details_query);
$payment_details_stmt->execute(['student_id' => $student_id]);
$payment_details = $payment_details_stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les types de frais de l'école pour calculer les frais dus
$fees_query = "SELECT 
    tf.*
FROM types_frais tf
WHERE tf.ecole_id = :ecole_id 
AND tf.statut = 'actif'
ORDER BY tf.libelle";

$fees_stmt = $db->prepare($fees_query);
$fees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$all_fees = $fees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques financières
$total_paye = 0;
$paiements_par_statut = [
    'confirmé' => 0,
    'en_attente' => 0,
    'annulé' => 0
];

foreach ($payments as $payment) {
    $mode_key = $payment['mode'] ?? $payment['mode_paiement'] ?? 'inconnu';
    $statut = $payment['statut'];
    
    if ($statut === 'confirmé') {
        $total_paye += $payment['montant_total'];
    }
    
    if (isset($paiements_par_statut[$statut])) {
        $paiements_par_statut[$statut] += $payment['montant_total'];
    }
}

// Calculer les totaux par type de frais
$frais_payes = [];
foreach ($payment_details as $detail) {
    $type_id = $detail['type_frais_id'];
    $montant = $detail['montant'] ?? $detail['montant_ligne'] ?? 0;
    
    if ($detail['payment_statut'] === 'confirmé') {
        if (!isset($frais_payes[$type_id])) {
            $frais_payes[$type_id] = [
                'nom' => $detail['type_frais_nom'],
                'code' => $detail['type_frais_code'],
                'total_paye' => 0,
                'nb_paiements' => 0
            ];
        }
        $frais_payes[$type_id]['total_paye'] += $montant;
        $frais_payes[$type_id]['nb_paiements']++;
    }
}

// Déterminer les noms de colonnes en fonction du schéma
function getPaymentColumns($payment) {
    return [
        'mode' => $payment['mode'] ?? $payment['mode_paiement'] ?? '',
        'numero_recu' => $payment['recu_numero'] ?? $payment['numero_recu'] ?? '',
        'reference' => $payment['reference_externe'] ?? $payment['reference_transaction'] ?? ''
    ];
}

$page_title = "Finances - " . $student['nom'] . ' ' . $student['prenom'];
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
                <h1><i class="bi bi-person-badge me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
                <p class="text-muted">Tableau de bord financier de l'élève</p>
            </div>
            
            <div class="topbar-actions">
                <a href="../students/view.php?id=<?php echo $student_id; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Retour à la fiche
                </a>
                <a href="payment.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Nouveau paiement
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="../auth/dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item"><a href="../students/index.php">Élèves</a></li>
                    <li class="breadcrumb-item"><a href="../students/view.php?id=<?php echo $student_id; ?>"><?php echo htmlspecialchars($student['nom'] . ' ' . $student['prenom']); ?></a></li>
                    <li class="breadcrumb-item active">Finances</li>
                </ol>
            </nav>

    <!-- Informations de l'élève -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <?php if (!empty($student['photo_url']) && file_exists('../' . $student['photo_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($student['photo_url']); ?>" 
                                     alt="Photo élève" class="img-thumbnail" style="max-width: 100px; max-height: 120px;">
                            <?php else: ?>
                                <div class="bg-light border rounded d-flex align-items-center justify-content-center"
                                     style="width: 100px; height: 120px; margin: 0 auto;">
                                    <i class="bi bi-person fs-1 text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-10">
                            <div class="row">
                                <div class="col-md-6">
                                    <h4 class="mb-2"><?php echo htmlspecialchars($student['nom'] . ' ' . $student['prenom']); ?></h4>
                                    <p class="mb-1"><strong>Matricule:</strong> <span class="font-monospace"><?php echo htmlspecialchars($student['matricule']); ?></span></p>
                                    <?php if (!empty($student['classe_nom'])): ?>
                                        <p class="mb-1"><strong>Classe:</strong> <?php echo htmlspecialchars($student['classe_nom']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($student['annee_scolaire'])): ?>
                                        <p class="mb-0"><strong>Année scolaire:</strong> <?php echo htmlspecialchars($student['annee_scolaire']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php if (!empty($student['date_naissance'])): ?>
                                        <p class="mb-1"><strong>Date de naissance:</strong> <?php echo formatDate($student['date_naissance'], 'd/m/Y'); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($student['date_inscription'])): ?>
                                        <p class="mb-1"><strong>Date d'inscription:</strong> <?php echo formatDate($student['date_inscription'], 'd/m/Y'); ?></p>
                                    <?php endif; ?>
                                    <p class="mb-0">
                                        <strong>Statut:</strong> 
                                        <span class="badge bg-success">Actif</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Résumé financier -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total payé</h6>
                            <h4 class="mb-0"><?php echo formatAmount($total_paye, 'CDF'); ?></h4>
                        </div>
                        <div class="ms-3">
                            <i class="bi bi-cash-coin fs-1 opacity-75"></i>
                        </div>
                    </div>
                    <small class="opacity-75">Paiements confirmés</small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Paiements confirmés</h6>
                            <h4 class="mb-0"><?php echo formatAmount($paiements_par_statut['confirmé'], 'CDF'); ?></h4>
                        </div>
                        <div class="ms-3">
                            <i class="bi bi-check-circle fs-1 opacity-75"></i>
                        </div>
                    </div>
                    <small class="opacity-75"><?php echo count(array_filter($payments, fn($p) => $p['statut'] === 'confirmé')); ?> transaction(s)</small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">En attente</h6>
                            <h4 class="mb-0"><?php echo formatAmount($paiements_par_statut['en_attente'], 'CDF'); ?></h4>
                        </div>
                        <div class="ms-3">
                            <i class="bi bi-clock fs-1 opacity-75"></i>
                        </div>
                    </div>
                    <small class="opacity-75"><?php echo count(array_filter($payments, fn($p) => $p['statut'] === 'en_attente')); ?> transaction(s)</small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Nb. paiements</h6>
                            <h4 class="mb-0"><?php echo count($payments); ?></h4>
                        </div>
                        <div class="ms-3">
                            <i class="bi bi-receipt fs-1 opacity-75"></i>
                        </div>
                    </div>
                    <small class="opacity-75">Total transactions</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Colonne principale - Historique des paiements -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>Historique des paiements
                    </h5>
                    <span class="badge bg-primary"><?php echo count($payments); ?> paiement(s)</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($payments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Reçu N°</th>
                                        <th>Mode</th>
                                        <th>Montant</th>
                                        <th>Statut</th>
                                        <th>Caissier</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): 
                                        $cols = getPaymentColumns($payment);
                                        $status_class = [
                                            'confirmé' => 'bg-success',
                                            'en_attente' => 'bg-warning text-dark',
                                            'annulé' => 'bg-danger',
                                            'remboursé' => 'bg-info'
                                        ];
                                    ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo formatDate($payment['date_paiement'], 'd/m/Y'); ?></strong>
                                                    <br><small class="text-muted"><?php echo formatDate($payment['date_paiement'], 'H:i'); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark font-monospace">
                                                    <?php echo htmlspecialchars($cols['numero_recu']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $modes = [
                                                    'espèces' => '<i class="bi bi-cash-coin text-success"></i> Espèces',
                                                    'mobile_money' => '<i class="bi bi-phone text-primary"></i> Mobile Money',
                                                    'carte' => '<i class="bi bi-credit-card text-info"></i> Carte',
                                                    'virement' => '<i class="bi bi-bank text-warning"></i> Virement',
                                                    'chèque' => '<i class="bi bi-journal-check text-secondary"></i> Chèque'
                                                ];
                                                echo $modes[$cols['mode']] ?? ucfirst($cols['mode']);
                                                ?>
                                            </td>
                                            <td>
                                                <strong><?php echo formatAmount($payment['montant_total'], $payment['monnaie']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $status_class[$payment['statut']] ?? 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($payment['statut']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($payment['caissier_nom'])): ?>
                                                    <?php echo htmlspecialchars($payment['caissier_nom'] . ' ' . $payment['caissier_prenom']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view_payment.php?id=<?php echo $payment['id']; ?>" 
                                                       class="btn btn-outline-info" title="Voir détails">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="print_receipt.php?id=<?php echo $payment['id']; ?>" 
                                                       class="btn btn-outline-primary" target="_blank" title="Imprimer">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <h5 class="text-muted mt-3">Aucun paiement enregistré</h5>
                            <p class="text-muted">Cet élève n'a encore effectué aucun paiement.</p>
                            <a href="payment.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>Enregistrer un paiement
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colonne latérale - Détails et statistiques -->
        <div class="col-lg-4">
            <!-- Répartition par type de frais -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pie-chart me-2"></i>Frais payés par type
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($frais_payes)): ?>
                        <?php foreach ($frais_payes as $frais): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo htmlspecialchars($frais['nom']); ?></div>
                                    <small class="text-muted">
                                        <?php echo $frais['nb_paiements']; ?> paiement(s)
                                        <?php if (!empty($frais['code'])): ?>
                                            - <?php echo htmlspecialchars($frais['code']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-primary">
                                        <?php echo formatAmount($frais['total_paye'], 'CDF'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-graph-down text-muted fs-1"></i>
                            <p class="text-muted mt-2 mb-0">Aucun frais payé</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning me-2"></i>Actions rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="payment.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Nouveau paiement
                        </a>
                        <a href="../students/view.php?id=<?php echo $student_id; ?>" class="btn btn-outline-info">
                            <i class="bi bi-person me-2"></i>Voir fiche complète
                        </a>
                        <a href="../students/edit.php?id=<?php echo $student_id; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-pencil me-2"></i>Modifier élève
                        </a>
                        <a href="index.php?search=<?php echo urlencode($student['matricule']); ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-search me-2"></i>Rechercher paiements
                        </a>
                    </div>
                </div>
            </div>

            <!-- Résumé par monnaie -->
            <?php
            $totaux_monnaie = [];
            foreach ($payments as $payment) {
                if ($payment['statut'] === 'confirmé') {
                    $monnaie = $payment['monnaie'];
                    if (!isset($totaux_monnaie[$monnaie])) {
                        $totaux_monnaie[$monnaie] = 0;
                    }
                    $totaux_monnaie[$monnaie] += $payment['montant_total'];
                }
            }
            ?>
            <?php if (!empty($totaux_monnaie)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-currency-exchange me-2"></i>Totaux par monnaie
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($totaux_monnaie as $monnaie => $total): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-primary"><?php echo $monnaie; ?></span>
                            <span class="fw-bold"><?php echo formatAmount($total, $monnaie); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Informations de contact (si disponibles) -->
            <?php if (!empty($student['telephone']) || !empty($student['email'])): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-telephone me-2"></i>Contact
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($student['telephone'])): ?>
                        <p class="mb-1">
                            <i class="bi bi-phone me-2"></i>
                            <a href="tel:<?php echo htmlspecialchars($student['telephone']); ?>">
                                <?php echo htmlspecialchars($student['telephone']); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($student['email'])): ?>
                        <p class="mb-0">
                            <i class="bi bi-envelope me-2"></i>
                            <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>">
                                <?php echo htmlspecialchars($student['email']); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

        </div> <!-- End content-area -->
    </div> <!-- End main-content -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JS personnalisés -->
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        // Script pour améliorer l'UX
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter des tooltips aux boutons d'action
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Animation au survol des cartes de résumé
            const summaryCards = document.querySelectorAll('.card.bg-primary, .card.bg-success, .card.bg-warning, .card.bg-info');
            summaryCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.transition = 'transform 0.2s ease';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
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

.card-title {
    font-size: 1.1rem;
    font-weight: 600;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.badge.font-monospace {
    font-family: 'Courier New', monospace;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.btn {
    border-radius: 8px;
    font-weight: 500;
}

.badge {
    border-radius: 6px;
}

.table {
    border-radius: 8px;
    overflow: hidden;
}

.breadcrumb {
    background: none;
    padding: 0;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: "›";
    color: #6c757d;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 10px;
    }
    
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn {
        margin-bottom: 0.25rem;
    }
    
    .d-flex.justify-content-between.align-items-center {
        flex-direction: column;
        align-items: stretch !important;
        gap: 1rem;
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
