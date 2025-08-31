<?php
/**
 * Script d'installation automatique de PHPMailer
 * Télécharge et installe PHPMailer si Composer n'est pas disponible
 */

echo "<h1>Installation de PHPMailer - Naklass</h1>";

// Vérifier si PHPMailer est déjà installé
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color: green;'>✅ PHPMailer est déjà installé via Composer</p>";
    echo "<p><a href='test_email_config.php'>Tester la configuration email</a></p>";
    exit;
}

// Vérifier si Composer est disponible
if (file_exists('vendor/autoload.php')) {
    echo "<p style='color: green;'>✅ Composer est disponible</p>";
    echo "<p>Exécutez : <code>composer install</code> pour installer PHPMailer</p>";
    exit;
}

echo "<h2>Installation Manuelle de PHPMailer</h2>";

// Créer le dossier lib s'il n'existe pas
$libDir = 'lib/PHPMailer';
if (!is_dir($libDir)) {
    if (mkdir($libDir, 0755, true)) {
        echo "<p style='color: green;'>✅ Dossier lib/PHPMailer créé</p>";
    } else {
        echo "<p style='color: red;'>❌ Impossible de créer le dossier lib/PHPMailer</p>";
        exit;
    }
}

// URLs des fichiers PHPMailer
$phpmailerFiles = [
    'PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
    'SMTP.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
    'Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php'
];

$downloadSuccess = true;

foreach ($phpmailerFiles as $filename => $url) {
    $filepath = $libDir . '/' . $filename;
    
    if (file_exists($filepath)) {
        echo "<p style='color: orange;'>⚠️ Le fichier $filename existe déjà</p>";
        continue;
    }
    
    echo "<p>Téléchargement de $filename...</p>";
    
    // Télécharger le fichier
    $content = file_get_contents($url);
    if ($content === false) {
        echo "<p style='color: red;'>❌ Échec du téléchargement de $filename</p>";
        $downloadSuccess = false;
        continue;
    }
    
    // Sauvegarder le fichier
    if (file_put_contents($filepath, $content)) {
        echo "<p style='color: green;'>✅ $filename téléchargé avec succès</p>";
    } else {
        echo "<p style='color: red;'>❌ Impossible de sauvegarder $filename</p>";
        $downloadSuccess = false;
    }
}

if ($downloadSuccess) {
    echo "<h2 style='color: green;'>✅ Installation terminée avec succès !</h2>";
    echo "<p>PHPMailer a été installé dans le dossier lib/PHPMailer/</p>";
    
    // Créer le dossier de logs
    if (!is_dir('logs')) {
        if (mkdir('logs', 0755, true)) {
            echo "<p style='color: green;'>✅ Dossier de logs créé</p>";
        }
    }
    
    echo "<p><a href='test_email_config.php' class='btn btn-primary'>Tester la configuration email</a></p>";
    
} else {
    echo "<h2 style='color: red;'>❌ Installation incomplète</h2>";
    echo "<p>Certains fichiers n'ont pas pu être téléchargés. Veuillez :</p>";
    echo "<ol>";
    echo "<li>Vérifier votre connexion internet</li>";
    echo "<li>Vérifier les permissions du dossier lib/</li>";
    echo "<li>Essayer l'installation via Composer : <code>composer require phpmailer/phpmailer</code></li>";
    echo "</ol>";
}

echo "<hr>";
echo "<h2>Prochaines étapes</h2>";
echo "<ol>";
echo "<li><strong>Configurer le mot de passe SMTP :</strong> Modifiez config/email.php</li>";
echo "<li><strong>Tester la configuration :</strong> Utilisez test_email_config.php</li>";
echo "<li><strong>Vérifier les permissions :</strong> Assurez-vous que PHP peut écrire dans le dossier logs/</li>";
echo "</ol>";

echo "<h2>Alternative : Installation via Composer</h2>";
echo "<p>Si vous avez Composer installé, vous pouvez utiliser :</p>";
echo "<pre>composer require phpmailer/phpmailer</pre>";
echo "<p>Cette méthode est plus fiable et permet les mises à jour automatiques.</p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2 { color: #0077b6; }
.btn { display: inline-block; padding: 10px 20px; background: #0077b6; color: white; text-decoration: none; border-radius: 5px; }
.btn:hover { background: #005a8b; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; }
</style>
