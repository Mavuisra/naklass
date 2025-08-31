<?php
/**
 * Installation Super Admin - Version Simple
 * Script simplifié sans requêtes préparées complexes
 */

require_once 'config/database.php';

function executeSQL($db, $sql, $description) {
    try {
        $db->exec($sql);
        return "✅ $description - Réussi";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || 
            strpos($e->getMessage(), 'already exists') !== false ||
            strpos($e->getMessage(), 'Duplicate entry') !== false) {
            return "ℹ️ $description - Déjà existant (ignoré)";
        } else {
            return "⚠️ $description - " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Installation Super Admin Simple - Naklass</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .info { background: #cce7ff; color: #004085; border-left: 4px solid #007bff; }
        .warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .credentials { background: #e2f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ Installation Super Admin - Version Simple</h1>
        <p>Configuration du système Super Administrateur étape par étape</p>
    </div>

<?php
try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='result success'>✅ Connexion à la base de données réussie</div>";
    
    // Étape 1: Ajouter les colonnes utilisateurs
    echo "<h3>Étape 1: Configuration des utilisateurs</h3>";
    
    $queries = [
        "ALTER TABLE utilisateurs ADD COLUMN is_super_admin BOOLEAN DEFAULT FALSE" => "Ajout colonne is_super_admin",
        "ALTER TABLE utilisateurs ADD COLUMN niveau_acces ENUM('super_admin', 'school_admin', 'user') DEFAULT 'user'" => "Ajout colonne niveau_acces"
    ];
    
    foreach ($queries as $sql => $desc) {
        $result = executeSQL($db, $sql, $desc);
        $class = strpos($result, '✅') !== false ? 'success' : (strpos($result, 'ℹ️') !== false ? 'info' : 'warning');
        echo "<div class='result $class'>$result</div>";
    }
    
    // Étape 2: Ajouter les colonnes écoles
    echo "<h3>Étape 2: Configuration des écoles</h3>";
    
    $queries = [
        "ALTER TABLE ecoles ADD COLUMN activee BOOLEAN DEFAULT FALSE" => "Ajout colonne activee",
        "ALTER TABLE ecoles ADD COLUMN date_activation DATETIME NULL" => "Ajout colonne date_activation",
        "ALTER TABLE ecoles ADD COLUMN activee_par BIGINT NULL" => "Ajout colonne activee_par"
    ];
    
    foreach ($queries as $sql => $desc) {
        $result = executeSQL($db, $sql, $desc);
        $class = strpos($result, '✅') !== false ? 'success' : (strpos($result, 'ℹ️') !== false ? 'info' : 'warning');
        echo "<div class='result $class'>$result</div>";
    }
    
    // Étape 3: Créer le rôle Super Admin
    echo "<h3>Étape 3: Création du rôle Super Admin</h3>";
    
    $sql = "INSERT IGNORE INTO roles (id, code, libelle, permissions, niveau_hierarchie) VALUES (0, 'super_admin', 'Super Administrateur', '{\"all\": true, \"multi_school\": true}', 0)";
    $result = executeSQL($db, $sql, "Création rôle Super Admin");
    $class = strpos($result, '✅') !== false ? 'success' : (strpos($result, 'ℹ️') !== false ? 'info' : 'warning');
    echo "<div class='result $class'>$result</div>";
    
    // Étape 4: Créer l'utilisateur Super Admin
    echo "<h3>Étape 4: Création de l'utilisateur Super Admin</h3>";
    
    $password_hash = '$2y$12$gkTF8Zzb4Kb8FzF5UzHzxu5.WZh4uCw5sVJNZa4F7xQrHb9EsThcW'; // SuperAdmin2024!
    $sql = "INSERT IGNORE INTO utilisateurs (id, ecole_id, nom, prenom, email, mot_de_passe_hash, role_id, is_super_admin, niveau_acces, actif, created_by) VALUES (0, NULL, 'Super', 'Administrateur', 'superadmin@naklass.cd', '$password_hash', 0, TRUE, 'super_admin', TRUE, 0)";
    
    $result = executeSQL($db, $sql, "Création utilisateur Super Admin");
    $class = strpos($result, '✅') !== false ? 'success' : (strpos($result, 'ℹ️') !== false ? 'info' : 'warning');
    echo "<div class='result $class'>$result</div>";
    
    // Étape 5: Activer l'école de démonstration
    echo "<h3>Étape 5: Activation de l'école de démonstration</h3>";
    
    $sql = "UPDATE ecoles SET activee = TRUE, date_activation = NOW(), activee_par = 0 WHERE id = 1";
    $result = executeSQL($db, $sql, "Activation école de démonstration");
    $class = strpos($result, '✅') !== false ? 'success' : (strpos($result, 'ℹ️') !== false ? 'info' : 'warning');
    echo "<div class='result $class'>$result</div>";
    
    // Étape 6: Créer la table des demandes
    echo "<h3>Étape 6: Création table des demandes d'inscription</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS demandes_inscription_ecoles (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        nom_ecole VARCHAR(255) NOT NULL,
        adresse TEXT NOT NULL,
        telephone VARCHAR(50) NOT NULL,
        email VARCHAR(255) NOT NULL,
        directeur_nom VARCHAR(255) NOT NULL,
        directeur_telephone VARCHAR(20) NOT NULL,
        directeur_email VARCHAR(255),
        regime ENUM('public', 'privé', 'conventionné') NOT NULL,
        type_enseignement SET('maternelle', 'primaire', 'secondaire', 'technique', 'professionnel', 'université') NOT NULL,
        description TEXT,
        statut ENUM('en_attente', 'approuvee', 'rejetee') DEFAULT 'en_attente',
        motif_rejet TEXT NULL,
        traitee_par BIGINT NULL,
        date_traitement DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $result = executeSQL($db, $sql, "Création table demandes_inscription_ecoles");
    $class = strpos($result, '✅') !== false ? 'success' : (strpos($result, 'ℹ️') !== false ? 'info' : 'warning');
    echo "<div class='result $class'>$result</div>";
    
    // Étape 7: Créer les index
    echo "<h3>Étape 7: Création des index</h3>";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_utilisateurs_super_admin ON utilisateurs(is_super_admin)" => "Index is_super_admin",
        "CREATE INDEX IF NOT EXISTS idx_utilisateurs_niveau_acces ON utilisateurs(niveau_acces)" => "Index niveau_acces",
        "CREATE INDEX IF NOT EXISTS idx_ecoles_activee ON ecoles(activee)" => "Index activee"
    ];
    
    foreach ($indexes as $sql => $desc) {
        $result = executeSQL($db, $sql, $desc);
        $class = strpos($result, '✅') !== false ? 'success' : (strpos($result, 'ℹ️') !== false ? 'info' : 'warning');
        echo "<div class='result $class'>$result</div>";
    }
    
    // Vérification finale
    echo "<h3>Vérification finale</h3>";
    
    $query = "SELECT COUNT(*) as count FROM utilisateurs WHERE is_super_admin = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $count = $stmt->fetch()['count'];
    
    if ($count > 0) {
        echo "<div class='result success'>✅ $count Super Administrateur(s) trouvé(s)</div>";
        
        // Afficher les détails
        $query = "SELECT id, nom, prenom, email FROM utilisateurs WHERE is_super_admin = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $super_admins = $stmt->fetchAll();
        
        foreach ($super_admins as $admin) {
            echo "<div class='result info'>👤 {$admin['prenom']} {$admin['nom']} ({$admin['email']})</div>";
        }
    } else {
        echo "<div class='result error'>❌ Aucun Super Administrateur trouvé</div>";
    }
    
    // Afficher les identifiants
    echo "<div class='credentials'>";
    echo "<h4>🔑 Identifiants de connexion :</h4>";
    echo "<p><strong>URL :</strong> <a href='superadmin/login.php'>superadmin/login.php</a></p>";
    echo "<p><strong>Email :</strong> superadmin@naklass.cd</p>";
    echo "<p><strong>Mot de passe :</strong> SuperAdmin2024!</p>";
    echo "<p style='color: #dc3545;'><strong>⚠️ Changez ce mot de passe lors de la première connexion !</strong></p>";
    echo "</div>";
    
    // Liens de navigation
    echo "<div style='text-align: center; margin-top: 30px;'>";
    echo "<a href='superadmin/login.php' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 0 10px; display: inline-block;'>🛡️ Connexion Super Admin</a>";
    echo "<a href='auth/login.php' style='background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 0 10px; display: inline-block;'>👤 Connexion Normale</a>";
    echo "</div>";
    
    echo "<div class='result success' style='margin-top: 30px; text-align: center;'>";
    echo "<h3>🎉 Installation terminée avec succès !</h3>";
    echo "<p>Le système Super Administrateur est maintenant opérationnel.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='result error'>❌ Erreur fatale: " . $e->getMessage() . "</div>";
    echo "<div class='result info'>💡 Vérifiez que la base de données 'naklass_db' existe et que vous avez les permissions nécessaires.</div>";
}
?>

</body>
</html>
