<?php
/**
 * Gestionnaire d'emails pour Naklass
 * Utilise PHPMailer pour l'envoi d'emails via SMTP
 */

require_once __DIR__ . '/../config/email.php';

class EmailManager {
    private $mailer;
    private $debug;
    
    public function __construct() {
        $this->debug = EMAIL_DEBUG;
        $this->initMailer();
    }
    
    /**
     * Initialise le client SMTP
     */
    private function initMailer() {
        try {
            // Vérifier si PHPMailer est disponible
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                // Essayer d'inclure PHPMailer depuis Composer
                $composerAutoload = __DIR__ . '/../vendor/autoload.php';
                if (file_exists($composerAutoload)) {
                    require_once $composerAutoload;
                } else {
                    // Inclure PHPMailer manuellement si disponible
                    $phpmailerPath = __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
                    if (file_exists($phpmailerPath)) {
                        require_once $phpmailerPath;
                        require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';
                        require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
                    } else {
                        throw new Exception('PHPMailer n\'est pas installé. Veuillez l\'installer via Composer ou le télécharger manuellement.');
                    }
                }
            }
            
            $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = SMTP_SECURE;
            $this->mailer->Port = SMTP_PORT;
            $this->mailer->CharSet = 'UTF-8';
            
            // Configuration du debug
            if ($this->debug) {
                $this->mailer->SMTPDebug = 2;
                $this->mailer->Debugoutput = function($str, $level) {
                    $this->logEmail("DEBUG: $str");
                };
            } else {
                $this->mailer->SMTPDebug = 0;
            }
            
            // Configuration de l'expéditeur
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
        } catch (Exception $e) {
            $this->logEmail("Erreur d'initialisation: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Envoie un email de confirmation de création d'école
     */
    public function sendSchoolCreationConfirmation($ecoleData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($ecoleData['email']);
            
            $this->mailer->Subject = "Confirmation de création d'école - {$ecoleData['nom_ecole']}";
            
            // Corps de l'email en HTML
            $htmlBody = $this->getSchoolCreationEmailTemplate($ecoleData);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $htmlBody;
            
            // Corps de l'email en texte brut
            $textBody = $this->getSchoolCreationEmailTextTemplate($ecoleData);
            $this->mailer->AltBody = $textBody;
            
            // Envoi de l'email
            if ($this->mailer->send()) {
                $this->logEmail("Email de confirmation envoyé avec succès à {$ecoleData['email']}");
                return true;
            } else {
                $this->logEmail("Échec de l'envoi de l'email à {$ecoleData['email']}");
                return false;
            }
            
        } catch (Exception $e) {
            $this->logEmail("Erreur lors de l'envoi de l'email: " . $e->getMessage());
            
            // Essayer avec le serveur alternatif
            return $this->tryAlternativeServer($ecoleData);
        }
    }
    
    /**
     * Envoie une notification à l'administrateur
     */
    public function sendAdminNotification($ecoleData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress(ADMIN_EMAIL);
            
            $this->mailer->Subject = "Nouvelle demande de création d'école - {$ecoleData['nom_ecole']}";
            
            // Corps de l'email en HTML
            $htmlBody = $this->getAdminNotificationEmailTemplate($ecoleData);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $htmlBody;
            
            // Corps de l'email en texte brut
            $textBody = $this->getAdminNotificationEmailTextTemplate($ecoleData);
            $this->mailer->AltBody = $textBody;
            
            // Envoi de l'email
            if ($this->mailer->send()) {
                $this->logEmail("Notification admin envoyée avec succès");
                return true;
            } else {
                $this->logEmail("Échec de l'envoi de la notification admin");
                return false;
            }
            
        } catch (Exception $e) {
            $this->logEmail("Erreur lors de l'envoi de la notification admin: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Essaie d'envoyer avec le serveur alternatif
     */
    private function tryAlternativeServer($ecoleData) {
        try {
            $this->mailer->Host = SMTP_HOST_ALT;
            $this->mailer->Port = SMTP_PORT_ALT;
            $this->mailer->SMTPSecure = SMTP_SECURE_ALT;
            
            $this->logEmail("Tentative avec le serveur alternatif: " . SMTP_HOST_ALT);
            
            return $this->sendSchoolCreationConfirmation($ecoleData);
            
        } catch (Exception $e) {
            $this->logEmail("Échec avec le serveur alternatif: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Template HTML pour l'email de confirmation
     */
    private function getSchoolCreationEmailTemplate($ecoleData) {
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Confirmation de création d'école</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0077b6; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .success-icon { font-size: 48px; margin-bottom: 20px; }
                .school-info { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #0077b6; }
                .code-ecole { background: #e3f2fd; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 18px; text-align: center; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
                .btn { display: inline-block; padding: 12px 24px; background: #0077b6; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='success-icon'>🎓</div>
                    <h1>École créée avec succès !</h1>
                    <p>Votre établissement scolaire a été enregistré dans notre système</p>
                </div>
                
                <div class='content'>
                    <h2>Félicitations !</h2>
                    <p>Votre école <strong>{$ecoleData['nom_ecole']}</strong> a été créée avec succès dans le système Naklass.</p>
                    
                    <div class='school-info'>
                        <h3>Informations de votre école :</h3>
                        <p><strong>Nom :</strong> {$ecoleData['nom_ecole']}</p>
                        <p><strong>Adresse :</strong> {$ecoleData['adresse']}</p>
                        <p><strong>Ville :</strong> {$ecoleData['ville']}</p>
                        <p><strong>Province :</strong> {$ecoleData['province']}</p>
                        <p><strong>Téléphone :</strong> {$ecoleData['telephone']}</p>
                        <p><strong>Email :</strong> {$ecoleData['email']}</p>
                    </div>
                    
                    <div class='code-ecole'>
                        <strong>Code d'école :</strong><br>
                        {$ecoleData['code_ecole']}
                    </div>
                    
                    <h3>Prochaines étapes :</h3>
                    <ol>
                        <li><strong>Validation :</strong> Votre demande sera examinée par un super administrateur</li>
                        <li><strong>Approbation :</strong> Une fois approuvée, vous recevrez un email de confirmation</li>
                        <li><strong>Activation :</strong> Vous pourrez alors créer votre compte administrateur</li>
                        <li><strong>Configuration :</strong> Commencer à configurer votre école</li>
                    </ol>
                    
                    <p><strong>Durée estimée :</strong> 24-48 heures pour la validation</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='https://naklass.impact-entreprises.net' class='btn'>Accéder à Naklass</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>Ceci est un email automatique, merci de ne pas y répondre.</p>
                    <p>© " . date('Y') . " Naklass - Système de Gestion Scolaire</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template texte pour l'email de confirmation
     */
    private function getSchoolCreationEmailTextTemplate($ecoleData) {
        return "
ÉCOLE CRÉÉE AVEC SUCCÈS !

Félicitations ! Votre école {$ecoleData['nom_ecole']} a été créée avec succès dans le système Naklass.

INFORMATIONS DE VOTRE ÉCOLE :
- Nom : {$ecoleData['nom_ecole']}
- Adresse : {$ecoleData['adresse']}
- Ville : {$ecoleData['ville']}
- Province : {$ecoleData['province']}
- Téléphone : {$ecoleData['telephone']}
- Email : {$ecoleData['email']}

CODE D'ÉCOLE : {$ecoleData['code_ecole']}

PROCHAINES ÉTAPES :
1. Validation : Votre demande sera examinée par un super administrateur
2. Approbation : Une fois approuvée, vous recevrez un email de confirmation
3. Activation : Vous pourrez alors créer votre compte administrateur
4. Configuration : Commencer à configurer votre école

Durée estimée : 24-48 heures pour la validation

Accéder à Naklass : https://naklass.impact-entreprises.net

© " . date('Y') . " Naklass - Système de Gestion Scolaire";
    }
    
    /**
     * Template HTML pour la notification admin
     */
    private function getAdminNotificationEmailTemplate($ecoleData) {
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Nouvelle demande de création d'école</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .alert-icon { font-size: 48px; margin-bottom: 20px; }
                .school-info { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #dc3545; }
                .btn { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='alert-icon'>⚠️</div>
                    <h1>Nouvelle demande de création d'école</h1>
                    <p>Une nouvelle école demande à être validée</p>
                </div>
                
                <div class='content'>
                    <h2>Détails de la demande :</h2>
                    
                    <div class='school-info'>
                        <h3>Informations de l'école :</h3>
                        <p><strong>Nom :</strong> {$ecoleData['nom_ecole']}</p>
                        <p><strong>Code :</strong> {$ecoleData['code_ecole']}</p>
                        <p><strong>Adresse :</strong> {$ecoleData['adresse']}</p>
                        <p><strong>Ville :</strong> {$ecoleData['ville']}</p>
                        <p><strong>Province :</strong> {$ecoleData['province']}</p>
                        <p><strong>Téléphone :</strong> {$ecoleData['telephone']}</p>
                        <p><strong>Email :</strong> {$ecoleData['email']}</p>
                        <p><strong>Directeur :</strong> {$ecoleData['directeur_nom']}</p>
                        <p><strong>ID Visiteur :</strong> {$ecoleData['visitor_id']}</p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='https://naklass.impact-entreprises.net/superadmin/school_validation.php?id={$ecoleData['ecole_id']}' class='btn'>Valider cette école</a>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template texte pour la notification admin
     */
    private function getAdminNotificationEmailTextTemplate($ecoleData) {
        return "
NOUVELLE DEMANDE DE CRÉATION D'ÉCOLE

Une nouvelle école demande à être validée.

DÉTAILS DE LA DEMANDE :
- Nom : {$ecoleData['nom_ecole']}
- Code : {$ecoleData['code_ecole']}
- Adresse : {$ecoleData['adresse']}
- Ville : {$ecoleData['ville']}
- Province : {$ecoleData['province']}
- Téléphone : {$ecoleData['telephone']}
- Email : {$ecoleData['email']}
- Directeur : {$ecoleData['directeur_nom']}
- ID Visiteur : {$ecoleData['visitor_id']}

Valider cette école : https://naklass.impact-entreprises.net/superadmin/school_validation.php?id={$ecoleData['ecole_id']}";
    }
    
    /**
     * Enregistre les logs d'emails
     */
    private function logEmail($message) {
        if (!is_dir(dirname(EMAIL_LOG_FILE))) {
            mkdir(dirname(EMAIL_LOG_FILE), 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        
        file_put_contents(EMAIL_LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
        
        if ($this->debug) {
            error_log("EMAIL LOG: $message");
        }
    }
    
    /**
     * Teste la connexion SMTP
     */
    public function testConnection() {
        try {
            $this->mailer->smtpConnect();
            $this->logEmail("Test de connexion SMTP réussi");
            return true;
        } catch (Exception $e) {
            $this->logEmail("Test de connexion SMTP échoué: " . $e->getMessage());
            return false;
        }
    }
}
