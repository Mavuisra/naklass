<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

$database = new Database();
$db = $database->getConnection();

$page_title = "Vérification des Tables";
$errors = [];
$success = [];

// Vérifier et créer les tables manquantes
try {
    // Vérifier la table eleves
    try {
        $db->query("SELECT 1 FROM eleves LIMIT 1");
        $success[] = "✓ Table 'eleves' existe";
    } catch (Exception $e) {
        $create_eleves = "CREATE TABLE IF NOT EXISTS eleves (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ecole_id BIGINT NOT NULL,
            matricule VARCHAR(50) UNIQUE NOT NULL,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            date_naissance DATE NOT NULL,
            sexe ENUM('M', 'F') NOT NULL,
            telephone VARCHAR(20),
            email VARCHAR(255),
            adresse TEXT,
            photo VARCHAR(255),
            statut ENUM('actif', 'inactif', 'suspendu') DEFAULT 'actif',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_eleves_ecole (ecole_id),
            INDEX idx_eleves_matricule (matricule),
            INDEX idx_eleves_nom (nom),
            INDEX idx_eleves_statut (statut)
        )";
        
        $db->exec($create_eleves);
        $success[] = "✓ Table 'eleves' créée avec succès";
    }
    
    // Vérifier la table inscriptions
    try {
        $db->query("SELECT 1 FROM inscriptions LIMIT 1");
        $success[] = "✓ Table 'inscriptions' existe";
    } catch (Exception $e) {
        $create_inscriptions = "CREATE TABLE IF NOT EXISTS inscriptions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            eleve_id BIGINT NOT NULL,
            classe_id BIGINT NOT NULL,
            date_inscription DATE NOT NULL DEFAULT (CURRENT_DATE),
            date_fin DATE NULL,
            statut ENUM('validée', 'en_attente', 'annulée', 'suspendue') DEFAULT 'validée',
            notes TEXT NULL,
            created_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_inscriptions_eleve (eleve_id),
            INDEX idx_inscriptions_classe (classe_id),
            INDEX idx_inscriptions_statut (statut),
            INDEX idx_inscriptions_date (date_inscription)
        )";
        
        $db->exec($create_inscriptions);
        $success[] = "✓ Table 'inscriptions' créée avec succès";
    }
    
    // Vérifier la table niveaux
    try {
        $db->query("SELECT 1 FROM niveaux LIMIT 1");
        $success[] = "✓ Table 'niveaux' existe";
    } catch (Exception $e) {
        $create_niveaux = "CREATE TABLE IF NOT EXISTS niveaux (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ecole_id BIGINT NULL,
            nom VARCHAR(100) NOT NULL,
            description TEXT,
            ordre INT DEFAULT 0,
            actif BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_niveaux_ecole (ecole_id),
            INDEX idx_niveaux_ordre (ordre)
        )";
        
        $db->exec($create_niveaux);
        
        // Insérer les niveaux standards
        $insert_niveaux = "INSERT IGNORE INTO niveaux (ecole_id, nom, description, ordre) VALUES
            (NULL, 'Maternelle', 'Petite section, Moyenne section, Grande section', 1),
            (NULL, 'Primaire', 'CP, CE1, CE2, CM1, CM2', 2),
            (NULL, 'Collège', '6ème, 5ème, 4ème, 3ème', 3),
            (NULL, 'Lycée', 'Seconde, Première, Terminale', 4),
            (NULL, 'Supérieur', 'Études supérieures', 5)";
        
        $db->exec($insert_niveaux);
        $success[] = "✓ Table 'niveaux' créée avec succès et niveaux standards insérés";
    }
    
    // Vérifier la table sections
    try {
        $db->query("SELECT 1 FROM sections LIMIT 1");
        $success[] = "✓ Table 'sections' existe";
    } catch (Exception $e) {
        $create_sections = "CREATE TABLE IF NOT EXISTS sections (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ecole_id BIGINT NULL,
            nom VARCHAR(100) NOT NULL,
            description TEXT,
            actif BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sections_ecole (ecole_id)
        )";
        
        $db->exec($create_sections);
        
        // Insérer les sections standards
        $insert_sections = "INSERT IGNORE INTO sections (ecole_id, nom, description) VALUES
            (NULL, 'Générale', 'Section générale standard'),
            (NULL, 'Scientifique', 'Section à dominante scientifique'),
            (NULL, 'Littéraire', 'Section à dominante littéraire'),
            (NULL, 'Économique', 'Section à dominante économique'),
            (NULL, 'Technologique', 'Section à dominante technologique')";
        
        $db->exec($insert_sections);
        $success[] = "✓ Table 'sections' créée avec succès et sections standards insérées";
    }
    
} catch (Exception $e) {
    $errors[] = "Erreur lors de la création des tables : " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation latérale -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Contenu principal -->
    <main class="main-content">
        <!-- Barre supérieure -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-database me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Vérification et création des tables nécessaires</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour aux Classes
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>État des Tables</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success">
                                    <h6><i class="bi bi-check-circle me-2"></i>Résultats de la vérification</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($success as $msg): ?>
                                            <li><?php echo htmlspecialchars($msg); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-center mt-4">
                                <a href="index.php" class="btn btn-primary me-2">
                                    <i class="bi bi-building me-2"></i>Retour aux Classes
                                </a>
                                <a href="add_test_students.php" class="btn btn-success me-2">
                                    <i class="bi bi-person-plus me-2"></i>Ajouter des Élèves de Test
                                </a>
                                <a href="diagnose_inscriptions.php" class="btn btn-info me-2">
                                    <i class="bi bi-search me-2"></i>Diagnostiquer Inscriptions
                                </a>
                                <a href="debug_students.php" class="btn btn-secondary me-2">
                                    <i class="bi bi-bug me-2"></i>Debug Élèves
                                </a>
                                <a href="fix_inscriptions_table.php" class="btn btn-warning me-2">
                                    <i class="bi bi-wrench me-2"></i>Corriger la Table Inscriptions
                                </a>
                                <a href="check_tables.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Vérifier à nouveau
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
