<?php
/**
 * Script de configuration rapide pour Naklass
 * Résout les problèmes de colonnes et initialise les données de base
 */

require_once 'config/database.php';

echo "<h1>🚀 Configuration Rapide Naklass</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Connexion réussie<br><br>";
    
    // 1. Créer l'école par défaut
    echo "<h3>1. Configuration de l'école</h3>";
    $query = "INSERT IGNORE INTO ecoles (id, nom, adresse, telephone, email, directeur_nom) 
              VALUES (1, 'École Naklass Démo', '123 Avenue de l\'Éducation, Kinshasa', '+243 123 456 789', 'contact@naklass.cd', 'Directeur Naklass')";
    $db->exec($query);
    echo "✅ École par défaut créée<br>";
    
    // 2. Créer les rôles
    echo "<h3>2. Configuration des rôles</h3>";
    $roles = [
        [1, 'admin', 'Administrateur', '{"all": true}'],
        [2, 'direction', 'Direction', '{"gestion_eleves": true, "gestion_enseignants": true, "validation_bulletins": true, "rapports": true}'],
        [3, 'enseignant', 'Enseignant', '{"saisie_notes": true, "consultation_eleves": true, "generation_bulletins": true}'],
        [4, 'secretaire', 'Secrétaire', '{"gestion_inscriptions": true, "gestion_paiements": true, "consultation_eleves": true}'],
        [5, 'caissier', 'Caissier', '{"gestion_paiements": true, "consultation_eleves": true}']
    ];
    
    foreach ($roles as $role) {
        $query = "INSERT IGNORE INTO roles (id, code, libelle, permissions) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute($role);
    }
    echo "✅ Rôles créés<br>";
    
    // 3. Créer l'utilisateur admin
    echo "<h3>3. Configuration utilisateur admin</h3>";
    $query = "INSERT IGNORE INTO utilisateurs (id, ecole_id, nom, prenom, email, mot_de_passe_hash, role_id, created_by) 
              VALUES (1, 1, 'Admin', 'Système', 'admin@naklass.cd', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1)";
    $db->exec($query);
    
    // Mettre à jour au cas où il existait déjà sans ecole_id
    $query = "UPDATE utilisateurs SET ecole_id = 1, role_id = 1 WHERE email = 'admin@naklass.cd' AND (ecole_id IS NULL OR role_id IS NULL)";
    $db->exec($query);
    echo "✅ Utilisateur admin configuré<br>";
    
    // 4. Vérification finale
    echo "<h3>4. Vérification</h3>";
    $query = "SELECT u.id, u.nom, u.prenom, u.email, u.ecole_id, u.role_id, e.nom_ecole as ecole_nom, r.code as role_code
              FROM utilisateurs u 
              JOIN ecoles e ON u.ecole_id = e.id 
              JOIN roles r ON u.role_id = r.id 
              WHERE u.email = 'admin@naklass.cd'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin && $admin['ecole_id']) {
        echo "✅ <strong>Configuration réussie!</strong><br>";
        echo "📧 Email: admin@naklass.cd<br>";
        echo "🔐 Mot de passe: password<br>";
        echo "🏫 École: " . htmlspecialchars($admin['ecole_nom']) . "<br>";
        echo "👤 Rôle: " . htmlspecialchars($admin['role_code']) . "<br><br>";
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4>🎉 Installation terminée avec succès!</h4>";
        echo "<p><strong>Prochaines étapes:</strong></p>";
        echo "<ol>";
        echo "<li>Supprimez ce fichier (quick_setup.php)</li>";
        echo "<li>Supprimez debug_session.php et install_database.php</li>";
        echo "<li><a href='auth/login.php'>Connectez-vous maintenant</a></li>";
        echo "</ol>";
        echo "</div>";
        
    } else {
        echo "❌ Problème lors de la configuration<br>";
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Erreur:</strong> " . $e->getMessage() . "<br>";
    echo "<p>Vérifiez votre configuration dans config/database.php</p>";
    echo "<p>Assurez-vous que MySQL est démarré et que la base naklass_db existe.</p>";
}

echo "<hr>";
echo "<p><em>Script généré automatiquement - Supprimez après utilisation</em></p>";
?>
