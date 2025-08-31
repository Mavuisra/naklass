<?php
/**
 * Configuration Email pour Naklass - FICHIER D'EXEMPLE
 * Copiez ce fichier vers email.php et configurez le mot de passe
 */

// Configuration SMTP
define('SMTP_HOST', 'mail.impact-entreprises.net');
define('SMTP_PORT', 465); // Port SSL
define('SMTP_USERNAME', 'naklasse@impact-entreprises.net');
define('SMTP_PASSWORD', 'rX1!ZE-Zr5p18WC'); // ⚠️ AJOUTEZ VOTRE MOT DE PASSE ICI
define('SMTP_SECURE', 'ssl'); // SSL ou TLS
define('SMTP_FROM_EMAIL', 'naklasse@impact-entreprises.net');
define('SMTP_FROM_NAME', 'Naklass - Système de Gestion Scolaire');

// Configuration alternative (si le premier serveur ne fonctionne pas)
define('SMTP_HOST_ALT', 'mail70.lwspanel.com');
define('SMTP_PORT_ALT', 587); // Port TLS
define('SMTP_SECURE_ALT', 'tls');

// Configuration des emails
define('ADMIN_EMAIL', 'admin@naklass.com'); // Email de l'administrateur principal
define('SUPPORT_EMAIL', 'support@naklass.com'); // Email de support

// Templates d'emails
define('EMAIL_TEMPLATE_PATH', __DIR__ . '/../templates/emails/');

// Configuration de debug
define('EMAIL_DEBUG', false); // Mettre à true pour activer le debug
define('EMAIL_LOG_FILE', __DIR__ . '/../logs/email.log');

/*
 * INSTRUCTIONS DE CONFIGURATION :
 * 
 * 1. Copiez ce fichier vers config/email.php
 * 2. Ajoutez votre mot de passe SMTP dans SMTP_PASSWORD
 * 3. Vérifiez que les autres paramètres correspondent à votre serveur
 * 4. Testez la configuration avec test_email_config.php
 * 
 * SÉCURITÉ :
 * - Ne commitez jamais le fichier email.php avec le mot de passe
 * - Utilisez des variables d'environnement en production
 * - Limitez l'accès au dossier config/
 */
