<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$page_title = "Sauvegarde du Système";
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
    <link href="../assets/css/naklass-theme.css" rel="stylesheet">
    
    <style>
        .backup-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .backup-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #0077b6;
        }
        
        .backup-status {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
    </style>
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
                <h1><i class="bi bi-cloud-arrow-up me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Gérez les sauvegardes de votre système</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
                
                <button class="btn btn-primary" onclick="createBackup()">
                    <i class="bi bi-cloud-arrow-up me-2"></i>Créer Sauvegarde
                </button>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <?php
            $flash_messages = getFlashMessages();
            foreach ($flash_messages as $type => $message):
            ?>
                <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            
            <!-- Cartes de statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-cloud-arrow-up"></i>
                        </div>
                        <div class="stat-content">
                            <h3>5</h3>
                            <p>Sauvegardes Totales</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>3</h3>
                            <p>Sauvegardes Récentes</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stat-content">
                            <h3>24h</h3>
                            <p>Dernière Sauvegarde</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-hdd"></i>
                        </div>
                        <div class="stat-content">
                            <h3>2.5 GB</h3>
                            <p>Espace Utilisé</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions de sauvegarde -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-lightning-charge me-2"></i>Actions Rapides</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <button class="btn btn-outline-primary w-100 h-100 py-4" onclick="createBackup()">
                                        <i class="bi bi-cloud-arrow-up fs-1 d-block mb-2"></i>
                                        <span>Créer Sauvegarde Manuelle</span>
                                    </button>
                                </div>
                                
                                <div class="col-md-6">
                                    <button class="btn btn-outline-success w-100 h-100 py-4" onclick="scheduleBackup()">
                                        <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
                                        <span>Planifier Sauvegarde</span>
                                    </button>
                                </div>
                                
                                <div class="col-md-6">
                                    <button class="btn btn-outline-info w-100 h-100 py-4" onclick="restoreBackup()">
                                        <i class="bi bi-cloud-arrow-down fs-1 d-block mb-2"></i>
                                        <span>Restaurer Sauvegarde</span>
                                    </button>
                                </div>
                                
                                <div class="col-md-6">
                                    <button class="btn btn-outline-warning w-100 h-100 py-4" onclick="testBackup()">
                                        <i class="bi bi-shield-check fs-1 d-block mb-2"></i>
                                        <span>Tester Sauvegarde</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-info-circle me-2"></i>Informations</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-cloud-check text-success fs-3"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Sauvegarde Automatique</h6>
                                    <small class="text-muted">Tous les jours à 2h00</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-shield-lock text-info fs-3"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Chiffrement</h6>
                                    <small class="text-muted">AES-256 bits</small>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-clock text-warning fs-3"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Rétention</h6>
                                    <small class="text-muted">30 jours</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configuration de sauvegarde -->
            <div class="row g-4">
                <!-- Sauvegarde automatique -->
                <div class="col-lg-6">
                    <div class="card backup-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-clock me-2"></i>Sauvegarde Automatique
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Fréquence</label>
                                <select class="form-select">
                                    <option value="daily" selected>Quotidienne</option>
                                    <option value="weekly">Hebdomadaire</option>
                                    <option value="monthly">Mensuelle</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Heure de sauvegarde</label>
                                <input type="time" class="form-control" value="02:00">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tables à sauvegarder</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="backup_all" checked>
                                    <label class="form-check-label" for="backup_all">
                                        Toutes les tables
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="backup_files">
                                    <label class="form-check-label" for="backup_files">
                                        Fichiers uploadés
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button class="btn btn-primary" onclick="saveAutoBackupSettings()">
                                    <i class="bi bi-save me-2"></i>Enregistrer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Paramètres de stockage -->
                <div class="col-lg-6">
                    <div class="card backup-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-hdd me-2"></i>Paramètres de Stockage
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Emplacement de sauvegarde</label>
                                <select class="form-select">
                                    <option value="local" selected>Stockage local</option>
                                    <option value="ftp">Serveur FTP</option>
                                    <option value="cloud">Cloud (Google Drive, Dropbox)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Compression</label>
                                <select class="form-select">
                                    <option value="gzip" selected>GZIP (recommandé)</option>
                                    <option value="zip">ZIP</option>
                                    <option value="none">Aucune</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Rétention des sauvegardes</label>
                                <select class="form-select">
                                    <option value="7">7 jours</option>
                                    <option value="15">15 jours</option>
                                    <option value="30" selected>30 jours</option>
                                    <option value="90">90 jours</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Taille maximale par sauvegarde</label>
                                <select class="form-select">
                                    <option value="100">100 MB</option>
                                    <option value="500">500 MB</option>
                                    <option value="1000" selected>1 GB</option>
                                    <option value="unlimited">Illimitée</option>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button class="btn btn-primary" onclick="saveStorageSettings()">
                                    <i class="bi bi-save me-2"></i>Enregistrer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Historique des sauvegardes -->
                <div class="col-12">
                    <div class="card backup-status">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history me-2"></i>Historique des Sauvegardes
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Taille</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>25/08/2025 02:00</td>
                                            <td><span class="badge bg-primary">Automatique</span></td>
                                            <td>2.1 GB</td>
                                            <td><span class="badge bg-success">Réussie</span></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="downloadBackup('backup_20250825_020000.sql')">
                                                        <i class="bi bi-download"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="restoreBackup('backup_20250825_020000.sql')">
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteBackup('backup_20250825_020000.sql')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>24/08/2025 02:00</td>
                                            <td><span class="badge bg-primary">Automatique</span></td>
                                            <td>2.0 GB</td>
                                            <td><span class="badge bg-success">Réussie</span></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="downloadBackup('backup_20250824_020000.sql')">
                                                        <i class="bi bi-download"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="restoreBackup('backup_20250824_020000.sql')">
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteBackup('backup_20250824_020000.sql')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>23/08/2025 15:30</td>
                                            <td><span class="badge bg-warning">Manuelle</span></td>
                                            <td>2.2 GB</td>
                                            <td><span class="badge bg-success">Réussie</span></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="downloadBackup('backup_20250823_153000.sql')">
                                                        <i class="bi bi-download"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="restoreBackup('backup_20250823_153000.sql')">
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteBackup('backup_20250823_153000.sql')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <script>
        // Animation des cartes au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.backup-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // Fonctions de sauvegarde
        function createBackup() {
            if (confirm('Voulez-vous créer une sauvegarde manuelle maintenant ?')) {
                // Simulation de création de sauvegarde
                alert('Sauvegarde en cours... Veuillez patienter.');
                // Ici, vous ajouteriez le code PHP pour créer la sauvegarde
            }
        }
        
        function scheduleBackup() {
            alert('Fonctionnalité de planification à implémenter.');
        }
        
        function restoreBackup() {
            alert('Fonctionnalité de restauration à implémenter.');
        }
        
        function testBackup() {
            alert('Fonctionnalité de test à implémenter.');
        }
        
        function saveAutoBackupSettings() {
            alert('Paramètres de sauvegarde automatique enregistrés.');
        }
        
        function saveStorageSettings() {
            alert('Paramètres de stockage enregistrés.');
        }
        
        function downloadBackup(filename) {
            alert('Téléchargement de ' + filename + ' en cours...');
        }
        
        function deleteBackup(filename) {
            if (confirm('Êtes-vous sûr de vouloir supprimer la sauvegarde ' + filename + ' ?')) {
                alert('Sauvegarde supprimée.');
            }
        }
    </script>
</body>
</html>

