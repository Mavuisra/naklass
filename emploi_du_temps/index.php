<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Récupérer les paramètres de filtrage
$annee_scolaire = $_GET['annee_scolaire'] ?? '';
$enseignant_id = $_GET['enseignant_id'] ?? '';
$classe_id = $_GET['classe_id'] ?? '';

// Construire la requête avec filtres
$where_conditions = ['e.ecole_id = :ecole_id'];
$params = ['ecole_id' => $_SESSION['ecole_id']];

if (!empty($annee_scolaire)) {
    $where_conditions[] = 'e.annee_scolaire = :annee_scolaire';
    $params['annee_scolaire'] = $annee_scolaire;
}

if (!empty($enseignant_id)) {
    $where_conditions[] = 'e.enseignant_id = :enseignant_id';
    $params['enseignant_id'] = $enseignant_id;
}

if (!empty($classe_id)) {
    $where_conditions[] = 'e.classe_id = :classe_id';
    $params['classe_id'] = $classe_id;
}

// Si l'utilisateur est un enseignant, filtrer par son ID
if ($_SESSION['user_role'] === 'enseignant') {
    // Récupérer l'ID de l'enseignant depuis la table enseignants
    $enseignant_query = "SELECT id FROM enseignants WHERE utilisateur_id = :user_id AND ecole_id = :ecole_id";
    $stmt = $db->prepare($enseignant_query);
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    $enseignant_data = $stmt->fetch();
    
    if ($enseignant_data) {
        $where_conditions[] = 'e.enseignant_id = :enseignant_user_id';
        $params['enseignant_user_id'] = $enseignant_data['id'];
    }
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Récupérer les emplois du temps
    $query = "SELECT e.*, 
                     en.nom as enseignant_nom, en.prenom as enseignant_prenom,
                     c.nom_classe as classe_nom,
                     co.nom_cours as cours_nom
              FROM emploi_du_temps e
              LEFT JOIN enseignants en ON e.enseignant_id = en.id
              LEFT JOIN classes c ON e.classe_id = c.id
              LEFT JOIN cours co ON e.cours_id = co.id
              WHERE $where_clause
              ORDER BY e.jour_semaine, e.heure_debut";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $emplois = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les données pour les filtres
    // Années scolaires
    $annees_query = "SELECT DISTINCT annee_scolaire FROM emploi_du_temps WHERE ecole_id = :ecole_id ORDER BY annee_scolaire DESC";
    $stmt = $db->prepare($annees_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Enseignants
    $enseignants_query = "SELECT id, nom, prenom FROM enseignants WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom, prenom";
    $stmt = $db->prepare($enseignants_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Classes
    $classes_query = "SELECT id, nom_classe as nom FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' ORDER BY nom_classe";
    $stmt = $db->prepare($classes_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $emplois = [];
    $error_message = "Erreur lors du chargement des données: " . $e->getMessage();
}

$page_title = "Emploi du Temps";
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
        .emploi-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }
        
        .emploi-card:hover {
            transform: translateY(-2px);
        }
        
        .jour-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }
        
        .heure-badge {
            background: #e3f2fd;
            color: #1976d2;
            font-weight: 600;
        }
        
        .filters-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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
                <h1><i class="bi bi-calendar-week me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Consultez et gérez les emplois du temps</p>
            </div>
            
            <div class="topbar-actions">
                <?php if (in_array($_SESSION['user_role'], ['admin', 'direction'])): ?>
                    <a href="create.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>Nouvel emploi du temps
                    </a>
                <?php endif; ?>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Filtres -->
            <div class="filters-section p-4">
                <h5 class="mb-3"><i class="bi bi-funnel me-2"></i>Filtres</h5>
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="annee_scolaire" class="form-label">Année scolaire</label>
                        <select class="form-select" id="annee_scolaire" name="annee_scolaire">
                            <option value="">Toutes les années</option>
                            <?php foreach ($annees as $annee): ?>
                                <option value="<?php echo htmlspecialchars($annee); ?>" 
                                        <?php echo $annee_scolaire === $annee ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($annee); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'direction'])): ?>
                        <div class="col-md-3">
                            <label for="enseignant_id" class="form-label">Enseignant</label>
                            <select class="form-select" id="enseignant_id" name="enseignant_id">
                                <option value="">Tous les enseignants</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?php echo $enseignant['id']; ?>" 
                                            <?php echo $enseignant_id == $enseignant['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3">
                        <label for="classe_id" class="form-label">Classe</label>
                        <select class="form-select" id="classe_id" name="classe_id">
                            <option value="">Toutes les classes</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>" 
                                        <?php echo $classe_id == $classe['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($classe['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary me-2">
                            <i class="bi bi-search me-1"></i>Filtrer
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i>Effacer
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Liste des emplois du temps -->
            <?php if (empty($emplois)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x display-1 text-muted"></i>
                    <h3 class="mt-3">Aucun emploi du temps trouvé</h3>
                    <p class="text-muted">Aucun emploi du temps ne correspond aux critères sélectionnés.</p>
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'direction'])): ?>
                        <a href="create.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>Créer le premier emploi du temps
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Tableau des emplois du temps -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-table me-2"></i>
                            Liste des Emplois du Temps
                        </h5>
                        <span class="badge bg-primary"><?php echo count($emplois); ?> emploi(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Jour</th>
                                        <th>Heure</th>
                                        <th>Cours</th>
                                        <th>Enseignant</th>
                                        <th>Classe</th>
                                        <th>Salle</th>
                                        <th>Année</th>
                                        <th>Statut</th>
                                        <?php if (in_array($_SESSION['user_role'], ['admin', 'direction'])): ?>
                                            <th width="120">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
                                    $couleurs_jours = [
                                        'Lundi' => 'primary',
                                        'Mardi' => 'success', 
                                        'Mercredi' => 'info',
                                        'Jeudi' => 'warning',
                                        'Vendredi' => 'danger',
                                        'Samedi' => 'secondary',
                                        'Dimanche' => 'dark'
                                    ];
                                    
                                    foreach ($emplois as $emploi): 
                                        $couleur = $couleurs_jours[$emploi['jour_semaine']] ?? 'secondary';
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php echo $couleur; ?>">
                                                    <?php echo $emploi['jour_semaine']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo date('H:i', strtotime($emploi['heure_debut'])); ?></strong> - 
                                                <?php echo date('H:i', strtotime($emploi['heure_fin'])); ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($emploi['cours_nom']); ?></strong>
                                            </td>
                                            <td>
                                                <i class="bi bi-person me-1"></i>
                                                <?php echo htmlspecialchars($emploi['enseignant_prenom'] . ' ' . $emploi['enseignant_nom']); ?>
                                            </td>
                                            <td>
                                                <i class="bi bi-building me-1"></i>
                                                <?php echo htmlspecialchars($emploi['classe_nom']); ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($emploi['salle'])): ?>
                                                    <i class="bi bi-geo-alt me-1"></i>
                                                    <?php echo htmlspecialchars($emploi['salle']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo htmlspecialchars($emploi['annee_scolaire']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statut_class = '';
                                                $statut_text = '';
                                                switch($emploi['statut']) {
                                                    case 'actif':
                                                        $statut_class = 'success';
                                                        $statut_text = 'Actif';
                                                        break;
                                                    case 'suspendu':
                                                        $statut_class = 'warning';
                                                        $statut_text = 'Suspendu';
                                                        break;
                                                    case 'archivé':
                                                        $statut_class = 'secondary';
                                                        $statut_text = 'Archivé';
                                                        break;
                                                    default:
                                                        $statut_class = 'light';
                                                        $statut_text = ucfirst($emploi['statut']);
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $statut_class; ?>">
                                                    <?php echo $statut_text; ?>
                                                </span>
                                            </td>
                                            <?php if (in_array($_SESSION['user_role'], ['admin', 'direction'])): ?>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="edit.php?id=<?php echo $emploi['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm" 
                                                           title="Modifier">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="delete.php?id=<?php echo $emploi['id']; ?>" 
                                                           class="btn btn-outline-danger btn-sm"
                                                           title="Supprimer"
                                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet emploi du temps ?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                        
                                        <?php if (!empty($emploi['notes'])): ?>
                                            <tr class="table-light">
                                                <td colspan="<?php echo in_array($_SESSION['user_role'], ['admin', 'direction']) ? '9' : '8'; ?>" class="py-2">
                                                    <small class="text-muted">
                                                        <i class="bi bi-chat-text me-1"></i>
                                                        <strong>Notes :</strong> <?php echo htmlspecialchars($emploi['notes']); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
