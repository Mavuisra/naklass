<?php
/**
 * Script de correction automatique du comptage des inscriptions
 * Met à jour le champ effectif_actuel dans la table classes
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction du Comptage des Inscriptions</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css' rel='stylesheet'>
</head>
<body class='bg-light'>
    <div class='container mt-4'>
        <h1 class='mb-4'>
            <i class='bi bi-wrench me-2'></i>
            Correction Automatique du Comptage des Inscriptions
        </h1>";

try {
    // 1. Vérifier si la colonne effectif_actuel existe
    echo "<div class='card mb-4'>
            <div class='card-header'>
                <h5><i class='bi bi-check-circle me-2'></i>1. Vérification de la structure</h5>
            </div>
            <div class='card-body'>";
    
    $check_column_query = "SHOW COLUMNS FROM classes LIKE 'effectif_actuel'";
    $stmt = $db->prepare($check_column_query);
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        echo "<div class='alert alert-warning'>
                <i class='bi bi-exclamation-triangle me-2'></i>
                La colonne 'effectif_actuel' n'existe pas dans la table classes.
              </div>";
        
        // Créer la colonne
        $add_column_query = "ALTER TABLE classes ADD COLUMN effectif_actuel INT DEFAULT 0";
        $db->exec($add_column_query);
        echo "<div class='alert alert-success'>
                <i class='bi bi-check-circle me-2'></i>
                Colonne 'effectif_actuel' créée avec succès !
              </div>";
    } else {
        echo "<div class='alert alert-success'>
                <i class='bi bi-check-circle me-2'></i>
                La colonne 'effectif_actuel' existe déjà.
              </div>";
    }
    echo "</div></div>";
    
    // 2. État actuel des classes
    echo "<div class='card mb-4'>
            <div class='card-header'>
                <h5><i class='bi bi-list me-2'></i>2. État actuel des classes</h5>
            </div>
            <div class='card-body'>";
    
    $classes_query = "SELECT c.id, c.nom_classe, c.effectif_actuel, 
                             COUNT(DISTINCT i.eleve_id) as nombre_eleves_reel
                      FROM classes c
                      LEFT JOIN inscriptions i ON c.id = i.classe_id 
                           AND i.statut_inscription = 'validée' 
                           AND i.statut = 'actif'
                      WHERE c.ecole_id = :ecole_id
                      GROUP BY c.id
                      ORDER BY c.nom_classe";
    
    $stmt = $db->prepare($classes_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classes = $stmt->fetchAll();
    
    if (empty($classes)) {
        echo "<div class='alert alert-warning'>
                <i class='bi bi-exclamation-triangle me-2'></i>
                Aucune classe trouvée pour cette école.
              </div>";
    } else {
        echo "<table class='table table-sm table-bordered'>
                <thead class='table-dark'>
                    <tr>
                        <th>Classe</th>
                        <th>Effectif actuel (DB)</th>
                        <th>Nombre d'élèves (réel)</th>
                        <th>Différence</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>";
        
        $total_corrections = 0;
        foreach ($classes as $classe) {
            $difference = $classe['nombre_eleves_reel'] - $classe['effectif_actuel'];
            $status_class = '';
            $status_text = '';
            
            if ($difference == 0) {
                $status_class = 'table-success';
                $status_text = '✅ Correct';
            } elseif ($difference > 0) {
                $status_class = 'table-warning';
                $status_text = '⚠️ Sous-évalué';
                $total_corrections++;
            } else {
                $status_class = 'table-danger';
                $status_text = '❌ Sur-évalué';
                $total_corrections++;
            }
            
            echo "<tr class='$status_class'>
                    <td><strong>{$classe['nom_classe']}</strong></td>
                    <td>{$classe['effectif_actuel']}</td>
                    <td>{$classe['nombre_eleves_reel']}</td>
                    <td>" . ($difference > 0 ? '+' : '') . "{$difference}</td>
                    <td>{$status_text}</td>
                  </tr>";
        }
        echo "</tbody></table>";
        
        echo "<div class='alert alert-info'>
                <i class='bi bi-info-circle me-2'></i>
                <strong>{$total_corrections}</strong> classe(s) nécessitent une correction.
              </div>";
    }
    echo "</div></div>";
    
    // 3. Correction automatique
    if ($total_corrections > 0) {
        echo "<div class='card mb-4'>
                <div class='card-header bg-warning'>
                    <h5><i class='bi bi-tools me-2'></i>3. Correction automatique</h5>
                </div>
                <div class='card-body'>";
        
        $update_query = "UPDATE classes c 
                        SET effectif_actuel = (
                            SELECT COUNT(DISTINCT i.eleve_id)
                            FROM inscriptions i
                            WHERE i.classe_id = c.id
                            AND i.statut_inscription = 'validée'
                            AND i.statut = 'actif'
                        )
                        WHERE c.ecole_id = :ecole_id";
        
        $stmt = $db->prepare($update_query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $rows_affected = $stmt->rowCount();
        
        echo "<div class='alert alert-success'>
                <i class='bi bi-check-circle me-2'></i>
                <strong>{$rows_affected}</strong> classe(s) mises à jour avec succès !
              </div>";
        
        echo "<p>La correction a été appliquée. Vérifiez maintenant la page des classes pour voir les résultats.</p>";
        
        echo "</div></div>";
        
        // 4. Vérification après correction
        echo "<div class='card mb-4'>
                <div class='card-header bg-success text-white'>
                    <h5><i class='bi bi-check-circle me-2'></i>4. Vérification après correction</h5>
                </div>
                <div class='card-body'>";
        
        $stmt = $db->prepare($classes_query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $classes_after = $stmt->fetchAll();
        
        echo "<table class='table table-sm table-bordered'>
                <thead class='table-success'>
                    <tr>
                        <th>Classe</th>
                        <th>Effectif actuel (DB)</th>
                        <th>Nombre d'élèves (réel)</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach ($classes_after as $classe) {
            $status_class = ($classe['effectif_actuel'] == $classe['nombre_eleves_reel']) ? 'table-success' : 'table-danger';
            $status_text = ($classe['effectif_actuel'] == $classe['nombre_eleves_reel']) ? '✅ Correct' : '❌ Erreur';
            
            echo "<tr class='$status_class'>
                    <td><strong>{$classe['nom_classe']}</strong></td>
                    <td>{$classe['effectif_actuel']}</td>
                    <td>{$classe['nombre_eleves_reel']}</td>
                    <td>{$status_text}</td>
                  </tr>";
        }
        echo "</tbody></table>";
        
        echo "</div></div>";
    } else {
        echo "<div class='card mb-4'>
                <div class='card-header bg-success text-white'>
                    <h5><i class='bi bi-check-circle me-2'></i>3. Aucune correction nécessaire</h5>
                </div>
                <div class='card-body'>
                    <div class='alert alert-success'>
                        <i class='bi bi-check-circle me-2'></i>
                        Toutes les classes ont déjà le bon comptage d'élèves !
                    </div>
                </div>
              </div>";
    }
    
    // 5. Recommandations
    echo "<div class='card mb-4'>
            <div class='card-header bg-primary text-white'>
                <h5><i class='bi bi-lightbulb me-2'></i>5. Recommandations</h5>
            </div>
            <div class='card-body'>";
    
    echo "<h6>Pour éviter ce problème à l'avenir :</h6>";
    echo "<ul>
            <li>✅ Utilisez toujours <code>statut_inscription = 'validée'</code> pour le statut de l'inscription</li>
            <li>✅ Vérifiez aussi <code>statut = 'actif'</code> pour le statut du record</li>
            <li>✅ Les triggers de la base de données mettent à jour automatiquement <code>effectif_actuel</code></li>
            <li>✅ Vérifiez régulièrement la cohérence des données avec le script de diagnostic</li>
          </ul>";
    
    echo "<h6>Requête SQL correcte :</h6>";
    echo "<code class='d-block p-2 bg-light'>";
    echo "SELECT c.*, COUNT(DISTINCT i.eleve_id) as nombre_eleves<br>";
    echo "FROM classes c<br>";
    echo "LEFT JOIN inscriptions i ON c.id = i.classe_id <br>";
    echo "     AND i.statut_inscription = 'validée' <br>";
    echo "     AND i.statut = 'actif'<br>";
    echo "WHERE c.ecole_id = :ecole_id<br>";
    echo "GROUP BY c.id";
    echo "</code>";
    
    echo "</div></div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
            <i class='bi bi-exclamation-triangle me-2'></i>
            Erreur lors de la correction : " . htmlspecialchars($e->getMessage()) . "
          </div>";
}

echo "<div class='text-center mt-4 mb-4'>
        <a href='index.php' class='btn btn-primary'>
            <i class='bi bi-arrow-left me-2'></i>Retour aux classes
        </a>
        <a href='debug_inscriptions_count.php' class='btn btn-info'>
            <i class='bi bi-bug me-2'></i>Diagnostic
        </a>
        <a href='../index.php' class='btn btn-secondary'>
            <i class='bi bi-house me-2'></i>Accueil
        </a>
      </div>
    </div>
    
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>
