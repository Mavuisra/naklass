<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

// Traitement de création d'année scolaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_annee') {
    try {
        $libelle = sanitize($_POST['libelle'] ?? '');
        $date_debut = sanitize($_POST['date_debut'] ?? '');
        $date_fin = sanitize($_POST['date_fin'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if (empty($libelle) || empty($date_debut) || empty($date_fin)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }
        
        // Vérifier que l'année n'existe pas déjà
        $check_query = "SELECT COUNT(*) FROM annees_scolaires WHERE libelle = :libelle AND ecole_id = :ecole_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute(['libelle' => $libelle, 'ecole_id' => $_SESSION['ecole_id']]);
        
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("Cette année scolaire existe déjà.");
        }
        
        $db->beginTransaction();
        
        // Créer l'année scolaire
        $annee_query = "INSERT INTO annees_scolaires (ecole_id, libelle, date_debut, date_fin, description, created_by) 
                        VALUES (:ecole_id, :libelle, :date_debut, :date_fin, :description, :created_by)";
        $annee_stmt = $db->prepare($annee_query);
        $annee_stmt->execute([
            'ecole_id' => $_SESSION['ecole_id'],
            'libelle' => $libelle,
            'date_debut' => $date_debut,
            'date_fin' => $date_fin,
            'description' => $description,
            'created_by' => $_SESSION['user_id']
        ]);
        
        $annee_id = $db->lastInsertId();
        
        // Créer automatiquement les 3 trimestres
        $trimestres = [
            ['nom' => '1er Trimestre', 'ordre' => 1, 'periode' => 'trimestre1'],
            ['nom' => '2ème Trimestre', 'ordre' => 2, 'periode' => 'trimestre2'],
            ['nom' => '3ème Trimestre', 'ordre' => 3, 'periode' => 'trimestre3']
        ];
        
        foreach ($trimestres as $trimestre) {
            $trim_query = "INSERT INTO periodes_scolaires (annee_scolaire_id, nom, type_periode, ordre_periode, created_by) 
                           VALUES (:annee_id, :nom, 'trimestre', :ordre, :created_by)";
            $trim_stmt = $db->prepare($trim_query);
            $trim_stmt->execute([
                'annee_id' => $annee_id,
                'nom' => $trimestre['nom'],
                'ordre' => $trimestre['ordre'],
                'created_by' => $_SESSION['user_id']
            ]);
            
            $trimestre_id = $db->lastInsertId();
            
            // Créer 2 périodes d'évaluation par trimestre
            $periode_base = ($trimestre['ordre'] - 1) * 2;
            $periodes = [
                ['nom' => 'Période ' . ($periode_base + 1), 'ordre' => $periode_base + 1],
                ['nom' => 'Période ' . ($periode_base + 2), 'ordre' => $periode_base + 2]
            ];
            
            foreach ($periodes as $periode) {
                $per_query = "INSERT INTO periodes_scolaires (annee_scolaire_id, periode_parent_id, nom, type_periode, ordre_periode, created_by) 
                              VALUES (:annee_id, :parent_id, :nom, 'periode', :ordre, :created_by)";
                $per_stmt = $db->prepare($per_query);
                $per_stmt->execute([
                    'annee_id' => $annee_id,
                    'parent_id' => $trimestre_id,
                    'nom' => $periode['nom'],
                    'ordre' => $periode['ordre'],
                    'created_by' => $_SESSION['user_id']
                ]);
            }
        }
        
        $db->commit();
        $success = "Année scolaire créée avec succès avec ses trimestres et périodes !";
        
        logUserAction('CREATE_SCHOOL_YEAR', "Création année scolaire: $libelle");
        
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = $e->getMessage();
        error_log("Erreur création année scolaire: " . $e->getMessage());
    }
}

