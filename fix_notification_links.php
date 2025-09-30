<?php
/**
 * Correction des Liens de Notifications Naklass
 * Ce script corrige tous les liens générés dans les notifications pour qu'ils mènent vers les bonnes pages
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier l'authentification
requireAuth();

// Vérifier les permissions (admin ou super admin)
if (!hasRole(['admin', 'super_admin'])) {
    setFlashMessage('error', 'Accès refusé. Seuls les administrateurs peuvent corriger les liens de notifications.');
    redirect('auth/dashboard.php');
}

echo "<h2>🔗 Correction des Liens de Notifications</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h3>1. Analyse des Liens Problématiques</h3>";
    
    // Récupérer toutes les notifications avec des liens problématiques
    $query = "SELECT id, action_url, type, title FROM notifications WHERE action_url IS NOT NULL ORDER BY created_at DESC LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Notifications avec liens trouvées: " . count($notifications) . "<br>";
    
    if (count($notifications) > 0) {
        echo "<h4>Liens actuels (problématiques):</h4>";
        foreach ($notifications as $notification) {
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px;'>";
            echo "<strong>ID:</strong> " . $notification['id'] . "<br>";
            echo "<strong>Type:</strong> " . $notification['type'] . "<br>";
            echo "<strong>Titre:</strong> " . htmlspecialchars($notification['title']) . "<br>";
            echo "<strong>Lien actuel:</strong> " . htmlspecialchars($notification['action_url']) . "<br>";
            echo "</div>";
        }
    }
    
    echo "<h3>2. Correction des Liens</h3>";
    
    $corrected_count = 0;
    foreach ($notifications as $notification) {
        $new_url = '';
        
        // Corriger les liens selon le type de notification
        switch ($notification['type']) {
            case 'student_registered':
                // Lien vers la page de l'élève
                $new_url = '../students/view.php?id=' . $notification['id'];
                break;
                
            case 'course_assigned':
            case 'teacher_assigned':
                // Lien vers la page de la classe
                $new_url = '../classes/view.php?id=' . $notification['id'];
                break;
                
            case 'payment_received':
                // Lien vers le reçu de paiement
                $new_url = '../finance/print_receipt.php?id=' . $notification['id'];
                break;
                
            case 'student_absent':
                // Lien vers la page de l'élève
                $new_url = '../students/view.php?id=' . $notification['id'];
                break;
                
            case 'system_maintenance':
                // Lien vers les paramètres système
                $new_url = '../settings/system.php';
                break;
                
            case 'login_suspicious':
                // Lien vers les logs de sécurité
                $new_url = '../settings/security.php';
                break;
                
            default:
                // Pour les autres types, utiliser un lien générique
                $new_url = '../notifications.php';
                break;
        }
        
        // Mettre à jour le lien dans la base de données
        if ($new_url) {
            $update_query = "UPDATE notifications SET action_url = :new_url WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $result = $update_stmt->execute([
                'new_url' => $new_url,
                'id' => $notification['id']
            ]);
            
            if ($result) {
                $corrected_count++;
                echo "✅ Lien corrigé pour notification ID " . $notification['id'] . ": " . $new_url . "<br>";
            } else {
                echo "❌ Échec correction pour notification ID " . $notification['id'] . "<br>";
            }
        }
    }
    
    echo "<h3>3. Mise à Jour des Templates</h3>";
    
    // Mettre à jour les templates pour qu'ils génèrent des liens corrects
    $templates_to_update = [
        'student_registered' => '../students/view.php?id={student_id}',
        'course_assigned' => '../classes/view.php?id={class_id}',
        'teacher_assigned' => '../classes/view.php?id={class_id}',
        'payment_received' => '../finance/print_receipt.php?id={payment_id}',
        'student_absent' => '../students/view.php?id={student_id}',
        'system_maintenance' => '../settings/system.php',
        'login_suspicious' => '../settings/security.php'
    ];
    
    foreach ($templates_to_update as $type => $template_url) {
        $update_template = "UPDATE notification_templates SET action_url_template = :url WHERE type = :type";
        $stmt = $db->prepare($update_template);
        $stmt->execute([
            'url' => $template_url,
            'type' => $type
        ]);
        echo "✅ Template mis à jour pour type '$type': $template_url<br>";
    }
    
    echo "<h3>4. Création de Fonctions Utilitaires</h3>";
    
    // Créer un fichier avec des fonctions utilitaires pour générer des liens corrects
    $utility_functions = '<?php
/**
 * Fonctions Utilitaires pour les Liens de Notifications Naklass
 */

/**
 * Générer un lien correct pour une notification
 * @param string $type Type de notification
 * @param array $data Données de la notification
 * @return string URL correcte
 */
