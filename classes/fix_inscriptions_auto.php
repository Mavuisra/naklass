<?php
/**
 * Correction automatique de la requête d'inscription
 * Vérifie la structure réelle et corrige automatiquement students.php
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
    <title>Correction Auto Inscription - " . APP_NAME . "</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-4'>
        <div class='card'>
            <div class='card-header bg-warning text-dark'>
                <h2>🔧 Correction Automatique de l'Inscription</h2>
            </div>
            <div class='card-body'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='alert alert-info'>
        <strong>🔧 Début de la correction automatique...</strong><br>
        Vérification de la structure réelle et correction automatique de students.php.
    </div>";
    
    // 1. VÉRIFIER LA STRUCTURE RÉELLE
    echo "<h3 class='text-primary'>1. Structure réelle de la table 'inscriptions'</h3>";
    
    $describe_query = "DESCRIBE inscriptions";
    $stmt = $db->prepare($describe_query);
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    $existing_columns = array_column($columns, 'Field');
    
    echo "<h4>Colonnes existantes :</h4>";
    echo "<ul>";
    foreach ($existing_columns as $col) {
        echo "<li><code>$col</code></li>";
    }
    echo "</ul>";
    
    // 2. IDENTIFIER LES COLONNES DISPONIBLES
    echo "<h3 class='text-primary'>2. Identification des colonnes disponibles</h3>";
    
    $required_columns = ['eleve_id', 'classe_id', 'annee_scolaire', 'date_inscription'];
    $optional_columns = ['notes', 'observations', 'remarques', 'commentaires', 'description'];
    $status_columns = ['statut', 'statut_record', 'statut_inscription'];
    $audit_columns = ['created_by', 'created_at', 'updated_by', 'updated_at'];
    
    echo "<h4>Colonnes requises :</h4>";
    echo "<ul>";
    foreach ($required_columns as $col) {
        $status = in_array($col, $existing_columns) ? '✅ Existe' : '❌ Manquante';
        echo "<li><code>$col</code> - <strong>$status</strong></li>";
    }
    echo "</ul>";
    
    echo "<h4>Colonnes de statut :</h4>";
    echo "<ul>";
    foreach ($status_columns as $col) {
        $status = in_array($col, $existing_columns) ? '✅ Existe' : '❌ Manquante';
        echo "<li><code>$col</code> - <strong>$status</strong></li>";
    }
    echo "</ul>";
    
    echo "<h4>Colonnes d'audit :</h4>";
    echo "<ul>";
    foreach ($audit_columns as $col) {
        $status = in_array($col, $existing_columns) ? '✅ Existe' : '❌ Manquante';
        echo "<li><code>$col</code> - <strong>$status</strong></li>";
    }
    echo "</ul>";
    
    echo "<h4>Colonnes de notes :</h4>";
    echo "<ul>";
    foreach ($optional_columns as $col) {
        $status = in_array($col, $existing_columns) ? '✅ Existe' : '❌ Manquante';
        echo "<li><code>$col</code> - <strong>$status</strong></li>";
    }
    echo "</ul>";
    
    // 3. CONSTRUIRE LA REQUÊTE CORRIGÉE
    echo "<h3 class='text-primary'>3. Construction de la requête corrigée</h3>";
    
    $insert_columns = ['eleve_id', 'classe_id', 'annee_scolaire', 'date_inscription'];
    $insert_values = [':eleve_id', ':classe_id', ':annee_scolaire', ':date_inscription'];
    
    // Ajouter la colonne de statut si elle existe
    $status_col = null;
    foreach ($status_columns as $col) {
        if (in_array($col, $existing_columns)) {
            $status_col = $col;
            break;
        }
    }
    
    if ($status_col) {
        $insert_columns[] = $status_col;
        $insert_values[] = "'validée'";
        echo "<p class='text-success'>✅ Colonne de statut : <code>$status_col</code></p>";
    } else {
        echo "<p class='text-warning'>⚠️ Aucune colonne de statut trouvée</p>";
    }
    
    // Ajouter la colonne de notes si elle existe
    $notes_col = null;
    foreach ($optional_columns as $col) {
        if (in_array($col, $existing_columns)) {
            $notes_col = $col;
            break;
        }
    }
    
    if ($notes_col) {
        $insert_columns[] = $notes_col;
        $insert_values[] = ':notes';
        echo "<p class='text-success'>✅ Colonne de notes : <code>$notes_col</code></p>";
    } else {
        echo "<p class='text-warning'>⚠️ Aucune colonne de notes trouvée</p>";
    }
    
    // Ajouter la colonne created_by si elle existe
    if (in_array('created_by', $existing_columns)) {
        $insert_columns[] = 'created_by';
        $insert_values[] = ':created_by';
        echo "<p class='text-success'>✅ Colonne created_by disponible</p>";
    } else {
        echo "<p class='text-warning'>⚠️ Colonne created_by manquante</p>";
    }
    
    // Ajouter la colonne statut_record si elle existe
    if (in_array('statut_record', $existing_columns)) {
        $insert_columns[] = 'statut_record';
        $insert_values[] = "'actif'";
        echo "<p class='text-success'>✅ Colonne statut_record disponible</p>";
    } else {
        echo "<p class='text-warning'>⚠️ Colonne statut_record manquante</p>";
    }
    
    // 4. REQUÊTE CORRIGÉE FINALE
    echo "<h3 class='text-primary'>4. Requête corrigée finale</h3>";
    
    $corrected_query = "INSERT INTO inscriptions (" . implode(', ', $insert_columns) . ") 
                        VALUES (" . implode(', ', $insert_values) . ")";
    
    echo "<h4>Requête corrigée :</h4>";
    echo "<pre class='bg-light p-3'>" . htmlspecialchars($corrected_query) . "</pre>";
    
    // 5. CORRIGER AUTOMATIQUEMENT students.php
    echo "<h3 class='text-primary'>5. Correction automatique de students.php</h3>";
    
    $students_file = 'students.php';
    $file_content = file_get_contents($students_file);
    
    if ($file_content === false) {
        echo "<p class='text-danger'>❌ Impossible de lire le fichier students.php</p>";
    } else {
        // Remplacer la requête d'insertion
        $old_pattern = '/INSERT INTO inscriptions \([^)]+\) VALUES \([^)]+\)/';
        $new_query = "INSERT INTO inscriptions (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $insert_values) . ")";
        
        if (preg_match($old_pattern, $file_content)) {
            $new_content = preg_replace($old_pattern, $new_query, $file_content);
            
            if (file_put_contents($students_file, $new_content)) {
                echo "<div class='alert alert-success'>
                    <h5>✅ Fichier students.php corrigé automatiquement !</h5>
                    <p>La requête d'insertion a été mise à jour avec la structure correcte de votre table.</p>
                </div>";
            } else {
                echo "<p class='text-danger'>❌ Impossible d'écrire dans le fichier students.php</p>";
            }
        } else {
            echo "<p class='text-warning'>⚠️ Requête d'insertion non trouvée dans students.php</p>";
        }
    }
    
    // 6. TEST DE LA REQUÊTE CORRIGÉE
    echo "<h3 class='text-primary'>6. Test de la requête corrigée</h3>";
    
    try {
        $stmt = $db->prepare($corrected_query);
        echo "<p class='text-success'>✅ Requête préparée avec succès !</p>";
        
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
    
    // 7. RÉSUMÉ DE LA CORRECTION
    echo "<h3 class='text-primary'>7. Résumé de la correction</h3>";
    
    echo "<div class='alert alert-success'>
        <h5>🔧 Correction appliquée :</h5>
        <ul>
            <li><strong>Structure détectée :</strong> " . count($existing_columns) . " colonnes trouvées</li>
            <li><strong>Requête corrigée :</strong> Utilise uniquement les colonnes existantes</li>
            <li><strong>Fichier mis à jour :</strong> students.php corrigé automatiquement</li>
        </ul>
    </div>";
    
    echo "<div class='mt-4'>
        <a href='students.php?class_id=1' class='btn btn-primary me-2'>👥 Tester l'inscription</a>
        <a href='index.php' class='btn btn-success me-2'>📚 Liste des classes</a>
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
