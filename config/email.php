<?php
/**
 * Configuration Email pour Naklass
 * Paramètres SMTP pour l'envoi d'emails de confirmation
 */

// Configuration SMTP
define('SMTP_HOST', 'mail.impact-entreprises.net');
define('SMTP_PORT', 465); // Port SSL
define('SMTP_USERNAME', 'naklasse@impact-entreprises.net');
define('SMTP_PASSWORD', 'rX1!ZE-Zr5p18WC'); // Mot de passe SMTP configuré
define('SMTP_SECURE', 'ssl'); // SSL ou TLS
define('SMTP_FROM_EMAIL', 'naklasse@impact-entreprises.net');
define('SMTP_FROM_NAME', 'Naklass - Système de Gestion Scolaire');

// Alias pour compatibilité
define('EMAIL_FROM', SMTP_FROM_EMAIL);

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
