<?php
/**
 * Script de correction automatique des colonnes manquantes
 * Ajoute les colonnes ecole_id manquantes dans les tables
 */

require_once '../config/database.php';

echo "<!DOCTYPE html>";
echo "<html lang='fr'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Correction Colonnes Manquantes - Rapports</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css' rel='stylesheet'>";
echo "<style>";
echo ".success-icon { color: #28a745; }";
echo ".warning-icon { color: #ffc107; }";
echo ".error-icon { color: #dc3545; }";
echo ".info-icon { color: #17a2b8; }";
echo "</style>";
echo "</head>";
echo "<body class='bg-light'>";

echo "<div class='container mt-4'>";
echo "<div class='card'>";
echo "<div class='card-header'>";
echo "<h3><i class='bi bi-wrench me-2'></i>Correction Automatique des Colonnes Manquantes</h3>";
echo "</div>";
echo "<div class='card-body'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    echo "<div class='alert alert-success'>✅ Connexion à la base de données réussie</div>";
    
    // Tables à vérifier et corriger
    $tables_to_fix = [
        'classes' => [
            'ecole_id' => 'INT',
            'description' => 'ID de l\'école pour filtrer les classes'
        ],
        'inscriptions' => [
            'ecole_id' => 'INT', 
            'description' => 'ID de l\'école pour filtrer les inscriptions'
        ],
        'cours' => [
            'ecole_id' => 'INT',
            'description' => 'ID de l\'école pour filtrer les cours'
        ],
        'transactions_financieres' => [
            'ecole_id' => 'INT',
            'description' => 'ID de l\'école pour filtrer les transactions'
        ],
        'utilisateurs' => [
            'ecole_id' => 'INT',
            'description' => 'ID de l\'école pour filtrer les utilisateurs'
        ]
    ];
    
    $corrections_applied = [];
    $errors = [];
    
    foreach ($tables_to_fix as $table => $column_info) {
        echo "<h5>Table: <code>$table</code></h5>";
        
        try {
            // Vérifier si la colonne existe
            $stmt = $db->query("SHOW COLUMNS FROM $table LIKE 'ecole_id'");
            $column_exists = $stmt->rowCount() > 0;
            
            if ($column_exists) {
                echo "<div class='alert alert-success'>";
                echo "<i class='bi bi-check-circle success-icon me-2'></i>";
                echo "Colonne <code>ecole_id</code> existe déjà dans la table <strong>$table</strong>";
                echo "</div>";
            } else {
                echo "<div class='alert alert-warning'>";
                echo "<i class='bi bi-exclamation-triangle warning-icon me-2'></i>";
                echo "Colonne <code>ecole_id</code> manquante dans la table <strong>$table</strong>";
                echo "</div>";
                
                // Ajouter la colonne
                try {
                    $alter_query = "ALTER TABLE $table ADD COLUMN ecole_id INT";
                    $db->exec($alter_query);
                    
                    echo "<div class='alert alert-success'>";
                    echo "<i class='bi bi-check-circle success-icon me-2'></i>";
                    echo "Colonne <code>ecole_id</code> ajoutée avec succès dans la table <strong>$table</strong>";
                    echo "</div>";
                    
                    $corrections_applied[] = $table;
                    
                    // Ajouter une clé étrangère si la table ecoles existe
                    try {
                        $fk_query = "ALTER TABLE $table ADD CONSTRAINT fk_{$table}_ecole FOREIGN KEY (ecole_id) REFERENCES ecoles(id)";
                        $db->exec($fk_query);
                        
                        echo "<div class='alert alert-info'>";
                        echo "<i class='bi bi-info-circle info-icon me-2'></i>";
                        echo "Clé étrangère ajoutée pour la table <strong>$table</strong>";
                        echo "</div>";
                        
                    } catch (Exception $e) {
                        echo "<div class='alert alert-warning'>";
                        echo "<i class='bi bi-exclamation-triangle warning-icon me-2'></i>";
                        echo "Impossible d'ajouter la clé étrangère: " . $e->getMessage();
                        echo "</div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>";
                    echo "<i class='bi bi-x-circle error-icon me-2'></i>";
                    echo "Erreur lors de l'ajout de la colonne: " . $e->getMessage();
                    echo "</div>";
                    $errors[] = "Table $table: " . $e->getMessage();
                }
            }
            
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>";
            echo "<i class='bi bi-x-circle error-icon me-2'></i>";
            echo "Erreur lors de la vérification de la table <strong>$table</strong>: " . $e->getMessage();
            echo "</div>";
            $errors[] = "Vérification $table: " . $e->getMessage();
        }
        
        echo "<hr>";
    }
    
    // Résumé des corrections
    echo "<h4>📋 Résumé des Corrections</h4>";
    
    if (!empty($corrections_applied)) {
        echo "<div class='alert alert-success'>";
        echo "<h5><i class='bi bi-check-circle me-2'></i>Corrections Appliquées</h5>";
        echo "<ul>";
        foreach ($corrections_applied as $table) {
            echo "<li>Table <strong>$table</strong> : Colonne <code>ecole_id</code> ajoutée</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
    if (!empty($errors)) {
        echo "<div class='alert alert-danger'>";
        echo "<h5><i class='bi bi-x-circle me-2'></i>Erreurs Rencontrées</h5>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
    if (empty($corrections_applied) && empty($errors)) {
        echo "<div class='alert alert-info'>";
        echo "<i class='bi bi-info-circle me-2'></i>";
        echo "Aucune correction nécessaire. Toutes les colonnes <code>ecole_id</code> sont déjà présentes.";
        echo "</div>";
    }
    
    // Actions recommandées
    echo "<h4>🛠️ Actions Recommandées</h4>";
    
    if (!empty($corrections_applied)) {
        echo "<div class='alert alert-info'>";
        echo "<h6>Après les corrections, vous devrez :</h6>";
        echo "<ol>";
        echo "<li><strong>Mettre à jour les données existantes</strong> avec l'ID de l'école approprié</li>";
        echo "<li><strong>Tester le module de rapports</strong> pour vérifier que tout fonctionne</li>";
        echo "<li><strong>Vérifier les contraintes de clés étrangères</strong> si nécessaire</li>";
        echo "</ol>";
        echo "</div>";
        
        // Script de mise à jour des données
        echo "<h5>📝 Script de Mise à Jour des Données</h5>";
        echo "<div class='alert alert-warning'>";
        echo "<strong>⚠️ Attention :</strong> Exécutez ces requêtes avec précaution sur un environnement de test d'abord !";
        echo "</div>";
        
        foreach ($corrections_applied as $table) {
            echo "<h6>Table <code>$table</code> :</h6>";
            echo "<div class='bg-light p-3 rounded'>";
            echo "<code>";
            echo "-- Mettre à jour tous les enregistrements avec l'ID de l'école par défaut<br>";
            echo "UPDATE $table SET ecole_id = 1 WHERE ecole_id IS NULL;<br>";
            echo "<br>";
            echo "-- Vérifier la mise à jour<br>";
            echo "SELECT COUNT(*) as total, COUNT(ecole_id) as avec_ecole_id FROM $table;<br>";
            echo "</code>";
            echo "</div><br>";
        }
    }
    
    // Liens utiles
    echo "<h4>🔗 Liens Utiles</h4>";
    echo "<div class='d-flex gap-2'>";
    echo "<a href='check_table_structure.php' class='btn btn-info'>";
    echo "<i class='bi bi-search me-2'></i>Vérifier la Structure";
    echo "</a>";
    echo "<a href='index_fixed.php' class='btn btn-success'>";
    echo "<i class='bi bi-graph-up me-2'></i>Tester les Rapports";
    echo "</a>";
    echo "<a href='../index.php' class='btn btn-secondary'>";
    echo "<i class='bi bi-house me-2'></i>Retour à l'Accueil";
    echo "</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<i class='bi bi-x-circle me-2'></i>";
    echo "<strong>Erreur fatale :</strong> " . $e->getMessage();
    echo "</div>";
}

echo "</div>"; // card-body
echo "</div>"; // card
echo "</div>"; // container

echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>";
echo "</body>";
echo "</html>";
?>
