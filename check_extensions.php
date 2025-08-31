<?php
// Page de diagnostic des extensions PHP pour Naklass

echo "<h1>Diagnostic des extensions PHP - Naklass</h1>";

echo "<h2>Extensions requises :</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Extension</th><th>Statut</th><th>Description</th></tr>";

$required_extensions = [
    'pdo' => 'Base de données PDO',
    'pdo_mysql' => 'Connecteur MySQL pour PDO',
    'gd' => 'Manipulation d\'images (upload/redimensionnement)',
    'mbstring' => 'Support des chaînes multi-octets',
    'openssl' => 'Chiffrement et sécurité',
    'curl' => 'Requêtes HTTP',
    'fileinfo' => 'Détection de type de fichier',
    'json' => 'Support JSON'
];

foreach ($required_extensions as $ext => $description) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '<span style="color: green;">✓ ACTIVÉE</span>' : '<span style="color: red;">✗ MANQUANTE</span>';
    echo "<tr><td>$ext</td><td>$status</td><td>$description</td></tr>";
}

echo "</table>";

echo "<h2>Configuration PHP actuelle :</h2>";
echo "<p><strong>Version PHP :</strong> " . phpversion() . "</p>";
echo "<p><strong>Fichier php.ini :</strong> " . (php_ini_loaded_file() ?: 'Aucun') . "</p>";
echo "<p><strong>Répertoire d'upload :</strong> " . ini_get('upload_tmp_dir') . "</p>";
echo "<p><strong>Taille max upload :</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>Limite POST :</strong> " . ini_get('post_max_size') . "</p>";

if (!extension_loaded('gd')) {
    echo "<h2 style='color: red;'>⚠️ Extension GD manquante</h2>";
    echo "<p>L'extension GD est nécessaire pour l'upload et le redimensionnement des photos.</p>";
    echo "<h3>Pour activer GD dans XAMPP :</h3>";
    echo "<ol>";
    echo "<li>Ouvrez le <strong>Panneau de contrôle XAMPP</strong></li>";
    echo "<li>Cliquez sur <strong>Config</strong> à côté d'Apache</li>";
    echo "<li>Sélectionnez <strong>PHP (php.ini)</strong></li>";
    echo "<li>Recherchez la ligne <code>;extension=gd</code></li>";
    echo "<li>Supprimez le <code>;</code> au début pour obtenir <code>extension=gd</code></li>";
    echo "<li>Sauvegardez le fichier</li>";
    echo "<li>Redémarrez Apache dans XAMPP</li>";
    echo "</ol>";
    echo "<p>Puis actualisez cette page pour vérifier.</p>";
}

echo "<h2>Fonctions GD disponibles :</h2>";
if (extension_loaded('gd')) {
    $gd_functions = [
        'imagecreatetruecolor',
        'imagecreatefromjpeg',
        'imagecreatefrompng', 
        'imagecreatefromgif',
        'imagejpeg',
        'imagepng',
        'imagegif',
        'imagecopyresampled',
        'getimagesize'
    ];
    
    echo "<ul>";
    foreach ($gd_functions as $func) {
        $available = function_exists($func);
        $status = $available ? '✓' : '✗';
        echo "<li>$status $func</li>";
    }
    echo "</ul>";
    
    $gd_info = gd_info();
    echo "<h3>Informations GD :</h3>";
    echo "<pre>" . print_r($gd_info, true) . "</pre>";
} else {
    echo "<p style='color: red;'>Extension GD non chargée</p>";
}

echo "<hr>";
echo "<p><a href='students/edit.php?id=1'>← Retour à l'édition d'élève</a></p>";
?>
