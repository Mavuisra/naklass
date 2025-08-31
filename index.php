<?php
/**
 * Page d'accueil principale de Naklass
 * Détecte automatiquement les nouveaux visiteurs et redirige vers la configuration d'école
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Démarrer la session si pas encore démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détecter si c'est un nouveau visiteur
$is_new_visitor = false;
$visitor_id = null;

// Vérifier si le visiteur a déjà un cookie
if (!isset($_COOKIE['naklass_visitor_id'])) {
    // Nouveau visiteur - créer un ID unique
    $visitor_id = 'visitor_' . time() . '_' . rand(1000, 9999);
    setcookie('naklass_visitor_id', $visitor_id, time() + (86400 * 365), '/'); // 1 an
    $is_new_visitor = true;
} else {
    $visitor_id = $_COOKIE['naklass_visitor_id'];
}

// Vérifier si l'utilisateur est connecté
if (isLoggedIn()) {
    // Rediriger vers le tableau de bord
    redirect('auth/dashboard.php');
} else {
    // Vérifier si c'est un nouveau visiteur
    if ($is_new_visitor) {
        // Rediriger automatiquement vers la configuration d'école
        redirect('visitor_school_setup.php');
    } else {
        // Vérifier si le visiteur a déjà créé une école
        if (isset($_COOKIE['naklass_ecole_created'])) {
            // Le visiteur a déjà créé une école, rediriger vers la connexion
            redirect('auth/login.php');
        } else {
            // Visiteur existant sans école, rediriger vers la configuration
            redirect('visitor_school_setup.php');
        }
    }
}
?>
