<?php
/**
 * Diagnostic complet de la recherche d'élèves
 * Exécutez ce script pour identifier tous les problèmes
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier l'authentification
requireAuth();

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnostic complet - " . APP_NAME . "</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container-fluid mt-4'>
        <div class='card'>
            <div class='card-header bg-primary text-white'>
                <h2>🔍 Diagnostic complet de la recherche d'élèves</h2>
            </div>
            <div class='card-body'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='alert alert-info'>
        <strong>🔧 Début du diagnostic...</strong><br>
        Ce script va analyser en détail tous les aspects de la recherche d'élèves.
    </div>";
    
    // 1. VÉRIFICATION DES TABLES ET COLONNES
    echo "<h3 class='text-primary'>1. Vérification des tables et colonnes</h3>";
    
    $tables_to_check = ['eleves', 'classes', 'inscriptions'];
    
    foreach ($tables_to_check as $table) {
        echo "<h4>Table '$table' :</h4>";
        
        try {
            $check_table = "SHOW TABLES LIKE '$table'";
            $stmt = $db->prepare($check_table);
            $stmt->execute();
            $table_exists = $stmt->fetch();
            
            if ($table_exists) {
                echo "<p class='success'>✅ Table '$table' existe</p>";
                
                // Vérifier les colonnes
                $check_columns = "DESCRIBE $table";
                $stmt = $db->prepare($check_columns);
                $stmt->execute();
                $columns = $stmt->fetchAll();
                
                echo "<table class='table table-sm table-bordered'>";
                echo "<thead><tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr></thead>";
                echo "<tbody>";
                foreach ($columns as $col) {
                    echo "<tr>";
                    echo "<td>{$col['Field']}</td>";
                    echo "<td>{$col['Type']}</td>";
                    echo "<td>{$col['Null']}</td>";
                    echo "<td>{$col['Key']}</td>";
                    echo "<td>{$col['Default']}</td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<p class='error'>❌ Table '$table' n'existe pas</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erreur lors de la vérification de '$table': " . $e->getMessage() . "</p>";
        }
    }
    
    // 2. VÉRIFICATION DES DONNÉES
    echo "<h3 class='text-primary'>2. Vérification des données</h3>";
    
    // Compter les élèves
    try {
        $count_eleves = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = ?";
        $stmt = $db->prepare($count_eleves);
        $stmt->execute([$_SESSION['ecole_id']]);
        $total_eleves = $stmt->fetch()['total'];
        
        echo "<p><strong>Total des élèves :</strong> <span class='info'>$total_eleves</span></p>";
        
        if ($total_eleves == 0) {
            echo "<p class='warning'>⚠️ Aucun élève trouvé dans la base de données</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur lors du comptage des élèves: " . $e->getMessage() . "</p>";
    }
    
    // Compter les classes
    try {
        $count_classes = "SELECT COUNT(*) as total FROM classes WHERE ecole_id = ?";
        $stmt = $db->prepare($count_classes);
        $stmt->execute([$_SESSION['ecole_id']]);
        $total_classes = $stmt->fetch()['total'];
        
        echo "<p><strong>Total des classes :</strong> <span class='info'>$total_classes</span></p>";
        
        if ($total_classes == 0) {
            echo "<p class='warning'>⚠️ Aucune classe trouvée dans la base de données</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur lors du comptage des classes: " . $e->getMessage() . "</p>";
    }
    
    // Compter les inscriptions
    try {
        $count_inscriptions = "SELECT COUNT(*) as total FROM inscriptions i 
                              JOIN eleves e ON i.eleve_id = e.id 
                              WHERE e.ecole_id = ?";
        $stmt = $db->prepare($count_inscriptions);
        $stmt->execute([$_SESSION['ecole_id']]);
        $total_inscriptions = $stmt->fetch()['total'];
        
        echo "<p><strong>Total des inscriptions :</strong> <span class='info'>$total_inscriptions</span></p>";
        
        if ($total_inscriptions == 0) {
            echo "<p class='warning'>⚠️ Aucune inscription trouvée dans la base de données</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur lors du comptage des inscriptions: " . $e->getMessage() . "</p>";
    }
    
    // 3. TEST DE LA REQUÊTE DE RECHERCHE
    echo "<h3 class='text-primary'>3. Test de la requête de recherche</h3>";
    
    $search_query = "SELECT e.id, e.matricule, e.nom, e.prenom,
                            c.nom_classe, 
                            c.niveau,
                            c.cycle,
                            i.annee_scolaire
                     FROM eleves e 
                     LEFT JOIN inscriptions i ON e.id = i.eleve_id 
                          AND i.statut IN ('validée', 'en_cours')
                     LEFT JOIN classes c ON i.classe_id = c.id
                     WHERE e.ecole_id = ? 
                     AND e.statut = 'actif'
                     AND (e.nom LIKE ? 
                          OR e.prenom LIKE ? 
                          OR e.matricule LIKE ?)
                     GROUP BY e.id
                     ORDER BY e.nom, e.prenom
                     LIMIT 5";
    
    echo "<h4>Requête testée :</h4>";
    echo "<pre>" . htmlspecialchars($search_query) . "</pre>";
    
    try {
        $stmt = $db->prepare($search_query);
        $search_term = "%test%";
        $stmt->execute([
            $_SESSION['ecole_id'],
            $search_term,
            $search_term,
            $search_term
        ]);
        
        $test_results = $stmt->fetchAll();
        
        echo "<h4>Résultats du test (recherche 'test') :</h4>";
        if (empty($test_results)) {
            echo "<p class='text-muted'>Aucun résultat trouvé pour 'test'</p>";
        } else {
            echo "<table class='table table-sm table-bordered'>";
            echo "<thead><tr><th>ID</th><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Classe</th><th>Niveau</th><th>Cycle</th><th>Année</th></tr></thead>";
            echo "<tbody>";
            foreach ($test_results as $row) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['matricule']}</td>";
                echo "<td>{$row['nom']}</td>";
                echo "<td>{$row['prenom']}</td>";
                echo "<td>{$row['nom_classe']}</td>";
                echo "<td>{$row['niveau']}</td>";
                echo "<td>{$row['cycle']}</td>";
                echo "<td>{$row['annee_scolaire']}</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur lors du test de la requête: " . $e->getMessage() . "</p>";
    }
    
    // 4. TEST AVEC UN VRAI ÉLÈVE
    echo "<h3 class='text-primary'>4. Test avec un vrai élève</h3>";
    
    try {
        // Chercher un vrai élève
        $real_search = "SELECT e.id, e.matricule, e.nom, e.prenom,
                               c.nom_classe, 
                               c.niveau,
                               c.cycle,
                               i.annee_scolaire
                        FROM eleves e 
                        LEFT JOIN inscriptions i ON e.id = i.eleve_id 
                             AND i.statut IN ('validée', 'en_cours')
                        LEFT JOIN classes c ON i.classe_id = c.id
                        WHERE e.ecole_id = ? 
                        AND e.statut = 'actif'
                        LIMIT 3";
        
        $stmt = $db->prepare($real_search);
        $stmt->execute([$_SESSION['ecole_id']]);
        $real_results = $stmt->fetchAll();
        
        if (empty($real_results)) {
            echo "<p class='text-warning'>⚠️ Aucun élève trouvé dans la base de données</p>";
        } else {
            echo "<h4>Exemples d'élèves existants :</h4>";
            echo "<table class='table table-sm table-bordered'>";
            echo "<thead><tr><th>ID</th><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Classe</th><th>Niveau</th><th>Cycle</th><th>Année</th></tr></thead>";
            echo "<tbody>";
            foreach ($real_results as $row) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['matricule']}</td>";
                echo "<td>{$row['nom']}</td>";
                echo "<td>{$row['prenom']}</td>";
                echo "<td>{$row['nom_classe']}</td>";
                echo "<td>{$row['niveau']}</td>";
                echo "<td>{$row['cycle']}</td>";
                echo "<td>{$row['annee_scolaire']}</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
            
            // Test de recherche avec le nom du premier élève
            if (!empty($real_results[0]['nom'])) {
                $test_nom = $real_results[0]['nom'];
                echo "<h4>Test de recherche avec le nom '{$test_nom}' :</h4>";
                
                $stmt = $db->prepare($search_query);
                $search_term = "%{$test_nom}%";
                $stmt->execute([
                    $_SESSION['ecole_id'],
                    $search_term,
                    $search_term,
                    $search_term
                ]);
                
                $search_results = $stmt->fetchAll();
                
                if (empty($search_results)) {
                    echo "<p class='text-danger'>❌ La recherche ne fonctionne pas !</p>";
                } else {
                    echo "<p class='text-success'>✅ La recherche fonctionne ! {$test_nom} trouvé.</p>";
                }
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur lors du test avec un vrai élève: " . $e->getMessage() . "</p>";
    }
    
    // 5. VÉRIFICATION DES FICHIERS
    echo "<h3 class='text-primary'>5. Vérification des fichiers</h3>";
    
    $files_to_check = [
        'search_students.php' => 'Fichier de recherche original',
        'search_students_fixed.php' => 'Fichier de recherche corrigé',
        'payment.php' => 'Formulaire de paiement'
    ];
    
    foreach ($files_to_check as $file => $description) {
        if (file_exists($file)) {
            echo "<p class='success'>✅ $file - $description</p>";
        } else {
            echo "<p class='error'>❌ $file - $description (fichier manquant)</p>";
        }
    }
    
    // 6. RECOMMANDATIONS
    echo "<h3 class='text-primary'>6. Recommandations</h3>";
    
    echo "<div class='alert alert-warning'>
        <h5>🔧 Actions recommandées :</h5>
        <ol>
            <li><strong>Exécutez d'abord le test :</strong> <code>test_search.php</code></li>
            <li><strong>Remplacez le fichier de recherche :</strong> Utilisez <code>search_students_fixed.php</code></li>
            <li><strong>Vérifiez les données :</strong> Assurez-vous qu'il y a des élèves, classes et inscriptions</li>
            <li><strong>Testez la recherche :</strong> Allez sur <code>payment.php</code> et testez la recherche</li>
        </ol>
    </div>";
    
    echo "<div class='mt-4'>
        <a href='test_search.php' class='btn btn-primary me-2'>🔍 Test de recherche</a>
        <a href='payment.php' class='btn btn-success me-2'>💰 Formulaire de paiement</a>
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
