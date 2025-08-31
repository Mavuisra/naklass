<?php
require_once 'config/database.php';

// Vérifier que l'utilisateur est connecté et est un administrateur
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Module Classes - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .installation-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .step-card {
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .step-card:hover {
            transform: translateX(5px);
        }
        .success-icon { color: #28a745; }
        .warning-icon { color: #ffc107; }
        .error-icon { color: #dc3545; }
    </style>
</head>
<body>
    <div class="installation-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-building me-3"></i>Installation Module Classes</h1>
                    <p class="mb-0">Configuration du système de gestion des classes et des niveaux scolaires</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <i class="bi bi-gear-fill display-4 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php
                $success_count = 0;
                $error_count = 0;
                $warnings = [];
                
                try {
                    echo "<div class='step-card card mb-3'>";
                    echo "<div class='card-header'><h5><i class='bi bi-1-circle me-2'></i>Lecture du fichier SQL</h5></div>";
                    echo "<div class='card-body'>";
                    
                    $sql_file = 'database/07_module_classes_fixed.sql';
                    if (!file_exists($sql_file)) {
                        throw new Exception("Fichier SQL introuvable: $sql_file");
                    }
                    
                    $sql_content = file_get_contents($sql_file);
                    if ($sql_content === false) {
                        throw new Exception("Impossible de lire le fichier SQL");
                    }
                    
                    echo "<i class='bi bi-check-circle success-icon me-2'></i>Fichier SQL chargé avec succès<br>";
                    echo "<small class='text-muted'>Taille: " . number_format(strlen($sql_content)) . " caractères</small>";
                    echo "</div></div>";
                    $success_count++;
                    
                } catch (Exception $e) {
                    echo "<i class='bi bi-x-circle error-icon me-2'></i>Erreur: " . $e->getMessage();
                    echo "</div></div>";
                    $error_count++;
                }
                
                // Étape 2: Vérification des dépendances
                echo "<div class='step-card card mb-3'>";
                echo "<div class='card-header'><h5><i class='bi bi-2-circle me-2'></i>Vérification des dépendances</h5></div>";
                echo "<div class='card-body'>";
                
                try {
                    // Vérifier que les tables nécessaires existent
                    $required_tables = ['ecoles', 'eleves', 'enseignants', 'utilisateurs'];
                    $missing_tables = [];
                    
                    foreach ($required_tables as $table) {
                        $check_query = "SHOW TABLES LIKE '$table'";
                        $result = $db->query($check_query);
                        if ($result->rowCount() == 0) {
                            $missing_tables[] = $table;
                        }
                    }
                    
                    if (empty($missing_tables)) {
                        echo "<i class='bi bi-check-circle success-icon me-2'></i>Toutes les tables requises sont présentes<br>";
                        echo "<small class='text-muted'>Tables vérifiées: " . implode(', ', $required_tables) . "</small>";
                        $success_count++;
                    } else {
                        throw new Exception("Tables manquantes: " . implode(', ', $missing_tables));
                    }
                    
                } catch (Exception $e) {
                    echo "<i class='bi bi-x-circle error-icon me-2'></i>Erreur: " . $e->getMessage();
                    $error_count++;
                }
                echo "</div></div>";
                
                // Étape 3: Exécution du SQL
                if ($error_count == 0) {
                    echo "<div class='step-card card mb-3'>";
                    echo "<div class='card-header'><h5><i class='bi bi-3-circle me-2'></i>Installation des tables et données</h5></div>";
                    echo "<div class='card-body'>";
                    
                    try {
                        // Nettoyer le contenu SQL et diviser en requêtes individuelles
                        // Supprimer les commentaires sur une ligne
                        $sql_content = preg_replace('/--.*$/m', '', $sql_content);
                        
                        // Diviser le contenu SQL en requêtes individuelles
                        // Utiliser un regex plus sophistiqué pour gérer les requêtes complexes
                        $sql_statements = preg_split('/;\s*$/m', $sql_content);
                        $executed_count = 0;
                        $skipped_count = 0;
                        
                        foreach ($sql_statements as $statement) {
                            $statement = trim($statement);
                            
                            // Ignorer les lignes vides et les commentaires
                            if (empty($statement) || strpos($statement, '--') === 0) {
                                continue;
                            }
                            
                            // Ignorer les commandes DELIMITER
                            if (stripos($statement, 'DELIMITER') !== false) {
                                continue;
                            }
                            
                            try {
                                // Ajouter le point-virgule à la fin si nécessaire
                                if (substr($statement, -1) !== ';') {
                                    $statement .= ';';
                                }
                                
                                $db->exec($statement);
                                $executed_count++;
                                
                            } catch (PDOException $e) {
                                // Gérer les erreurs spécifiques
                                $error_msg = $e->getMessage();
                                
                                // Ignorer les erreurs non critiques
                                if (strpos($error_msg, 'already exists') !== false) {
                                    $warnings[] = "Table ou index déjà existant (ignoré)";
                                    $skipped_count++;
                                } elseif (strpos($error_msg, 'Duplicate entry') !== false) {
                                    $warnings[] = "Donnée déjà existante (ignorée)";
                                    $skipped_count++;
                                } elseif (strpos($error_msg, 'Unknown column') !== false && strpos($statement, 'ALTER TABLE') !== false) {
                                    // Ignorer les erreurs de colonnes dans les ALTER TABLE COMMENT
                                    $warnings[] = "Commentaire de table ignoré";
                                    $skipped_count++;
                                } else {
                                    // Pour les vraies erreurs, afficher plus de détails
                                    throw new Exception($e->getMessage() . "\nRequête: " . substr($statement, 0, 100) . "...");
                                }
                            }
                        }
                        
                        echo "<i class='bi bi-check-circle success-icon me-2'></i>Installation réussie<br>";
                        echo "<small class='text-muted'>$executed_count requêtes exécutées avec succès";
                        if ($skipped_count > 0) {
                            echo " ($skipped_count ignorées)";
                        }
                        echo "</small>";
                        $success_count++;
                        
                    } catch (Exception $e) {
                        echo "<i class='bi bi-x-circle error-icon me-2'></i>Erreur SQL: " . $e->getMessage();
                        $error_count++;
                    }
                    echo "</div></div>";
                }
                
                // Étape 4: Vérification post-installation
                if ($error_count == 0) {
                    echo "<div class='step-card card mb-3'>";
                    echo "<div class='card-header'><h5><i class='bi bi-4-circle me-2'></i>Vérification post-installation</h5></div>";
                    echo "<div class='card-body'>";
                    
                    try {
                        $created_tables = ['niveaux', 'sections', 'classes', 'inscriptions'];
                        $verification_results = [];
                        
                        foreach ($created_tables as $table) {
                            $count_query = "SELECT COUNT(*) as count FROM $table";
                            $stmt = $db->prepare($count_query);
                            $stmt->execute();
                            $count = $stmt->fetch()['count'];
                            $verification_results[] = "$table: $count enregistrement(s)";
                        }
                        
                        echo "<i class='bi bi-check-circle success-icon me-2'></i>Vérification terminée<br>";
                        echo "<small class='text-muted'>" . implode('<br>', $verification_results) . "</small>";
                        $success_count++;
                        
                    } catch (Exception $e) {
                        echo "<i class='bi bi-exclamation-triangle warning-icon me-2'></i>Avertissement: " . $e->getMessage();
                        $warnings[] = "Vérification partielle";
                    }
                    echo "</div></div>";
                }
                
                // Résumé final
                echo "<div class='card'>";
                echo "<div class='card-header'>";
                echo "<h5><i class='bi bi-flag-fill me-2'></i>Résumé de l'installation</h5>";
                echo "</div>";
                echo "<div class='card-body'>";
                
                if ($error_count == 0) {
                    echo "<div class='alert alert-success'>";
                    echo "<h6><i class='bi bi-check-circle-fill me-2'></i>Installation réussie !</h6>";
                    echo "<p class='mb-0'>Le module Classes a été installé avec succès. Vous pouvez maintenant :</p>";
                    echo "<ul class='mt-2'>";
                    echo "<li>Créer et gérer des classes</li>";
                    echo "<li>Assigner des élèves aux classes</li>";
                    echo "<li>Organiser par niveaux et sections</li>";
                    echo "<li>Gérer les capacités et horaires</li>";
                    echo "</ul>";
                    echo "</div>";
                    
                    echo "<div class='d-grid gap-2 d-md-flex justify-content-md-end'>";
                    echo "<a href='classes/index.php' class='btn btn-primary me-md-2'>";
                    echo "<i class='bi bi-building me-2'></i>Accéder aux Classes";
                    echo "</a>";
                    echo "<a href='classes/create.php' class='btn btn-outline-primary'>";
                    echo "<i class='bi bi-plus-circle me-2'></i>Créer une Classe";
                    echo "</a>";
                    echo "</div>";
                    
                } else {
                    echo "<div class='alert alert-danger'>";
                    echo "<h6><i class='bi bi-x-circle-fill me-2'></i>Échec de l'installation</h6>";
                    echo "<p class='mb-0'>$error_count erreur(s) détectée(s). Veuillez corriger les problèmes et réessayer.</p>";
                    echo "</div>";
                    
                    echo "<div class='d-grid'>";
                    echo "<a href='install_classes_module.php' class='btn btn-warning'>";
                    echo "<i class='bi bi-arrow-clockwise me-2'></i>Réessayer l'Installation";
                    echo "</a>";
                    echo "</div>";
                }
                
                // Afficher les avertissements s'il y en a
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
                echo "Étapes réussies: $success_count | Erreurs: $error_count | Avertissements: " . count($warnings);
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
