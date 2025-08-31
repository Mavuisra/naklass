<?php
/**
 * Script de mise à jour de la structure de base de données
 * Ajoute les colonnes manquantes à la table utilisateurs
 */

require_once 'config/database.php';

echo "<h1>🔧 Mise à Jour de la Structure de Base de Données</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Connexion à la base de données réussie<br><br>";
    
    echo "<h3>1. Vérification de la structure actuelle</h3>";
    
    // Vérifier quelles colonnes existent déjà
    $columns_query = "SHOW COLUMNS FROM utilisateurs";
    $columns_stmt = $db->prepare($columns_query);
    $columns_stmt->execute();
    $existing_columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Colonnes existantes dans la table utilisateurs:<br>";
    foreach ($existing_columns as $column) {
        echo "- $column<br>";
    }
    
    echo "<br><h3>2. Ajout des colonnes manquantes</h3>";
    
    // Colonnes à ajouter avec leurs définitions
    $columns_to_add = [
        'tentatives_connexion' => 'INT DEFAULT 0',
        'derniere_tentative' => 'DATETIME NULL',
        'token_reset' => 'VARCHAR(255) NULL',
        'token_reset_expire' => 'DATETIME NULL',
        'photo_path' => 'VARCHAR(500) NULL'
    ];
    
    $added_columns = [];
    $skipped_columns = [];
    
    foreach ($columns_to_add as $column_name => $column_definition) {
        if (!in_array($column_name, $existing_columns)) {
            try {
                $alter_query = "ALTER TABLE utilisateurs ADD COLUMN $column_name $column_definition";
                $db->exec($alter_query);
                $added_columns[] = $column_name;
                echo "✅ Colonne '$column_name' ajoutée<br>";
            } catch (PDOException $e) {
                echo "❌ Erreur lors de l'ajout de '$column_name': " . $e->getMessage() . "<br>";
            }
        } else {
            $skipped_columns[] = $column_name;
            echo "ℹ️ Colonne '$column_name' existe déjà<br>";
        }
    }
    
    echo "<br><h3>3. Mise à jour des valeurs par défaut</h3>";
    
    // Mettre à jour les valeurs par défaut pour les enregistrements existants
    if (in_array('tentatives_connexion', $added_columns)) {
        $update_query = "UPDATE utilisateurs SET tentatives_connexion = 0 WHERE tentatives_connexion IS NULL";
        $db->exec($update_query);
        echo "✅ Valeurs par défaut mises à jour pour 'tentatives_connexion'<br>";
    }
    
    echo "<br><h3>4. Vérification finale</h3>";
    
    // Vérifier la structure finale
    $final_columns_stmt = $db->prepare($columns_query);
    $final_columns_stmt->execute();
    $final_columns = $final_columns_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Structure finale de la table utilisateurs:<br>";
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace;'>";
    foreach ($final_columns as $column) {
        echo "$column<br>";
    }
    echo "</div>";
    
    echo "<br><h3>5. Test de connexion</h3>";
    
    // Tester si la connexion fonctionne maintenant
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>✅ Mise à jour terminée avec succès!</h4>";
    echo "<p><strong>Résumé:</strong></p>";
    echo "<ul>";
    echo "<li>Colonnes ajoutées: " . (count($added_columns) > 0 ? implode(', ', $added_columns) : 'Aucune') . "</li>";
    echo "<li>Colonnes existantes: " . (count($skipped_columns) > 0 ? implode(', ', $skipped_columns) : 'Aucune') . "</li>";
    echo "</ul>";
    echo "<p><strong>Vous pouvez maintenant:</strong></p>";
    echo "<ol>";
    echo "<li><a href='auth/login.php'>Tester la connexion</a></li>";
    echo "<li>Supprimer ce fichier (update_database_structure.php)</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "❌ <strong>Erreur:</strong> " . $e->getMessage() . "<br>";
    echo "<p>Vérifiez votre configuration dans config/database.php</p>";
    
    echo "<br><h3>🔧 Solution Alternative</h3>";
    echo "<p>Si l'erreur persiste, exécutez manuellement ces commandes SQL dans phpMyAdmin :</p>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;'>";
    echo "ALTER TABLE utilisateurs ADD COLUMN tentatives_connexion INT DEFAULT 0;<br>";
    echo "ALTER TABLE utilisateurs ADD COLUMN derniere_tentative DATETIME NULL;<br>";
    echo "ALTER TABLE utilisateurs ADD COLUMN token_reset VARCHAR(255) NULL;<br>";
    echo "ALTER TABLE utilisateurs ADD COLUMN token_reset_expire DATETIME NULL;<br>";
    echo "ALTER TABLE utilisateurs ADD COLUMN photo_path VARCHAR(500) NULL;<br>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Script de mise à jour automatique - Supprimez après utilisation</em></p>";
?>
