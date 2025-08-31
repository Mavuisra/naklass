<?php
/**
 * Script de correction de la table tuteurs
 * Ajoute les colonnes manquantes si nécessaire
 */

require_once '../includes/functions.php';

// Vérifier l'authentification
requireAuth();

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction Table Tuteurs - " . APP_NAME . "</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-4'>
        <div class='card'>
            <div class='card-header bg-warning text-dark'>
                <h2>🔧 Correction de la table Tuteurs</h2>
            </div>
            <div class='card-body'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='alert alert-info'>
        <strong>🔧 Début de la correction...</strong><br>
        Vérification et ajout des colonnes manquantes dans la table 'tuteurs'.
    </div>";
    
    // 1. VÉRIFIER LA STRUCTURE ACTUELLE
    echo "<h3 class='text-primary'>1. Vérification de la structure actuelle</h3>";
    
    $describe_query = "DESCRIBE tuteurs";
    $stmt = $db->prepare($describe_query);
    $stmt->execute();
    $current_columns = $stmt->fetchAll();
    
    $existing_columns = array_column($current_columns, 'Field');
    
    echo "<h4>Colonnes existantes :</h4>";
    echo "<ul>";
    foreach ($existing_columns as $col) {
        echo "<li><code>$col</code></li>";
    }
    echo "</ul>";
    
    // 2. COLONNES REQUISES
    echo "<h3 class='text-primary'>2. Colonnes requises</h3>";
    
    $required_columns = [
        'lien_parente' => "ENUM('père', 'mère', 'tuteur_legal', 'autre') NOT NULL DEFAULT 'tuteur_legal'",
        'profession' => "VARCHAR(255)",
        'personne_contact_urgence' => "BOOLEAN DEFAULT FALSE",
        'version' => "INT DEFAULT 1",
        'notes_internes' => "TEXT"
    ];
    
    echo "<h4>Colonnes à vérifier/ajouter :</h4>";
    echo "<ul>";
    foreach ($required_columns as $col => $definition) {
        $status = in_array($col, $existing_columns) ? '✅ Existe' : '❌ Manquante';
        echo "<li><code>$col</code> - $definition - <strong>$status</strong></li>";
    }
    echo "</ul>";
    
    // 3. AJOUT DES COLONNES MANQUANTES
    echo "<h3 class='text-primary'>3. Ajout des colonnes manquantes</h3>";
    
    $added_columns = [];
    
    foreach ($required_columns as $col => $definition) {
        if (!in_array($col, $existing_columns)) {
            try {
                $add_query = "ALTER TABLE tuteurs ADD COLUMN `$col` $definition";
                $db->exec($add_query);
                $added_columns[] = $col;
                echo "<p class='text-success'>✅ Colonne '$col' ajoutée avec succès</p>";
            } catch (Exception $e) {
                echo "<p class='text-danger'>❌ Erreur lors de l'ajout de '$col': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='text-muted'>ℹ️ Colonne '$col' existe déjà</p>";
        }
    }
    
    // 4. VÉRIFICATION FINALE
    echo "<h3 class='text-primary'>4. Vérification finale</h3>";
    
    if (!empty($added_columns)) {
        echo "<div class='alert alert-success'>
            <h5>🎉 Colonnes ajoutées avec succès !</h5>
            <p>Les colonnes suivantes ont été ajoutées : " . implode(', ', $added_columns) . "</p>
        </div>";
        
        // Vérifier la nouvelle structure
        $stmt = $db->prepare($describe_query);
        $stmt->execute();
        $new_columns = $stmt->fetchAll();
        
        echo "<h4>Nouvelle structure de la table :</h4>";
        echo "<table class='table table-sm table-bordered'>";
        echo "<thead><tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr></thead>";
        echo "<tbody>";
        foreach ($new_columns as $col) {
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
    } else {
        echo "<div class='alert alert-info'>
            <h5>ℹ️ Aucune colonne à ajouter</h5>
            <p>Toutes les colonnes requises existent déjà dans la table 'tuteurs'.</p>
        </div>";
    }
    
    // 5. TEST DE LA REQUÊTE CORRIGÉE
    echo "<h3 class='text-primary'>5. Test de la requête corrigée</h3>";
    
    $test_query = "SELECT t.id, t.nom, t.prenom, t.telephone, t.email, 
                          COALESCE(t.lien_parente, 'tuteur') as lien_parente
                   FROM tuteurs t
                   JOIN eleve_tuteurs et ON t.id = et.tuteur_id
                   WHERE et.eleve_id = :student_id AND t.statut = 'actif' AND et.statut = 'actif'
                   ORDER BY et.tuteur_principal DESC, t.nom, t.prenom";
    
    echo "<h4>Requête testée :</h4>";
    echo "<pre class='bg-light p-3'>" . htmlspecialchars($test_query) . "</pre>";
    
    try {
        $stmt = $db->prepare($test_query);
        echo "<p class='text-success'>✅ Requête préparée avec succès</p>";
        
        // Vérifier s'il y a des élèves pour tester
        $count_eleves = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id AND statut = 'actif' LIMIT 1";
        $stmt = $db->prepare($count_eleves);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $total_eleves = $stmt->fetch()['total'];
        
        if ($total_eleves > 0) {
            echo "<p class='text-success'>✅ Il y a $total_eleves élève(s) pour tester</p>";
        } else {
            echo "<p class='text-warning'>⚠️ Aucun élève trouvé pour tester la requête</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='text-danger'>❌ Erreur lors de la préparation : " . $e->getMessage() . "</p>";
    }
    
    echo "<div class='mt-4'>
        <a href='edit.php?id=1' class='btn btn-primary me-2'>✏️ Tester edit.php</a>
        <a href='diagnostic_tuteurs.php' class='btn btn-info me-2'>🔍 Diagnostic complet</a>
        <a href='index.php' class='btn btn-success me-2'>📋 Liste des élèves</a>
        <a href='../auth/dashboard.php' class='btn btn-secondary'>🏠 Tableau de bord</a>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
        <h4>❌ Erreur lors de la correction</h4>
        <p>" . $e->getMessage() . "</p>
    </div>";
}

echo "</div></div></div></body></html>";
?>
