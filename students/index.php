<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Paramètres de pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Paramètres de recherche et filtrage
$search = sanitize($_GET['search'] ?? '');
$classe_filter = sanitize($_GET['classe'] ?? '');
$statut_filter = sanitize($_GET['statut'] ?? '');

// Construction de la requête avec filtres
$where_conditions = ["e.ecole_id = :ecole_id"];
$params = ['ecole_id' => $_SESSION['ecole_id']];

if (!empty($search)) {
    $where_conditions[] = "(e.nom LIKE :search OR e.prenom LIKE :search OR e.matricule LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($classe_filter)) {
    $where_conditions[] = "c.id = :classe_id";
    $params['classe_id'] = $classe_filter;
}

if (!empty($statut_filter)) {
    $where_conditions[] = "e.statut_scolaire = :statut";
    $params['statut'] = $statut_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Compter le total d'élèves - REQUÊTE CORRIGÉE
$count_query = "SELECT COUNT(DISTINCT e.id) as total 
                FROM eleves e 
                LEFT JOIN (
                    SELECT eleve_id, classe_id, annee_scolaire, statut_inscription
                    FROM inscriptions 
                    WHERE statut_inscription IN ('validée', 'en_cours')
                    ORDER BY created_at DESC
                ) i ON e.id = i.eleve_id
                LEFT JOIN classes c ON i.classe_id = c.id
                WHERE $where_clause";

$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_students = $count_stmt->fetch()['total'];
$total_pages = ceil($total_students / $limit);

// Récupérer les élèves avec pagination - REQUÊTE CORRIGÉE
$query = "SELECT DISTINCT e.*, 
                 c.nom_classe, c.niveau, c.cycle,
                 i.annee_scolaire,
                 i.statut_inscription as statut_inscription,
                 COUNT(DISTINCT t.id) as nb_tuteurs
          FROM eleves e 
          LEFT JOIN (
              SELECT eleve_id, classe_id, annee_scolaire, statut_inscription
              FROM inscriptions 
              WHERE statut_inscription IN ('validée', 'en_cours')
              ORDER BY created_at DESC
          ) i ON e.id = i.eleve_id
          LEFT JOIN classes c ON i.classe_id = c.id
          LEFT JOIN eleve_tuteurs et ON e.id = et.eleve_id
          LEFT JOIN tuteurs t ON et.tuteur_id = t.id
          WHERE $where_clause
          GROUP BY e.id
          ORDER BY e.nom, e.prenom
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll();

// Récupérer les classes pour le filtre
$classes_query = "SELECT id, nom_classe, niveau FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY niveau, nom_classe";
$classes_stmt = $db->prepare($classes_query);
$classes_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
$classes = $classes_stmt->fetchAll();

$page_title = "Gestion des Élèves";
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
                <h1><i class="bi bi-people me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Gérer les élèves et leurs inscriptions</p>
            </div>
            
            <div class="topbar-actions">
                <a href="add.php" class="btn btn-primary">
                    <i class="bi bi-person-plus-fill me-2"></i>Inscrire un Élève
                </a>
                
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-download me-2"></i>Exporter
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="showExportModal()"><i class="bi bi-file-earmark-excel me-2"></i>Excel avec filtres</a></li>
                        <li><a class="dropdown-item" href="export_excel.php"><i class="bi bi-file-earmark-excel me-2"></i>Excel (tous les élèves)</a></li>
                        <li><a class="dropdown-item" href="#" onclick="showExportCSVModal()"><i class="bi bi-file-earmark-text me-2"></i>CSV avec filtres</a></li>
                        <li><a class="dropdown-item" href="export_csv.php"><i class="bi bi-file-earmark-text me-2"></i>CSV (tous les élèves)</a></li>
                        <li><a class="dropdown-item" href="export.php?format=pdf"><i class="bi bi-file-earmark-pdf me-2"></i>PDF</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Statistiques rapides -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($total_students); ?></h3>
                            <p>Total élèves</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-person-check"></i>
                        </div>
                        <div class="stat-content">
                            <?php
                            $inscrits_query = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id AND statut_scolaire = 'inscrit'";
                            $inscrits_stmt = $db->prepare($inscrits_query);
                            $inscrits_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                            $total_inscrits = $inscrits_stmt->fetch()['total'];
                            ?>
                            <h3><?php echo number_format($total_inscrits); ?></h3>
                            <p>Élèves inscrits</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <div class="stat-content">
                            <?php
                            $nouveaux_query = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                            $nouveaux_stmt = $db->prepare($nouveaux_query);
                            $nouveaux_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                            $total_nouveaux = $nouveaux_stmt->fetch()['total'];
                            ?>
                            <h3><?php echo number_format($total_nouveaux); ?></h3>
                            <p>Nouveaux (30j)</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <?php
                            $problemes_query = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id AND statut_scolaire IN ('suspendu', 'exclu')";
                            $problemes_stmt = $db->prepare($problemes_query);
                            $problemes_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                            $total_problemes = $problemes_stmt->fetch()['total'];
                            ?>
                            <h3><?php echo number_format($total_problemes); ?></h3>
                            <p>Problèmes</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtres et recherche -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Rechercher</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Nom, prénom ou matricule..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="classe" class="form-label">Classe</label>
                            <select class="form-select" id="classe" name="classe">
                                <option value="">Toutes les classes</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" <?php echo ($classe_filter == $classe['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom_classe']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="">Tous les statuts</option>
                                <option value="inscrit" <?php echo ($statut_filter == 'inscrit') ? 'selected' : ''; ?>>Inscrit</option>
                                <option value="suspendu" <?php echo ($statut_filter == 'suspendu') ? 'selected' : ''; ?>>Suspendu</option>
                                <option value="exclu" <?php echo ($statut_filter == 'exclu') ? 'selected' : ''; ?>>Exclu</option>
                                <option value="diplômé" <?php echo ($statut_filter == 'diplômé') ? 'selected' : ''; ?>>Diplômé</option>
                                <option value="abandonné" <?php echo ($statut_filter == 'abandonné') ? 'selected' : ''; ?>>Abandonné</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-funnel me-2"></i>Filtrer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Liste des élèves -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>Liste des élèves
                        <span class="badge bg-primary ms-2"><?php echo number_format($total_students); ?></span>
                    </h5>
                    
                    <div class="btn-group btn-group-sm">
                        <input type="checkbox" class="btn-check" id="selectAll">
                        <label class="btn btn-outline-secondary" for="selectAll">
                            <i class="bi bi-check-all"></i>
                        </label>
                        
                        <button type="button" class="btn btn-outline-danger" id="deleteSelected" disabled>
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($students)): ?>
                        <div class="text-center p-5">
                            <i class="bi bi-people display-1 text-muted mb-3"></i>
                            <h5>Aucun élève trouvé</h5>
                            <p class="text-muted">Aucun élève ne correspond à vos critères de recherche.</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="bi bi-person-plus-fill me-2"></i>Inscrire le Premier Élève
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="40"><input type="checkbox" id="selectAllTable"></th>
                                        <th>Photo</th>
                                        <th>Matricule</th>
                                        <th>Nom complet</th>
                                        <th>Sexe</th>
                                        <th>Âge</th>
                                        <th>Classe</th>
                                        <th>Statut</th>
                                        <th>Tuteurs</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="student-checkbox" value="<?php echo $student['id']; ?>">
                                            </td>
                                            <td>
                                                <div class="avatar-sm">
                                                    <?php if (!empty($student['photo_url'])): ?>
                                                        <img src="<?php echo htmlspecialchars($student['photo_url']); ?>" 
                                                             alt="Photo" class="rounded-circle" width="40" height="40">
                                                    <?php else: ?>
                                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="bi bi-person text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark font-monospace">
                                                    <?php echo htmlspecialchars($student['matricule']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['nom'] . ' ' . $student['prenom']); ?></strong>
                                                    <?php if (!empty($student['postnom'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($student['postnom']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $student['sexe'] == 'M' ? 'bg-primary' : 'bg-pink'; ?>">
                                                    <?php echo $student['sexe']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo calculateAge($student['date_naissance']); ?> ans</td>
                                            <td>
                                                <?php if (!empty($student['nom_classe'])): ?>
                                                    <div>
                                                        <span class="badge bg-info">
                                                            <?php 
                                                            $classe_info = '';
                                                            if ($student['niveau'] && $student['cycle']) {
                                                                $classe_info = $student['niveau'] . ' - ' . $student['nom_classe'];
                                                            } elseif ($student['niveau']) {
                                                                $classe_info = $student['niveau'] . ' - ' . $student['nom_classe'];
                                                            } elseif ($student['cycle']) {
                                                                $classe_info = $student['cycle'] . ' - ' . $student['nom_classe'];
                                                            } else {
                                                                $classe_info = $student['nom_classe'];
                                                            }
                                                            echo htmlspecialchars($classe_info);
                                                            ?>
                                                        </span>
                                                        <?php if (!empty($student['annee_scolaire'])): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($student['annee_scolaire']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Non assigné</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statut_class = [
                                                    'inscrit' => 'bg-success',
                                                    'suspendu' => 'bg-warning',
                                                    'exclu' => 'bg-danger',
                                                    'diplômé' => 'bg-primary',
                                                    'abandonné' => 'bg-secondary'
                                                ];
                                                
                                                // Utiliser le statut de l'inscription si disponible, sinon le statut scolaire
                                                $statut_a_afficher = $student['statut_inscription'] ?? $student['statut_scolaire'] ?? 'Non inscrit';
                                                $statut_key = strtolower($statut_a_afficher);
                                                ?>
                                                <span class="badge <?php echo $statut_class[$statut_key] ?? 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($statut_a_afficher); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-outline-secondary">
                                                    <?php echo $student['nb_tuteurs']; ?> tuteur(s)
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="generate_card.php?id=<?php echo $student['id']; ?>" 
                                                       class="btn btn-outline-success" title="Carte d'Élève">
                                                        <i class="bi bi-card-heading"></i>
                                                    </a>
                                                    <a href="view.php?id=<?php echo $student['id']; ?>" 
                                                       class="btn btn-outline-info" title="Voir">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $student['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Modifier">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteStudent(<?php echo $student['id']; ?>)" title="Supprimer">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Navigation des pages">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    
                    <div class="text-center mt-2">
                        <small class="text-muted">
                            Page <?php echo $page; ?> sur <?php echo $total_pages; ?> 
                            (<?php echo number_format($total_students); ?> élève<?php echo $total_students > 1 ? 's' : ''; ?> au total)
                        </small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Gestion de la sélection multiple
        document.getElementById('selectAllTable').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateDeleteButton();
        });
        
        document.querySelectorAll('.student-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateDeleteButton);
        });
        
        function updateDeleteButton() {
            const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            const deleteBtn = document.getElementById('deleteSelected');
            deleteBtn.disabled = checkedBoxes.length === 0;
        }
        
        // Suppression d'un élève
        function deleteStudent(id) {
            NaklassUtils.confirmAction(
                'Êtes-vous sûr de vouloir supprimer cet élève ? Cette action est irréversible.',
                function() {
                    fetch('delete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            NaklassUtils.showToast(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            NaklassUtils.showToast(data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        NaklassUtils.showToast('Erreur lors de la suppression', 'danger');
                    });
                }
            );
        }
        
        // Suppression multiple
        document.getElementById('deleteSelected').addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            const ids = Array.from(checkedBoxes).map(cb => cb.value);
            
            if (ids.length === 0) return;
            
            NaklassUtils.confirmAction(
                `Êtes-vous sûr de vouloir supprimer ${ids.length} élève(s) ? Cette action est irréversible.`,
                function() {
                    fetch('delete_multiple.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ ids: ids })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            NaklassUtils.showToast(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            NaklassUtils.showToast(data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        NaklassUtils.showToast('Erreur lors de la suppression', 'danger');
                    });
                }
            );
        });
    </script>

    <!-- Modal d'exportation Excel avec filtres -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">
                        <i class="bi bi-file-earmark-excel me-2"></i>Exporter les Élèves en Excel
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="exportForm" method="GET" action="export_excel.php">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Sélectionnez les critères d'exportation. Laissez vide pour exporter tous les élèves.
                        </div>
                        
                        <div class="mb-3">
                            <label for="exportClasse" class="form-label">Classe</label>
                            <select class="form-select" id="exportClasse" name="classe_id">
                                <option value="">Toutes les classes</option>
                                <?php
                                $classes_query = "SELECT id, nom_classe FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom_classe";
                                $stmt = $db->prepare($classes_query);
                                $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                                $classes = $stmt->fetchAll();
                                
                                foreach ($classes as $classe) {
                                    $selected = ($classe_filter == $classe['id']) ? 'selected' : '';
                                    echo "<option value=\"{$classe['id']}\" $selected>{$classe['nom_classe']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="exportStatut" class="form-label">Statut d'inscription</label>
                            <select class="form-select" id="exportStatut" name="statut">
                                <option value="">Tous les statuts</option>
                                <option value="validée" <?php echo ($statut_filter == 'validée') ? 'selected' : ''; ?>>Validée</option>
                                <option value="en_cours" <?php echo ($statut_filter == 'en_cours') ? 'selected' : ''; ?>>En cours</option>
                                <option value="annulée" <?php echo ($statut_filter == 'annulée') ? 'selected' : ''; ?>>Annulée</option>
                                <option value="archivé" <?php echo ($statut_filter == 'archivé') ? 'selected' : ''; ?>>Archivé</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="exportAnnee" class="form-label">Année scolaire</label>
                            <select class="form-select" id="exportAnnee" name="annee_scolaire">
                                <option value="">Toutes les années</option>
                                <?php
                                $annees_query = "SELECT DISTINCT annee_scolaire FROM inscriptions WHERE ecole_id = :ecole_id ORDER BY annee_scolaire DESC";
                                $stmt = $db->prepare($annees_query);
                                $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                                $annees = $stmt->fetchAll();
                                
                                foreach ($annees as $annee) {
                                    echo "<option value=\"{$annee['annee_scolaire']}\">{$annee['annee_scolaire']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeInactive" name="include_inactive">
                            <label class="form-check-label" for="includeInactive">
                                Inclure les élèves inactifs
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-download me-2"></i>Exporter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal d'exportation CSV avec filtres -->
    <div class="modal fade" id="exportCSVModal" tabindex="-1" aria-labelledby="exportCSVModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportCSVModalLabel">
                        <i class="bi bi-file-earmark-text me-2"></i>Exporter les Élèves en CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="exportCSVForm" method="GET" action="export_csv.php">
                    <div class="modal-body">
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Format CSV recommandé</strong> - Compatible Excel, fonctionne sans extensions supplémentaires.
                        </div>
                        
                        <div class="mb-3">
                            <label for="exportCSVClasse" class="form-label">Classe</label>
                            <select class="form-select" id="exportCSVClasse" name="classe_id">
                                <option value="">Toutes les classes</option>
                                <?php
                                $classes_query = "SELECT id, nom_classe FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom_classe";
                                $stmt = $db->prepare($classes_query);
                                $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                                $classes = $stmt->fetchAll();
                                
                                foreach ($classes as $classe) {
                                    $selected = ($classe_filter == $classe['id']) ? 'selected' : '';
                                    echo "<option value=\"{$classe['id']}\" $selected>{$classe['nom_classe']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="exportCSVStatut" class="form-label">Statut d'inscription</label>
                            <select class="form-select" id="exportCSVStatut" name="statut">
                                <option value="">Tous les statuts</option>
                                <option value="validée" <?php echo ($statut_filter == 'validée') ? 'selected' : ''; ?>>Validée</option>
                                <option value="en_cours" <?php echo ($statut_filter == 'en_cours') ? 'selected' : ''; ?>>En cours</option>
                                <option value="annulée" <?php echo ($statut_filter == 'annulée') ? 'selected' : ''; ?>>Annulée</option>
                                <option value="archivé" <?php echo ($statut_filter == 'archivé') ? 'selected' : ''; ?>>Archivé</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="exportCSVAnnee" class="form-label">Année scolaire</label>
                            <select class="form-select" id="exportCSVAnnee" name="annee_scolaire">
                                <option value="">Toutes les années</option>
                                <?php
                                $annees_query = "SELECT DISTINCT annee_scolaire FROM inscriptions WHERE ecole_id = :ecole_id ORDER BY annee_scolaire DESC";
                                $stmt = $db->prepare($annees_query);
                                $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
                                $annees = $stmt->fetchAll();
                                
                                foreach ($annees as $annee) {
                                    echo "<option value=\"{$annee['annee_scolaire']}\">{$annee['annee_scolaire']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-download me-2"></i>Exporter CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showExportModal() {
            var exportModal = new bootstrap.Modal(document.getElementById('exportModal'));
            exportModal.show();
        }
        
        function showExportCSVModal() {
            var exportCSVModal = new bootstrap.Modal(document.getElementById('exportCSVModal'));
            exportCSVModal.show();
        }
        
        // Pré-remplir les filtres actuels
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentClasse = urlParams.get('classe');
            const currentStatut = urlParams.get('statut');
            
            if (currentClasse) {
                document.getElementById('exportClasse').value = currentClasse;
            }
            if (currentStatut) {
                document.getElementById('exportStatut').value = currentStatut;
            }
        });
    </script>
</body>
</html>

