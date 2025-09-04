<?php
/**
 * Script pour activer automatiquement l'extension GD
 * 
 * ATTENTION : Ce script modifie le fichier php.ini
 * Assurez-vous d'avoir les permissions d'écriture nécessaires
 */

echo "<h1>🔧 Activation Automatique de l'Extension GD</h1>";
echo "<hr>";

// Vérifier si GD est déjà activé
if (extension_loaded('gd')) {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>✅ Extension GD déjà activée !</h2>";
    echo "L'extension GD est déjà chargée. Aucune action nécessaire.";
    echo "</div>";
    exit;
}

$phpIniPath = php_ini_loaded_file();

if (!$phpIniPath || !file_exists($phpIniPath)) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>❌ Fichier php.ini non trouvé</h2>";
    echo "Impossible de localiser le fichier php.ini.";
    echo "</div>";
    exit;
}

echo "<h2>📁 Fichier php.ini trouvé :</h2>";
echo "<code>" . $phpIniPath . "</code><br><br>";

// Lire le contenu du fichier php.ini
$phpIniContent = file_get_contents($phpIniPath);

// Vérifier si GD est déjà configuré
if (preg_match('/^extension\s*=\s*gd\s*$/m', $phpIniContent)) {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>✅ Extension GD déjà configurée</h2>";
    echo "L'extension GD est déjà configurée dans php.ini. Redémarrez Apache pour l'activer.";
    echo "</div>";
    exit;
}

// Chercher la ligne commentée
if (preg_match('/^;extension\s*=\s*gd\s*$/m', $phpIniContent)) {
    echo "<h2>🔧 Activation de l'extension GD...</h2>";
    
    // Décommenter la ligne
    $newContent = preg_replace('/^;extension\s*=\s*gd\s*$/m', 'extension=gd', $phpIniContent);
    
    // Sauvegarder le fichier
    if (file_put_contents($phpIniPath, $newContent)) {
        echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h2>✅ Extension GD activée avec succès !</h2>";
        echo "Le fichier php.ini a été modifié. <strong>Redémarrez Apache</strong> pour appliquer les changements.";
        echo "</div>";
        
        echo "<h3>📋 Prochaines étapes :</h3>";
        echo "<ol>";
        echo "<li>Ouvrir le panneau de contrôle XAMPP</li>";
        echo "<li>Arrêter Apache (bouton Stop)</li>";
        echo "<li>Redémarrer Apache (bouton Start)</li>";
        echo "<li>Recharger cette page pour vérifier</li>";
        echo "</ol>";
        
    } else {
        echo "<div style='background: #f8d7da; padding: 20px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h2>❌ Erreur lors de la sauvegarde</h2>";
        echo "Impossible de modifier le fichier php.ini. Vérifiez les permissions d'écriture.";
        echo "</div>";
    }
    
} else {
    echo "<div style='background: #fff3cd; padding: 20px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>⚠️ Extension GD non trouvée</h2>";
    echo "La ligne d'extension GD n'a pas été trouvée dans php.ini.";
    echo "<br><br>";
    echo "<strong>Ajoutez manuellement cette ligne :</strong><br>";
    echo "<code>extension=gd</code>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>🔍 Vérification manuelle</h2>";
echo "<p>Pour vérifier manuellement, ouvrez le fichier php.ini et cherchez :</p>";
echo "<code>extension=gd</code>";
echo "<p>Cette ligne ne doit PAS commencer par un point-virgule (;)</p>";

echo "<hr>";
echo "<h2>🧪 Test après redémarrage</h2>";
echo "<p>Après avoir redémarré Apache, exécutez :</p>";
echo "<code>php test_qr_display.php</code>";
echo "<p>Vous devriez voir : <strong>Extension GD activée - Format PNG disponible</strong></p>";
?>
