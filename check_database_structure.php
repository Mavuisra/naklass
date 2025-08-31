<?php
/**
 * Script de vérification de la structure de base de données
 * Vérifie que toutes les colonnes nécessaires existent
 */

require_once 'config/database.php';

echo "<h1>🔍 Vérification de la Structure de Base de Données</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Connexion à la base de données réussie<br><br>";
    
    echo "<h3>1. Vérification de la table 'roles'</h3>";
    $roles_columns = $db->query("SHOW COLUMNS FROM roles")->fetchAll(PDO::FETCH_COLUMN);
    echo "Colonnes dans la table 'roles':<br>";
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace;'>";
    foreach ($roles_columns as $column) {
        echo "- $column<br>";
    }
    echo "</div>";
    
    // Vérifier si la colonne 'libelle' existe
    if (in_array('libelle', $roles_columns)) {
        echo "✅ Colonne 'libelle' trouvée dans la table 'roles'<br>";
    } else {
        echo "❌ Colonne 'libelle' manquante dans la table 'roles'<br>";
    }
    
    echo "<br><h3>2. Vérification de la table 'utilisateurs'</h3>";
    $users_columns = $db->query("SHOW COLUMNS FROM utilisateurs")->fetchAll(PDO::FETCH_COLUMN);
    echo "Colonnes dans la table 'utilisateurs':<br>";
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace;'>";
    foreach ($users_columns as $column) {
        echo "- $column<br>";
    }
    echo "</div>";
    
    // Vérifier les colonnes spécifiques
    $required_user_columns = ['photo_path', 'tentatives_connexion', 'derniere_tentative'];
    foreach ($required_user_columns as $req_column) {
        if (in_array($req_column, $users_columns)) {
            echo "✅ Colonne '$req_column' trouvée<br>";
        } else {
            echo "⚠️ Colonne '$req_column' manquante (optionnelle)<br>";
        }
    }
    
    echo "<br><h3>3. Vérification de la table 'ecoles'</h3>";
    $ecoles_columns = $db->query("SHOW COLUMNS FROM ecoles")->fetchAll(PDO::FETCH_COLUMN);
    echo "Colonnes dans la table 'ecoles':<br>";
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace;'>";
    foreach ($ecoles_columns as $column) {
        echo "- $column<br>";
    }
    echo "</div>";
    
    // Vérifier si la colonne 'nom' existe
    if (in_array('nom', $ecoles_columns)) {
        echo "✅ Colonne 'nom' trouvée dans la table 'ecoles'<br>";
    } else {
        echo "❌ Colonne 'nom' manquante dans la table 'ecoles'<br>";
    }
    
    echo "<br><h3>4. Test de requête SQL</h3>";
    
    // Tester la requête du profil
    try {
        $test_query = "SELECT u.id, u.nom, u.prenom, u.email, r.libelle as role_nom, r.code as role_code, e.nom_ecole as ecole_nom 
                       FROM utilisateurs u 
                       JOIN roles r ON u.role_id = r.id 
                       JOIN ecoles e ON u.ecole_id = e.id 
                       WHERE u.statut = 'actif' 
                       LIMIT 1";
        $test_stmt = $db->prepare($test_query);
        $test_stmt->execute();
        $test_result = $test_stmt->fetch();
        
        if ($test_result) {
            echo "✅ Requête de test réussie<br>";
            echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
            echo "<strong>Exemple de données :</strong><br>";
            echo "- Utilisateur: " . htmlspecialchars($test_result['prenom'] . ' ' . $test_result['nom']) . "<br>";
            echo "- Email: " . htmlspecialchars($test_result['email']) . "<br>";
            echo "- Rôle: " . htmlspecialchars($test_result['role_nom']) . "<br>";
            echo "- École: " . htmlspecialchars($test_result['ecole_nom']) . "<br>";
            echo "</div>";
        } else {
            echo "⚠️ Aucun utilisateur trouvé pour le test<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Erreur dans la requête de test: " . $e->getMessage() . "<br>";
    }
    
    echo "<br><h3>5. Données de test dans les tables</h3>";
    
    // Compter les enregistrements
    $roles_count = $db->query("SELECT COUNT(*) FROM roles WHERE statut = 'actif'")->fetchColumn();
    $users_count = $db->query("SELECT COUNT(*) FROM utilisateurs WHERE statut = 'actif'")->fetchColumn();
    $ecoles_count = $db->query("SELECT COUNT(*) FROM ecoles WHERE statut = 'actif'")->fetchColumn();
    
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px;'>";
    echo "<strong>Statistiques des données :</strong><br>";
    echo "- Rôles actifs: $roles_count<br>";
    echo "- Utilisateurs actifs: $users_count<br>";
    echo "- Écoles actives: $ecoles_count<br>";
    echo "</div>";
    
    echo "<br><h3>6. Recommandations</h3>";
    
    $all_good = true;
    if (!in_array('libelle', $roles_columns)) {
        echo "❌ Exécutez le script de création de base de données complet<br>";
        $all_good = false;
    }
    if (!in_array('photo_path', $users_columns)) {
        echo "⚠️ Exécutez 'update_database_structure.php' pour ajouter les colonnes manquantes<br>";
    }
    if ($users_count == 0) {
        echo "⚠️ Aucun utilisateur dans la base de données. Exécutez 'quick_setup.php'<br>";
        $all_good = false;
    }
    
    if ($all_good && $users_count > 0) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4>✅ Tout semble en ordre !</h4>";
        echo "<p>Votre base de données est correctement configurée.</p>";
        echo "<p><strong>Vous pouvez maintenant :</strong></p>";
        echo "<ol>";
        echo "<li><a href='profile/'>Accéder aux pages de profil</a></li>";
        echo "<li><a href='auth/login.php'>Tester la connexion</a></li>";
        echo "<li>Supprimer ce fichier de vérification</li>";
        echo "</ol>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Erreur:</strong> " . $e->getMessage() . "<br>";
    echo "<p>Vérifiez votre configuration dans config/database.php</p>";
}

echo "<hr>";
echo "<p><em>Script de vérification - Supprimez après utilisation</em></p>";
?>
