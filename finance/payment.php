<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'caissier']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Validation des données principales
        $eleve_id = intval($_POST['eleve_id'] ?? 0);
        $montant_total = floatval($_POST['montant_total'] ?? 0);
        $monnaie = sanitize($_POST['monnaie'] ?? 'CDF');
        $mode_paiement = sanitize($_POST['mode_paiement'] ?? '');
        $reference_transaction = sanitize($_POST['reference_transaction'] ?? '');
        $date_paiement = sanitize($_POST['date_paiement'] ?? '');
        $observations = sanitize($_POST['observations'] ?? '');
        
        // Validation
        if (!$eleve_id) {
            $errors[] = "Veuillez sélectionner un élève.";
        }
        
        if ($montant_total <= 0) {
            $errors[] = "Le montant total doit être supérieur à 0.";
        }
        
        if (empty($mode_paiement)) {
            $errors[] = "Veuillez sélectionner un mode de paiement.";
        }
        
        if (empty($date_paiement)) {
            $errors[] = "Veuillez sélectionner une date de paiement.";
        }
        
        // Vérifier que l'élève existe et appartient à l'école
        if ($eleve_id) {
            $eleve_check = "SELECT id, nom, prenom, matricule FROM eleves WHERE id = :eleve_id AND ecole_id = :ecole_id AND statut = 'actif'";
            $eleve_stmt = $db->prepare($eleve_check);
            $eleve_stmt->execute(['eleve_id' => $eleve_id, 'ecole_id' => $_SESSION['ecole_id']]);
            $eleve = $eleve_stmt->fetch();
            
            if (!$eleve) {
                $errors[] = "Élève introuvable ou non autorisé.";
            }
        }
        
        // Validation des lignes de paiement
        $lignes_paiement = [];
        if (isset($_POST['lignes']) && is_array($_POST['lignes'])) {
            $total_lignes = 0;
            
            foreach ($_POST['lignes'] as $index => $ligne) {
                $type_frais_id = intval($ligne['type_frais_id'] ?? 0);
                $montant_ligne = floatval($ligne['montant'] ?? 0);
                $periode = sanitize($ligne['periode'] ?? '');
                
                if ($type_frais_id && $montant_ligne > 0) {
                    // Vérifier que le type de frais existe
                    $frais_check = "SELECT libelle FROM types_frais WHERE id = :id AND ecole_id = :ecole_id AND statut = 'actif'";
                    $frais_stmt = $db->prepare($frais_check);
                    $frais_stmt->execute(['id' => $type_frais_id, 'ecole_id' => $_SESSION['ecole_id']]);
                    $frais = $frais_stmt->fetch();
                    
                    if ($frais) {
                        $lignes_paiement[] = [
                            'type_frais_id' => $type_frais_id,
                            'montant' => $montant_ligne,
                            'periode' => $periode,
                            'libelle' => $frais['libelle']
                        ];
                        $total_lignes += $montant_ligne;
                    }
                }
            }
            
            if (empty($lignes_paiement)) {
                $errors[] = "Veuillez ajouter au moins une ligne de paiement.";
            }
            
            if (abs($total_lignes - $montant_total) > 0.01) {
                $errors[] = "Le total des lignes ne correspond pas au montant total.";
            }
        } else {
            $errors[] = "Veuillez ajouter au moins une ligne de paiement.";
        }
        
        if (empty($errors)) {
            // Générer le numéro de reçu
            $numero_recu = generateReceiptNumber($db, $_SESSION['ecole_id']);
            
            // Générer automatiquement la référence de transaction si vide
            if (empty($reference_transaction)) {
                $reference_transaction = generateTransactionReference($db, $mode_paiement);
            } else {
                // Vérifier l'unicité de la référence fournie
                if (transactionReferenceExists($db, $reference_transaction)) {
                    $errors[] = "Cette référence de transaction existe déjà. Référence générée automatiquement.";
                    $reference_transaction = generateTransactionReference($db, $mode_paiement);
                }
            }
            
            // Insérer le paiement principal
            $paiement_query = "INSERT INTO paiements (
                ecole_id, eleve_id, date_paiement, montant_total, monnaie, mode_paiement, 
                reference_transaction, numero_recu, statut, recu_par, caissier_id, 
                observations, created_by
            ) VALUES (
                :ecole_id, :eleve_id, :date_paiement, :montant_total, :monnaie, :mode_paiement,
                :reference_transaction, :numero_recu, 'confirmé', :recu_par, :caissier_id,
                :observations, :created_by
            )";
            
            $paiement_stmt = $db->prepare($paiement_query);
            $paiement_stmt->execute([
                'ecole_id' => $_SESSION['ecole_id'],
                'eleve_id' => $eleve_id,
                'date_paiement' => $date_paiement,
                'montant_total' => $montant_total,
                'monnaie' => $monnaie,
                'mode_paiement' => $mode_paiement,
                'reference_transaction' => $reference_transaction,
                'numero_recu' => $numero_recu,
                'recu_par' => $_SESSION['user_id'],
                'caissier_id' => $_SESSION['user_id'],
                'observations' => $observations,
                'created_by' => $_SESSION['user_id']
            ]);
            
            $paiement_id = $db->lastInsertId();
            
            // Insérer les lignes de paiement
            foreach ($lignes_paiement as $ligne) {
                $ligne_query = "INSERT INTO paiement_lignes (
                    paiement_id, type_frais_id, montant_ligne, montant_net, periode_concerne, created_by
                ) VALUES (
                    :paiement_id, :type_frais_id, :montant_ligne, :montant_net, :periode_concerne, :created_by
                )";
                
                $ligne_stmt = $db->prepare($ligne_query);
                $ligne_stmt->execute([
                    'paiement_id' => $paiement_id,
                    'type_frais_id' => $ligne['type_frais_id'],
                    'montant_ligne' => $ligne['montant'],
                    'montant_net' => $ligne['montant'], // Pour l'instant, pas de remise
                    'periode_concerne' => $ligne['periode'],
                    'created_by' => $_SESSION['user_id']
                ]);
            }
            
            $db->commit();
            
            // Log de l'action
            logUserAction('CREATE_PAYMENT', "Nouveau paiement: $numero_recu - {$eleve['nom']} {$eleve['prenom']} - " . formatAmount($montant_total, $monnaie));
            
            setFlashMessage('success', "Paiement enregistré avec succès ! Numéro de reçu: $numero_recu");
            
            // Rediriger vers l'impression du reçu
            redirect("print_receipt.php?id=$paiement_id");
        }
        
        if (!empty($errors)) {
            $db->rollBack();
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = "Erreur lors de l'enregistrement: " . $e->getMessage();
    }
}

