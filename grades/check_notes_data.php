<?php
/**
 * Vérification des données de notes et évaluations
 * Pour diagnostiquer pourquoi les moyennes sont NULL
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'professeur']);

$database = new Database();
$db = $database->getConnection();

echo "<h2>🔍 Vérification des données de notes et évaluations</h2>";

// 1. Vérifier les évaluations
echo "<h3>1. Évaluations dans la base de données</h3>";
try {
    $evaluations_query = "SELECT COUNT(*) as count FROM evaluations";
    $stmt = $db->prepare($evaluations_query);
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo "<p><strong>Nombre total d'évaluations :</strong> " . $result['count'] . "</p>";
    
    if ($result['count'] > 0) {
        $evaluations_detail = "SELECT e.*, cc.classe_id, c.nom_cours, cl.nom_classe 
                              FROM evaluations e
                              JOIN classe_cours cc ON e.classe_cours_id = cc.id
                              JOIN cours c ON cc.cours_id = c.id
                              JOIN classes cl ON cc.classe_id = cl.id
                              WHERE cl.ecole_id = :ecole_id
                              LIMIT 10";
        $stmt = $db->prepare($evaluations_detail);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $evaluations = $stmt->fetchAll();
        
        echo "<table class='table table-sm table-bordered'>";
        echo "<thead><tr><th>ID</th><th>Nom</th><th>Type</th><th>Période</th><th>Date</th><th>Classe</th><th>Cours</th></tr></thead>";
        echo "<tbody>";
        foreach ($evaluations as $eval) {
            echo "<tr>";
            echo "<td>{$eval['id']}</td>";
            echo "<td>{$eval['nom_evaluation']}</td>";
            echo "<td>{$eval['type_evaluation']}</td>";
            echo "<td>{$eval['periode']}</td>";
            echo "<td>{$eval['date_evaluation']}</td>";
            echo "<td>{$eval['nom_classe']}</td>";
            echo "<td>{$eval['nom_cours']}</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='text-warning'>⚠️ Aucune évaluation trouvée !</p>";
    }
} catch (Exception $e) {
    echo "<p class='text-danger'>❌ Erreur: " . $e->getMessage() . "</p>";
}

// 2. Vérifier les notes
echo "<h3>2. Notes dans la base de données</h3>";
try {
    $notes_query = "SELECT COUNT(*) as count FROM notes";
    $stmt = $db->prepare($notes_query);
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo "<p><strong>Nombre total de notes :</strong> " . $result['count'] . "</p>";
    
    if ($result['count'] > 0) {
        $notes_detail = "SELECT n.*, e.nom, e.prenom, ev.nom_evaluation, ev.periode, c.nom_cours, cl.nom_classe
                        FROM notes n
                        JOIN eleves e ON n.eleve_id = e.id
                        JOIN evaluations ev ON n.evaluation_id = ev.id
                        JOIN classe_cours cc ON ev.classe_cours_id = cc.id
                        JOIN cours c ON cc.cours_id = c.id
                        JOIN classes cl ON cc.classe_id = cl.id
                        WHERE cl.ecole_id = :ecole_id
                        LIMIT 10";
        $stmt = $db->prepare($notes_detail);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $notes = $stmt->fetchAll();
        
        echo "<table class='table table-sm table-bordered'>";
        echo "<thead><tr><th>ID</th><th>Élève</th><th>Évaluation</th><th>Période</th><th>Note</th><th>Absent</th><th>Validée</th><th>Classe</th><th>Cours</th></tr></thead>";
        echo "<tbody>";
        foreach ($notes as $note) {
            echo "<tr>";
            echo "<td>{$note['id']}</td>";
            echo "<td>{$note['nom']} {$note['prenom']}</td>";
            echo "<td>{$note['nom_evaluation']}</td>";
            echo "<td>{$note['periode']}</td>";
            echo "<td>{$note['valeur']}</td>";
            echo "<td>" . ($note['absent'] ? 'Oui' : 'Non') . "</td>";
            echo "<td>" . ($note['validee'] ? 'Oui' : 'Non') . "</td>";
            echo "<td>{$note['nom_classe']}</td>";
            echo "<td>{$note['nom_cours']}</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='text-warning'>⚠️ Aucune note trouvée !</p>";
    }
} catch (Exception $e) {
    echo "<p class='text-danger'>❌ Erreur: " . $e->getMessage() . "</p>";
}

// 3. Vérifier les classe_cours
echo "<h3>3. Relations classe-cours</h3>";
try {
    $classe_cours_query = "SELECT cc.*, c.nom_cours, cl.nom_classe, ens.nom as enseignant_nom
                          FROM classe_cours cc
                          JOIN cours c ON cc.cours_id = c.id
                          JOIN classes cl ON cc.classe_id = cl.id
                          JOIN enseignants ens ON cc.enseignant_id = ens.id
                          WHERE cl.ecole_id = :ecole_id";
    $stmt = $db->prepare($classe_cours_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classe_cours = $stmt->fetchAll();
    
    echo "<p><strong>Nombre de relations classe-cours :</strong> " . count($classe_cours) . "</p>";
    
    if (!empty($classe_cours)) {
        echo "<table class='table table-sm table-bordered'>";
        echo "<thead><tr><th>ID</th><th>Classe</th><th>Cours</th><th>Enseignant</th><th>Année</th><th>Statut</th></tr></thead>";
        echo "<tbody>";
        foreach ($classe_cours as $cc) {
            echo "<tr>";
            echo "<td>{$cc['id']}</td>";
            echo "<td>{$cc['nom_classe']}</td>";
            echo "<td>{$cc['nom_cours']}</td>";
            echo "<td>{$cc['enseignant_nom']}</td>";
            echo "<td>{$cc['annee_scolaire']}</td>";
            echo "<td>{$cc['statut']}</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p class='text-warning'>⚠️ Aucune relation classe-cours trouvée !</p>";
    }
} catch (Exception $e) {
    echo "<p class='text-danger'>❌ Erreur: " . $e->getMessage() . "</p>";
}

// 4. Test de la requête de calcul de moyenne
echo "<h3>4. Test de la requête de calcul de moyenne</h3>";
try {
    // Récupérer un élève et une classe pour tester
    $test_query = "SELECT e.id as eleve_id, e.nom, e.prenom, c.id as classe_id, c.nom_classe
                   FROM eleves e
                   JOIN inscriptions i ON e.id = i.eleve_id
                   JOIN classes c ON i.classe_id = c.id
                   WHERE c.ecole_id = :ecole_id
                   LIMIT 1";
    $stmt = $db->prepare($test_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $test_data = $stmt->fetch();
    
    if ($test_data) {
        echo "<p><strong>Test avec l'élève :</strong> {$test_data['nom']} {$test_data['prenom']} (Classe: {$test_data['nom_classe']})</p>";
        
        // Tester la requête de moyenne
        $moyenne_test = "SELECT AVG(n.valeur) as moyenne, COUNT(n.id) as nb_evaluations
                        FROM notes n
                        JOIN evaluations ev ON n.evaluation_id = ev.id
                        JOIN classe_cours cc ON ev.classe_cours_id = cc.id
                        WHERE cc.classe_id = :classe_id 
                        AND ev.periode = '1er Trimestre'
                        AND n.eleve_id = :eleve_id
                        AND n.absent = 0
                        AND n.validee = 1";
        $stmt = $db->prepare($moyenne_test);
        $stmt->execute([
            'classe_id' => $test_data['classe_id'],
            'eleve_id' => $test_data['eleve_id']
        ]);
        $moyenne_result = $stmt->fetch();
        
        echo "<p><strong>Résultat du calcul de moyenne :</strong></p>";
        echo "<ul>";
        echo "<li>Moyenne calculée : " . ($moyenne_result['moyenne'] ?? 'NULL') . "</li>";
        echo "<li>Nombre d'évaluations : " . $moyenne_result['nb_evaluations'] . "</li>";
        echo "</ul>";
        
        if ($moyenne_result['moyenne'] === null) {
            echo "<p class='text-warning'>⚠️ La moyenne est NULL - probablement pas de notes valides pour cet élève</p>";
        }
    } else {
        echo "<p class='text-warning'>⚠️ Aucun élève trouvé pour le test</p>";
    }
} catch (Exception $e) {
    echo "<p class='text-danger'>❌ Erreur: " . $e->getMessage() . "</p>";
}

// 5. Créer des données de test si nécessaire
echo "<h3>5. Création de données de test</h3>";
echo "<p>Si aucune donnée n'est trouvée, vous pouvez créer des données de test :</p>";
echo "<a href='create_test_data.php' class='btn btn-primary'>Créer des données de test</a>";

echo "<hr>";
echo "<p><a href='bulletins.php'>← Retour aux bulletins</a></p>";
?>

