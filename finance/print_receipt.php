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
    c.nom_classe as classe_nom,
    c.niveau as classe_niveau
FROM paiements p
JOIN eleves e ON p.eleve_id = e.id
JOIN ecoles ec ON e.ecole_id = ec.id
LEFT JOIN utilisateurs u ON p.caissier_id = u.id
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
    tf.description as type_frais_description
FROM paiement_lignes pl
JOIN types_frais tf ON pl.type_frais_id = tf.id
WHERE pl.paiement_id = :payment_id
ORDER BY tf.libelle";

$lignes_stmt = $db->prepare($lignes_query);
$lignes_stmt->execute(['payment_id' => $payment_id]);
$lignes_paiement = $lignes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Déterminer le nom de colonne pour le mode de paiement
$mode_paiement = $payment['mode'] ?? $payment['mode_paiement'] ?? '';

// Déterminer le nom de colonne pour le numéro de reçu
$numero_recu = $payment['recu_numero'] ?? $payment['numero_recu'] ?? '';

// Déterminer le nom de colonne pour la référence
$reference = $payment['reference_externe'] ?? $payment['reference_transaction'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu de Paiement - <?php echo htmlspecialchars($numero_recu); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-after: always; }
            body { 
                font-size: 12pt;
                color: black !important;
                background: white !important;
            }
            .receipt-container {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            .btn { display: none; }
            .navbar { display: none; }
            .footer { display: none; }
            h1, h2, h3, h4, h5, h6 { color: black !important; }
            .table { border-collapse: collapse !important; }
            .table th, .table td { 
                border: 1px solid #000 !important;
                padding: 8px !important;
            }
        }

        @media screen {
            body { background-color: #f8f9fa; }
            .receipt-container {
                background: white;
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                margin: 20px auto;
                max-width: 800px;
                padding: 30px;
            }
        }

        .receipt-header {
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .school-logo {
            max-height: 80px;
            max-width: 120px;
            object-fit: contain;
        }

        .receipt-title {
            background: linear-gradient(135deg, #0d6efd, #0056b3);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            font-weight: bold;
            font-size: 1.2em;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px dotted #dee2e6;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 140px;
        }

        .info-value {
            color: #212529;
            flex: 1;
            text-align: right;
        }

        .amount-highlight {
            background-color: #f8f9fa;
            border: 2px solid #0d6efd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin: 20px 0;
        }

        .amount-total {
            font-size: 1.8em;
            font-weight: bold;
            color: #0d6efd;
        }

        .signature-area {
            margin-top: 40px;
            border-top: 2px solid #dee2e6;
            padding-top: 30px;
        }

        .signature-box {
            border: 1px solid #dee2e6;
            height: 80px;
            border-radius: 5px;
            background-color: #f8f9fa;
        }

        .watermark {
            position: relative;
            z-index: 1;
        }

        .watermark::before {
            content: 'PAYÉ';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            font-size: 4em;
            color: rgba(13, 110, 253, 0.1);
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
        }

        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 10;
        }

        @media (max-width: 768px) {
            .receipt-container { 
                margin: 10px;
                padding: 20px;
            }
            .info-row {
                flex-direction: column;
            }
            .info-value {
                text-align: left;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container watermark">
        <!-- Boutons d'action (masqués à l'impression) -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
            <div>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Imprimer
                </button>
                <a href="view_payment.php?id=<?php echo $payment_id; ?>" class="btn btn-outline-info">
                    <i class="bi bi-eye"></i> Détails
                </a>
            </div>
        </div>

        <!-- Badge de statut -->
        <div class="status-badge no-print">
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
            ?>
            <span class="badge fs-6 <?php echo $status_class[$payment['statut']] ?? 'bg-secondary'; ?>">
                <?php echo $status_text[$payment['statut']] ?? 'Inconnu'; ?>
            </span>
        </div>

        <!-- En-tête de l'école -->
        <div class="receipt-header">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <?php if (!empty($payment['logo_url']) && file_exists('../' . $payment['logo_url'])): ?>
                        <img src="../<?php echo htmlspecialchars($payment['logo_url']); ?>" 
                             alt="Logo école" class="school-logo">
                    <?php else: ?>
                        <div class="bg-primary text-white d-inline-flex align-items-center justify-content-center rounded" 
                             style="width: 80px; height: 80px; font-size: 2em;">
                            <i class="bi bi-building"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-9">
                    <h2 class="mb-1 text-primary"><?php echo htmlspecialchars($payment['ecole_nom']); ?></h2>
                    <?php if (!empty($payment['ecole_adresse']) || !empty($payment['ecole_ville']) || !empty($payment['ecole_province'])): ?>
                        <p class="mb-1">
                            <i class="bi bi-geo-alt me-2"></i>
                            <?php 
                            $adresse_parts = array_filter([
                                $payment['ecole_adresse'], 
                                $payment['ecole_ville'], 
                                $payment['ecole_province']
                            ]);
                            echo htmlspecialchars(implode(', ', $adresse_parts));
                            ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($payment['ecole_pays'])): ?>
                        <p class="mb-1"><i class="bi bi-flag me-2"></i><?php echo htmlspecialchars($payment['ecole_pays']); ?></p>
                    <?php endif; ?>
                    <div class="row">
                        <?php if (!empty($payment['ecole_telephone'])): ?>
                            <div class="col-md-6">
                                <small><i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($payment['ecole_telephone']); ?></small>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($payment['ecole_email'])): ?>
                            <div class="col-md-6">
                                <small><i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($payment['ecole_email']); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Titre du reçu -->
        <div class="receipt-title">
            REÇU DE PAIEMENT N° <?php echo htmlspecialchars($numero_recu); ?>
        </div>

        <!-- Informations du paiement -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-person me-2"></i>Informations de l'élève</h5>
                <div class="info-row">
                    <span class="info-label">Nom complet :</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['eleve_nom'] . ' ' . $payment['eleve_prenom']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Matricule :</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['matricule']); ?></span>
                </div>
                <?php if (!empty($payment['classe_nom'])): ?>
                <div class="info-row">
                    <span class="info-label">Classe :</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['classe_nom']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($payment['classe_niveau'])): ?>
                <div class="info-row">
                    <span class="info-label">Niveau :</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['classe_niveau']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-receipt me-2"></i>Informations du paiement</h5>
                <div class="info-row">
                    <span class="info-label">Date de paiement :</span>
                    <span class="info-value"><?php echo formatDate($payment['date_paiement'], 'd/m/Y à H:i'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mode de paiement :</span>
                    <span class="info-value">
                        <?php
                        $modes = [
                            'espèces' => 'Espèces',
                            'mobile_money' => 'Mobile Money',
                            'carte' => 'Carte bancaire',
                            'virement' => 'Virement',
                            'chèque' => 'Chèque'
                        ];
                        echo $modes[$mode_paiement] ?? ucfirst($mode_paiement);
                        ?>
                    </span>
                </div>
                <?php if (!empty($reference)): ?>
                <div class="info-row">
                    <span class="info-label">Référence :</span>
                    <span class="info-value font-monospace"><?php echo htmlspecialchars($reference); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($payment['caissier_nom'])): ?>
                <div class="info-row">
                    <span class="info-label">Reçu par :</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['caissier_nom'] . ' ' . $payment['caissier_prenom']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Détail des frais payés -->
        <div class="mb-4">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-list-check me-2"></i>Détail des frais payés</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Type de frais</th>
                            <th>Période</th>
                            <th class="text-end">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_verif = 0;
                        foreach ($lignes_paiement as $ligne): 
                            $montant_ligne = $ligne['montant'] ?? $ligne['montant_ligne'] ?? 0;
                            $total_verif += $montant_ligne;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($ligne['type_frais_nom']); ?></strong>
                                    <?php if (!empty($ligne['type_frais_description'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($ligne['type_frais_description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($ligne['periode'] ?? $ligne['periode_concerne'] ?? 'N/A'); ?></td>
                                <td class="text-end font-monospace">
                                    <?php echo formatAmount($montant_ligne, $payment['monnaie']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-primary">
                        <tr>
                            <th colspan="2" class="text-end">TOTAL :</th>
                            <th class="text-end font-monospace">
                                <?php echo formatAmount($payment['montant_total'], $payment['monnaie']); ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Montant total en évidence -->
        <div class="amount-highlight">
            <div class="mb-2">MONTANT TOTAL PAYÉ</div>
            <div class="amount-total">
                <?php echo formatAmount($payment['montant_total'], $payment['monnaie']); ?>
            </div>
            <small class="text-muted">
                (<?php echo ucfirst(numberToWords($payment['montant_total'])); ?> <?php echo $payment['monnaie']; ?>)
            </small>
        </div>

        <?php if (!empty($payment['commentaire'])): ?>
        <!-- Observations -->
        <div class="mb-4">
            <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-chat-text me-2"></i>Observations</h6>
            <div class="bg-light p-3 rounded">
                <?php echo nl2br(htmlspecialchars($payment['commentaire'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Zone de signature -->
        <div class="signature-area">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="mb-3">Signature du payeur</h6>
                    <div class="signature-box"></div>
                    <small class="text-muted">Date : _______________</small>
                </div>
                <div class="col-md-6">
                    <h6 class="mb-3">Signature du caissier</h6>
                    <div class="signature-box"></div>
                    <?php if (!empty($payment['caissier_nom'])): ?>
                        <small class="text-muted">
                            <?php echo htmlspecialchars($payment['caissier_nom'] . ' ' . $payment['caissier_prenom']); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pied de page -->
        <div class="text-center mt-4 pt-3 border-top">
            <small class="text-muted">
                Reçu généré le <?php echo formatDate(date('Y-m-d H:i:s'), 'd/m/Y à H:i'); ?>
                <?php if (isset($_SESSION['nom']) && isset($_SESSION['prenom'])): ?>
                    par <?php echo htmlspecialchars($_SESSION['nom'] . ' ' . $_SESSION['prenom']); ?>
                <?php endif; ?>
            </small>
        </div>
    </div>

    <!-- Script pour impression automatique (optionnel) -->
    <script>
        // Imprimer automatiquement si paramètre auto_print=1 dans l'URL
        if (new URLSearchParams(window.location.search).get('auto_print') === '1') {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        }

        // Raccourci clavier pour imprimer
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });

        // Fermer la fenêtre après impression si ouvert dans un nouvel onglet
        window.addEventListener('afterprint', function() {
            if (window.opener) {
                setTimeout(function() {
                    window.close();
                }, 1000);
            }
        });
    </script>
</body>
</html>
