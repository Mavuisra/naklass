<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();

echo "<h1>Correction des Inscriptions</h1>";

try {
    // 1. Vérifier la structure de la table
    echo "<h2>1. Vérification de la structure</h2>";
    $columns_query = "SHOW COLUMNS FROM inscriptions";
    $stmt = $db->prepare($columns_query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $has_statut = in_array('statut', $columns);
    $has_statut_inscription = in_array('statut_inscription', $columns);
    $has_statut_record = in_array('statut_record', $columns);
    
    echo "<p>Colonnes de statut trouvées:</p>";
    echo "<ul>";
    echo "<li>statut: " . ($has_statut ? "✅" : "❌") . "</li>";
    echo "<li>statut_inscription: " . ($has_statut_inscription ? "✅" : "❌") . "</li>";
    echo "<li>statut_record: " . ($has_statut_record ? "✅" : "❌") . "</li>";
    echo "</ul>";
    
    // 2. Compter les inscriptions par statut
    echo "<h2>2. État actuel des inscriptions</h2>";
    
    if ($has_statut) {
        $count_query = "SELECT statut, COUNT(*) as count FROM inscriptions GROUP BY statut";
        $stmt = $db->prepare($count_query);
        $stmt->execute();
        $counts = $stmt->fetchAll();
        
        echo "<h3>Par statut principal:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Statut</th><th>Nombre</th></tr>";
        foreach ($counts as $count) {
            echo "<tr><td>{$count['statut']}</td><td>{$count['count']}</td></tr>";
        }
        echo "</table>";
    }
    
    if ($has_statut_record) {
        $count_query = "SELECT statut_record, COUNT(*) as count FROM inscriptions GROUP BY statut_record";
        $stmt = $db->prepare($count_query);
        $stmt->execute();
        $counts = $stmt->fetchAll();
        
        echo "<h3>Par statut record:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Statut Record</th><th>Nombre</th></tr>";
        foreach ($counts as $count) {
            echo "<tr><td>{$count['statut_record']}</td><td>{$count['count']}</td></tr>";
        }
        echo "</table>";
    }
    
    // 3. Actions de correction
    echo "<h2>3. Actions de correction</h2>";
    
    if (isset($_POST['fix_statuts'])) {
        $affected_rows = 0;
        
        if ($has_statut) {
            // Mettre à jour les statuts vers 'validée'
            $update_query = "UPDATE inscriptions SET statut = 'validée' WHERE statut IN ('en_cours', 'en_attente')";
            $stmt = $db->prepare($update_query);
            $stmt->execute();
            $affected_rows += $stmt->rowCount();
        }
        
        if ($has_statut_record) {
            // Mettre à jour les statuts record vers 'actif'
            $update_query = "UPDATE inscriptions SET statut_record = 'actif' WHERE statut_record IS NULL OR statut_record = 'archivé'";
            $stmt = $db->prepare($update_query);
            $stmt->execute();
            $affected_rows += $stmt->rowCount();
        }
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0;'>";
        echo "<strong>✅ Correction effectuée!</strong><br>";
        echo "Nombre de lignes mises à jour: $affected_rows";
        echo "</div>";
        
        // Recharger la page pour voir les changements
        echo "<script>setTimeout(() => window.location.reload(), 2000);</script>";
    }
    
    // 4. Ajouter des élèves de test si nécessaire
    if (isset($_POST['add_test_students'])) {
        $classe_id = $_POST['classe_id'];
        
        // Vérifier d'abord s'il y a des élèves dans l'école
        $eleves_query = "SELECT COUNT(*) as count FROM eleves WHERE ecole_id = :ecole_id";
        $stmt = $db->prepare($eleves_query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $eleves_count = $stmt->fetch()['count'];
        
        if ($eleves_count == 0) {
            // Créer quelques élèves de test
            $test_students = [
                ['nom' => 'Dupont', 'prenom' => 'Jean', 'sexe' => 'M'],
                ['nom' => 'Martin', 'prenom' => 'Marie', 'sexe' => 'F'],
                ['nom' => 'Bernard', 'prenom' => 'Pierre', 'sexe' => 'M'],
            ];
            
            foreach ($test_students as $student) {
                // Créer l'élève
                $matricule = 'TEST' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                $insert_eleve = "INSERT INTO eleves (ecole_id, nom, prenom, sexe, matricule, date_naissance, created_by) 
                                VALUES (:ecole_id, :nom, :prenom, :sexe, :matricule, '2010-01-01', :created_by)";
                $stmt = $db->prepare($insert_eleve);
                $stmt->execute([
                    'ecole_id' => $_SESSION['ecole_id'],
                    'nom' => $student['nom'],
                    'prenom' => $student['prenom'],
                    'sexe' => $student['sexe'],
                    'matricule' => $matricule,
                    'created_by' => $_SESSION['user_id']
                ]);
                
                $eleve_id = $db->lastInsertId();
                
                // L'inscrire dans la classe
                $insert_inscription = "INSERT INTO inscriptions (eleve_id, classe_id, date_inscription, statut, annee_scolaire, created_by) 
                                      VALUES (:eleve_id, :classe_id, CURDATE(), 'validée', '2024-2025', :created_by)";
                $stmt = $db->prepare($insert_inscription);
                $stmt->execute([
                    'eleve_id' => $eleve_id,
                    'classe_id' => $classe_id,
                    'created_by' => $_SESSION['user_id']
                ]);
            }
            
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0;'>";
            echo "<strong>✅ Élèves de test créés!</strong><br>";
            echo "3 élèves de test ont été créés et inscrits dans la classe.";
            echo "</div>";
        }
    }
    
    // Formulaire de correction
    echo "<form method='POST'>";
    echo "<button type='submit' name='fix_statuts' class='btn btn-warning' style='background: #ffc107; border: none; padding: 10px 20px; margin: 5px;'>";
    echo "🔧 Corriger les statuts d'inscription";
    echo "</button>";
    echo "</form>";
    
    // Formulaire pour ajouter des élèves de test
    if (isset($_GET['class_id'])) {
        echo "<form method='POST'>";
        echo "<input type='hidden' name='classe_id' value='" . htmlspecialchars($_GET['class_id']) . "'>";
        echo "<button type='submit' name='add_test_students' class='btn btn-info' style='background: #17a2b8; border: none; padding: 10px 20px; margin: 5px; color: white;'>";
        echo "👥 Ajouter des élèves de test";
        echo "</button>";
        echo "</form>";
    }
    
    // 5. Statistiques finales
    echo "<h2>4. Vérification finale</h2>";
    $final_query = "SELECT 
                        c.nom as classe_nom,
                        COUNT(i.id) as total_inscriptions,
                        SUM(CASE WHEN i.statut = 'validée' THEN 1 ELSE 0 END) as inscriptions_validees,
                        COUNT(e.id) as eleves_actifs
                    FROM classes c
                    LEFT JOIN inscriptions i ON c.id = i.classe_id
                    LEFT JOIN eleves e ON i.eleve_id = e.id AND e.statut = 'actif'
                    WHERE c.ecole_id = :ecole_id
                    GROUP BY c.id, c.nom
                    ORDER BY c.nom_classe";
    
    $stmt = $db->prepare($final_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $stats = $stmt->fetchAll();
    
    echo "<table border='1'>";
    echo "<tr><th>Classe</th><th>Total Inscriptions</th><th>Inscriptions Validées</th><th>Élèves Actifs</th></tr>";
    foreach ($stats as $stat) {
        echo "<tr>";
        echo "<td>{$stat['classe_nom']}</td>";
        echo "<td>{$stat['total_inscriptions']}</td>";
        echo "<td>{$stat['inscriptions_validees']}</td>";
        echo "<td>{$stat['eleves_actifs']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Erreur: " . htmlspecialchars($e->getMessage()) . "</h2>";
}

echo "<hr>";
echo "<a href='index.php'>🏠 Retour aux classes</a> | ";
if (isset($_GET['class_id'])) {
    echo "<a href='view.php?id=" . htmlspecialchars($_GET['class_id']) . "'>👁️ Voir la classe</a> | ";
}
echo "<a href='debug_students.php?class_id=" . ($_GET['class_id'] ?? 1) . "'>🔍 Debug détaillé</a>";
?>