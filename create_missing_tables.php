<?php
/**
 * Script pour créer les tables manquantes liées aux QR codes
 * À exécuter sur votre hébergement en ligne
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>Création des tables manquantes pour les QR codes</h2>\n";
    
    // 1. Créer la table qr_scan_logs
    echo "<h3>1. Création de la table qr_scan_logs</h3>\n";
    
    $create_qr_scan_logs = "
    CREATE TABLE IF NOT EXISTS `qr_scan_logs` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `student_id` BIGINT NOT NULL,
        `scan_type` ENUM('identification', 'presence', 'access', 'library', 'finance', 'other') NOT NULL,
        `scan_data` TEXT NULL,
        `ip_address` VARCHAR(45) NULL,
        `user_agent` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX `idx_student_id` (`student_id`),
        INDEX `idx_scan_type` (`scan_type`),
        INDEX `idx_created_at` (`created_at`),
        
        FOREIGN KEY (`student_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($create_qr_scan_logs);
    echo "<p style='color: green;'>✅ Table qr_scan_logs créée</p>\n";
    
    // 2. Créer la table qr_scan_stats
    echo "<h3>2. Création de la table qr_scan_stats</h3>\n";
    
    $create_qr_scan_stats = "
    CREATE TABLE IF NOT EXISTS `qr_scan_stats` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `date_scan` DATE NOT NULL,
        `school_id` BIGINT NOT NULL,
        `total_scans` INT DEFAULT 0,
        `unique_students` INT DEFAULT 0,
        `scans_by_type` JSON NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        UNIQUE KEY `unique_date_school` (`date_scan`, `school_id`),
        INDEX `idx_date_scan` (`date_scan`),
        INDEX `idx_school_id` (`school_id`),
        
        FOREIGN KEY (`school_id`) REFERENCES `ecoles` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($create_qr_scan_stats);
    echo "<p style='color: green;'>✅ Table qr_scan_stats créée</p>\n";
    
    // 3. Créer la table qr_codes (si elle n'existe pas)
    echo "<h3>3. Création de la table qr_codes</h3>\n";
    
    $create_qr_codes = "
    CREATE TABLE IF NOT EXISTS `qr_codes` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `student_id` BIGINT NOT NULL,
        `qr_data` TEXT NOT NULL,
        `qr_type` ENUM('student_card', 'attendance', 'access', 'library', 'finance') DEFAULT 'student_card',
        `is_active` BOOLEAN DEFAULT TRUE,
        `expires_at` TIMESTAMP NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        UNIQUE KEY `unique_student_qr` (`student_id`, `qr_type`),
        INDEX `idx_student_id` (`student_id`),
        INDEX `idx_qr_type` (`qr_type`),
        INDEX `idx_is_active` (`is_active`),
        
        FOREIGN KEY (`student_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($create_qr_codes);
    echo "<p style='color: green;'>✅ Table qr_codes créée</p>\n";
    
    // 4. Vérifier que toutes les tables existent
    echo "<h3>4. Vérification des tables créées</h3>\n";
    
    $tables_to_check = ['qr_scan_logs', 'qr_scan_stats', 'qr_codes'];
    
    foreach ($tables_to_check as $table) {
        $check_sql = "SHOW TABLES LIKE ?";
        $stmt = $db->prepare($check_sql);
        $stmt->execute([$table]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "<p style='color: green;'>✅ Table $table existe</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Table $table manquante</p>\n";
        }
    }
    
    // 5. Maintenant, créer la procédure sans DEFINER
    echo "<h3>5. Création de la procédure UpdateQRScanStats</h3>\n";
    
    // Supprimer la procédure existante
    $drop_sql = "DROP PROCEDURE IF EXISTS `UpdateQRScanStats`";
    $db->exec($drop_sql);
    echo "<p style='color: green;'>✅ Procédure existante supprimée</p>\n";
    
    // Créer la procédure sans DEFINER
    $create_procedure = "
    CREATE PROCEDURE `UpdateQRScanStats` (IN `p_date` DATE, IN `p_school_id` INT)
    BEGIN
        DECLARE v_total_scans INT DEFAULT 0;
        DECLARE v_unique_students INT DEFAULT 0;
        DECLARE v_scans_by_type JSON;
        
        SELECT 
            COUNT(*),
            COUNT(DISTINCT student_id),
            JSON_OBJECT(
                'identification', SUM(CASE WHEN scan_type = 'identification' THEN 1 ELSE 0 END),
                'presence', SUM(CASE WHEN scan_type = 'presence' THEN 1 ELSE 0 END),
                'access', SUM(CASE WHEN scan_type = 'access' THEN 1 ELSE 0 END),
                'library', SUM(CASE WHEN scan_type = 'library' THEN 1 ELSE 0 END),
                'finance', SUM(CASE WHEN scan_type = 'finance' THEN 1 ELSE 0 END),
                'other', SUM(CASE WHEN scan_type = 'other' THEN 1 ELSE 0 END)
            )
        INTO v_total_scans, v_unique_students, v_scans_by_type
        FROM qr_scan_logs qsl
        JOIN eleves el ON qsl.student_id = el.id
        WHERE DATE(qsl.created_at) = p_date 
        AND el.ecole_id = p_school_id;
        
        -- Insérer ou mettre à jour les statistiques
        INSERT INTO qr_scan_stats (date_scan, school_id, total_scans, unique_students, scans_by_type, created_at, updated_at)
        VALUES (p_date, p_school_id, v_total_scans, v_unique_students, v_scans_by_type, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            total_scans = v_total_scans,
            unique_students = v_unique_students,
            scans_by_type = v_scans_by_type,
            updated_at = NOW();
            
    END";
    
    $db->exec($create_procedure);
    echo "<p style='color: green;'>✅ Procédure UpdateQRScanStats créée</p>\n";
    
    // 6. Test de la procédure
    echo "<h3>6. Test de la procédure</h3>\n";
    
    try {
        $test_date = date('Y-m-d');
        $test_school_id = 1;
        
        $call_sql = "CALL UpdateQRScanStats(?, ?)";
        $stmt = $db->prepare($call_sql);
        $stmt->execute([$test_date, $test_school_id]);
        echo "<p style='color: green;'>✅ Test de la procédure réussi</p>\n";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ Test de la procédure : " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    echo "<h3>✅ Installation terminée !</h3>\n";
    echo "<p>Toutes les tables et la procédure sont maintenant créées sur votre hébergement en ligne.</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Code d'erreur :</strong> " . $e->getCode() . "</p>\n";
}
?>



