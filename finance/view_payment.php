<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'caissier']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Récupérer l'ID du paiement
$payment_id = intval($_GET['id'] ?? 0);

if (!$payment_id) {
    setFlashMessage('error', 'ID de paiement invalide.');
    redirect('index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cancel_payment' && hasRole(['admin', 'direction'])) {
        try {
            $cancel_query = "UPDATE paiements SET 
                statut = 'annulé',
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP,
                notes_internes = CONCAT(IFNULL(notes_internes, ''), '\n[', NOW(), '] Paiement annulé par ', :user_name)
                WHERE id = :payment_id 
                AND eleve_id IN (SELECT id FROM eleves WHERE ecole_id = :ecole_id)";
            
            $cancel_stmt = $db->prepare($cancel_query);
            $cancel_stmt->execute([
                'payment_id' => $payment_id,
                'ecole_id' => $_SESSION['ecole_id'],
                'updated_by' => $_SESSION['user_id'],
                'user_name' => $_SESSION['nom'] . ' ' . $_SESSION['prenom']
            ]);
            
            if ($cancel_stmt->rowCount() > 0) {
                logUserAction('CANCEL_PAYMENT', "Paiement annulé: ID $payment_id");
                setFlashMessage('success', 'Paiement annulé avec succès.');
            } else {
                setFlashMessage('error', 'Impossible d\'annuler ce paiement.');
            }
        } catch (Exception $e) {
            error_log("Erreur lors de l'annulation du paiement: " . $e->getMessage());
            setFlashMessage('error', 'Erreur lors de l\'annulation du paiement.');
        }
        
        redirect("view_payment.php?id=$payment_id");
    }
}

// Récupérer les données complètes du paiement
$payment_query = "SELECT 
    p.*,
    e.nom as eleve_nom,
    e.prenom as eleve_prenom,
    e.matricule,
    e.date_naissance,
    ec.nom_ecole as ecole_nom,
    ec.adresse as ecole_adresse,
    ec.ville as ecole_ville,
    ec.province as ecole_province,
    ec.pays as ecole_pays,
    ec.telephone as ecole_telephone,
    ec.email as ecole_email,
    ec.logo_path as logo_url,
    u.nom as caissier_nom,
    u.prenom as caissier_prenom,
    u.email as caissier_email,
    uc.nom as created_by_nom,
    uc.prenom as created_by_prenom,
    uu.nom as updated_by_nom,
    uu.prenom as updated_by_prenom,
    c.nom_classe as classe_nom,
    c.niveau as classe_niveau
FROM paiements p
JOIN eleves e ON p.eleve_id = e.id
JOIN ecoles ec ON e.ecole_id = ec.id
LEFT JOIN utilisateurs u ON p.caissier_id = u.id
LEFT JOIN utilisateurs uc ON p.created_by = uc.id
LEFT JOIN utilisateurs uu ON p.updated_by = uu.id
LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut_inscription IN ('validée', 'en_cours')
LEFT JOIN classes c ON i.classe_id = c.id
WHERE p.id = :payment_id 
AND e.ecole_id = :ecole_id
AND p.statut_record = 'actif'";

$payment_stmt = $db->prepare($payment_query);
$payment_stmt->execute([
    'payment_id' => $payment_id,
    'ecole_id' => $_SESSION['ecole_id']
]);

$payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    setFlashMessage('error', 'Paiement introuvable.');
    redirect('index.php');
}

// Récupérer les lignes de paiement
$lignes_query = "SELECT 
    pl.*,
    tf.libelle as type_frais_nom,
    tf.code_frais as type_frais_code,
    tf.description as type_frais_description,
    tf.montant_defaut as montant_standard
FROM paiement_lignes pl
JOIN types_frais tf ON pl.type_frais_id = tf.id
WHERE pl.paiement_id = :payment_id
ORDER BY tf.libelle";

$lignes_stmt = $db->prepare($lignes_query);
$lignes_stmt->execute(['payment_id' => $payment_id]);
$lignes_paiement = $lignes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Déterminer les noms de colonnes en fonction du schéma
$mode_paiement = $payment['mode'] ?? $payment['mode_paiement'] ?? '';
$numero_recu = $payment['recu_numero'] ?? $payment['numero_recu'] ?? '';
$reference = $payment['reference_externe'] ?? $payment['reference_transaction'] ?? '';

