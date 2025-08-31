<?php
/**
 * Script de correction COMPLÈTE de toutes les tables
 * Vérification et correction des tables : tuteurs, eleve_tuteurs, inscriptions, classes
 */

require_once '../includes/functions.php';

// Vérifier l'authentification
requireAuth();

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction Complète des Tables - " . APP_NAME . "</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-4'>
        <div class='card'>
            <div class='card-header bg-danger text-white'>
                <h2>🔧 Correction COMPLÈTE de toutes les Tables</h2>
            </div>
            <div class='card-body'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='alert alert-warning'>
        <strong>⚠️ ATTENTION :</strong><br>
        Ce script va vérifier et corriger TOUTES les tables nécessaires aux étudiants.<br>
        <strong>Assurez-vous d'avoir une sauvegarde de votre base de données !</strong>
    </div>";
    
    // 1. CORRECTION DE LA TABLE TUTEURS
    echo "<h3 class='text-primary'>1. Correction de la table 'tuteurs'</h3>";
    
    $tuteurs_columns = [
        'lien_parente' => "ENUM('père', 'mère', 'tuteur_legal', 'autre') NOT NULL DEFAULT 'tuteur_legal'",
        'profession' => "VARCHAR(255)",
        'personne_contact_urgence' => "BOOLEAN DEFAULT FALSE",
        'version' => "INT DEFAULT 1",
        'notes_internes' => "TEXT"
    ];
    
    $added_tuteurs = [];
    foreach ($tuteurs_columns as $col => $definition) {
        try {
            $check_query = "SHOW COLUMNS FROM tuteurs LIKE '$col'";
            $stmt = $db->prepare($check_query);
            $stmt->execute();
            $exists = $stmt->fetch();
            
            if (!$exists) {
                $add_query = "ALTER TABLE tuteurs ADD COLUMN `$col` $definition";
                $db->exec($add_query);
                $added_tuteurs[] = $col;
                echo "<p class='text-success'>✅ Colonne '$col' ajoutée à 'tuteurs'</p>";
            } else {
                echo "<p class='text-muted'>ℹ️ Colonne '$col' existe déjà dans 'tuteurs'</p>";
            }
        } catch (Exception $e) {
            echo "<p class='text-danger'>❌ Erreur pour '$col' dans 'tuteurs': " . $e->getMessage() . "</p>";
        }
    }
    
    // 2. CORRECTION DE LA TABLE ELEVE_TUTEURS
    echo "<h3 class='text-primary'>2. Correction de la table 'eleve_tuteurs'</h3>";
    
    $eleve_tuteurs_columns = [
        'principal' => "BOOLEAN NOT NULL DEFAULT FALSE",
        'autorisation_sortie' => "BOOLEAN DEFAULT FALSE",
        'version' => "INT DEFAULT 1",
        'notes_internes' => "TEXT"
    ];
    
    $added_eleve_tuteurs = [];
    foreach ($eleve_tuteurs_columns as $col => $definition) {
        try {
            $check_query = "SHOW COLUMNS FROM eleve_tuteurs LIKE '$col'";
            $stmt = $db->prepare($check_query);
            $stmt->execute();
            $exists = $stmt->fetch();
            
            if (!$exists) {
                $add_query = "ALTER TABLE eleve_tuteurs ADD COLUMN `$col` $definition";
                $db->exec($add_query);
                $added_eleve_tuteurs[] = $col;
                echo "<p class='text-success'>✅ Colonne '$col' ajoutée à 'eleve_tuteurs'</p>";
            } else {
                echo "<p class='text-muted'>ℹ️ Colonne '$col' existe déjà dans 'eleve_tuteurs'</p>";
            }
        } catch (Exception $e) {
            echo "<p class='text-danger'>❌ Erreur pour '$col' dans 'eleve_tuteurs': " . $e->getMessage() . "</p>";
        }
    }
    
    // 3. CORRECTION DE LA TABLE INSCRIPTIONS
    echo "<h3 class='text-primary'>3. Correction de la table 'inscriptions'</h3>";
    
    $inscriptions_columns = [
        'numero_dossier' => "VARCHAR(100) UNIQUE",
        'motif_annulation' => "TEXT",
        'redoublant' => "BOOLEAN DEFAULT FALSE",
        'mode_inscription' => "ENUM('en_presentiel', 'en_ligne') DEFAULT 'en_presentiel'",
        'pieces_jointes' => "JSON",
        'remarques' => "TEXT",
        'version' => "INT DEFAULT 1",
        'notes_internes' => "TEXT"
    ];
    
    $added_inscriptions = [];
    foreach ($inscriptions_columns as $col => $definition) {
        try {
            $check_query = "SHOW COLUMNS FROM inscriptions LIKE '$col'";
            $stmt = $db->prepare($check_query);
            $stmt->execute();
            $exists = $stmt->fetch();
            
            if (!$exists) {
                $add_query = "ALTER TABLE inscriptions ADD COLUMN `$col` $definition";
                $db->exec($add_query);
                $added_inscriptions[] = $col;
                echo "<p class='text-success'>✅ Colonne '$col' ajoutée à 'inscriptions'</p>";
            } else {
                echo "<p class='text-muted'>ℹ️ Colonne '$col' existe déjà dans 'inscriptions'</p>";
            }
        } catch (Exception $e) {
            echo "<p class='text-danger'>❌ Erreur pour '$col' dans 'inscriptions': " . $e->getMessage() . "</p>";
        }
    }
    
    // 4. CORRECTION DE LA TABLE CLASSES
    echo "<h3 class='text-primary'>4. Correction de la table 'classes'</h3>";
    
    $classes_columns = [
        'option_filiere' => "VARCHAR(255)",
        'capacite_max' => "INT",
        'salle_physique' => "VARCHAR(100)",
        'version' => "INT DEFAULT 1",
        'notes_internes' => "TEXT"
    ];
    
    $added_classes = [];
    foreach ($classes_columns as $col => $definition) {
        try {
            $check_query = "SHOW COLUMNS FROM classes LIKE '$col'";
            $stmt = $db->prepare($check_query);
            $stmt->execute();
            $exists = $stmt->fetch();
            
            if (!$exists) {
                $add_query = "ALTER TABLE classes ADD COLUMN `$col` $definition";
                $db->exec($add_query);
                $added_classes[] = $col;
                echo "<p class='text-success'>✅ Colonne '$col' ajoutée à 'classes'</p>";
            } else {
                echo "<p class='text-muted'>ℹ️ Colonne '$col' existe déjà dans 'classes'</p>";
            }
        } catch (Exception $e) {
            echo "<p class='text-danger'>❌ Erreur pour '$col' dans 'classes': " . $e->getMessage() . "</p>";
        }
    }
    
    // 5. RÉSUMÉ DES CORRECTIONS
    echo "<h3 class='text-primary'>5. Résumé des corrections appliquées</h3>";
    
    $total_added = count($added_tuteurs) + count($added_eleve_tuteurs) + count($added_inscriptions) + count($added_classes);
    
    if ($total_added > 0) {
        echo "<div class='alert alert-success'>
            <h5>🎉 Corrections appliquées avec succès !</h5>
            <p><strong>Total des colonnes ajoutées : $total_added</strong></p>
            <ul>";
        
        if (!empty($added_tuteurs)) {
            echo "<li><strong>Table 'tuteurs' :</strong> " . implode(', ', $added_tuteurs) . "</li>";
        }
        if (!empty($added_eleve_tuteurs)) {
            echo "<li><strong>Table 'eleve_tuteurs' :</strong> " . implode(', ', $added_eleve_tuteurs) . "</li>";
        }
        if (!empty($added_inscriptions)) {
            echo "<li><strong>Table 'inscriptions' :</strong> " . implode(', ', $added_inscriptions) . "</li>";
        }
        if (!empty($added_classes)) {
            echo "<li><strong>Table 'classes' :</strong> " . implode(', ', $added_classes) . "</li>";
        }
        
        echo "</ul></div>";
    } else {
        echo "<div class='alert alert-info'>
            <h5>ℹ️ Aucune correction nécessaire</h5>
            <p>Toutes les colonnes requises existent déjà dans vos tables.</p>
        </div>";
    }
    
    // 6. TEST FINAL DE LA REQUÊTE CORRIGÉE
    echo "<h3 class='text-primary'>6. Test final de la requête corrigée</h3>";
    
    $test_query = "SELECT t.id, t.nom, t.prenom, t.telephone, t.email, 
                          COALESCE(t.lien_parente, 'tuteur') as lien_parente
                   FROM tuteurs t
                   JOIN eleve_tuteurs et ON t.id = et.tuteur_id
                   WHERE et.eleve_id = :student_id AND t.statut = 'actif' AND et.statut = 'actif'
                   ORDER BY COALESCE(et.tuteur_principal, 0) DESC, t.nom, t.prenom";
    
    echo "<h4>Requête testée (avec COALESCE pour et.tuteur_principal) :</h4>";
    echo "<pre class='bg-light p-3'>" . htmlspecialchars($test_query) . "</pre>";
    
    try {
        $stmt = $db->prepare($test_query);
        echo "<p class='text-success'>✅ Requête préparée avec succès !</p>";
        
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
    
    // 7. MISE À JOUR DE LA REQUÊTE DANS EDIT.PHP
    echo "<h3 class='text-primary'>7. Mise à jour de la requête dans edit.php</h3>";
    
    $updated_query = "SELECT t.id, t.nom, t.prenom, t.telephone, t.email, 
                             COALESCE(t.lien_parente, 'tuteur') as lien_parente
                      FROM tuteurs t
                      JOIN eleve_tuteurs et ON t.id = et.tuteur_id
                      WHERE et.eleve_id = :student_id AND t.statut = 'actif' AND et.statut = 'actif'
                      ORDER BY COALESCE(et.tuteur_principal, 0) DESC, t.nom, t.prenom";
    
    echo "<div class='alert alert-info'>
        <h6>📝 Requête à utiliser dans edit.php :</h6>
        <pre class='bg-light p-3'>" . htmlspecialchars($updated_query) . "</pre>
        <p><strong>Note :</strong> Utilisez COALESCE(et.tuteur_principal, 0) pour éviter l'erreur si la colonne n'existe pas.</p>
    </div>";
    
    echo "<div class='mt-4'>
        <a href='edit.php?id=1' class='btn btn-primary me-2'>✏️ Tester edit.php</a>
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
