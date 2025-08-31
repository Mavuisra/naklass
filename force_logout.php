<?php
/**
 * Script pour forcer une déconnexion complète
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h1>🚪 Déconnexion Forcée</h1>";

// Détruire complètement la session
if (session_status() !== PHP_SESSION_NONE) {
    // Vider toutes les variables de session
    $_SESSION = array();
    
    // Supprimer le cookie de session si il existe
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Détruire la session
    session_destroy();
    
    echo "✅ Session détruite<br>";
} else {
    echo "ℹ️ Aucune session active<br>";
}

// Supprimer tous les cookies liés à l'application
if (isset($_COOKIE)) {
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'PHPSESSID') !== false || strpos($name, 'naklass') !== false) {
            setcookie($name, '', time() - 3600, '/');
            echo "🍪 Cookie '$name' supprimé<br>";
        }
    }
}

echo "<br><div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<h3>✅ Déconnexion Complète Effectuée</h3>";
echo "<p>Toutes les sessions et cookies ont été supprimés.</p>";
echo "<p><strong>Vous pouvez maintenant tester la connexion normale :</strong></p>";
echo "<a href='auth/login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔐 Aller à la page de connexion</a>";
echo "</div>";

echo "<br><hr>";
echo "<p><em>Ce script a fait son travail - vous pouvez le supprimer.</em></p>";
?>