// Récupérer les types de frais disponibles
$types_frais_query = "SELECT id, code, libelle, montant_standard, monnaie FROM types_frais 
                      WHERE ecole_id = :ecole_id AND statut = 'actif' 
                      ORDER BY libelle";
$types_frais_stmt = $db->prepare($types_frais_query);
$types_frais_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$types_frais = $types_frais_stmt->fetchAll();

// Récupérer les informations de l'école
$ecole_query = "SELECT nom_ecole, adresse, ville, province, pays, telephone, email, logo_path FROM ecoles WHERE id = :ecole_id";
$ecole_stmt = $db->prepare($ecole_query);
$ecole_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$ecole = $ecole_stmt->fetch();

// Récupérer l'année scolaire active
$annee_active_query = "SELECT libelle, date_debut, date_fin FROM annees_scolaires WHERE ecole_id = :ecole_id AND active = 1 LIMIT 1";
$annee_active_stmt = $db->prepare($annee_active_query);
$annee_active_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$annee_active = $annee_active_stmt->fetch();

// Récupérer les périodes scolaires de l'année active
$periodes_query = "SELECT nom, ordre_periode FROM periodes_scolaires WHERE annee_scolaire_id = (SELECT id FROM annees_scolaires WHERE ecole_id = :ecole_id AND active = 1 LIMIT 1) AND statut = 'actif' ORDER BY ordre_periode";
$periodes_stmt = $db->prepare($periodes_query);
$periodes_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$periodes = $periodes_stmt->fetchAll();

