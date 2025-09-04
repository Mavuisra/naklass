<?php
/**
 * Script pour corriger les chemins des QR codes
 * 
 * Ce script déplace les QR codes du mauvais répertoire vers le bon
 * et corrige les chemins d'accès.
 */

echo "<h1>🔧 Correction des Chemins QR Code</h1>";
echo "<hr>";

// Répertoires
$sourceDir = __DIR__ . '/includes/uploads/qr_codes/';
$targetDir = __DIR__ . '/uploads/qr_codes/';

echo "<h2>📁 Vérification des répertoires</h2>";

// Vérifier le répertoire source
if (is_dir($sourceDir)) {
    echo "✅ Répertoire source trouvé : " . $sourceDir . "<br>";
    $files = glob($sourceDir . '*');
    echo "📊 Fichiers trouvés : " . count($files) . "<br>";
} else {
    echo "❌ Répertoire source non trouvé : " . $sourceDir . "<br>";
    $sourceDir = null;
}

// Créer le répertoire cible s'il n'existe pas
if (!is_dir($targetDir)) {
    if (mkdir($targetDir, 0755, true)) {
        echo "✅ Répertoire cible créé : " . $targetDir . "<br>";
    } else {
        echo "❌ Impossible de créer le répertoire cible : " . $targetDir . "<br>";
        exit;
    }
} else {
    echo "✅ Répertoire cible existe : " . $targetDir . "<br>";
}

echo "<hr>";

if ($sourceDir && is_dir($sourceDir)) {
    echo "<h2>🔄 Déplacement des fichiers</h2>";
    
    $movedCount = 0;
    $errorCount = 0;
    
    $files = glob($sourceDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $targetFile = $targetDir . $filename;
            
            if (copy($file, $targetFile)) {
                echo "✅ Déplacé : " . $filename . "<br>";
                $movedCount++;
                
                // Supprimer le fichier source
                unlink($file);
            } else {
                echo "❌ Erreur : " . $filename . "<br>";
                $errorCount++;
            }
        }
    }
    
    echo "<br><strong>Résultat :</strong><br>";
    echo "✅ Fichiers déplacés : " . $movedCount . "<br>";
    echo "❌ Erreurs : " . $errorCount . "<br>";
    
    // Supprimer le répertoire source s'il est vide
    if (count(glob($sourceDir . '*')) === 0) {
        rmdir($sourceDir);
        echo "🗑️ Répertoire source supprimé (vide)<br>";
    }
}

echo "<hr>";

// Tester la génération d'un nouveau QR code
echo "<h2>🧪 Test de génération</h2>";

try {
    require_once 'includes/QRCodeManager.php';
    $qrManager = new QRCodeManager();
    
    $testData = [
        'id' => 999,
        'matricule' => 'TEST_PATH',
        'nom' => 'Test',
        'prenom' => 'Path',
        'ecole_id' => 1,
        'classe_nom' => 'Test',
        'annee_scolaire' => '2024-2025',
        'statut_scolaire' => 'actif',
        'ecole_nom' => 'École Test',
        'type_etablissement' => 'Primaire'
    ];
    
    $result = $qrManager->generateWebQRCode($testData);
    
    if ($result['success']) {
        echo "✅ Nouveau QR code généré avec succès<br>";
        echo "Fichier : " . $result['filename'] . "<br>";
        echo "Chemin web : " . $result['web_path'] . "<br>";
        echo "Format : " . $result['format'] . "<br>";
        
        // Vérifier que le fichier est accessible
        $webPath = $result['web_path'];
        if (file_exists($webPath)) {
            echo "✅ Fichier accessible via le chemin web<br>";
            
            // Afficher le QR code
            echo "<br><div style='border: 2px solid #007BFF; padding: 20px; display: inline-block; background: white;'>";
            echo "<h3>Test QR Code</h3>";
            echo "<img src='" . $webPath . "' alt='QR Code Test' style='max-width: 150px; border: 1px solid #ccc;'>";
            echo "<br><small>Chemin : " . $webPath . "</small>";
            echo "</div>";
        } else {
            echo "❌ Fichier non accessible via le chemin web<br>";
        }
    } else {
        echo "❌ Erreur de génération : " . $result['error'] . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Vérifier les permissions
echo "<h2>🔐 Vérification des permissions</h2>";
$testFile = $targetDir . 'test_permissions.txt';
if (file_put_contents($testFile, 'test')) {
    echo "✅ Permissions d'écriture OK<br>";
    unlink($testFile);
} else {
    echo "❌ Problème de permissions d'écriture<br>";
}

echo "<hr>";

echo "<h2>✅ Correction terminée !</h2>";
echo "<p>Les QR codes sont maintenant dans le bon répertoire et accessibles via les chemins corrects.</p>";
echo "<p><strong>Chemin correct :</strong> <code>uploads/qr_codes/filename.png</code></p>";
echo "<p><strong>URL d'accès :</strong> <code>http://localhost/naklass/uploads/qr_codes/filename.png</code></p>";

?>
