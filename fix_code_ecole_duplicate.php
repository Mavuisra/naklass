<?php
/**
 * Correction du problème de duplication des codes d'école vides
 * Ce script résout l'erreur : SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '' for key 'code_ecole'
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Correction des Codes d'École Dupliqués</h2>";
    
    // 1. Identifier les écoles avec des codes vides
    $query = "SELECT id, nom_ecole, code_ecole FROM ecoles WHERE code_ecole = '' OR code_ecole IS NULL";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ecoles_vides = $stmt->fetchAll();
    
    if (empty($ecoles_vides)) {
        echo "<p class='text-success'>✅ Aucune école avec un code vide trouvée. Aucune correction nécessaire.</p>";
        exit;
    }
    
    echo "<p class='text-warning'>⚠️ Trouvé " . count($ecoles_vides) . " école(s) avec un code vide :</p>";
    echo "<ul>";
    foreach ($ecoles_vides as $ecole) {
        echo "<li>ID: {$ecole['id']} - Nom: {$ecole['nom_ecole']} - Code: '{$ecole['code_ecole']}'</li>";
    }
    echo "</ul>";
    
    // 2. Générer des codes uniques pour chaque école
    echo "<h3>📝 Génération des codes uniques...</h3>";
    
    foreach ($ecoles_vides as $ecole) {
        // Générer un code basé sur le nom de l'école
        $nom_clean = preg_replace('/[^A-Za-z0-9]/', '', $ecole['nom_ecole']);
        $nom_clean = substr($nom_clean, 0, 8); // Limiter à 8 caractères
        $code_unique = strtoupper($nom_clean) . '_' . $ecole['id'];
        
        // Vérifier que ce code n'existe pas déjà
        $check_query = "SELECT COUNT(*) as total FROM ecoles WHERE code_ecole = :code";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute(['code' => $code_unique]);
        
        $counter = 1;
        while ($check_stmt->fetch()['total'] > 0) {
            $code_unique = strtoupper($nom_clean) . '_' . $ecole['id'] . '_' . $counter;
            $check_stmt->execute(['code' => $code_unique]);
            $counter++;
        }
        
        // Mettre à jour l'école avec le nouveau code
        $update_query = "UPDATE ecoles SET code_ecole = :code WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([
            'code' => $code_unique,
            'id' => $ecole['id']
        ]);
        
        echo "<p class='text-success'>✅ École '{$ecole['nom_ecole']}' (ID: {$ecole['id']}) : code mis à jour vers '{$code_unique}'</p>";
    }
    
    // 3. Vérifier qu'il n'y a plus de codes vides
    $verify_query = "SELECT COUNT(*) as total FROM ecoles WHERE code_ecole = '' OR code_ecole IS NULL";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute();
    $count_vides = $verify_stmt->fetch()['total'];
    
    if ($count_vides == 0) {
        echo "<h3 class='text-success'>🎉 Correction terminée avec succès !</h3>";
        echo "<p class='text-success'>✅ Toutes les écoles ont maintenant un code unique.</p>";
    } else {
        echo "<h3 class='text-danger'>❌ Problème persistant</h3>";
        echo "<p class='text-danger'>Il reste {$count_vides} école(s) avec un code vide.</p>";
    }
    
    // 4. Afficher un résumé des codes générés
    echo "<h3>📊 Résumé des codes générés :</h3>";
    $summary_query = "SELECT id, nom_ecole, code_ecole FROM ecoles ORDER BY id";
    $summary_stmt = $db->prepare($summary_query);
    $summary_stmt->execute();
    $toutes_ecoles = $summary_stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Nom de l'École</th><th>Code École</th></tr>";
    foreach ($toutes_ecoles as $ecole) {
        echo "<tr>";
        echo "<td>{$ecole['id']}</td>";
        echo "<td>{$ecole['nom_ecole']}</td>";
        echo "<td><strong>{$ecole['code_ecole']}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<h3 class='text-danger'>❌ Erreur lors de la correction</h3>";
    echo "<p class='text-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='text-danger'>Trace : " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.text-success { color: #28a745; }
.text-warning { color: #ffc107; }
.text-danger { color: #dc3545; }
table { margin-top: 20px; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; }
</style>