$page_title = "Nouveau Paiement";
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
        .student-search-result {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }
        .payment-line {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
        }
        .total-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
        }
        .currency-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .amount-display {
            font-size: 1.25rem;
            font-weight: bold;
        }
    </style>
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
                <p class="text-muted">Enregistrer un nouveau paiement d'élève</p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../auth/dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Finances</a></li>
                        <li class="breadcrumb-item active">Nouveau paiement</li>
                    </ol>
                </nav>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour à la liste
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Informations de l'école -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-1"><?php echo htmlspecialchars($ecole['nom_ecole'] ?? 'École'); ?></h4>
                            <?php if ($ecole['adresse'] || $ecole['ville'] || $ecole['province']): ?>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-geo-alt me-1"></i>
                                    <?php 
                                    $adresse_parts = array_filter([$ecole['adresse'], $ecole['ville'], $ecole['province']]);
                                    echo htmlspecialchars(implode(', ', $adresse_parts));
                                    ?>
                                </p>
                            <?php endif; ?>
                            <div class="d-flex gap-3 text-muted small">
                                <?php if ($ecole['telephone']): ?>
                                    <span><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($ecole['telephone']); ?></span>
                                <?php endif; ?>
                                <?php if ($ecole['email']): ?>
                                    <span><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($ecole['email']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($annee_active): ?>
                                <div class="badge bg-primary fs-6 p-2">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?php echo htmlspecialchars($annee_active['libelle']); ?>
                                </div>
                                <div class="text-muted small mt-1">
                                    <?php echo date('d/m/Y', strtotime($annee_active['date_debut'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($annee_active['date_fin'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="paymentForm">
                <div class="row g-4">
                    <!-- Informations principales -->
                    <div class="col-lg-8">
                        <!-- Sélection de l'élève -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="bi bi-person-search me-2"></i>Sélection de l'élève</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="student-search" class="form-label">Rechercher un élève</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="student-search" 
                                               placeholder="Tapez le nom, prénom ou matricule..." autocomplete="off">
                                    </div>
                                    <div id="search-results" class="mt-2"></div>
                                </div>
                                
                                <div id="selected-student" style="display: none;" class="student-search-result">
                                    <input type="hidden" id="eleve_id" name="eleve_id">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1" id="student-name"></h6>
                                            <small class="text-muted">Matricule: <span id="student-matricule"></span></small>
                                            <br><small class="text-info" id="student-classe"></small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearStudent()">
                                            <i class="bi bi-x"></i> Changer
                                        </button>
                                    </div>
                                    <div class="mt-2" id="student-financial-status"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Détails du paiement -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="bi bi-credit-card me-2"></i>Détails du paiement</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="date_paiement" class="form-label">Date du paiement *</label>
                                        <input type="datetime-local" class="form-control" id="date_paiement" name="date_paiement" 
                                               value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="mode_paiement" class="form-label">Mode de paiement *</label>
                                        <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                            <option value="">Choisir...</option>
                                            <option value="espèces">💵 Espèces</option>
                                            <option value="mobile_money">📱 Mobile Money</option>
                                            <option value="carte">💳 Carte bancaire</option>
                                            <option value="virement">🏦 Virement bancaire</option>
                                            <option value="chèque">📝 Chèque</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="monnaie" class="form-label">Monnaie</label>
                                        <select class="form-select" id="monnaie" name="monnaie" onchange="updateCurrency()">
                                            <option value="CDF">CDF (Franc Congolais)</option>
                                            <option value="USD">USD (Dollar Américain)</option>
                                            <option value="EUR">EUR (Euro)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="reference_transaction" class="form-label">Référence transaction *</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="reference_transaction" name="reference_transaction" 
                                                   placeholder="Référence auto-générée..." readonly>
                                            <button class="btn btn-outline-secondary" type="button" onclick="generateNewReference()" title="Générer nouvelle référence">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="btn btn-outline-primary" type="button" onclick="toggleManualReference()" title="Saisie manuelle">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Référence unique générée automatiquement</div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="observations" class="form-label">Observations</label>
                                        <textarea class="form-control" id="observations" name="observations" rows="2" 
                                                  placeholder="Remarques particulières..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Lignes de paiement -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="bi bi-list-ul me-2"></i>Détail des frais payés</h5>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addPaymentLine()">
                                    <i class="bi bi-plus"></i> Ajouter une ligne
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="payment-lines">
                                    <!-- Les lignes de paiement seront ajoutées ici -->
                                </div>
                                <div class="text-muted small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Ajoutez au moins une ligne de paiement pour spécifier les frais payés.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Résumé -->
                    <div class="col-lg-4">
                        <!-- Total -->
                        <div class="card mb-4">
                            <div class="card-body total-summary text-center">
                                <h5 class="mb-3">Résumé du paiement</h5>
                                <div class="amount-display mb-2">
                                    <span id="total-amount">0</span>
                                    <span class="currency-badge bg-white bg-opacity-25 rounded" id="currency-display">CDF</span>
                                </div>
                                <input type="hidden" id="montant_total" name="montant_total" value="0">
                                <p class="mb-0 opacity-75">Montant total</p>
                            </div>
                        </div>
                        
                        <!-- Actions rapides -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6><i class="bi bi-lightning me-2"></i>Actions rapides</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="addCommonFees()">
                                        <i class="bi bi-plus-circle me-2"></i>Frais courants
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="calculateChange()" id="change-btn" style="display: none;">
                                        <i class="bi bi-calculator me-2"></i>Calculer la monnaie
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearForm()">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Réinitialiser
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modes de paiement populaires -->
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="bi bi-credit-card me-2"></i>Modes populaires</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="setPaymentMode('espèces')">
                                        💵 Espèces
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="setPaymentMode('mobile_money')">
                                        📱 Mobile Money
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setPaymentMode('carte')">
                                        💳 Carte bancaire
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons de soumission -->
                <div class="card mt-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Annuler
                                </a>
                            </div>
                            
                            <div>
                                <button type="button" class="btn btn-outline-primary me-2" onclick="saveAsDraft()">
                                    <i class="bi bi-save me-2"></i>Enregistrer comme brouillon
                                </button>
                                <button type="submit" class="btn btn-primary" id="submit-btn" disabled>
                                    <i class="bi bi-check-lg me-2"></i>Enregistrer le paiement
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Modal pour créer un nouveau type de frais -->
    <div class="modal fade" id="newFeeTypeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Nouveau type de frais
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="newFeeTypeForm">
                    <div class="modal-body">
                        <input type="hidden" id="modal_line_counter" value="">
                        
                        <div class="mb-3">
                            <label for="new_fee_libelle" class="form-label">Libellé du frais *</label>
                            <input type="text" class="form-control" id="new_fee_libelle" required 
                                   placeholder="Ex: Frais de cantine, Transport scolaire...">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_fee_code" class="form-label">Code</label>
                            <input type="text" class="form-control" id="new_fee_code" 
                                   placeholder="Code court (optionnel)">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="new_fee_montant" class="form-label">Montant standard</label>
                                <input type="number" step="0.01" class="form-control" id="new_fee_montant" 
                                       placeholder="0.00">
                            </div>
                            <div class="col-md-6">
                                <label for="new_fee_monnaie" class="form-label">Monnaie</label>
                                <select class="form-select" id="new_fee_monnaie">
                                    <option value="CDF">CDF</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_fee_type_recurrence" class="form-label">Type de récurrence</label>
                            <select class="form-select" id="new_fee_type_recurrence">
                                <option value="unique">Unique</option>
                                <option value="mensuel">Mensuel</option>
                                <option value="trimestriel">Trimestriel</option>
                                <option value="semestriel">Semestriel</option>
                                <option value="annuel">Annuel</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_fee_description" class="form-label">Description</label>
                            <textarea class="form-control" id="new_fee_description" rows="2" 
                                      placeholder="Description détaillée du frais..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Créer le type de frais
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        const typesFreais = <?php echo json_encode($types_frais); ?>;
        const periodesEcole = <?php echo json_encode($periodes); ?>;
        let lineCounter = 0;
        let selectedStudent = null;
        
        // Recherche d'élèves en temps réel
        let searchTimeout;
        document.getElementById('student-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                document.getElementById('search-results').innerHTML = '';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchStudents(query);
            }, 300);
        });
        
        function searchStudents(query) {
            const resultsDiv = document.getElementById('search-results');
            resultsDiv.innerHTML = '<div class="text-center p-2"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';
            
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
                            <button type="button" class="list-group-item list-group-item-action" onclick="selectStudent(${student.id}, '${student.nom}', '${student.prenom}', '${student.matricule}', '${student.classe || ''}')">
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
                            </button>
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
        
        function selectStudent(id, nom, prenom, matricule, classe) {
            selectedStudent = {id, nom, prenom, matricule, classe};
            
            document.getElementById('eleve_id').value = id;
            document.getElementById('student-name').textContent = nom + ' ' + prenom;
            document.getElementById('student-matricule').textContent = matricule;
            document.getElementById('student-classe').textContent = classe || 'Classe non assignée';
            
            document.getElementById('student-search').style.display = 'none';
            document.getElementById('search-results').innerHTML = '';
            document.getElementById('selected-student').style.display = 'block';
            
            updateSubmitButton();
        }
        
        function clearStudent() {
            selectedStudent = null;
            document.getElementById('eleve_id').value = '';
            document.getElementById('student-search').style.display = 'block';
            document.getElementById('student-search').value = '';
            document.getElementById('selected-student').style.display = 'none';
            updateSubmitButton();
        }
        
        function addPaymentLine() {
            const container = document.getElementById('payment-lines');
            const lineDiv = document.createElement('div');
            lineDiv.className = 'payment-line';
            lineDiv.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Ligne ${lineCounter + 1}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePaymentLine(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label form-label-sm">Type de frais *</label>
                        <div class="input-group">
                            <select class="form-select form-select-sm" name="lignes[${lineCounter}][type_frais_id]" required onchange="updateLineAmount(this)" id="type_frais_${lineCounter}">
                                <option value="">Choisir...</option>
                                ${typesFreais.map(frais => `<option value="${frais.id}" data-montant="${frais.montant_standard}" data-monnaie="${frais.monnaie}">${frais.libelle}</option>`).join('')}
                                <option value="new">➕ Créer un nouveau type</option>
                            </select>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="showNewFeeTypeModal(${lineCounter})" title="Ajouter un type de frais">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Montant *</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" name="lignes[${lineCounter}][montant]" 
                               required onchange="updateTotal()" min="0">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Période</label>
                        <select class="form-select form-select-sm" name="lignes[${lineCounter}][periode]">
                            <option value="">-</option>
                            ${periodesEcole.map(periode => `<option value="${periode.nom}">${periode.nom}</option>`).join('')}
                        </select>
                    </div>
                </div>
            `;
            
            container.appendChild(lineDiv);
            lineCounter++;
            updateSubmitButton();
        }
        
        function removePaymentLine(button) {
            button.closest('.payment-line').remove();
            updateTotal();
            updateSubmitButton();
        }
        
        function updateLineAmount(select) {
            const selectedOption = select.options[select.selectedIndex];
            const montantStandard = selectedOption.dataset.montant;
            const montantInput = select.closest('.payment-line').querySelector('input[name*="[montant]"]');
            
            if (montantStandard && montantStandard > 0) {
                montantInput.value = montantStandard;
                updateTotal();
            }
        }
        
        function updateTotal() {
            const montantInputs = document.querySelectorAll('input[name*="[montant]"]');
            let total = 0;
            
            montantInputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                total += value;
            });
            
            document.getElementById('total-amount').textContent = total.toLocaleString('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.getElementById('montant_total').value = total;
            
            updateSubmitButton();
        }
        
        function updateCurrency() {
            const currency = document.getElementById('monnaie').value;
            document.getElementById('currency-display').textContent = currency;
        }
        
        function updateSubmitButton() {
            const submitBtn = document.getElementById('submit-btn');
            const hasStudent = selectedStudent !== null;
            const hasLines = document.querySelectorAll('.payment-line').length > 0;
            const hasAmount = parseFloat(document.getElementById('montant_total').value) > 0;
            
            submitBtn.disabled = !(hasStudent && hasLines && hasAmount);
        }
        
        function setPaymentMode(mode) {
            document.getElementById('mode_paiement').value = mode;
            
            // Générer automatiquement une nouvelle référence selon le mode
            generateNewReference();
            
            // Afficher le calcul de monnaie pour les espèces
            if (mode === 'espèces') {
                document.getElementById('change-btn').style.display = 'block';
            } else {
                document.getElementById('change-btn').style.display = 'none';
            }
        }
        
        function addCommonFees() {
            // Ajouter automatiquement les frais les plus courants de l'école
            const commonFees = typesFreais.filter(frais => 
                frais.libelle.toLowerCase().includes('scolarité') || 
                frais.libelle.toLowerCase().includes('écolage') ||
                frais.libelle.toLowerCase().includes('inscription') ||
                frais.libelle.toLowerCase().includes('frais de scolarité') ||
                frais.libelle.toLowerCase().includes('cantin') ||
                frais.libelle.toLowerCase().includes('transport')
            );
            
            if (commonFees.length === 0) {
                // Si aucun frais courant n'est trouvé, proposer de créer un
                if (confirm('Aucun frais courant trouvé. Voulez-vous créer un nouveau type de frais ?')) {
                    showNewFeeTypeModal(0);
                }
                return;
            }
            
            // Limiter à 3 frais pour éviter de surcharger le formulaire
            const limitedFees = commonFees.slice(0, 3);
            
            limitedFees.forEach(frais => {
                addPaymentLine();
                const lastLine = document.querySelector('.payment-line:last-child');
                const select = lastLine.querySelector('select[name*="[type_frais_id]"]');
                const montantInput = lastLine.querySelector('input[name*="[montant]"]');
                
                select.value = frais.id;
                montantInput.value = frais.montant_standard || 0;
            });
            
            updateTotal();
            
            // Afficher un message informatif
            showSuccessMessage(`${limitedFees.length} frais courants ajoutés automatiquement`);
        }
        
        function calculateChange() {
            const total = parseFloat(document.getElementById('montant_total').value);
            if (total > 0) {
                const received = prompt('Montant reçu du client:', total);
                if (received && parseFloat(received) >= total) {
                    const change = parseFloat(received) - total;
                    alert(`Monnaie à rendre: ${change.toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${document.getElementById('monnaie').value}`);
                }
            }
        }
        
        function clearForm() {
            if (confirm('Êtes-vous sûr de vouloir réinitialiser le formulaire ?')) {
                location.reload();
            }
        }
        
        function saveAsDraft() {
            // Implémenter la sauvegarde en brouillon
            NaklassUtils.showToast('Fonction de brouillon à implémenter', 'info');
        }
        
        // Générer une nouvelle référence de transaction
        function generateNewReference() {
            const mode = document.getElementById('mode_paiement').value || 'PAY';
            const now = new Date();
            
            // Préfixes selon le mode de paiement
            const prefixes = {
                'espèces': 'ESP',
                'mobile_money': 'MM',
                'carte': 'CB',
                'virement': 'VIR',
                'chèque': 'CHQ'
            };
            
            const prefix = prefixes[mode] || 'PAY';
            const date = now.toISOString().slice(0, 10).replace(/-/g, '');
            const time = now.toTimeString().slice(0, 8).replace(/:/g, '');
            const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            
            const reference = `${prefix}-${date}-${time}-${random}`;
            document.getElementById('reference_transaction').value = reference;
        }
        
        // Basculer entre saisie automatique et manuelle
        function toggleManualReference() {
            const input = document.getElementById('reference_transaction');
            const isReadonly = input.hasAttribute('readonly');
            
            if (isReadonly) {
                input.removeAttribute('readonly');
                input.placeholder = 'Saisissez votre référence...';
                input.select();
            } else {
                input.setAttribute('readonly', 'readonly');
                input.placeholder = 'Référence auto-générée...';
                generateNewReference();
            }
        }
        
        // Afficher le modal de création de type de frais
        function showNewFeeTypeModal(lineCounter) {
            document.getElementById('modal_line_counter').value = lineCounter;
            document.getElementById('newFeeTypeForm').reset();
            document.getElementById('new_fee_monnaie').value = document.getElementById('monnaie').value;
            
            const modal = new bootstrap.Modal(document.getElementById('newFeeTypeModal'));
            modal.show();
        }
        
        // Gérer la soumission du formulaire de nouveau type de frais
        document.getElementById('newFeeTypeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                libelle: document.getElementById('new_fee_libelle').value,
                code: document.getElementById('new_fee_code').value,
                montant_standard: document.getElementById('new_fee_montant').value,
                monnaie: document.getElementById('new_fee_monnaie').value,
                type_recurrence: document.getElementById('new_fee_type_recurrence').value,
                description: document.getElementById('new_fee_description').value
            };
            
            createNewFeeType(formData);
        });
        
        // Créer un nouveau type de frais via AJAX
        function createNewFeeType(formData) {
            fetch('create_fee_type.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Ajouter le nouveau type à la liste
                    const newFeeType = {
                        id: data.fee_type_id,
                        libelle: formData.libelle,
                        code: formData.code,
                        montant_standard: formData.montant_standard,
                        monnaie: formData.monnaie
                    };
                    
                    typesFreais.push(newFeeType);
                    
                    // Mettre à jour le select de la ligne courante
                    const lineCounter = document.getElementById('modal_line_counter').value;
                    const select = document.getElementById(`type_frais_${lineCounter}`);
                    
                    // Ajouter la nouvelle option
                    const option = document.createElement('option');
                    option.value = newFeeType.id;
                    option.textContent = newFeeType.libelle;
                    option.dataset.montant = newFeeType.montant_standard;
                    option.dataset.monnaie = newFeeType.monnaie;
                    
                    // Insérer avant l'option "Créer un nouveau type"
                    const lastOption = select.querySelector('option[value="new"]');
                    select.insertBefore(option, lastOption);
                    
                    // Sélectionner automatiquement le nouveau type
                    select.value = newFeeType.id;
                    
                    // Mettre à jour le montant si fourni
                    if (formData.montant_standard) {
                        const montantInput = select.closest('.payment-line').querySelector('input[name*="[montant]"]');
                        montantInput.value = formData.montant_standard;
                        updateTotal();
                    }
                    
                    // Fermer le modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('newFeeTypeModal'));
                    modal.hide();
                    
                    // Afficher un message de succès
                    showSuccessMessage('Type de frais créé avec succès !');
                    
                } else {
                    showErrorMessage('Erreur lors de la création : ' + data.message);
                }
            })
            .catch(error => {
                showErrorMessage('Erreur de communication : ' + error.message);
            });
        }
        
        // Fonctions d'affichage des messages
        function showSuccessMessage(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="bi bi-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        function showErrorMessage(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed';
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        // Ajouter une ligne par défaut au chargement
        document.addEventListener('DOMContentLoaded', function() {
            addPaymentLine();
            generateNewReference(); // Générer une référence initiale
        });
        
        // Validation du formulaire
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            if (!selectedStudent) {
                e.preventDefault();
                NaklassUtils.showToast('Veuillez sélectionner un élève.', 'warning');
                return;
            }
            
            const lines = document.querySelectorAll('.payment-line');
            if (lines.length === 0) {
                e.preventDefault();
                NaklassUtils.showToast('Veuillez ajouter au moins une ligne de paiement.', 'warning');
                return;
            }
            
            const total = parseFloat(document.getElementById('montant_total').value);
            if (total <= 0) {
                e.preventDefault();
                NaklassUtils.showToast('Le montant total doit être supérieur à 0.', 'warning');
                return;
            }
        });
    </script>
</body>
</html>
