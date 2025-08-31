<?php
/**
 * Script pour corriger le statut super_admin de l'utilisateur admin@naklass.cd
 */
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction du statut Super Admin</title>
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
        <h1>🔧 Correction du statut Super Admin</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='result success'>✅ Connexion à la base de données réussie</div>";
    
    // Étape 1: Vérifier l'état actuel
    echo "<h3>Étape 1: État actuel de l'utilisateur admin@naklass.cd</h3>";
    
    $query = "SELECT u.id, u.nom, u.prenom, u.email, u.actif, u.statut, u.role_id, 
                     r.code as role_code, r.libelle as role_libelle,
                     u.is_super_admin, u.niveau_acces
              FROM utilisateurs u 
              JOIN roles r ON u.role_id = r.id 
              WHERE u.email = 'admin@naklass.cd'";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admin_user = $stmt->fetch();
    
    if ($admin_user) {
        echo "<div class='result info'>✅ Utilisateur trouvé :</div>";
        echo "<div class='result info'>
            <strong>ID :</strong> {$admin_user['id']}<br>
            <strong>Nom :</strong> {$admin_user['prenom']} {$admin_user['nom']}<br>
            <strong>Email :</strong> {$admin_user['email']}<br>
            <strong>Rôle :</strong> {$admin_user['role_code']} ({$admin_user['role_libelle']})<br>
            <strong>Statut :</strong> " . ($admin_user['actif'] ? 'Actif' : 'Inactif') . "<br>
            <strong>is_super_admin :</strong> " . ($admin_user['is_super_admin'] ? 'OUI' : 'NON') . "<br>
            <strong>niveau_acces :</strong> " . ($admin_user['niveau_acces'] ?? 'N/A') . "
        </div>";
    } else {
        echo "<div class='result error'>❌ Utilisateur admin@naklass.cd non trouvé</div>";
        exit;
    }
    
    // Étape 2: Corriger le statut super_admin
    echo "<h3>Étape 2: Correction du statut super_admin</h3>";
    
    if (!$admin_user['is_super_admin']) {
        $update_query = "UPDATE utilisateurs 
                        SET is_super_admin = TRUE, 
                            niveau_acces = 'super_admin' 
                        WHERE email = 'admin@naklass.cd'";
        
        try {
            $stmt = $db->prepare($update_query);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                echo "<div class='result success'>✅ Statut super_admin mis à jour avec succès !</div>";
            } else {
                echo "<div class='result warning'>ℹ️ Aucune modification nécessaire</div>";
            }
        } catch (Exception $e) {
            echo "<div class='result error'>❌ Erreur lors de la mise à jour : " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='result info'>ℹ️ L'utilisateur est déjà marqué comme super_admin</div>";
    }
    
    // Étape 3: Vérifier la correction
    echo "<h3>Étape 3: Vérification de la correction</h3>";
    
    $verify_query = "SELECT u.id, u.nom, u.prenom, u.email, u.actif, u.statut, u.role_id, 
                            r.code as role_code, r.libelle as role_libelle,
                            u.is_super_admin, u.niveau_acces
                     FROM utilisateurs u 
                     JOIN roles r ON u.role_id = r.id 
                     WHERE u.email = 'admin@naklass.cd'";
    
    $stmt = $db->prepare($verify_query);
    $stmt->execute();
    $updated_user = $stmt->fetch();
    
    if ($updated_user) {
        echo "<div class='result success'>✅ Vérification réussie :</div>";
        echo "<div class='result info'>
            <strong>ID :</strong> {$updated_user['id']}<br>
            <strong>Nom :</strong> {$updated_user['prenom']} {$updated_user['nom']}<br>
            <strong>Email :</strong> {$updated_user['email']}<br>
            <strong>Rôle :</strong> {$updated_user['role_code']} ({$updated_user['role_libelle']})<br>
            <strong>Statut :</strong> " . ($updated_user['actif'] ? 'Actif' : 'Inactif') . "<br>
            <strong>is_super_admin :</strong> " . ($updated_user['is_super_admin'] ? 'OUI' : 'NON') . "<br>
            <strong>niveau_acces :</strong> " . ($updated_user['niveau_acces'] ?? 'N/A') . "
        </div>";
        
        if ($updated_user['is_super_admin'] && $updated_user['niveau_acces'] === 'super_admin') {
            echo "<div class='result success'>🎉 Correction réussie ! L'utilisateur est maintenant un Super Admin complet.</div>";
        }
    }
    
    // Étape 4: Test de connexion
    echo "<h3>Étape 4: Test de connexion</h3>";
    
    $test_query = "SELECT u.*, r.code as role_code 
                   FROM utilisateurs u 
                   JOIN roles r ON u.role_id = r.id 
                   WHERE u.email = 'admin@naklass.cd' 
                   AND u.actif = 1 
                   AND u.statut = 'actif' 
                   AND (r.code = 'super_admin' OR u.is_super_admin = TRUE)";
    
    $stmt = $db->prepare($test_query);
    $stmt->execute();
    $test_result = $stmt->fetch();
    
    if ($test_result) {
        echo "<div class='result success'>✅ Test de connexion réussi ! L'utilisateur peut maintenant se connecter.</div>";
        echo "<div class='result info'>
            <strong>Email :</strong> admin@naklass.cd<br>
            <strong>Mot de passe :</strong> password<br>
            <strong>Rôle :</strong> {$test_result['role_code']}<br>
            <strong>Super Admin :</strong> " . ($test_result['is_super_admin'] ? 'OUI' : 'NON') . "
        </div>";
    } else {
        echo "<div class='result error'>❌ Test de connexion échoué. Vérifiez la configuration.</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='result error'>❌ Erreur : " . $e->getMessage() . "</div>";
}

echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<a href='superadmin/login.php' class='btn'>🔑 Tester la connexion</a>";
echo "<a href='test_login_credentials.php' class='btn'>🔍 Vérifier les identifiants</a>";
echo "</div>";

echo "</div></body></html>";
?>
