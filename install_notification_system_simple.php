<?php
/**
 * Installation Simplifiée du Système de Notifications Naklass
 * Version sans procédures stockées pour éviter les erreurs
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier l'authentification
requireAuth();

// Vérifier les permissions (admin ou super admin)
if (!hasRole(['admin', 'super_admin'])) {
    setFlashMessage('error', 'Accès refusé. Seuls les administrateurs peuvent installer le système de notifications.');
    redirect('auth/dashboard.php');
}

$success_messages = [];
$error_messages = [];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🚀 Installation Simplifiée du Système de Notifications Naklass</h2>";
    
    $db->beginTransaction();
    
    // 1. Créer la table principale des notifications
    echo "<h3>📋 Création des Tables</h3>";
    
    $create_notifications = "
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ecole_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        category VARCHAR(30) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        data JSON DEFAULT NULL,
        priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
        status ENUM('unread', 'read', 'archived') DEFAULT 'unread',
        channels JSON DEFAULT NULL,
        action_url VARCHAR(500) DEFAULT NULL,
        action_text VARCHAR(100) DEFAULT NULL,
        expires_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL DEFAULT NULL,
        archived_at TIMESTAMP NULL DEFAULT NULL,
        
        INDEX idx_user_id (user_id),
        INDEX idx_ecole_id (ecole_id),
        INDEX idx_type (type),
        INDEX idx_category (category),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_expires_at (expires_at),
        
        FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($create_notifications);
    echo "✅ Table 'notifications' créée<br>";
    
    // 2. Créer la table des préférences
    $create_preferences = "
    CREATE TABLE IF NOT EXISTS notification_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ecole_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        email_enabled BOOLEAN DEFAULT TRUE,
        web_enabled BOOLEAN DEFAULT TRUE,
        push_enabled BOOLEAN DEFAULT FALSE,
        frequency ENUM('immediate', 'daily', 'weekly', 'never') DEFAULT 'immediate',
        quiet_hours_start TIME DEFAULT NULL,
        quiet_hours_end TIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_user_type (user_id, type),
        INDEX idx_user_id (user_id),
        INDEX idx_ecole_id (ecole_id),
        
        FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($create_preferences);
    echo "✅ Table 'notification_preferences' créée<br>";
    
    // 3. Créer la table des templates
    $create_templates = "
    CREATE TABLE IF NOT EXISTS notification_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        category VARCHAR(30) NOT NULL,
        title_template VARCHAR(255) NOT NULL,
        message_template TEXT NOT NULL,
        email_subject_template VARCHAR(255) DEFAULT NULL,
        email_body_template TEXT DEFAULT NULL,
        variables JSON DEFAULT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_type (type),
        INDEX idx_category (category),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($create_templates);
    echo "✅ Table 'notification_templates' créée<br>";
    
    // 4. Créer la table des canaux
    $create_channels = "
    CREATE TABLE IF NOT EXISTS notification_channels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        description TEXT DEFAULT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        config JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_name (name),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($create_channels);
    echo "✅ Table 'notification_channels' créée<br>";
    
    // 5. Créer la table des logs
    $create_logs = "
    CREATE TABLE IF NOT EXISTS notification_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_id INT NOT NULL,
        channel VARCHAR(50) NOT NULL,
        recipient VARCHAR(255) NOT NULL,
        status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
        error_message TEXT DEFAULT NULL,
        sent_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_notification_id (notification_id),
        INDEX idx_channel (channel),
        INDEX idx_status (status),
        INDEX idx_sent_at (sent_at),
        
        FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($create_logs);
    echo "✅ Table 'notification_logs' créée<br>";
    
    // 6. Insérer les canaux par défaut
    echo "<h3>📡 Insertion des Canaux</h3>";
    
    $channels = [
        ['web', 'Notification Web', 'Notifications affichées dans l\'interface web', TRUE],
        ['email', 'Email', 'Notifications envoyées par email', TRUE],
        ['push', 'Push Mobile', 'Notifications push pour les appareils mobiles', FALSE],
        ['sms', 'SMS', 'Notifications par SMS (futur)', FALSE]
    ];
    
    foreach ($channels as $channel) {
        $stmt = $db->prepare("INSERT IGNORE INTO notification_channels (name, display_name, description, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute($channel);
    }
    echo "✅ Canaux insérés<br>";
    
    // 7. Insérer les templates par défaut
    echo "<h3>📝 Insertion des Templates</h3>";
    
    $templates = [
        // Académiques
        ['student_registered', 'academic', 'Nouvel élève inscrit', 'L\'élève {student_name} a été inscrit en {class_name}', 'Nouvelle inscription - {student_name}', 'Bonjour,\n\nL\'élève {student_name} a été inscrit en {class_name}.\n\nCordialement,\nSystème Naklass'],
        ['student_class_changed', 'academic', 'Changement de classe', 'L\'élève {student_name} a été transféré de {old_class} vers {new_class}', 'Changement de classe - {student_name}', 'Bonjour,\n\nL\'élève {student_name} a été transféré de {old_class} vers {new_class}.\n\nCordialement,\nSystème Naklass'],
        ['course_assigned', 'academic', 'Cours assigné', 'Le cours {course_name} a été assigné à la classe {class_name}', 'Nouveau cours assigné', 'Bonjour,\n\nLe cours {course_name} a été assigné à la classe {class_name}.\n\nCordialement,\nSystème Naklass'],
        ['teacher_assigned', 'academic', 'Enseignant assigné', '{teacher_name} a été assigné comme professeur principal de {class_name}', 'Nouvel enseignant assigné', 'Bonjour,\n\n{teacher_name} a été assigné comme professeur principal de {class_name}.\n\nCordialement,\nSystème Naklass'],
        
        // Financières
        ['payment_received', 'financial', 'Paiement reçu', 'Paiement de {amount} reçu pour {student_name} - {payment_type}', 'Paiement reçu - {student_name}', 'Bonjour,\n\nUn paiement de {amount} a été reçu pour {student_name}.\nType: {payment_type}\n\nCordialement,\nSystème Naklass'],
        ['payment_overdue', 'financial', 'Paiement en retard', 'Le paiement de {student_name} est en retard de {days} jours', 'Paiement en retard - {student_name}', 'Bonjour,\n\nLe paiement de {student_name} est en retard de {days} jours.\nMontant dû: {amount}\n\nCordialement,\nSystème Naklass'],
        ['fees_updated', 'financial', 'Frais mis à jour', 'Les frais scolaires ont été mis à jour pour {class_name}', 'Frais scolaires mis à jour', 'Bonjour,\n\nLes frais scolaires ont été mis à jour pour {class_name}.\n\nCordialement,\nSystème Naklass'],
        
        // Utilisateurs
        ['user_created', 'user', 'Nouveau compte créé', 'Un nouveau compte a été créé pour {user_name} ({user_role})', 'Nouveau compte créé', 'Bonjour,\n\nUn nouveau compte a été créé pour {user_name} avec le rôle {user_role}.\n\nCordialement,\nSystème Naklass'],
        ['login_suspicious', 'security', 'Connexion suspecte', 'Connexion suspecte détectée pour {user_name} depuis {ip_address}', 'Connexion suspecte détectée', 'Bonjour,\n\nUne connexion suspecte a été détectée pour {user_name} depuis {ip_address}.\n\nCordialement,\nSystème Naklass'],
        ['password_changed', 'security', 'Mot de passe modifié', 'Le mot de passe de {user_name} a été modifié', 'Mot de passe modifié', 'Bonjour,\n\nLe mot de passe de {user_name} a été modifié.\n\nCordialement,\nSystème Naklass'],
        
        // École
        ['school_created', 'school', 'Nouvelle école créée', 'Une nouvelle école "{school_name}" a été créée', 'Nouvelle école créée', 'Bonjour,\n\nUne nouvelle école "{school_name}" a été créée.\n\nCordialement,\nSystème Naklass'],
        ['school_validated', 'school', 'École validée', 'L\'école "{school_name}" a été validée par le super administrateur', 'École validée', 'Bonjour,\n\nL\'école "{school_name}" a été validée par le super administrateur.\n\nCordialement,\nSystème Naklass'],
        
        // Alertes
        ['student_absent', 'alert', 'Absence répétée', 'L\'élève {student_name} a {absence_count} absences consécutives', 'Absence répétée - {student_name}', 'Bonjour,\n\nL\'élève {student_name} a {absence_count} absences consécutives.\n\nCordialement,\nSystème Naklass'],
        ['class_capacity', 'alert', 'Capacité de classe atteinte', 'La classe {class_name} a atteint {percentage}% de sa capacité', 'Capacité de classe atteinte', 'Bonjour,\n\nLa classe {class_name} a atteint {percentage}% de sa capacité.\n\nCordialement,\nSystème Naklass'],
        ['low_grades', 'alert', 'Notes faibles', 'L\'élève {student_name} a des notes en dessous de la moyenne', 'Notes faibles - {student_name}', 'Bonjour,\n\nL\'élève {student_name} a des notes en dessous de la moyenne.\n\nCordialement,\nSystème Naklass'],
        
        // Système
        ['system_maintenance', 'system', 'Maintenance système', 'Le système sera en maintenance de {start_time} à {end_time}', 'Maintenance système', 'Bonjour,\n\nLe système sera en maintenance de {start_time} à {end_time}.\n\nCordialement,\nSystème Naklass']
    ];
    
    foreach ($templates as $template) {
        $stmt = $db->prepare("INSERT IGNORE INTO notification_templates (type, category, title_template, message_template, email_subject_template, email_body_template) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute($template);
    }
    echo "✅ Templates insérés<br>";
    
    $db->commit();
    
    echo "<h3>🧪 Test du Système</h3>";
    
    // Test 1: Vérifier les tables
    $tables_to_check = ['notifications', 'notification_preferences', 'notification_templates', 'notification_channels', 'notification_logs'];
    foreach ($tables_to_check as $table) {
        $query = "SHOW TABLES LIKE '$table'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        if ($stmt->fetch()) {
            echo "✅ Table '$table' existe<br>";
        } else {
            echo "❌ Table '$table' manquante<br>";
        }
    }
    
    // Test 2: Créer une notification de test
    require_once 'includes/NotificationManager.php';
    $notificationManager = new NotificationManager();
    
    $test_notification_id = $notificationManager->createNotification(
        'system_maintenance',
        'Test du système de notifications',
        'Ceci est une notification de test pour vérifier le bon fonctionnement du système.',
        ['test' => true],
        'normal',
        ['web'],
        'notifications.php',
        'Voir les notifications',
        60 // Expire dans 1 heure
    );
    
    if ($test_notification_id) {
        echo "✅ Notification de test créée (ID: $test_notification_id)<br>";
        
        // Test 3: Récupérer les notifications
        $notifications = $notificationManager->getUserNotifications();
        if (count($notifications) > 0) {
            echo "✅ Récupération des notifications réussie (" . count($notifications) . " notifications)<br>";
        } else {
            echo "⚠️ Aucune notification récupérée<br>";
        }
        
        // Test 4: Marquer comme lue
        if ($notificationManager->markAsRead($test_notification_id)) {
            echo "✅ Marquage comme lue réussi<br>";
        } else {
            echo "❌ Échec du marquage comme lue<br>";
        }
        
        // Test 5: Supprimer la notification de test
        if ($notificationManager->deleteNotification($test_notification_id)) {
            echo "✅ Suppression de la notification de test réussie<br>";
        } else {
            echo "❌ Échec de la suppression<br>";
        }
        
    } else {
        echo "❌ Échec de la création de notification de test<br>";
    }
    
    // Test 6: Vérifier les templates
    $query = "SELECT COUNT(*) as count FROM notification_templates WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $template_count = $stmt->fetch()['count'];
    echo "✅ Templates actifs: $template_count<br>";
    
    // Test 7: Vérifier les canaux
    $query = "SELECT COUNT(*) as count FROM notification_channels WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $channel_count = $stmt->fetch()['count'];
    echo "✅ Canaux actifs: $channel_count<br>";
    
    echo "<h3>🎉 Installation Terminée !</h3>";
    echo "<p>Le système de notifications est maintenant opérationnel.</p>";
    echo "<p><a href='auth/dashboard.php' class='btn btn-primary'>Retour au Tableau de Bord</a></p>";
    
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    $error_messages[] = "Erreur lors de l'installation: " . $e->getMessage();
    echo "<h3>❌ Erreur d'Installation</h3>";
    echo "<p>Une erreur s'est produite: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='auth/dashboard.php' class='btn btn-secondary'>Retour au Tableau de Bord</a></p>";
}

// Afficher les messages de succès et d'erreur
if (!empty($success_messages)) {
    echo "<div class='alert alert-success'>";
    foreach ($success_messages as $message) {
        echo "<p>✅ " . htmlspecialchars($message) . "</p>";
    }
    echo "</div>";
}

if (!empty($error_messages)) {
    echo "<div class='alert alert-danger'>";
    foreach ($error_messages as $message) {
        echo "<p>❌ " . htmlspecialchars($message) . "</p>";
    }
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Système de Notifications - Naklass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .alert {
            margin: 20px 0;
        }
        .btn {
            margin: 10px 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">🚀 Système de Notifications Naklass</h1>
            </div>
            <div class="card-body">
                <!-- Le contenu de l'installation sera affiché ici -->
            </div>
        </div>
    </div>
</body>
</html>
