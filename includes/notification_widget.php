<?php
/**
 * Widget de Notifications Naklass
 * Affichage des notifications en temps réel
 */

require_once __DIR__ . '/NotificationManager.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    return;
}

try {
    $notificationManager = new NotificationManager();
    
    // Récupérer les notifications non lues
    $unread_count = $notificationManager->getUnreadCount();
    
    // Récupérer les dernières notifications (limitées à 5 pour le widget)
    $recent_notifications = $notificationManager->getUserNotifications(null, [
        'limit' => 5,
        'unread_only' => false
    ]);
    
} catch (Exception $e) {
    error_log("Erreur widget notifications: " . $e->getMessage());
    $unread_count = 0;
    $recent_notifications = [];
}
?>

<!-- Widget de Notifications -->
<div class="notification-widget position-relative">
    <!-- Bouton de notification avec badge -->
    <button class="btn btn-outline-light position-relative" id="notificationToggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell-fill"></i>
        <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
            </span>
        <?php endif; ?>
    </button>
    
    <!-- Dropdown des notifications -->
    <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationToggle">
        <div class="notification-header">
            <h6 class="mb-0">
                <i class="bi bi-bell me-2"></i>Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-primary ms-2"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </h6>
            <div class="notification-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="markAllAsRead()">
                    <i class="bi bi-check-all"></i> Tout marquer lu
                </button>
                <a href="../notifications.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-list"></i> Voir tout
                </a>
            </div>
        </div>
        
        <div class="notification-list">
            <?php if (empty($recent_notifications)): ?>
                <div class="notification-item notification-empty">
                    <div class="text-center py-3">
                        <i class="bi bi-bell-slash text-muted" style="font-size: 2rem;"></i>
                        <p class="text-muted mb-0">Aucune notification</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($recent_notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['status'] === 'unread' ? 'unread' : ''; ?>" 
                         data-notification-id="<?php echo $notification['id']; ?>">
                        <div class="notification-content">
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
                                
                                $priority_class = '';
                                switch ($notification['priority']) {
                                    case 'urgent':
                                        $priority_class = 'text-danger';
                                        break;
                                    case 'high':
                                        $priority_class = 'text-warning';
                                        break;
                                    case 'normal':
                                        $priority_class = 'text-primary';
                                        break;
                                    case 'low':
                                        $priority_class = 'text-muted';
                                        break;
                                }
                                ?>
                                <i class="bi <?php echo $icon_class; ?> <?php echo $priority_class; ?>"></i>
                            </div>
                            
                            <div class="notification-body">
                                <h6 class="notification-title mb-1">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                    <?php if ($notification['status'] === 'unread'): ?>
                                        <span class="badge bg-primary ms-1">Nouveau</span>
                                    <?php endif; ?>
                                </h6>
                                <p class="notification-message mb-1">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                <small class="notification-time text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo formatDateTime($notification['created_at'], 'd/m/Y H:i'); ?>
                                </small>
                            </div>
                            
                            <?php if ($notification['action_url']): ?>
                                <div class="notification-action">
                                    <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" 
                                       class="btn btn-sm btn-outline-primary"
                                       onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                        <?php echo htmlspecialchars($notification['action_text'] ?: 'Voir'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-footer">
                            <button class="btn btn-sm btn-outline-secondary" 
                                    onclick="markAsRead(<?php echo $notification['id']; ?>)"
                                    title="Marquer comme lu">
                                <i class="bi bi-check"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteNotification(<?php echo $notification['id']; ?>)"
                                    title="Supprimer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-footer-actions">
            <a href="../notifications.php" class="btn btn-primary w-100">
                <i class="bi bi-list me-2"></i>Voir toutes les notifications
            </a>
        </div>
    </div>
</div>

<style>
.notification-widget .dropdown-menu {
    width: 400px;
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.notification-header {
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
}

.notification-actions {
    margin-top: 0.5rem;
    display: flex;
    gap: 0.5rem;
}

.notification-list {
    max-height: 350px;
    overflow-y: auto;
}

.notification-item {
    padding: 1rem;
    border-bottom: 1px solid #f1f3f4;
    transition: background-color 0.2s;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #e3f2fd;
    border-left: 4px solid #2196f3;
}

.notification-content {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.notification-icon {
    flex-shrink: 0;
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 50%;
}

.notification-body {
    flex: 1;
    min-width: 0;
}

.notification-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #212529;
}

.notification-message {
    font-size: 0.8rem;
    color: #6c757d;
    line-height: 1.4;
}

.notification-time {
    font-size: 0.75rem;
}

.notification-action {
    flex-shrink: 0;
}

.notification-footer {
    margin-top: 0.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.25rem;
}

.notification-empty {
    text-align: center;
    padding: 2rem 1rem;
}

.notification-footer-actions {
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
}

.notification-badge {
    font-size: 0.7rem;
    min-width: 1.2rem;
    height: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Animation pour les nouvelles notifications */
@keyframes notificationPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification-item.unread .notification-icon {
    animation: notificationPulse 2s infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .notification-widget .dropdown-menu {
        width: 300px;
    }
    
    .notification-content {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .notification-action {
        align-self: flex-start;
    }
}
</style>

<script>
// Fonctions JavaScript pour la gestion des notifications
function markAsRead(notificationId) {
    fetch('../ajax/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            notification_id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mettre à jour l'interface
            const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.classList.remove('unread');
                const badge = notificationItem.querySelector('.badge');
                if (badge) {
                    badge.remove();
                }
            }
            
            // Mettre à jour le compteur
            updateNotificationCount();
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
}

function markAllAsRead() {
    fetch('../ajax/mark_all_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recharger le widget
            location.reload();
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
}

function deleteNotification(notificationId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette notification ?')) {
        fetch('../ajax/delete_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                notification_id: notificationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Supprimer l'élément de l'interface
                const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.remove();
                }
                
                // Mettre à jour le compteur
                updateNotificationCount();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    }
}

function updateNotificationCount() {
    fetch('../ajax/get_notification_count.php')
    .then(response => response.json())
    .then(data => {
        const badge = document.querySelector('.notification-badge');
        if (data.count > 0) {
            if (badge) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
            } else {
                // Créer le badge s'il n'existe pas
                const button = document.getElementById('notificationToggle');
                const newBadge = document.createElement('span');
                newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge';
                newBadge.textContent = data.count > 99 ? '99+' : data.count;
                button.appendChild(newBadge);
            }
        } else {
            if (badge) {
                badge.remove();
            }
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
}

// Actualiser le compteur toutes les 30 secondes
setInterval(updateNotificationCount, 30000);

// Actualiser le widget toutes les 2 minutes
setInterval(() => {
    // Recharger le contenu du dropdown si ouvert
    const dropdown = document.querySelector('.notification-dropdown');
    if (dropdown && dropdown.classList.contains('show')) {
        // Optionnel: recharger le contenu via AJAX
    }
}, 120000);
</script>
