<?php
/**
 * Script de correction pour le problème de colonne 'remarques' manquante
 * dans la table inscriptions
 */

require_once 'includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = [];

// Vérifier la structure actuelle de la table inscriptions
try {
    $success[] = "=== VÉRIFICATION DE LA STRUCTURE DE LA TABLE INSCRIPTIONS ===";
    
    // Décrire la table inscriptions
    $columns = $db->query("DESCRIBE inscriptions")->fetchAll(PDO::FETCH_ASSOC);
    $success[] = "Colonnes actuelles de la table 'inscriptions':";
    
    $column_names = [];
    foreach ($columns as $column) {
        $column_names[] = $column['Field'];
        $success[] = "  - " . $column['Field'] . " (" . $column['Type'] . ") " . 
                    ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . 
                    ($column['Default'] ? " DEFAULT " . $column['Default'] : "");
    }
    
    // Vérifier si la colonne 'remarques' existe
    if (in_array('remarques', $column_names)) {
        $success[] = "✅ La colonne 'remarques' existe déjà";
    } else {
        $success[] = "❌ La colonne 'remarques' n'existe pas";
        
        // Vérifier si la colonne 'notes' existe
        if (in_array('notes', $column_names)) {
            $success[] = "✅ La colonne 'notes' existe - nous pouvons l'utiliser";
        } else {
            $success[] = "❌ La colonne 'notes' n'existe pas non plus";
        }
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur lors de la vérification de la structure : " . $e->getMessage();
}

// Traitement de la correction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'fix_remarques_column') {
        try {
            $db->beginTransaction();
            
            // Vérifier à nouveau la structure
            $columns = $db->query("DESCRIBE inscriptions")->fetchAll(PDO::FETCH_ASSOC);
            $column_names = array_column($columns, 'Field');
            
            if (!in_array('remarques', $column_names)) {
                if (in_array('notes', $column_names)) {
                    // Ajouter la colonne 'remarques' comme alias de 'notes'
                    $add_remarques = "ALTER TABLE inscriptions ADD COLUMN remarques TEXT AFTER notes";
                    $stmt = $db->prepare($add_remarques);
                    $stmt->execute();
                    $success[] = "✅ Colonne 'remarques' ajoutée à la table inscriptions";
                    
                    // Copier les données de 'notes' vers 'remarques' si nécessaire
                    $copy_data = "UPDATE inscriptions SET remarques = notes WHERE notes IS NOT NULL AND remarques IS NULL";
                    $stmt = $db->prepare($copy_data);
                    $stmt->execute();
                    $success[] = "✅ Données copiées de 'notes' vers 'remarques'";
                    
                } else {
                    // Ajouter les deux colonnes
                    $add_notes = "ALTER TABLE inscriptions ADD COLUMN notes TEXT";
                    $stmt = $db->prepare($add_notes);
                    $stmt->execute();
                    $success[] = "✅ Colonne 'notes' ajoutée";
                    
                    $add_remarques = "ALTER TABLE inscriptions ADD COLUMN remarques TEXT AFTER notes";
                    $stmt = $db->prepare($add_remarques);
                    $stmt->execute();
                    $success[] = "✅ Colonne 'remarques' ajoutée";
                }
            }
            
            $db->commit();
            $success[] = "✅ Correction terminée avec succès !";
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "Erreur lors de la correction : " . $e->getMessage();
        }
    }
}

// Vérifier les fichiers qui utilisent 'remarques' dans les requêtes d'insertion
$success[] = "";
$success[] = "=== FICHIERS À CORRIGER ===";

$files_to_check = [
    'classes/students.php',
    'students/add.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'remarques') !== false) {
            $success[] = "⚠️  $file utilise la colonne 'remarques'";
        } else {
            $success[] = "✅ $file n'utilise pas 'remarques'";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction Colonne Remarques - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="bi bi-tools me-2"></i>Correction de la colonne 'remarques' manquante</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <?php foreach ($errors as $error): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle me-2"></i>Informations de diagnostic :</h6>
                                <pre style="white-space: pre-wrap; font-size: 0.9em;"><?php echo htmlspecialchars(implode("\n", $success)); ?></pre>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle me-2"></i>Problème identifié :</h6>
                            <p>L'erreur <code>SQLSTATE[42S22]: Column not found: 1054 Unknown column 'remarques' in 'field list'</code> 
                            indique que le code essaie d'insérer des données dans une colonne 'remarques' qui n'existe pas dans la table 'inscriptions'.</p>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>Solution :</h6>
                            <p>Ce script va ajouter la colonne 'remarques' manquante à la table 'inscriptions' pour résoudre l'erreur d'inscription.</p>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="fix_remarques_column">
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning btn-lg">
                                    <i class="bi bi-tools me-2"></i>Corriger la colonne 'remarques'
                                </button>
                            </div>
                        </form>
                        
                        <hr>
                        
                        <div class="d-flex gap-2">
                            <a href="students/add.php" class="btn btn-outline-primary">
                                <i class="bi bi-person-plus me-2"></i>Tester l'inscription
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Retour à l'accueil
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
