<?php
/**
 * Script d'installation du système Super Administrateur
 * Ce script configure le système pour gérer plusieurs écoles avec un Super Admin central
 */

require_once 'config/database.php';

function displayMessage($message, $type = 'info') {
    $class = '';
    switch($type) {
        case 'success': $class = 'color: green;'; break;
        case 'error': $class = 'color: red;'; break;
        case 'warning': $class = 'color: orange;'; break;
        default: $class = 'color: blue;'; break;
    }
    echo "<div style='$class margin: 10px 0; padding: 10px; border-left: 4px solid currentColor;'>$message</div>";
}

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Installation Super Administrateur - Naklass</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .step { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px; }
        .step h3 { margin-top: 0; color: #333; }
        .credentials { background: #e8f4f8; padding: 15px; border-radius: 5px; border-left: 4px solid #17a2b8; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🛡️ Installation du système Super Administrateur</h1>
        <p>Configuration du système de gestion centralisée multi-écoles</p>
    </div>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    displayMessage("✅ Connexion à la base de données réussie", 'success');
    
    // Étape 1: Exécuter le script SQL de configuration
    echo "<div class='step'>";
    echo "<h3>Étape 1: Configuration de la base de données</h3>";
    
    $sql_file = 'database/super_admin_setup.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        
        // Nettoyer et préparer les requêtes
        $sql_content = preg_replace('/^USE.*$/m', '', $sql_content);
        
        // Exécuter les requêtes une par une
        $statements = explode(';', $sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) continue;
            
            try {
                $db->exec($statement);
            } catch (PDOException $e) {
                // Ignorer certaines erreurs attendues
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    displayMessage("⚠️ Avertissement: " . $e->getMessage(), 'warning');
                }
            }
        }
        
        displayMessage("✅ Structure de base de données configurée", 'success');
    } else {
        displayMessage("❌ Fichier SQL non trouvé: $sql_file", 'error');
    }
    
    echo "</div>";
    
    // Étape 2: Vérifier la création du Super Admin
    echo "<div class='step'>";
    echo "<h3>Étape 2: Vérification du Super Administrateur</h3>";
    
    $query = "SELECT * FROM utilisateurs WHERE is_super_admin = TRUE LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $super_admin = $stmt->fetch();
    
    if ($super_admin) {
        displayMessage("✅ Super Administrateur créé avec succès", 'success');
        echo "<div class='credentials'>";
        echo "<h4>🔑 Identifiants du Super Administrateur :</h4>";
        echo "<p><strong>Email :</strong> " . htmlspecialchars($super_admin['email']) . "</p>";
        echo "<p><strong>Mot de passe :</strong> SuperAdmin2024!</p>";
        echo "<p><strong>Nom :</strong> " . htmlspecialchars($super_admin['prenom'] . ' ' . $super_admin['nom']) . "</p>";
        echo "<p class='mb-0'><em>⚠️ Veuillez changer ce mot de passe lors de la première connexion</em></p>";
        echo "</div>";
    } else {
        displayMessage("❌ Erreur lors de la création du Super Administrateur", 'error');
    }
    
    echo "</div>";
    
    // Étape 3: Vérifier les rôles et permissions
    echo "<div class='step'>";
    echo "<h3>Étape 3: Vérification des rôles</h3>";
    
    $query = "SELECT * FROM roles ORDER BY niveau_hierarchie ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $roles = $stmt->fetchAll();
    
    if (!empty($roles)) {
        displayMessage("✅ Rôles configurés correctement", 'success');
        echo "<ul>";
        foreach ($roles as $role) {
            echo "<li><strong>" . htmlspecialchars($role['libelle']) . "</strong> (" . htmlspecialchars($role['code']) . ")</li>";
        }
        echo "</ul>";
    } else {
        displayMessage("❌ Problème avec la configuration des rôles", 'error');
    }
    
    echo "</div>";
    
    // Étape 4: Vérifier les écoles
    echo "<div class='step'>";
    echo "<h3>Étape 4: Vérification des écoles</h3>";
    
    $query = "SELECT * FROM ecoles WHERE statut = 'actif'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ecoles = $stmt->fetchAll();
    
    if (!empty($ecoles)) {
        displayMessage("✅ " . count($ecoles) . " école(s) trouvée(s)", 'success');
        foreach ($ecoles as $ecole) {
            $status = $ecole['activee'] ? '🟢 Active' : '🟡 En attente';
            echo "<p>- <strong>" . htmlspecialchars($ecole['nom']) . "</strong> $status</p>";
        }
    } else {
        displayMessage("⚠️ Aucune école trouvée", 'warning');
    }
    
    echo "</div>";
    
    // Étape 5: Créer les dossiers nécessaires
    echo "<div class='step'>";
    echo "<h3>Étape 5: Création des dossiers nécessaires</h3>";
    
    $directories = [
        'superadmin',
        'superadmin/schools',
        'superadmin/users', 
        'superadmin/reports',
        'superadmin/system'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                displayMessage("✅ Dossier créé: $dir", 'success');
            } else {
                displayMessage("❌ Erreur lors de la création du dossier: $dir", 'error');
            }
        } else {
            displayMessage("ℹ️ Dossier existe déjà: $dir", 'info');
        }
    }
    
    echo "</div>";
    
    // Étape 6: Instructions finales
    echo "<div class='step'>";
    echo "<h3>Étape 6: Instructions finales</h3>";
    
    displayMessage("🎉 Installation du Super Administrateur terminée avec succès !", 'success');
    
    echo "<h4>🔍 Fonctionnalités activées :</h4>";
    echo "<ul>";
    echo "<li>✅ Super Administrateur avec accès global</li>";
    echo "<li>✅ Gestion centralisée de toutes les écoles</li>";
    echo "<li>✅ Création d'administrateurs d'école</li>";
    echo "<li>✅ Système d'activation des écoles</li>";
    echo "<li>✅ Interface de supervision multi-écoles</li>";
    echo "<li>✅ Gestion des demandes d'inscription</li>";
    echo "</ul>";
    
    echo "<h4>📋 Prochaines étapes :</h4>";
    echo "<ol>";
    echo "<li>Connectez-vous en tant que Super Administrateur</li>";
    echo "<li>Changez le mot de passe par défaut</li>";
    echo "<li>Activez les écoles existantes</li>";
    echo "<li>Créez les administrateurs pour chaque école</li>";
    echo "<li>Configurez les paramètres système</li>";
    echo "</ol>";
    
    echo "<h4>🔗 Liens utiles :</h4>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='superadmin/login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔑 Connexion Super Admin</a>";
    echo "<a href='superadmin/index.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🛡️ Interface Super Admin</a>";
    echo "<a href='auth/login.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>👤 Connexion Normale</a>";
    echo "<a href='index.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Accueil</a>";
    echo "</div>";
    
    echo "<div style='margin-top: 15px; padding: 10px; background: #e8f4f8; border-radius: 5px;'>";
    echo "<h5>📍 URLs importantes :</h5>";
    echo "<ul style='margin-bottom: 0;'>";
    echo "<li><strong>Connexion Super Admin :</strong> <code>/superadmin/login.php</code></li>";
    echo "<li><strong>Interface Super Admin :</strong> <code>/superadmin/index.php</code></li>";
    echo "<li><strong>Connexion normale :</strong> <code>/auth/login.php</code></li>";
    echo "</ul>";
    echo "</div>";
    
    echo "</div>";
    
    // Avertissements de sécurité
    echo "<div class='step' style='background: #fff3cd; border-left: 4px solid #ffc107;'>";
    echo "<h3>⚠️ Avertissements de sécurité</h3>";
    echo "<ul>";
    echo "<li>Changez immédiatement le mot de passe par défaut du Super Administrateur</li>";
    echo "<li>Supprimez ce script d'installation après utilisation</li>";
    echo "<li>Limitez l'accès au dossier superadmin/ aux IP autorisées</li>";
    echo "<li>Activez la vérification en deux étapes si possible</li>";
    echo "<li>Surveillez régulièrement les logs d'accès</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (PDOException $e) {
    displayMessage("❌ Erreur de base de données: " . $e->getMessage(), 'error');
} catch (Exception $e) {
    displayMessage("❌ Erreur générale: " . $e->getMessage(), 'error');
}

echo "</body></html>";
?>