// Récupérer les années scolaires avec statistiques
$annees_query = "SELECT 
    a.*,
    COUNT(DISTINCT p.id) as nb_trimestres,
    COUNT(DISTINCT pp.id) as nb_periodes,
    COUNT(DISTINCT e.id) as nb_evaluations,
    COUNT(DISTINCT n.id) as nb_notes
FROM annees_scolaires a
LEFT JOIN periodes_scolaires p ON a.id = p.annee_scolaire_id AND p.type_periode = 'trimestre'
LEFT JOIN periodes_scolaires pp ON a.id = pp.annee_scolaire_id AND pp.type_periode = 'periode'
LEFT JOIN evaluations e ON a.id = e.annee_scolaire_id
LEFT JOIN notes n ON e.id = n.evaluation_id
WHERE a.ecole_id = :ecole_id AND a.statut = 'actif'
GROUP BY a.id
ORDER BY a.date_debut DESC";

$annees_stmt = $db->prepare($annees_query);
$annees_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$annees_scolaires = $annees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'année courante (la plus récente active)
$annee_courante = null;
if (!empty($annees_scolaires)) {
    foreach ($annees_scolaires as $annee) {
        if ($annee['active'] || $annee_courante === null) {
            $annee_courante = $annee;
            if ($annee['active']) break;
        }
    }
}

