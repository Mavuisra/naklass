<?php
/**
 * Correction rapide de la table classes
 * Ajoute toutes les colonnes manquantes en une seule fois
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction Rapide Table Classes - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .fix-header {
            background: linear-gradient(135deg, #fd7e14 0%, #e55a00 100%);
            color: white;
            padding: 2rem 0;
        }
        .fix-card {
            border-left: 4px solid #fd7e14;
            transition: transform 0.2s;
        }
        .fix-card:hover {
            transform: translateX(5px);
        }
        .success-icon { color: #28a745; }
        .warning-icon { color: #ffc107; }
        .error-icon { color: #dc3545; }
        .info-icon { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="fix-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-lightning me-3"></i>Correction Rapide Table Classes</h1>
                    <p class="mb-0">Ajout de toutes les colonnes manquantes en une seule opération</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <i class="bi bi-speedometer2 display-4 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <?php
                $success_count = 0;
                $error_count = 0;
                $corrections = [];
                
                // Étape 1: Vérification de l'état actuel
                echo "<div class='fix-card card mb-3'>";
                echo "<div class='card-header'><h5><i class='bi bi-1-circle me-2'></i>État actuel de la table classes</h5></div>";
                echo "<div class='card-body'>";
                
                try {
                    // Vérifier si la table existe
                    $stmt = $db->query("SHOW TABLES LIKE 'classes'");
                    if ($stmt->rowCount() > 0) {
                        echo "<i class='bi bi-check-circle success-icon me-2'></i>Table <strong>classes</strong> existe<br>";
                        
                        // Vérifier la structure actuelle
                        $stmt = $db->query("DESCRIBE classes");
                        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo "<h6 class='mt-3'>Colonnes actuelles :</h6>";
                        echo "<div class='table-responsive'>";
                        echo "<table class='table table-sm table-bordered'>";
                        echo "<thead><tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr></thead>";
                        echo "<tbody>";
                        
                        $existing_columns = [];
                        foreach ($columns as $column) {
                            $existing_columns[] = $column['Field'];
                            echo "<tr>";
                            echo "<td>{$column['Field']}</td>";
                            echo "<td>{$column['Type']}</td>";
                            echo "<td>{$column['Null']}</td>";
                            echo "<td>{$column['Key']}</td>";
                            echo "<td>{$column['Default']}</td>";
                            echo "</tr>";
                        }
                        
                        echo "</tbody></table></div>";
                        
                        // Identifier les colonnes manquantes
                        $required_columns = [
                            'nom' => 'VARCHAR(100) NOT NULL',
                            'niveau_id' => 'BIGINT NULL',
                            'section_id' => 'BIGINT NULL',
                            'description' => 'TEXT',
                            'capacite_max' => 'INT NULL',
                            'enseignant_principal_id' => 'BIGINT NULL',
                            'salle' => 'VARCHAR(50) NULL',
                            'horaire_debut' => 'TIME NULL',
                            'horaire_fin' => 'TIME NULL',
                            'statut' => "ENUM('active', 'preparation', 'suspendue', 'fermee') DEFAULT 'active'",
                            'created_by' => 'BIGINT NULL'
                        ];
                        
                        $missing_columns = [];
                        foreach ($required_columns as $col => $type) {
                            if (!in_array($col, $existing_columns)) {
                                $missing_columns[$col] = $type;
                            }
                        }
                        
                        if (!empty($missing_columns)) {
                            echo "<div class='alert alert-warning mt-3'>";
                            echo "<i class='bi bi-exclamation-triangle me-2'></i>";
                            echo "<strong>Colonnes manquantes détectées :</strong>";
                            echo "<ul class='mb-0 mt-2'>";
                            foreach ($missing_columns as $col => $type) {
                                echo "<li><code>$col</code> ($type)</li>";
                            }
                            echo "</ul>";
                            echo "</div>";
                        } else {
                            echo "<div class='alert alert-success mt-3'>";
                            echo "<i class='bi bi-check-circle me-2'></i>";
                            echo "<strong>Toutes les colonnes requises sont présentes</strong>";
                            echo "</div>";
                            $success_count++;
                        }
                        
                    } else {
                        echo "<i class='bi bi-x-circle error-icon me-2'></i>Table <strong>classes</strong> n'existe pas<br>";
                        $error_count++;
                    }
                    
                } catch (PDOException $e) {
                    echo "<i class='bi bi-x-circle error-icon me-2'></i>Erreur lors de la vérification : " . $e->getMessage() . "<br>";
                    $error_count++;
                }
                
                echo "</div></div>";
                
                // Étape 2: Correction rapide
                if (!empty($missing_columns) || $error_count > 0) {
                    echo "<div class='fix-card card mb-3'>";
                    echo "<div class='card-header'><h5><i class='bi bi-2-circle me-2'></i>Correction rapide</h5></div>";
                    echo "<div class='card-body'>";
                    
                    try {
                        if ($error_count > 0) {
                            // Créer la table complète
                            echo "<h6>Création complète de la table classes</h6>";
                            
                            $create_sql = "
                                CREATE TABLE IF NOT EXISTS classes (
                                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                                    ecole_id BIGINT NOT NULL,
                                    nom VARCHAR(100) NOT NULL,
                                    niveau_id BIGINT NULL,
                                    section_id BIGINT NULL,
                                    description TEXT,
                                    capacite_max INT NULL,
                                    enseignant_principal_id BIGINT NULL,
                                    salle VARCHAR(50) NULL,
                                    horaire_debut TIME NULL,
                                    horaire_fin TIME NULL,
                                    statut ENUM('active', 'preparation', 'suspendue', 'fermee') DEFAULT 'active',
                                    created_by BIGINT NULL,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    
                                    FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE,
                                    FOREIGN KEY (niveau_id) REFERENCES niveaux(id) ON DELETE SET NULL,
                                    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
                                    FOREIGN KEY (enseignant_principal_id) REFERENCES enseignants(id) ON DELETE SET NULL,
                                    FOREIGN KEY (created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL,
                                    
                                    UNIQUE KEY unique_classe_nom_ecole (ecole_id, nom),
                                    INDEX idx_classes_ecole (ecole_id),
                                    INDEX idx_classes_niveau (niveau_id),
                                    INDEX idx_classes_section (section_id),
                                    INDEX idx_classes_enseignant (enseignant_principal_id),
                                    INDEX idx_classes_statut (statut)
                                )";
                            
                            $db->exec($create_sql);
                            echo "<i class='bi bi-check-circle success-icon me-2'></i>Table <strong>classes</strong> créée avec succès<br>";
                            $corrections[] = "Table classes créée complètement";
                            $success_count++;
                            
                        } else {
                            // Ajouter toutes les colonnes manquantes en une seule requête ALTER
                            echo "<h6>Ajout de toutes les colonnes manquantes en une seule opération</h6>";
                            
                            $alter_parts = [];
                            foreach ($missing_columns as $col => $type) {
                                $alter_parts[] = "ADD COLUMN $col $type";
                            }
                            
                            if (!empty($alter_parts)) {
                                $alter_sql = "ALTER TABLE classes " . implode(", ", $alter_parts);
                                $db->exec($alter_sql);
                                
                                echo "<i class='bi bi-check-circle success-icon me-2'></i>Toutes les colonnes manquantes ajoutées avec succès<br>";
                                echo "<small class='text-muted'>Requête exécutée : " . htmlspecialchars($alter_sql) . "</small>";
                                
                                foreach ($missing_columns as $col => $type) {
                                    $corrections[] = "Colonne $col ajoutée";
                                }
                            }
                        }
                        
                        // Ajouter les contraintes de clés étrangères si nécessaire
                        echo "<h6 class='mt-3'>Vérification des contraintes de clés étrangères</h6>";
                        
                        try {
                            // Vérifier et ajouter la contrainte niveau_id
                            $stmt = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                                               WHERE TABLE_NAME = 'classes' AND COLUMN_NAME = 'niveau_id' 
                                               AND REFERENCED_TABLE_NAME IS NOT NULL");
                            if ($stmt->rowCount() == 0) {
                                $db->exec("ALTER TABLE classes ADD CONSTRAINT fk_classes_niveau 
                                          FOREIGN KEY (niveau_id) REFERENCES niveaux(id) ON DELETE SET NULL");
                                echo "<i class='bi bi-check-circle success-icon me-2'></i>Contrainte FK niveau_id ajoutée<br>";
                                $corrections[] = "Contrainte FK niveau_id ajoutée";
                            }
                        } catch (PDOException $e) {
                            echo "<i class='bi bi-exclamation-triangle warning-icon me-2'></i>Avertissement contrainte niveau_id : " . $e->getMessage() . "<br>";
                        }
                        
                        try {
                            // Vérifier et ajouter la contrainte section_id
                            $stmt = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                                               WHERE TABLE_NAME = 'classes' AND COLUMN_NAME = 'section_id' 
                                               AND REFERENCED_TABLE_NAME IS NOT NULL");
                            if ($stmt->rowCount() == 0) {
                                $db->exec("ALTER TABLE classes ADD CONSTRAINT fk_classes_section 
                                          FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL");
                                echo "<i class='bi bi-check-circle success-icon me-2'></i>Contrainte FK section_id ajoutée<br>";
                                $corrections[] = "Contrainte FK section_id ajoutée";
                            }
                        } catch (PDOException $e) {
                            echo "<i class='bi bi-exclamation-triangle warning-icon me-2'></i>Avertissement contrainte section_id : " . $e->getMessage() . "<br>";
                        }
                        
                    } catch (PDOException $e) {
                        echo "<i class='bi bi-x-circle error-icon me-2'></i>Erreur lors de la correction : " . $e->getMessage() . "<br>";
                        $error_count++;
                    }
                    
                    echo "</div></div>";
                }
                
                // Étape 3: Test de la table corrigée
                if ($error_count == 0) {
                    echo "<div class='fix-card card mb-3'>";
                    echo "<div class='card-header'><h5><i class='bi bi-3-circle me-2'></i>Test de la table corrigée</h5></div>";
                    echo "<div class='card-body'>";
                    
                    try {
                        // Tester une requête simple
                        $test_query = "SELECT COUNT(*) as total FROM classes";
                        $stmt = $db->query($test_query);
                        $result = $stmt->fetch();
                        
                        echo "<i class='bi bi-check-circle success-icon me-2'></i>Test de requête réussi<br>";
                        echo "<small class='text-muted'>Nombre de classes dans la base : " . $result['total'] . "</small><br>";
                        
                        // Tester une requête avec les nouvelles colonnes
                        $test_query2 = "SELECT id, nom, description, capacite_max FROM classes LIMIT 1";
                        $stmt = $db->query($test_query2);
                        
                        echo "<i class='bi bi-check-circle success-icon me-2'></i>Test des nouvelles colonnes réussi<br>";
                        $success_count++;
                        
                    } catch (PDOException $e) {
                        echo "<i class='bi bi-x-circle error-icon me-2'></i>Erreur lors du test : " . $e->getMessage() . "<br>";
                        $error_count++;
                    }
                    
                    echo "</div></div>";
                }
                
                // Résumé final
                echo "<div class='card'>";
                echo "<div class='card-header'>";
                echo "<h5><i class='bi bi-flag-fill me-2'></i>Résumé de la correction rapide</h5>";
                echo "</div>";
                echo "<div class='card-body'>";
                
                if ($error_count == 0) {
                    echo "<div class='alert alert-success'>";
                    echo "<h6><i class='bi bi-check-circle-fill me-2'></i>Correction rapide réussie !</h6>";
                    echo "<p class='mb-0'>La table classes a été corrigée avec succès. Toutes les colonnes requises sont maintenant présentes.</p>";
                    echo "</div>";
                    
                    if (!empty($corrections)) {
                        echo "<div class='alert alert-info'>";
                        echo "<h6><i class='bi bi-info-circle me-2'></i>Corrections effectuées</h6>";
                        echo "<ul class='mb-0'>";
                        foreach ($corrections as $correction) {
                            echo "<li>$correction</li>";
                        }
                        echo "</ul>";
                        echo "</div>";
                    }
                    
                    echo "<div class='d-grid gap-2 d-md-flex justify-content-md-end'>";
                    echo "<a href='classes/create.php' class='btn btn-success me-md-2'>";
                    echo "<i class='bi bi-plus-circle me-2'></i>Créer une Classe";
                    echo "</a>";
                    echo "<a href='classes/index.php' class='btn btn-primary'>";
                    echo "<i class='bi bi-building me-2'></i>Voir les Classes";
                    echo "</a>";
                    echo "</div>";
                    
                } else {
                    echo "<div class='alert alert-danger'>";
                    echo "<h6><i class='bi bi-x-circle-fill me-2'></i>Problèmes persistants</h6>";
                    echo "<p class='mb-0'>$error_count erreur(s) détectée(s). La table classes n'a pas pu être complètement corrigée.</p>";
                    echo "</div>";
                    
                    echo "<div class='d-grid'>";
                    echo "<a href='fix_classes_table.php' class='btn btn-warning'>";
                    echo "<i class='bi bi-wrench me-2'></i>Correction Complète";
                    echo "</a>";
                    echo "</div>";
                }
                
                echo "<div class='mt-3'>";
                echo "<small class='text-muted'>";
                echo "Tests réussis: $success_count | Erreurs: $error_count";
                if (!empty($corrections)) {
                    echo " | Corrections: " . count($corrections);
                }
                echo "</small>";
                echo "</div>";
                
                echo "</div></div>";
                ?>
                
                <div class="text-center mt-4">
                    <a href="auth/dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house me-2"></i>Retour au Tableau de Bord
                    </a>
                </div>
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
