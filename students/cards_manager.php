<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

try {
    // Récupérer toutes les classes avec le nombre d'élèves
    $classes_query = "SELECT c.id, c.nom as classe_nom, c.description,
                             COUNT(DISTINCT i.eleve_id) as nombre_eleves,
                             n.nom as niveau_nom, s.nom as section_nom
                      FROM classes c
                      LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut = 'validée'
                      LEFT JOIN niveaux n ON c.niveau_id = n.id
                      LEFT JOIN sections s ON c.section_id = s.id
                      WHERE c.ecole_id = :ecole_id AND c.statut = 'active'
                      GROUP BY c.id
                      ORDER BY n.ordre, c.nom_classe";
    
    $stmt = $db->prepare($classes_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classes = $stmt->fetchAll();
    
    // Statistiques générales
    $stats_query = "SELECT 
                        COUNT(DISTINCT e.id) as total_eleves,
                        COUNT(DISTINCT c.id) as total_classes,
                        COUNT(DISTINCT i.id) as total_inscriptions
                    FROM eleves e
                    LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'validée'
                    LEFT JOIN classes c ON i.classe_id = c.id AND c.statut = 'active'
                    WHERE e.ecole_id = :ecole_id AND e.statut = 'actif'";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    $classes = [];
    $stats = ['total_eleves' => 0, 'total_classes' => 0, 'total_inscriptions' => 0];
    error_log("Erreur lors de la récupération des classes: " . $e->getMessage());
}

$page_title = "Gestion des Cartes d'Élèves";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Naklass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .class-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .class-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .badge-students {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
        }
        
        .btn-cards {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-cards:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 20px;
            margin-bottom: 30px;
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
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
                <h1><i class="bi bi-card-heading me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Générez et imprimez les cartes d'élèves par classe</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour aux Élèves
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Section héro -->
            <div class="hero-section p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="h4 mb-3">Système de Cartes d'Élèves</h2>
                        <p class="text-muted mb-0">
                            Générez des cartes d'élèves modernes et professionnelles avec QR codes. 
                            Exportez en PDF ou imprimez directement avec un layout optimisé (9 cartes par page A4).
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-qr-code"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="display-6 mb-2">
                                <i class="bi bi-people"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['total_eleves']; ?></h3>
                            <p class="mb-0">Élèves Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="display-6 mb-2">
                                <i class="bi bi-building"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['total_classes']; ?></h3>
                            <p class="mb-0">Classes Actives</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="display-6 mb-2">
                                <i class="bi bi-card-heading"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['total_inscriptions']; ?></h3>
                            <p class="mb-0">Cartes Disponibles</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Actions Rapides</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-grid">
                                        <button onclick="generateAllCards()" class="btn btn-cards btn-lg">
                                            <i class="bi bi-collection me-2"></i>
                                            Générer Toutes les Cartes
                                            <small class="d-block mt-1">PDF avec toutes les cartes de l'école</small>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-grid">
                                        <a href="add.php" class="btn btn-outline-primary btn-lg">
                                            <i class="bi bi-person-plus me-2"></i>
                                            Ajouter un Nouvel Élève
                                            <small class="d-block mt-1">Créer un profil et générer sa carte</small>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Liste des classes -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>Cartes par Classe</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($classes)): ?>
                        <div class="text-center p-5">
                            <i class="bi bi-building display-4 text-muted mb-3"></i>
                            <h6>Aucune classe trouvée</h6>
                            <p class="text-muted">Créez d'abord des classes et inscrivez des élèves pour générer des cartes.</p>
                            <a href="../classes/create.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Créer une Classe
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($classes as $classe): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card class-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($classe['classe_nom']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($classe['niveau_nom']); ?>
                                                        <?php if ($classe['section_nom']): ?>
                                                            - <?php echo htmlspecialchars($classe['section_nom']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <span class="badge badge-students">
                                                    <?php echo $classe['nombre_eleves']; ?> élèves
                                                </span>
                                            </div>
                                            
                                            <?php if ($classe['description']): ?>
                                                <p class="card-text small text-muted mb-3">
                                                    <?php echo htmlspecialchars($classe['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="d-grid gap-2">
                                                <?php if ($classe['nombre_eleves'] > 0): ?>
                                                    <a href="generate_card.php?mode=class&class_id=<?php echo $classe['id']; ?>" 
                                                       class="btn btn-cards btn-sm">
                                                        <i class="bi bi-card-list me-2"></i>
                                                        Générer les Cartes
                                                    </a>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="../classes/view.php?id=<?php echo $classe['id']; ?>" 
                                                           class="btn btn-outline-secondary">
                                                            <i class="bi bi-eye me-1"></i>Voir
                                                        </a>
                                                        <a href="../classes/students.php?class_id=<?php echo $classe['id']; ?>" 
                                                           class="btn btn-outline-secondary">
                                                            <i class="bi bi-people me-1"></i>Élèves
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center py-2">
                                                        <small class="text-muted">Aucun élève inscrit</small>
                                                        <br>
                                                        <a href="../classes/students.php?class_id=<?php echo $classe['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm mt-2">
                                                            <i class="bi bi-plus me-1"></i>Inscrire des élèves
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Guide d'utilisation -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-question-circle me-2"></i>Guide d'Utilisation</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-1-circle text-primary me-2"></i>Génération de Cartes</h6>
                            <ul class="list-unstyled mb-4">
                                <li>• Sélectionnez une classe pour générer toutes ses cartes</li>
                                <li>• Ou utilisez "Générer Toutes les Cartes" pour l'école entière</li>
                                <li>• Les cartes incluent photo, QR code et informations essentielles</li>
                            </ul>
                            
                            <h6><i class="bi bi-2-circle text-primary me-2"></i>Options d'Export</h6>
                            <ul class="list-unstyled mb-4">
                                <li>• <strong>PDF :</strong> Format optimisé, 9 cartes par page A4</li>
                                <li>• <strong>Impression :</strong> Layout automatique pour l'impression</li>
                                <li>• <strong>Images :</strong> Téléchargement individuel en PNG</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-3-circle text-primary me-2"></i>Qualité d'Impression</h6>
                            <ul class="list-unstyled mb-4">
                                <li>• Utilisez du papier cartonné (200-300g/m²)</li>
                                <li>• Résolution optimisée pour une impression nette</li>
                                <li>• Plastification recommandée pour la durabilité</li>
                            </ul>
                            
                            <h6><i class="bi bi-4-circle text-primary me-2"></i>Code QR</h6>
                            <ul class="list-unstyled mb-4">
                                <li>• Contient l'identité numérique sécurisée de l'élève</li>
                                <li>• Permet une identification rapide</li>
                                <li>• Compatible avec tout lecteur QR standard</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        function generateAllCards() {
            // Récupérer tous les IDs d'élèves
            fetch('get_all_students.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.students.length > 0) {
                        const studentIds = data.students.map(s => s.id).join(',');
                        window.open(`generate_card.php?mode=multiple&ids=${studentIds}`, '_blank');
                    } else {
                        alert('Aucun élève trouvé dans l\'école.');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la récupération des élèves.');
                });
        }
        
        // Animation des cartes au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.class-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>

