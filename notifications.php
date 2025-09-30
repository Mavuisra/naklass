<?php
/**
 * Page principale des Notifications Naklass
 * Centre de gestion des notifications
 */

require_once 'includes/functions.php';

// Vérifier l'authentification
requireAuth();

// Vérifier la configuration de l'école
requireSchoolSetup();

require_once 'includes/NotificationManager.php';

$notificationManager = new NotificationManager();

// Traitement des actions
$action = $_GET['action'] ?? '';
$notification_id = $_GET['id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notification_id = $_POST['notification_id'] ?? '';
}

$success_message = '';
$error_message = '';

try {
    switch ($action) {
        case 'mark_read':
            if ($notification_id && $notificationManager->markAsRead($notification_id)) {
                $success_message = 'Notification marquée comme lue.';
            } else {
                $error_message = 'Erreur lors du marquage de la notification.';
            }
            break;
            
        case 'mark_all_read':
            if ($notificationManager->markAllAsRead()) {
                $success_message = 'Toutes les notifications ont été marquées comme lues.';
            } else {
                $error_message = 'Erreur lors du marquage des notifications.';
            }
            break;
            
        case 'archive':
            if ($notification_id && $notificationManager->archiveNotification($notification_id)) {
                $success_message = 'Notification archivée.';
            } else {
                $error_message = 'Erreur lors de l\'archivage de la notification.';
            }
            break;
            
        case 'delete':
            if ($notification_id && $notificationManager->deleteNotification($notification_id)) {
                $success_message = 'Notification supprimée.';
            } else {
                $error_message = 'Erreur lors de la suppression de la notification.';
            }
            break;
    }
} catch (Exception $e) {
    $error_message = 'Erreur: ' . $e->getMessage();
}

// Récupérer les filtres
$filters = [
    'status' => $_GET['status'] ?? '',
    'type' => $_GET['type'] ?? '',
    'category' => $_GET['category'] ?? '',
    'limit' => 20,
    'offset' => ($_GET['page'] ?? 1) * 20 - 20
];

// Récupérer les notifications
$notifications = $notificationManager->getUserNotifications(null, $filters);

// Récupérer les statistiques
$stats = $notificationManager->getNotificationStats();

// Récupérer les types et catégories disponibles
$available_types = NotificationManager::getAvailableTypes();
$available_categories = NotificationManager::getAvailableCategories();

