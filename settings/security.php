<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$page_title = "Paramètres de Sécurité";
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
        .security-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .security-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #0077b6;
        }
        
        .security-status {
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
                <h1><i class="bi bi-shield-lock me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Configurez la sécurité de votre système</p>
            </div>
            
            <div class="topbar-actions">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
                
                <a href="security_logs.php" class="btn btn-primary">
                    <i class="bi bi-journal-text me-2"></i>Logs de Sécurité
                </a>
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
                        <div class="stat-icon bg-success">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3>100%</h3>
                            <p>Système Sécurisé</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div class="stat-content">
                            <h3>5</h3>
                            <p>Niveaux d'Accès</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stat-content">
                            <h3>24/7</h3>
                            <p>Surveillance</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-lock"></i>
                        </div>
                        <div class="stat-content">
                            <h3>256</h3>
                            <p>Bits de Chiffrement</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres de sécurité -->
            <div class="row g-4">
                <!-- Authentification -->
                <div class="col-lg-6">
                    <div class="card security-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-key me-2"></i>Authentification
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Connexion sécurisée HTTPS</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Mots de passe chiffrés</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Session sécurisée</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Déconnexion automatique</span>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="password_policy.php" class="btn btn-outline-primary">
                                    <i class="bi bi-shield-lock me-2"></i>Politique Mots de Passe
                                </a>
                                <a href="session_settings.php" class="btn btn-outline-info">
                                    <i class="bi bi-clock me-2"></i>Paramètres Session
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Autorisation -->
                <div class="col-lg-6">
                    <div class="card security-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-shield me-2"></i>Autorisation
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Gestion des rôles</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Permissions granulaires</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Accès par école</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Audit des accès</span>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="roles_permissions.php" class="btn btn-outline-primary">
                                    <i class="bi bi-gear me-2"></i>Rôles & Permissions
                                </a>
                                <a href="access_logs.php" class="btn btn-outline-info">
                                    <i class="bi bi-journal-text me-2"></i>Logs d'Accès
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Protection des données -->
                <div class="col-lg-6">
                    <div class="card security-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-database-lock me-2"></i>Protection des Données
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Chiffrement des données sensibles</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Sauvegarde sécurisée</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Protection contre les injections SQL</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Validation des entrées</span>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="data_encryption.php" class="btn btn-outline-primary">
                                    <i class="bi bi-lock me-2"></i>Chiffrement des Données
                                </a>
                                <a href="backup_security.php" class="btn btn-outline-info">
                                    <i class="bi bi-cloud-arrow-up me-2"></i>Sécurité Sauvegarde
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Surveillance -->
                <div class="col-lg-6">
                    <div class="card security-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-eye me-2"></i>Surveillance & Audit
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Logs de connexion</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Suivi des actions utilisateur</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Alertes de sécurité</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Rapports de sécurité</span>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="security_logs.php" class="btn btn-outline-primary">
                                    <i class="bi bi-journal-text me-2"></i>Logs de Sécurité
                                </a>
                                <a href="security_reports.php" class="btn btn-outline-info">
                                    <i class="bi bi-file-earmark-text me-2"></i>Rapports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- État de la sécurité -->
            <div class="row g-4 mt-4">
                <div class="col-12">
                    <div class="card security-status">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-shield-check me-2"></i>État de la Sécurité
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="border-end">
                                        <i class="bi bi-shield-check text-success fs-1 mb-2"></i>
                                        <h6>Authentification</h6>
                                        <span class="badge bg-success">Sécurisée</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border-end">
                                        <i class="bi bi-shield text-info fs-1 mb-2"></i>
                                        <h6>Autorisation</h6>
                                        <span class="badge bg-info">Active</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border-end">
                                        <i class="bi bi-database-lock text-warning fs-1 mb-2"></i>
                                        <h6>Données</h6>
                                        <span class="badge bg-warning">Protégées</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <i class="bi bi-eye text-primary fs-1 mb-2"></i>
                                    <h6>Surveillance</h6>
                                    <span class="badge bg-primary">En Temps Réel</span>
                                </div>
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
            const cards = document.querySelectorAll('.security-card');
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
    </script>
</body>
</html>

