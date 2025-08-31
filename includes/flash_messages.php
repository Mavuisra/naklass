<?php
/**
 * Affichage des messages flash
 * Gère l'affichage des messages de succès, d'erreur, d'avertissement et d'information
 */

// Vérifier si des messages flash existent
if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
    foreach ($_SESSION['flash_messages'] as $type => $messages) {
        if (is_array($messages)) {
            foreach ($messages as $message) {
                $this->displayFlashMessage($type, $message);
            }
        } else {
            $this->displayFlashMessage($type, $messages);
        }
    }
    
    // Nettoyer les messages flash après affichage
    unset($_SESSION['flash_messages']);
}

/**
 * Affiche un message flash avec le style approprié
 * @param string $type Type de message (success, error, warning, info)
 * @param string $message Contenu du message
 */
function displayFlashMessage($type, $message) {
    $alert_class = '';
    $icon_class = '';
    
    switch ($type) {
        case 'success':
            $alert_class = 'alert-success';
            $icon_class = 'bi-check-circle';
            break;
        case 'error':
            $alert_class = 'alert-danger';
            $icon_class = 'bi-exclamation-triangle';
            break;
        case 'warning':
            $alert_class = 'alert-warning';
            $icon_class = 'bi-exclamation-triangle';
            break;
        case 'info':
            $alert_class = 'alert-info';
            $icon_class = 'bi-info-circle';
            break;
        default:
            $alert_class = 'alert-secondary';
            $icon_class = 'bi-info-circle';
    }
    
    echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
    echo '<i class="bi ' . $icon_class . ' me-2"></i>';
    echo htmlspecialchars($message);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}

// Alternative: Si la fonction n'est pas accessible, utiliser une approche directe
if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
    foreach ($_SESSION['flash_messages'] as $type => $messages) {
        if (is_array($messages)) {
            foreach ($messages as $message) {
                $alert_class = '';
                $icon_class = '';
                
                switch ($type) {
                    case 'success':
                        $alert_class = 'alert-success';
                        $icon_class = 'bi-check-circle';
                        break;
                    case 'error':
                        $alert_class = 'alert-danger';
                        $icon_class = 'bi-exclamation-triangle';
                        break;
                    case 'warning':
                        $alert_class = 'alert-warning';
                        $icon_class = 'bi-exclamation-triangle';
                        break;
                    case 'info':
                        $alert_class = 'alert-info';
                        $icon_class = 'bi-info-circle';
                        break;
                    default:
                        $alert_class = 'alert-secondary';
                        $icon_class = 'bi-info-circle';
                }
                
                echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
                echo '<i class="bi ' . $icon_class . ' me-2"></i>';
                echo htmlspecialchars($message);
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
            }
        } else {
            $alert_class = '';
            $icon_class = '';
            
            switch ($type) {
                case 'success':
                    $alert_class = 'alert-success';
                    $icon_class = 'bi-check-circle';
                    break;
                case 'error':
                    $alert_class = 'alert-danger';
                    $icon_class = 'bi-exclamation-triangle';
                    break;
                case 'warning':
                    $alert_class = 'alert-warning';
                    $icon_class = 'bi-exclamation-triangle';
                    break;
                case 'info':
                    $alert_class = 'alert-info';
                    $icon_class = 'bi-info-circle';
                    break;
                default:
                    $alert_class = 'alert-secondary';
                    $icon_class = 'bi-info-circle';
            }
            
            echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
            echo '<i class="bi ' . $icon_class . ' me-2"></i>';
            echo htmlspecialchars($messages);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
    }
    
    // Nettoyer les messages flash après affichage
    unset($_SESSION['flash_messages']);
}
?>
