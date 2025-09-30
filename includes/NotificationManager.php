<?php
/**
 * Gestionnaire de Notifications Naklass
 * Système de notifications complet pour la gestion scolaire
 * 
 * @author Naklass Team
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/database.php';

class NotificationManager {
    private $db;
    private $user_id;
    private $ecole_id;
    
    // Types de notifications
    const TYPES = [
        // Académiques
        'student_registered' => 'Nouvelle inscription d\'élève',
        'student_class_changed' => 'Changement de classe',
        'course_assigned' => 'Cours assigné',
        'teacher_assigned' => 'Enseignant assigné',
        'student_promoted' => 'Promotion d\'élève',
        'student_graduated' => 'Diplômé',
        
        // Financières
        'payment_received' => 'Paiement reçu',
        'payment_overdue' => 'Paiement en retard',
        'fees_updated' => 'Frais mis à jour',
        'refund_processed' => 'Remboursement effectué',
        'invoice_generated' => 'Facture générée',
        
        // Utilisateurs
        'user_created' => 'Nouveau compte créé',
        'user_updated' => 'Profil modifié',
        'user_deleted' => 'Compte supprimé',
        'login_suspicious' => 'Connexion suspecte',
        'password_changed' => 'Mot de passe modifié',
        
        // École
        'school_created' => 'Nouvelle école créée',
        'school_validated' => 'École validée',
        'school_updated' => 'Paramètres modifiés',
        'school_logo_changed' => 'Logo changé',
        
        // Alertes
        'student_absent' => 'Absence répétée',
        'class_capacity' => 'Capacité atteinte',
        'low_grades' => 'Notes faibles',
        'system_maintenance' => 'Maintenance système',
        'backup_completed' => 'Sauvegarde terminée'
    ];
    
    // Catégories de notifications
    const CATEGORIES = [
        'academic' => 'Académique',
        'financial' => 'Financière',
        'user' => 'Utilisateur',
        'school' => 'École',
        'alert' => 'Alerte',
        'security' => 'Sécurité',
        'system' => 'Système'
    ];
    
    // Priorités
    const PRIORITIES = [
        'low' => 'Faible',
        'normal' => 'Normale',
        'high' => 'Élevée',
        'urgent' => 'Urgente'
    ];
    
    // Canaux de notification
    const CHANNELS = [
        'web' => 'Web',
        'email' => 'Email',
        'push' => 'Push Mobile',
        'sms' => 'SMS'
    ];
    
    public function __construct($user_id = null, $ecole_id = null) {
        $database = new Database();
        $this->db = $database->getConnection();
        
        $this->user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        $this->ecole_id = $ecole_id ?? ($_SESSION['ecole_id'] ?? null);
    }
    
    /**
     * Créer une nouvelle notification
     * 
     * @param string $type Type de notification
     * @param string $title Titre de la notification
     * @param string $message Message de la notification
     * @param array $data Données supplémentaires (JSON)
     * @param string $priority Priorité (low, normal, high, urgent)
     * @param array $channels Canaux de diffusion
     * @param string $action_url URL d'action
     * @param string $action_text Texte du bouton d'action
     * @param int $expires_in_minutes Minutes avant expiration
     * @return int|false ID de la notification créée ou false
     */
    public function createNotification($type, $title, $message, $data = [], $priority = 'normal', $channels = ['web'], $action_url = null, $action_text = null, $expires_in_minutes = null) {
        try {
            // Valider le type de notification
            if (!isset(self::TYPES[$type])) {
                throw new Exception("Type de notification invalide: $type");
            }
            
            // Déterminer la catégorie
            $category = $this->getCategoryFromType($type);
            
            // Calculer la date d'expiration
            $expires_at = null;
            if ($expires_in_minutes) {
                $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_in_minutes minutes"));
            }
            
            // Insérer la notification
            $query = "INSERT INTO notifications (
                user_id, ecole_id, type, category, title, message, data, 
                priority, channels, action_url, action_text, expires_at
            ) VALUES (
                :user_id, :ecole_id, :type, :category, :title, :message, :data,
                :priority, :channels, :action_url, :action_text, :expires_at
            )";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                'user_id' => $this->user_id,
                'ecole_id' => $this->ecole_id,
                'type' => $type,
                'category' => $category,
                'title' => $title,
                'message' => $message,
                'data' => json_encode($data),
                'priority' => $priority,
                'channels' => json_encode($channels),
                'action_url' => $action_url,
                'action_text' => $action_text,
                'expires_at' => $expires_at
            ]);
            
            if ($result) {
                $notification_id = $this->db->lastInsertId();
                
                // Envoyer via les canaux spécifiés
                $this->sendNotification($notification_id, $channels);
                
                // Log de l'action
                $this->logNotificationAction('created', $notification_id, $type);
                
                return $notification_id;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Erreur création notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Créer une notification avec template
     * 
     * @param string $type Type de notification
     * @param array $variables Variables pour le template
     * @param array $options Options supplémentaires
     * @return int|false ID de la notification créée
     */
    public function createNotificationFromTemplate($type, $variables = [], $options = []) {
        try {
            // Récupérer le template
            $template = $this->getTemplate($type);
            if (!$template) {
                throw new Exception("Template non trouvé pour le type: $type");
            }
            
            // Remplacer les variables dans le template
            $title = $this->replaceVariables($template['title_template'], $variables);
            $message = $this->replaceVariables($template['message_template'], $variables);
            
            // Options par défaut
            $default_options = [
                'priority' => 'normal',
                'channels' => ['web'],
                'action_url' => null,
                'action_text' => null,
                'expires_in_minutes' => null
            ];
            
            $options = array_merge($default_options, $options);
            
            return $this->createNotification(
                $type,
                $title,
                $message,
                $variables,
                $options['priority'],
                $options['channels'],
                $options['action_url'],
                $options['action_text'],
                $options['expires_in_minutes']
            );
            
        } catch (Exception $e) {
            error_log("Erreur création notification template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Créer une notification pour plusieurs utilisateurs
     * 
     * @param array $user_ids IDs des utilisateurs
     * @param string $type Type de notification
     * @param string $title Titre
     * @param string $message Message
     * @param array $options Options
     * @return array IDs des notifications créées
     */
    public function createBulkNotification($user_ids, $type, $title, $message, $options = []) {
        $notification_ids = [];
        
        foreach ($user_ids as $user_id) {
            $manager = new self($user_id, $this->ecole_id);
            $notification_id = $manager->createNotification($type, $title, $message, $options['data'] ?? [], $options['priority'] ?? 'normal', $options['channels'] ?? ['web']);
            
            if ($notification_id) {
                $notification_ids[] = $notification_id;
            }
        }
        
        return $notification_ids;
    }
    
    /**
     * Récupérer les notifications d'un utilisateur
     * 
     * @param int $user_id ID de l'utilisateur
     * @param array $filters Filtres (status, type, category, limit, offset)
     * @return array Liste des notifications
     */
    public function getUserNotifications($user_id = null, $filters = []) {
        $user_id = $user_id ?? $this->user_id;
        
        $default_filters = [
            'status' => null,
            'type' => null,
            'category' => null,
            'limit' => 50,
            'offset' => 0,
            'unread_only' => false
        ];
        
        $filters = array_merge($default_filters, $filters);
        
        try {
            $where_conditions = ["n.user_id = :user_id", "n.ecole_id = :ecole_id"];
            $params = ['user_id' => $user_id, 'ecole_id' => $this->ecole_id];
            
            if ($filters['status']) {
                $where_conditions[] = "n.status = :status";
                $params['status'] = $filters['status'];
            }
            
            if ($filters['type']) {
                $where_conditions[] = "n.type = :type";
                $params['type'] = $filters['type'];
            }
            
            if ($filters['category']) {
                $where_conditions[] = "n.category = :category";
                $params['category'] = $filters['category'];
            }
            
            if ($filters['unread_only']) {
                $where_conditions[] = "n.status = 'unread'";
            }
            
            $where_sql = implode(' AND ', $where_conditions);
            
            $query = "SELECT n.*
                      FROM notifications n
                      WHERE $where_sql
                      AND (n.expires_at IS NULL OR n.expires_at > NOW())
                      ORDER BY n.created_at DESC
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', (int)$filters['limit'], PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$filters['offset'], PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            $stmt->execute();
            $notifications = $stmt->fetchAll();
            
            // Décoder les données JSON
            foreach ($notifications as &$notification) {
                $notification['data'] = json_decode($notification['data'], true) ?? [];
                $notification['channels'] = json_decode($notification['channels'], true) ?? [];
            }
            
            return $notifications;
            
        } catch (Exception $e) {
            error_log("Erreur récupération notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Marquer une notification comme lue
     * 
     * @param int $notification_id ID de la notification
     * @param int $user_id ID de l'utilisateur
     * @return bool Succès
     */
    public function markAsRead($notification_id, $user_id = null) {
        $user_id = $user_id ?? $this->user_id;
        
        try {
            $query = "UPDATE notifications 
                      SET status = 'read', read_at = NOW() 
                      WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute(['id' => $notification_id, 'user_id' => $user_id]);
            
            if ($result) {
                $this->logNotificationAction('read', $notification_id);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Erreur marquage notification lue: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marquer toutes les notifications comme lues
     * 
     * @param int $user_id ID de l'utilisateur
     * @param string $type Type spécifique (optionnel)
     * @return bool Succès
     */
    public function markAllAsRead($user_id = null, $type = null) {
        $user_id = $user_id ?? $this->user_id;
        
        try {
            $where_conditions = ["user_id = :user_id", "ecole_id = :ecole_id", "status = 'unread'"];
            $params = ['user_id' => $user_id, 'ecole_id' => $this->ecole_id];
            
            if ($type) {
                $where_conditions[] = "type = :type";
                $params['type'] = $type;
            }
            
            $where_sql = implode(' AND ', $where_conditions);
            
            $query = "UPDATE notifications 
                      SET status = 'read', read_at = NOW() 
                      WHERE $where_sql";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                $this->logNotificationAction('mark_all_read', null, $type);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Erreur marquage toutes notifications lues: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Archiver une notification
     * 
     * @param int $notification_id ID de la notification
     * @param int $user_id ID de l'utilisateur
     * @return bool Succès
     */
    public function archiveNotification($notification_id, $user_id = null) {
        $user_id = $user_id ?? $this->user_id;
        
        try {
            $query = "UPDATE notifications 
                      SET status = 'archived', archived_at = NOW() 
                      WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute(['id' => $notification_id, 'user_id' => $user_id]);
            
            if ($result) {
                $this->logNotificationAction('archived', $notification_id);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Erreur archivage notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Supprimer une notification
     * 
     * @param int $notification_id ID de la notification
     * @param int $user_id ID de l'utilisateur
     * @return bool Succès
     */
    public function deleteNotification($notification_id, $user_id = null) {
        $user_id = $user_id ?? $this->user_id;
        
        try {
            $query = "DELETE FROM notifications 
                      WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute(['id' => $notification_id, 'user_id' => $user_id]);
            
            if ($result) {
                $this->logNotificationAction('deleted', $notification_id);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Erreur suppression notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir le nombre de notifications non lues
     * 
     * @param int $user_id ID de l'utilisateur
     * @param string $type Type spécifique (optionnel)
     * @return int Nombre de notifications non lues
     */
    public function getUnreadCount($user_id = null, $type = null) {
        $user_id = $user_id ?? $this->user_id;
        
        try {
            $where_conditions = ["user_id = :user_id", "ecole_id = :ecole_id", "status = 'unread'"];
            $params = ['user_id' => $user_id, 'ecole_id' => $this->ecole_id];
            
            if ($type) {
                $where_conditions[] = "type = :type";
                $params['type'] = $type;
            }
            
            $where_sql = implode(' AND ', $where_conditions);
            
            $query = "SELECT COUNT(*) as count 
                      FROM notifications 
                      WHERE $where_sql
                      AND (expires_at IS NULL OR expires_at > NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return (int)$result['count'];
            
        } catch (Exception $e) {
            error_log("Erreur comptage notifications non lues: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtenir les statistiques de notifications
     * 
     * @param int $user_id ID de l'utilisateur
     * @return array Statistiques
     */
    public function getNotificationStats($user_id = null) {
        $user_id = $user_id ?? $this->user_id;
        
        try {
            $query = "SELECT 
                         COUNT(*) as total,
                         SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
                         SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                         SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived,
                         COUNT(DISTINCT type) as unique_types,
                         MAX(created_at) as last_notification
                      FROM notifications 
                      WHERE user_id = :user_id AND ecole_id = :ecole_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id, 'ecole_id' => $this->ecole_id]);
            $stats = $stmt->fetch();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Erreur statistiques notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Nettoyer les anciennes notifications
     * 
     * @param int $days_old Jours d'ancienneté
     * @return bool Succès
     */
    public function cleanOldNotifications($days_old = 90) {
        try {
            $this->db->beginTransaction();
            
            // Supprimer les notifications expirées
            $query1 = "DELETE FROM notifications 
                       WHERE expires_at IS NOT NULL 
                       AND expires_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $stmt1 = $this->db->prepare($query1);
            $stmt1->execute(['days' => $days_old]);
            
            // Archiver les notifications lues anciennes
            $query2 = "UPDATE notifications 
                       SET status = 'archived', archived_at = NOW() 
                       WHERE status = 'read' 
                       AND read_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $stmt2 = $this->db->prepare($query2);
            $stmt2->execute(['days' => $days_old]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur nettoyage notifications: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envoyer une notification via les canaux spécifiés
     * 
     * @param int $notification_id ID de la notification
     * @param array $channels Canaux de diffusion
     * @return bool Succès
     */
    private function sendNotification($notification_id, $channels) {
        try {
            // Récupérer les détails de la notification
            $query = "SELECT n.*, u.email, u.prenom, u.nom 
                      FROM notifications n
                      JOIN utilisateurs u ON n.user_id = u.id
                      WHERE n.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $notification_id]);
            $notification = $stmt->fetch();
            
            if (!$notification) {
                return false;
            }
            
            // Envoyer via chaque canal
            foreach ($channels as $channel) {
                $this->sendViaChannel($notification, $channel);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur envoi notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envoyer une notification via un canal spécifique
     * 
     * @param array $notification Détails de la notification
     * @param string $channel Canal de diffusion
     * @return bool Succès
     */
    private function sendViaChannel($notification, $channel) {
        try {
            switch ($channel) {
                case 'web':
                    // Les notifications web sont déjà stockées en base
                    return true;
                    
                case 'email':
                    return $this->sendEmailNotification($notification);
                    
                case 'push':
                    return $this->sendPushNotification($notification);
                    
                case 'sms':
                    return $this->sendSMSNotification($notification);
                    
                default:
                    error_log("Canal de notification non supporté: $channel");
                    return false;
            }
            
        } catch (Exception $e) {
            error_log("Erreur envoi canal $channel: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envoyer une notification par email
     * 
     * @param array $notification Détails de la notification
     * @return bool Succès
     */
    private function sendEmailNotification($notification) {
        try {
            // Vérifier les préférences utilisateur
            if (!$this->isEmailEnabled($notification['user_id'], $notification['type'])) {
                return true; // Email désactivé pour ce type
            }
            
            // Récupérer le template email
            $template = $this->getTemplate($notification['type']);
            if (!$template || !$template['email_subject_template']) {
                return false; // Pas de template email
            }
            
            $data = json_decode($notification['data'], true) ?? [];
            $subject = $this->replaceVariables($template['email_subject_template'], $data);
            $body = $this->replaceVariables($template['email_body_template'], $data);
            
            // Envoyer l'email (intégration avec votre système email existant)
            // TODO: Intégrer avec EmailManager existant
            
            // Log de l'envoi
            $this->logNotificationSent($notification['id'], 'email', $notification['email'], 'sent');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur envoi email notification: " . $e->getMessage());
            $this->logNotificationSent($notification['id'], 'email', $notification['email'], 'failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envoyer une notification push
     * 
     * @param array $notification Détails de la notification
     * @return bool Succès
     */
    private function sendPushNotification($notification) {
        // TODO: Implémenter les notifications push
        // Intégration avec Firebase Cloud Messaging ou similaire
        return true;
    }
    
    /**
     * Envoyer une notification SMS
     * 
     * @param array $notification Détails de la notification
     * @return bool Succès
     */
    private function sendSMSNotification($notification) {
        // TODO: Implémenter les notifications SMS
        // Intégration avec un service SMS
        return true;
    }
    
    /**
     * Récupérer un template de notification
     * 
     * @param string $type Type de notification
     * @return array|false Template ou false
     */
    private function getTemplate($type) {
        try {
            $query = "SELECT * FROM notification_templates WHERE type = :type AND is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['type' => $type]);
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Erreur récupération template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remplacer les variables dans un template
     * 
     * @param string $template Template avec variables {variable}
     * @param array $variables Variables de remplacement
     * @return string Template avec variables remplacées
     */
    private function replaceVariables($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Déterminer la catégorie à partir du type
     * 
     * @param string $type Type de notification
     * @return string Catégorie
     */
    private function getCategoryFromType($type) {
        $category_map = [
            'student_registered' => 'academic',
            'student_class_changed' => 'academic',
            'course_assigned' => 'academic',
            'teacher_assigned' => 'academic',
            'student_promoted' => 'academic',
            'student_graduated' => 'academic',
            
            'payment_received' => 'financial',
            'payment_overdue' => 'financial',
            'fees_updated' => 'financial',
            'refund_processed' => 'financial',
            'invoice_generated' => 'financial',
            
            'user_created' => 'user',
            'user_updated' => 'user',
            'user_deleted' => 'user',
            'login_suspicious' => 'security',
            'password_changed' => 'security',
            
            'school_created' => 'school',
            'school_validated' => 'school',
            'school_updated' => 'school',
            'school_logo_changed' => 'school',
            
            'student_absent' => 'alert',
            'class_capacity' => 'alert',
            'low_grades' => 'alert',
            'system_maintenance' => 'system',
            'backup_completed' => 'system'
        ];
        
        return $category_map[$type] ?? 'system';
    }
    
    /**
     * Vérifier si l'email est activé pour un type de notification
     * 
     * @param int $user_id ID de l'utilisateur
     * @param string $type Type de notification
     * @return bool Email activé
     */
    private function isEmailEnabled($user_id, $type) {
        try {
            $query = "SELECT email_enabled FROM notification_preferences 
                      WHERE user_id = :user_id AND type = :type";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['user_id' => $user_id, 'type' => $type]);
            $pref = $stmt->fetch();
            
            return $pref ? (bool)$pref['email_enabled'] : true; // Par défaut activé
            
        } catch (Exception $e) {
            error_log("Erreur vérification préférences email: " . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Logger l'envoi d'une notification
     * 
     * @param int $notification_id ID de la notification
     * @param string $channel Canal utilisé
     * @param string $recipient Destinataire
     * @param string $status Statut (sent, failed, bounced)
     * @param string $error_message Message d'erreur (optionnel)
     * @return bool Succès
     */
    private function logNotificationSent($notification_id, $channel, $recipient, $status, $error_message = null) {
        try {
            $query = "INSERT INTO notification_logs 
                      (notification_id, channel, recipient, status, error_message, sent_at) 
                      VALUES (:notification_id, :channel, :recipient, :status, :error_message, :sent_at)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                'notification_id' => $notification_id,
                'channel' => $channel,
                'recipient' => $recipient,
                'status' => $status,
                'error_message' => $error_message,
                'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Erreur log envoi notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Logger une action sur une notification
     * 
     * @param string $action Action effectuée
     * @param int $notification_id ID de la notification
     * @param string $type Type de notification (optionnel)
     * @return bool Succès
     */
    private function logNotificationAction($action, $notification_id, $type = null) {
        try {
            $details = [
                'action' => $action,
                'notification_id' => $notification_id,
                'type' => $type,
                'user_id' => $this->user_id,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Utiliser la fonction de log existante
            logUserAction("notification_$action", json_encode($details), $this->user_id);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur log action notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir les types de notifications disponibles
     * 
     * @return array Types de notifications
     */
    public static function getAvailableTypes() {
        return self::TYPES;
    }
    
    /**
     * Obtenir les catégories disponibles
     * 
     * @return array Catégories
     */
    public static function getAvailableCategories() {
        return self::CATEGORIES;
    }
    
    /**
     * Obtenir les priorités disponibles
     * 
     * @return array Priorités
     */
    public static function getAvailablePriorities() {
        return self::PRIORITIES;
    }
    
    /**
     * Obtenir les canaux disponibles
     * 
     * @return array Canaux
     */
    public static function getAvailableChannels() {
        return self::CHANNELS;
    }
}
?>