$page_title = "Notifications";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
    <link href="assets/css/common.css" rel="stylesheet">
    <style>
        .notification-card {
            border-left: 4px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .notification-card.unread {
            border-left-color: #0d6efd;
            background-color: #f8f9ff;
        }
        
        .notification-card.high-priority {
            border-left-color: #fd7e14;
        }
        
        .notification-card.urgent-priority {
            border-left-color: #dc3545;
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .notification-icon {
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f8f9fa;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .notification-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .notification-card:hover .notification-actions {
            opacity: 1;
        }
        
        .category-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .priority-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        .priority-low { background-color: #6c757d; }
        .priority-normal { background-color: #0d6efd; }
        .priority-high { background-color: #fd7e14; }
        .priority-urgent { background-color: #dc3545; }
    </style>
</head>
<body>
    <!-- Navigation latérale -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Contenu principal -->
    <main class="main-content">
        <!-- Barre supérieure -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-bell-fill me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Gérez vos notifications et restez informé des activités importantes</p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="auth/dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item active">Notifications</li>
                    </ol>
                </nav>
            </div>
        </header>
        
        <!-- Contenu de la page -->
        <div class="container-fluid">
            <!-- Messages flash -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-bell-fill" style="font-size: 2rem;"></i>
                            <h3 class="mt-2"><?php echo $stats['total'] ?? 0; ?></h3>
                            <p class="mb-0">Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-bell" style="font-size: 2rem;"></i>
                            <h3 class="mt-2"><?php echo $stats['unread'] ?? 0; ?></h3>
                            <p class="mb-0">Non lues</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                            <h3 class="mt-2"><?php echo $stats['read_count'] ?? 0; ?></h3>
                            <p class="mb-0">Lues</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="bi bi-archive" style="font-size: 2rem;"></i>
                            <h3 class="mt-2"><?php echo $stats['archived'] ?? 0; ?></h3>
                            <p class="mb-0">Archivées</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Actions rapides</h5>
                                <div class="btn-group">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="mark_all_read">
                                        <button type="submit" class="btn btn-outline-primary">
                                            <i class="bi bi-check-all me-2"></i>Marquer tout comme lu
                                        </button>
                                    </form>
                                    <button class="btn btn-outline-secondary" onclick="refreshNotifications()">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Actualiser
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtres -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card filter-card">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Statut</label>
                                    <select name="status" class="form-select">
                                        <option value="">Tous les statuts</option>
                                        <option value="unread" <?php echo $filters['status'] === 'unread' ? 'selected' : ''; ?>>Non lues</option>
                                        <option value="read" <?php echo $filters['status'] === 'read' ? 'selected' : ''; ?>>Lues</option>
                                        <option value="archived" <?php echo $filters['status'] === 'archived' ? 'selected' : ''; ?>>Archivées</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Catégorie</label>
                                    <select name="category" class="form-select">
                                        <option value="">Toutes les catégories</option>
                                        <?php foreach ($available_categories as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $filters['category'] === $key ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Type</label>
                                    <select name="type" class="form-select">
                                        <option value="">Tous les types</option>
                                        <?php foreach ($available_types as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $filters['type'] === $key ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-funnel me-2"></i>Filtrer
                                        </button>
                                        <a href="notifications.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-circle me-2"></i>Effacer
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Liste des notifications -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($notifications)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-bell-slash text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3 text-muted">Aucune notification</h4>
                                <p class="text-muted">Vous n'avez aucune notification correspondant à vos critères.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="card notification-card mb-3 <?php echo $notification['status'] === 'unread' ? 'unread' : ''; ?> <?php echo $notification['priority'] . '-priority'; ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-1">
                                            <div class="notification-icon">
                                                <?php
                                                $icon_class = 'bi-bell';
                                                switch ($notification['category']) {
                                                    case 'academic':
                                                        $icon_class = 'bi-book';
                                                        break;
                                                    case 'financial':
                                                        $icon_class = 'bi-currency-dollar';
                                                        break;
                                                    case 'user':
                                                        $icon_class = 'bi-person';
                                                        break;
                                                    case 'school':
                                                        $icon_class = 'bi-building';
                                                        break;
                                                    case 'alert':
                                                        $icon_class = 'bi-exclamation-triangle';
                                                        break;
                                                    case 'security':
                                                        $icon_class = 'bi-shield-lock';
                                                        break;
                                                    case 'system':
                                                        $icon_class = 'bi-gear';
                                                        break;
                                                }
                                                ?>
                                                <i class="bi <?php echo $icon_class; ?>"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-start">
                                                <span class="priority-indicator priority-<?php echo $notification['priority']; ?>"></span>
                                                <div>
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($notification['title']); ?>
                                                        <?php if ($notification['status'] === 'unread'): ?>
                                                            <span class="badge bg-primary ms-2">Nouveau</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <small class="text-muted">
                                                            <i class="bi bi-clock me-1"></i>
                                                            <?php echo formatDateTime($notification['created_at'], 'd/m/Y H:i'); ?>
                                                        </small>
                                                        <span class="badge category-badge bg-secondary">
                                                            <?php echo htmlspecialchars($available_categories[$notification['category']] ?? $notification['category']); ?>
                                                        </span>
                                                        <span class="badge category-badge bg-info">
                                                            <?php echo htmlspecialchars($available_types[$notification['type']] ?? $notification['type']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <div class="notification-actions d-flex justify-content-end gap-2">
                                                <?php if ($notification['action_url']): ?>
                                                    <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <?php echo htmlspecialchars($notification['action_text'] ?: 'Voir'); ?>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($notification['status'] === 'unread'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="mark_read">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Marquer comme lu">
                                                            <i class="bi bi-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="archive">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Archiver">
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette notification ?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshNotifications() {
            location.reload();
        }
        
        // Auto-refresh toutes les 2 minutes
        setInterval(refreshNotifications, 120000);
    </script>
</body>
</html>