$page_title = "Gestion des Notes et Bulletins";
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
                <h1><i class="bi bi-journal-bookmark me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
                <p class="text-muted">Gestion des années scolaires, trimestres, et saisie des notes</p>
            </div>
            
            <div class="topbar-actions">
                <?php if (hasRole(['admin', 'direction'])): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnneeModal">
                        <i class="bi bi-plus-circle me-1"></i>Nouvelle année
                    </button>
                <?php endif; ?>
                <a href="notes_entry.php" class="btn btn-success">
                    <i class="bi bi-journal-text me-1"></i>Saisir des notes
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            
            <!-- Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Résumé de l'année courante -->
            <?php if ($annee_courante): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-star me-2"></i>Année Scolaire Courante : <?php echo htmlspecialchars($annee_courante['libelle']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon bg-info me-3">
                                                <i class="bi bi-calendar3"></i>
                        </div>
                                            <div>
                                                <h6 class="mb-0">Période</h6>
                                                <small class="text-muted">
                                                    <?php echo formatDate($annee_courante['date_debut'], 'd/m/Y'); ?> - 
                                                    <?php echo formatDate($annee_courante['date_fin'], 'd/m/Y'); ?>
                                                </small>
                        </div>
                    </div>
                </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon bg-success me-3">
                                                <i class="bi bi-grid-3x3"></i>
                        </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo $annee_courante['nb_trimestres']; ?> Trimestres</h6>
                                                <small class="text-muted"><?php echo $annee_courante['nb_periodes']; ?> périodes d'évaluation</small>
                        </div>
                    </div>
                </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon bg-warning me-3">
                                                <i class="bi bi-journal-check"></i>
                        </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo $annee_courante['nb_evaluations']; ?> Évaluations</h6>
                                                <small class="text-muted">Créées cette année</small>
                        </div>
                    </div>
                </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center">
                                            <div class="stat-icon bg-primary me-3">
                                                <i class="bi bi-pencil-square"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo $annee_courante['nb_notes']; ?> Notes</h6>
                                                <small class="text-muted">Saisies cette année</small>
            </div>
                        </div>
                    </div>
                </div>
                
                                <div class="row mt-3">
                                    <div class="col-12">
                                                                                <div class="d-flex gap-2">
                                            <a href="notes_entry.php" class="btn btn-success btn-sm">
                                                <i class="bi bi-journal-text me-1"></i>Saisir des notes
                                            </a>
                                            <a href="student_grades.php" class="btn btn-warning btn-sm">
                                                <i class="bi bi-table me-1"></i>Consulter les notes
                                            </a>
                                            <a href="bulletins.php?annee_id=<?php echo $annee_courante['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="bi bi-file-earmark-text me-1"></i>Bulletins
                                            </a>
                                            <a href="evaluations.php?annee_id=<?php echo $annee_courante['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-clipboard-check me-1"></i>Évaluations
                                            </a>
                                            <?php if (hasRole(['admin', 'direction'])): ?>
                                                <a href="periodes.php?annee_id=<?php echo $annee_courante['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                    <i class="bi bi-calendar-week me-1"></i>Gérer périodes
                                                </a>
                                            <?php endif; ?>
                                        </div>
                    </div>
                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Liste des années scolaires -->
            <div class="row">
                <div class="col-12">
                    <h4 class="mb-3">
                        <i class="bi bi-collection me-2"></i>Toutes les années scolaires
                        <span class="badge bg-primary"><?php echo count($annees_scolaires); ?></span>
                    </h4>
                </div>
            </div>
            
            <?php if (!empty($annees_scolaires)): ?>
                <div class="row">
                    <?php foreach ($annees_scolaires as $annee): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card h-100 <?php echo $annee['active'] ? 'border-success' : ''; ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <?php if ($annee['active']): ?>
                                            <i class="bi bi-star-fill text-warning me-2"></i>
                                        <?php else: ?>
                                            <i class="bi bi-calendar3 me-2"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($annee['libelle']); ?>
                                    </h5>
                                    <?php if ($annee['active']): ?>
                                        <span class="badge bg-success">Actuelle</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($annee['statut']); ?></span>
                                    <?php endif; ?>
                                </div>
                <div class="card-body">
                                    <div class="mb-3">
                                        <h6 class="text-muted">Période scolaire</h6>
                                        <p class="mb-1">
                                            <i class="bi bi-calendar-date me-2"></i>
                                            <strong>Début :</strong> <?php echo formatDate($annee['date_debut'], 'd/m/Y'); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="bi bi-calendar-x me-2"></i>
                                            <strong>Fin :</strong> <?php echo formatDate($annee['date_fin'], 'd/m/Y'); ?>
                                        </p>
                        </div>
                        
                                    <?php if (!empty($annee['description'])): ?>
                                        <div class="mb-3">
                                            <h6 class="text-muted">Description</h6>
                                            <p class="text-muted small"><?php echo nl2br(htmlspecialchars($annee['description'])); ?></p>
                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row text-center mb-3">
                                        <div class="col-3">
                                            <div class="stat-small">
                                                <div class="number"><?php echo $annee['nb_trimestres']; ?></div>
                                                <div class="label">Trimestres</div>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="stat-small">
                                                <div class="number"><?php echo $annee['nb_periodes']; ?></div>
                                                <div class="label">Périodes</div>
                                            </div>
                        </div>
                                        <div class="col-3">
                                            <div class="stat-small">
                                                <div class="number"><?php echo $annee['nb_evaluations']; ?></div>
                                                <div class="label">Évaluations</div>
                        </div>
                        </div>
                                        <div class="col-3">
                                            <div class="stat-small">
                                                <div class="number"><?php echo $annee['nb_notes']; ?></div>
                                                <div class="label">Notes</div>
                </div>
            </div>
                        </div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex gap-1 flex-wrap">
                                        <a href="notes_entry.php?annee_id=<?php echo $annee['id']; ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="bi bi-journal-text me-1"></i>Notes
                                        </a>
                                        <a href="bulletins.php?annee_id=<?php echo $annee['id']; ?>" 
                                           class="btn btn-info btn-sm">
                                            <i class="bi bi-file-earmark-text me-1"></i>Bulletins
                                        </a>
                                        <a href="evaluations.php?annee_id=<?php echo $annee['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-clipboard-check me-1"></i>Évaluations
                                        </a>
                                        <?php if (hasRole(['admin', 'direction'])): ?>
                                            <a href="periodes.php?annee_id=<?php echo $annee['id']; ?>" 
                                               class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-calendar-week me-1"></i>Périodes
                                            </a>
                                        <?php endif; ?>
                                                        </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x fs-1 text-muted"></i>
                    <h4 class="text-muted mt-3">Aucune année scolaire</h4>
                    <p class="text-muted">Commencez par créer votre première année scolaire.</p>
                    <?php if (hasRole(['admin', 'direction'])): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnneeModal">
                            <i class="bi bi-plus-circle me-1"></i>Créer la première année
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div> <!-- End content-area -->
    </div> <!-- End main-content -->

    <!-- Modal de création d'année scolaire -->
    <?php if (hasRole(['admin', 'direction'])): ?>
    <div class="modal fade" id="createAnneeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Créer une nouvelle année scolaire
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_annee">
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Création automatique :</strong> Les 3 trimestres et 6 périodes d'évaluation seront créés automatiquement.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="libelle" class="form-label">Libellé <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="libelle" name="libelle" 
                                           placeholder="Ex: 2024-2025" required>
                                    <div class="form-text">Format recommandé : YYYY-YYYY</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Structure automatique</label>
                                    <div class="bg-light p-3 rounded">
                                        <small>
                                            <strong>3 Trimestres :</strong><br>
                                            • 1er Trimestre (Sept → Déc)<br>
                                            • 2ème Trimestre (Jan → Mars)<br>
                                            • 3ème Trimestre (Avr → Juil)<br><br>
                                            <strong>6 Périodes :</strong><br>
                                            • P1, P2 (1er trim.) • P3, P4 (2ème trim.) • P5, P6 (3ème trim.)
                                        </small>
                            </div>
                        </div>
                    </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_debut" class="form-label">Date de début <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_debut" name="date_debut" required>
                                    <div class="form-text">Généralement début septembre</div>
                                </div>
                                        </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_fin" class="form-label">Date de fin <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_fin" name="date_fin" required>
                                    <div class="form-text">Généralement début juillet</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (optionnel)</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Informations complémentaires sur cette année scolaire..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Créer l'année scolaire
                        </button>
                </div>
                </form>
            </div>
                </div>
            </div>
            <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JS personnalisés -->
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        // Auto-remplir les dates selon l'année saisie
        document.getElementById('libelle').addEventListener('input', function() {
            const libelle = this.value;
            const match = libelle.match(/(\d{4})-(\d{4})/);
            
            if (match) {
                const anneeDebut = parseInt(match[1]);
                const anneeFin = parseInt(match[2]);
                
                // Date de début : 1er septembre de l'année de début
                const dateDebut = `${anneeDebut}-09-01`;
                document.getElementById('date_debut').value = dateDebut;
                
                // Date de fin : 15 juillet de l'année de fin
                const dateFin = `${anneeFin}-07-15`;
                document.getElementById('date_fin').value = dateFin;
            }
        });
        
        // Validation des dates
        document.getElementById('date_fin').addEventListener('change', function() {
            const dateDebut = new Date(document.getElementById('date_debut').value);
            const dateFin = new Date(this.value);
            
            if (dateFin <= dateDebut) {
                alert('La date de fin doit être postérieure à la date de début.');
                this.value = '';
            }
        });
    </script>

<style>
.main-content {
    margin-left: 250px;
    padding: 20px;
    min-height: 100vh;
    background-color: #f8f9fa;
}

.topbar {
    background: white;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.topbar-title h1 {
    margin: 0 0 4px 0;
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
}

.topbar-title p {
    margin: 0;
    color: #7f8c8d;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 12px 12px 0 0 !important;
    padding: 20px 24px;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.stat-small {
    text-align: center;
}

.stat-small .number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #2c3e50;
}

.stat-small .label {
    font-size: 0.75rem;
    color: #7f8c8d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
}

.badge {
    border-radius: 6px;
}

.border-success {
    border-color: #28a745 !important;
    border-width: 2px !important;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 10px;
    }
    
    .topbar {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .topbar-actions {
        width: 100%;
        justify-content: center;
    }
    
    .card-footer .d-flex {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .card-footer .btn {
        width: 100%;
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