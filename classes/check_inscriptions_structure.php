<?php
/**
 * Vérification de la structure exacte de la table inscriptions
 * Pour identifier les colonnes disponibles et corriger la requête
 */

require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Structure Table Inscriptions - " . APP_NAME . "</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-4'>
        <div class='card'>
            <div class='card-header bg-info text-white'>
                <h2>🔍 Structure de la table Inscriptions</h2>
            </div>
            <div class='card-body'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='alert alert-info'>
        <strong>🔧 Vérification de la structure...</strong><br>
        Identification des colonnes exactes disponibles dans la table 'inscriptions'.
    </div>";
    
    // 1. VÉRIFIER L'EXISTENCE DE LA TABLE
    echo "<h3 class='text-primary'>1. Vérification de l'existence de la table</h3>";
    
    $check_table = "SHOW TABLES LIKE 'inscriptions'";
    $stmt = $db->prepare($check_table);
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "<p class='text-success'>✅ Table 'inscriptions' existe</p>";
    } else {
        echo "<p class='text-danger'>❌ Table 'inscriptions' n'existe pas</p>";
        echo "<div class='alert alert-warning'>
            <h6>⚠️ Action recommandée :</h6>
            <p>Créez la table 'inscriptions' en utilisant le schéma de base de données.</p>
        </div>";
        exit;
    }
    
    // 2. STRUCTURE EXACTE DE LA TABLE
    echo "<h3 class='text-primary'>2. Structure exacte de la table 'inscriptions'</h3>";
    
    $describe_query = "DESCRIBE inscriptions";
    $stmt = $db->prepare($describe_query);
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "<table class='table table-sm table-bordered'>";
    echo "<thead><tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr></thead>";
    echo "<tbody>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><code>{$col['Field']}</code></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    
    // 3. IDENTIFIER LES COLONNES POUR L'INSERTION
    echo "<h3 class='text-primary'>3. Colonnes disponibles pour l'insertion</h3>";
    
    $required_columns = ['eleve_id', 'classe_id', 'annee_scolaire', 'date_inscription', 'statut', 'created_by'];
    $optional_columns = ['notes', 'observations', 'remarques', 'commentaires', 'description'];
    
    echo "<h4>Colonnes requises :</h4>";
    echo "<ul>";
    foreach ($required_columns as $col) {
        $exists = false;
        foreach ($columns as $db_col) {
            if ($db_col['Field'] === $col) {
                $exists = true;
                break;
            }
        }
        $status = $exists ? '✅ Existe' : '❌ Manquante';
        echo "<li><code>$col</code> - <strong>$status</strong></li>";
    }
    echo "</ul>";
    
    echo "<h4>Colonnes optionnelles (notes/observations) :</h4>";
    echo "<ul>";
    foreach ($optional_columns as $col) {
        $exists = false;
        foreach ($columns as $db_col) {
            if ($db_col['Field'] === $col) {
                $exists = true;
                break;
            }
        }
        $status = $exists ? '✅ Existe' : '❌ Manquante';
        echo "<li><code>$col</code> - <strong>$status</strong></li>";
    }
    echo "</ul>";
    
    // 4. REQUÊTE D'INSERTION CORRIGÉE
    echo "<h3 class='text-primary'>4. Requête d'insertion corrigée</h3>";
    
    // Trouver la colonne pour les notes
    $notes_column = null;
    foreach ($optional_columns as $col) {
        foreach ($columns as $db_col) {
            if ($db_col['Field'] === $col) {
                $notes_column = $col;
                break 2;
            }
        }
    }
    
    if ($notes_column) {
        echo "<div class='alert alert-success'>
            <h5>✅ Colonne pour les notes trouvée : <code>$notes_column</code></h5>
        </div>";
        
        $corrected_query = "INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire, date_inscription, statut, $notes_column, created_by, statut_record) 
                           VALUES (:eleve_id, :classe_id, :annee_scolaire, :date_inscription, 'validée', :notes, :created_by, 'actif')";
        
        echo "<h4>Requête corrigée :</h4>";
        echo "<pre class='bg-light p-3'>" . htmlspecialchars($corrected_query) . "</pre>";
        
    } else {
        echo "<div class='alert alert-warning'>
            <h5>⚠️ Aucune colonne pour les notes trouvée</h5>
            <p>La requête d'insertion ne peut pas inclure de notes.</p>
        </div>";
        
        $corrected_query = "INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire, date_inscription, statut, created_by, statut_record) 
                           VALUES (:eleve_id, :classe_id, :annee_scolaire, :date_inscription, 'validée', :created_by, 'actif')";
        
        echo "<h4>Requête sans notes :</h4>";
        echo "<pre class='bg-light p-3'>" . htmlspecialchars($corrected_query) . "</pre>";
    }
    
    // 5. TEST DE LA REQUÊTE CORRIGÉE
    echo "<h3 class='text-primary'>5. Test de la requête corrigée</h3>";
    
    try {
        $stmt = $db->prepare($corrected_query);
        echo "<p class='text-success'>✅ Requête préparée avec succès</p>";
        
        // Vérifier s'il y a des classes et élèves pour tester
        $count_classes = "SELECT COUNT(*) as total FROM classes WHERE ecole_id = :ecole_id AND statut = 'actif' LIMIT 1";
        $stmt = $db->prepare($count_classes);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $total_classes = $stmt->fetch()['total'];
        
        $count_eleves = "SELECT COUNT(*) as total FROM eleves WHERE ecole_id = :ecole_id AND statut = 'actif' LIMIT 1";
        $stmt = $db->prepare($count_eleves);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $total_eleves = $stmt->fetch()['total'];
        
        echo "<p><strong>Classes disponibles :</strong> $total_classes</p>";
        echo "<p><strong>Élèves disponibles :</strong> $total_eleves</p>";
        
        if ($total_classes > 0 && $total_eleves > 0) {
            echo "<p class='text-success'>✅ Données disponibles pour tester l'inscription</p>";
        } else {
            echo "<p class='text-warning'>⚠️ Données insuffisantes pour tester l'inscription</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='text-danger'>❌ Erreur lors de la préparation : " . $e->getMessage() . "</p>";
    }
    
    // 6. RECOMMANDATIONS
    echo "<h3 class='text-primary'>6. Recommandations</h3>";
    
    echo "<div class='alert alert-warning'>
        <h5>🔧 Actions recommandées :</h5>
        <ol>
            <li><strong>Mettre à jour students.php :</strong> Utilisez la requête corrigée ci-dessus</li>
            <li><strong>Vérifier les colonnes :</strong> Assurez-vous que toutes les colonnes requises existent</li>
            <li><strong>Tester l'inscription :</strong> Essayez d'inscrire un élève après la correction</li>
        </ol>
    </div>";
    
    echo "<div class='mt-4'>
        <a href='students.php?class_id=1' class='btn btn-primary me-2'>👥 Tester l'inscription</a>
        <a href='index.php' class='btn btn-success me-2'>📚 Liste des classes</a>
        <a href='../auth/dashboard.php' class='btn btn-secondary'>🏠 Tableau de bord</a>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
        <h4>❌ Erreur lors de la vérification</h4>
        <p>" . $e->getMessage() . "</p>
    </div>";
}

echo "</div></div></div></body></html>";
?>
