<?php
/**
 * Vérification de la structure de la table eleves
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Vérification Structure Table Élèves</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css' rel='stylesheet'>
</head>
<body class='bg-light'>
    <div class='container mt-4'>
        <h1 class='mb-4'>
            <i class='bi bi-check-circle me-2'></i>
            Vérification de la Structure de la Table Élèves
        </h1>";

try {
    // 1. Récupérer la structure de la table eleves
    echo "<div class='card mb-4'>
            <div class='card-header'>
                <h5><i class='bi bi-table me-2'></i>Structure de la table eleves</h5>
            </div>
            <div class='card-body'>";
    
    $columns_query = "SHOW COLUMNS FROM eleves";
    $stmt = $db->prepare($columns_query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Colonnes disponibles :</strong></p>";
    echo "<div class='table-responsive'>
            <table class='table table-sm table-bordered'>
                <thead class='table-dark'>
                    <tr>
                        <th>Colonne</th>
                        <th>Type</th>
                        <th>Null</th>
                        <th>Clé</th>
                        <th>Défaut</th>
                        <th>Extra</th>
                    </tr>
                </thead>
                <tbody>";
    
    foreach ($columns as $column) {
        echo "<tr>
                <td><code>{$column['Field']}</code></td>
                <td>{$column['Type']}</td>
                <td>{$column['Null']}</td>
                <td>{$column['Key']}</td>
                <td>{$column['Default']}</td>
                <td>{$column['Extra']}</td>
              </tr>";
    }
    
    echo "</tbody></table></div>";
    echo "</div></div>";
    
    // 2. Colonnes utilisées dans presence/classe.php
    echo "<div class='card mb-4'>
            <div class='card-header'>
                <h5><i class='bi bi-code me-2'></i>Colonnes utilisées dans presence/classe.php</h5>
            </div>
            <div class='card-body'>";
    
    $used_columns = [
        'id' => 'ID de l\'élève',
        'prenom' => 'Prénom de l\'élève',
        'nom' => 'Nom de l\'élève',
        'date_naissance' => 'Date de naissance',
        'sexe' => 'Sexe de l\'élève',
        'photo_path' => 'Chemin de la photo',
        'matricule' => 'Matricule de l\'élève'
    ];
    
    $available_columns = array_column($columns, 'Field');
    
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
        $status = in_array($column, $available_columns) ? '✅ Existe' : '❌ Manquante';
        $status_class = in_array($column, $available_columns) ? 'table-success' : 'table-danger';
        
        echo "<tr class='$status_class'>
                <td><code>{$column}</code></td>
                <td>{$description}</td>
                <td><strong>{$status}</strong></td>
              </tr>";
    }
    echo "</tbody></table>";
    echo "</div></div>";
    
    // 3. Test de récupération des élèves
    echo "<div class='card mb-4'>
            <div class='card-header'>
                <h5><i class='bi bi-database me-2'></i>Test de récupération des élèves</h5>
            </div>
            <div class='card-body'>";
    
    // Récupérer une classe pour tester
    $class_query = "SELECT id, nom_classe FROM classes WHERE ecole_id = :ecole_id LIMIT 1";
    $stmt = $db->prepare($class_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $test_class = $stmt->fetch();
    
    if ($test_class) {
        echo "<p><strong>Classe de test :</strong> {$test_class['nom_classe']} (ID: {$test_class['id']})</p>";
        
        // Test de la requête des élèves
        $test_query = "SELECT i.id as inscription_id,
                              el.prenom, 
                              el.nom, 
                              el.date_naissance,
                              el.sexe,
                              el.photo_path as photo,
                              el.matricule,
                              i.date_inscription,
                              i.statut_inscription,
                              i.statut as inscription_statut
                       FROM inscriptions i
                       JOIN eleves el ON i.eleve_id = el.id
                       WHERE i.classe_id = :class_id 
                       AND i.statut_inscription = 'validée'
                       AND i.statut = 'actif'
                       AND el.statut = 'actif'
                       ORDER BY el.nom ASC, el.prenom ASC
                       LIMIT 3";
        
        try {
            $stmt = $db->prepare($test_query);
            $stmt->execute(['class_id' => $test_class['id']]);
            $test_eleves = $stmt->fetchAll();
            
            if (!empty($test_eleves)) {
                echo "<div class='alert alert-success'>
                        <h6>✅ Requête réussie !</h6>
                        <p>Nombre d'élèves trouvés : " . count($test_eleves) . "</p>
                      </div>";
                
                echo "<h6>Exemple d'élève :</h6>";
                $eleve = $test_eleves[0];
                echo "<div class='row'>";
                foreach ($used_columns as $column => $description) {
                    if (isset($eleve[$column])) {
                        $value = $eleve[$column] ?: 'NULL';
                        echo "<div class='col-md-6 mb-2'>
                                <strong>{$column}:</strong> <code>{$value}</code>
                              </div>";
                    }
                }
                echo "</div>";
                
            } else {
                echo "<div class='alert alert-warning'>
                        <h6>⚠️ Requête réussie mais aucun élève trouvé</h6>
                        <p>Vérifiez qu'il y a des inscriptions valides pour cette classe.</p>
                      </div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>
                    <h6>❌ Erreur lors de la requête</h6>
                    <p><strong>Message :</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                  </div>";
        }
        
    } else {
        echo "<div class='alert alert-warning'>
                <h6>⚠️ Aucune classe trouvée</h6>
                <p>Aucune classe n'a été trouvée pour cette école.</p>
              </div>";
    }
    
    echo "</div></div>";
    
    // 4. Recommandations
    echo "<div class='card mb-4'>
            <div class='card-header bg-primary text-white'>
                <h5><i class='bi bi-lightbulb me-2'></i>Recommandations</h5>
            </div>
            <div class='card-body'>";
    
    $missing_columns = array_diff(array_keys($used_columns), $available_columns);
    
    if (empty($missing_columns)) {
        echo "<div class='alert alert-success'>
                <h6>🎉 Parfait !</h6>
                <p>Toutes les colonnes utilisées dans presence/classe.php existent dans la table eleves.</p>
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
        <a href='classe.php?id=1' class='btn btn-primary me-2' target='_blank'>
            <i class='bi bi-clipboard-check me-2'></i>Tester classe.php
        </a>
        <a href='index.php' class='btn btn-secondary'>
            <i class='bi bi-arrow-left me-2'></i>Retour à la gestion de présence
        </a>
      </div>
    </div>
    
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>
