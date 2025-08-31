<?php
/**
 * Vérification de la structure des tables pour le module de rapports
 * Identifie les colonnes manquantes et corrige les requêtes SQL
 */

require_once '../config/database.php';

echo "<!DOCTYPE html>";
echo "<html lang='fr'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Vérification Structure Tables - Rapports</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css' rel='stylesheet'>";
echo "<style>";
echo ".table-structure { font-family: monospace; font-size: 0.9em; }";
echo ".missing-column { color: #dc3545; font-weight: bold; }";
echo ".existing-column { color: #28a745; }";
echo "</style>";
echo "</head>";
echo "<body class='bg-light'>";

echo "<div class='container mt-4'>";
echo "<div class='card'>";
echo "<div class='card-header'>";
echo "<h3><i class='bi bi-database me-2'></i>Vérification de la Structure des Tables</h3>";
echo "</div>";
echo "<div class='card-body'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    echo "<div class='alert alert-success'>✅ Connexion à la base de données réussie</div>";
    
    // Tables requises pour les rapports
    $required_tables = [
        'ecoles',
        'classes', 
        'inscriptions',
        'cours',
        'notes',
        'transactions_financieres',
        'presence',
        'sessions_presence',
        'utilisateurs',
        'roles'
    ];
    
    $table_structures = [];
    $missing_columns = [];
    
    foreach ($required_tables as $table) {
        echo "<h5>Table: <code>$table</code></h5>";
        
        try {
            $stmt = $db->query("SHOW COLUMNS FROM $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($columns) {
                $table_structures[$table] = array_column($columns, 'Field');
                
                echo "<div class='table-structure'>";
                foreach ($columns as $column) {
                    $column_name = $column['Field'];
                    $column_type = $column['Type'];
                    $is_null = $column['Null'];
                    $key = $column['Key'];
                    $default = $column['Default'];
                    
                    echo "<div class='existing-column'>";
                    echo "- <strong>$column_name</strong> ($column_type)";
                    if ($is_null === 'NO') echo " NOT NULL";
                    if ($key === 'PRI') echo " PRIMARY KEY";
                    if ($default !== null) echo " DEFAULT '$default'";
                    echo "</div>";
                }
                echo "</div>";
            } else {
                echo "<div class='alert alert-warning'>⚠️ Table vide ou inaccessible</div>";
            }
            
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>❌ Erreur: " . $e->getMessage() . "</div>";
        }
        
        echo "<hr>";
    }
    
    // Analyse des colonnes manquantes
    echo "<h4>🔍 Analyse des Colonnes Manquantes</h4>";
    
    $expected_columns = [
        'ecoles' => ['id', 'nom_ecole', 'adresse', 'statut'],
        'classes' => ['id', 'nom', 'niveau_id', 'section_id', 'ecole_id', 'statut'],
        'inscriptions' => ['id', 'etudiant_id', 'classe_id', 'ecole_id', 'statut'],
        'cours' => ['id', 'nom', 'matiere_id', 'classe_id', 'ecole_id', 'statut'],
        'notes' => ['id', 'inscription_id', 'evaluation_id', 'note'],
        'transactions_financieres' => ['id', 'ecole_id', 'type', 'montant', 'date'],
        'presence' => ['id', 'session_id', 'etudiant_id', 'statut'],
        'sessions_presence' => ['id', 'cours_id', 'date', 'heure_debut', 'heure_fin'],
        'utilisateurs' => ['id', 'nom', 'prenom', 'email', 'role_id', 'ecole_id', 'statut'],
        'roles' => ['id', 'code', 'libelle']
    ];
    
    foreach ($expected_columns as $table => $expected_cols) {
        if (isset($table_structures[$table])) {
            $existing_cols = $table_structures[$table];
            $missing = array_diff($expected_cols, $existing_cols);
            
            if (!empty($missing)) {
                echo "<div class='alert alert-warning'>";
                echo "<strong>Table $table:</strong> Colonnes manquantes: ";
                foreach ($missing as $col) {
                    echo "<code class='missing-column'>$col</code> ";
                }
                echo "</div>";
                $missing_columns[$table] = $missing;
            } else {
                echo "<div class='alert alert-success'>✅ Table <strong>$table</strong>: Toutes les colonnes requises sont présentes</div>";
            }
        }
    }
    
    // Recommandations pour corriger les requêtes
    echo "<h4>🛠️ Recommandations de Correction</h4>";
    
    if (!empty($missing_columns)) {
        echo "<div class='alert alert-info'>";
        echo "<strong>Colonnes manquantes détectées:</strong><br>";
        foreach ($missing_columns as $table => $columns) {
            echo "- <strong>$table</strong>: " . implode(', ', $columns) . "<br>";
        }
        echo "<br><strong>Actions recommandées:</strong><br>";
        echo "1. Vérifiez si les colonnes ont des noms différents<br>";
        echo "2. Ajoutez les colonnes manquantes si nécessaire<br>";
        echo "3. Modifiez les requêtes SQL pour utiliser les bonnes colonnes<br>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-success'>✅ Toutes les colonnes requises sont présentes. Les requêtes SQL devraient fonctionner.</div>";
    }
    
    // Test des requêtes SQL
    echo "<h4>🧪 Test des Requêtes SQL</h4>";
    
    $test_queries = [
        'ecoles' => "SELECT COUNT(*) FROM ecoles",
        'classes' => "SELECT COUNT(*) FROM classes",
        'inscriptions' => "SELECT COUNT(*) FROM inscriptions",
        'cours' => "SELECT COUNT(*) FROM cours",
        'utilisateurs' => "SELECT COUNT(*) FROM utilisateurs"
    ];
    
    foreach ($test_queries as $table => $query) {
        try {
            $stmt = $db->query($query);
            $count = $stmt->fetchColumn();
            echo "<div class='alert alert-success'>✅ <strong>$table</strong>: $count enregistrements</div>";
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>❌ <strong>$table</strong>: " . $e->getMessage() . "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ <strong>Erreur:</strong> " . $e->getMessage() . "</div>";
}

echo "</div>"; // card-body
echo "</div>"; // card
echo "</div>"; // container

echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>";
echo "</body>";
echo "</html>";
?>
