<?php
/**
 * Configuration du Module de Rapports
 * Paramètres et options pour la génération des rapports
 */

// Configuration des permissions
define('REPORTS_ALLOWED_ROLES', ['admin', 'direction', 'secretaire']);

// Configuration des formats d'export
define('EXPORT_FORMATS', [
    'pdf' => [
        'name' => 'PDF',
        'extension' => '.pdf',
        'mime_type' => 'application/pdf',
        'description' => 'Document portable et lisible'
    ],
    'excel' => [
        'name' => 'Excel',
        'extension' => '.xlsx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'description' => 'Tableau de données modifiable'
    ],
    'csv' => [
        'name' => 'CSV',
        'extension' => '.csv',
        'mime_type' => 'text/csv',
        'description' => 'Données séparées par virgules'
    ]
]);

// Configuration des sections de rapports
define('REPORT_SECTIONS', [
    'classes' => [
        'name' => 'Classes',
        'icon' => 'bi-people',
        'color' => 'text-info',
        'description' => 'Informations sur les classes et niveaux',
        'tables' => ['classes', 'niveaux', 'sections']
    ],
    'inscriptions' => [
        'name' => 'Inscriptions',
        'icon' => 'bi-person-plus',
        'color' => 'text-success',
        'description' => 'Statistiques des inscriptions',
        'tables' => ['inscriptions', 'etudiants']
    ],
    'cours' => [
        'name' => 'Cours',
        'icon' => 'bi-book',
        'color' => 'text-warning',
        'description' => 'Programme et matières enseignées',
        'tables' => ['cours', 'matieres', 'enseignants']
    ],
    'notes' => [
        'name' => 'Notes',
        'icon' => 'bi-star',
        'color' => 'text-warning',
        'description' => 'Résultats et moyennes',
        'tables' => ['notes', 'evaluations']
    ],
    'finances' => [
        'name' => 'Finances',
        'icon' => 'bi-cash-coin',
        'color' => 'text-success',
        'description' => 'Recettes, dépenses et solde',
        'tables' => ['transactions_financieres', 'types_frais']
    ],
    'presence' => [
        'name' => 'Présence',
        'icon' => 'bi-calendar-check',
        'color' => 'text-info',
        'description' => 'Statistiques de présence',
        'tables' => ['presence', 'sessions_presence']
    ],
    'utilisateurs' => [
        'name' => 'Utilisateurs',
        'icon' => 'bi-person-badge',
        'color' => 'text-primary',
        'description' => 'Gestion des comptes utilisateurs',
        'tables' => ['utilisateurs', 'roles']
    ]
]);

// Configuration des graphiques
define('CHART_CONFIG', [
    'inscriptions' => [
        'type' => 'line',
        'colors' => ['rgb(75, 192, 192)'],
        'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin']
    ],
    'finances' => [
        'type' => 'doughnut',
        'colors' => ['rgb(75, 192, 192)', 'rgb(255, 99, 132)'],
        'labels' => ['Entrées', 'Sorties']
    ],
    'presence' => [
        'type' => 'bar',
        'colors' => ['rgb(40, 167, 69)', 'rgb(220, 53, 69)', 'rgb(255, 193, 7)'],
        'labels' => ['Présences', 'Absences', 'Retards']
    ]
]);

// Configuration des métriques
define('METRICS_CONFIG', [
    'classes' => [
        'total' => 'COUNT(*)',
        'actives' => 'COUNT(CASE WHEN statut = "actif" THEN 1 END)',
        'inactives' => 'COUNT(CASE WHEN statut = "inactif" THEN 1 END)',
        'taux_activite' => 'ROUND((COUNT(CASE WHEN statut = "actif" THEN 1 END) / COUNT(*)) * 100, 1)'
    ],
    'inscriptions' => [
        'total' => 'COUNT(*)',
        'actives' => 'COUNT(CASE WHEN statut = "active" THEN 1 END)',
        'terminees' => 'COUNT(CASE WHEN statut = "terminee" THEN 1 END)',
        'annulees' => 'COUNT(CASE WHEN statut = "annulee" THEN 1 END)'
    ],
    'cours' => [
        'total' => 'COUNT(*)',
        'actifs' => 'COUNT(CASE WHEN statut = "actif" THEN 1 END)',
        'termines' => 'COUNT(CASE WHEN statut = "termine" THEN 1 END)'
    ],
    'notes' => [
        'total' => 'COUNT(*)',
        'moyenne' => 'AVG(note)',
        'min' => 'MIN(note)',
        'max' => 'MAX(note)'
    ],
    'finances' => [
        'total_transactions' => 'COUNT(*)',
        'entrees' => 'SUM(CASE WHEN type = "entree" THEN montant ELSE 0 END)',
        'sorties' => 'SUM(CASE WHEN type = "sortie" THEN montant ELSE 0 END)',
        'solde' => 'SUM(CASE WHEN type = "entree" THEN montant ELSE -montant END)'
    ],
    'presence' => [
        'total_seances' => 'COUNT(*)',
        'presences' => 'COUNT(CASE WHEN statut = "present" THEN 1 END)',
        'absences' => 'COUNT(CASE WHEN statut = "absent" THEN 1 END)',
        'retards' => 'COUNT(CASE WHEN statut = "retard" THEN 1 END)',
        'taux_presence' => 'ROUND((COUNT(CASE WHEN statut = "present" THEN 1 END) / COUNT(*)) * 100, 1)'
    ]
]);

