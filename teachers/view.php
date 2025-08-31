<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier l'ID de l'enseignant
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID d\'enseignant invalide.');
    redirect('index.php');
}

$enseignant_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

try {
    // Récupérer les détails de l'enseignant
    $enseignant_query = "SELECT e.*, u1.prenom as created_by_prenom, u1.nom as created_by_nom,
                                u2.prenom as updated_by_prenom, u2.nom as updated_by_nom
                         FROM enseignants e
                         LEFT JOIN utilisateurs u1 ON e.created_by = u1.id
                         LEFT JOIN utilisateurs u2 ON e.updated_by = u2.id
                         WHERE e.id = :enseignant_id AND e.ecole_id = :ecole_id";
    
    $stmt = $db->prepare($enseignant_query);
    $stmt->execute(['enseignant_id' => $enseignant_id, 'ecole_id' => $_SESSION['ecole_id']]);
    $enseignant = $stmt->fetch();
    
    if (!$enseignant) {
        setFlashMessage('error', 'Enseignant non trouvé.');
        redirect('index.php');
    }
    
    // Récupérer les matières assignées
    $matieres_query = "SELECT cc.*, c.nom_cours, c.code_cours, c.coefficient,
                              cl.nom_classe, cl.niveau, cl.cycle
                       FROM classe_cours cc
                       JOIN cours c ON cc.cours_id = c.id
                       JOIN classes cl ON cc.classe_id = cl.id
                       WHERE cc.enseignant_id = :enseignant_id AND cc.statut = 'actif'
                       ORDER BY cl.cycle ASC, cl.niveau ASC, cl.nom_classe ASC";
    
    $stmt = $db->prepare($matieres_query);
    $stmt->execute(['enseignant_id' => $enseignant_id]);
    $matieres_assignees = $stmt->fetchAll();
    
    // Récupérer les statistiques
    $stats_query = "SELECT 
                        COUNT(DISTINCT cc.id) as total_cours,
                        COUNT(DISTINCT cc.classe_id) as total_classes,
                        COUNT(DISTINCT cc.cours_id) as total_matieres
                     FROM classe_cours cc
                     WHERE cc.enseignant_id = :enseignant_id AND cc.statut = 'actif'";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute(['enseignant_id' => $enseignant_id]);
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération des données: ' . $e->getMessage());
    redirect('index.php');
}

