<?php
require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();
    $stmt = $db->query('DESCRIBE types_frais');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colonnes de la table types_frais:\n";
    foreach($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
?>


