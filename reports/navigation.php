<?php
/**
 * Navigation du Module de Rapports
 * Intégration dans le menu principal de l'application
 */

// Vérifier si le fichier est appelé directement
if (!defined('APP_NAME')) {
    require_once '../config/database.php';
}

// Fonction pour générer le menu de navigation des rapports
function generateReportsNavigation($current_page = '') {
    $nav_items = [
        'index' => [
            'url' => 'index.php',
            'title' => 'Tableau de Bord',
            'icon' => 'bi-graph-up',
            'description' => 'Vue synthétique des informations'
        ],
        'export' => [
            'url' => 'export.php',
            'title' => 'Export des Données',
            'icon' => 'bi-download',
            'description' => 'Générer des rapports'
        ]
    ];
    
    $html = '<div class="reports-navigation">';
    $html .= '<h6 class="text-muted mb-3"><i class="bi bi-bar-chart me-2"></i>Rapports</h6>';
    
    foreach ($nav_items as $key => $item) {
        $is_active = ($current_page === $key) ? 'active' : '';
        $html .= '<a href="' . $item['url'] . '" class="nav-link reports-nav-item ' . $is_active . '">';
        $html .= '<i class="bi ' . $item['icon'] . ' me-2"></i>';
        $html .= '<div>';
        $html .= '<div class="nav-title">' . $item['title'] . '</div>';
        $html .= '<small class="text-muted">' . $item['description'] . '</small>';
        $html .= '</div>';
        $html .= '</a>';
    }
    
    $html .= '</div>';
    return $html;
}