// Récupérer l'historique des modifications si disponible
$history_query = "SELECT 
    p.created_at as date_action,
    'Création' as action,
    CONCAT(uc.nom, ' ', uc.prenom) as utilisateur
FROM paiements p
LEFT JOIN utilisateurs uc ON p.created_by = uc.id
WHERE p.id = ?

UNION ALL

SELECT 
    p.updated_at as date_action,
    'Modification' as action,
    CONCAT(uu.nom, ' ', uu.prenom) as utilisateur
FROM paiements p
LEFT JOIN utilisateurs uu ON p.updated_by = uu.id
WHERE p.id = ? AND p.updated_at != p.created_at

ORDER BY date_action DESC";

$history_stmt = $db->prepare($history_query);
$history_stmt->execute([$payment_id, $payment_id]);
$payment_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer des statistiques
$total_lignes = array_sum(array_column($lignes_paiement, 'montant_ligne'));
$nb_lignes = count($lignes_paiement);

$page_title = "Détails du paiement - " . $numero_recu;
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
    
    <style>
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .badge.fs-6 {
            font-size: 0.875rem !important;
        }
        
        .table-borderless th {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .table-borderless td {
            color: #212529;
        }
        
        .timeline {
            position: relative;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 32px;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline .d-flex:last-child::before {
            display: none;
        }
        
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item i {
            font-size: 1.5rem;
            margin-top: 0.25rem;
        }
        
        .notification-item div {
            flex: 1;
        }
        
        .notification-item strong {
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .notification-item p {
            margin-bottom: 0.25rem;
        }
        
        .notification-item small {
            color: #6c757d;
        }
        
        /* Styles pour l'affichage à l'écran vs impression */
        .screen-only {
            display: block;
        }
        
        .print-only {
            display: none;
        }
        
        /* Styles pour l'impression */
        @media print {
            /* Masquer les éléments de navigation et interface */
            .sidebar,
            .topbar,
            .btn-group,
            .modal,
            .screen-only {
                display: none !important;
            }
            
            /* Afficher les éléments d'impression */
            .print-only {
                display: block !important;
            }
            
            /* Styles d'impression */
            body {
                background: white !important;
                color: black !important;
                font-size: 12pt !important;
                line-height: 1.4 !important;
            }
            
            .container-fluid {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                margin-bottom: 20px !important;
                page-break-inside: avoid !important;
            }
            
            .card-header {
                background: #f8f9fa !important;
                border-bottom: 1px solid #ddd !important;
            }
            
            .table {
                font-size: 10pt !important;
            }
            
            .table th,
            .table td {
                padding: 4px 8px !important;
            }
            
            /* Masquer les badges colorés à l'impression */
            .badge {
                background: #f8f9fa !important;
                color: #495057 !important;
                border: 1px solid #ddd !important;
            }
            
            /* Forcer les sauts de page */
            .page-break {
                page-break-before: always !important;
            }
            
            /* Masquer les icônes Bootstrap à l'impression */
            .bi {
                display: none !important;
            }
        }
        
        @media (max-width: 768px) {
            .d-flex.justify-content-between.align-items-center .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .d-flex.justify-content-between.align-items-center .btn-group .btn {
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation latérale -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="bi bi-mortarboard-fill"></i>
                <span>Naklass</span>
            </div>
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="bi bi-person-circle"></i>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']; ?></div>
                <div class="user-role"><?php echo ROLES[$_SESSION['user_role']] ?? $_SESSION['user_role']; ?></div>
                <div class="user-school"><?php echo $_SESSION['ecole_nom']; ?></div>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="../auth/dashboard.php" class="menu-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            
            <?php if (hasRole(['admin', 'direction', 'secretaire'])): ?>
            <li class="menu-item">
                <a href="../students/" class="menu-link">
                    <i class="bi bi-people"></i>
                    <span>Élèves</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../classes/" class="menu-link">
                    <i class="bi bi-building"></i>
                    <span>Classes</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
            <li class="menu-item">
                <a href="../teachers/" class="menu-link">
                    <i class="bi bi-person-badge"></i>
                    <span>Enseignants</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction', 'caissier'])): ?>
            <li class="menu-item active">
                <a href="../finance/" class="menu-link">
                    <i class="bi bi-cash-coin"></i>
                    <span>Finances</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
            <li class="menu-item">
                <a href="../grades/" class="menu-link">
                    <i class="bi bi-journal-text"></i>
                    <span>Notes & Bulletins</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction'])): ?>
            <li class="menu-item">
                <a href="../reports/" class="menu-link">
                    <i class="bi bi-graph-up"></i>
                    <span>Rapports</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="../settings/" class="menu-link">
                    <i class="bi bi-gear"></i>
                    <span>Paramètres</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </nav>
    
    <!-- Contenu principal -->
    <main class="main-content">
        <!-- Barre supérieure -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-receipt-cutoff me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Détails du paiement et informations complètes</p>
            </div>
            
            <div class="topbar-actions">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                    <i class="bi bi-bell"></i>
                    <span class="badge bg-danger">3</span>
                </button>
                
                <div class="dropdown">
                    <button class="btn btn-link p-0" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-4"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile/"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                        <li><a class="dropdown-item" href="../settings/"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Contenu de la page -->
        <div class="content-area">

<div class="container-fluid">
    <!-- En-tête de la page -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-receipt-cutoff me-2"></i>
                        Détails du paiement
                    </h1>
                    <nav aria-label="breadcrumb" class="screen-only">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../auth/dashboard.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Finances</a></li>
                            <li class="breadcrumb-item active">Détails paiement</li>
                        </ol>
                    </nav>
                </div>
                
                <!-- Actions -->
                <div class="btn-group screen-only">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Retour
                    </a>
                    <a href="print_receipt.php?id=<?php echo $payment_id; ?>" 
                       class="btn btn-primary" target="_blank">
                        <i class="bi bi-printer me-1"></i>Imprimer
                    </a>
                    <?php if (hasRole(['admin', 'direction']) && $payment['statut'] != 'annulé'): ?>
                        <button type="button" class="btn btn-outline-danger" 
                                data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="bi bi-x-circle me-1"></i>Annuler
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- En-tête d'impression (visible uniquement à l'impression) -->
    <div class="print-only">
    <div class="row mb-4">
            <div class="col-12 text-center">
                <h2 class="mb-2">REÇU DE PAIEMENT</h2>
                <h4 class="text-muted mb-3"><?php echo htmlspecialchars($payment['ecole_nom']); ?></h4>
                <hr class="my-3">
            </div>
        </div>
    </div>

    <!-- Badge de statut global (visible uniquement à l'écran) -->
    <div class="row mb-4 screen-only">
        <div class="col-12">
            <?php
            $status_class = [
                'confirmé' => 'bg-success',
                'en_attente' => 'bg-warning text-dark',
                'annulé' => 'bg-danger',
                'remboursé' => 'bg-info',
                'partiel' => 'bg-secondary'
            ];
            $status_text = [
                'confirmé' => 'Confirmé',
                'en_attente' => 'En attente',
                'annulé' => 'Annulé',
                'remboursé' => 'Remboursé',
                'partiel' => 'Partiel'
            ];
            $status_icon = [
                'confirmé' => 'check-circle',
                'en_attente' => 'clock',
                'annulé' => 'x-circle',
                'remboursé' => 'arrow-clockwise',
                'partiel' => 'partiel'
            ];
            ?>
            <div class="alert alert-primary d-flex align-items-center">
                <div class="me-3">
                    <span class="badge fs-6 <?php echo $status_class[$payment['statut']] ?? 'bg-secondary'; ?>">
                        <i class="bi bi-<?php echo $status_icon[$payment['statut']] ?? 'question-circle'; ?> me-1"></i>
                        <?php echo $status_text[$payment['statut']] ?? 'Inconnu'; ?>
                    </span>
                </div>
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-1">Paiement N° <?php echo htmlspecialchars($numero_recu); ?></h5>
                    <p class="mb-0">
                        Montant total: <strong><?php echo formatAmount($payment['montant_total'], $payment['monnaie']); ?></strong>
                        - Payé le <?php echo formatDate($payment['date_paiement'], 'd/m/Y à H:i'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="row">
        <!-- Colonne principale -->
        <div class="col-lg-8">
            <!-- Informations de l'élève -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person me-2"></i>Informations de l'élève
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3">
                            <?php if (!empty($payment['photo_url']) && file_exists('../' . $payment['photo_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($payment['photo_url']); ?>" 
                                     alt="Photo élève" class="img-thumbnail" style="max-width: 120px; max-height: 150px;">
                            <?php else: ?>
                                <div class="bg-light border rounded d-flex align-items-center justify-content-center"
                                     style="width: 120px; height: 150px; margin: 0 auto;">
                                    <i class="bi bi-person fs-1 text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">Nom complet:</th>
                                    <td><strong><?php echo htmlspecialchars($payment['eleve_nom'] . ' ' . $payment['eleve_prenom']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Matricule:</th>
                                    <td><span class="font-monospace"><?php echo htmlspecialchars($payment['matricule']); ?></span></td>
                                </tr>
                                <?php if (!empty($payment['classe_nom'])): ?>
                                <tr>
                                    <th>Classe:</th>
                                    <td><?php echo htmlspecialchars($payment['classe_nom']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($payment['classe_niveau'])): ?>
                                <tr>
                                    <th>Niveau:</th>
                                    <td><?php echo htmlspecialchars($payment['classe_niveau']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($payment['date_naissance'])): ?>
                                <tr>
                                    <th>Date de naissance:</th>
                                    <td><?php echo formatDate($payment['date_naissance'], 'd/m/Y'); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Détails du paiement -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-credit-card me-2"></i>Détails du paiement
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Numéro de reçu:</th>
                                    <td><span class="badge bg-light text-dark font-monospace fs-6"><?php echo htmlspecialchars($numero_recu); ?></span></td>
                                </tr>
                                <tr>
                                    <th>Date de paiement:</th>
                                    <td><?php echo formatDate($payment['date_paiement'], 'd/m/Y à H:i'); ?></td>
                                </tr>
                                <tr>
                                    <th>Mode de paiement:</th>
                                    <td>
                                        <?php
                                        $modes = [
                                            'espèces' => '<i class="bi bi-cash-coin text-success"></i> Espèces',
                                            'mobile_money' => '<i class="bi bi-phone text-primary"></i> Mobile Money',
                                            'carte' => '<i class="bi bi-credit-card text-info"></i> Carte bancaire',
                                            'virement' => '<i class="bi bi-bank text-warning"></i> Virement',
                                            'chèque' => '<i class="bi bi-journal-check text-secondary"></i> Chèque'
                                        ];
                                        echo $modes[$mode_paiement] ?? ucfirst($mode_paiement);
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Monnaie:</th>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($payment['monnaie']); ?></span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <?php if (!empty($reference)): ?>
                                <tr>
                                    <th width="40%">Référence:</th>
                                    <td><span class="font-monospace text-muted"><?php echo htmlspecialchars($reference); ?></span></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Montant total:</th>
                                    <td>
                                        <span class="fs-5 fw-bold text-primary">
                                            <?php echo formatAmount($payment['montant_total'], $payment['monnaie']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Statut:</th>
                                    <td>
                                        <span class="badge <?php echo $status_class[$payment['statut']] ?? 'bg-secondary'; ?>">
                                            <?php echo $status_text[$payment['statut']] ?? 'Inconnu'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if (!empty($payment['caissier_nom'])): ?>
                                <tr>
                                    <th>Reçu par:</th>
                                    <td>
                                        <?php echo htmlspecialchars($payment['caissier_nom'] . ' ' . $payment['caissier_prenom']); ?>
                                        <?php if (!empty($payment['caissier_email'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($payment['caissier_email']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Détail des frais payés -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-check me-2"></i>Frais payés
                    </h5>
                    <span class="badge bg-primary"><?php echo $nb_lignes; ?> ligne<?php echo $nb_lignes > 1 ? 's' : ''; ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($lignes_paiement)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Type de frais</th>
                                        <th>Période</th>
                                        <th class="text-end">Montant standard</th>
                                        <th class="text-end">Montant payé</th>
                                        <th class="text-center">Différence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lignes_paiement as $ligne): 
                                        $montant_ligne = $ligne['montant'] ?? $ligne['montant_ligne'] ?? 0;
                                        $montant_standard = $ligne['montant_standard'] ?? 0;
                                        $difference = $montant_ligne - $montant_standard;
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark font-monospace">
                                                    <?php echo htmlspecialchars($ligne['type_frais_code']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($ligne['type_frais_nom']); ?></strong>
                                                    <?php if (!empty($ligne['type_frais_description'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($ligne['type_frais_description']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($ligne['periode'] ?? $ligne['periode_concerne'] ?? 'N/A'); ?></td>
                                            <td class="text-end font-monospace text-muted">
                                                <?php echo formatAmount($montant_standard, $payment['monnaie']); ?>
                                            </td>
                                            <td class="text-end font-monospace fw-bold">
                                                <?php echo formatAmount($montant_ligne, $payment['monnaie']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($difference != 0): ?>
                                                    <span class="badge <?php echo $difference > 0 ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                        <?php echo ($difference > 0 ? '+' : '') . formatAmount($difference, $payment['monnaie']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <i class="bi bi-check-circle text-success"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-primary">
                                    <tr>
                                        <th colspan="4" class="text-end">TOTAL PAYÉ:</th>
                                        <th class="text-end font-monospace">
                                            <?php echo formatAmount($payment['montant_total'], $payment['monnaie']); ?>
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-2">Aucun détail de frais disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($payment['commentaire'])): ?>
            <!-- Observations -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-chat-text me-2"></i>Observations
                    </h5>
                </div>
                <div class="card-body">
                    <div class="bg-light p-3 rounded">
                        <?php echo nl2br(htmlspecialchars($payment['commentaire'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Colonne latérale -->
        <div class="col-lg-4">
            <!-- Résumé financier -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-calculator me-2"></i>Résumé financier
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Montant total:</span>
                        <span class="fs-5 fw-bold text-primary">
                            <?php echo formatAmount($payment['montant_total'], $payment['monnaie']); ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Nombre de lignes:</span>
                        <span class="badge bg-secondary"><?php echo $nb_lignes; ?></span>
                    </div>
                    <?php if (abs($total_lignes - $payment['montant_total']) > 0.01): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Vérification:</span>
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-exclamation-triangle me-1"></i>Différence détectée
                        </span>
                    </div>
                    <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Vérification:</span>
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle me-1"></i>Cohérent
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <hr>
                    <small class="text-muted">
                        En lettres: <?php echo ucfirst(numberToWords($payment['montant_total'])); ?> <?php echo $payment['monnaie']; ?>
                    </small>
                </div>
            </div>

            <!-- Actions rapides (visible uniquement à l'écran) -->
            <div class="card mb-4 screen-only">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning me-2"></i>Actions rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="print_receipt.php?id=<?php echo $payment_id; ?>&auto_print=1" 
                           class="btn btn-primary" target="_blank">
                            <i class="bi bi-printer me-2"></i>Imprimer automatiquement
                        </a>
                        <a href="../students/view.php?id=<?php echo $payment['eleve_id']; ?>" 
                           class="btn btn-outline-info">
                            <i class="bi bi-person me-2"></i>Voir fiche élève
                        </a>
                        <a href="index.php?search=<?php echo urlencode($payment['matricule']); ?>" 
                           class="btn btn-outline-secondary">
                            <i class="bi bi-search me-2"></i>Autres paiements
                        </a>
                    </div>
                </div>
            </div>

            <!-- Historique (visible uniquement à l'écran) -->
            <?php if (!empty($payment_history)): ?>
            <div class="card mb-4 screen-only">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>Historique
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($payment_history as $entry): ?>
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 32px; height: 32px;">
                                        <i class="bi bi-<?php echo $entry['action'] === 'Création' ? 'plus' : 'pencil'; ?> text-white small"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($entry['action']); ?></h6>
                                    <p class="mb-1 small text-muted">
                                        <?php echo htmlspecialchars($entry['utilisateur'] ?? 'Utilisateur inconnu'); ?>
                                    </p>
                                    <p class="mb-0 small text-muted">
                                        <?php echo formatDate($entry['date_action'], 'd/m/Y à H:i'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Informations techniques (visible uniquement à l'écran) -->
            <?php if (hasRole(['admin'])): ?>
            <div class="card screen-only">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear me-2"></i>Informations techniques
                    </h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <strong>ID:</strong> <?php echo $payment['id']; ?><br>
                        <strong>Créé le:</strong> <?php echo formatDate($payment['created_at'], 'd/m/Y à H:i'); ?><br>
                        <?php if ($payment['updated_at'] != $payment['created_at']): ?>
                            <strong>Modifié le:</strong> <?php echo formatDate($payment['updated_at'], 'd/m/Y à H:i'); ?><br>
                        <?php endif; ?>
                        <strong>Version:</strong> <?php echo $payment['version'] ?? 1; ?><br>
                        <?php if (!empty($payment['notes_internes'])): ?>
                            <strong>Notes internes:</strong><br>
                            <div class="bg-light p-2 rounded mt-1" style="font-size: 0.75rem;">
                                <?php echo nl2br(htmlspecialchars($payment['notes_internes'])); ?>
                            </div>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Pied de page d'impression (visible uniquement à l'impression) -->
    <div class="print-only">
        <div class="row mt-5">
            <div class="col-12">
                <hr class="my-4">
                <div class="row">
                    <div class="col-6">
                        <p class="mb-1"><strong>Signature du caissier:</strong></p>
                        <div style="border-bottom: 1px solid #000; height: 40px; margin-top: 20px;"></div>
                        <small class="text-muted"><?php echo htmlspecialchars($payment['caissier_nom'] . ' ' . $payment['caissier_prenom']); ?></small>
                    </div>
                    <div class="col-6 text-end">
                        <p class="mb-1"><strong>Signature de l'élève/parent:</strong></p>
                        <div style="border-bottom: 1px solid #000; height: 40px; margin-top: 20px;"></div>
                        <small class="text-muted"><?php echo htmlspecialchars($payment['eleve_nom'] . ' ' . $payment['eleve_prenom']); ?></small>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12 text-center">
                        <small class="text-muted">
                            Ce reçu est généré automatiquement le <?php echo formatDate(date('Y-m-d H:i:s'), 'd/m/Y à H:i'); ?><br>
                            Pour toute question, contactez <?php echo htmlspecialchars($payment['ecole_nom']); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'annulation -->
<?php if (hasRole(['admin', 'direction']) && $payment['statut'] != 'annulé'): ?>
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    Confirmer l'annulation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir annuler ce paiement ?</p>
                <div class="alert alert-warning">
                    <strong>Attention:</strong> Cette action est irréversible. Le paiement sera marqué comme annulé 
                    et ne pourra plus être modifié.
                </div>
                <p><strong>Paiement:</strong> <?php echo htmlspecialchars($numero_recu); ?></p>
                <p><strong>Montant:</strong> <?php echo formatAmount($payment['montant_total'], $payment['monnaie']); ?></p>
                <p><strong>Élève:</strong> <?php echo htmlspecialchars($payment['eleve_nom'] . ' ' . $payment['eleve_prenom']); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="cancel_payment">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Confirmer l'annulation
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>



        </div> <!-- Fin du content-area -->
    </main>
    
    <!-- Modal des notifications -->
    <div class="modal fade" id="notificationsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-bell me-2"></i>Notifications
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="notification-item">
                        <i class="bi bi-exclamation-circle text-warning"></i>
                        <div>
                            <strong>Retards de paiement</strong>
                            <p class="mb-0">Vérifiez les paiements en retard</p>
                            <small class="text-muted">Il y a 2 heures</small>
                        </div>
                    </div>
                    
                    <div class="notification-item">
                        <i class="bi bi-cash-coin text-success"></i>
                        <div>
                            <strong>Nouveaux paiements</strong>
                            <p class="mb-0">Paiements récents enregistrés</p>
                            <small class="text-muted">Il y a 1 jour</small>
                        </div>
                    </div>
                    
                    <div class="notification-item">
                        <i class="bi bi-calendar-check text-info"></i>
                        <div>
                            <strong>Fin de période</strong>
                            <p class="mb-0">La période se termine bientôt</p>
                            <small class="text-muted">Il y a 3 jours</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary">Marquer comme lues</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Initialisation des tooltips Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Confirmation pour l'annulation
        document.addEventListener('DOMContentLoaded', function() {
            const cancelForm = document.querySelector('form[action*="cancel_payment"]');
            if (cancelForm) {
                cancelForm.addEventListener('submit', function(e) {
                    if (!confirm('Êtes-vous sûr de vouloir annuler ce paiement ? Cette action est irréversible.')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>
