<?php
/**
 * Script rapide pour ajouter les colonnes Super Admin
 * À utiliser si vous voulez juste tester la fonctionnalité sans installation complète
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
            return "✅ Colonne '$column' ajoutée à la table '$table'";
        } else {
            return "ℹ️ Colonne '$column' existe déjà dans la table '$table'";
        }
    } catch (Exception $e) {
        return "❌ Erreur lors de l'ajout de la colonne '$column': " . $e->getMessage();
    }
}

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Configuration Rapide Super Admin - Naklass</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .info { background: #cce7ff; color: #004085; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🚀 Configuration Rapide Super Admin</h1>
        <p>Ajout des colonnes nécessaires pour le système Super Admin</p>
    </div>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='result success'>✅ Connexion à la base de données réussie</div>";
    
    // Ajouter les colonnes nécessaires
    $columns = [
        'is_super_admin' => 'BOOLEAN DEFAULT FALSE',
        'niveau_acces' => "ENUM('super_admin', 'school_admin', 'user') DEFAULT 'user'"
    ];
    
    echo "<h3>Ajout des colonnes à la table 'utilisateurs':</h3>";
    
    foreach ($columns as $column => $definition) {
        $result = addColumnIfNotExists($db, 'utilisateurs', $column, $definition);
        $class = strpos($result, '✅') !== false ? 'success' : (strpos($result, 'ℹ️') !== false ? 'info' : 'error');
        echo "<div class='result $class'>$result</div>";
    }
    
    echo "<h3>Ajout des colonnes à la table 'ecoles':</h3>";
    
    $ecoles_columns = [
        'activee' => 'BOOLEAN DEFAULT FALSE',
        'date_activation' => 'DATETIME NULL',
        'activee_par' => 'BIGINT NULL'
    ];
    
    foreach ($ecoles_columns as $column => $definition) {
        $result = addColumnIfNotExists($db, 'ecoles', $column, $definition);
        $class = strpos($result, '✅') !== false ? 'success' : (strpos($result, 'ℹ️') !== false ? 'info' : 'error');
        echo "<div class='result $class'>$result</div>";
    }
    
    // Vérifier s'il y a déjà un Super Admin
    $query = "SELECT COUNT(*) as count FROM utilisateurs WHERE is_super_admin = TRUE";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $super_admin_count = $stmt->fetch()['count'];
    
    if ($super_admin_count == 0) {
        echo "<h3>Création du Super Admin par défaut:</h3>";
        
        // Vérifier si le rôle super_admin existe
        $query = "SELECT id FROM roles WHERE code = 'super_admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $super_role = $stmt->fetch();
        
        if (!$super_role) {
            // Créer le rôle super_admin
            $query = "INSERT INTO roles (id, code, libelle, permissions, niveau_hierarchie) VALUES (0, 'super_admin', 'Super Administrateur', '{\"all\": true, \"multi_school\": true}', 0)";
            $db->exec($query);
            echo "<div class='result success'>✅ Rôle Super Administrateur créé</div>";
            $super_role_id = 0;
        } else {
            $super_role_id = $super_role['id'];
            echo "<div class='result info'>ℹ️ Rôle Super Administrateur existe déjà</div>";
        }
        
        // Créer le Super Admin
        $password_hash = password_hash('SuperAdmin2024!', PASSWORD_BCRYPT, ['cost' => 12]);
        $query = "INSERT INTO utilisateurs (id, ecole_id, nom, prenom, email, mot_de_passe_hash, role_id, is_super_admin, niveau_acces, created_by) 
                  VALUES (0, NULL, 'Super', 'Administrateur', 'superadmin@naklass.cd', :password, :role_id, TRUE, 'super_admin', 0)";
        
        try {
            $stmt = $db->prepare($query);
            $stmt->execute([
                'password' => $password_hash,
                'role_id' => $super_role_id
            ]);
            echo "<div class='result success'>✅ Super Administrateur créé avec succès !</div>";
            
            echo "<div class='result info'>
                <strong>Identifiants :</strong><br>
                Email: superadmin@naklass.cd<br>
                Mot de passe: SuperAdmin2024!
            </div>";
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "<div class='result info'>ℹ️ Super Administrateur existe déjà</div>";
            } else {
                echo "<div class='result error'>❌ Erreur: " . $e->getMessage() . "</div>";
            }
        }
    } else {
        echo "<div class='result info'>ℹ️ Super Administrateur existe déjà ($super_admin_count compte(s))</div>";
    }
    
    echo "<hr>";
    echo "<div class='result success'>";
    echo "<h3>🎉 Configuration terminée !</h3>";
    echo "<p>Vous pouvez maintenant :</p>";
    echo "<ul>";
    echo "<li>Vous connecter via <a href='superadmin/login.php'>superadmin/login.php</a></li>";
    echo "<li>Utiliser les identifiants Super Admin</li>";
    echo "<li>Accéder à l'interface de gestion multi-écoles</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 20px;'>";
    echo "<a href='superadmin/login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔑 Connexion Super Admin</a>";
    echo "<a href='auth/login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>👤 Connexion Normale</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='result error'>❌ Erreur de connexion à la base de données: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
