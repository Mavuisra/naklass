<?php
/**
 * Vérification des colonnes utilisées dans view.php
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Vérification des Colonnes - Classes</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css' rel='stylesheet'>
</head>
<body class='bg-light'>
    <div class='container mt-4'>
        <h1 class='mb-4'>
            <i class='bi bi-check-circle me-2'></i>
            Vérification des Colonnes Utilisées dans view.php
        </h1>";

try {
    // 1. Récupérer la structure de la table classes
    echo "<div class='card mb-4'>
            <div class='card-header'>
                <h5><i class='bi bi-table me-2'></i>Structure de la table classes</h5>
            </div>
            <div class='card-body'>";
    
    $columns_query = "SHOW COLUMNS FROM classes";
    $stmt = $db->prepare($columns_query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>Colonnes disponibles :</strong></p>";
    echo "<div class='row'>";
    foreach ($columns as $column) {
        echo "<div class='col-md-3 mb-2'>
                <span class='badge bg-success'>{$column}</span>
              </div>";
    }
    echo "</div>";
    echo "</div></div>";
    
    // 2. Colonnes utilisées dans view.php
    echo "<div class='card mb-4'>
            <div class='card-header'>
                <h5><i class='bi bi-code me-2'></i>Colonnes utilisées dans view.php</h5>
            </div>
            <div class='card-body'>";
    
    $used_columns = [
        'id' => 'ID de la classe',
        'nom_classe' => 'Nom de la classe',
        'niveau' => 'Niveau d\'études',
        'cycle' => 'Cycle d\'enseignement',
        'salle_classe' => 'Salle de classe',
        'capacite_max' => 'Capacité maximale',
        'statut' => 'Statut de la classe',
        'annee_scolaire' => 'Année scolaire',
        'created_at' => 'Date de création',
        'professeur_principal_id' => 'ID de l\'enseignant principal',
        'created_by' => 'ID du créateur'
    ];
    
    echo "<table class='table table-sm table-bordered'>
            <thead class='table-dark'>
                <tr>
                    <th>Colonne</th>
                    <th>Description</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach ($used_columns as $column => $description) {
        $status = in_array($column, $columns) ? '✅ Existe' : '❌ Manquante';
        $status_class = in_array($column, $columns) ? 'table-success' : 'table-danger';
        
        echo "<tr class='$status_class'>
                <td><code>{$column}</code></td>
                <td>{$description}</td>
                <td><strong>{$status}</strong></td>
              </tr>";
    }
    echo "</tbody></table>";
    echo "</div></div>";
    
    // 3. Test de la requête complète
    echo "<div class='card mb-4'>
            <div class='card-header'>
                <h5><i class='bi bi-database me-2'></i>Test de la requête complète</h5>
            </div>
            <div class='card-body'>";
    
    $test_query = "SELECT c.*, 
                           c.niveau as niveau_nom,
                           c.cycle as section_nom,
                           e.prenom as enseignant_prenom,
                           e.nom as enseignant_nom,
                           u.prenom as created_by_prenom,
                           u.nom as created_by_nom
                    FROM classes c
                    LEFT JOIN enseignants e ON c.professeur_principal_id = e.id
                    LEFT JOIN utilisateurs u ON c.created_by = u.id
                    WHERE c.ecole_id = :ecole_id
                    LIMIT 1";
    
    try {
        $stmt = $db->prepare($test_query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $test_result = $stmt->fetch();
        
        if ($test_result) {
            echo "<div class='alert alert-success'>
                    <h6>✅ Requête réussie !</h6>
                    <p>Classe trouvée : <strong>{$test_result['nom_classe']}</strong></p>
                  </div>";
            
            echo "<h6>Données récupérées :</h6>";
            echo "<div class='row'>";
            foreach ($used_columns as $column => $description) {
                if (isset($test_result[$column])) {
                    $value = $test_result[$column] ?: 'NULL';
                    echo "<div class='col-md-6 mb-2'>
                            <strong>{$column}:</strong> <code>{$value}</code>
                          </div>";
                }
            }
            echo "</div>";
            
        } else {
            echo "<div class='alert alert-warning'>
                    <h6>⚠️ Requête réussie mais aucune classe trouvée</h6>
                  </div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>
                <h6>❌ Erreur lors de la requête</h6>
                <p><strong>Message :</strong> " . htmlspecialchars($e->getMessage()) . "</p>
              </div>";
    }
    
    echo "</div></div>";
    
    // 4. Recommandations
    echo "<div class='card mb-4'>
            <div class='card-header bg-primary text-white'>
                <h5><i class='bi bi-lightbulb me-2'></i>Recommandations</h5>
            </div>
            <div class='card-body'>";
    
    $missing_columns = array_diff(array_keys($used_columns), $columns);
    
    if (empty($missing_columns)) {
        echo "<div class='alert alert-success'>
                <h6>🎉 Parfait !</h6>
                <p>Toutes les colonnes utilisées dans view.php existent dans la table classes.</p>
              </div>";
    } else {
        echo "<div class='alert alert-warning'>
                <h6>⚠️ Colonnes manquantes détectées</h6>
                <p>Les colonnes suivantes sont utilisées mais n'existent pas :</p>
                <ul>";
        foreach ($missing_columns as $missing) {
            echo "<li><code>{$missing}</code> - {$used_columns[$missing]}</li>";
        }
        echo "</ul>
              </div>";
    }
    
    echo "</div></div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
            <h5>❌ Erreur lors de la vérification</h5>
            <p><strong>Message :</strong> " . htmlspecialchars($e->getMessage()) . "</p>
          </div>";
}

echo "<div class='text-center mt-4 mb-4'>
        <a href='view.php?id=1' class='btn btn-primary me-2' target='_blank'>
            <i class='bi bi-eye me-2'></i>Tester view.php
        </a>
        <a href='index.php' class='btn btn-secondary'>
            <i class='bi bi-arrow-left me-2'></i>Retour aux classes
        </a>
      </div>
    </div>
    
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>
