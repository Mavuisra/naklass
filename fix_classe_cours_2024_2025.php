<?php
require_once 'config/database.php';

echo "=== CRÉATION CLASSE_COURS POUR 2024-2025 ===\n\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Vérifier l'année 2024-2025
    $stmt = $conn->prepare("SELECT id, libelle, ecole_id FROM annees_scolaires WHERE libelle = '2024-2025'");
    $stmt->execute();
    $annee_2024_2025 = $stmt->fetch();
    
    if (!$annee_2024_2025) {
        echo "❌ Année 2024-2025 non trouvée !\n";
        exit;
    }
    
    echo "✅ Année 2024-2025 trouvée: ID {$annee_2024_2025['id']}, École {$annee_2024_2025['ecole_id']}\n";
    
    // Récupérer les classes pour 2024-2025
    $stmt = $conn->prepare("SELECT id, nom_classe, ecole_id FROM classes WHERE annee_scolaire = '2024-2025'");
    $stmt->execute();
    $classes_2024_2025 = $stmt->fetchAll();
    
    echo "📊 Classes trouvées pour 2024-2025: " . count($classes_2024_2025) . "\n";
    
    foreach ($classes_2024_2025 as $classe) {
        echo "- Classe: {$classe['nom_classe']} (ID: {$classe['id']}, École: {$classe['ecole_id']})\n";
    }
    
    // Récupérer les cours pour chaque école
    $stmt = $conn->prepare("SELECT id, nom_cours, ecole_id FROM cours WHERE ecole_id = :ecole_id");
    
    $total_created = 0;
    
    foreach ($classes_2024_2025 as $classe) {
        echo "\n🔍 Traitement de la classe: {$classe['nom_classe']}\n";
        
        // Récupérer les cours de cette école
        $stmt->execute(['ecole_id' => $classe['ecole_id']]);
        $cours_ecole = $stmt->fetchAll();
        
        echo "📚 Cours disponibles pour l'école {$classe['ecole_id']}: " . count($cours_ecole) . "\n";
        
        foreach ($cours_ecole as $cours) {
            echo "  - {$cours['nom_cours']}\n";
            
            // Vérifier si ce classe_cours existe déjà
            $check_stmt = $conn->prepare("SELECT COUNT(*) as total FROM classe_cours 
                                        WHERE classe_id = :classe_id AND cours_id = :cours_id AND annee_scolaire = '2024-2025'");
            $check_stmt->execute([
                'classe_id' => $classe['id'],
                'cours_id' => $cours['id']
            ]);
            $exists = $check_stmt->fetch();
            
            if ($exists['total'] == 0) {
                // Créer le classe_cours
                $insert_stmt = $conn->prepare("INSERT INTO classe_cours (classe_id, cours_id, annee_scolaire, statut, created_at, updated_at) 
                                             VALUES (:classe_id, :cours_id, '2024-2025', 'actif', NOW(), NOW())");
                
                $insert_stmt->execute([
                    'classe_id' => $classe['id'],
                    'cours_id' => $cours['id']
                ]);
                
                echo "  ✅ Créé: {$classe['nom_classe']} + {$cours['nom_cours']}\n";
                $total_created++;
            } else {
                echo "  ⚠️ Existe déjà: {$classe['nom_classe']} + {$cours['nom_cours']}\n";
            }
        }
    }
    
    echo "\n🎉 RÉSULTAT FINAL:\n";
    echo "✅ {$total_created} classe_cours créés pour l'année 2024-2025\n";
    
    // Vérifier le résultat
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM classe_cours WHERE annee_scolaire = '2024-2025'");
    $stmt->execute();
    $count = $stmt->fetch();
    
    echo "📊 Total classe_cours pour 2024-2025: {$count['total']}\n";
    
    if ($count['total'] > 0) {
        echo "\n🎯 MAINTENANT vous pouvez accéder à:\n";
        echo "http://localhost/naklass/grades/evaluations.php?annee_id=2\n";
        echo "✅ Plus de message 'Aucune classe/cours trouvée' !\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . "\n";
    echo "Ligne: " . $e->getLine() . "\n";
}
?>
