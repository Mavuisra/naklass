<?php
/**
 * Script simple pour ajouter les colonnes nécessaires au système multi-écoles
 * À utiliser si le script principal rencontre des problèmes
 */

require_once 'config/database.php';

function addColumnIfNotExists($db, $table, $column, $definition) {
    try {
        // Vérifier si la colonne existe
        $check = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check->rowCount() == 0) {
            // La colonne n'existe pas, l'ajouter
            $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
            $db->exec($sql);
            echo "✅ Colonne '$column' ajoutée à la table '$table'<br>";
            return true;
        } else {
            echo "ℹ️ Colonne '$column' existe déjà dans la table '$table'<br>";
            return false;
        }
    } catch (Exception $e) {
        echo "❌ Erreur lors de l'ajout de la colonne '$column': " . $e->getMessage() . "<br>";
        return false;
    }
}

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Ajout des colonnes - Naklass</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>";

echo "<h1>🔧 Ajout des colonnes pour le système multi-écoles</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<p class='success'>✅ Connexion à la base de données réussie</p>";
    
    // Liste des colonnes à ajouter
    $columns = [
        'configuration_complete' => 'BOOLEAN DEFAULT FALSE',
        'date_configuration' => 'DATE NULL',
        'sigle' => 'VARCHAR(10) NULL',
        'site_web' => 'VARCHAR(255) NULL',
        'fax' => 'VARCHAR(50) NULL',
        'bp' => 'VARCHAR(100) NULL',
        'regime' => "ENUM('public', 'privé', 'conventionné') DEFAULT 'privé'",
        'type_enseignement' => "SET('maternelle', 'primaire', 'secondaire', 'technique', 'professionnel', 'université') DEFAULT 'primaire'",
        'langue_enseignement' => "SET('français', 'anglais', 'lingala', 'kikongo', 'tshiluba', 'swahili') DEFAULT 'français'",
        'devise_principale' => "ENUM('CDF', 'USD', 'EUR') DEFAULT 'CDF'",
        'fuseau_horaire' => "VARCHAR(50) DEFAULT 'Africa/Kinshasa'",
        'numero_autorisation' => 'VARCHAR(100) NULL',
        'date_autorisation' => 'DATE NULL',
        'directeur_telephone' => 'VARCHAR(20) NULL',
        'directeur_email' => 'VARCHAR(255) NULL',
        'description_etablissement' => 'TEXT NULL'
    ];
    
    echo "<h2>Ajout des colonnes à la table 'ecoles':</h2>";
    
    $added_count = 0;
    foreach ($columns as $column => $definition) {
        if (addColumnIfNotExists($db, 'ecoles', $column, $definition)) {
            $added_count++;
        }
    }
    
    echo "<hr>";
    echo "<h2>Création des index:</h2>";
    
    // Créer les index
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_ecoles_config_complete ON ecoles(configuration_complete)");
        echo "✅ Index 'idx_ecoles_config_complete' créé<br>";
    } catch (Exception $e) {
        echo "ℹ️ Index 'idx_ecoles_config_complete' existe déjà<br>";
    }
    
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_ecoles_sigle ON ecoles(sigle)");
        echo "✅ Index 'idx_ecoles_sigle' créé<br>";
    } catch (Exception $e) {
        echo "ℹ️ Index 'idx_ecoles_sigle' existe déjà<br>";
    }
    
    echo "<hr>";
    echo "<h2>Mise à jour de l'école de démonstration:</h2>";
    
    // Mettre à jour l'école de démonstration
    try {
        $update_sql = "UPDATE ecoles SET 
                       configuration_complete = TRUE, 
                       date_configuration = CURDATE(),
                       sigle = 'NAKLASS',
                       regime = 'privé',
                       type_enseignement = 'primaire,secondaire',
                       langue_enseignement = 'français',
                       devise_principale = 'CDF',
                       description_etablissement = 'École de démonstration du système Naklass'
                       WHERE id = 1";
        $db->exec($update_sql);
        echo "✅ École de démonstration mise à jour<br>";
    } catch (Exception $e) {
        echo "❌ Erreur lors de la mise à jour de l'école de démonstration: " . $e->getMessage() . "<br>";
    }
    
    echo "<hr>";
    echo "<h2>📊 Résumé:</h2>";
    echo "<p><strong>$added_count</strong> nouvelles colonnes ajoutées</p>";
    echo "<p class='success'>🎉 Configuration terminée avec succès !</p>";
    
    echo "<h3>Prochaines étapes:</h3>";
    echo "<ol>";
    echo "<li>Connectez-vous en tant qu'administrateur</li>";
    echo "<li>Vous serez redirigé vers la page de configuration si votre école n'est pas configurée</li>";
    echo "<li>Remplissez toutes les informations de votre établissement</li>";
    echo "</ol>";
    
    echo "<p style='margin-top: 20px;'>";
    echo "<a href='auth/login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔑 Aller à la connexion</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur de connexion à la base de données: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>
