<?php
/**
 * Diagnostic de la table tuteurs
 * Vérification de la structure et des colonnes disponibles
 */

require_once '../includes/functions.php';

// Vérifier l'authentification
requireAuth();

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnostic Tuteurs - " . APP_NAME . "</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-4'>
        <div class='card'>
            <div class='card-header bg-info text-white'>
                <h2>🔍 Diagnostic de la table Tuteurs</h2>
            </div>
            <div class='card-body'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='alert alert-info'>
        <strong>🔧 Début du diagnostic...</strong><br>
        Vérification de la structure de la table 'tuteurs' et identification des colonnes disponibles.
    </div>";
    
    // 1. VÉRIFIER L'EXISTENCE DE LA TABLE
    echo "<h3 class='text-primary'>1. Vérification de l'existence de la table</h3>";
    
    $check_table = "SHOW TABLES LIKE 'tuteurs'";
    $stmt = $db->prepare($check_table);
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "<p class='success'>✅ Table 'tuteurs' existe</p>";
    } else {
        echo "<p class='error'>❌ Table 'tuteurs' n'existe pas</p>";
        echo "<div class='alert alert-warning'>
            <h6>⚠️ Action recommandée :</h6>
            <p>Créez la table 'tuteurs' en utilisant le schéma de base de données.</p>
        </div>";
        exit;
    }
    
    // 2. VÉRIFIER LA STRUCTURE DE LA TABLE
    echo "<h3 class='text-primary'>2. Structure de la table 'tuteurs'</h3>";
    
    $describe_query = "DESCRIBE tuteurs";
    $stmt = $db->prepare($describe_query);
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "<table class='table table-sm table-bordered'>";
    echo "<thead><tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr></thead>";
    echo "<tbody>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    
    // 3. VÉRIFIER LES DONNÉES
    echo "<h3 class='text-primary'>3. Données dans la table 'tuteurs'</h3>";
    
    $count_query = "SELECT COUNT(*) as total FROM tuteurs WHERE ecole_id = :ecole_id";
    $stmt = $db->prepare($count_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $total_tuteurs = $stmt->fetch()['total'];
    
    echo "<p><strong>Total des tuteurs :</strong> <span class='info'>$total_tuteurs</span></p>";
    
    if ($total_tuteurs > 0) {
        echo "<h4>Exemples de tuteurs :</h4>";
        
        $sample_query = "SELECT * FROM tuteurs WHERE ecole_id = :ecole_id LIMIT 3";
        $stmt = $db->prepare($sample_query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $sample_tuteurs = $stmt->fetchAll();
        
        echo "<table class='table table-sm table-bordered'>";
        echo "<thead><tr>";
        foreach (array_keys($sample_tuteurs[0]) as $key) {
            echo "<th>$key</th>";
        }
        echo "</tr></thead>";
        echo "<tbody>";
        foreach ($sample_tuteurs as $tuteur) {
            echo "<tr>";
            foreach ($tuteur as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table>";
    }
    
    // 4. TEST DE LA REQUÊTE CORRIGÉE
    echo "<h3 class='text-primary'>4. Test de la requête corrigée</h3>";
    
    $test_query = "SELECT t.id, t.nom, t.prenom, t.telephone, t.email, 
                          COALESCE(t.lien_parente, 'tuteur') as lien_parente
                   FROM tuteurs t
                   JOIN eleve_tuteurs et ON t.id = et.tuteur_id
                   WHERE et.eleve_id = :student_id AND t.statut = 'actif' AND et.statut = 'actif'
                   ORDER BY et.tuteur_principal DESC, t.nom, t.prenom";
    
    echo "<h4>Requête testée :</h4>";
    echo "<pre class='bg-light p-3'>" . htmlspecialchars($test_query) . "</pre>";
    
    // Vérifier s'il y a des élèves pour tester
    $count_eleves = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id AND statut = 'actif' LIMIT 1";
    $stmt = $db->prepare($count_eleves);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $total_eleves = $stmt->fetch()['total'];
    
    if ($total_eleves > 0) {
        // Récupérer un élève pour tester
        $eleve_query = "SELECT id, nom, prenom FROM eleves WHERE ecole_id = :ecole_id AND statut = 'actif' LIMIT 1";
        $eleve_stmt = $db->prepare($eleve_query);
        $eleve_stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $eleve = $eleve_stmt->fetch();
        
        if ($eleve) {
            echo "<h4>Test avec l'élève : {$eleve['prenom']} {$eleve['nom']}</h4>";
            
            try {
                $stmt = $db->prepare($test_query);
                $stmt->execute(['student_id' => $eleve['id']]);
                $tuteurs_result = $stmt->fetchAll();
                
                if (empty($tuteurs_result)) {
                    echo "<p class='text-muted'>Aucun tuteur trouvé pour cet élève</p>";
                } else {
                    echo "<p class='text-success'>✅ Requête exécutée avec succès !</p>";
                    echo "<p>Nombre de tuteurs trouvés : " . count($tuteurs_result) . "</p>";
                }
            } catch (Exception $e) {
                echo "<p class='text-danger'>❌ Erreur lors de l'exécution : " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // 5. RECOMMANDATIONS
    echo "<h3 class='text-primary'>5. Recommandations</h3>";
    
    echo "<div class='alert alert-warning'>
        <h5>🔧 Actions recommandées :</h5>
        <ol>
            <li><strong>Vérifiez la structure :</strong> Assurez-vous que toutes les colonnes nécessaires existent</li>
            <li><strong>Ajoutez les colonnes manquantes :</strong> Si 'lien_parente' n'existe pas, ajoutez-la</li>
            <li><strong>Testez la requête :</strong> Utilisez la version corrigée avec COALESCE</li>
            <li><strong>Vérifiez les données :</strong> Assurez-vous qu'il y a des tuteurs et des liaisons</li>
        </ol>
    </div>";
    
    echo "<div class='mt-4'>
        <a href='edit.php?id=1' class='btn btn-primary me-2'>✏️ Tester edit.php</a>
        <a href='index.php' class='btn btn-success me-2'>📋 Liste des élèves</a>
        <a href='../auth/dashboard.php' class='btn btn-secondary'>🏠 Tableau de bord</a>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
        <h4>❌ Erreur lors du diagnostic</h4>
        <p>" . $e->getMessage() . "</p>
    </div>";
}

echo "</div></div></div></body></html>";
?>