$page_title = "Enseignant : " . $enseignant['prenom'] . ' ' . $enseignant['nom'];
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
    
    <style>
        .enseignant-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .specialite-tag {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
            border-radius: 15px;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            display: inline-block;
        }
        
        .photo-enseignant {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            border: 4px solid #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .matiere-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .matiere-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #0077b6;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #6c757d;
            margin-bottom: 1rem;
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
                <h1><i class="bi bi-person-workspace me-2"></i>Détails de l'enseignant</h1>
                <p class="text-muted">Informations complètes sur l'enseignant</p>
            </div>
            
            <div class="topbar-actions">
                <a href="edit.php?id=<?php echo $enseignant_id; ?>" class="btn btn-outline-primary me-2">
                    <i class="bi bi-pencil me-2"></i>Modifier
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <?php
            $flash_messages = getFlashMessages();
            foreach ($flash_messages as $type => $message):
            ?>
                <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            
            <!-- En-tête de l'enseignant -->
            <div class="enseignant-header">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <?php if (!empty($enseignant['photo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($enseignant['photo_path']); ?>" 
                                 alt="Photo de <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>"
                                 class="photo-enseignant">
                        <?php else: ?>
                            <div class="photo-placeholder">
                                <?php echo strtoupper(substr($enseignant['prenom'], 0, 1) . substr($enseignant['nom'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h1 class="mb-2">
                            <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                        </h1>
                        <p class="mb-1">
                            <strong>Matricule:</strong> <?php echo htmlspecialchars($enseignant['matricule_enseignant']); ?> |
                            <strong>Statut:</strong> 
                            <span class="badge <?php echo $enseignant['statut_record'] === 'actif' ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo ucfirst($enseignant['statut_record']); ?>
                            </span>
                        </p>
                        <?php if (!empty($enseignant['email'])): ?>
                            <p class="mb-0">
                                <i class="bi bi-envelope me-2"></i>
                                <?php echo htmlspecialchars($enseignant['email']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3 text-md-end">
                        <div class="btn-group-vertical">
                            <a href="edit.php?id=<?php echo $enseignant_id; ?>" class="btn btn-outline-light mb-2">
                                <i class="bi bi-pencil me-2"></i>Modifier
                            </a>
                            <a href="../matieres/assign.php?enseignant_id=<?php echo $enseignant_id; ?>" class="btn btn-outline-light">
                                <i class="bi bi-book me-2"></i>Affecter des matières
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3 class="mb-1"><?php echo $stats['total_cours']; ?></h3>
                        <p class="mb-0">Cours assignés</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3 class="mb-1"><?php echo $stats['total_classes']; ?></h3>
                        <p class="mb-0">Classes</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h3 class="mb-1"><?php echo $stats['total_matieres']; ?></h3>
                        <p class="mb-0">Matières</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Informations personnelles -->
                <div class="col-lg-6">
                    <div class="info-card p-4">
                        <h4 class="mb-4">
                            <i class="bi bi-person me-2"></i>Informations personnelles
                        </h4>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="info-label">Nom</div>
                                <div class="info-value"><?php echo htmlspecialchars($enseignant['nom']); ?></div>
                            </div>
                            <div class="col-6">
                                <div class="info-label">Prénom</div>
                                <div class="info-value"><?php echo htmlspecialchars($enseignant['prenom']); ?></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="info-label">Sexe</div>
                                <div class="info-value">
                                    <?php 
                                    switch($enseignant['sexe']) {
                                        case 'M': echo 'Masculin'; break;
                                        case 'F': echo 'Féminin'; break;
                                        default: echo htmlspecialchars($enseignant['sexe']); break;
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-label">Nationalité</div>
                                <div class="info-value"><?php echo htmlspecialchars($enseignant['nationalite']); ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($enseignant['date_naissance'])): ?>
                        <div class="row">
                            <div class="col-6">
                                <div class="info-label">Date de naissance</div>
                                <div class="info-value"><?php echo date('d/m/Y', strtotime($enseignant['date_naissance'])); ?></div>
                            </div>
                            <?php if (!empty($enseignant['lieu_naissance'])): ?>
                            <div class="col-6">
                                <div class="info-label">Lieu de naissance</div>
                                <div class="info-value"><?php echo htmlspecialchars($enseignant['lieu_naissance']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Informations de contact -->
                <div class="col-lg-6">
                    <div class="info-card p-4">
                        <h4 class="mb-4">
                            <i class="bi bi-envelope me-2"></i>Informations de contact
                        </h4>
                        
                        <?php if (!empty($enseignant['telephone'])): ?>
                        <div class="info-label">Téléphone</div>
                        <div class="info-value">
                            <i class="bi bi-telephone me-2"></i>
                            <?php echo htmlspecialchars($enseignant['telephone']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($enseignant['email'])): ?>
                        <div class="info-label">Email</div>
                        <div class="info-value">
                            <i class="bi bi-envelope me-2"></i>
                            <?php echo htmlspecialchars($enseignant['email']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($enseignant['adresse_complete'])): ?>
                        <div class="info-label">Adresse</div>
                        <div class="info-value">
                            <i class="bi bi-geo-alt me-2"></i>
                            <?php echo nl2br(htmlspecialchars($enseignant['adresse_complete'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Informations professionnelles -->
            <div class="info-card p-4">
                <h4 class="mb-4">
                    <i class="bi bi-briefcase me-2"></i>Informations professionnelles
                </h4>
                
                <div class="row">
                    <?php if (!empty($enseignant['specialites'])): ?>
                    <div class="col-12 mb-3">
                        <div class="info-label">Spécialités</div>
                        <div class="info-value">
                            <?php 
                            $specialites = json_decode($enseignant['specialites'], true);
                            if (is_array($specialites)):
                                foreach ($specialites as $specialite):
                            ?>
                                <span class="specialite-tag"><?php echo htmlspecialchars($specialite); ?></span>
                            <?php 
                                endforeach;
                            endif;
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($enseignant['diplomes'])): ?>
                    <div class="col-md-6">
                        <div class="info-label">Diplômes</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($enseignant['diplomes'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($enseignant['experience_annees'])): ?>
                    <div class="col-md-6">
                        <div class="info-label">Expérience</div>
                        <div class="info-value"><?php echo $enseignant['experience_annees']; ?> an(s)</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($enseignant['date_embauche'])): ?>
                    <div class="col-md-6">
                        <div class="info-label">Date d'embauche</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($enseignant['date_embauche'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($enseignant['notes_internes'])): ?>
                <div class="mt-3">
                    <div class="info-label">Notes internes</div>
                    <div class="info-value">
                        <div class="alert alert-info">
                            <?php echo nl2br(htmlspecialchars($enseignant['notes_internes'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Matières assignées -->
            <div class="info-card p-4">
                <h4 class="mb-4">
                    <i class="bi bi-book me-2"></i>Matières assignées
                    <span class="badge bg-primary ms-2"><?php echo count($matieres_assignees); ?></span>
                </h4>
                
                <?php if (empty($matieres_assignees)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-book display-4 text-muted"></i>
                        <h5 class="mt-3">Aucune matière assignée</h5>
                        <p class="text-muted">Cet enseignant n'a pas encore de matières assignées.</p>
                        <a href="../matieres/assign.php?enseignant_id=<?php echo $enseignant_id; ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Assigner des matières
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($matieres_assignees as $matiere): ?>
                            <div class="col-lg-6">
                                <div class="matiere-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($matiere['nom_cours']); ?></h6>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($matiere['code_cours']); ?></span>
                                    </div>
                                    
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <small class="text-muted d-block">Classe</small>
                                            <strong><?php echo htmlspecialchars($matiere['nom_classe']); ?></strong>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">Niveau</small>
                                            <strong><?php echo htmlspecialchars($matiere['niveau']); ?></strong>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">Coefficient</small>
                                            <strong><?php echo $matiere['coefficient_classe'] ?: $matiere['coefficient']; ?></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo htmlspecialchars($matiere['annee_scolaire']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Métadonnées -->
            <div class="info-card p-4">
                <h4 class="mb-4">
                    <i class="bi bi-info-circle me-2"></i>Métadonnées
                </h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-label">Créé par</div>
                        <div class="info-value">
                            <?php if ($enseignant['created_by_prenom'] && $enseignant['created_by_nom']): ?>
                                <?php echo htmlspecialchars($enseignant['created_by_prenom'] . ' ' . $enseignant['created_by_nom']); ?>
                            <?php else: ?>
                                <em>Non spécifié</em>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-label">Date de création</div>
                        <div class="info-value">
                            <?php echo date('d/m/Y H:i', strtotime($enseignant['created_at'])); ?>
                        </div>
                    </div>
                    
                    <?php if ($enseignant['updated_by']): ?>
                    <div class="col-md-6">
                        <div class="info-label">Modifié par</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($enseignant['updated_by_prenom'] . ' ' . $enseignant['updated_by_nom']); ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-label">Dernière modification</div>
                        <div class="info-value">
                            <?php echo date('d/m/Y H:i', strtotime($enseignant['updated_at'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

