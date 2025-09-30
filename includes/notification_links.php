<?php
/**
 * Fonctions Utilitaires pour les Liens de Notifications Naklass
 * Ce fichier contient des fonctions pour générer des liens corrects dans les notifications
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
            
        case "user_created":
            return $base_url . "settings/users.php";
            
        case "password_changed":
            return $base_url . "profile/change_password.php";
            
        case "school_created":
            return $base_url . "ecole/view.php?id=" . ($data["school_id"] ?? "");
            
        case "school_validated":
            return $base_url . "ecole/view.php?id=" . ($data["school_id"] ?? "");
            
        case "class_capacity":
            return $base_url . "classes/view.php?id=" . ($data["class_id"] ?? "");
            
        case "low_grades":
            return $base_url . "students/view.php?id=" . ($data["student_id"] ?? "");
            
        case "backup_completed":
            return $base_url . "settings/backup.php";
            
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
            
        case "user_created":
            return "../settings/users.php";
            
        case "password_changed":
            return "../profile/change_password.php";
            
        case "school_created":
            return "../ecole/view.php?id=" . ($data["school_id"] ?? "");
            
        case "school_validated":
            return "../ecole/view.php?id=" . ($data["school_id"] ?? "");
            
        case "class_capacity":
            return "../classes/view.php?id=" . ($data["class_id"] ?? "");
            
        case "low_grades":
            return "../students/view.php?id=" . ($data["student_id"] ?? "");
            
        case "backup_completed":
            return "../settings/backup.php";
            
        default:
            return "../notifications.php";
    }
}

/**
 * Générer un lien sécurisé correct pour une notification
 * @param string $type Type de notification
 * @param array $data Données de la notification
 * @return string URL sécurisée correcte
 */
function generateNotificationSecureLink($type, $data = []) {
    switch ($type) {
        case "student_registered":
            return createSecureLink('../students/view.php', $data["student_id"] ?? 0, 'id');
            
        case "course_assigned":
        case "teacher_assigned":
            return createSecureLink('../classes/view.php', $data["class_id"] ?? 0, 'id');
            
        case "payment_received":
            return createSecureLink('../finance/print_receipt.php', $data["payment_id"] ?? 0, 'id');
            
        case "student_absent":
            return createSecureLink('../students/view.php', $data["student_id"] ?? 0, 'id');
            
        case "system_maintenance":
            return '../settings/system.php';
            
        case "login_suspicious":
            return '../settings/security.php';
            
        case "user_created":
            return '../settings/users.php';
            
        case "password_changed":
            return '../profile/change_password.php';
            
        case "school_created":
            return createSecureLink('../ecole/view.php', $data["school_id"] ?? 0, 'id');
            
        case "school_validated":
            return createSecureLink('../ecole/view.php', $data["school_id"] ?? 0, 'id');
            
        case "class_capacity":
            return createSecureLink('../classes/view.php', $data["class_id"] ?? 0, 'id');
            
        case "low_grades":
            return createSecureLink('../students/view.php', $data["student_id"] ?? 0, 'id');
            
        case "backup_completed":
            return '../settings/backup.php';
            
        default:
            return '../notifications.php';
    }
}

/**
 * Obtenir le texte d'action approprié pour une notification
 * @param string $type Type de notification
 * @return string Texte d'action
 */
function getNotificationActionText($type) {
    switch ($type) {
        case "student_registered":
        case "student_absent":
        case "low_grades":
            return "Voir l'élève";
            
        case "course_assigned":
        case "teacher_assigned":
        case "class_capacity":
            return "Voir la classe";
            
        case "payment_received":
            return "Imprimer le reçu";
            
        case "system_maintenance":
            return "Voir les détails";
            
        case "login_suspicious":
            return "Voir les logs";
            
        case "user_created":
            return "Voir les utilisateurs";
            
        case "password_changed":
            return "Changer le mot de passe";
            
        case "school_created":
        case "school_validated":
            return "Voir l'école";
            
        case "backup_completed":
            return "Voir les sauvegardes";
            
        default:
            return "Voir les détails";
    }
}

/**
 * Valider qu'un lien de notification est correct
 * @param string $url URL à valider
 * @return bool True si le lien est valide
 */
function validateNotificationLink($url) {
    // Vérifier que l'URL n'est pas vide
    if (empty($url)) {
        return false;
    }
    
    // Vérifier que l'URL commence par un chemin valide
    $valid_paths = [
        '../students/',
        '../classes/',
        '../finance/',
        '../settings/',
        '../profile/',
        '../ecole/',
        '../notifications.php'
    ];
    
    foreach ($valid_paths as $path) {
        if (strpos($url, $path) === 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Corriger automatiquement un lien de notification
 * @param string $type Type de notification
 * @param string $current_url URL actuelle (potentiellement incorrecte)
 * @param array $data Données de la notification
 * @return string URL corrigée
 */
function fixNotificationLink($type, $current_url, $data = []) {
    // Si le lien actuel est déjà valide, le garder
    if (validateNotificationLink($current_url)) {
        return $current_url;
    }
    
    // Sinon, générer un nouveau lien correct
    return generateNotificationSecureLink($type, $data);
}
?>

