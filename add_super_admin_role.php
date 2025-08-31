<?php
/**
 * Script pour ajouter le rôle super_admin à la base de données
 */
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Ajout du rôle Super Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .result { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        h1 { color: #dc3545; text-align: center; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Ajout du rôle Super Admin</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='result success'>✅ Connexion à la base de données réussie</div>";
    
    // Étape 1: Vérifier si le rôle super_admin existe déjà
    echo "<h3>Étape 1: Vérification du rôle existant</h3>";
    
    $query = "SELECT id, code, libelle, niveau_hierarchie FROM roles WHERE code = 'super_admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $existing_role = $stmt->fetch();
    
    if ($existing_role) {
        echo "<div class='result info'>ℹ️ Le rôle super_admin existe déjà :</div>";
        echo "<div class='result info'>
            ID: {$existing_role['id']}<br>
            Code: {$existing_role['code']}<br>
            Libellé: {$existing_role['libelle']}<br>
            Niveau hiérarchie: {$existing_role['niveau_hierarchie']}
        </div>";
        $super_admin_role_id = $existing_role['id'];
    } else {
        echo "<div class='result warning'>⚠️ Le rôle super_admin n'existe pas</div>";
        
        // Étape 2: Créer le rôle super_admin
        echo "<h3>Étape 2: Création du rôle super_admin</h3>";
        
        // Trouver le prochain ID disponible
        $query = "SELECT MAX(id) as max_id FROM roles";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $max_id = $stmt->fetch()['max_id'];
        $new_id = $max_id + 1;
        
        $sql = "INSERT INTO roles (id, code, libelle, description, permissions, niveau_hierarchie, statut) 
                VALUES (:id, 'super_admin', 'Super Administrateur', 'Accès complet à toutes les écoles et fonctionnalités', 
                '{\"all\": true, \"multi_school\": true, \"super_admin\": true}', 0, 'actif')";
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute(['id' => $new_id]);
            echo "<div class='result success'>✅ Rôle super_admin créé avec succès (ID: $new_id)</div>";
            $super_admin_role_id = $new_id;
        } catch (Exception $e) {
            echo "<div class='result error'>❌ Erreur lors de la création du rôle : " . $e->getMessage() . "</div>";
            
            // Essayer avec l'ID 0
            try {
                $sql = "INSERT INTO roles (id, code, libelle, description, permissions, niveau_hierarchie, statut) 
                        VALUES (0, 'super_admin', 'Super Administrateur', 'Accès complet à toutes les écoles et fonctionnalités', 
                        '{\"all\": true, \"multi_school\": true, \"super_admin\": true}', 0, 'actif')";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                echo "<div class='result success'>✅ Rôle super_admin créé avec l'ID 0</div>";
                $super_admin_role_id = 0;
            } catch (Exception $e2) {
                echo "<div class='result error'>❌ Erreur avec l'ID 0 : " . $e2->getMessage() . "</div>";
                $super_admin_role_id = null;
            }
        }
    }
    
    if ($super_admin_role_id !== null) {
        // Étape 3: Vérifier les utilisateurs super_admin existants
        echo "<h3>Étape 3: Vérification des utilisateurs super_admin</h3>";
        
        // Vérifier par rôle
        $query = "SELECT u.id, u.nom, u.prenom, u.email, u.role_id, r.code as role_code 
                  FROM utilisateurs u 
                  JOIN roles r ON u.role_id = r.id 
                  WHERE r.code = 'super_admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $super_admin_users = $stmt->fetchAll();
        
        if (count($super_admin_users) > 0) {
            echo "<div class='result success'>✅ Utilisateurs avec rôle super_admin trouvés :</div>";
            echo "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr style='background: #f0f0f0;'><th style='border: 1px solid #ddd; padding: 8px;'>ID</th><th style='border: 1px solid #ddd; padding: 8px;'>Nom</th><th style='border: 1px solid #ddd; padding: 8px;'>Prénom</th><th style='border: 1px solid #ddd; padding: 8px;'>Email</th><th style='border: 1px solid #ddd; padding: 8px;'>Rôle</th></tr>";
            
            foreach ($super_admin_users as $user) {
                echo "<tr>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['id']}</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['nom']}</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['prenom']}</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['email']}</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['role_code']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='result warning'>⚠️ Aucun utilisateur avec le rôle super_admin trouvé</div>";
        }
        
        // Vérifier par colonne is_super_admin si elle existe
        $check_column = "SHOW COLUMNS FROM utilisateurs LIKE 'is_super_admin'";
        $stmt = $db->prepare($check_column);
        $stmt->execute();
        $column_exists = $stmt->fetch();
        
        if ($column_exists) {
            $query = "SELECT id, nom, prenom, email, is_super_admin, niveau_acces 
                      FROM utilisateurs 
                      WHERE is_super_admin = TRUE";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $is_super_admin_users = $stmt->fetchAll();
            
            if (count($is_super_admin_users) > 0) {
                echo "<div class='result info'>ℹ️ Utilisateurs avec is_super_admin = TRUE :</div>";
                echo "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
                echo "<tr style='background: #f0f0f0;'><th style='border: 1px solid #ddd; padding: 8px;'>ID</th><th style='border: 1px solid #ddd; padding: 8px;'>Nom</th><th style='border: 1px solid #ddd; padding: 8px;'>Prénom</th><th style='border: 1px solid #ddd; padding: 8px;'>Email</th><th style='border: 1px solid #ddd; padding: 8px;'>Niveau</th></tr>";
                
                foreach ($is_super_admin_users as $user) {
                    echo "<tr>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['id']}</td>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['nom']}</td>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['prenom']}</td>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['email']}</td>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['niveau_acces']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
        
        echo "<div class='result success'>🎉 Configuration terminée !</div>";
        echo "<p>Le système vérifie maintenant les super admins selon cette priorité :</p>";
        echo "<ol>";
        echo "<li><strong>Rôle :</strong> Utilisateurs avec le rôle 'super_admin'</li>";
        echo "<li><strong>Colonne :</strong> Utilisateurs avec is_super_admin = TRUE</li>";
        echo "<li><strong>Fallback :</strong> Utilisateurs avec le rôle 'admin' ou niveau_hierarchie = 0</li>";
        echo "</ol>";
        
    } else {
        echo "<div class='result error'>❌ Impossible de créer le rôle super_admin</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='result error'>❌ Erreur de connexion à la base de données: " . $e->getMessage() . "</div>";
}

echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<a href='superadmin/login.php' class='btn'>🔑 Connexion Super Admin</a>";
echo "<a href='auth/login.php' class='btn'>👤 Connexion Normale</a>";
echo "</div>";

echo "</div></body></html>";
?>