// Fonction pour générer le breadcrumb
function generateReportsBreadcrumb($current_page = '') {
    $breadcrumb_items = [
        'index' => 'Tableau de Bord',
        'export' => 'Export des Données'
    ];
    
    $html = '<nav aria-label="breadcrumb">';
    $html .= '<ol class="breadcrumb">';
    $html .= '<li class="breadcrumb-item"><a href="../index.php"><i class="bi bi-house me-1"></i>Accueil</a></li>';
    $html .= '<li class="breadcrumb-item"><a href="index.php">Rapports</a></li>';
    
    if (isset($breadcrumb_items[$current_page])) {
        $html .= '<li class="breadcrumb-item active" aria-current="page">' . $breadcrumb_items[$current_page] . '</li>';
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

// Fonction pour générer le menu latéral des actions rapides
function generateQuickActionsMenu() {
    $actions = [
        [
            'url' => '../classes/',
            'title' => 'Gérer les Classes',
            'icon' => 'bi-people',
            'color' => 'text-info'
        ],
        [
            'url' => '../students/',
            'title' => 'Inscriptions',
            'icon' => 'bi-person-plus',
            'color' => 'text-success'
        ],
        [
            'url' => '../finance/',
            'title' => 'Finances',
            'icon' => 'bi-cash-coin',
            'color' => 'text-success'
        ],
        [
            'url' => '../presence/',
            'title' => 'Présence',
            'icon' => 'bi-calendar-check',
            'color' => 'text-info'
        ],
        [
            'url' => '../grades/',
            'title' => 'Notes',
            'icon' => 'bi-star',
            'color' => 'text-warning'
        ]
    ];
    
    $html = '<div class="quick-actions-sidebar">';
    $html .= '<h6 class="text-muted mb-3"><i class="bi bi-lightning me-2"></i>Actions Rapides</h6>';
    
    foreach ($actions as $action) {
        $html .= '<a href="' . $action['url'] . '" class="quick-action-link">';
        $html .= '<i class="bi ' . $action['icon'] . ' ' . $action['color'] . ' me-2"></i>';
        $html .= '<span>' . $action['title'] . '</span>';
        $html .= '</a>';
    }
    
    $html .= '</div>';
    return $html;
}

// Fonction pour générer le menu de navigation principal
function generateMainNavigation() {
    $main_nav = [
        'dashboard' => [
            'url' => 'index.php',
            'title' => 'Tableau de Bord',
            'icon' => 'bi-graph-up'
        ],
        'export' => [
            'url' => 'export.php',
            'title' => 'Export',
            'icon' => 'bi-download'
        ],
        'test' => [
            'url' => 'test_reports.php',
            'title' => 'Tests',
            'icon' => 'bi-tools'
        ]
    ];
    
    $html = '<ul class="nav nav-pills flex-column">';
    
    foreach ($main_nav as $key => $item) {
        $is_active = (basename($_SERVER['PHP_SELF']) === $item['url']) ? 'active' : '';
        $html .= '<li class="nav-item">';
        $html .= '<a class="nav-link ' . $is_active . '" href="' . $item['url'] . '">';
        $html .= '<i class="bi ' . $item['icon'] . ' me-2"></i>';
        $html .= $item['title'];
        $html .= '</a>';
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    return $html;
}

// Styles CSS pour la navigation
function getReportsNavigationStyles() {
    return '
    <style>
        .reports-navigation {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .reports-nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            text-decoration: none;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        
        .reports-nav-item:hover {
            background-color: #f8f9fa;
            color: #495057;
            text-decoration: none;
        }
        
        .reports-nav-item.active {
            background-color: #e7f3ff;
            color: #007bff;
            border-left: 3px solid #007bff;
        }
        
        .quick-actions-sidebar {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-top: 1rem;
        }
        
        .quick-action-link {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            border-radius: 6px;
            margin-bottom: 0.25rem;
            text-decoration: none;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        
        .quick-action-link:hover {
            background-color: #f8f9fa;
            color: #495057;
            text-decoration: none;
            transform: translateX(5px);
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }
        
        .breadcrumb-item a {
            color: #6c757d;
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            color: #007bff;
        }
        
        .breadcrumb-item.active {
            color: #495057;
        }
        
        .nav-pills .nav-link {
            color: #6c757d;
            border-radius: 8px;
            margin-bottom: 0.25rem;
        }
        
        .nav-pills .nav-link:hover {
            background-color: #f8f9fa;
        }
        
        .nav-pills .nav-link.active {
            background-color: #007bff;
            color: white;
        }
    </style>';
}

// Fonction pour inclure la navigation dans une page
function includeReportsNavigation($current_page = '') {
    echo getReportsNavigationStyles();
    
    if (basename($_SERVER['PHP_SELF']) === 'index.php') {
        echo '<div class="row">';
        echo '<div class="col-lg-9">';
        // Le contenu principal sera ici
    } else {
        echo '<div class="row">';
        echo '<div class="col-lg-3">';
        echo generateMainNavigation();
        echo generateQuickActionsMenu();
        echo '</div>';
        echo '<div class="col-lg-9">';
    }
}

// Fonction pour fermer la navigation
function closeReportsNavigation() {
    echo '</div>'; // Fermer col-lg-9
    echo '</div>'; // Fermer row
}

// Fonction pour générer le header de la page
function generateReportsHeader($title, $subtitle = '', $show_breadcrumb = true) {
    $html = '<div class="reports-header mb-4">';
    
    if ($show_breadcrumb) {
        $html .= generateReportsBreadcrumb();
    }
    
    $html .= '<div class="d-flex justify-content-between align-items-center">';
    $html .= '<div>';
    $html .= '<h1 class="h3 mb-1">' . $title . '</h1>';
    if ($subtitle) {
        $html .= '<p class="text-muted mb-0">' . $subtitle . '</p>';
    }
    $html .= '</div>';
    
    $html .= '<div class="header-actions">';
    $html .= '<a href="../index.php" class="btn btn-outline-secondary me-2">';
    $html .= '<i class="bi bi-house me-1"></i>Accueil';
    $html .= '</a>';
    
    if (basename($_SERVER['PHP_SELF']) !== 'index.php') {
        $html .= '<a href="index.php" class="btn btn-outline-primary">';
        $html .= '<i class="bi bi-arrow-left me-1"></i>Retour';
        $html .= '</a>';
    }
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// Fonction pour générer le footer de la page
function generateReportsFooter() {
    return '
    <div class="reports-footer mt-5 pt-4 border-top">
        <div class="row">
            <div class="col-md-6">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Module de Rapports - ' . APP_NAME . '
                </small>
            </div>
            <div class="col-md-6 text-md-end">
                <small class="text-muted">
                    Dernière mise à jour: ' . date('d/m/Y H:i') . '
                </small>
            </div>
        </div>
    </div>';
}
?>
