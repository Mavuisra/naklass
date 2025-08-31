<?php
/**
 * Script de diagnostic pour vérifier le statut de session actuel
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h1>🔍 Diagnostic de Session Naklass</h1>";

// 1. Informations de session PHP
echo "<h3>1. Informations de Session PHP</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session status: " . session_status() . " (1=disabled, 2=active, 3=none)<br>";
echo "Session cookie lifetime: " . ini_get('session.cookie_lifetime') . " secondes<br>";
echo "Session GC max lifetime: " . ini_get('session.gc_maxlifetime') . " secondes<br>";

// 2. Contenu de la session
echo "<h3>2. Contenu de la Session</h3>";
if (isset($_SESSION) && !empty($_SESSION)) {
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    echo "<strong>Variables de session trouvées:</strong><br>";
    foreach ($_SESSION as $key => $value) {
        echo "- <code>$key</code>: " . htmlspecialchars($value) . "<br>";
    }
    echo "</div>";
} else {
    echo "❌ Aucune variable de session trouvée<br>";
}

// 3. Test des fonctions de vérification
echo "<h3>3. Tests de Vérification</h3>";
echo "isLoggedIn(): " . (isLoggedIn() ? "✅ TRUE" : "❌ FALSE") . "<br>";

if (isLoggedIn()) {
    echo "hasRole('admin'): " . (hasRole('admin') ? "✅ TRUE" : "❌ FALSE") . "<br>";
    echo "User ID en session: " . ($_SESSION['user_id'] ?? 'N/A') . "<br>";
    echo "École ID en session: " . ($_SESSION['ecole_id'] ?? 'N/A') . "<br>";
    echo "Rôle en session: " . ($_SESSION['user_role'] ?? 'N/A') . "<br>";
}

// 4. Vérification dans la base de données
if (isset($_SESSION['user_id'])) {
    echo "<h3>4. Vérification Base de Données</h3>";
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT u.*, e.nom_ecole as ecole_nom, r.code as role_code 
                  FROM utilisateurs u 
                  LEFT JOIN ecoles e ON u.ecole_id = e.id 
                  LEFT JOIN roles r ON u.role_id = r.id 
                  WHERE u.id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "✅ Utilisateur trouvé dans la base:<br>";
            echo "- Nom: " . htmlspecialchars($user['prenom'] . ' ' . $user['nom']) . "<br>";
            echo "- Email: " . htmlspecialchars($user['email']) . "<br>";
            echo "- École: " . htmlspecialchars($user['ecole_nom'] ?? 'N/A') . "<br>";
            echo "- Rôle: " . htmlspecialchars($user['role_code'] ?? 'N/A') . "<br>";
            echo "- Actif: " . ($user['actif'] ? "Oui" : "Non") . "<br>";
            echo "- Dernière connexion: " . ($user['derniere_connexion_at'] ?? 'Jamais') . "<br>";
        } else {
            echo "❌ Utilisateur non trouvé dans la base (ID: " . $_SESSION['user_id'] . ")<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "<br>";
    }
}

// 5. Cookies
echo "<h3>5. Cookies</h3>";
if (isset($_COOKIE) && !empty($_COOKIE)) {
    echo "Cookies trouvés:<br>";
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'PHPSESSID') !== false || strpos($name, 'naklass') !== false) {
            echo "- <code>$name</code>: " . htmlspecialchars(substr($value, 0, 20)) . "...<br>";
        }
    }
} else {
    echo "Aucun cookie trouvé<br>";
}

// 6. Actions possibles
echo "<h3>6. Actions Possibles</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Pour forcer une déconnexion :</h4>";
echo "<a href='force_logout.php' class='btn' style='background: #dc3545; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🚪 Forcer la Déconnexion</a>";
echo "</div>";

echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Pour tester la connexion normale :</h4>";
echo "<a href='auth/login.php' class='btn' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🔐 Page de Connexion</a>";
echo "</div>";

echo "<hr>";
echo "<p><em>Supprimez ce fichier après diagnostic pour des raisons de sécurité.</em></p>";
?>