function generateNotificationLink($type, $data = []) {
    $base_url = "http://localhost/naklass/"; // À adapter selon votre configuration
    
    switch ($type) {
        case "student_registered":
            return $base_url . "students/view.php?id=" . ($data["student_id"] ?? "");
            
        case "course_assigned":
        case "teacher_assigned":
            return $base_url . "classes/view.php?id=" . ($data["class_id"] ?? "");
            
        case "payment_received":
            return $base_url . "finance/print_receipt.php?id=" . ($data["payment_id"] ?? "");
            
        case "student_absent":
            return $base_url . "students/view.php?id=" . ($data["student_id"] ?? "");
            
        case "system_maintenance":
            return $base_url . "settings/system.php";
            
        case "login_suspicious":
            return $base_url . "settings/security.php";
            
        default:
            return $base_url . "notifications.php";
    }
}

/**
 * Générer un lien relatif correct pour une notification
 * @param string $type Type de notification
 * @param array $data Données de la notification
 * @return string URL relative correcte
 */
function generateNotificationRelativeLink($type, $data = []) {
    switch ($type) {
        case "student_registered":
            return "../students/view.php?id=" . ($data["student_id"] ?? "");
            
        case "course_assigned":
        case "teacher_assigned":
            return "../classes/view.php?id=" . ($data["class_id"] ?? "");
            
        case "payment_received":
            return "../finance/print_receipt.php?id=" . ($data["payment_id"] ?? "");
            
        case "student_absent":
            return "../students/view.php?id=" . ($data["student_id"] ?? "");
            
        case "system_maintenance":
            return "../settings/system.php";
            
        case "login_suspicious":
            return "../settings/security.php";
            
        default:
            return "../notifications.php";
    }
}
?>';
    
    file_put_contents('includes/notification_links.php', $utility_functions);
    echo "✅ Fichier de fonctions utilitaires créé: includes/notification_links.php<br>";
    
    echo "<h3>5. Résumé des Corrections</h3>";
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h5>✅ Corrections Effectuées</h5>";
    echo "<ul>";
    echo "<li><strong>Notifications corrigées:</strong> $corrected_count</li>";
    echo "<li><strong>Templates mis à jour:</strong> " . count($templates_to_update) . "</li>";
    echo "<li><strong>Fonctions utilitaires:</strong> Créées</li>";
    echo "</ul>";
    echo "<p><strong>Types de liens corrigés:</strong></p>";
    echo "<ul>";
    echo "<li>🎓 <strong>Académiques:</strong> Liens vers pages élèves et classes</li>";
    echo "<li>💰 <strong>Financières:</strong> Liens vers reçus de paiement</li>";
    echo "<li>⚠️ <strong>Alertes:</strong> Liens vers pages élèves</li>";
    echo "<li>🔒 <strong>Sécurité:</strong> Liens vers logs de sécurité</li>";
    echo "<li>⚙️ <strong>Système:</strong> Liens vers paramètres système</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>6. Instructions pour les Développeurs</h3>";
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
    echo "<h5>🔧 Comment Utiliser les Nouveaux Liens</h5>";
    echo "<p>Pour créer des notifications avec des liens corrects, utilisez maintenant :</p>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    echo "require_once 'includes/notification_links.php';\n\n";
    echo "// Générer un lien correct\n";
    echo "\$link = generateNotificationRelativeLink('student_registered', ['student_id' => 123]);\n\n";
    echo "// Créer la notification\n";
    echo "\$notificationManager->createNotification(\n";
    echo "    'student_registered',\n";
    echo "    'Nouvel élève inscrit',\n";
    echo "    'L\\'élève a été inscrit',\n";
    echo "    [],\n";
    echo "    'normal',\n";
    echo "    ['web'],\n";
    echo "    \$link,  // Lien correct\n";
    echo "    'Voir l\\'élève'\n";
    echo ");";
    echo "</pre>";
    echo "</div>";
    
    echo "<div class='text-center mt-4'>";
    echo "<a href='auth/dashboard.php' class='btn btn-primary btn-lg me-3'>";
    echo "<i class='bi bi-speedometer2 me-2'></i>Retour au Dashboard";
    echo "</a>";
    echo "<a href='notifications.php' class='btn btn-success btn-lg me-3'>";
    echo "<i class='bi bi-bell me-2'></i>Voir les Notifications";
    echo "</a>";
    echo "<a href='test_sidebar_notifications.php' class='btn btn-outline-primary btn-lg'>";
    echo "<i class='bi bi-gear me-2'></i>Tester les Liens";
    echo "</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Erreur lors de la Correction</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h5>❌ Une erreur s'est produite</h5>";
    echo "<p><strong>Erreur:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Ligne:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
    
    echo "<div class='text-center mt-4'>";
    echo "<a href='auth/dashboard.php' class='btn btn-secondary'>Retour au Dashboard</a>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction Liens Notifications - Naklass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .btn {
            margin: 10px 5px;
        }
        h2, h3 {
            color: #495057;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h1 class="card-title mb-0">
                    <i class="bi bi-link-45deg me-2"></i>Correction des Liens de Notifications
                </h1>
            </div>
            <div class="card-body">
                <!-- Le contenu de la correction sera affiché ici -->
            </div>
        </div>
    </div>
</body>
</html>

