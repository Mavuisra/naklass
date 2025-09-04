<?php
/**
 * Script pour vérifier la structure de la table utilisateurs
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>Structure de la table utilisateurs</h2>";
    
    $stmt = $db->query("DESCRIBE utilisateurs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Vérifier les colonnes nécessaires
    echo "<h3>Vérification des colonnes nécessaires</h3>";
    
    $column_names = array_column($columns, 'Field');
    
    $required_columns = [
        'ecole_id' => 'ID de l\'école',
        'nom' => 'Nom',
        'prenom' => 'Prénom',
        'email' => 'Email',
        'telephone' => 'Téléphone',
        'role' => 'Rôle',
        'statut' => 'Statut',
        'nom_utilisateur' => 'Nom d\'utilisateur',
        'mot_de_passe' => 'Mot de passe',
        'created_by_visitor' => 'Créé par visiteur',
        'date_creation' => 'Date de création'
    ];
    
    foreach ($required_columns as $col => $desc) {
        if (in_array($col, $column_names)) {
            echo "✅ $desc ($col)<br>";
        } else {
            echo "❌ $desc ($col) manquant<br>";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
