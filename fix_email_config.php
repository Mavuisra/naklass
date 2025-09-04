<?php
/**
 * Script de correction de la configuration email
 */

echo "<h2>Correction de la Configuration Email</h2>";

// Vérifier si le fichier de configuration existe
if (file_exists('config/email.php')) {
    echo "✅ Fichier config/email.php existe<br>";
    
    // Lire le contenu actuel
    $content = file_get_contents('config/email.php');
    
    // Vérifier si EMAIL_FROM est défini
    if (strpos($content, 'EMAIL_FROM') === false) {
        echo "⚠️ Constante EMAIL_FROM manquante, ajout en cours...<br>";
        
        // Ajouter la constante EMAIL_FROM
        $new_content = str_replace(
            "define('SMTP_FROM_NAME', 'Naklass - Système de Gestion Scolaire');",
            "define('SMTP_FROM_NAME', 'Naklass - Système de Gestion Scolaire');\n\n// Alias pour compatibilité\ndefine('EMAIL_FROM', SMTP_FROM_EMAIL);",
            $content
        );
        
        // Sauvegarder le fichier
        if (file_put_contents('config/email.php', $new_content)) {
            echo "✅ Constante EMAIL_FROM ajoutée avec succès<br>";
        } else {
            echo "❌ Erreur lors de l'ajout de la constante<br>";
        }
    } else {
        echo "✅ Constante EMAIL_FROM déjà présente<br>";
    }
    
    // Vérifier si EMAIL_TO est défini (pour les tests)
    if (strpos($content, 'EMAIL_TO') === false) {
        echo "⚠️ Constante EMAIL_TO manquante, ajout en cours...<br>";
        
        // Ajouter la constante EMAIL_TO
        $new_content = str_replace(
            "define('EMAIL_FROM', SMTP_FROM_EMAIL);",
            "define('EMAIL_FROM', SMTP_FROM_EMAIL);\n\n// Email de test\ndefine('EMAIL_TO', 'test@example.com');",
            $content
        );
        
        // Sauvegarder le fichier
        if (file_put_contents('config/email.php', $new_content)) {
            echo "✅ Constante EMAIL_TO ajoutée avec succès<br>";
        } else {
            echo "❌ Erreur lors de l'ajout de la constante<br>";
        }
    } else {
        echo "✅ Constante EMAIL_TO déjà présente<br>";
    }
    
} else {
    echo "❌ Fichier config/email.php n'existe pas<br>";
    echo "Création du fichier de configuration par défaut...<br>";
    
    $default_config = '<?php
/**
 * Configuration Email pour Naklass
 * Paramètres SMTP pour l\'envoi d\'emails de confirmation
 */

// Configuration SMTP
define(\'SMTP_HOST\', \'mail.impact-entreprises.net\');
define(\'SMTP_PORT\', 465); // Port SSL
define(\'SMTP_USERNAME\', \'naklasse@impact-entreprises.net\');
define(\'SMTP_PASSWORD\', \'rX1!ZE-Zr5p18WC\'); // Mot de passe SMTP configuré
define(\'SMTP_SECURE\', \'ssl\'); // SSL ou TLS
define(\'SMTP_FROM_EMAIL\', \'naklasse@impact-entreprises.net\');
define(\'SMTP_FROM_NAME\', \'Naklass - Système de Gestion Scolaire\');

// Alias pour compatibilité
define(\'EMAIL_FROM\', SMTP_FROM_EMAIL);

// Configuration alternative (si le premier serveur ne fonctionne pas)
define(\'SMTP_HOST_ALT\', \'mail70.lwspanel.com\');
define(\'SMTP_PORT_ALT\', 587); // Port TLS
define(\'SMTP_SECURE_ALT\', \'tls\');

// Configuration des emails
define(\'ADMIN_EMAIL\', \'admin@naklass.com\'); // Email de l\'administrateur principal
define(\'SUPPORT_EMAIL\', \'support@naklass.com\'); // Email de support

// Templates d\'emails
define(\'EMAIL_TEMPLATE_PATH\', __DIR__ . \'/../templates/emails/\');

// Configuration de debug
define(\'EMAIL_DEBUG\', false); // Mettre à true pour activer le debug
define(\'EMAIL_LOG_FILE\', __DIR__ . \'/../logs/email.log\');
';
    
    if (file_put_contents('config/email.php', $default_config)) {
        echo "✅ Fichier de configuration créé avec succès<br>";
    } else {
        echo "❌ Erreur lors de la création du fichier<br>";
    }
}

echo "<h3>Test de la configuration</h3>";

// Tester la configuration
try {
    require_once 'config/email.php';
    
    echo "✅ Configuration chargée avec succès<br>";
    echo "SMTP_HOST : " . SMTP_HOST . "<br>";
    echo "SMTP_FROM_EMAIL : " . SMTP_FROM_EMAIL . "<br>";
    echo "EMAIL_FROM : " . EMAIL_FROM . "<br>";
    
} catch (Exception $e) {
    echo "❌ Erreur lors du chargement de la configuration : " . $e->getMessage() . "<br>";
}

echo "<h3>Liens utiles</h3>";
echo "<a href='test_email_config.php'>Test de configuration email</a><br>";
echo "<a href='test_visitor_school_creation.php'>Test complet du système</a><br>";
echo "<a href='visitor_create_school.php'>Créer une école</a>";
?>
