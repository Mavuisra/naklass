<?php
/**
 * Correction rapide des tables manquantes
 * Exécutez ce script pour créer automatiquement demandes_inscription_ecoles
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "🔧 Correction des tables manquantes...\n";
    
    // Créer la table demandes_inscription_ecoles si elle n'existe pas
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
    echo "✅ Table demandes_inscription_ecoles créée avec succès\n";
    
    // Vérifier si la table a des données
    $check_query = "SELECT COUNT(*) as total FROM demandes_inscription_ecoles";
    $result = $db->query($check_query);
    $count = $result->fetch()['total'];
    
    if ($count == 0) {
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
        echo "✅ Données d'exemple insérées\n";
    } else {
        echo "✅ Table contient déjà $count enregistrement(s)\n";
    }
    
    echo "\n🎉 Correction terminée avec succès !\n";
    echo "Vous pouvez maintenant accéder à :\n";
    echo "- superadmin/index.php\n";
    echo "- superadmin/schools/requests.php\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    echo "Veuillez vérifier la configuration de la base de données.\n";
}
?>
