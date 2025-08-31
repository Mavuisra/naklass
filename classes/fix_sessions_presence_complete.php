<?php
/**
 * Script complet pour corriger la table sessions_presence
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

$database = new Database();
$db = $database->getConnection();

$page_title = "Correction Complète de la Table Sessions Présence";
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
                        <h5 class="mb-0">🔧 Correction Complète de la Table</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            // 1. Vérifier l'existence de la table
                            echo "<h6 class='text-primary'>1. Vérification de l'existence de la table</h6>";
                            
                            $check_table = "SHOW TABLES LIKE 'sessions_presence'";
                            $stmt = $db->prepare($check_table);
                            $stmt->execute();
                            $table_exists = $stmt->fetch();
                            
                            if (!$table_exists) {
                                echo "<p class='text-danger'>❌ Table 'sessions_presence' n'existe pas</p>";
                                echo "<div class='alert alert-warning'>";
                                echo "<h6>⚠️ Création de la table...</h6>";
                                
                                $create_table = "CREATE TABLE `sessions_presence` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `classe_id` int(11) NOT NULL,
                                    `cours_id` int(11) NOT NULL,
                                    `date_session` date NOT NULL,
                                    `heure_debut` time DEFAULT NULL,
                                    `heure_fin` time DEFAULT NULL,
                                    `remarques` text,
                                    `statut` enum('actif','archivé') DEFAULT 'actif',
                                    `created_by` int(11) NOT NULL,
                                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                                    PRIMARY KEY (`id`),
                                    KEY `classe_id` (`classe_id`),
                                    KEY `cours_id` (`cours_id`),
                                    KEY `date_session` (`date_session`),
                                    UNIQUE KEY `unique_session` (`classe_id`, `cours_id`, `date_session`)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                                
                                $db->exec($create_table);
                                echo "<p class='text-success'>✅ Table 'sessions_presence' créée avec succès</p>";
                            } else {
                                echo "<p class='text-success'>✅ Table 'sessions_presence' existe</p>";
                            }
                            
                            // 2. Supprimer toutes les contraintes de clés étrangères problématiques
                            echo "<hr><h6 class='text-primary'>2. Suppression des contraintes problématiques</h6>";
                            
                            try {
                                // Récupérer toutes les contraintes de clés étrangères
                                $fk_query = "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                                           WHERE TABLE_SCHEMA = DATABASE() 
                                           AND TABLE_NAME = 'sessions_presence' 
                                           AND REFERENCED_TABLE_NAME IS NOT NULL";
                                $stmt = $db->prepare($fk_query);
                                $stmt->execute();
                                $foreign_keys = $stmt->fetchAll();
                                
                                foreach ($foreign_keys as $fk) {
                                    $constraint_name = $fk['CONSTRAINT_NAME'];
                                    try {
                                        $drop_fk = "ALTER TABLE sessions_presence DROP FOREIGN KEY `{$constraint_name}`";
                                        $db->exec($drop_fk);
                                        echo "<p class='text-success'>✅ Contrainte '{$constraint_name}' supprimée</p>";
                                    } catch (Exception $e) {
                                        echo "<p class='text-warning'>⚠️ Impossible de supprimer '{$constraint_name}': " . $e->getMessage() . "</p>";
                                    }
                                }
                            } catch (Exception $e) {
                                echo "<p class='text-warning'>⚠️ Erreur lors de la vérification des contraintes: " . $e->getMessage() . "</p>";
                            }
                            
                            // 3. Vérifier et corriger la structure
                            echo "<hr><h6 class='text-primary'>3. Vérification et correction de la structure</h6>";
                            
                            $describe_query = "DESCRIBE sessions_presence";
                            $stmt = $db->prepare($describe_query);
                            $stmt->execute();
                            $columns = $stmt->fetchAll();
                            
                            echo "<p><strong>Structure actuelle :</strong></p>";
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
                            
                            // 4. Ajouter les colonnes manquantes
                            echo "<hr><h6 class='text-primary'>4. Ajout des colonnes manquantes</h6>";
                            
                            $required_columns = [
                                'remarques' => 'text',
                                'statut' => "enum('actif','archivé') DEFAULT 'actif",
                                'updated_at' => 'timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
                            ];
                            
                            $existing_columns = array_column($columns, 'Field');
                            
                            foreach ($required_columns as $col_name => $definition) {
                                if (!in_array($col_name, $existing_columns)) {
                                    try {
                                        $add_column_query = "ALTER TABLE sessions_presence ADD COLUMN `{$col_name}` {$definition}";
                                        $db->exec($add_column_query);
                                        echo "<p class='text-success'>✅ Colonne '{$col_name}' ajoutée</p>";
                                    } catch (Exception $e) {
                                        echo "<p class='text-danger'>❌ Erreur pour '{$col_name}': " . $e->getMessage() . "</p>";
                                    }
                                } else {
                                    echo "<p class='text-muted'>ℹ️ Colonne '{$col_name}' existe déjà</p>";
                                }
                            }
                            
                            // 5. Vérifier que la table presences existe
                            echo "<hr><h6 class='text-primary'>5. Vérification de la table presences</h6>";
                            
                            $check_presences = "SHOW TABLES LIKE 'presences'";
                            $stmt = $db->prepare($check_presences);
                            $stmt->execute();
                            $presences_exists = $stmt->fetch();
                            
                            if (!$presences_exists) {
                                echo "<p class='text-warning'>⚠️ Table 'presences' n'existe pas, création...</p>";
                                
                                $create_presences = "CREATE TABLE `presences` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `session_id` int(11) NOT NULL,
                                    `eleve_id` int(11) NOT NULL,
                                    `statut` enum('present','absent','retard','justifie') NOT NULL DEFAULT 'absent',
                                    `justification` text,
                                    `created_by` int(11) NOT NULL,
                                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                    PRIMARY KEY (`id`),
                                    KEY `session_id` (`session_id`),
                                    KEY `eleve_id` (`eleve_id`),
                                    UNIQUE KEY `unique_presence` (`session_id`, `eleve_id`)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                                
                                $db->exec($create_presences);
                                echo "<p class='text-success'>✅ Table 'presences' créée</p>";
                            } else {
                                echo "<p class='text-success'>✅ Table 'presences' existe</p>";
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
                            
                            // 7. Test d'insertion dans sessions_presence
                            echo "<hr><h6 class='text-primary'>7. Test d'insertion dans sessions_presence</h6>";
                            
                            try {
                                $test_insert = "INSERT INTO sessions_presence 
                                              (classe_id, cours_id, date_session, heure_debut, heure_fin, remarques, created_by) 
                                              VALUES (4, 13, CURDATE(), '08:00:00', '09:00:00', 'Test de correction', 1)";
                                $db->exec($test_insert);
                                $test_id = $db->lastInsertId();
                                
                                // Supprimer le test
                                $db->exec("DELETE FROM sessions_presence WHERE id = {$test_id}");
                                
                                echo "<div class='alert alert-success'>";
                                echo "<h6>✅ Test d'insertion réussi !</h6>";
                                echo "<p>La table sessions_presence fonctionne maintenant correctement.</p>";
                                echo "</div>";
                            } catch (Exception $e) {
                                echo "<div class='alert alert-danger'>";
                                echo "<h6>❌ Test d'insertion échoué</h6>";
                                echo "<p>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
                                echo "</div>";
                            }
                            
                        } catch (Exception $e) {
                            echo "<div class='alert alert-danger'>";
                            echo "<h6>❌ Erreur lors de la correction</h6>";
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


