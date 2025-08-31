<?php
/**
 * Script de vérification et correction des tables du module Classes
 * Vérifie la structure et corrige les problèmes détectés
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification Tables Classes - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .verification-header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 2rem 0;
        }
        .verification-card {
            border-left: 4px solid #17a2b8;
            transition: transform 0.2s;
        }
        .verification-card:hover {
            transform: translateX(5px);
        }
        .success-icon { color: #28a745; }
        .warning-icon { color: #ffc107; }
        .error-icon { color: #dc3545; }
        .info-icon { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="verification-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-search me-3"></i>Vérification Tables Classes</h1>
                    <p class="mb-0">Diagnostic et correction de la structure des tables du module Classes</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <i class="bi bi-tools display-4 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <?php
                $success_count = 0;
                $error_count = 0;
                $warnings = [];
                $corrections = [];
                
                // Étape 1: Vérification de l'existence des tables
                echo "<div class='verification-card card mb-3'>";
                echo "<div class='card-header'><h5><i class='bi bi-1-circle me-2'></i>Vérification de l'existence des tables</h5></div>";
                echo "<div class='card-body'>";
                
                $required_tables = ['niveaux', 'sections', 'classes', 'inscriptions'];
                $existing_tables = [];
                $missing_tables = [];
                
                foreach ($required_tables as $table) {
                    try {
                        $stmt = $db->query("SHOW TABLES LIKE '$table'");
                        if ($stmt->rowCount() > 0) {
                            $existing_tables[] = $table;
                            echo "<i class='bi bi-check-circle success-icon me-2'></i>Table <strong>$table</strong> existe<br>";
                        } else {
                            $missing_tables[] = $table;
                            echo "<i class='bi bi-x-circle error-icon me-2'></i>Table <strong>$table</strong> manquante<br>";
                        }
                    } catch (PDOException $e) {
                        $missing_tables[] = $table;
                        echo "<i class='bi bi-x-circle error-icon me-2'></i>Erreur lors de la vérification de <strong>$table</strong>: " . $e->getMessage() . "<br>";
                    }
                }
                
                if (empty($missing_tables)) {
                    $success_count++;
                    echo "<br><i class='bi bi-check-circle success-icon me-2'></i><strong>Toutes les tables requises sont présentes</strong>";
                } else {
                    $error_count++;
                    echo "<br><i class='bi bi-exclamation-triangle warning-icon me-2'></i><strong>Tables manquantes détectées</strong>";
                }
                
                echo "</div></div>";
                
                // Étape 2: Vérification de la structure des tables existantes
                if (!empty($existing_tables)) {
                    echo "<div class='verification-card card mb-3'>";
                    echo "<div class='card-header'><h5><i class='bi bi-2-circle me-2'></i>Vérification de la structure des tables</h5></div>";
                    echo "<div class='card-body'>";
                    
                    foreach ($existing_tables as $table) {
                        echo "<h6>Table: <strong>$table</strong></h6>";
                        
                        try {
                            $stmt = $db->query("DESCRIBE $table");
                            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            echo "<div class='table-responsive'>";
                            echo "<table class='table table-sm table-bordered'>";
                            echo "<thead><tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr></thead>";
                            echo "<tbody>";
                            
                            foreach ($columns as $column) {
                                $status_icon = "<i class='bi bi-check-circle success-icon'></i>";
                                echo "<tr>";
                                echo "<td>{$column['Field']}</td>";
                                echo "<td>{$column['Type']}</td>";
                                echo "<td>{$column['Null']}</td>";
                                echo "<td>{$column['Key']}</td>";
                                echo "<td>{$column['Default']}</td>";
                                echo "</tr>";
                            }
                            
                            echo "</tbody></table></div>";
                            
                            // Vérifications spécifiques pour la table classes
                            if ($table === 'classes') {
                                $required_columns = ['niveau_id', 'section_id', 'capacite_max'];
                                $missing_columns = [];
                                
                                foreach ($required_columns as $col) {
                                    $col_exists = false;
                                    foreach ($columns as $column) {
                                        if ($column['Field'] === $col) {
                                            $col_exists = true;
                                            break;
                                        }
                                    }
                                    if (!$col_exists) {
                                        $missing_columns[] = $col;
                                    }
                                }
                                
                                if (!empty($missing_columns)) {
                                    echo "<div class='alert alert-warning mt-2'>";
                                    echo "<i class='bi bi-exclamation-triangle me-2'></i>";
                                    echo "<strong>Colonnes manquantes dans la table classes:</strong> " . implode(', ', $missing_columns);
                                    echo "</div>";
                                    $warnings[] = "Colonnes manquantes dans classes: " . implode(', ', $missing_columns);
                                } else {
                                    echo "<div class='alert alert-success mt-2'>";
                                    echo "<i class='bi bi-check-circle me-2'></i>";
                                    echo "<strong>Structure de la table classes correcte</strong>";
                                    echo "</div>";
                                }
                            }
                            
                        } catch (PDOException $e) {
                            echo "<div class='alert alert-danger'>";
                            echo "<i class='bi bi-x-circle me-2'></i>";
                            echo "Erreur lors de la description de la table: " . $e->getMessage();
                            echo "</div>";
                            $error_count++;
                        }
                        
                        echo "<hr>";
                    }
                    
                    echo "</div></div>";
                }
                
                // Étape 3: Tentative de correction automatique
                if (!empty($missing_tables) || !empty($warnings)) {
                    echo "<div class='verification-card card mb-3'>";
                    echo "<div class='card-header'><h5><i class='bi bi-3-circle me-2'></i>Tentative de correction automatique</h5></div>";
                    echo "<div class='card-body'>";
                    
                    if (!empty($missing_tables)) {
                        echo "<h6>Création des tables manquantes</h6>";
                        
                        // Créer les tables manquantes une par une
                        $create_queries = [
                            'niveaux' => "
                                CREATE TABLE IF NOT EXISTS niveaux (
                                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                                    ecole_id BIGINT NULL,
                                    nom VARCHAR(100) NOT NULL,
                                    description TEXT,
                                    ordre INT DEFAULT 0,
                                    actif BOOLEAN DEFAULT TRUE,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    
                                    FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE,
                                    INDEX idx_niveaux_ecole (ecole_id),
                                    INDEX idx_niveaux_ordre (ordre)
                                )",
                            
                            'sections' => "
                                CREATE TABLE IF NOT EXISTS sections (
                                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                                    ecole_id BIGINT NULL,
                                    nom VARCHAR(100) NOT NULL,
                                    description TEXT,
                                    actif BOOLEAN DEFAULT TRUE,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    
                                    FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE,
                                    INDEX idx_sections_ecole (ecole_id)
                                )",
                            
                            'classes' => "
                                CREATE TABLE IF NOT EXISTS classes (
                                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                                    ecole_id BIGINT NOT NULL,
                                    nom VARCHAR(100) NOT NULL,
                                    niveau_id BIGINT NULL,
                                    section_id BIGINT NULL,
                                    description TEXT,
                                    capacite_max INT NULL,
                                    enseignant_principal_id BIGINT NULL,
                                    salle VARCHAR(50) NULL,
                                    horaire_debut TIME NULL,
                                    horaire_fin TIME NULL,
                                    statut ENUM('active', 'preparation', 'suspendue', 'fermee') DEFAULT 'active',
                                    created_by BIGINT NULL,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    
                                    FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE,
                                    FOREIGN KEY (niveau_id) REFERENCES niveaux(id) ON DELETE SET NULL,
                                    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
                                    FOREIGN KEY (enseignant_principal_id) REFERENCES enseignants(id) ON DELETE SET NULL,
                                    FOREIGN KEY (created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL,
                                    
                                    UNIQUE KEY unique_classe_nom_ecole (ecole_id, nom),
                                    INDEX idx_classes_ecole (ecole_id),
                                    INDEX idx_classes_niveau (niveau_id),
                                    INDEX idx_classes_section (section_id),
                                    INDEX idx_classes_enseignant (enseignant_principal_id),
                                    INDEX idx_classes_statut (statut)
                                )",
                            
                            'inscriptions' => "
                                CREATE TABLE IF NOT EXISTS inscriptions (
                                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                                    eleve_id BIGINT NOT NULL,
                                    classe_id BIGINT NOT NULL,
                                    date_inscription DATE NOT NULL DEFAULT (CURRENT_DATE),
                                    date_fin DATE NULL,
                                    statut ENUM('validée', 'en_attente', 'annulée', 'suspendue') DEFAULT 'validée',
                                    notes TEXT,
                                    created_by BIGINT NULL,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    
                                    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
                                    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
                                    FOREIGN KEY (created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL,
                                    
                                    INDEX idx_inscriptions_eleve (eleve_id),
                                    INDEX idx_inscriptions_classe (classe_id),
                                    INDEX idx_inscriptions_statut (statut),
                                    INDEX idx_inscriptions_date (date_inscription)
                                )"
                        ];
                        
                        foreach ($missing_tables as $table) {
                            if (isset($create_queries[$table])) {
                                try {
                                    $db->exec($create_queries[$table]);
                                    echo "<i class='bi bi-check-circle success-icon me-2'></i>Table <strong>$table</strong> créée avec succès<br>";
                                    $corrections[] = "Table $table créée";
                                } catch (PDOException $e) {
                                    echo "<i class='bi bi-x-circle error-icon me-2'></i>Erreur lors de la création de <strong>$table</strong>: " . $e->getMessage() . "<br>";
                                    $error_count++;
                                }
                            }
                        }
                    }
                    
                    // Ajouter les données de référence
                    if (in_array('niveaux', $existing_tables) || in_array('niveaux', $missing_tables)) {
                        echo "<h6 class='mt-3'>Insertion des données de référence</h6>";
                        
                        try {
                            $db->exec("INSERT IGNORE INTO niveaux (ecole_id, nom, description, ordre) VALUES
                                (NULL, 'Maternelle', 'Petite section, Moyenne section, Grande section', 1),
                                (NULL, 'Primaire', 'CP, CE1, CE2, CM1, CM2', 2),
                                (NULL, 'Collège', '6ème, 5ème, 4ème, 3ème', 3),
                                (NULL, 'Lycée', 'Seconde, Première, Terminale', 4),
                                (NULL, 'Supérieur', 'Études supérieures', 5)");
                            echo "<i class='bi bi-check-circle success-icon me-2'></i>Niveaux standards insérés<br>";
                            $corrections[] = "Niveaux standards insérés";
                        } catch (PDOException $e) {
                            echo "<i class='bi bi-exclamation-triangle warning-icon me-2'></i>Avertissement lors de l'insertion des niveaux: " . $e->getMessage() . "<br>";
                        }
                        
                        try {
                            $db->exec("INSERT IGNORE INTO sections (ecole_id, nom, description) VALUES
                                (NULL, 'Générale', 'Section générale standard'),
                                (NULL, 'Scientifique', 'Section à dominante scientifique'),
                                (NULL, 'Littéraire', 'Section à dominante littéraire'),
                                (NULL, 'Technique', 'Section technique et technologique'),
                                (NULL, 'Professionnelle', 'Section professionnelle'),
                                (NULL, 'Bilingue', 'Section bilingue'),
                                (NULL, 'Internationale', 'Section internationale')");
                            echo "<i class='bi bi-check-circle success-icon me-2'></i>Sections standards insérées<br>";
                            $corrections[] = "Sections standards insérées";
                        } catch (PDOException $e) {
                            echo "<i class='bi bi-exclamation-triangle warning-icon me-2'></i>Avertissement lors de l'insertion des sections: " . $e->getMessage() . "<br>";
                        }
                    }
                    
                    echo "</div></div>";
                }
                
                // Étape 4: Création des vues
                echo "<div class='verification-card card mb-3'>";
                echo "<div class='card-header'><h5><i class='bi bi-4-circle me-2'></i>Création des vues</h5></div>";
                echo "<div class='card-body'>";
                
                try {
                    // Vue classes complètes
                    $db->exec("CREATE OR REPLACE VIEW vue_classes_completes AS
                        SELECT 
                            c.*,
                            n.nom as niveau_nom,
                            n.ordre as niveau_ordre,
                            s.nom as section_nom,
                            e.prenom as enseignant_prenom,
                            e.nom as enseignant_nom,
                            e.telephone as enseignant_telephone,
                            e.email as enseignant_email,
                            COUNT(DISTINCT i.eleve_id) as nombre_eleves,
                            ROUND((COUNT(DISTINCT i.eleve_id) / NULLIF(c.capacite_max, 0)) * 100, 2) as pourcentage_occupation,
                            CASE 
                                WHEN c.capacite_max IS NULL THEN 'illimitee'
                                WHEN COUNT(DISTINCT i.eleve_id) >= c.capacite_max THEN 'complete'
                                WHEN COUNT(DISTINCT i.eleve_id) >= (c.capacite_max * 0.8) THEN 'presque_complete'
                                ELSE 'disponible'
                            END as disponibilite,
                            uc.prenom as created_by_prenom,
                            uc.nom as created_by_nom
                        FROM classes c
                        LEFT JOIN niveaux n ON c.niveau_id = n.id
                        LEFT JOIN sections s ON c.section_id = s.id
                        LEFT JOIN enseignants e ON c.enseignant_principal_id = e.id
                        LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut = 'validée'
                        LEFT JOIN utilisateurs uc ON c.created_by = uc.id
                        GROUP BY c.id");
                    
                    echo "<i class='bi bi-check-circle success-icon me-2'></i>Vue <strong>vue_classes_completes</strong> créée avec succès<br>";
                    $success_count++;
                    
                } catch (PDOException $e) {
                    echo "<i class='bi bi-x-circle error-icon me-2'></i>Erreur lors de la création de la vue: " . $e->getMessage() . "<br>";
                    $error_count++;
                }
                
                try {
                    // Vue élèves avec classes
                    $db->exec("CREATE OR REPLACE VIEW vue_eleves_classes AS
                        SELECT 
                            el.*,
                            c.id as classe_id,
                            c.nom as classe_nom,
                            n.nom as niveau_nom,
                            s.nom as section_nom,
                            i.date_inscription as date_inscription_classe,
                            i.statut as statut_inscription
                        FROM eleves el
                        LEFT JOIN inscriptions i ON el.id = i.eleve_id AND i.statut = 'validée'
                        LEFT JOIN classes c ON i.classe_id = c.id
                        LEFT JOIN niveaux n ON c.niveau_id = n.id
                        LEFT JOIN sections s ON c.section_id = s.id");
                    
                    echo "<i class='bi bi-check-circle success-icon me-2'></i>Vue <strong>vue_eleves_classes</strong> créée avec succès<br>";
                    $success_count++;
                    
                } catch (PDOException $e) {
                    echo "<i class='bi bi-x-circle error-icon me-2'></i>Erreur lors de la création de la vue: " . $e->getMessage() . "<br>";
                    $error_count++;
                }
                
                echo "</div></div>";
                
                // Résumé final
                echo "<div class='card'>";
                echo "<div class='card-header'>";
                echo "<h5><i class='bi bi-flag-fill me-2'></i>Résumé de la vérification</h5>";
                echo "</div>";
                echo "<div class='card-body'>";
                
                if ($error_count == 0) {
                    echo "<div class='alert alert-success'>";
                    echo "<h6><i class='bi bi-check-circle-fill me-2'></i>Vérification réussie !</h6>";
                    echo "<p class='mb-0'>Toutes les tables et vues du module Classes sont correctement configurées.</p>";
                    echo "</div>";
                    
                    if (!empty($corrections)) {
                        echo "<div class='alert alert-info'>";
                        echo "<h6><i class='bi bi-info-circle me-2'></i>Corrections effectuées</h6>";
                        echo "<ul class='mb-0'>";
                        foreach ($corrections as $correction) {
                            echo "<li>$correction</li>";
                        }
                        echo "</ul>";
                        echo "</div>";
                    }
                    
                    echo "<div class='d-grid gap-2 d-md-flex justify-content-md-end'>";
                    echo "<a href='classes/index.php' class='btn btn-primary me-md-2'>";
                    echo "<i class='bi bi-building me-2'></i>Accéder aux Classes";
                    echo "</a>";
                    echo "<a href='classes/create.php' class='btn btn-success'>";
                    echo "<i class='bi bi-plus-circle me-2'></i>Créer une Classe";
                    echo "</a>";
                    echo "</div>";
                    
                } else {
                    echo "<div class='alert alert-danger'>";
                    echo "<h6><i class='bi bi-x-circle-fill me-2'></i>Problèmes détectés</h6>";
                    echo "<p class='mb-0'>$error_count erreur(s) détectée(s). Certaines fonctionnalités peuvent ne pas fonctionner correctement.</p>";
                    echo "</div>";
                    
                    echo "<div class='d-grid'>";
                    echo "<a href='install_classes_module.php' class='btn btn-warning'>";
                    echo "<i class='bi bi-arrow-clockwise me-2'></i>Réessayer l'Installation";
                    echo "</a>";
                    echo "</div>";
                }
                
                if (!empty($warnings)) {
                    echo "<div class='alert alert-warning mt-3'>";
                    echo "<h6><i class='bi bi-exclamation-triangle-fill me-2'></i>Avertissements</h6>";
                    echo "<ul class='mb-0'>";
                    foreach ($warnings as $warning) {
                        echo "<li>$warning</li>";
                    }
                    echo "</ul>";
                    echo "</div>";
                }
                
                echo "<div class='mt-3'>";
                echo "<small class='text-muted'>";
                echo "Vérifications réussies: $success_count | Erreurs: $error_count | Avertissements: " . count($warnings);
                if (!empty($corrections)) {
                    echo " | Corrections: " . count($corrections);
                }
                echo "</small>";
                echo "</div>";
                
                echo "</div></div>";
                ?>
                
                <div class="text-center mt-4">
                    <a href="auth/dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house me-2"></i>Retour au Tableau de Bord
                    </a>
                </div>
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
