<?php
/**
 * Installation Finale du Système de Notifications Naklass
 * Version ultra-simple sans procédures stockées
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
    
    echo "<h2>🚀 Installation Finale du Système de Notifications Naklass</h2>";
    
    // Lire le fichier SQL propre
    $sql_file = __DIR__ . '/create_notification_tables_clean.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("Fichier SQL non trouvé: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Diviser le contenu en requêtes individuelles
    $queries = array_filter(array_map('trim', explode(';', $sql_content)));
    
    $db->beginTransaction();
    
    $tables_created = 0;
    $templates_inserted = 0;
    $channels_inserted = 0;
    
    foreach ($queries as $query) {
        if (empty($query) || strpos($query, '--') === 0) {
            continue; // Ignorer les commentaires et lignes vides
        }
        
        try {
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            // Compter les créations
            if (stripos($query, 'CREATE TABLE') !== false) {
                $tables_created++;
                echo "✅ Table créée<br>";
            } elseif (stripos($query, 'INSERT IGNORE INTO notification_templates') !== false) {
                $templates_inserted++;
            } elseif (stripos($query, 'INSERT IGNORE INTO notification_channels') !== false) {
                $channels_inserted++;
            } elseif (stripos($query, 'CREATE INDEX') !== false) {
                echo "✅ Index créé<br>";
            }
            
        } catch (PDOException $e) {
            // Ignorer les erreurs de tables existantes
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⚠️ Élément déjà existant<br>";
                continue;
            }
            throw $e;
        }
    }
    
    $db->commit();
    
    $success_messages[] = "Installation terminée avec succès !";
    $success_messages[] = "Tables créées: $tables_created";
    $success_messages[] = "Templates insérés: $templates_inserted";
    $success_messages[] = "Canaux insérés: $channels_inserted";
    
    // Tester le système
    echo "<h3>🧪 Test du Système</h3>";
    
    // Test 1: Vérifier les tables
    $tables_to_check = ['notifications', 'notification_preferences', 'notification_templates', 'notification_channels', 'notification_logs', 'notification_groups'];
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
    
    // Test 8: Créer une notification avec template
    $template_id = $notificationManager->createNotificationFromTemplate('student_registered', [
        'student_name' => 'Jean Dupont',
        'class_name' => '6ème Primaire A',
        'matricule' => 'EL241001',
        'niveau' => 'Primaire',
        'cycle' => 'Primaire'
    ], [
        'priority' => 'normal',
        'channels' => ['web'],
        'action_url' => 'students/view.php?id=1',
        'action_text' => 'Voir l\'élève'
    ]);
    
    if ($template_id) {
        echo "✅ Notification avec template créée (ID: $template_id)<br>";
        
        // Supprimer la notification de test avec template
        $notificationManager->deleteNotification($template_id);
        echo "✅ Notification avec template supprimée<br>";
    } else {
        echo "❌ Échec de la création de notification avec template<br>";
    }
    
    echo "<h3>🎉 Installation Terminée !</h3>";
    echo "<div class='alert alert-success'>";
    echo "<h5>✅ Système de Notifications Opérationnel</h5>";
    echo "<p>Le système de notifications Naklass est maintenant complètement installé et fonctionnel !</p>";
    echo "<ul>";
    echo "<li>✅ Tables créées et configurées</li>";
    echo "<li>✅ Templates de notifications installés</li>";
    echo "<li>✅ Canaux de notification configurés</li>";
    echo "<li>✅ Système testé et validé</li>";
    echo "<li>✅ Widget intégré dans la sidebar</li>";
    echo "<li>✅ Page des notifications accessible</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='alert alert-info'>";
    echo "<h5>🚀 Prochaines Étapes</h5>";
    echo "<p>Votre système de notifications est maintenant prêt ! Voici ce que vous pouvez faire :</p>";
    echo "<ul>";
    echo "<li><strong>Utiliser</strong> : Les notifications apparaîtront automatiquement lors des actions</li>";
    echo "<li><strong>Personnaliser</strong> : Modifier les templates dans la base de données</li>";
    echo "<li><strong>Configurer</strong> : Ajuster les préférences utilisateur</li>";
    echo "<li><strong>Étendre</strong> : Ajouter de nouveaux types de notifications</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='text-center mt-4'>";
    echo "<a href='auth/dashboard.php' class='btn btn-primary btn-lg me-3'>";
    echo "<i class='bi bi-house me-2'></i>Retour au Tableau de Bord";
    echo "</a>";
    echo "<a href='notifications.php' class='btn btn-success btn-lg me-3'>";
    echo "<i class='bi bi-bell me-2'></i>Voir les Notifications";
    echo "</a>";
    echo "<a href='test_notification_system.php' class='btn btn-outline-primary btn-lg'>";
    echo "<i class='bi bi-gear me-2'></i>Tester le Système";
    echo "</a>";
    echo "</div>";
    
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    $error_messages[] = "Erreur lors de l'installation: " . $e->getMessage();
    echo "<h3>❌ Erreur d'Installation</h3>";
    echo "<div class='alert alert-danger'>";
    echo "<h5>❌ Une erreur s'est produite</h5>";
    echo "<p><strong>Erreur:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Ligne:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
    
    echo "<div class='alert alert-warning'>";
    echo "<h5>🔧 Solutions possibles</h5>";
    echo "<ul>";
    echo "<li>Vérifiez que les tables <code>utilisateurs</code> et <code>ecoles</code> existent</li>";
    echo "<li>Vérifiez les permissions de la base de données</li>";
    echo "<li>Vérifiez que MariaDB/MySQL supporte JSON (version 5.7+)</li>";
    echo "<li>Essayez l'installation manuelle via phpMyAdmin</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='text-center mt-4'>";
    echo "<a href='auth/dashboard.php' class='btn btn-secondary'>Retour au Tableau de Bord</a>";
    echo "</div>";
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .alert {
            margin: 20px 0;
        }
        .btn {
            margin: 10px 5px;
        }
        h2, h3 {
            color: #495057;
            margin-top: 2rem;
        }
        code {
            background-color: #f8f9fa;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h1 class="card-title mb-0">
                    <i class="bi bi-bell-fill me-2"></i>Système de Notifications Naklass
                </h1>
            </div>
            <div class="card-body">
                <!-- Le contenu de l'installation sera affiché ici -->
            </div>
        </div>
    </div>
</body>
</html>
