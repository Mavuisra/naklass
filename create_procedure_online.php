<?php
/**
 * Script pour créer la procédure UpdateQRScanStats sur l'hébergement en ligne
 * Ce script évite les problèmes de permissions DEFINER
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>Création de la procédure UpdateQRScanStats</h2>\n";
    
    // Supprimer la procédure si elle existe
    $drop_sql = "DROP PROCEDURE IF EXISTS `UpdateQRScanStats`";
    $db->exec($drop_sql);
    echo "<p style='color: green;'>✅ Procédure existante supprimée</p>\n";
    
    // Créer la procédure sans DEFINER
    $create_sql = "
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
    
    $db->exec($create_sql);
    echo "<p style='color: green;'>✅ Procédure UpdateQRScanStats créée avec succès</p>\n";
    
    // Vérifier que la procédure existe
    $check_sql = "SHOW PROCEDURE STATUS WHERE Name = 'UpdateQRScanStats'";
    $stmt = $db->query($check_sql);
    $procedure = $stmt->fetch();
    
    if ($procedure) {
        echo "<p style='color: green;'>✅ Vérification : Procédure trouvée dans la base de données</p>\n";
        echo "<p><strong>Nom :</strong> " . htmlspecialchars($procedure['Name']) . "</p>\n";
        echo "<p><strong>Définie par :</strong> " . htmlspecialchars($procedure['Definer']) . "</p>\n";
        echo "<p><strong>Type :</strong> " . htmlspecialchars($procedure['Type']) . "</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Erreur : Procédure non trouvée après création</p>\n";
    }
    
    echo "<h3>Instructions d'utilisation :</h3>\n";
    echo "<p>Vous pouvez maintenant utiliser la procédure avec :</p>\n";
    echo "<pre>CALL UpdateQRScanStats('2025-01-09', 1);</pre>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Code d'erreur :</strong> " . $e->getCode() . "</p>\n";
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<h3>Solutions possibles :</h3>\n";
        echo "<ol>\n";
        echo "<li><strong>Contactez votre hébergeur</strong> pour obtenir les privilèges SUPER</li>\n";
        echo "<li><strong>Utilisez phpMyAdmin</strong> avec un compte administrateur</li>\n";
        echo "<li><strong>Exécutez le script SQL</strong> directement dans phpMyAdmin</li>\n";
        echo "</ol>\n";
    }
}
?>
