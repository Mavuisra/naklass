<?php
/**
 * Script de vérification et correction des tables financières
 * Exécutez ce script pour vérifier que toutes les colonnes nécessaires existent
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier l'authentification
requireAuth();

echo "<h2>Vérification des tables financières</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier la table paiements
    echo "<h3>1. Vérification de la table 'paiements'</h3>";
    
    $check_columns = [
        'caissier_id' => 'BIGINT NULL',
        'remise_appliquee' => 'DECIMAL(10,2) DEFAULT 0',
        'penalite_retard' => 'DECIMAL(10,4) DEFAULT 0',
        'taux_change' => 'DECIMAL(10,4) DEFAULT 1'
    ];
    
    foreach ($check_columns as $column => $definition) {
        $check_query = "SHOW COLUMNS FROM paiements LIKE :column";
        $stmt = $db->prepare($check_query);
        $stmt->execute(['column' => $column]);
        $exists = $stmt->fetch();
        
        if (!$exists) {
            echo "<p style='color: red;'>❌ Colonne '$column' manquante</p>";
            
            // Ajouter la colonne
            try {
                $add_column_query = "ALTER TABLE paiements ADD COLUMN $column $definition";
                $db->exec($add_column_query);
                echo "<p style='color: green;'>✅ Colonne '$column' ajoutée avec succès</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erreur lors de l'ajout de '$column': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Colonne '$column' existe déjà</p>";
        }
    }
    
    // Vérifier les contraintes de clé étrangère
    echo "<h3>2. Vérification des contraintes de clé étrangère</h3>";
    
    $check_constraints = [
        'fk_paiements_caissier' => 'paiements.caissier_id -> utilisateurs.id'
    ];
    
    foreach ($check_constraints as $constraint_name => $description) {
        $check_constraint_query = "SELECT COUNT(*) as exists FROM information_schema.table_constraints 
                                  WHERE constraint_name = :constraint_name 
                                  AND table_schema = DATABASE()";
        $stmt = $db->prepare($check_constraint_query);
        $stmt->execute(['constraint_name' => $constraint_name]);
        $exists = $stmt->fetch()['exists'] > 0;
        
        if (!$exists) {
            echo "<p style='color: red;'>❌ Contrainte '$constraint_name' manquante</p>";
            
            // Ajouter la contrainte
            try {
                $add_constraint_query = "ALTER TABLE paiements 
                                       ADD CONSTRAINT $constraint_name 
                                       FOREIGN KEY (caissier_id) REFERENCES utilisateurs(id)";
                $db->exec($add_constraint_query);
                echo "<p style='color: green;'>✅ Contrainte '$constraint_name' ajoutée avec succès</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erreur lors de l'ajout de la contrainte: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Contrainte '$constraint_name' existe déjà</p>";
        }
    }
    
    // Vérifier la table types_frais
    echo "<h3>3. Vérification de la table 'types_frais'</h3>";
    
    $check_types_frais = [
        'code' => 'VARCHAR(50) UNIQUE NOT NULL',
        'description' => 'TEXT',
        'montant_standard' => 'DECIMAL(10,2)',
        'monnaie' => "ENUM('CDF', 'USD', 'EUR') DEFAULT 'CDF'",
        'periodicite' => "ENUM('unique', 'mensuel', 'trimestriel', 'semestriel', 'annuel') NOT NULL",
        'remboursable' => 'BOOLEAN DEFAULT FALSE',
        'taxable' => 'BOOLEAN DEFAULT FALSE',
        'actif' => 'BOOLEAN DEFAULT TRUE',
        'statut' => "ENUM('actif', 'archivé', 'supprimé_logique') DEFAULT 'actif'",
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'created_by' => 'BIGINT',
        'updated_by' => 'BIGINT',
        'version' => 'INT DEFAULT 1',
        'notes_internes' => 'TEXT'
    ];
    
    foreach ($check_types_frais as $column => $definition) {
        $check_query = "SHOW COLUMNS FROM types_frais LIKE :column";
        $stmt = $db->prepare($check_query);
        $stmt->execute(['column' => $column]);
        $exists = $stmt->fetch();
        
        if (!$exists) {
            echo "<p style='color: red;'>❌ Colonne '$column' manquante dans types_frais</p>";
            
            try {
                $add_column_query = "ALTER TABLE types_frais ADD COLUMN $column $definition";
                $db->exec($add_column_query);
                echo "<p style='color: green;'>✅ Colonne '$column' ajoutée avec succès</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erreur lors de l'ajout de '$column': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Colonne '$column' existe déjà</p>";
        }
    }
    
    // Vérifier les contraintes de clé étrangère pour types_frais
    echo "<h3>4. Vérification des contraintes de clé étrangère pour types_frais</h3>";
    
    $check_types_frais_constraints = [
        'fk_types_frais_ecole' => 'types_frais.ecole_id -> ecoles.id',
        'fk_types_frais_created_by' => 'types_frais.created_by -> utilisateurs(id',
        'fk_types_frais_updated_by' => 'types_frais.updated_by -> utilisateurs(id'
    ];
    
    foreach ($check_types_frais_constraints as $constraint_name => $description) {
        $check_constraint_query = "SELECT COUNT(*) as exists FROM information_schema.table_constraints 
                                  WHERE constraint_name = :constraint_name 
                                  AND table_schema = DATABASE()";
        $stmt = $db->prepare($check_constraint_query);
        $stmt->execute(['constraint_name' => $constraint_name]);
        $exists = $stmt->fetch()['exists'] > 0;
        
        if (!$exists) {
            echo "<p style='color: red;'>❌ Contrainte '$constraint_name' manquante</p>";
            
            try {
                if (strpos($constraint_name, 'ecole_id') !== false) {
                    $add_constraint_query = "ALTER TABLE types_frais 
                                           ADD CONSTRAINT $constraint_name 
                                           FOREIGN KEY (ecole_id) REFERENCES ecoles(id)";
                } elseif (strpos($constraint_name, 'created_by') !== false) {
                    $add_constraint_query = "ALTER TABLE types_frais 
                                           ADD CONSTRAINT $constraint_name 
                                           FOREIGN KEY (created_by) REFERENCES utilisateurs(id)";
                } elseif (strpos($constraint_name, 'updated_by') !== false) {
                    $add_constraint_query = "ALTER TABLE types_frais 
                                           ADD CONSTRAINT $constraint_name 
                                           FOREIGN KEY (updated_by) REFERENCES utilisateurs(id)";
                }
                
                if (isset($add_constraint_query)) {
                    $db->exec($add_constraint_query);
                    echo "<p style='color: green;'>✅ Contrainte '$constraint_name' ajoutée avec succès</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erreur lors de l'ajout de la contrainte: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Contrainte '$constraint_name' existe déjà</p>";
        }
    }
    
    // Vérifier si la table types_frais a des données et en créer si nécessaire
    echo "<h3>5. Vérification des données types_frais</h3>";
    
    $check_types_frais_data_query = "SELECT COUNT(*) as count FROM types_frais";
    $stmt = $db->prepare($check_types_frais_data_query);
    $stmt->execute();
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        echo "<p style='color: orange;'>⚠️ Aucun type de frais trouvé. Création de types de base...</p>";
        
        try {
            $insert_query = "INSERT INTO types_frais (ecole_id, code, libelle, description, montant_standard, monnaie, periodicite, remboursable, taxable, actif, statut, created_by) VALUES 
                           (:ecole_id, 'FRAIS_INSCRIPTION', 'Frais d\'inscription', 'Frais d\'inscription annuels', 50000, 'CDF', 'annuel', FALSE, FALSE, TRUE, 'actif', :created_by),
                           (:ecole_id, 'FRAIS_MENSUELS', 'Frais mensuels', 'Frais de scolarité mensuels', 25000, 'CDF', 'mensuel', FALSE, FALSE, TRUE, 'actif', :created_by),
                           (:ecole_id, 'FRAIS_UNIFORME', 'Uniforme scolaire', 'Uniforme obligatoire', 15000, 'CDF', 'unique', FALSE, FALSE, TRUE, 'actif', :created_by),
                           (:ecole_id, 'FRAIS_CANTINE', 'Cantine scolaire', 'Service de restauration', 5000, 'CDF', 'mensuel', FALSE, FALSE, TRUE, 'actif', :created_by)";
            
            $stmt = $db->prepare($insert_query);
            $stmt->execute([
                'ecole_id' => $_SESSION['ecole_id'],
                'created_by' => $_SESSION['user_id']
            ]);
            
            echo "<p style='color: green;'>✅ Types de frais de base créés avec succès</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erreur lors de la création des types de frais: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ $count type(s) de frais trouvé(s)</p>";
    }
    
    // Vérifier la table situation_frais
    echo "<h3>6. Vérification de la table 'situation_frais'</h3>";
    
    $check_situation_frais = [
        'en_retard' => 'BOOLEAN DEFAULT FALSE',
        'derniere_operation_at' => 'DATETIME NULL'
    ];
    
    foreach ($check_situation_frais as $column => $definition) {
        $check_query = "SHOW COLUMNS FROM situation_frais LIKE :column";
        $stmt = $db->prepare($check_query);
        $stmt->execute(['column' => $column]);
        $exists = $stmt->fetch();
        
        if (!$exists) {
            echo "<p style='color: red;'>❌ Colonne '$column' manquante dans situation_frais</p>";
            
            try {
                $add_column_query = "ALTER TABLE situation_frais ADD COLUMN $column $definition";
                $db->exec($add_column_query);
                echo "<p style='color: green;'>✅ Colonne '$column' ajoutée avec succès</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Erreur lors de l'ajout de '$column': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Colonne '$column' existe déjà</p>";
        }
    }
    
    echo "<h3>✅ Vérification terminée !</h3>";
    echo "<p>Si toutes les colonnes sont marquées comme existantes, votre base de données est à jour.</p>";
    echo "<p><a href='finance/'>Retour à la gestion financière</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur lors de la vérification: " . $e->getMessage() . "</p>";
}
?>
