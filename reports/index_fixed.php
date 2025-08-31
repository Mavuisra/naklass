<?php
/**
 * Page de Rapports - Tableau de Bord Complet (Version Corrigée)
 * Affiche toutes les informations essentielles de l'école en vue synthétique
 * Gère les colonnes manquantes et utilise des requêtes SQL adaptées
 */

require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Vérifier les permissions
if (!hasPermission(['admin', 'direction', 'secretaire'])) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Récupérer l'ID de l'école de l'utilisateur connecté
$ecole_id = $_SESSION['ecole_id'] ?? null;

if (!$ecole_id) {
    header('Location: ../auth/login.php');
    exit();
}

// Fonction pour formater les nombres
function formatNumber($number) {
    return number_format($number, 0, ',', ' ');
}

// Fonction pour calculer le pourcentage
function calculatePercentage($part, $total) {
    if ($total == 0) return 0;
    return round(($part / $total) * 100, 1);
}

// Fonction pour vérifier si une colonne existe
function columnExists($db, $table, $column) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

try {
    // 1. Informations générales de l'école
    $ecole_query = "SELECT * FROM ecoles WHERE id = ?";
    $ecole_stmt = $db->prepare($ecole_query);
    $ecole_stmt->execute([$ecole_id]);
    $ecole = $ecole_stmt->fetch();
    
    // 2. Statistiques des classes
    if (columnExists($db, 'classes', 'ecole_id')) {
        $classes_query = "SELECT 
                            COUNT(*) as total_classes,
                            COUNT(CASE WHEN statut = 'actif' THEN 1 END) as classes_actives,
                            COUNT(CASE WHEN statut = 'inactif' THEN 1 END) as classes_inactives
                          FROM classes WHERE ecole_id = ?";
    } else {
        // Fallback si ecole_id n'existe pas
        $classes_query = "SELECT 
                            COUNT(*) as total_classes,
                            COUNT(CASE WHEN statut = 'actif' THEN 1 END) as classes_actives,
                            COUNT(CASE WHEN statut = 'inactif' THEN 1 END) as classes_inactives
                          FROM classes";
    }
    $classes_stmt = $db->prepare($classes_query);
    $classes_stmt->execute(columnExists($db, 'classes', 'ecole_id') ? [$ecole_id] : []);
    $classes_stats = $classes_stmt->fetch();
    
    // 3. Statistiques des inscriptions
    if (columnExists($db, 'inscriptions', 'ecole_id')) {
        $inscriptions_query = "SELECT 
                               COUNT(*) as total_inscriptions,
                               COUNT(CASE WHEN statut = 'active' THEN 1 END) as inscriptions_actives,
                               COUNT(CASE WHEN statut = 'terminee' THEN 1 END) as inscriptions_terminees,
                               COUNT(CASE WHEN statut = 'annulee' THEN 1 END) as inscriptions_annulees
                             FROM inscriptions WHERE ecole_id = ?";
    } else {
        // Fallback si ecole_id n'existe pas
        $inscriptions_query = "SELECT 
                               COUNT(*) as total_inscriptions,
                               COUNT(CASE WHEN statut = 'active' THEN 1 END) as inscriptions_actives,
                               COUNT(CASE WHEN statut = 'terminee' THEN 1 END) as inscriptions_terminees,
                               COUNT(CASE WHEN statut = 'annulee' THEN 1 END) as inscriptions_annulees
                             FROM inscriptions";
    }
    $inscriptions_stmt = $db->prepare($inscriptions_query);
    $inscriptions_stmt->execute(columnExists($db, 'inscriptions', 'ecole_id') ? [$ecole_id] : []);
    $inscriptions_stats = $inscriptions_stmt->fetch();
    
    // 4. Statistiques des cours
    if (columnExists($db, 'cours', 'ecole_id')) {
        $cours_query = "SELECT 
                          COUNT(*) as total_cours,
                          COUNT(CASE WHEN statut = 'actif' THEN 1 END) as cours_actifs,
                          COUNT(CASE WHEN statut = 'termine' THEN 1 END) as cours_termines
                        FROM cours WHERE ecole_id = ?";
    } else {
        // Fallback si ecole_id n'existe pas
        $cours_query = "SELECT 
                          COUNT(*) as total_cours,
                          COUNT(CASE WHEN statut = 'actif' THEN 1 END) as cours_actifs,
                          COUNT(CASE WHEN statut = 'termine' THEN 1 END) as cours_termines
                        FROM cours";
    }
    $cours_stmt = $db->prepare($cours_query);
    $cours_stmt->execute(columnExists($db, 'cours', 'ecole_id') ? [$ecole_id] : []);
    $cours_stats = $cours_stmt->fetch();
    
    // 5. Statistiques des notes
    if (columnExists($db, 'notes', 'inscription_id') && columnExists($db, 'inscriptions', 'ecole_id')) {
        $notes_query = "SELECT 
                          COUNT(*) as total_notes,
                          AVG(note) as moyenne_generale,
                          MIN(note) as note_min,
                          MAX(note) as note_max
                        FROM notes n 
                        JOIN inscriptions i ON n.inscription_id = i.id 
                        WHERE i.ecole_id = ?";
    } else {
        // Fallback si les jointures ne sont pas possibles
        $notes_query = "SELECT 
                          COUNT(*) as total_notes,
                          AVG(note) as moyenne_generale,
                          MIN(note) as note_min,
                          MAX(note) as note_max
                        FROM notes";
    }
    $notes_stmt = $db->prepare($notes_query);
    $notes_stmt->execute(columnExists($db, 'notes', 'inscription_id') && columnExists($db, 'inscriptions', 'ecole_id') ? [$ecole_id] : []);
    $notes_stats = $notes_stmt->fetch();
    
    // 6. Statistiques des finances
    if (columnExists($db, 'transactions_financieres', 'ecole_id')) {
        $finances_query = "SELECT 
                             COUNT(*) as total_transactions,
                             SUM(CASE WHEN type = 'entree' THEN montant ELSE 0 END) as total_entrees,
                             SUM(CASE WHEN type = 'sortie' THEN montant ELSE 0 END) as total_sorties,
                             SUM(CASE WHEN type = 'entree' THEN montant ELSE -montant END) as solde
                           FROM transactions_financieres WHERE ecole_id = ?";
    } else {
        // Fallback si ecole_id n'existe pas
        $finances_query = "SELECT 
                             COUNT(*) as total_transactions,
                             SUM(CASE WHEN type = 'entree' THEN montant ELSE 0 END) as total_entrees,
                             SUM(CASE WHEN type = 'sortie' THEN montant ELSE 0 END) as total_sorties,
                             SUM(CASE WHEN type = 'entree' THEN montant ELSE -montant END) as solde
                           FROM transactions_financieres";
    }
    $finances_stmt = $db->prepare($finances_query);
    $finances_stmt->execute(columnExists($db, 'transactions_financieres', 'ecole_id') ? [$ecole_id] : []);
    $finances_stats = $finances_stmt->fetch();
    
    // 7. Statistiques de présence
    if (columnExists($db, 'presence', 'session_id') && columnExists($db, 'sessions_presence', 'cours_id') && columnExists($db, 'cours', 'ecole_id')) {
        $presence_query = "SELECT 
                             COUNT(*) as total_seances,
                             COUNT(CASE WHEN statut = 'present' THEN 1 END) as total_presences,
                             COUNT(CASE WHEN statut = 'absent' THEN 1 END) as total_absences,
                             COUNT(CASE WHEN statut = 'retard' THEN 1 END) as total_retards
                           FROM presence p 
                           JOIN sessions_presence sp ON p.session_id = sp.id 
                           JOIN cours c ON sp.cours_id = c.id 
                           WHERE c.ecole_id = ?";
    } else {
        // Fallback si les jointures ne sont pas possibles
        $presence_query = "SELECT 
                             COUNT(*) as total_seances,
                             COUNT(CASE WHEN statut = 'present' THEN 1 END) as total_presences,
                             COUNT(CASE WHEN statut = 'absent' THEN 1 END) as total_absences,
                             COUNT(CASE WHEN statut = 'retard' THEN 1 END) as total_retards
                           FROM presence";
    }
    $presence_stmt = $db->prepare($presence_query);
    $presence_stmt->execute(columnExists($db, 'presence', 'session_id') && columnExists($db, 'sessions_presence', 'cours_id') && columnExists($db, 'cours', 'ecole_id') ? [$ecole_id] : []);
    $presence_stats = $presence_stmt->fetch();
    
    // 8. Statistiques des utilisateurs
    if (columnExists($db, 'utilisateurs', 'ecole_id')) {
        $users_query = "SELECT 
                          COUNT(*) as total_users,
                          COUNT(CASE WHEN role_id = (SELECT id FROM roles WHERE code = 'enseignant') THEN 1 END) as total_enseignants,
                          COUNT(CASE WHEN role_id = (SELECT id FROM roles WHERE code = 'admin') THEN 1 END) as total_admins,
                          COUNT(CASE WHEN statut = 'actif' THEN 1 END) as users_actifs
                        FROM utilisateurs WHERE ecole_id = ?";
    } else {
        // Fallback si ecole_id n'existe pas
        $users_query = "SELECT 
                          COUNT(*) as total_users,
                          COUNT(CASE WHEN role_id = (SELECT id FROM roles WHERE code = 'enseignant') THEN 1 END) as total_enseignants,
                          COUNT(CASE WHEN role_id = (SELECT id FROM roles WHERE code = 'admin') THEN 1 END) as total_admins,
                          COUNT(CASE WHEN statut = 'actif' THEN 1 END) as users_actifs
                        FROM utilisateurs";
    }
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute(columnExists($db, 'utilisateurs', 'ecole_id') ? [$ecole_id] : []);
    $users_stats = $users_stmt->fetch();
    
} catch (Exception $e) {
    $error_message = "Erreur lors de la récupération des données: " . $e->getMessage();
}
?>
