<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$bulletin = null;
$bulletin_lignes = [];

// Récupérer l'ID du bulletin depuis l'URL
$bulletin_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$bulletin_id) {
    redirect('index.php');
}

// Récupérer les informations du bulletin
try {
    $bulletin_query = "SELECT b.*, e.nom, e.prenom, e.matricule, e.date_naissance, e.sexe,
                              c.nom_classe, c.niveau, c.cycle,
                              u.nom as generateur_nom, u.prenom as generateur_prenom,
                              uv.nom as validateur_nom, uv.prenom as validateur_prenom
                       FROM bulletins b
                       JOIN eleves e ON b.eleve_id = e.id
                       JOIN classes c ON b.classe_id = c.id
                       LEFT JOIN utilisateurs u ON b.genere_par = u.id
                       LEFT JOIN utilisateurs uv ON b.valide_par = uv.id
                       WHERE b.id = :bulletin_id 
                       AND c.ecole_id = :ecole_id 
                       AND b.statut = 'actif'";
    $bulletin_stmt = $db->prepare($bulletin_query);
    $bulletin_stmt->execute([
        'bulletin_id' => $bulletin_id,
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    $bulletin = $bulletin_stmt->fetch();
    
    if (!$bulletin) {
        throw new Exception("Bulletin non trouvé ou non autorisé.");
    }
    
    // Récupérer les lignes du bulletin (notes par matière)
    $lignes_query = "SELECT bl.*, c.code_cours, c.nom_cours, c.coefficient
                     FROM bulletin_lignes bl
                     JOIN cours c ON bl.cours_id = c.id
                     WHERE bl.bulletin_id = :bulletin_id 
                     AND bl.statut = 'actif'
                     ORDER BY c.coefficient DESC, c.nom_cours";
    $lignes_stmt = $db->prepare($lignes_query);
    $lignes_stmt->execute(['bulletin_id' => $bulletin_id]);
    $bulletin_lignes = $lignes_stmt->fetchAll();
    
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// Traitement de la validation du bulletin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'valider_bulletin') {
        try {
            $update_query = "UPDATE bulletins 
                            SET valide = 1, valide_par = :valide_par, date_validation = NOW(), 
                                updated_by = :updated_by, updated_at = NOW()
                            WHERE id = :bulletin_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([
                'valide_par' => $_SESSION['user_id'],
                'updated_by' => $_SESSION['user_id'],
                'bulletin_id' => $bulletin_id
            ]);
            
            // Recharger les informations du bulletin
            $bulletin_stmt->execute([
                'bulletin_id' => $bulletin_id,
                'ecole_id' => $_SESSION['ecole_id']
            ]);
            $bulletin = $bulletin_stmt->fetch();
            
            $success = "Bulletin validé avec succès !";
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la validation : " . $e->getMessage();
        }
    }
}

$page_title = "Bulletin de " . ($bulletin['nom'] ?? '') . " " . ($bulletin['prenom'] ?? '');
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
        .bulletin-header {
            background: linear-gradient(135deg, #0077b6 0%, #005577 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .bulletin-content {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        .bulletin-section {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        .bulletin-section:last-child {
            border-bottom: none;
        }
        .grade-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .grade-row:hover {
            background-color: #f8f9fa;
        }
        .print-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }
        @media print {
            .sidebar, .topbar, .print-button, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .bulletin-content {
                box-shadow: none;
                border: 1px solid #ddd;
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
            <li class="menu-item">
                <a href="../finance/" class="menu-link">
                    <i class="bi bi-cash-coin"></i>
                    <span>Finances</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
            <li class="menu-item active">
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
                <h1><i class="bi bi-journal-text me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Visualisation du bulletin scolaire</p>
            </div>
            
            <div class="topbar-actions">
                <a href="bulletins.php?annee_id=<?php echo $bulletin['annee_scolaire_id'] ?? 1; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i>
                    <span>Retour</span>
                </a>
                
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
        
        <!-- Contenu principal -->
        <div class="content-area">
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($bulletin): ?>
                <!-- En-tête du bulletin -->
                <div class="bulletin-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-2">
                                <i class="bi bi-journal-text me-3"></i>
                                BULLETIN SCOLAIRE
                            </h2>
                            <h4 class="mb-1"><?php echo htmlspecialchars($bulletin['nom'] . ' ' . $bulletin['prenom']); ?></h4>
                            <p class="mb-0">
                                <strong>Matricule :</strong> <?php echo htmlspecialchars($bulletin['matricule']); ?> | 
                                <strong>Classe :</strong> <?php echo htmlspecialchars($bulletin['niveau'] . ' - ' . $bulletin['nom_classe']); ?> | 
                                <strong>Période :</strong> <?php echo htmlspecialchars($bulletin['periode']); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="bulletin-status">
                                <?php if ($bulletin['valide']): ?>
                                    <span class="badge bg-success fs-6">VALIDÉ</span>
                                <?php else: ?>
                                    <span class="badge bg-warning fs-6">EN ATTENTE</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contenu du bulletin -->
                <div class="bulletin-content">
                    <!-- Informations de l'élève -->
                    <div class="bulletin-section">
                        <h5 class="section-title">
                            <i class="bi bi-person-badge me-2"></i>Informations de l'élève
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nom complet :</strong> <?php echo htmlspecialchars($bulletin['nom'] . ' ' . $bulletin['prenom']); ?></p>
                                <p><strong>Matricule :</strong> <?php echo htmlspecialchars($bulletin['matricule']); ?></p>
                                <p><strong>Date de naissance :</strong> <?php echo $bulletin['date_naissance'] ? date('d/m/Y', strtotime($bulletin['date_naissance'])) : '-'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Sexe :</strong> <?php echo $bulletin['sexe'] === 'M' ? 'Masculin' : ($bulletin['sexe'] === 'F' ? 'Féminin' : 'Autre'); ?></p>
                                <p><strong>Classe :</strong> <?php echo htmlspecialchars($bulletin['niveau'] . ' - ' . $bulletin['nom_classe']); ?></p>
                                <p><strong>Cycle :</strong> <?php echo ucfirst(htmlspecialchars($bulletin['cycle'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes par matière -->
                    <div class="bulletin-section">
                        <h5 class="section-title">
                            <i class="bi bi-list-check me-2"></i>Notes par matière
                        </h5>
                        <?php if (empty($bulletin_lignes)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox display-4 text-muted"></i>
                                <p class="text-muted mt-2">Aucune note disponible pour cette période</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover grade-table">
                                    <thead>
                                        <tr>
                                            <th>Matière</th>
                                            <th>Coefficient</th>
                                            <th>Moyenne</th>
                                            <th>Rang</th>
                                            <th>Moyenne pondérée</th>
                                            <th>Appréciation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bulletin_lignes as $ligne): ?>
                                            <tr class="grade-row">
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($ligne['nom_cours']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($ligne['code_cours']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $ligne['coefficient']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($ligne['moyenne_matiere']): ?>
                                                        <span class="fw-bold <?php echo $ligne['moyenne_matiere'] >= 10 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo number_format($ligne['moyenne_matiere'], 2); ?>/20
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($ligne['rang_matiere']): ?>
                                                        <span class="badge bg-info"><?php echo $ligne['rang_matiere']; ?><?php echo $ligne['rang_matiere'] == 1 ? 'er' : 'ème'; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($ligne['moyenne_ponderee']): ?>
                                                        <span class="fw-bold"><?php echo number_format($ligne['moyenne_ponderee'], 2); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($ligne['appreciation']): ?>
                                                        <small><?php echo htmlspecialchars($ligne['appreciation']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Résultats généraux -->
                    <div class="bulletin-section">
                        <h5 class="section-title">
                            <i class="bi bi-calculator me-2"></i>Résultats généraux
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="result-item">
                                    <h6>Moyenne générale</h6>
                                    <?php if ($bulletin['moyenne_generale']): ?>
                                        <div class="result-value <?php echo $bulletin['moyenne_generale'] >= 10 ? 'text-success' : 'text-danger'; ?>">
                                            <span class="display-6 fw-bold"><?php echo number_format($bulletin['moyenne_generale'], 2); ?></span>
                                            <span class="fs-5">/20</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="result-value text-muted">
                                            <span class="display-6">-</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="result-item">
                                    <h6>Rang dans la classe</h6>
                                    <?php if ($bulletin['rang_classe']): ?>
                                        <div class="result-value">
                                            <span class="display-6 fw-bold text-primary"><?php echo $bulletin['rang_classe']; ?><?php echo $bulletin['rang_classe'] == 1 ? 'er' : 'ème'; ?></span>
                                            <span class="fs-6 text-muted">sur <?php echo $bulletin['effectif_classe']; ?> élèves</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="result-value text-muted">
                                            <span class="display-6">-</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($bulletin['mention']): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="result-item">
                                        <h6>Mention</h6>
                                        <div class="result-value">
                                            <span class="badge bg-success fs-5"><?php echo ucfirst(str_replace('_', ' ', $bulletin['mention'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Observations et recommandations -->
                    <?php if ($bulletin['observations_conseil'] || $bulletin['recommandations']): ?>
                        <div class="bulletin-section">
                            <h5 class="section-title">
                                <i class="bi bi-chat-text me-2"></i>Observations et recommandations
                            </h5>
                            <?php if ($bulletin['observations_conseil']): ?>
                                <div class="mb-3">
                                    <h6>Observations du conseil de classe</h6>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($bulletin['observations_conseil'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($bulletin['recommandations']): ?>
                                <div class="mb-3">
                                    <h6>Recommandations</h6>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($bulletin['recommandations'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Informations de génération -->
                    <div class="bulletin-section bg-light">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>Généré le :</strong> 
                                    <?php echo $bulletin['genere_le'] ? date('d/m/Y à H:i', strtotime($bulletin['genere_le'])) : '-'; ?>
                                </small>
                                <br>
                                <small class="text-muted">
                                    <strong>Par :</strong> 
                                    <?php echo $bulletin['generateur_nom'] ? htmlspecialchars($bulletin['generateur_nom'] . ' ' . $bulletin['generateur_prenom']) : '-'; ?>
                                </small>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <?php if ($bulletin['valide']): ?>
                                    <small class="text-muted">
                                        <strong>Validé le :</strong> 
                                        <?php echo $bulletin['date_validation'] ? date('d/m/Y à H:i', strtotime($bulletin['date_validation'])) : '-'; ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <strong>Par :</strong> 
                                        <?php echo $bulletin['validateur_nom'] ? htmlspecialchars($bulletin['validateur_nom'] . ' ' . $bulletin['validateur_prenom']) : '-'; ?>
                                    </small>
                                <?php else: ?>
                                    <?php if (hasRole(['admin', 'direction'])): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="valider_bulletin">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="bi bi-check-circle me-1"></i>Valider le bulletin
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bouton d'impression -->
                <button class="btn btn-primary print-button" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i>Imprimer
                </button>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-exclamation-triangle display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">Bulletin non trouvé</h4>
                    <p class="text-muted">Le bulletin demandé n'existe pas ou vous n'avez pas les permissions pour y accéder.</p>
                    <a href="bulletins.php" class="btn btn-primary">Retour aux bulletins</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
