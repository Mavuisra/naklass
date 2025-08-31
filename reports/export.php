<?php
/**
 * Page d'Export des Rapports
 * Permet d'exporter les données du tableau de bord
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

// Traitement de l'export
if ($_POST['action'] ?? '' === 'export') {
    $format = $_POST['format'] ?? 'pdf';
    $sections = $_POST['sections'] ?? [];
    
    if ($format === 'pdf') {
        exportToPDF($ecole_id, $sections);
    } elseif ($format === 'excel') {
        exportToExcel($ecole_id, $sections);
    }
}

// Fonction d'export PDF
function exportToPDF($ecole_id, $sections) {
    // Ici vous pouvez intégrer une bibliothèque comme TCPDF ou FPDF
    // Pour l'instant, on génère un fichier texte simple
    
    $filename = "rapport_ecole_" . date('Y-m-d_H-i-s') . ".txt";
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "RAPPORT DE L'ÉCOLE\n";
    echo "==================\n\n";
    echo "Date de génération: " . date('d/m/Y H:i:s') . "\n\n";
    
    // Ajouter les sections sélectionnées
    foreach ($sections as $section) {
        echo "SECTION: " . strtoupper($section) . "\n";
        echo str_repeat("-", strlen($section) + 9) . "\n";
        // Ici vous ajouteriez les données de chaque section
        echo "Données de la section $section\n\n";
    }
    
    exit();
}

// Fonction d'export Excel
function exportToExcel($ecole_id, $sections) {
    // Ici vous pouvez intégrer une bibliothèque comme PhpSpreadsheet
    // Pour l'instant, on génère un fichier CSV
    
    $filename = "rapport_ecole_" . date('Y-m-d_H-i-s') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes CSV
    fputcsv($output, ['Section', 'Métrique', 'Valeur', 'Date']);
    
    // Données d'exemple
    foreach ($sections as $section) {
        fputcsv($output, [$section, 'Total', '100', date('Y-m-d')]);
        fputcsv($output, [$section, 'Actifs', '80', date('Y-m-d')]);
        fputcsv($output, [$section, 'Inactifs', '20', date('Y-m-d')]);
    }
    
    fclose($output);
    exit();
}

// Récupérer les données pour l'aperçu
try {
    // Informations de l'école
    $ecole_query = "SELECT * FROM ecoles WHERE id = ?";
    $ecole_stmt = $db->prepare($ecole_query);
    $ecole_stmt->execute([$ecole_id]);
    $ecole = $ecole_stmt->fetch();
    
    // Statistiques générales
    $stats_query = "SELECT 
                      (SELECT COUNT(*) FROM classes WHERE ecole_id = ?) as total_classes,
                      (SELECT COUNT(*) FROM inscriptions WHERE ecole_id = ?) as total_inscriptions,
                      (SELECT COUNT(*) FROM cours WHERE ecole_id = ?) as total_cours,
                      (SELECT COUNT(*) FROM utilisateurs WHERE ecole_id = ?) as total_users";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$ecole_id, $ecole_id, $ecole_id, $ecole_id]);
    $stats = $stats_stmt->fetch();
    
} catch (Exception $e) {
    $error_message = "Erreur lors de la récupération des données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export des Rapports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .export-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
        }
        .export-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .export-card:hover {
            transform: translateY(-5px);
        }
        .format-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .format-option:hover {
            border-color: #28a745;
            background-color: #f8f9fa;
        }
        .format-option.selected {
            border-color: #28a745;
            background-color: #d4edda;
        }
        .section-checkbox {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .section-checkbox:hover {
            background-color: #f8f9fa;
        }
        .section-checkbox.selected {
            background-color: #e7f3ff;
            border-color: #007bff;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <div class="export-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-download me-3"></i>Export des Rapports</h1>
                    <p class="mb-0">Générez et téléchargez vos rapports au format PDF ou Excel</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="index.php" class="btn btn-outline-light me-2">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                    <a href="../index.php" class="btn btn-light">
                        <i class="bi bi-house"></i> Accueil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Configuration de l'export -->
            <div class="col-lg-8">
                <div class="card export-card">
                    <div class="card-header">
                        <h5><i class="bi bi-gear me-2"></i>Configuration de l'Export</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <!-- Format d'export -->
                            <div class="mb-4">
                                <h6><i class="bi bi-file-earmark me-2"></i>Format d'Export</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="format-option" onclick="selectFormat('pdf')">
                                            <div class="d-flex align-items-center">
                                                <input type="radio" name="format" value="pdf" id="format-pdf" class="me-3" checked>
                                                <div>
                                                    <i class="bi bi-file-pdf text-danger fs-1"></i>
                                                    <h6 class="mb-1">PDF</h6>
                                                    <small class="text-muted">Document portable et lisible</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="format-option" onclick="selectFormat('excel')">
                                            <div class="d-flex align-items-center">
                                                <input type="radio" name="format" value="excel" id="format-excel" class="me-3">
                                                <div>
                                                    <i class="bi bi-file-earmark-excel text-success fs-1"></i>
                                                    <h6 class="mb-1">Excel</h6>
                                                    <small class="text-muted">Tableau de données modifiable</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sections à exporter -->
                            <div class="mb-4">
                                <h6><i class="bi bi-list-check me-2"></i>Sections à Exporter</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="section-checkbox" onclick="toggleSection(this, 'classes')">
                                            <div class="d-flex align-items-center">
                                                <input type="checkbox" name="sections[]" value="classes" id="section-classes" class="me-3" checked>
                                                <div>
                                                    <i class="bi bi-people text-info me-2"></i>
                                                    <strong>Classes</strong>
                                                    <br><small class="text-muted">Informations sur les classes et niveaux</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="section-checkbox" onclick="toggleSection(this, 'inscriptions')">
                                            <div class="d-flex align-items-center">
                                                <input type="checkbox" name="sections[]" value="inscriptions" id="section-inscriptions" class="me-3" checked>
                                                <div>
                                                    <i class="bi bi-person-plus text-success me-2"></i>
                                                    <strong>Inscriptions</strong>
                                                    <br><small class="text-muted">Statistiques des inscriptions</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="section-checkbox" onclick="toggleSection(this, 'cours')">
                                            <div class="d-flex align-items-center">
                                                <input type="checkbox" name="sections[]" value="cours" id="section-cours" class="me-3" checked>
                                                <div>
                                                    <i class="bi bi-book text-warning me-2"></i>
                                                    <strong>Cours</strong>
                                                    <br><small class="text-muted">Programme et matières enseignées</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="section-checkbox" onclick="toggleSection(this, 'notes')">
                                            <div class="d-flex align-items-center">
                                                <input type="checkbox" name="sections[]" value="notes" id="section-notes" class="me-3" checked>
                                                <div>
                                                    <i class="bi bi-star text-warning me-2"></i>
                                                    <strong>Notes</strong>
                                                    <br><small class="text-muted">Résultats et moyennes</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="section-checkbox" onclick="toggleSection(this, 'finances')">
                                            <div class="d-flex align-items-center">
                                                <input type="checkbox" name="sections[]" value="finances" id="section-finances" class="me-3" checked>
                                                <div>
                                                    <i class="bi bi-cash-coin text-success me-2"></i>
                                                    <strong>Finances</strong>
                                                    <br><small class="text-muted">Recettes, dépenses et solde</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="section-checkbox" onclick="toggleSection(this, 'presence')">
                                            <div class="d-flex align-items-center">
                                                <input type="checkbox" name="sections[]" value="presence" id="section-presence" class="me-3" checked>
                                                <div>
                                                    <i class="bi bi-calendar-check text-info me-2"></i>
                                                    <strong>Présence</strong>
                                                    <br><small class="text-muted">Statistiques de présence</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Boutons d'action -->
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="selectAllSections()">
                                    <i class="bi bi-check-all me-2"></i>Tout Sélectionner
                                </button>
                                <button type="submit" name="action" value="export" class="btn btn-success btn-lg">
                                    <i class="bi bi-download me-2"></i>Générer l'Export
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Aperçu des données -->
            <div class="col-lg-4">
                <div class="card export-card">
                    <div class="card-header">
                        <h5><i class="bi bi-eye me-2"></i>Aperçu des Données</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6><i class="bi bi-building me-2"></i><?php echo htmlspecialchars($ecole['nom_ecole'] ?? 'École'); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($ecole['adresse'] ?? 'Adresse non disponible'); ?></small>
                        </div>
                        
                        <hr>
                        
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="text-primary fs-4 fw-bold"><?php echo number_format($stats['total_classes'] ?? 0); ?></div>
                                <small class="text-muted">Classes</small>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="text-success fs-4 fw-bold"><?php echo number_format($stats['total_inscriptions'] ?? 0); ?></div>
                                <small class="text-muted">Inscriptions</small>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="text-warning fs-4 fw-bold"><?php echo number_format($stats['total_cours'] ?? 0); ?></div>
                                <small class="text-muted">Cours</small>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="text-info fs-4 fw-bold"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                                <small class="text-muted">Utilisateurs</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                L'export inclura toutes les sections sélectionnées avec leurs données respectives.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sélection du format
        function selectFormat(format) {
            // Désélectionner tous les formats
            document.querySelectorAll('input[name="format"]').forEach(input => {
                input.checked = false;
            });
            
            // Sélectionner le format choisi
            document.getElementById('format-' + format).checked = true;
            
            // Mettre à jour l'apparence
            document.querySelectorAll('.format-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            event.currentTarget.classList.add('selected');
        }

        // Toggle des sections
        function toggleSection(element, sectionId) {
            const checkbox = document.getElementById('section-' + sectionId);
            checkbox.checked = !checkbox.checked;
            
            // Mettre à jour l'apparence
            if (checkbox.checked) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
        }

        // Sélectionner toutes les sections
        function selectAllSections() {
            document.querySelectorAll('input[name="sections[]"]').forEach(checkbox => {
                checkbox.checked = true;
            });
            
            document.querySelectorAll('.section-checkbox').forEach(section => {
                section.classList.add('selected');
            });
        }

        // Initialiser l'apparence
        document.addEventListener('DOMContentLoaded', function() {
            // Sélectionner le format PDF par défaut
            document.querySelector('.format-option').classList.add('selected');
            
            // Sélectionner toutes les sections par défaut
            selectAllSections();
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
