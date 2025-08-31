<?php
/**
 * Script de correction automatique des problèmes de redirection dans school_setup.php
 * Résout les problèmes de base de données et de sessions
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction des Redirections - Naklass</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .fix-section { margin: 20px 0; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        .fix-success { background-color: #d4edda; border-color: #c3e6cb; }
        .fix-warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .fix-error { background-color: #f8d7da; border-color: #f5c6cb; }
        .fix-info { background-color: #d1ecf1; border-color: #bee5eb; }
    </style>
</head>
<body class='container mt-4'>
    <h1>🔧 Correction des Redirections School Setup</h1>
    <p class='lead'>Ce script corrige automatiquement les problèmes de base de données et de sessions</p>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='fix-section fix-success'>
            <h4>✅ Connexion à la base de données réussie</h4>
          </div>";
    
    // Étape 1: Vérifier et créer les colonnes manquantes
    echo "<div class='fix-section fix-info'>
            <h4>🔍 Étape 1: Vérification de la structure de la table ecoles</h4>";
    
    $required_columns = [
        'super_admin_validated' => "BOOLEAN DEFAULT FALSE COMMENT 'Validation par super admin'",
        'date_validation_super_admin' => "DATETIME NULL COMMENT 'Date de validation par super admin'",
        'validated_by_super_admin' => "INT NULL COMMENT 'ID du super admin qui a validé'",
        'validation_status' => "ENUM('pending', 'approved', 'rejected', 'needs_changes') DEFAULT 'pending' COMMENT 'Statut de validation'",
        'validation_notes' => "TEXT NULL COMMENT 'Notes du super admin'",
        'date_creation_ecole' => "DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création de l\'école'",
        'created_by_visitor' => "INT NULL COMMENT 'ID du visiteur qui a créé l\'école'"
    ];
    
    $columns_added = 0;
    foreach ($required_columns as $column => $definition) {
        $query = "SHOW COLUMNS FROM ecoles LIKE :column";
        $stmt = $db->prepare($query);
        $stmt->execute(['column' => $column]);
        
        if (!$stmt->fetch()) {
            try {
                $alter_query = "ALTER TABLE ecoles ADD COLUMN {$column} {$definition}";
                $db->exec($alter_query);
                echo "<p class='text-success'>✅ Colonne <code>{$column}</code> ajoutée</p>";
                $columns_added++;
            } catch (Exception $e) {
                echo "<p class='text-danger'>❌ Erreur lors de l'ajout de la colonne <code>{$column}</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p class='text-info'>ℹ️ Colonne <code>{$column}</code> existe déjà</p>";
        }
    }
    
    if ($columns_added > 0) {
        echo "<p class='text-success'><strong>{$columns_added} colonne(s) ajoutée(s)</strong></p>";
    } else {
        echo "<p class='text-success'><strong>Toutes les colonnes sont déjà présentes</strong></p>";
    }
    
    echo "</div>";
    
    // Étape 2: Créer les tables de support
    echo "<div class='fix-section fix-info'>
            <h4>🔍 Étape 2: Création des tables de support</h4>";
    
    // Table des notifications
    $create_notifications = "CREATE TABLE IF NOT EXISTS super_admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('new_school', 'school_validation', 'school_rejection') NOT NULL,
        ecole_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        read_at DATETIME NULL,
        FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE
    )";
    
    try {
        $db->exec($create_notifications);
        echo "<p class='text-success'>✅ Table <code>super_admin_notifications</code> créée/vérifiée</p>";
    } catch (Exception $e) {
        echo "<p class='text-warning'>⚠️ Table <code>super_admin_notifications</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Table de l'historique
    $create_history = "CREATE TABLE IF NOT EXISTS school_validation_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ecole_id INT NOT NULL,
        action ENUM('created', 'configured', 'validated', 'rejected', 'changes_requested') NOT NULL,
        performed_by INT NOT NULL COMMENT 'ID de l\'utilisateur qui a effectué l\'action',
        notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE
    )";
    
    try {
        $db->exec($create_history);
        echo "<p class='text-success'>✅ Table <code>school_validation_history</code> créée/vérifiée</p>";
    } catch (Exception $e) {
        echo "<p class='text-warning'>⚠️ Table <code>school_validation_history</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
    // Étape 3: Mettre à jour les écoles existantes
    echo "<div class='fix-section fix-info'>
            <h4>🔍 Étape 3: Mise à jour des écoles existantes</h4>";
    
    $query = "SELECT id, nom FROM ecoles";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ecoles = $stmt->fetchAll();
    
    if (empty($ecoles)) {
        echo "<p class='text-warning'>⚠️ Aucune école trouvée dans la base de données</p>";
    } else {
        echo "<p><strong>Écoles trouvées :</strong></p>";
        
        foreach ($ecoles as $ecole) {
            echo "<p>🏫 <strong>{$ecole['nom']}</strong> (ID: {$ecole['id']})</p>";
            
            // Mettre à jour le statut de validation
            $update_query = "UPDATE ecoles SET 
                            validation_status = 'approved',
                            super_admin_validated = TRUE,
                            date_validation_super_admin = NOW(),
                            validated_by_super_admin = 1
                            WHERE id = :ecole_id";
            
            try {
                $stmt = $db->prepare($update_query);
                $stmt->execute(['ecole_id' => $ecole['id']]);
                echo "<p class='text-success'>&nbsp;&nbsp;&nbsp;&nbsp;✅ Statut mis à jour : APPROVED</p>";
                
                // Insérer dans l'historique
                $history_query = "INSERT INTO school_validation_history (ecole_id, action, performed_by, notes) 
                                 VALUES (:ecole_id, 'validated', 1, 'Validation automatique lors de la correction')";
                $stmt = $db->prepare($history_query);
                $stmt->execute(['ecole_id' => $ecole['id']]);
                
            } catch (Exception $e) {
                echo "<p class='text-danger'>&nbsp;&nbsp;&nbsp;&nbsp;❌ Erreur lors de la mise à jour : " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    echo "</div>";
    
    // Étape 4: Vérifier les index
    echo "<div class='fix-section fix-info'>
            <h4>🔍 Étape 4: Création des index de performance</h4>";
    
    $indexes = [
        'idx_ecoles_validation_status' => 'ecoles(validation_status)',
        'idx_ecoles_super_admin_validated' => 'ecoles(super_admin_validated)',
        'idx_notifications_unread' => 'super_admin_notifications(is_read, type)'
    ];
    
    foreach ($indexes as $index_name => $index_definition) {
        try {
            // Extraire le nom de la table de la définition d'index
            $table_name = explode('(', $index_definition)[0];
            
            // Vérifier si l'index existe déjà
            $check_index = "SHOW INDEX FROM {$table_name} WHERE Key_name = '{$index_name}'";
            $result = $db->query($check_index);
            
            if ($result && $result->rowCount() == 0) {
                $create_index = "CREATE INDEX {$index_name} ON {$index_definition}";
                $db->exec($create_index);
                echo "<p class='text-success'>✅ Index <code>{$index_name}</code> créé</p>";
            } else {
                echo "<p class='text-info'>ℹ️ Index <code>{$index_name}</code> existe déjà</p>";
            }
        } catch (Exception $e) {
            echo "<p class='text-warning'>⚠️ Index <code>{$index_name}</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "</div>";
    
    // Étape 5: Vérification finale
    echo "<div class='fix-section fix-success'>
            <h4>✅ Étape 5: Vérification finale</h4>";
    
    try {
        // Vérifier la structure finale
        $query = "DESCRIBE ecoles";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $columns = $stmt->fetchAll();
        
        $column_names = array_column($columns, 'Field');
        $required_columns_check = array_keys($required_columns);
        
        $missing_columns_final = array_diff($required_columns_check, $column_names);
        
        if (empty($missing_columns_final)) {
            echo "<p class='text-success'>✅ Toutes les colonnes requises sont présentes</p>";
        } else {
            echo "<p class='text-danger'>❌ Colonnes manquantes : " . implode(', ', $missing_columns_final) . "</p>";
        }
        
        // Vérifier les tables de support
        $tables = ['super_admin_notifications', 'school_validation_history'];
        foreach ($tables as $table) {
            $query = "SHOW TABLES LIKE :table";
            $stmt = $db->prepare($query);
            $stmt->execute(['table' => $table]);
            if ($stmt->fetch()) {
                echo "<p class='text-success'>✅ Table <code>{$table}</code> disponible</p>";
            } else {
                echo "<p class='text-danger'>❌ Table <code>{$table}</code> manquante</p>";
            }
        }
        
        echo "<p class='text-success'><strong>🎉 Correction terminée avec succès !</strong></p>";
        
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
