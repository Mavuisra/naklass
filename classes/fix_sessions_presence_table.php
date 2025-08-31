<?php
/**
 * Script pour vérifier et corriger la structure de la table sessions_presence
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

$database = new Database();
$db = $database->getConnection();

$page_title = "Correction de la Table Sessions Présence";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-tools me-2"></i><?php echo $page_title; ?></h1>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">🔧 Vérification et Correction de la Table</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            // 1. Vérifier si la table existe
                            echo "<h6 class='text-primary'>1. Vérification de l'existence de la table</h6>";
                            
                            $check_table = "SHOW TABLES LIKE 'sessions_presence'";
                            $stmt = $db->prepare($check_table);
                            $stmt->execute();
                            $table_exists = $stmt->fetch();
                            
                            if ($table_exists) {
                                echo "<p class='text-success'>✅ Table 'sessions_presence' existe</p>";
                            } else {
                                echo "<p class='text-danger'>❌ Table 'sessions_presence' n'existe pas</p>";
                                echo "<div class='alert alert-warning'>";
                                echo "<h6>⚠️ Action recommandée :</h6>";
                                echo "<p>Créez la table en utilisant le fichier SQL fourni.</p>";
                                echo "</div>";
                                exit;
                            }
                            
                            // 2. Vérifier la structure actuelle
                            echo "<hr><h6 class='text-primary'>2. Structure actuelle de la table</h6>";
                            
                            $describe_query = "DESCRIBE sessions_presence";
                            $stmt = $db->prepare($describe_query);
                            $stmt->execute();
                            $columns = $stmt->fetchAll();
                            
                            echo "<div class='table-responsive'>";
                            echo "<table class='table table-sm table-bordered'>";
                            echo "<thead><tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr></thead>";
                            echo "<tbody>";
                            foreach ($columns as $col) {
                                echo "<tr>";
                                echo "<td><code>{$col['Field']}</code></td>";
                                echo "<td>{$col['Type']}</td>";
                                echo "<td>{$col['Null']}</td>";
                                echo "<td>{$col['Key']}</td>";
                                echo "<td>{$col['Default']}</td>";
                                echo "<td>{$col['Extra']}</td>";
                                echo "</tr>";
                            }
                            echo "</tbody></table>";
                            echo "</div>";
                            
                            // 3. Vérifier les colonnes manquantes
                            echo "<hr><h6 class='text-primary'>3. Vérification des colonnes requises</h6>";
                            
                            $required_columns = [
                                'id' => 'int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY',
                                'classe_id' => 'int(11) NOT NULL',
                                'cours_id' => 'int(11) NOT NULL',
                                'date_session' => 'date NOT NULL',
                                'heure_debut' => 'time DEFAULT NULL',
                                'heure_fin' => 'time DEFAULT NULL',
                                'remarques' => 'text',
                                'statut' => "enum('actif','archivé') DEFAULT 'actif'",
                                'created_by' => 'int(11) NOT NULL',
                                'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
                                'updated_at' => 'timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
                            ];
                            
                            $existing_columns = array_column($columns, 'Field');
                            $missing_columns = [];
                            
                            foreach ($required_columns as $col_name => $definition) {
                                if (!in_array($col_name, $existing_columns)) {
                                    $missing_columns[$col_name] = $definition;
                                }
                            }
                            
                            if (empty($missing_columns)) {
                                echo "<p class='text-success'>✅ Toutes les colonnes requises sont présentes</p>";
                            } else {
                                echo "<div class='alert alert-warning'>";
                                echo "<h6>⚠️ Colonnes manquantes :</h6>";
                                echo "<ul>";
                                foreach ($missing_columns as $col_name => $definition) {
                                    echo "<li><code>{$col_name}</code> : {$definition}</li>";
                                }
                                echo "</ul>";
                                echo "</div>";
                                
                                // 4. Ajouter les colonnes manquantes
                                echo "<hr><h6 class='text-primary'>4. Ajout des colonnes manquantes</h6>";
                                
                                foreach ($missing_columns as $col_name => $definition) {
                                    try {
                                        $add_column_query = "ALTER TABLE sessions_presence ADD COLUMN `{$col_name}` {$definition}";
                                        $db->exec($add_column_query);
                                        echo "<p class='text-success'>✅ Colonne '{$col_name}' ajoutée avec succès</p>";
                                    } catch (Exception $e) {
                                        echo "<p class='text-danger'>❌ Erreur lors de l'ajout de '{$col_name}': " . htmlspecialchars($e->getMessage()) . "</p>";
                                    }
                                }
                                
                                // 5. Vérifier la structure finale
                                echo "<hr><h6 class='text-primary'>5. Structure finale après correction</h6>";
                                
                                $stmt = $db->prepare($describe_query);
                                $stmt->execute();
                                $final_columns = $stmt->fetchAll();
                                
                                echo "<div class='table-responsive'>";
                                echo "<table class='table table-sm table-bordered'>";
                                echo "<thead><tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr></thead>";
                                echo "<tbody>";
                                foreach ($final_columns as $col) {
                                    echo "<tr>";
                                    echo "<td><code>{$col['Field']}</code></td>";
                                    echo "<td>{$col['Type']}</td>";
                                    echo "<td>{$col['Null']}</td>";
                                    echo "<td>{$col['Key']}</td>";
                                    echo "<td>{$col['Default']}</td>";
                                    echo "<td>{$col['Extra']}</td>";
                                    echo "</tr>";
                                }
                                echo "</tbody></table>";
                                echo "</div>";
                            }
                            
                            // 6. Test de la fonction getClassStudents
                            echo "<hr><h6 class='text-primary'>6. Test de la fonction getClassStudents(4)</h6>";
                            $test_eleves = getClassStudents(4, $db);
                            echo "<p><strong>Résultat de getClassStudents(4) :</strong> <span class='badge bg-info'>" . count($test_eleves) . " élèves</span></p>";
                            
                            if (!empty($test_eleves)) {
                                echo "<div class='alert alert-success'>";
                                echo "<h6>🎉 Problème résolu !</h6>";
                                echo "<p>La fonction getClassStudents(4) retourne maintenant " . count($test_eleves) . " élève(s).</p>";
                                echo "</div>";
                            } else {
                                echo "<div class='alert alert-warning'>";
                                echo "<h6>⚠️ Problème persistant</h6>";
                                echo "<p>La fonction getClassStudents(4) retourne toujours 0 élève(s).</p>";
                                echo "</div>";
                            }
                            
                        } catch (Exception $e) {
                            echo "<div class='alert alert-danger'>";
                            echo "<h6>❌ Erreur lors de la vérification</h6>";
                            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="debug_inscriptions.php" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-2"></i>Retour au Debug
            </a>
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-house me-2"></i>Accueil Classes
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


