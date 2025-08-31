<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier l'ID de la matière
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de matière invalide.');
    redirect('index.php');
}

$cours_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la matière
try {
    $cours_query = "SELECT c.*, u1.prenom as created_by_prenom, u1.nom as created_by_nom,
                            u2.prenom as updated_by_prenom, u2.nom as updated_by_nom
                     FROM cours c
                     LEFT JOIN utilisateurs u1 ON c.created_by = u1.id
                     LEFT JOIN utilisateurs u2 ON c.updated_by = u2.id
                     WHERE c.id = :cours_id AND c.ecole_id = :ecole_id";
    
    $stmt = $db->prepare($cours_query);
    $stmt->execute(['cours_id' => $cours_id, 'ecole_id' => $_SESSION['ecole_id']]);
    $cours = $stmt->fetch();
    
    if (!$cours) {
        setFlashMessage('error', 'Matière non trouvée.');
        redirect('index.php');
    }
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération de la matière: ' . $e->getMessage());
    redirect('index.php');
}

// Récupérer les affectations de la matière
try {
    $assignments_query = "SELECT cc.*, c.nom_classe, c.niveau, c.cycle, c.annee_scolaire,
                                e.nom as enseignant_nom, e.prenom as enseignant_prenom,
                                COUNT(DISTINCT i.eleve_id) as nombre_eleves
                         FROM classe_cours cc
                         JOIN classes c ON cc.classe_id = c.id
                         JOIN enseignants e ON cc.enseignant_id = e.id
                         LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut = 'actif'
                         WHERE cc.cours_id = :cours_id AND cc.statut = 'actif'
                         GROUP BY cc.id
                         ORDER BY c.cycle ASC, c.niveau ASC, c.nom_classe ASC";
    
    $stmt = $db->prepare($assignments_query);
    $stmt->execute(['cours_id' => $cours_id]);
    $assignments = $stmt->fetchAll();
} catch (Exception $e) {
    $assignments = [];
    setFlashMessage('error', 'Erreur lors de la récupération des affectations: ' . $e->getMessage());
}

// Récupérer les statistiques d'utilisation
try {
    $stats_query = "SELECT 
                        COUNT(DISTINCT cc.classe_id) as nombre_classes,
                        COUNT(DISTINCT cc.enseignant_id) as nombre_enseignants,
                        AVG(cc.coefficient_classe) as coefficient_moyen,
                        SUM(CASE WHEN cc.statut = 'actif' THEN 1 ELSE 0 END) as affectations_actives
                    FROM classe_cours cc
                    WHERE cc.cours_id = :cours_id";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute(['cours_id' => $cours_id]);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = ['nombre_classes' => 0, 'nombre_enseignants' => 0, 'coefficient_moyen' => 0, 'affectations_actives' => 0];
}

