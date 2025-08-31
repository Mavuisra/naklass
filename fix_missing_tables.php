<?php
/**
 * Script pour créer les tables manquantes du système
 * Crée automatiquement demandes_inscription_ecoles si elle n'existe pas
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction Tables Manquantes - Naklass</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .fix-section { margin: 20px 0; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        .fix-success { background-color: #d4edda; border-color: #c3e6cb; }
        .fix-error { background-color: #f8d7da; border-color: #f5c6cb; }
        .fix-info { background-color: #d1ecf1; border-color: #bee5eb; }
        .fix-warning { background-color: #fff3cd; border-color: #ffeaa7; }
    </style>
</head>
<body class='container mt-4'>
    <h1>🔧 Correction des Tables Manquantes</h1>
    <p class='lead'>Création automatique des tables nécessaires au système</p>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='fix-section fix-success'>
            <h4>✅ Connexion à la base de données réussie</h4>
          </div>";
    
    // Vérifier et créer la table demandes_inscription_ecoles
    echo "<div class='fix-section fix-info'>
            <h4>🔍 Vérification de la table demandes_inscription_ecoles</h4>";
    
    try {
        // Vérifier si la table existe
        $check_query = "SHOW TABLES LIKE 'demandes_inscription_ecoles'";
        $result = $db->query($check_query);
        
        if ($result && $result->rowCount() > 0) {
            echo "<p class='text-success'>✅ Table <code>demandes_inscription_ecoles</code> existe déjà</p>";
        } else {
            echo "<p class='text-warning'>⚠️ Table <code>demandes_inscription_ecoles</code> manquante - Création en cours...</p>";
            
            // Créer la table
            $create_table_sql = "
            CREATE TABLE IF NOT EXISTS `demandes_inscription_ecoles` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `nom_ecole` varchar(255) NOT NULL COMMENT 'Nom de l''école demandée',
              `directeur_nom` varchar(255) DEFAULT NULL COMMENT 'Nom du directeur',
              `email` varchar(255) NOT NULL COMMENT 'Email de contact',
              `telephone` varchar(50) DEFAULT NULL COMMENT 'Téléphone de contact',
              `adresse` text DEFAULT NULL COMMENT 'Adresse de l''école',
              `statut` enum('en_attente','approuvee','rejetee') NOT NULL DEFAULT 'en_attente' COMMENT 'Statut de la demande',
              `notes` text DEFAULT NULL COMMENT 'Notes du super admin',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création de la demande',
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date de mise à jour',
              `processed_by` int(11) DEFAULT NULL COMMENT 'ID du super admin qui a traité la demande',
              `processed_at` timestamp NULL DEFAULT NULL COMMENT 'Date de traitement',
              PRIMARY KEY (`id`),
              KEY `idx_statut` (`statut`),
              KEY `idx_created_at` (`created_at`),
              KEY `idx_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Demandes d''inscription d''écoles en attente de validation'
            ";
            
            $db->exec($create_table_sql);
            echo "<p class='text-success'>✅ Table <code>demandes_inscription_ecoles</code> créée avec succès</p>";
            
            // Insérer des données d'exemple
            $insert_data_sql = "
            INSERT INTO `demandes_inscription_ecoles` 
            (`nom_ecole`, `directeur_nom`, `email`, `telephone`, `adresse`, `statut`, `notes`) 
            VALUES 
            ('École Sainte-Marie', 'Jean Dupont', 'contact@saintemarie.edu', '+243 123 456 789', '123 Avenue de la Paix, Kinshasa', 'en_attente', 'École privée catholique'),
            ('Institut Technique Moderne', 'Marie Martin', 'info@itm.edu', '+243 987 654 321', '456 Boulevard du Progrès, Lubumbashi', 'en_attente', 'Institut technique privé'),
            ('Lycée Excellence', 'Pierre Durand', 'admin@excellence.edu', '+243 555 123 456', '789 Rue de l''Avenir, Goma', 'en_attente', 'Lycée privée d''excellence')
            ";
            
            $db->exec($insert_data_sql);
            echo "<p class='text-success'>✅ Données d'exemple insérées</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='text-danger'>❌ Erreur lors de la création de la table : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
    // Vérifier les autres tables importantes
    echo "<div class='fix-section fix-info'>
            <h4>🔍 Vérification des autres tables importantes</h4>";
    
    $important_tables = [
        'ecoles' => 'Table principale des écoles',
        'utilisateurs' => 'Table des utilisateurs',
        'roles' => 'Table des rôles utilisateurs',
        'super_admin_notifications' => 'Table des notifications super admin',
        'school_validation_history' => 'Table de l\'historique de validation'
    ];
    
    foreach ($important_tables as $table => $description) {
        try {
            $check_query = "SHOW TABLES LIKE '$table'";
            $result = $db->query($check_query);
            
            if ($result && $result->rowCount() > 0) {
                echo "<p class='text-success'>✅ Table <code>$table</code> : $description</p>";
            } else {
                echo "<p class='text-warning'>⚠️ Table <code>$table</code> manquante : $description</p>";
            }
        } catch (Exception $e) {
            echo "<p class='text-danger'>❌ Erreur lors de la vérification de <code>$table</code> : " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "</div>";
    
    // Test de fonctionnement
    echo "<div class='fix-section fix-info'>
            <h4>🧪 Test de fonctionnement</h4>";
    
    try {
        // Tester la récupération des demandes
        $test_query = "SELECT COUNT(*) as total FROM demandes_inscription_ecoles WHERE statut = 'en_attente'";
        $result = $db->query($test_query);
        
        if ($result) {
            $count = $result->fetch()['total'];
            echo "<p class='text-success'>✅ Test réussi : $count demande(s) en attente trouvée(s)</p>";
        } else {
            echo "<p class='text-warning'>⚠️ Test partiel : impossible de compter les demandes</p>";
        }
        
        // Tester la récupération des écoles
        $test_query = "SELECT COUNT(*) as total FROM ecoles WHERE validation_status = 'approved'";
        $result = $db->query($test_query);
        
        if ($result) {
            $count = $result->fetch()['total'];
            echo "<p class='text-success'>✅ Test réussi : $count école(s) approuvée(s) trouvée(s)</p>";
        } else {
            echo "<p class='text-warning'>⚠️ Test partiel : impossible de compter les écoles</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='text-danger'>❌ Erreur lors des tests : " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
    // Actions recommandées
    echo "<div class='fix-section fix-info'>
            <h4>📝 Actions recommandées</h4>
            <ul>
                <li><a href='superadmin/index.php' class='btn btn-sm btn-primary'>👑 Tester le super admin</a></li>
                <li><a href='superadmin/schools/requests.php' class='btn btn-sm btn-info'>📋 Voir les demandes</a></li>
                <li><a href='create_school.php' class='btn btn-sm btn-success'>🏫 Créer une école</a></li>
                <li><a href='test_create_school.php' class='btn btn-sm btn-warning'>🧪 Tests complets</a></li>
            </ul>
          </div>";
    
} catch (Exception $e) {
    echo "<div class='fix-section fix-error'>
            <h4>❌ Erreur critique</h4>
            <p><strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Veuillez vérifier la configuration de la base de données.</p>
          </div>";
}

echo "</body></html>";
?>
