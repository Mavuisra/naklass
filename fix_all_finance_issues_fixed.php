<?php
/**
 * Script de correction rapide pour tous les problèmes financiers (VERSION CORRIGÉE)
 * Exécutez ce script pour corriger automatiquement tous les problèmes de colonnes manquantes
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier l'authentification
requireAuth();

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction des problèmes financiers - " . APP_NAME . "</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
    </style>
</head>
<body>";

echo "<div class='container'>
    <div class='row justify-content-center'>
        <div class='col-md-10'>
            <div class='card'>
                <div class='card-header bg-primary text-white'>
                    <h2>🔧 Correction automatique des problèmes financiers</h2>
                </div>
                <div class='card-body'>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='alert alert-info'>
        <strong>🔧 Début de la correction automatique...</strong><br>
        Ce script va vérifier et corriger automatiquement tous les problèmes de colonnes manquantes dans vos tables financières.
    </div>";
    
    // 1. CORRECTION TABLE PAIEMENTS
    echo "<h3 class='text-primary'>1. Correction de la table 'paiements'</h3>";
    
    $paiements_columns = [
        'caissier_id' => 'BIGINT NULL',
        'remise_appliquee' => 'DECIMAL(10,2) DEFAULT 0',
        'penalite_retard' => 'DECIMAL(10,2) DEFAULT 0',
        'taux_change' => 'DECIMAL(10,4) DEFAULT 1'
    ];
    
    foreach ($paiements_columns as $column => $definition) {
        $check_query = "SHOW COLUMNS FROM paiements LIKE '$column'";
        $stmt = $db->prepare($check_query);
        $stmt->execute();
        $exists = $stmt->fetch();
        
        if (!$exists) {
            echo "<p class='error'>❌ Colonne '$column' manquante dans paiements</p>";
            
            try {
                $add_column_query = "ALTER TABLE paiements ADD COLUMN `$column` $definition";
                $db->exec($add_column_query);
                echo "<p class='success'>✅ Colonne '$column' ajoutée avec succès</p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Erreur lors de l'ajout de '$column': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='success'>✅ Colonne '$column' existe déjà</p>";
        }
    }
    
    // 2. CORRECTION TABLE TYPES_FRAIS
    echo "<h3 class='text-primary'>2. Correction de la table 'types_frais'</h3>";
    
    // Ajouter les colonnes une par une pour éviter les problèmes de syntaxe
    $types_frais_columns = [
        'code' => 'VARCHAR(50)',
        'description' => 'TEXT',
        'montant_standard' => 'DECIMAL(10,2)',
        'monnaie' => "ENUM('CDF', 'USD', 'EUR')",
        'periodicite' => "ENUM('unique', 'mensuel', 'trimestriel', 'semestriel', 'annuel')",
        'remboursable' => 'TINYINT(1)',
        'taxable' => 'TINYINT(1)',
        'actif' => 'TINYINT(1)',
        'statut' => "ENUM('actif', 'archive', 'supprime')",
        'created_at' => 'TIMESTAMP',
        'updated_at' => 'TIMESTAMP',
        'created_by' => 'BIGINT',
        'updated_by' => 'BIGINT',
        'version' => 'INT',
        'notes_internes' => 'TEXT'
    ];
    
    foreach ($types_frais_columns as $column => $definition) {
        $check_query = "SHOW COLUMNS FROM types_frais LIKE '$column'";
        $stmt = $db->prepare($check_query);
        $stmt->execute();
        $exists = $stmt->fetch();
        
        if (!$exists) {
            echo "<p class='error'>❌ Colonne '$column' manquante dans types_frais</p>";
            
            try {
                // Ajouter la colonne sans valeurs par défaut problématiques
                $add_column_query = "ALTER TABLE types_frais ADD COLUMN `$column` $definition";
                $db->exec($add_column_query);
                
                // Ajouter les valeurs par défaut après si nécessaire
                if ($column == 'monnaie') {
                    $db->exec("ALTER TABLE types_frais ALTER COLUMN `$column` SET DEFAULT 'CDF'");
                } elseif ($column == 'periodicite') {
                    $db->exec("ALTER TABLE types_frais ALTER COLUMN `$column` SET DEFAULT 'mensuel'");
                } elseif (in_array($column, ['remboursable', 'taxable', 'actif'])) {
                    $db->exec("ALTER TABLE types_frais ALTER COLUMN `$column` SET DEFAULT 0");
                } elseif ($column == 'statut') {
                    $db->exec("ALTER TABLE types_frais ALTER COLUMN `$column` SET DEFAULT 'actif'");
                } elseif ($column == 'version') {
                    $db->exec("ALTER TABLE types_frais ALTER COLUMN `$column` SET DEFAULT 1");
                } elseif ($column == 'created_at') {
                    $db->exec("ALTER TABLE types_frais ALTER COLUMN `$column` SET DEFAULT CURRENT_TIMESTAMP");
                } elseif ($column == 'updated_at') {
                    $db->exec("ALTER TABLE types_frais ALTER COLUMN `$column` SET DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                }
                
                echo "<p class='success'>✅ Colonne '$column' ajoutée avec succès</p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Erreur lors de l'ajout de '$column': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='success'>✅ Colonne '$column' existe déjà</p>";
        }
    }
    
    // 3. CORRECTION TABLE SITUATION_FRAIS
    echo "<h3 class='text-primary'>3. Correction de la table 'situation_frais'</h3>";
    
    $situation_frais_columns = [
        'en_retard' => 'TINYINT(1)',
        'derniere_operation_at' => 'DATETIME'
    ];
    
    foreach ($situation_frais_columns as $column => $definition) {
        $check_query = "SHOW COLUMNS FROM situation_frais LIKE '$column'";
        $stmt = $db->prepare($check_query);
        $stmt->execute();
        $exists = $stmt->fetch();
        
        if (!$exists) {
            echo "<p class='error'>❌ Colonne '$column' manquante dans situation_frais</p>";
            
            try {
                $add_column_query = "ALTER TABLE situation_frais ADD COLUMN `$column` $definition";
                $db->exec($add_column_query);
                
                if ($column == 'en_retard') {
                    $db->exec("ALTER TABLE situation_frais ALTER COLUMN `$column` SET DEFAULT 0");
                }
                
                echo "<p class='success'>✅ Colonne '$column' ajoutée avec succès</p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Erreur lors de l'ajout de '$column': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='success'>✅ Colonne '$column' existe déjà</p>";
        }
    }
    
    // 4. AJOUT DES CONTRAINTES DE CLÉ ÉTRANGÈRE
    echo "<h3 class='text-primary'>4. Ajout des contraintes de clé étrangère</h3>";
    
    $constraints = [
        'fk_paiements_caissier' => "ALTER TABLE paiements ADD CONSTRAINT fk_paiements_caissier FOREIGN KEY (caissier_id) REFERENCES utilisateurs(id)",
        'fk_types_frais_ecole' => "ALTER TABLE types_frais ADD CONSTRAINT fk_types_frais_ecole FOREIGN KEY (ecole_id) REFERENCES ecoles(id)",
        'fk_types_frais_created_by' => "ALTER TABLE types_frais ADD CONSTRAINT fk_types_frais_created_by FOREIGN KEY (created_by) REFERENCES utilisateurs(id)",
        'fk_types_frais_updated_by' => "ALTER TABLE types_frais ADD CONSTRAINT fk_types_frais_updated_by FOREIGN KEY (updated_by) REFERENCES utilisateurs(id)"
    ];
    
    foreach ($constraints as $constraint_name => $sql) {
        try {
            $check_constraint_query = "SELECT COUNT(*) as exists FROM information_schema.table_constraints 
                                      WHERE constraint_name = '$constraint_name' 
                                      AND table_schema = DATABASE()";
            $stmt = $db->prepare($check_constraint_query);
            $stmt->execute();
            $exists = $stmt->fetch()['exists'] > 0;
            
            if (!$exists) {
                $db->exec($sql);
                echo "<p class='success'>✅ Contrainte '$constraint_name' ajoutée avec succès</p>";
            } else {
                echo "<p class='success'>✅ Contrainte '$constraint_name' existe déjà</p>";
            }
        } catch (Exception $e) {
            echo "<p class='warning'>⚠️ Contrainte '$constraint_name': " . $e->getMessage() . "</p>";
        }
    }
    
    // 5. CRÉATION DES DONNÉES DE BASE
    echo "<h3 class='text-primary'>5. Création des données de base</h3>";
    
    // Vérifier si types_frais a des données
    $check_data_query = "SELECT COUNT(*) as count FROM types_frais";
    $stmt = $db->prepare($check_data_query);
    $stmt->execute();
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        echo "<p class='warning'>⚠️ Aucun type de frais trouvé. Création de types de base...</p>";
        
        try {
            $insert_query = "INSERT INTO types_frais (ecole_id, code, libelle, description, montant_standard, monnaie, periodicite, remboursable, taxable, actif, statut, created_by) VALUES 
                           (?, 'FRAIS_INSCRIPTION', 'Frais d''inscription', 'Frais d''inscription annuels', 50000, 'CDF', 'annuel', 0, 0, 1, 'actif', ?)";
            
            $stmt = $db->prepare($insert_query);
            $stmt->execute([$_SESSION['ecole_id'], $_SESSION['user_id']]);
            
            $insert_query = "INSERT INTO types_frais (ecole_id, code, libelle, description, montant_standard, monnaie, periodicite, remboursable, taxable, actif, statut, created_by) VALUES 
                           (?, 'FRAIS_MENSUELS', 'Frais mensuels', 'Frais de scolarite mensuels', 25000, 'CDF', 'mensuel', 0, 0, 1, 'actif', ?)";
            
            $stmt = $db->prepare($insert_query);
            $stmt->execute([$_SESSION['ecole_id'], $_SESSION['user_id']]);
            
            $insert_query = "INSERT INTO types_frais (ecole_id, code, libelle, description, montant_standard, monnaie, periodicite, remboursable, taxable, actif, statut, created_by) VALUES 
                           (?, 'FRAIS_UNIFORME', 'Uniforme scolaire', 'Uniforme obligatoire', 15000, 'CDF', 'unique', 0, 0, 1, 'actif', ?)";
            
            $stmt = $db->prepare($insert_query);
            $stmt->execute([$_SESSION['ecole_id'], $_SESSION['user_id']]);
            
            $insert_query = "INSERT INTO types_frais (ecole_id, code, libelle, description, montant_standard, monnaie, periodicite, remboursable, taxable, actif, statut, created_by) VALUES 
                           (?, 'FRAIS_CANTINE', 'Cantine scolaire', 'Service de restauration', 5000, 'CDF', 'mensuel', 0, 0, 1, 'actif', ?)";
            
            $stmt = $db->prepare($insert_query);
            $stmt->execute([$_SESSION['ecole_id'], $_SESSION['user_id']]);
            
            echo "<p class='success'>✅ Types de frais de base créés avec succès</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erreur lors de la création des types de frais: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='success'>✅ $count type(s) de frais trouvé(s)</p>";
    }
    
    echo "<div class='alert alert-success mt-4'>
        <h4>🎉 Correction terminée avec succès !</h4>
        <p>Tous les problèmes de colonnes manquantes ont été corrigés. Votre système financier devrait maintenant fonctionner correctement.</p>
    </div>";
    
    echo "<div class='d-grid gap-2 d-md-flex justify-content-md-center'>
        <a href='finance/' class='btn btn-primary me-md-2'>📊 Aller à la gestion financière</a>
        <a href='finance/payment.php' class='btn btn-success me-md-2'>💰 Nouveau paiement</a>
        <a href='auth/dashboard.php' class='btn btn-secondary'>🏠 Retour au tableau de bord</a>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
        <h4>❌ Erreur lors de la correction</h4>
        <p>" . $e->getMessage() . "</p>
    </div>";
}

echo "</div></div></div></div></body></html>";
?>
