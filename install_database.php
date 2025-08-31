<?php
/**
 * Script d'installation et de vérification de la base de données Naklass
 * À exécuter une seule fois lors de l'installation
 */

require_once 'config/database.php';

echo "<h1>Installation Base de Données Naklass</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Connexion à la base de données réussie<br><br>";
    
    // Lire et exécuter le script de correction
    $sql_file = 'database/fix_admin_user.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        
        // Supprimer les commentaires et diviser en requêtes
        $sql_content = preg_replace('/--.*$/m', '', $sql_content);
        $queries = array_filter(array_map('trim', explode(';', $sql_content)));
        
        echo "<h3>Exécution des requêtes de correction:</h3>";
        
        foreach ($queries as $query) {
            if (!empty($query) && !preg_match('/^\s*(USE|SELECT)/i', $query)) {
                try {
                    $db->exec($query);
                    echo "✅ Requête exécutée<br>";
                } catch (PDOException $e) {
                    echo "⚠️ Avertissement: " . $e->getMessage() . "<br>";
                }
            }
        }
        
        echo "<br><h3>Vérification finale:</h3>";
        
        // Vérifier l'école
        $query = "SELECT * FROM ecoles WHERE id = 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $ecole = $stmt->fetch();
        
        if ($ecole) {
            echo "✅ École par défaut configurée: " . htmlspecialchars($ecole['nom']) . "<br>";
        }
        
        // Vérifier l'utilisateur admin
        $query = "SELECT u.*, e.nom_ecole as ecole_nom, r.code as role_code 
                  FROM utilisateurs u 
                  JOIN ecoles e ON u.ecole_id = e.id 
                  JOIN roles r ON u.role_id = r.id 
                  WHERE u.email = 'admin@naklass.cd'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $admin = $stmt->fetch();
        
        if ($admin && $admin['ecole_id']) {
            echo "✅ Utilisateur admin configuré correctement<br>";
            echo "- Email: admin@naklass.cd<br>";
            echo "- Mot de passe: password (à changer)<br>";
            echo "- École: " . htmlspecialchars($admin['ecole_nom']) . "<br>";
            echo "- Rôle: " . htmlspecialchars($admin['role_code']) . "<br>";
        } else {
            echo "❌ Problème avec l'utilisateur admin<br>";
        }
        
        echo "<br><h3>Installation terminée!</h3>";
        echo "<p><strong>Étapes suivantes:</strong></p>";
        echo "<ol>";
        echo "<li>Supprimez ce fichier install_database.php</li>";
        echo "<li>Supprimez le fichier debug_session.php</li>";
        echo "<li>Connectez-vous avec admin@naklass.cd / password</li>";
        echo "<li>Changez le mot de passe par défaut</li>";
        echo "</ol>";
        
    } else {
        echo "❌ Fichier SQL de correction non trouvé: $sql_file<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
    echo "<p>Vérifiez votre configuration dans config/database.php</p>";
}
?>
