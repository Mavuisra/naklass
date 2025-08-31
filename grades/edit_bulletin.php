<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';
$bulletin = null;
$bulletin_lignes = [];

// Récupérer l'ID du bulletin depuis l'URL
$bulletin_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$bulletin_id) {
    redirect('index.php');
}

// Récupérer les informations du bulletin
try {
    $bulletin_query = "SELECT b.*, e.nom, e.prenom, e.matricule, c.nom_classe, c.niveau
                       FROM bulletins b
                       JOIN eleves e ON b.eleve_id = e.id
                       JOIN classes c ON b.classe_id = c.id
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
    
    // Récupérer les lignes du bulletin
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

// Traitement de la mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_bulletin') {
        try {
            $db->beginTransaction();
            
            // Mettre à jour le bulletin principal
            $update_bulletin = "UPDATE bulletins 
                               SET moyenne_generale = :moyenne_generale,
                                   rang_classe = :rang_classe,
                                   mention = :mention,
                                   decision = :decision,
                                   observations_conseil = :observations_conseil,
                                   recommandations = :recommandations,
                                   updated_by = :updated_by,
                                   updated_at = NOW()
                               WHERE id = :bulletin_id";
            
            $bulletin_stmt = $db->prepare($update_bulletin);
            $bulletin_stmt->execute([
                'moyenne_generale' => $_POST['moyenne_generale'] ?: null,
                'rang_classe' => $_POST['rang_classe'] ?: null,
                'mention' => $_POST['mention'] ?: null,
                'decision' => $_POST['decision'] ?: null,
                'observations_conseil' => $_POST['observations_conseil'] ?: null,
                'recommandations' => $_POST['recommandations'] ?: null,
                'updated_by' => $_SESSION['user_id'],
                'bulletin_id' => $bulletin_id
            ]);
            
            // Mettre à jour les lignes du bulletin
            if (isset($_POST['lignes']) && is_array($_POST['lignes'])) {
                foreach ($_POST['lignes'] as $ligne_id => $ligne_data) {
                    $update_ligne = "UPDATE bulletin_lignes 
                                    SET moyenne_matiere = :moyenne_matiere,
                                        rang_matiere = :rang_matiere,
                                        appreciation = :appreciation,
                                        updated_by = :updated_by,
                                        updated_at = NOW()
                                    WHERE id = :ligne_id AND bulletin_id = :bulletin_id";
                    
                    $ligne_stmt = $db->prepare($update_ligne);
                    $ligne_stmt->execute([
                        'moyenne_matiere' => $ligne_data['moyenne_matiere'] ?: null,
                        'rang_matiere' => $ligne_data['rang_matiere'] ?: null,
                        'appreciation' => $ligne_data['appreciation'] ?: null,
                        'updated_by' => $_SESSION['user_id'],
                        'ligne_id' => $ligne_id,
                        'bulletin_id' => $bulletin_id
                    ]);
                }
            }
            
            safeCommit($db);
            $success = "Bulletin mis à jour avec succès !";
            
            // Recharger les informations
            $bulletin_stmt->execute([
                'bulletin_id' => $bulletin_id,
                'ecole_id' => $_SESSION['ecole_id']
            ]);
            $bulletin = $bulletin_stmt->fetch();
            
            $lignes_stmt->execute(['bulletin_id' => $bulletin_id]);
            $bulletin_lignes = $lignes_stmt->fetchAll();
            
        } catch (Exception $e) {
            safeRollback($db);
            $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}

$page_title = "Modifier le bulletin de " . ($bulletin['nom'] ?? '') . " " . ($bulletin['prenom'] ?? '');
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
        .edit-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .edit-section-header {
            background-color: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            border-radius: 10px 10px 0 0;
        }
        .edit-section-body {
            padding: 1.5rem;
        }
        .grade-input {
            max-width: 100px;
        }
        .appreciation-input {
            min-height: 80px;
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
                <h1><i class="bi bi-pencil-square me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Modification du bulletin scolaire</p>
            </div>
            
            <div class="topbar-actions">
                <a href="view_bulletin.php?id=<?php echo $bulletin_id; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                    <span>Voir</span>
                </a>
                
                <a href="bulletins.php?annee_id=<?php echo $bulletin['annee_scolaire_id'] ?? 1; ?>" class="btn btn-outline-primary">
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
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($bulletin): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="update_bulletin">
                    
                    <!-- Informations de l'élève -->
                    <div class="edit-section">
                        <div class="edit-section-header">
                            <h5 class="mb-0">
                                <i class="bi bi-person-badge me-2"></i>Informations de l'élève
                            </h5>
                        </div>
                        <div class="edit-section-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Nom :</strong> <?php echo htmlspecialchars($bulletin['nom'] . ' ' . $bulletin['prenom']); ?></p>
                                    <p><strong>Matricule :</strong> <?php echo htmlspecialchars($bulletin['matricule']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Classe :</strong> <?php echo htmlspecialchars($bulletin['niveau'] . ' - ' . $bulletin['nom_classe']); ?></p>
                                    <p><strong>Période :</strong> <?php echo htmlspecialchars($bulletin['periode']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Résultats généraux -->
                    <div class="edit-section">
                        <div class="edit-section-header">
                            <h5 class="mb-0">
                                <i class="bi bi-calculator me-2"></i>Résultats généraux
                            </h5>
                        </div>
                        <div class="edit-section-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="moyenne_generale" class="form-label">Moyenne générale</label>
                                    <input type="number" class="form-control grade-input" id="moyenne_generale" 
                                           name="moyenne_generale" step="0.01" min="0" max="20"
                                           value="<?php echo $bulletin['moyenne_generale'] ?? ''; ?>">
                                    <div class="form-text">Note sur 20</div>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="rang_classe" class="form-label">Rang dans la classe</label>
                                    <input type="number" class="form-control" id="rang_classe" 
                                           name="rang_classe" min="1"
                                           value="<?php echo $bulletin['rang_classe'] ?? ''; ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="mention" class="form-label">Mention</label>
                                    <select class="form-select" id="mention" name="mention">
                                        <option value="">Sélectionner</option>
                                        <option value="excellent" <?php echo ($bulletin['mention'] ?? '') === 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                                        <option value="très_bien" <?php echo ($bulletin['mention'] ?? '') === 'très_bien' ? 'selected' : ''; ?>>Très bien</option>
                                        <option value="bien" <?php echo ($bulletin['mention'] ?? '') === 'bien' ? 'selected' : ''; ?>>Bien</option>
                                        <option value="assez_bien" <?php echo ($bulletin['mention'] ?? '') === 'assez_bien' ? 'selected' : ''; ?>>Assez bien</option>
                                        <option value="passable" <?php echo ($bulletin['mention'] ?? '') === 'passable' ? 'selected' : ''; ?>>Passable</option>
                                        <option value="insuffisant" <?php echo ($bulletin['mention'] ?? '') === 'insuffisant' ? 'selected' : ''; ?>>Insuffisant</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="decision" class="form-label">Décision</label>
                                    <select class="form-select" id="decision" name="decision">
                                        <option value="">Sélectionner</option>
                                        <option value="admis" <?php echo ($bulletin['decision'] ?? '') === 'admis' ? 'selected' : ''; ?>>Admis</option>
                                        <option value="échec" <?php echo ($bulletin['decision'] ?? '') === 'échec' ? 'selected' : ''; ?>>Échec</option>
                                        <option value="renvoyé" <?php echo ($bulletin['decision'] ?? '') === 'renvoyé' ? 'selected' : ''; ?>>Renvoyé</option>
                                        <option value="passage_conditionnel" <?php echo ($bulletin['decision'] ?? '') === 'passage_conditionnel' ? 'selected' : ''; ?>>Passage conditionnel</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes par matière -->
                    <div class="edit-section">
                        <div class="edit-section-header">
                            <h5 class="mb-0">
                                <i class="bi bi-list-check me-2"></i>Notes par matière
                            </h5>
                        </div>
                        <div class="edit-section-body">
                            <?php if (empty($bulletin_lignes)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox display-4 text-muted"></i>
                                    <p class="text-muted mt-2">Aucune matière disponible pour ce bulletin</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Matière</th>
                                                <th>Coefficient</th>
                                                <th>Moyenne</th>
                                                <th>Rang</th>
                                                <th>Appréciation</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bulletin_lignes as $ligne): ?>
                                                <tr>
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
                                                        <input type="number" class="form-control grade-input" 
                                                               name="lignes[<?php echo $ligne['id']; ?>][moyenne_matiere]"
                                                               step="0.01" min="0" max="20"
                                                               value="<?php echo $ligne['moyenne_matiere'] ?? ''; ?>">
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control" 
                                                               name="lignes[<?php echo $ligne['id']; ?>][rang_matiere]"
                                                               min="1"
                                                               value="<?php echo $ligne['rang_matiere'] ?? ''; ?>">
                                                    </td>
                                                    <td>
                                                        <textarea class="form-control appreciation-input" 
                                                                  name="lignes[<?php echo $ligne['id']; ?>][appreciation]"
                                                                  placeholder="Appréciation..."><?php echo htmlspecialchars($ligne['appreciation'] ?? ''); ?></textarea>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Observations et recommandations -->
                    <div class="edit-section">
                        <div class="edit-section-header">
                            <h5 class="mb-0">
                                <i class="bi bi-chat-text me-2"></i>Observations et recommandations
                            </h5>
                        </div>
                        <div class="edit-section-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="observations_conseil" class="form-label">Observations du conseil de classe</label>
                                    <textarea class="form-control" id="observations_conseil" name="observations_conseil" 
                                              rows="4" placeholder="Observations..."><?php echo htmlspecialchars($bulletin['observations_conseil'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="recommandations" class="form-label">Recommandations</label>
                                    <textarea class="form-control" id="recommandations" name="recommandations" 
                                              rows="4" placeholder="Recommandations..."><?php echo htmlspecialchars($bulletin['recommandations'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="edit-section">
                        <div class="edit-section-body text-center">
                            <button type="submit" class="btn btn-primary btn-lg me-3">
                                <i class="bi bi-check-circle me-2"></i>Enregistrer les modifications
                            </button>
                            
                            <a href="view_bulletin.php?id=<?php echo $bulletin_id; ?>" class="btn btn-secondary btn-lg">
                                <i class="bi bi-x-circle me-2"></i>Annuler
                            </a>
                        </div>
                    </div>
                </form>
                
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
    <script>
        // Validation des notes
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('change', function() {
                const value = parseFloat(this.value);
                if (this.name.includes('moyenne') && value > 20) {
                    this.value = 20;
                    alert('La note ne peut pas dépasser 20');
                }
                if (this.name.includes('rang') && value < 1) {
                    this.value = '';
                    alert('Le rang doit être supérieur à 0');
                }
            });
        });
        
        // Confirmation avant soumission
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir enregistrer ces modifications ?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
