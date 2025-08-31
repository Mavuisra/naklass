<?php
/**
 * Script de diagnostic de la table ecoles
 * Vérifie la structure exacte de la table pour identifier les colonnes disponibles
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnostic Table Écoles - Naklass</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .diagnostic-section { margin: 20px 0; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        .diagnostic-success { background-color: #d4edda; border-color: #c3e6cb; }
        .diagnostic-warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .diagnostic-error { background-color: #f8d7da; border-color: #f5c6cb; }
        .diagnostic-info { background-color: #d1ecf1; border-color: #bee5eb; }
        .code-block { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; }
        .highlight { background-color: #fff3cd; padding: 10px; border-radius: 5px; border-left: 4px solid #ffc107; }
    </style>
</head>
<body class='container mt-4'>
    <h1>🔍 Diagnostic de la Table Écoles</h1>
    <p class='lead'>Vérification de la structure exacte de la table ecoles</p>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='diagnostic-section diagnostic-success'>
            <h4>✅ Connexion à la base de données réussie</h4>
          </div>";
    
    // Étape 1: Vérifier si la table ecoles existe
    echo "<div class='diagnostic-section diagnostic-info'>
            <h4>🔍 Étape 1: Vérification de l'existence de la table ecoles</h4>";
    
    $check_table = "SHOW TABLES LIKE 'ecoles'";
    $result = $db->query($check_table);
    
    if ($result && $result->rowCount() > 0) {
        echo "<p class='text-success'>✅ Table <code>ecoles</code> existe</p>";
    } else {
        echo "<p class='text-danger'>❌ Table <code>ecoles</code> n'existe pas</p>";
        echo "<p>Veuillez d'abord créer la table ecoles.</p>";
        echo "</div></body></html>";
        exit;
    }
    
    echo "</div>";
    
    // Étape 2: Afficher la structure complète de la table
    echo "<div class='diagnostic-section diagnostic-info'>
            <h4>🔍 Étape 2: Structure complète de la table ecoles</h4>";
    
    $describe_query = "DESCRIBE ecoles";
    $result = $db->query($describe_query);
    
    if ($result && $result->rowCount() > 0) {
        $columns = $result->fetchAll();
        
        echo "<p><strong>Colonnes disponibles :</strong></p>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-sm'>";
        echo "<thead><tr><th>Nom</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td><code>{$column['Field']}</code></td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
        echo "</div>";
        
        // Identifier les colonnes importantes
        $column_names = array_column($columns, 'Field');
        echo "<p><strong>Colonnes importantes identifiées :</strong></p>";
        echo "<ul>";
        
        if (in_array('id', $column_names)) {
            echo "<li>✅ <code>id</code> - Identifiant unique</li>";
        } else {
            echo "<li>❌ <code>id</code> - MANQUANT (problème critique)</li>";
        }
        
        // Chercher des alternatives pour le nom de l'école (INCLUANT nom_ecole)
        $name_alternatives = ['nom_ecole', 'nom', 'name', 'ecole_nom', 'school_name', 'libelle', 'designation'];
        $found_name_column = null;
        
        foreach ($name_alternatives as $alt) {
            if (in_array($alt, $column_names)) {
                $found_name_column = $alt;
                break;
            }
        }
        
        if ($found_name_column) {
            echo "<li>✅ <code>{$found_name_column}</code> - Nom de l'école</li>";
            echo "<div class='highlight'>";
            echo "<strong>🎯 COLONNE IDENTIFIÉE :</strong> Votre table utilise <code>{$found_name_column}</code> pour le nom de l'école";
            echo "</div>";
        } else {
            echo "<li>❌ Colonne de nom d'école - MANQUANTE</li>";
            echo "<li>Colonnes disponibles qui pourraient contenir le nom :</li>";
            echo "<ul>";
            foreach ($column_names as $col) {
                if (stripos($col, 'nom') !== false || stripos($col, 'name') !== false || 
                    stripos($col, 'libelle') !== false || stripos($col, 'designation') !== false ||
                    stripos($col, 'ecole') !== false) {
                    echo "<li><code>{$col}</code></li>";
                }
            }
            echo "</ul>";
        }
        
        if (in_array('configuration_complete', $column_names)) {
            echo "<li>✅ <code>configuration_complete</code> - Configuration complète</li>";
        } else {
            echo "<li>❌ <code>configuration_complete</code> - MANQUANTE</li>";
        }
        
        echo "</ul>";
        
    } else {
        echo "<p class='text-danger'>❌ Impossible de récupérer la structure de la table</p>";
    }
    
    echo "</div>";
    
    // Étape 3: Afficher un exemple de données
    echo "<div class='diagnostic-section diagnostic-info'>
            <h4>🔍 Étape 3: Exemple de données dans la table</h4>";
    
    try {
        // Essayer de récupérer les données avec les colonnes disponibles
        $sample_query = "SELECT * FROM ecoles LIMIT 3";
        $result = $db->query($sample_query);
        
        if ($result && $result->rowCount() > 0) {
            $sample_data = $result->fetchAll();
            
            echo "<p><strong>Exemple de données (3 premières lignes) :</strong></p>";
            echo "<div class='code-block'>";
            foreach ($sample_data as $index => $row) {
                echo "<strong>Ligne " . ($index + 1) . ":</strong><br>";
                foreach ($row as $column => $value) {
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;<code>{$column}</code>: " . htmlspecialchars($value) . "<br>";
                }
                echo "<br>";
            }
            echo "</div>";
        } else {
            echo "<p class='text-warning'>⚠️ Aucune donnée dans la table ecoles</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='text-danger'>❌ Erreur lors de la récupération des données : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
    // Étape 4: Test avec la colonne identifiée
    if (isset($found_name_column)) {
        echo "<div class='diagnostic-section diagnostic-success'>
                <h4>🧪 Étape 4: Test avec la colonne identifiée</h4>";
        
        try {
            $test_query = "SELECT id, $found_name_column FROM ecoles LIMIT 3";
            $result = $db->query($test_query);
            
            if ($result && $result->rowCount() > 0) {
                $test_data = $result->fetchAll();
                echo "<p class='text-success'>✅ Test réussi avec <code>$found_name_column</code> !</p>";
                echo "<p><strong>Données récupérées :</strong></p>";
                echo "<ul>";
                foreach ($test_data as $row) {
                    echo "<li>ID: <code>{$row['id']}</code> - Nom: <code>" . htmlspecialchars($row[$found_name_column]) . "</code></li>";
                }
                echo "</ul>";
            } else {
                echo "<p class='text-warning'>⚠️ Aucune donnée trouvée avec la requête de test</p>";
            }
        } catch (Exception $e) {
            echo "<p class='text-danger'>❌ Erreur lors du test : " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        echo "</div>";
    }
    
    // Étape 5: Recommandations
    echo "<div class='diagnostic-section diagnostic-warning'>
            <h4>📝 Recommandations</h4>";
    
    if (isset($found_name_column)) {
        echo "<div class='highlight'>";
        echo "<h5>🎉 Solution trouvée !</h5>";
        echo "<p>Votre table utilise <code>{$found_name_column}</code> pour le nom de l'école.</p>";
        echo "<p>Utilisez le script de correction adaptatif qui s'adaptera automatiquement.</p>";
        echo "</div>";
        
        echo "<h5>Actions à effectuer :</h5>";
        echo "<ul>";
        echo "<li><a href='fix_school_setup_adaptive.php' class='btn btn-sm btn-primary'>🔧 Correction adaptative (RECOMMANDÉ)</a></li>";
        echo "<li><a href='fix_school_setup_ultra_simple.php' class='btn btn-sm btn-info'>🔧 Correction ultra-simple</a></li>";
        echo "<li><a href='debug_school_setup.php' class='btn btn-sm btn-info'>🐛 Diagnostic complet</a></li>";
        echo "</ul>";
    } else {
        echo "<h5>Si aucune colonne de nom d'école n'est trouvée :</h5>";
        echo "<ol>";
        echo "<li><strong>Identifier manuellement</strong> la colonne contenant le nom de l'école</li>";
        echo "<li><strong>Modifier les scripts</strong> pour utiliser la bonne colonne</li>";
        echo "<li><strong>Ou créer la colonne manquante</strong> si nécessaire</li>";
        echo "</ol>";
        
        echo "<h5>Actions à effectuer :</h5>";
        echo "<ul>";
        echo "<li><a href='fix_school_setup_ultra_simple.php' class='btn btn-sm btn-primary'>🔧 Script de correction manuel</a></li>";
        echo "<li><a href='debug_school_setup.php' class='btn btn-sm btn-info'>🐛 Diagnostic complet</a></li>";
        echo "</ul>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='diagnostic-section diagnostic-error'>
            <h4>❌ Erreur critique</h4>
            <p><strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Veuillez vérifier la configuration de la base de données.</p>
          </div>";
}

echo "</body></html>";
?>
