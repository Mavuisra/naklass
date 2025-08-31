<?php
/**
 * Script de mise à jour de la table classes
 * Ajoute les colonnes nécessaires pour les détails des cycles d'enseignement
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Mise à jour de la table classes</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class='container mt-4'>
        <h1>🔧 Mise à jour de la table classes</h1>
        <p class='text-muted'>Ajout des colonnes pour les détails des cycles d'enseignement</p>
        <hr>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<p class='success'>✅ Connexion à la base de données réussie</p>";
    
    // Vérifier la structure actuelle de la table
    echo "<h3>Structure actuelle de la table classes :</h3>";
    $describe_query = "DESCRIBE classes";
    $stmt = $db->prepare($describe_query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table class='table table-sm table-bordered'>
        <thead><tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr></thead>
        <tbody>";
    
    foreach ($columns as $column) {
        echo "<tr>
            <td>{$column['Field']}</td>
            <td>{$column['Type']}</td>
            <td>{$column['Null']}</td>
            <td>{$column['Key']}</td>
            <td>{$column['Default']}</td>
            <td>{$column['Extra']}</td>
        </tr>";
    }
    echo "</tbody></table>";
    
    // Ajouter les nouvelles colonnes
    echo "<h3>Ajout des nouvelles colonnes :</h3>";
    
    $alter_queries = [
        "ALTER TABLE classes ADD COLUMN IF NOT EXISTS niveau_detaille VARCHAR(100) NULL AFTER niveau" => "Colonne niveau_detaille",
        "ALTER TABLE classes ADD COLUMN IF NOT EXISTS option_section VARCHAR(100) NULL AFTER niveau_detaille" => "Colonne option_section",
        "ALTER TABLE classes ADD COLUMN IF NOT EXISTS cycle_complet TEXT NULL AFTER option_section" => "Colonne cycle_complet"
    ];
    
    foreach ($alter_queries as $sql => $description) {
        try {
            $db->exec($sql);
            echo "<p class='success'>✅ {$description} ajoutée avec succès</p>";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "<p class='info'>ℹ️ {$description} existe déjà</p>";
            } else {
                echo "<p class='error'>❌ Erreur lors de l'ajout de {$description}: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    // Créer les index
    echo "<h3>Création des index :</h3>";
    
    $index_queries = [
        "CREATE INDEX IF NOT EXISTS idx_classes_niveau_detaille ON classes(niveau_detaille)" => "Index niveau_detaille",
        "CREATE INDEX IF NOT EXISTS idx_classes_option_section ON classes(option_section)" => "Index option_section"
    ];
    
    foreach ($index_queries as $sql => $description) {
        try {
            $db->exec($sql);
            echo "<p class='success'>✅ {$description} créé avec succès</p>";
        } catch (Exception $e) {
            echo "<p class='info'>ℹ️ {$description} existe déjà ou erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    // Mettre à jour les données existantes
    echo "<h3>Mise à jour des données existantes :</h3>";
    
    try {
        $update_query = "UPDATE classes SET niveau_detaille = niveau WHERE niveau_detaille IS NULL";
        $affected = $db->exec($update_query);
        echo "<p class='success'>✅ {$affected} enregistrements mis à jour (niveau_detaille)</p>";
    } catch (Exception $e) {
        echo "<p class='warning'>⚠️ Erreur lors de la mise à jour: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Vérifier la structure finale
    echo "<h3>Structure finale de la table classes :</h3>";
    $stmt = $db->prepare("DESCRIBE classes");
    $stmt->execute();
    $final_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table class='table table-sm table-bordered'>
        <thead><tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr></thead>
        <tbody>";
    
    foreach ($final_columns as $column) {
        $row_class = '';
        if (in_array($column['Field'], ['niveau_detaille', 'option_section', 'cycle_complet'])) {
            $row_class = 'table-success';
        }
        
        echo "<tr class='{$row_class}'>
            <td><strong>{$column['Field']}</strong></td>
            <td>{$column['Type']}</td>
            <td>{$column['Null']}</td>
            <td>{$column['Key']}</td>
            <td>{$column['Default']}</td>
            <td>{$column['Extra']}</td>
        </tr>";
    }
    echo "</tbody></table>";
    
    echo "<div class='alert alert-success'>
        <h4>🎉 Mise à jour terminée avec succès !</h4>
        <p>La table classes a été mise à jour avec les nouvelles colonnes :</p>
        <ul>
            <li><strong>niveau_detaille</strong> : Stocke le niveau spécifique (ex: 1ere_primaire, 3eme_humanites)</li>
            <li><strong>option_section</strong> : Stocke l'option/section (ex: scientifique, technique_electricite)</li>
            <li><strong>cycle_complet</strong> : Stocke la description complète du cycle pour l'affichage</li>
        </ul>
        <p>Vous pouvez maintenant utiliser le formulaire de création de classe avec toutes les fonctionnalités !</p>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
        <h4>❌ Erreur critique</h4>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}

echo "</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>
