<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Récupérer l'ID de l'élève depuis l'URL
$eleve_id = sanitize($_GET['id'] ?? '');

if (empty($eleve_id)) {
    setFlashMessage('error', 'ID de l\'élève non spécifié.');
    redirect('index.php');
}

try {
    // Récupérer les informations de l'élève
    $eleve_query = "SELECT e.*, c.nom_classe, c.niveau, c.niveau_detaille,
                           i.date_inscription, i.statut_inscription
                    FROM eleves e 
                    LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'actif'
                    LEFT JOIN classes c ON i.classe_id = c.id
                    WHERE e.id = :eleve_id AND e.ecole_id = :ecole_id";
    
    $eleve_stmt = $db->prepare($eleve_query);
    $eleve_stmt->execute([
        'eleve_id' => $eleve_id,
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    
    $eleve = $eleve_stmt->fetch();
    
    if (!$eleve) {
        setFlashMessage('error', 'Élève non trouvé ou accès non autorisé.');
        redirect('index.php');
    }
    
    // Récupérer les tuteurs de l'élève
    $tuteurs_query = "SELECT t.*, et.tuteur_principal, et.autorisation_sortie
                      FROM tuteurs t 
                      JOIN eleves_tuteurs et ON t.id = et.tuteur_id
                      WHERE et.eleve_id = :eleve_id
                      ORDER BY et.tuteur_principal DESC, t.nom, t.prenom";
    
    $tuteurs_stmt = $db->prepare($tuteurs_query);
    $tuteurs_stmt->execute(['eleve_id' => $eleve_id]);
    $tuteurs = $tuteurs_stmt->fetchAll();
    
    // Récupérer l'historique des inscriptions
    $inscriptions_query = "SELECT i.*, c.nom_classe, c.niveau, c.niveau_detaille
                          FROM inscriptions i 
                          JOIN classes c ON i.classe_id = c.id
                          WHERE i.eleve_id = :eleve_id
                          ORDER BY i.date_inscription DESC";
    
    $inscriptions_stmt = $db->prepare($inscriptions_query);
    $inscriptions_stmt->execute(['eleve_id' => $eleve_id]);
    $inscriptions = $inscriptions_stmt->fetchAll();
    
    // Récupérer la situation financière
    $finance_query = "SELECT sf.*, tf.libelle as nom_frais, tf.montant_defaut as montant_frais
                      FROM situation_frais sf 
                      JOIN types_frais tf ON sf.type_frais_id = tf.id
                      WHERE sf.eleve_id = :eleve_id
                      ORDER BY tf.code_frais";
    
    $finance_stmt = $db->prepare($finance_query);
    $finance_stmt->execute(['eleve_id' => $eleve_id]);
    $situation_financiere = $finance_stmt->fetchAll();
    
    // Récupérer les vrais paiements de l'élève
    $paiements_query = "SELECT p.*, pl.montant_ligne, pl.montant_net,
                               tf.libelle as type_frais, tf.monnaie as devise_frais
                        FROM paiements p 
                        JOIN paiement_lignes pl ON p.id = pl.paiement_id
                        JOIN types_frais tf ON pl.type_frais_id = tf.id
                        WHERE p.eleve_id = :eleve_id AND p.statut = 'confirmé'
                        ORDER BY p.date_paiement DESC";
    
    $paiements_stmt = $db->prepare($paiements_query);
    $paiements_stmt->execute(['eleve_id' => $eleve_id]);
    $paiements_eleve = $paiements_stmt->fetchAll();
    
    // Calculer les totaux financiers
    $total_dus = 0;
    $total_payes = 0;
    $total_reste = 0;
    
    foreach ($situation_financiere as $frais) {
        $total_dus += $frais['montant_frais'];
        $total_payes += $frais['montant_paye'];
        $total_reste += $frais['reste'];
    }
    
    // Calculer les totaux des vrais paiements
    $total_paiements_usd = 0;
    $total_paiements_cdf = 0;
    
    foreach ($paiements_eleve as $paiement) {
        if ($paiement['monnaie'] === 'USD') {
            $total_paiements_usd += $paiement['montant_total'];
        } else {
            $total_paiements_cdf += $paiement['montant_total'];
        }
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération des données : ' . $e->getMessage());
    redirect('index.php');
}

$page_title = "Profil de l'Élève";
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
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .profile-avatar {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 8px solid rgba(255, 255, 255, 0.95);
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4.5rem;
            color: #333;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            position: relative;
            z-index: 2;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.5);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            object-position: center top;
            transition: transform 0.3s ease;
        }
        
        .profile-avatar:hover img {
            transform: scale(1.1);
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .info-card h5 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-weight: 700;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s ease;
        }
        
        .info-row:hover {
            background-color: var(--light-color);
            margin: 0 -2rem;
            padding: 1rem 2rem;
            border-radius: 8px;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-value {
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-inscrit {
            background: linear-gradient(135deg, var(--success-color), #40c057);
            color: white;
        }
        
        .status-retire {
            background: linear-gradient(135deg, var(--danger-color), #e03131);
            color: white;
        }
        
        .status-diplome {
            background: linear-gradient(135deg, var(--warning-color), #fcc419);
            color: white;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-content p {
            color: #6c757d;
            margin: 0;
            font-weight: 500;
        }
        
        .tuteur-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .tuteur-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--success-color);
        }
        
        .tuteur-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .tuteur-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .tuteur-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            border: 3px solid rgba(255, 255, 255, 0.8);
        }
        
        .tuteur-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        .tuteur-info h6 {
            margin: 0;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .tuteur-info p {
            margin: 0;
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .finance-summary {
            background: linear-gradient(135deg, var(--success-color), #40c057);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }
        
        .finance-summary h4 {
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .finance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .finance-stat {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .finance-stat h5 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .finance-stat p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.875rem;
        }
        
        .breadcrumb-custom {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }
        
        .breadcrumb-custom .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .breadcrumb-custom .breadcrumb-item.active {
            color: #6c757d;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-modern {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .finance-item-modern {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .finance-item-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--success-color);
        }
        
        .finance-item-modern:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .finance-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .finance-item-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        
        .finance-item-stat {
            text-align: center;
            padding: 1rem;
            background: var(--light-color);
            border-radius: 8px;
        }
        
        .stat-label {
            display: block;
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            display: block;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .inscription-timeline-modern {
            position: relative;
            padding-left: 2rem;
        }
        
        .inscription-timeline-modern::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            border-radius: 2px;
        }
        
        .timeline-item-modern {
            position: relative;
            margin-bottom: 2rem;
        }
        
        .timeline-item-modern::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.5rem;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid white;
            box-shadow: 0 0 0 3px var(--primary-color);
        }
        
        .timeline-content {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .timeline-content:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .timeline-details p {
            margin-bottom: 0.5rem;
            color: #6c757d;
        }
        
        .timeline-details i {
            color: var(--primary-color);
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
                <h1><i class="bi bi-person-circle me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Détails complets du profil de l'élève</p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../auth/dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Élèves</a></li>
                        <li class="breadcrumb-item active"><?php echo $eleve['prenom'] . ' ' . $eleve['nom']; ?></li>
                    </ol>
                </nav>
            </div>
            
            <div class="topbar-actions">
                <a href="edit.php?id=<?php echo $eleve_id; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>Modifier
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Retour
                </a>
            </div>
        </header>
        
        <!-- Contenu principal -->
        <div class="content-area">
            <!-- Messages flash -->
            <?php 
            $flash_messages = getFlashMessages();
            foreach ($flash_messages as $type => $message): 
            ?>
                <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            
            <!-- En-tête du profil -->
            <div class="profile-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            
                            <?php 
                            // Inclure la configuration des photos
                            require_once '../config/photo_config.php';
                            
                            // Obtenir le chemin de la photo
                            $photo_path = '';
                            if (!empty($eleve['photo_path'])) {
                                // Utiliser le chemin absolu depuis la racine
                                $photo_path = 'uploads/students/' . $eleve['photo_path'];
                                if (!file_exists($photo_path)) {
                                    // Essayer le chemin avec PHOTO_CONFIG
                                    $photo_path = PHOTO_CONFIG['UPLOAD_DIR'] . $eleve['photo_path'];
                                }
                            }
                            
                            if (!empty($photo_path) && file_exists($photo_path)): ?>
                                <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Photo de l'élève" class="profile-avatar">
                            <?php else: ?>
                                <div class="profile-avatar">
                                    <i class="bi bi-person"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <h2 class="mb-3"><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></h2>
                            <p class="mb-2 fs-5 opacity-75">Matricule: <strong><?php echo htmlspecialchars($eleve['matricule']); ?></strong></p>
                            <?php if ($eleve['nom_classe']): ?>
                                <p class="mb-3 opacity-75">Classe: <strong><?php echo htmlspecialchars($eleve['nom_classe']); ?> (<?php echo htmlspecialchars($eleve['niveau']); ?>)</strong></p>
                            <?php endif; ?>
                            <span class="status-badge status-<?php echo strtolower($eleve['statut_scolaire'] ?? 'inscrit'); ?>">
                                <?php echo ucfirst($eleve['statut_scolaire'] ?? 'Inscrit'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cartes de statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($inscriptions); ?></h3>
                            <p>Inscriptions</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($tuteurs); ?></h3>
                            <p>Tuteurs</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($paiements_eleve); ?></h3>
                            <p>Paiements</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $eleve['date_premiere_inscription'] ? date('Y', strtotime($eleve['date_premiere_inscription'])) : 'N/A'; ?></h3>
                            <p>Année d'inscription</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Informations personnelles -->
                <div class="col-lg-6">
                    <div class="info-card">
                        <h5><i class="bi bi-person me-2"></i>Informations personnelles</h5>
                        
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-person-badge me-2"></i>Nom complet:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></span>
                        </div>
                        
                        <?php if (!empty($eleve['postnom'])): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-person-lines-fill me-2"></i>Post-nom:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['postnom']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-row">
                            <span class="info-label"><i class="bi bi-gender-ambiguous me-2"></i>Sexe:</span>
                            <span class="info-value">
                                <?php 
                                echo $eleve['sexe'] === 'M' ? 'Masculin' : 
                                     ($eleve['sexe'] === 'F' ? 'Féminin' : htmlspecialchars($eleve['sexe'])); 
                                ?>
                            </span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Date de naissance:</span>
                            <span class="info-value">
                                <?php echo !empty($eleve['date_naissance']) ? date('d/m/Y', strtotime($eleve['date_naissance'])) : 'Non renseignée'; ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($eleve['lieu_naissance'])): ?>
                        <div class="info-row">
                            <span class="info-label">Lieu de naissance:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['lieu_naissance']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-row">
                            <span class="info-label">Nationalité:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['nationalite']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Date d'inscription:</span>
                            <span class="info-value">
                                <?php echo !empty($eleve['date_premiere_inscription']) ? date('d/m/Y', strtotime($eleve['date_premiere_inscription'])) : 'Non renseignée'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Contact et adresse -->
                    <div class="info-card">
                        <h5><i class="bi bi-geo-alt me-2"></i>Contact et adresse</h5>
                        
                        <?php if (!empty($eleve['telephone'])): ?>
                        <div class="info-row">
                            <span class="info-label">Téléphone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['telephone']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($eleve['email'])): ?>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['email']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($eleve['adresse_complete'])): ?>
                        <div class="info-row">
                            <span class="info-label">Adresse:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['adresse_complete']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($eleve['quartier'])): ?>
                        <div class="info-row">
                            <span class="info-label">Quartier:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['quartier']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Informations scolaires et financières -->
                <div class="col-lg-6">
                    <!-- Informations scolaires -->
                    <div class="info-card">
                        <h5><i class="bi bi-mortarboard me-2"></i>Informations scolaires</h5>
                        
                        <?php if ($eleve['nom_classe']): ?>
                        <div class="info-row">
                            <span class="info-label">Classe actuelle:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['nom_classe']); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Niveau:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['niveau']); ?></span>
                        </div>
                        
                        <?php if (!empty($eleve['niveau_detaille'])): ?>
                        <div class="info-row">
                            <span class="info-label">Niveau détaillé:</span>
                            <span class="info-value"><?php echo htmlspecialchars($eleve['niveau_detaille']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-row">
                            <span class="info-label">Année scolaire:</span>
                            <span class="info-value"><?php echo date('Y'); ?>-<?php echo date('Y') + 1; ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Statut d'inscription:</span>
                            <span class="info-value">
                                <span class="status-badge status-<?php echo strtolower($eleve['statut_inscription'] ?? 'en_cours'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $eleve['statut_inscription'] ?? 'en_cours')); ?>
                                </span>
                            </span>
                        </div>
                        <?php else: ?>
                        <div class="info-row">
                            <span class="info-label">Classe:</span>
                            <span class="info-value text-muted">Aucune classe assignée</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Résumé financier -->
                    <div class="finance-summary">
                        <h4 class="mb-3"><i class="bi bi-cash-coin me-2"></i>Résumé financier</h4>
                        
                        <div class="finance-stats">
                            <div class="finance-stat">
                                <h5><?php echo number_format($total_dus, 0, ',', ' '); ?> FC</h5>
                                <p>Total dû</p>
                            </div>
                            <div class="finance-stat">
                                <h5><?php echo number_format($total_payes, 0, ',', ' '); ?> FC</h5>
                                <p>Total payé</p>
                            </div>
                            <div class="finance-stat">
                                <h5><?php echo number_format($total_reste, 0, ',', ' '); ?> FC</h5>
                                <p>Reste à payer</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tuteurs et situation financière -->
            <div class="row">
                <!-- Tuteurs -->
                <div class="col-lg-6">
                    <div class="info-card">
                        <h5><i class="bi bi-people me-2"></i>Tuteurs et parents</h5>
                        
                        <?php if (!empty($tuteurs)): ?>
                            <?php foreach ($tuteurs as $tuteur): ?>
                                <div class="tuteur-card">
                                    <div class="tuteur-header">
                                        <div class="tuteur-avatar">
                                            <?php echo strtoupper(substr($tuteur['prenom'], 0, 1) . substr($tuteur['nom'], 0, 1)); ?>
                                        </div>
                                        <div class="tuteur-info">
                                            <h6>
                                                <?php echo htmlspecialchars($tuteur['prenom'] . ' ' . $tuteur['nom']); ?>
                                                <?php if ($tuteur['tuteur_principal']): ?>
                                                    <span class="badge bg-warning text-dark ms-2">Principal</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p><?php echo ucfirst($tuteur['lien_parente']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <small class="text-muted">Lien de parenté:</small><br>
                                            <strong><?php echo htmlspecialchars(ucfirst($tuteur['lien_parente'])); ?></strong>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">Autorise la récupération:</small><br>
                                            <strong><?php echo $tuteur['autorisation_sortie'] ? 'Oui' : 'Non'; ?></strong>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($tuteur['telephone_principal'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Téléphone:</small><br>
                                        <strong><?php echo htmlspecialchars($tuteur['telephone_principal']); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($tuteur['email'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Email:</small><br>
                                        <strong><?php echo htmlspecialchars($tuteur['email']); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($tuteur['profession'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Profession:</small><br>
                                        <strong><?php echo htmlspecialchars($tuteur['profession']); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Aucun tuteur enregistré pour cet élève.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Situation financière détaillée -->
                <div class="col-lg-6">
                    <div class="info-card">
                        <h5><i class="bi bi-receipt me-2"></i>Situation financière détaillée</h5>
                        
                        <?php if (!empty($paiements_eleve)): ?>
                            <!-- Résumé des paiements -->
                            <div class="finance-summary mb-3">
                                <h6 class="text-success mb-2">Résumé des paiements</h6>
                                <?php if ($total_paiements_usd > 0): ?>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Total USD:</span>
                                        <strong class="text-success"><?php echo number_format($total_paiements_usd, 0, ',', ' '); ?> USD</strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($total_paiements_cdf > 0): ?>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Total CDF:</span>
                                        <strong class="text-success"><?php echo number_format($total_paiements_cdf, 0, ',', ' '); ?> CDF</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Détail des paiements -->
                            <?php foreach ($paiements_eleve as $paiement): ?>
                                <div class="finance-item-modern">
                                    <div class="finance-item-header">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($paiement['type_frais']); ?></h6>
                                        <span class="status-badge status-inscrit">
                                            Payé
                                        </span>
                                    </div>
                                    
                                    <div class="finance-item-stats">
                                        <div class="finance-item-stat">
                                            <span class="stat-label">Montant payé</span>
                                            <span class="stat-value text-success"><?php echo number_format($paiement['montant_total'], 0, ',', ' '); ?> <?php echo $paiement['monnaie']; ?></span>
                                        </div>
                                        <div class="finance-item-stat">
                                            <span class="stat-label">Mode</span>
                                            <span class="stat-value"><?php echo ucfirst($paiement['mode_paiement']); ?></span>
                                        </div>
                                        <div class="finance-item-stat">
                                            <span class="stat-label">Date</span>
                                            <span class="stat-value"><?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (!empty($situation_financiere)): ?>
                            <?php foreach ($situation_financiere as $frais): ?>
                                <div class="finance-item-modern">
                                    <div class="finance-item-header">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($frais['nom_frais']); ?></h6>
                                        <span class="status-badge <?php echo $frais['reste'] > 0 ? 'status-retire' : 'status-inscrit'; ?>">
                                            <?php echo $frais['reste'] > 0 ? 'En attente' : 'Payé'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="finance-item-stats">
                                        <div class="finance-item-stat">
                                            <span class="stat-label">Montant dû</span>
                                            <span class="stat-value"><?php echo number_format($frais['montant_frais'], 0, ',', ' '); ?> FC</span>
                                        </div>
                                        <div class="finance-item-stat">
                                            <span class="stat-label">Payé</span>
                                            <span class="stat-value"><?php echo number_format($frais['montant_paye'], 0, ',', ' '); ?> FC</span>
                                        </div>
                                        <div class="finance-item-stat">
                                            <span class="stat-label">Reste</span>
                                            <span class="stat-value <?php echo $frais['reste'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo number_format($frais['reste'], 0, ',', ' '); ?> FC
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Aucune information financière disponible pour cet élève.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Historique des inscriptions -->
            <?php if (!empty($inscriptions)): ?>
            <div class="row g-4">
                <div class="col-12">
                    <div class="info-card">
                        <h5><i class="bi bi-clock-history me-2"></i>Historique des inscriptions</h5>
                        
                        <div class="inscription-timeline-modern">
                            <?php foreach ($inscriptions as $inscription): ?>
                                <div class="timeline-item-modern">
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($inscription['nom_classe']); ?> (<?php echo htmlspecialchars($inscription['niveau']); ?>)</h6>
                                            <span class="status-badge status-<?php echo strtolower($inscription['statut_inscription']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $inscription['statut_inscription'])); ?>
                                            </span>
                                        </div>
                                        <div class="timeline-details">
                                            <?php if (!empty($inscription['niveau_detaille'])): ?>
                                            <p class="mb-1">
                                                <i class="bi bi-mortarboard me-2"></i>
                                                Niveau détaillé: <?php echo htmlspecialchars($inscription['niveau_detaille']); ?>
                                            </p>
                                            <?php endif; ?>
                                            <p class="mb-1">
                                                <i class="bi bi-calendar me-2"></i>
                                                Année: <?php echo date('Y'); ?>-<?php echo date('Y') + 1; ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-2"></i>
                                                Inscrit le: <?php echo date('d/m/Y', strtotime($inscription['date_inscription'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
