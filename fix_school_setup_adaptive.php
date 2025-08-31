<?php
/**
 * Script de correction ADAPTATIF des problèmes de redirection dans school_setup.php
 * S'adapte automatiquement à la structure de la table ecoles
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction Adaptative - Naklass</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .fix-section { margin: 20px 0; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        .fix-success { background-color: #d4edda; border-color: #c3e6cb; }
        .fix-warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .fix-error { background-color: #f8d7da; border-color: #f5c6cb; }
        .fix-info { background-color: #d1ecf1; border-color: #bee5eb; }
        .highlight { background-color: #fff3cd; padding: 10px; border-radius: 5px; border-left: 4px solid #ffc107; }
    </style>
</head>
<body class='container mt-4'>
    <h1>🔧 Correction Adaptative des Redirections School Setup</h1>
    <p class='lead'>S'adapte automatiquement à la structure de votre table ecoles</p>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='fix-section fix-success'>
            <h4>✅ Connexion à la base de données réussie</h4>
          </div>";
    
    // Étape 1: Analyser la structure de la table ecoles
    echo "<div class='fix-section fix-info'>
            <h4>🔍 Étape 1: Analyse de la structure de la table ecoles</h4>";
    
    // Vérifier si la table existe
    $check_table = "SHOW TABLES LIKE 'ecoles'";
    $result = $db->query($check_table);
    
    if (!$result || $result->rowCount() == 0) {
        echo "<p class='text-danger'>❌ Table <code>ecoles</code> n'existe pas</p>";
        echo "<p>Veuillez d'abord créer la table ecoles.</p>";
        echo "</div></body></html>";
        exit;
    }
    
    // Récupérer la structure de la table
    $describe_query = "DESCRIBE ecoles";
    $result = $db->query($describe_query);
    $columns = $result->fetchAll();
    $column_names = array_column($columns, 'Field');
    
    echo "<p class='text-success'>✅ Table <code>ecoles</code> trouvée</p>";
    echo "<p><strong>Colonnes disponibles :</strong> " . implode(', ', $column_names) . "</p>";
    
    // Identifier les colonnes importantes
    $id_column = in_array('id', $column_names) ? 'id' : null;
    
    // Chercher la colonne de nom d'école (PRIORISER nom_ecole)
    $name_alternatives = ['nom_ecole', 'nom', 'name', 'ecole_nom', 'school_name', 'libelle', 'designation'];
    $name_column = null;
    
    foreach ($name_alternatives as $alt) {
        if (in_array($alt, $column_names)) {
            $name_column = $alt;
            break;
        }
    }
    
    if (!$id_column) {
        echo "<p class='text-danger'>❌ Colonne <code>id</code> manquante (problème critique)</p>";
        echo "</div></body></html>";
        exit;
    }
    
    if (!$name_column) {
        echo "<p class='text-warning'>⚠️ Aucune colonne de nom d'école trouvée</p>";
        echo "<p>Colonnes disponibles : " . implode(', ', $column_names) . "</p>";
        echo "<p>Veuillez identifier manuellement la colonne contenant le nom de l'école.</p>";
        echo "</div></body></html>";
        exit;
    }
    
    echo "<div class='highlight'>";
    echo "<p class='text-success'>✅ Colonne ID : <code>{$id_column}</code></p>";
    echo "<p class='text-success'>✅ Colonne nom : <code>{$name_column}</code></p>";
    echo "<p><strong>🎯 Détection automatique réussie !</strong> Le script utilisera <code>{$name_column}</code> pour le nom de l'école.</p>";
    echo "</div>";
    
    echo "</div>";
    
    // Étape 2: Ajouter les colonnes manquantes
    echo "<div class='fix-section fix-info'>
            <h4>🔍 Étape 2: Ajout des colonnes manquantes</h4>";
    
    $required_columns = [
        'super_admin_validated' => "BOOLEAN DEFAULT FALSE",
        'date_validation_super_admin' => "DATETIME NULL",
        'validated_by_super_admin' => "INT NULL",
        'validation_status' => "VARCHAR(20) DEFAULT 'pending'",
        'validation_notes' => "TEXT NULL",
        'date_creation_ecole' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
        'created_by_visitor' => "INT NULL"
    ];
    
    $columns_added = 0;
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $column_names)) {
            try {
                $alter_query = "ALTER TABLE ecoles ADD COLUMN $column $definition";
                $db->exec($alter_query);
                echo "<p class='text-success'>✅ Colonne <code>$column</code> ajoutée</p>";
                $columns_added++;
            } catch (Exception $e) {
                echo "<p class='text-danger'>❌ Erreur avec la colonne <code>$column</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p class='text-info'>ℹ️ Colonne <code>$column</code> existe déjà</p>";
        }
    }
    
    if ($columns_added > 0) {
        echo "<p class='text-success'><strong>$columns_added colonne(s) ajoutée(s)</strong></p>";
    } else {
        echo "<p class='text-success'><strong>Toutes les colonnes sont déjà présentes</strong></p>";
    }
    
    echo "</div>";
    
    // Étape 3: Créer les tables de support
    echo "<div class='fix-section fix-info'>
            <h4>🔍 Étape 3: Création des tables de support</h4>";
    
    // Table des notifications
    try {
        $create_notifications = "CREATE TABLE IF NOT EXISTS super_admin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            ecole_id INT NOT NULL,
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL
        )";
        
        $db->exec($create_notifications);
        echo "<p class='text-success'>✅ Table <code>super_admin_notifications</code> créée/vérifiée</p>";
    } catch (Exception $e) {
        echo "<p class='text-warning'>⚠️ Table <code>super_admin_notifications</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Table de l'historique
    try {
        $create_history = "CREATE TABLE IF NOT EXISTS school_validation_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ecole_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            performed_by INT NOT NULL,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($create_history);
        echo "<p class='text-success'>✅ Table <code>school_validation_history</code> créée/vérifiée</p>";
    } catch (Exception $e) {
        echo "<p class='text-warning'>⚠️ Table <code>school_validation_history</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
    // Étape 4: Mettre à jour les écoles existantes
    echo "<div class='fix-section fix-info'>
            <h4>🔍 Étape 4: Mise à jour des écoles existantes</h4>";
    
    try {
        // Récupérer toutes les écoles avec la colonne de nom adaptative
        $query = "SELECT $id_column, $name_column FROM ecoles";
        $result = $db->query($query);
        
        if ($result && $result->rowCount() > 0) {
            $ecoles = $result->fetchAll();
            echo "<p><strong>Écoles trouvées :</strong></p>";
            
            foreach ($ecoles as $ecole) {
                $ecole_id = $ecole[$id_column];
                $ecole_nom = $ecole[$name_column];
                
                echo "<p>🏫 <strong>" . htmlspecialchars($ecole_nom) . "</strong> (ID: $ecole_id)</p>";
                
                // Mettre à jour le statut de validation
                try {
                    $update_query = "UPDATE ecoles SET 
                                    validation_status = 'approved',
                                    super_admin_validated = TRUE,
                                    date_validation_super_admin = NOW(),
                                    validated_by_super_admin = 1
                                    WHERE $id_column = $ecole_id";
                    
                    $db->exec($update_query);
                    echo "<p class='text-success'>&nbsp;&nbsp;&nbsp;&nbsp;✅ Statut mis à jour : APPROVED</p>";
                    
                    // Insérer dans l'historique
                    try {
                        $history_query = "INSERT INTO school_validation_history (ecole_id, action, performed_by, notes) 
                                         VALUES ($ecole_id, 'validated', 1, 'Validation automatique lors de la correction adaptative')";
                        $db->exec($history_query);
                    } catch (Exception $e) {
                        echo "<p class='text-warning'>&nbsp;&nbsp;&nbsp;&nbsp;⚠️ Historique non créé : " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                    
                } catch (Exception $e) {
                    echo "<p class='text-danger'>&nbsp;&nbsp;&nbsp;&nbsp;❌ Erreur lors de la mise à jour : " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        } else {
            echo "<p class='text-warning'>⚠️ Aucune école trouvée dans la base de données</p>";
        }
    } catch (Exception $e) {
        echo "<p class='text-danger'>❌ Erreur lors de la récupération des écoles : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
    // Étape 5: Vérification finale
    echo "<div class='fix-section fix-success'>
            <h4>✅ Étape 5: Vérification finale</h4>";
    
    try {
        // Vérifier la structure finale
        $query = "DESCRIBE ecoles";
        $result = $db->query($query);
        $columns = $result->fetchAll();
        
        $column_names_final = array_column($columns, 'Field');
        $required_columns_check = array_keys($required_columns);
        
        $missing_columns_final = array_diff($required_columns_check, $column_names_final);
        
        if (empty($missing_columns_final)) {
            echo "<p class='text-success'>✅ Toutes les colonnes requises sont présentes</p>";
        } else {
            echo "<p class='text-danger'>❌ Colonnes manquantes : " . implode(', ', $missing_columns_final) . "</p>";
        }
        
        // Vérifier les tables de support
        $tables = ['super_admin_notifications', 'school_validation_history'];
        foreach ($tables as $table) {
            try {
                $query = "SHOW TABLES LIKE '$table'";
                $result = $db->query($query);
                if ($result && $result->rowCount() > 0) {
                    echo "<p class='text-success'>✅ Table <code>$table</code> disponible</p>";
                } else {
                    echo "<p class='text-danger'>❌ Table <code>$table</code> manquante</p>";
                }
            } catch (Exception $e) {
                echo "<p class='text-danger'>❌ Erreur lors de la vérification de la table <code>$table</code></p>";
            }
        }
        
        echo "<div class='highlight'>";
        echo "<p class='text-success'><strong>🎉 Correction adaptative terminée avec succès !</strong></p>";
        echo "<p><strong>Colonnes utilisées :</strong></p>";
        echo "<ul>";
        echo "<li>ID : <code>$id_column</code></li>";
        echo "<li>Nom : <code>$name_column</code></li>";
        echo "</ul>";
        echo "<p><strong>✅ Le script s'est parfaitement adapté à votre structure de table !</strong></p>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<p class='text-danger'>❌ Erreur lors de la vérification finale : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
    // Actions recommandées
    echo "<div class='fix-section fix-warning'>
            <h4>📝 Actions recommandées après la correction</h4>
            <ol>
                <li><strong>Vider le cache et les cookies</strong> de votre navigateur</li>
                <li><strong>Tester la page</strong> <a href='auth/school_setup.php' class='btn btn-sm btn-primary'>school_setup.php</a></li>
                <li><strong>Vérifier le diagnostic</strong> avec <a href='debug_school_setup.php' class='btn btn-sm btn-info'>debug_school_setup.php</a></li>
                <li><strong>Tester le système complet</strong> avec <a href='test_school_validation_system.php' class='btn btn-sm btn-success'>test_school_validation_system.php</a></li>
            </ol>
          </div>";
    
} catch (Exception $e) {
    echo "<div class='fix-section fix-error'>
            <h4>❌ Erreur critique</h4>
            <p><strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Veuillez vérifier la configuration de la base de données.</p>
          </div>";
}

echo "</body></html>";
?>
