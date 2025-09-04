<?php
/**
 * Script pour activer l'extension GD et tester le système QR code
 * 
 * Ce script aide à diagnostiquer et résoudre les problèmes avec l'extension GD
 * nécessaire pour la génération des QR codes en format PNG.
 * 
 * @author Naklass Team
 * @version 1.0.0
 * @since 2024
 */

echo "<h1>🔧 Diagnostic et Correction - Extension GD</h1>";
echo "<hr>";

// 1. Vérifier l'état actuel de GD
echo "<h2>1. État actuel de l'extension GD</h2>";
$gdLoaded = extension_loaded('gd');
echo "Extension GD chargée : " . ($gdLoaded ? "✅ OUI" : "❌ NON") . "<br>";

if ($gdLoaded) {
    $gdInfo = gd_info();
    echo "Version GD : " . $gdInfo['GD Version'] . "<br>";
    echo "Support JPEG : " . ($gdInfo['JPEG Support'] ? "✅" : "❌") . "<br>";
    echo "Support PNG : " . ($gdInfo['PNG Support'] ? "✅" : "❌") . "<br>";
    echo "Support GIF : " . ($gdInfo['GIF Read Support'] ? "✅" : "❌") . "<br>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>⚠️ Extension GD non chargée !</strong><br>";
    echo "L'extension GD est nécessaire pour générer les QR codes en format PNG.";
    echo "</div>";
}

echo "<hr>";

// 2. Vérifier le fichier php.ini
echo "<h2>2. Configuration PHP</h2>";
$phpIniPath = php_ini_loaded_file();
echo "Fichier php.ini : " . ($phpIniPath ?: "Non trouvé") . "<br>";

if ($phpIniPath && file_exists($phpIniPath)) {
    $phpIniContent = file_get_contents($phpIniPath);
    $gdLine = preg_match('/^;?extension\s*=\s*gd\s*$/m', $phpIniContent);
    echo "Ligne GD dans php.ini : " . ($gdLine ? "✅ Trouvée" : "❌ Non trouvée") . "<br>";
    
    if ($gdLine) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>✅ Extension GD configurée dans php.ini</strong><br>";
        echo "Redémarrez Apache pour activer l'extension.";
        echo "</div>";
    } else {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>⚠️ Extension GD non configurée</strong><br>";
        echo "Ajoutez cette ligne dans votre php.ini : <code>extension=gd</code>";
        echo "</div>";
    }
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ Fichier php.ini non trouvé</strong><br>";
    echo "Impossible de vérifier la configuration.";
    echo "</div>";
}

echo "<hr>";

// 3. Tester la génération de QR code
echo "<h2>3. Test de génération QR code</h2>";

try {
    require_once 'includes/QRCodeManager.php';
    $qrManager = new QRCodeManager();
    
    // Test avec données fictives
    $testData = [
        'id' => 999,
        'matricule' => 'TEST001',
        'nom' => 'Test',
        'prenom' => 'QR Code',
        'ecole_id' => 1,
        'classe_nom' => 'Test',
        'annee_scolaire' => '2024-2025',
        'statut_scolaire' => 'actif',
        'ecole_nom' => 'École Test',
        'type_etablissement' => 'Primaire'
    ];
    
    echo "Test de génération QR code...<br>";
    
    // Test avec SVG (ne nécessite pas GD)
    $svgResult = $qrManager->generateSimpleQRCode("Test SVG", [
        'size' => 200,
        'format' => 'svg'
    ]);
    
    if ($svgResult['success']) {
        echo "✅ QR code SVG généré avec succès<br>";
        echo "Fichier : " . $svgResult['filename'] . "<br>";
        echo "Chemin : " . $svgResult['file_path'] . "<br>";
        
        // Afficher le QR code SVG
        echo "<br><div style='border: 1px solid #ccc; padding: 10px; display: inline-block;'>";
        echo "<strong>QR Code SVG :</strong><br>";
        echo "<img src='" . str_replace('../', '', $svgResult['file_path']) . "' alt='QR Code SVG' style='max-width: 200px;'>";
        echo "</div><br>";
    } else {
        echo "❌ Erreur génération SVG : " . $svgResult['error'] . "<br>";
    }
    
    // Test avec PNG (nécessite GD)
    if ($gdLoaded) {
        $pngResult = $qrManager->generateSimpleQRCode("Test PNG", [
            'size' => 200,
            'format' => 'png'
        ]);
        
        if ($pngResult['success']) {
            echo "✅ QR code PNG généré avec succès<br>";
            echo "Fichier : " . $pngResult['filename'] . "<br>";
            echo "Chemin : " . $pngResult['file_path'] . "<br>";
            
            // Afficher le QR code PNG
            echo "<br><div style='border: 1px solid #ccc; padding: 10px; display: inline-block;'>";
            echo "<strong>QR Code PNG :</strong><br>";
            echo "<img src='" . str_replace('../', '', $pngResult['file_path']) . "' alt='QR Code PNG' style='max-width: 200px;'>";
            echo "</div><br>";
        } else {
            echo "❌ Erreur génération PNG : " . $pngResult['error'] . "<br>";
        }
    } else {
        echo "⚠️ Test PNG ignoré (GD non disponible)<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur lors du test : " . $e->getMessage() . "<br>";
}

echo "<hr>";

// 4. Instructions de correction
echo "<h2>4. Instructions de Correction</h2>";

if (!$gdLoaded) {
    echo "<div style='background: #d1ecf1; padding: 20px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>🔧 Pour activer l'extension GD :</h3>";
    echo "<ol>";
    echo "<li><strong>Ouvrir le fichier php.ini</strong><br>";
    echo "Chemin : <code>" . ($phpIniPath ?: 'C:\\xampp\\php\\php.ini') . "</code></li>";
    echo "<li><strong>Chercher la ligne :</strong><br>";
    echo "<code>;extension=gd</code></li>";
    echo "<li><strong>Décommenter la ligne :</strong><br>";
    echo "Enlever le <code>;</code> au début : <code>extension=gd</code></li>";
    echo "<li><strong>Sauvegarder le fichier</strong></li>";
    echo "<li><strong>Redémarrer Apache</strong> via le panneau de contrôle XAMPP</li>";
    echo "<li><strong>Recharger cette page</strong> pour vérifier</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>✅ Extension GD activée !</h3>";
    echo "Le système QR code devrait maintenant fonctionner correctement.";
    echo "</div>";
}

echo "<hr>";

// 5. Solution de contournement temporaire
echo "<h2>5. Solution Temporaire</h2>";
echo "<div style='background: #fff3cd; padding: 20px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>🔄 En attendant l'activation de GD :</h3>";
echo "<p>Le système utilise automatiquement le format SVG qui ne nécessite pas l'extension GD.</p>";
echo "<p>Les QR codes s'afficheront correctement, mais en format vectoriel (SVG) au lieu de PNG.</p>";
echo "<p>Une fois GD activé, le système basculera automatiquement vers PNG pour de meilleures performances.</p>";
echo "</div>";

echo "<hr>";
echo "<p><strong>📞 Support :</strong> Si vous rencontrez des difficultés, consultez la documentation dans <code>QR_CODE_SYSTEM_GUIDE.md</code></p>";
?>
