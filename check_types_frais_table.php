<?php
/**
 * Script de vérification et correction de la table types_frais
 * Exécutez ce script pour vérifier que toutes les colonnes nécessaires existent
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier l'authentification
requireAuth();

echo "<h2>Vérification de la table types_frais</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier la table types_frais
    echo "<h3>1. Vérification de la table 'types_frais'</h3>";
    
    $check_columns = [
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
    
    foreach ($check_columns as $column => $definition) {
        $check_query = "SHOW COLUMNS FROM types_frais LIKE :column";
        $stmt = $db->prepare($check_query);
        $stmt->execute(['column' => $column]);
        $exists = $stmt->fetch();
        
        if (!$exists) {
            echo "<p style='color: red;'>❌ Colonne '$column' manquante</p>";
            
            // Ajouter la colonne
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
    
    // Vérifier les contraintes de clé étrangère
    echo "<h3>2. Vérification des contraintes de clé étrangère</h3>";
    
    $check_constraints = [
        'fk_types_frais_ecole' => 'types_frais.ecole_id -> ecoles.id',
        'fk_types_frais_created_by' => 'types_frais.created_by -> utilisateurs.id',
        'fk_types_frais_updated_by' => 'types_frais.updated_by -> utilisateurs.id'
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
            
            // Ajouter la contrainte (seulement si les colonnes existent)
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
    
    // Vérifier si la table existe et créer des données de test si nécessaire
    echo "<h3>3. Vérification des données de test</h3>";
    
    $check_data_query = "SELECT COUNT(*) as count FROM types_frais";
    $stmt = $db->prepare($check_data_query);
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
    
    echo "<h3>✅ Vérification terminée !</h3>";
    echo "<p>Si toutes les colonnes sont marquées comme existantes, votre table types_frais est à jour.</p>";
    echo "<p><a href='finance/payment.php'>Retour au formulaire de paiement</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur lors de la vérification: " . $e->getMessage() . "</p>";
}
?>
