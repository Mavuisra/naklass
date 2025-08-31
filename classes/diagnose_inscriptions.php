<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

$database = new Database();
$db = $database->getConnection();

$page_title = "Diagnostic de la Table Inscriptions";
$errors = [];
$success = [];
$table_info = [];

// Diagnostiquer la table inscriptions
try {
    // Vérifier si la table existe
    try {
        $db->query("SELECT 1 FROM inscriptions LIMIT 1");
        $success[] = "✓ Table 'inscriptions' existe";
        
        // Récupérer la structure de la table
        $structure_query = "DESCRIBE inscriptions";
        $stmt = $db->query($structure_query);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $table_info['structure'] = $columns;
        
        // Récupérer les index
        $indexes_query = "SHOW INDEX FROM inscriptions";
        $stmt = $db->query($indexes_query);
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $table_info['indexes'] = $indexes;
        
        // Récupérer le nombre d'enregistrements
        $count_query = "SELECT COUNT(*) as total FROM inscriptions";
        $stmt = $db->query($count_query);
        $count = $stmt->fetch()['total'];
        
        $table_info['count'] = $count;
        
        // Vérifier les colonnes requises
        $required_columns = ['id', 'eleve_id', 'classe_id', 'date_inscription', 'statut', 'notes', 'created_by', 'created_at', 'updated_at'];
        $missing_columns = [];
        
        foreach ($required_columns as $required_col) {
            $found = false;
            foreach ($columns as $col) {
                if ($col['Field'] === $required_col) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing_columns[] = $required_col;
            }
        }
        
        if (empty($missing_columns)) {
            $success[] = "✓ Toutes les colonnes requises sont présentes";
        } else {
            $errors[] = "Colonnes manquantes : " . implode(', ', $missing_columns);
        }
        
        // Vérifier les index requis
        $required_indexes = ['idx_inscriptions_eleve', 'idx_inscriptions_classe', 'idx_inscriptions_statut', 'idx_inscriptions_date'];
        $missing_indexes = [];
        
        foreach ($required_indexes as $required_idx) {
            $found = false;
            foreach ($indexes as $idx) {
                if ($idx['Key_name'] === $required_idx) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing_indexes[] = $required_idx;
            }
        }
        
        if (empty($missing_indexes)) {
            $success[] = "✓ Tous les index requis sont présents";
        } else {
            $errors[] = "Index manquants : " . implode(', ', $missing_indexes);
        }
        
    } catch (Exception $e) {
        $errors[] = "Table 'inscriptions' n'existe pas. Créez-la d'abord avec check_tables.php";
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur générale : " . $e->getMessage();
}

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
                <h1><i class="bi bi-search me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Analyse détaillée de la structure de la table inscriptions</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour aux Classes
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Résultats du Diagnostic</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Problèmes détectés</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success">
                                    <h6><i class="bi bi-check-circle me-2"></i>Vérifications réussies</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($success as $msg): ?>
                                            <li><?php echo htmlspecialchars($msg); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($table_info)): ?>
                        <!-- Structure de la table -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-table me-2"></i>Structure de la Table</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Colonne</th>
                                                <th>Type</th>
                                                <th>Null</th>
                                                <th>Clé</th>
                                                <th>Défaut</th>
                                                <th>Extra</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($table_info['structure'] as $column): ?>
                                                <tr>
                                                    <td><code><?php echo htmlspecialchars($column['Field']); ?></code></td>
                                                    <td><?php echo htmlspecialchars($column['Type']); ?></td>
                                                    <td><?php echo htmlspecialchars($column['Null']); ?></td>
                                                    <td><?php echo htmlspecialchars($column['Key']); ?></td>
                                                    <td><?php echo htmlspecialchars($column['Default'] ?? 'NULL'); ?></td>
                                                    <td><?php echo htmlspecialchars($column['Extra']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Index de la table -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Index de la Table</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($table_info['indexes'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Nom de l'Index</th>
                                                    <th>Colonne</th>
                                                    <th>Ordre</th>
                                                    <th>Type</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($table_info['indexes'] as $index): ?>
                                                    <tr>
                                                        <td><code><?php echo htmlspecialchars($index['Key_name']); ?></code></td>
                                                        <td><?php echo htmlspecialchars($index['Column_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($index['Seq_in_index']); ?></td>
                                                        <td><?php echo htmlspecialchars($index['Index_type']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Aucun index trouvé</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Statistiques -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Statistiques</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Nombre total d'enregistrements :</strong> <?php echo $table_info['count']; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Actions -->
                    <div class="card">
                        <div class="card-body">
                            <div class="text-center">
                                <a href="index.php" class="btn btn-primary me-2">
                                    <i class="bi bi-building me-2"></i>Retour aux Classes
                                </a>
                                <a href="fix_inscriptions_table.php" class="btn btn-warning me-2">
                                    <i class="bi bi-wrench me-2"></i>Corriger la Table
                                </a>
                                <a href="diagnose_inscriptions.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Actualiser
                                </a>
                            </div>
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