// Configuration des couleurs et styles
define('UI_CONFIG', [
    'colors' => [
        'primary' => '#667eea',
        'secondary' => '#764ba2',
        'success' => '#28a745',
        'danger' => '#dc3545',
        'warning' => '#ffc107',
        'info' => '#17a2b8',
        'light' => '#f8f9fa',
        'dark' => '#343a40'
    ],
    'gradients' => [
        'header' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'quick_actions' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'export_header' => 'linear-gradient(135deg, #28a745 0%, #20c997 100%)'
    ],
    'shadows' => [
        'card' => '0 4px 6px rgba(0, 0, 0, 0.1)',
        'card_hover' => '0 8px 25px rgba(0, 0, 0, 0.15)'
    ]
]);

// Configuration des limites et pagination
define('LIMITS', [
    'max_records_per_page' => 100,
    'max_chart_data_points' => 12,
    'max_export_records' => 10000
]);

// Configuration des formats de date et nombres
define('FORMAT_CONFIG', [
    'date_format' => 'd/m/Y',
    'datetime_format' => 'd/m/Y H:i:s',
    'number_decimal_places' => 2,
    'currency' => 'CDF',
    'thousands_separator' => ' ',
    'decimal_separator' => ','
]);

// Configuration des notifications
define('NOTIFICATION_CONFIG', [
    'enable_email_notifications' => false,
    'enable_browser_notifications' => true,
    'auto_refresh_interval' => 300000, // 5 minutes en millisecondes
    'show_loading_indicators' => true
]);

// Configuration de la sécurité
define('SECURITY_CONFIG', [
    'max_export_size' => 50 * 1024 * 1024, // 50 MB
    'allowed_file_types' => ['pdf', 'xlsx', 'csv'],
    'session_timeout' => 3600, // 1 heure
    'max_export_attempts' => 10,
    'export_rate_limit' => 60 // secondes entre exports
]);

// Configuration des logs
define('LOGGING_CONFIG', [
    'enable_logging' => true,
    'log_level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
    'log_file' => '../logs/reports.log',
    'max_log_size' => 10 * 1024 * 1024, // 10 MB
    'log_retention_days' => 30
]);

// Fonction utilitaire pour vérifier les permissions
function hasReportPermission($role) {
    return in_array($role, REPORTS_ALLOWED_ROLES);
}

// Fonction utilitaire pour formater les nombres
function formatReportNumber($number, $decimals = 0) {
    return number_format($number, $decimals, FORMAT_CONFIG['decimal_separator'], FORMAT_CONFIG['thousands_separator']);
}

// Fonction utilitaire pour formater la monnaie
function formatReportCurrency($amount) {
    return formatReportNumber($amount, FORMAT_CONFIG['number_decimal_places']) . ' ' . FORMAT_CONFIG['currency'];
}

// Fonction utilitaire pour calculer les pourcentages
function calculateReportPercentage($part, $total) {
    if ($total == 0) return 0;
    return round(($part / $total) * 100, 1);
}

// Fonction utilitaire pour valider les paramètres d'export
function validateExportParams($format, $sections) {
    if (!array_key_exists($format, EXPORT_FORMATS)) {
        return false;
    }
    
    foreach ($sections as $section) {
        if (!array_key_exists($section, REPORT_SECTIONS)) {
            return false;
        }
    }
    
    return true;
}
?>
