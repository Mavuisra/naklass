<?php
/**
 * Page de gestion de présence pour une classe spécifique
 * Redirige vers la fonctionnalité de présence appropriée
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'enseignant']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier les paramètres
if (!isset($_GET['classe_id']) || !is_numeric($_GET['classe_id'])) {
    setFlashMessage('error', 'ID de classe invalide.');
    redirect('index.php');
}

$classe_id = (int)$_GET['classe_id'];
$cours_id = isset($_GET['cours_id']) ? (int)$_GET['cours_id'] : null;

$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la classe
$classe = validateClassAccess($classe_id, $db);
if (!$classe) {
    redirect('index.php');
}

// Si un cours_id est spécifié, rediriger vers la page de session de présence
if ($cours_id) {
    redirect("../presence/session.php?classe_id={$classe_id}&cours_id={$cours_id}");
}

// Sinon, rediriger vers la page générale de présence de la classe
redirect("../presence/classe.php?id={$classe_id}");
?>


