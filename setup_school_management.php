<?php
/**
 * Script de mise en place du système de gestion multi-écoles
 * Ce script applique les modifications nécessaires pour implémenter
 * la configuration obligatoire des nouvelles écoles
 */

require_once 'config/database.php';

// Fonction pour afficher les messages
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

// Début du script
echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Configuration du système multi-écoles - Naklass</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .step { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px; }
        .step h3 { margin-top: 0; color: #333; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🏫 Configuration du système multi-écoles Naklass</h1>
        <p>Ce script configure votre système pour gérer plusieurs établissements scolaires.</p>
    </div>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    displayMessage("✅ Connexion à la base de données réussie", 'success');
    
    // Étape 1: Vérifier la structure actuelle de la table ecoles
    echo "<div class='step'>";
    echo "<h3>Étape 1: Vérification de la structure de la table ecoles</h3>";
    
    $query = "DESCRIBE ecoles";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    $existing_columns = array_column($columns, 'Field');
    $required_columns = [
        'configuration_complete',
        'date_configuration',
        'sigle',
        'site_web',
        'fax',
        'bp',
        'regime',
        'type_enseignement',
        'langue_enseignement',
        'devise_principale',
        'fuseau_horaire',
        'numero_autorisation',
        'date_autorisation',
        'directeur_telephone',
        'directeur_email',
        'description_etablissement'
    ];
    
    $missing_columns = array_diff($required_columns, $existing_columns);
    
    if (empty($missing_columns)) {
        displayMessage("✅ Toutes les colonnes nécessaires sont présentes", 'success');
    } else {
        displayMessage("⚠️ Colonnes manquantes détectées: " . implode(', ', $missing_columns), 'warning');
        
        // Étape 2: Ajouter les colonnes manquantes
        echo "</div><div class='step'>";
        echo "<h3>Étape 2: Ajout des colonnes manquantes</h3>";
        
        // Lire et exécuter le script SQL
        $sql_file = 'database/school_setup_enhancement.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            
            // Nettoyer le contenu et diviser en blocs de requêtes
            $sql_content = preg_replace('/^USE.*$/m', '', $sql_content); // Supprimer les USE
            $sql_content = preg_replace('/^--.*$/m', '', $sql_content);  // Supprimer les commentaires
            
            // Diviser par des blocs de requêtes preparées
            $blocks = explode('DEALLOCATE PREPARE stmt;', $sql_content);
            
            foreach ($blocks as $block) {
                $block = trim($block);
                if (empty($block)) continue;
                
                // Diviser le bloc en requêtes individuelles
                $queries = explode(';', $block);
                
                foreach ($queries as $query) {
                    $query = trim($query);
                    if (empty($query) || strlen($query) < 10) continue;
                    
                    try {
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        
                        // Afficher seulement les requêtes importantes
                        if (strpos($query, 'ALTER TABLE') !== false) {
                            $match = [];
                            if (preg_match('/ADD COLUMN (\w+)/', $query, $match)) {
                                displayMessage("✅ Colonne ajoutée: " . $match[1], 'success');
                            } else {
                                displayMessage("✅ Structure mise à jour", 'success');
                            }
                        }
                    } catch (PDOException $e) {
                        // Ignorer les erreurs pour colonnes déjà existantes
                        if (strpos($e->getMessage(), 'Duplicate column name') !== false || 
                            strpos($e->getMessage(), 'already exists') !== false) {
                            displayMessage("ℹ️ Élément déjà existant (ignoré)", 'info');
                        } else {
                            displayMessage("❌ Erreur: " . $e->getMessage(), 'error');
                        }
                    }
                }
            }
            
            // Exécuter les requêtes d'index séparément
            try {
                $db->exec("CREATE INDEX IF NOT EXISTS idx_ecoles_config_complete ON ecoles(configuration_complete)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_ecoles_sigle ON ecoles(sigle)");
                displayMessage("✅ Index créés", 'success');
            } catch (PDOException $e) {
                displayMessage("ℹ️ Index déjà existants", 'info');
            }
            
        } else {
            displayMessage("❌ Fichier SQL non trouvé: $sql_file", 'error');
        }
    }
    
    // Étape 3: Vérifier la configuration des écoles existantes
    echo "</div><div class='step'>";
    echo "<h3>Étape 3: Vérification des écoles existantes</h3>";
    
    $query = "SELECT id, nom, configuration_complete FROM ecoles";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ecoles = $stmt->fetchAll();
    
    if (empty($ecoles)) {
        displayMessage("⚠️ Aucune école trouvée dans la base de données", 'warning');
    } else {
        foreach ($ecoles as $ecole) {
            $status = $ecole['configuration_complete'] ? '✅ Configurée' : '⚠️ Configuration incomplète';
            displayMessage("École: {$ecole['nom']} (ID: {$ecole['id']}) - $status", 
                         $ecole['configuration_complete'] ? 'success' : 'warning');
        }
    }
    
    // Étape 4: Vérifier les utilisateurs admin
    echo "</div><div class='step'>";
    echo "<h3>Étape 4: Vérification des utilisateurs administrateurs</h3>";
    
    $query = "SELECT u.id, u.nom, u.prenom, u.email, u.ecole_id, r.code as role_code, e.nom_ecole as nom_ecole
              FROM utilisateurs u 
              JOIN roles r ON u.role_id = r.id 
              LEFT JOIN ecoles e ON u.ecole_id = e.id 
              WHERE r.code = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admins = $stmt->fetchAll();
    
    if (empty($admins)) {
        displayMessage("❌ Aucun administrateur trouvé", 'error');
    } else {
        foreach ($admins as $admin) {
            displayMessage("Admin: {$admin['prenom']} {$admin['nom']} ({$admin['email']}) - École: {$admin['nom_ecole']}", 'info');
        }
    }
    
    // Étape 5: Instructions finales
    echo "</div><div class='step'>";
    echo "<h3>Étape 5: Instructions finales</h3>";
    
    displayMessage("🎉 Configuration terminée avec succès !", 'success');
    echo "<p><strong>Prochaines étapes :</strong></p>";
    echo "<ol>";
    echo "<li>Connectez-vous en tant qu'administrateur</li>";
    echo "<li>Si votre école n'est pas encore configurée, vous serez automatiquement redirigé vers la page de configuration</li>";
    echo "<li>Remplissez toutes les informations de votre établissement</li>";
    echo "<li>Une fois configurée, tous les utilisateurs de votre école pourront accéder au système</li>";
    echo "</ol>";
    
    echo "<p><strong>Fonctionnalités ajoutées :</strong></p>";
    echo "<ul>";
    echo "<li>✅ Configuration obligatoire pour les nouvelles écoles</li>";
    echo "<li>✅ Gestion des informations complètes de l'établissement</li>";
    echo "<li>✅ Vérification automatique avant accès au système</li>";
    echo "<li>✅ Interface d'attente pour les utilisateurs non-admin</li>";
    echo "<li>✅ Support multi-établissements</li>";
    echo "</ul>";
    
    echo "<p style='margin-top: 20px;'>";
    echo "<a href='auth/login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔑 Accéder à la connexion</a>";
    echo "</p>";
    
    echo "</div>";
    
} catch (PDOException $e) {
    displayMessage("❌ Erreur de base de données: " . $e->getMessage(), 'error');
} catch (Exception $e) {
    displayMessage("❌ Erreur générale: " . $e->getMessage(), 'error');
}

echo "</body></html>";
?>