$page_title = $cours['nom_cours'];
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
        .matiere-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .assignment-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .assignment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .coefficient-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
        }
        
        .cycle-badge {
            font-size: 0.75rem;
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
                <h1><i class="bi bi-book me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Détails et affectations de la matière</p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Matières</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($cours['nom_cours']); ?></li>
                    </ol>
                </nav>
            </div>
            
            <div class="topbar-actions">
                <a href="edit.php?id=<?php echo $cours_id; ?>" class="btn btn-outline-primary me-2">
                    <i class="bi bi-pencil me-2"></i>Modifier
                </a>
                <a href="assign.php?id=<?php echo $cours_id; ?>" class="btn btn-success me-2">
                    <i class="bi bi-link-45deg me-2"></i>Affecter
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </header>
        
        <!-- Contenu principal -->
        <div class="container-fluid">
            <!-- En-tête de la matière -->
            <div class="matiere-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2"><?php echo htmlspecialchars($cours['nom_cours']); ?></h2>
                        <p class="mb-3 opacity-75">
                            <span class="badge bg-light text-dark me-2 fs-6"><?php echo htmlspecialchars($cours['code_cours']); ?></span>
                            <span class="badge bg-success me-2 fs-6">Coeff: <?php echo $cours['coefficient']; ?></span>
                            <span class="badge bg-info fs-6">Barème: <?php echo $cours['bareme_max']; ?></span>
                        </p>
                        <?php if ($cours['description']): ?>
                            <p class="mb-0 opacity-90"><?php echo htmlspecialchars($cours['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="d-flex flex-column align-items-md-end">
                            <span class="badge bg-light text-dark mb-2">
                                <i class="bi bi-building me-1"></i><?php echo $stats['nombre_classes']; ?> classe(s)
                            </span>
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-person-workspace me-1"></i><?php echo $stats['nombre_enseignants']; ?> enseignant(s)
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-primary rounded-circle p-3">
                                    <i class="bi bi-building text-white fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="mb-1"><?php echo $stats['nombre_classes']; ?></h3>
                                <p class="text-muted mb-0">Classes</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-success rounded-circle p-3">
                                    <i class="bi bi-person-workspace text-white fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="mb-1"><?php echo $stats['nombre_enseignants']; ?></h3>
                                <p class="text-muted mb-0">Enseignants</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-info rounded-circle p-3">
                                    <i class="bi bi-calculator text-white fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="mb-1"><?php echo number_format($stats['coefficient_moyen'], 2); ?></h3>
                                <p class="text-muted mb-0">Coeff. Moyen</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="bg-warning rounded-circle p-3">
                                    <i class="bi bi-check-circle text-white fs-4"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="mb-1"><?php echo $stats['affectations_actives']; ?></h3>
                                <p class="text-muted mb-0">Actives</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Détails de la matière -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle me-2"></i>Informations Détaillées
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Code de la matière</label>
                                <p class="mb-0"><?php echo htmlspecialchars($cours['code_cours']); ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Coefficient par défaut</label>
                                <p class="mb-0"><?php echo $cours['coefficient']; ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Barème maximum</label>
                                <p class="mb-0"><?php echo $cours['bareme_max']; ?></p>
                            </div>
                            
                            <?php if ($cours['volume_horaire_hebdo']): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Volume horaire hebdomadaire</label>
                                    <p class="mb-0"><?php echo $cours['volume_horaire_hebdo']; ?> heures/semaine</p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($cours['cycles_applicables']): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Cycles applicables</label>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php 
                                        $cycles = json_decode($cours['cycles_applicables'], true);
                                        if (is_array($cycles)):
                                            foreach ($cycles as $cycle):
                                        ?>
                                            <span class="badge bg-primary cycle-badge"><?php echo ucfirst($cycle); ?></span>
                                        <?php 
                                            endforeach;
                                        endif;
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($cours['ponderation_defaut']): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Pondération par défaut</label>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php 
                                        $ponderation = json_decode($cours['ponderation_defaut'], true);
                                        if (is_array($ponderation)):
                                            foreach ($ponderation as $type => $poids):
                                        ?>
                                            <span class="badge bg-secondary cycle-badge">
                                                <?php echo ucfirst($type); ?>: <?php echo $poids; ?>%
                                            </span>
                                        <?php 
                                            endforeach;
                                        endif;
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Statut</label>
                                <p class="mb-0">
                                    <span class="badge <?php echo $cours['statut'] === 'actif' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($cours['statut']); ?>
                                    </span>
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Créée le</label>
                                <p class="mb-0"><?php echo date('d/m/Y à H:i', strtotime($cours['created_at'])); ?></p>
                            </div>
                            
                            <?php if ($cours['created_by_prenom']): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Créée par</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($cours['created_by_prenom'] . ' ' . $cours['created_by_nom']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($cours['updated_at'] && $cours['updated_at'] !== $cours['created_at']): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Modifiée le</label>
                                    <p class="mb-0"><?php echo date('d/m/Y à H:i', strtotime($cours['updated_at'])); ?></p>
                                </div>
                                
                                <?php if ($cours['updated_by_prenom']): ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Modifiée par</label>
                                        <p class="mb-0"><?php echo htmlspecialchars($cours['updated_by_prenom'] . ' ' . $cours['updated_by_nom']); ?></p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($cours['notes_internes']): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Notes internes</label>
                                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($cours['notes_internes']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Affectations aux classes -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-link-45deg me-2"></i>Affectations aux Classes
                                <span class="badge bg-primary ms-2"><?php echo count($assignments); ?></span>
                            </h5>
                            <a href="assign.php?id=<?php echo $cours_id; ?>" class="btn btn-success btn-sm">
                                <i class="bi bi-plus-circle me-2"></i>Nouvelle Affectation
                            </a>
                        </div>
                        
                        <div class="card-body">
                            <?php if (empty($assignments)): ?>
                                <div class="text-center p-5">
                                    <i class="bi bi-link-45deg display-4 text-muted mb-3"></i>
                                    <h6>Aucune affectation</h6>
                                    <p class="text-muted">Cette matière n'est affectée à aucune classe pour le moment.</p>
                                    <a href="assign.php?id=<?php echo $cours_id; ?>" class="btn btn-success">
                                        <i class="bi bi-link-45deg me-2"></i>Première Affectation
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($assignments as $assignment): ?>
                                        <div class="col-lg-6 mb-3">
                                            <div class="card assignment-card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <div class="flex-grow-1">
                                                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($assignment['nom_classe']); ?></h6>
                                                            <div class="d-flex flex-wrap gap-1 mb-2">
                                                                <span class="badge bg-primary cycle-badge">
                                                                    <?php echo ucfirst($assignment['cycle']); ?>
                                                                </span>
                                                                <span class="badge bg-secondary cycle-badge">
                                                                    <?php echo htmlspecialchars($assignment['niveau']); ?>
                                                                </span>
                                                                <span class="badge bg-info cycle-badge">
                                                                    <?php echo htmlspecialchars($assignment['annee_scolaire']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <span class="badge coefficient-badge">
                                                            Coeff: <?php echo $assignment['coefficient_classe']; ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bi bi-person me-1"></i>
                                                            <strong>Enseignant :</strong> 
                                                            <?php echo htmlspecialchars($assignment['enseignant_prenom'] . ' ' . $assignment['enseignant_nom']); ?>
                                                        </small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bi bi-people me-1"></i>
                                                            <strong>Élèves :</strong> 
                                                            <?php echo $assignment['nombre_eleves']; ?> inscrit(s)
                                                        </small>
                                                    </div>
                                                    
                                                    <?php if ($assignment['ponderation_personnalisee']): ?>
                                                        <div class="mb-3">
                                                            <small class="text-muted">
                                                                <i class="bi bi-calculator me-1"></i>
                                                                <strong>Pondération personnalisée :</strong>
                                                            </small>
                                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                                <?php 
                                                                $ponderation = json_decode($assignment['ponderation_personnalisee'], true);
                                                                if (is_array($ponderation)):
                                                                    foreach ($ponderation as $type => $poids):
                                                                ?>
                                                                    <span class="badge bg-light text-dark cycle-badge">
                                                                        <?php echo ucfirst($type); ?>: <?php echo $poids; ?>%
                                                                    </span>
                                                                <?php 
                                                                    endforeach;
                                                                endif;
                                                                ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            Affectée le <?php echo date('d/m/Y', strtotime($assignment['created_at'])); ?>
                                                        </small>
                                                        
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="../classes/view.php?id=<?php echo $assignment['classe_id']; ?>" 
                                                               class="btn btn-outline-info" title="Voir la classe">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="../enseignants/view.php?id=<?php echo $assignment['enseignant_id']; ?>" 
                                                               class="btn btn-outline-primary" title="Voir l'enseignant">
                                                                <i class="bi bi-person"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>

