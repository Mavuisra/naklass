<?php
// Script de nettoyage des doublons dans classe_cours
session_start();

// Vérifier les permissions (admin seulement)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Accès refusé. Seuls les administrateurs peuvent exécuter ce script.");
}

echo "<h1>🧹 Nettoyage des Doublons - classe_cours</h1>";

if (file_exists('../config/database.php')) {
    require_once '../config/database.php';
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        echo "<p style='color: green;'>✅ Connexion base de données réussie</p>";
        
        // Commencer une transaction
        $db->beginTransaction();
        
        echo "<hr>";
        echo "<h2>🔍 Analyse des Doublons</h2>";
        
        // Identifier les doublons
        $duplicates_query = "SELECT classe_id, cours_id, enseignant_id, COUNT(*) as nb_occurrences,
                                   GROUP_CONCAT(id ORDER BY id) as ids
                            FROM classe_cours 
                            WHERE statut = 'actif'
                            GROUP BY classe_id, cours_id, enseignant_id
                            HAVING COUNT(*) > 1
                            ORDER BY nb_occurrences DESC";
        
        $stmt = $db->prepare($duplicates_query);
        $stmt->execute();
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($duplicates)) {
            echo "<p style='color: green;'>✅ Aucun doublon détecté. Aucune action nécessaire.</p>";
            $db->rollBack();
        } else {
            echo "<p style='color: red;'>❌ {$duplicates_query} doublons détectés :</p>";
            
            $total_deleted = 0;
            
            foreach ($duplicates as $dup) {
                echo "<p><strong>Groupe de doublons :</strong> Classe={$dup['classe_id']}, Cours={$dup['cours_id']}, Enseignant={$dup['enseignant_id']}</p>";
                
                // Récupérer tous les IDs pour ce groupe
                $ids = explode(',', $dup['ids']);
                $keep_id = $ids[0]; // Garder le premier (plus ancien)
                $delete_ids = array_slice($ids, 1); // Supprimer les autres
                
                echo "<p>Garder l'ID : {$keep_id}</p>";
                echo "<p>Supprimer les IDs : " . implode(', ', $delete_ids) . "</p>";
                
                // Supprimer les doublons (garder seulement le premier)
                $delete_query = "DELETE FROM classe_cours WHERE id IN (" . implode(',', $delete_ids) . ")";
                $stmt = $db->prepare($delete_query);
                $stmt->execute();
                
                $deleted_count = count($delete_ids);
                $total_deleted += $deleted_count;
                
                echo "<p style='color: green;'>✅ {$deleted_count} enregistrements supprimés</p>";
            }
            
            // Valider la transaction
            $db->commit();
            
            echo "<hr>";
            echo "<h2>✅ Nettoyage Terminé</h2>";
            echo "<p style='color: green;'>✅ Total des enregistrements supprimés : {$total_deleted}</p>";
            
            // Vérifier qu'il n'y a plus de doublons
            echo "<hr>";
            echo "<h2>🔍 Vérification Post-Nettoyage</h2>";
            
            $verify_query = "SELECT classe_id, cours_id, enseignant_id, COUNT(*) as nb_occurrences
                            FROM classe_cours 
                            WHERE statut = 'actif'
                            GROUP BY classe_id, cours_id, enseignant_id
                            HAVING COUNT(*) > 1
                            ORDER BY nb_occurrences DESC";
            
            $stmt = $db->prepare($verify_query);
            $stmt->execute();
            $remaining_duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($remaining_duplicates)) {
                echo "<p style='color: green;'>✅ Aucun doublon restant. Nettoyage réussi !</p>";
            } else {
                echo "<p style='color: red;'>❌ Il reste encore des doublons :</p>";
                foreach ($remaining_duplicates as $dup) {
                    echo "<p>Classe={$dup['classe_id']}, Cours={$dup['cours_id']}, Enseignant={$dup['enseignant_id']} : {$dup['nb_occurrences']} occurrences</p>";
                }
            }
        }
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
        echo "<p style='color: red;'>❌ Transaction annulée. Aucune modification n'a été effectuée.</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Fichier de configuration non trouvé</p>";
}

echo "<hr>";
echo "<h2>📋 Instructions</h2>";
echo "<p>Ce script supprime les doublons dans la table classe_cours en gardant seulement le premier enregistrement de chaque groupe.</p>";
echo "<p><strong>⚠️ ATTENTION :</strong> Ce script modifie définitivement la base de données. Assurez-vous d'avoir une sauvegarde avant de l'exécuter.</p>";

echo "<p><a href='debug_duplication.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔍 Diagnostic</a>";
echo "<a href='my_classes.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Retour à Mes Classes</a></p>";
?>














