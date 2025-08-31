<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Méthode non autorisée.');
    redirect('index.php');
}

$matiere_id = (int)($_POST['id'] ?? 0);
$activate = filter_var($_POST['activate'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($matiere_id <= 0) {
    setFlashMessage('error', 'ID de matière invalide.');
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();

try {
    // Vérifier que la matière existe et appartient à l'école
    $check_query = "SELECT nom_cours, actif FROM cours WHERE id = :id AND ecole_id = :ecole_id";
    $stmt = $db->prepare($check_query);
    $stmt->execute([
        'id' => $matiere_id,
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    $matiere = $stmt->fetch();
    
    if (!$matiere) {
        setFlashMessage('error', 'Matière non trouvée.');
        redirect('index.php');
    }
    
    // Mettre à jour le statut
    $update_query = "UPDATE cours SET 
                        actif = :actif,
                        updated_by = :updated_by,
                        updated_at = NOW(),
                        version = version + 1
                     WHERE id = :id AND ecole_id = :ecole_id";
    
    $stmt = $db->prepare($update_query);
    $result = $stmt->execute([
        'actif' => $activate ? 1 : 0,
        'updated_by' => $_SESSION['user_id'],
        'id' => $matiere_id,
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    
    if ($result) {
        $action = $activate ? 'activée' : 'désactivée';
        setFlashMessage('success', "La matière '{$matiere['nom_cours']}' a été {$action} avec succès.");
    } else {
        setFlashMessage('error', 'Erreur lors de la modification du statut.');
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la modification du statut: ' . $e->getMessage());
    error_log("Erreur toggle status matière: " . $e->getMessage());
}

// Rediriger vers la page d'édition
redirect("edit.php?id={$matiere_id}");
?>

