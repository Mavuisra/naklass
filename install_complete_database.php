<?php
/**
 * Script d'installation complète de la base de données Naklass
 * Crée toutes les tables nécessaires pour le système
 */

require_once 'config/database.php';

echo "<h1>Installation Complète Base de Données Naklass</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Connexion à la base de données réussie<br><br>";
    
    // Liste des fichiers SQL à exécuter dans l'ordre
    $sql_files = [
        'database/01_metadonnees.sql',
        'database/02_module_inscription.sql',
        'database/03_module_paiement.sql',
        'database/04_module_notes_bulletins.sql',
        'database/05_periodes_referentiels.sql',
        'database/06_vues_utiles.sql',
        'database/07_module_classes.sql',
        'database/08_module_periodes_scolaires.sql'
    ];
    
    echo "<h3>Installation des modules de base de données:</h3>";
    
    foreach ($sql_files as $sql_file) {
        if (file_exists($sql_file)) {
            echo "<h4>Exécution de: $sql_file</h4>";
            
            $sql_content = file_get_contents($sql_file);
            
            // Supprimer les commentaires et diviser en requêtes
            $sql_content = preg_replace('/--.*$/m', '', $sql_content);
            $queries = array_filter(array_map('trim', explode(';', $sql_content)));
            
            $success_count = 0;
            $error_count = 0;
            
            foreach ($queries as $query) {
                if (!empty($query) && !preg_match('/^\s*(USE|SELECT)/i', $query)) {
                    try {
                        $db->exec($query);
                        $success_count++;
                    } catch (PDOException $e) {
                        $error_count++;
                        echo "⚠️ Avertissement: " . $e->getMessage() . "<br>";
                    }
                }
            }
            
            echo "✅ Requêtes réussies: $success_count, Avertissements: $error_count<br><br>";
            
        } else {
            echo "❌ Fichier non trouvé: $sql_file<br><br>";
        }
    }
    
    // Vérifier que les tables importantes ont été créées
    echo "<h3>Vérification des tables créées:</h3>";
    
    $required_tables = [
        'ecoles',
        'utilisateurs', 
        'roles',
        'annees_scolaires',
        'periodes_scolaires',
        'classes',
        'eleves',
        'evaluations',
        'notes'
    ];
    
    foreach ($required_tables as $table) {
        try {
            $query = "SHOW TABLES LIKE '$table'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                echo "✅ Table '$table' existe<br>";
            } else {
                echo "❌ Table '$table' manquante<br>";
            }
        } catch (Exception $e) {
            echo "❌ Erreur vérification table '$table': " . $e->getMessage() . "<br>";
        }
    }
    
    // Créer une école par défaut si elle n'existe pas
    echo "<br><h3>Configuration de l'école par défaut:</h3>";
    
    try {
        $query = "SELECT COUNT(*) as count FROM ecoles";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $insert_ecole = "INSERT INTO ecoles (nom_ecole, code_ecole, type_etablissement, pays, statut) 
                             VALUES ('École Naklass', 'NAK001', 'mixte', 'RD Congo', 'actif')";
            $db->exec($insert_ecole);
            echo "✅ École par défaut créée<br>";
        } else {
            echo "✅ École(s) déjà existante(s)<br>";
        }
    } catch (Exception $e) {
        echo "⚠️ Avertissement création école: " . $e->getMessage() . "<br>";
    }
    
    // Créer un utilisateur admin par défaut
    echo "<br><h3>Configuration de l'utilisateur admin:</h3>";
    
    try {
        $query = "SELECT COUNT(*) as count FROM utilisateurs WHERE email = 'admin@naklass.cd'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // Créer le rôle admin s'il n'existe pas
            $check_role = "SELECT COUNT(*) as count FROM roles WHERE code = 'admin'";
            $stmt = $db->prepare($check_role);
            $stmt->execute();
            $role_result = $stmt->fetch();
            
            if ($role_result['count'] == 0) {
                $insert_role = "INSERT INTO roles (code, libelle, description, niveau_hierarchie) 
                               VALUES ('admin', 'Administrateur', 'Administrateur système complet', 1)";
                $db->exec($insert_role);
                echo "✅ Rôle admin créé<br>";
            }
            
            // Récupérer l'ID de l'école et du rôle
            $ecole_query = "SELECT id FROM ecoles LIMIT 1";
            $stmt = $db->prepare($ecole_query);
            $stmt->execute();
            $ecole = $stmt->fetch();
            
            $role_query = "SELECT id FROM roles WHERE code = 'admin'";
            $stmt = $db->prepare($role_query);
            $stmt->execute();
            $role = $stmt->fetch();
            
            if ($ecole && $role) {
                $password_hash = password_hash('password', PASSWORD_DEFAULT);
                $insert_admin = "INSERT INTO utilisateurs (ecole_id, role_id, nom, prenom, email, mot_de_passe_hash, actif) 
                                VALUES ({$ecole['id']}, {$role['id']}, 'Admin', 'Système', 'admin@naklass.cd', '$password_hash', 1)";
                $db->exec($insert_admin);
                echo "✅ Utilisateur admin créé<br>";
            }
        } else {
            echo "✅ Utilisateur admin déjà existant<br>";
        }
    } catch (Exception $e) {
        echo "⚠️ Avertissement création admin: " . $e->getMessage() . "<br>";
    }
    
    echo "<br><h3>Installation terminée!</h3>";
    echo "<p><strong>Étapes suivantes:</strong></p>";
    echo "<ol>";
    echo "<li>Supprimez ce fichier install_complete_database.php</li>";
    echo "<li>Connectez-vous avec admin@naklass.cd / password</li>";
    echo "<li>Changez le mot de passe par défaut</li>";
    echo "<li>Configurez votre école et vos données</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
    echo "<p>Vérifiez votre configuration dans config/database.php</p>";
}
?>
