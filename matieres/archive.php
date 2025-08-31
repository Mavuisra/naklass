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

if ($matiere_id <= 0) {
    setFlashMessage('error', 'ID de matière invalide.');
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();

try {
    // Vérifier que la matière existe et appartient à l'école
    $check_query = "SELECT nom_cours, statut FROM cours WHERE id = :id AND ecole_id = :ecole_id";
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
    
    if ($matiere['statut'] === 'archivé') {
        setFlashMessage('warning', 'Cette matière est déjà archivée.');
        redirect("view.php?id={$matiere_id}");
    }
    
    // Commencer une transaction
    $db->beginTransaction();
    
    // Archiver la matière
    $update_matiere_query = "UPDATE cours SET 
                                statut = 'archivé',
                                actif = 0,
                                updated_by = :updated_by,
                                updated_at = NOW(),
                                version = version + 1
                             WHERE id = :id AND ecole_id = :ecole_id";
    
    $stmt = $db->prepare($update_matiere_query);
    $stmt->execute([
        'updated_by' => $_SESSION['user_id'],
        'id' => $matiere_id,
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    
    // Désactiver toutes les assignations de cette matière
    $update_assignations_query = "UPDATE classe_cours SET 
                                     actif = 0,
                                     updated_at = NOW()
                                  WHERE cours_id = :cours_id";
    
    $stmt = $db->prepare($update_assignations_query);
    $stmt->execute(['cours_id' => $matiere_id]);
    
    // Valider la transaction
    $db->commit();
    
    setFlashMessage('success', "La matière '{$matiere['nom_cours']}' a été archivée avec succès. Toutes ses assignations ont été désactivées.");
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    setFlashMessage('error', 'Erreur lors de l\'archivage de la matière: ' . $e->getMessage());
    error_log("Erreur archivage matière: " . $e->getMessage());
}

// Rediriger vers la liste des matières
redirect('index.php');
?>

