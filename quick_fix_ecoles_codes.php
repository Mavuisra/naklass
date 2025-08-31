<?php
/**
 * Correction rapide des codes d'école manquants
 * Résout l'erreur : SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '' for key 'code_ecole'
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Correction Rapide des Codes d'École</h2>";
    
    // 1. Identifier et corriger les écoles avec des codes vides
    $ecoles_a_corriger = [
        ['id' => 2, 'nom' => 'les genis', 'code_suggere' => 'LESGENIS_2'],
        ['id' => 3, 'nom' => 'École 3', 'code_suggere' => 'ECOLE3_3']
    ];
    
    foreach ($ecoles_a_corriger as $ecole) {
        // Vérifier si l'école existe et a un code vide
        $check_query = "SELECT id, nom_ecole, code_ecole FROM ecoles WHERE id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute(['id' => $ecole['id']]);
        $ecole_data = $check_stmt->fetch();
        
        if ($ecole_data && ($ecole_data['code_ecole'] == '' || $ecole_data['code_ecole'] === NULL)) {
            // Vérifier que le code suggéré n'existe pas déjà
            $verify_query = "SELECT COUNT(*) as total FROM ecoles WHERE code_ecole = :code";
            $verify_stmt = $db->prepare($verify_query);
            $verify_stmt->execute(['code' => $ecole['code_suggere']]);
            
            if ($verify_stmt->fetch()['total'] == 0) {
                // Mettre à jour le code
                $update_query = "UPDATE ecoles SET code_ecole = :code WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    'code' => $ecole['code_suggere'],
                    'id' => $ecole['id']
                ]);
                
                echo "<p class='text-success'>✅ École ID {$ecole['id']} : code mis à jour vers '{$ecole['code_suggere']}'</p>";
            } else {
                // Générer un code alternatif
                $code_alternatif = $ecole['code_suggere'] . '_' . time();
                $update_query = "UPDATE ecoles SET code_ecole = :code WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    'code' => $code_alternatif,
                    'id' => $ecole['id']
                ]);
                
                echo "<p class='text-success'>✅ École ID {$ecole['id']} : code mis à jour vers '{$code_alternatif}'</p>";
            }
        } else {
            echo "<p class='text-info'>ℹ️ École ID {$ecole['id']} : déjà corrigée ou n'existe pas</p>";
        }
    }
    
    // 2. Vérifier qu'il n'y a plus de codes vides
    $verify_query = "SELECT COUNT(*) as total FROM ecoles WHERE code_ecole = '' OR code_ecole IS NULL";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute();
    $count_vides = $verify_stmt->fetch()['total'];
    
    if ($count_vides == 0) {
        echo "<h3 class='text-success'>🎉 Correction terminée avec succès !</h3>";
        echo "<p class='text-success'>✅ Toutes les écoles ont maintenant un code unique.</p>";
    } else {
        echo "<h3 class='text-warning'>⚠️ Attention</h3>";
        echo "<p class='text-warning'>Il reste {$count_vides} école(s) avec un code vide.</p>";
    }
    
    // 3. Afficher le résumé final
    echo "<h3>📊 État des écoles après correction :</h3>";
    $summary_query = "SELECT id, nom_ecole, code_ecole, statut FROM ecoles ORDER BY id";
    $summary_stmt = $db->prepare($summary_query);
    $summary_stmt->execute();
    $toutes_ecoles = $summary_stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-top: 20px;'>";
    echo "<tr><th>ID</th><th>Nom de l'École</th><th>Code École</th><th>Statut</th></tr>";
    foreach ($toutes_ecoles as $ecole) {
        $status_color = $ecole['statut'] === 'actif' ? 'text-success' : 'text-muted';
        echo "<tr>";
        echo "<td>{$ecole['id']}</td>";
        echo "<td>{$ecole['nom_ecole']}</td>";
        echo "<td><strong>{$ecole['code_ecole']}</strong></td>";
        echo "<td class='{$status_color}'>{$ecole['statut']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 4. Instructions pour tester
    echo "<div style='margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;'>";
    echo "<h4>🧪 Test de la correction :</h4>";
    echo "<p>Maintenant vous pouvez :</p>";
    echo "<ul>";
    echo "<li>✅ Créer de nouvelles écoles via le formulaire corrigé</li>";
    echo "<li>✅ Approuver des écoles existantes</li>";
    echo "<li>✅ Continuer avec la création de bulletins scolaires</li>";
    echo "</ul>";
    echo "<p><strong>URL de test :</strong> <a href='superadmin/schools/create.php'>Créer une nouvelle école</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 class='text-danger'>❌ Erreur lors de la correction</h3>";
    echo "<p class='text-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
.text-success { color: #28a745; }
.text-warning { color: #ffc107; }
.text-danger { color: #dc3545; }
.text-info { color: #17a2b8; }
table { margin-top: 20px; background: white; }
th, td { padding: 12px; text-align: left; border: 1px solid #dee2e6; }
th { background-color: #e9ecef; font-weight: bold; }
tr:nth-child(even) { background-color: #f8f9fa; }
h2, h3, h4 { color: #495057; }
</style>

