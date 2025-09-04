<?php
/**
 * Installation de PhpSpreadsheet pour l'importation Excel
 */
require_once 'includes/functions.php';

$page_title = "Installation de PhpSpreadsheet";
$errors = [];
$success = [];

// Vérifier si Composer est disponible
if (!file_exists('composer.json')) {
    $errors[] = "Composer n'est pas configuré dans ce projet.";
} else {
    $success[] = "✅ Composer est configuré";
}

// Vérifier si PhpSpreadsheet est déjà installé
if (file_exists('vendor/autoload.php')) {
    try {
        require_once 'vendor/autoload.php';
        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $success[] = "✅ PhpSpreadsheet est déjà installé et fonctionnel";
            $phpspreadsheet_installed = true;
        } else {
            $errors[] = "❌ PhpSpreadsheet n'est pas accessible malgré l'autoloader";
            $phpspreadsheet_installed = false;
        }
    } catch (Exception $e) {
        $errors[] = "❌ Erreur lors du chargement de PhpSpreadsheet : " . $e->getMessage();
        $phpspreadsheet_installed = false;
    }
} else {
    $success[] = "⚠️ PhpSpreadsheet n'est pas encore installé";
    $phpspreadsheet_installed = false;
}

// Traitement de l'installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'install_phpspreadsheet') {
        try {
            // Vérifier que Composer est disponible
            if (!file_exists('composer.json')) {
                throw new Exception("Composer n'est pas configuré");
            }
            
            // Installer PhpSpreadsheet via Composer
            $output = [];
            $return_var = 0;
            
            $command = 'composer require phpoffice/phpspreadsheet';
            exec($command . ' 2>&1', $output, $return_var);
            
            if ($return_var !== 0) {
                throw new Exception("Erreur lors de l'installation : " . implode("\n", $output));
            }
            
            $success[] = "✅ PhpSpreadsheet installé avec succès via Composer";
            $success[] = "📋 Sortie de la commande : " . implode("\n", $output);
            
            // Vérifier l'installation
            if (file_exists('vendor/autoload.php')) {
                require_once 'vendor/autoload.php';
                if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                    $success[] = "✅ PhpSpreadsheet est maintenant fonctionnel";
                    $phpspreadsheet_installed = true;
                }
            }
            
        } catch (Exception $e) {
            $errors[] = "❌ Erreur lors de l'installation : " . $e->getMessage();
        }
    }
}

// Vérifier les dépendances système
$system_requirements = [
    'PHP Version' => [
        'required' => '7.4.0',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
    ],
    'Extension ZIP' => [
        'required' => 'Obligatoire',
        'current' => extension_loaded('zip') ? 'Installée' : 'Manquante',
        'status' => extension_loaded('zip')
    ],
    'Extension XML' => [
        'required' => 'Obligatoire',
        'current' => extension_loaded('xml') ? 'Installée' : 'Manquante',
        'status' => extension_loaded('xml')
    ],
    'Extension GD' => [
        'required' => 'Recommandée',
        'current' => extension_loaded('gd') ? 'Installée' : 'Manquante',
        'status' => extension_loaded('gd')
    ]
];

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
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-file-earmark-excel me-2"></i>
                            <?php echo $page_title; ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>À propos de PhpSpreadsheet</h6>
                            <p class="mb-0">
                                PhpSpreadsheet est une bibliothèque PHP qui permet de lire et écrire des fichiers Excel (.xlsx, .xls).
                                Elle est nécessaire pour la fonctionnalité d'importation Excel des élèves.
                            </p>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs</h6>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <h6><i class="bi bi-check-circle me-2"></i>Succès</h6>
                                <ul class="mb-0">
                                    <?php foreach ($success as $msg): ?>
                                        <li><?php echo $msg; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Statut de l'installation -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Statut de l'installation</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($phpspreadsheet_installed): ?>
                                    <div class="alert alert-success mb-0">
                                        <i class="bi bi-check-circle me-2"></i>
                                        <strong>PhpSpreadsheet est installé et fonctionnel !</strong>
                                        <br>
                                        Vous pouvez maintenant utiliser la fonctionnalité d'importation Excel.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong>PhpSpreadsheet n'est pas installé.</strong>
                                        <br>
                                        Utilisez le bouton ci-dessous pour l'installer.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Exigences système -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Exigences système</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Composant</th>
                                                <th>Requis</th>
                                                <th>Actuel</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($system_requirements as $component => $requirement): ?>
                                                <tr>
                                                    <td><?php echo $component; ?></td>
                                                    <td><?php echo $requirement['required']; ?></td>
                                                    <td><?php echo $requirement['current']; ?></td>
                                                    <td>
                                                        <?php if ($requirement['status']): ?>
                                                            <span class="badge bg-success">✅ OK</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">❌ Manquant</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="text-center">
                            <?php if (!$phpspreadsheet_installed): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="install_phpspreadsheet">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-download me-2"></i>Installer PhpSpreadsheet
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="students/add.php" class="btn btn-secondary btn-lg ms-2">
                                <i class="bi bi-arrow-left me-2"></i>Retour à l'inscription
                            </a>
                        </div>
                        
                        <!-- Instructions manuelles -->
                        <?php if (!$phpspreadsheet_installed): ?>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-tools me-2"></i>Installation manuelle</h6>
                                </div>
                                <div class="card-body">
                                    <p>Si l'installation automatique ne fonctionne pas, vous pouvez installer PhpSpreadsheet manuellement :</p>
                                    <ol>
                                        <li>Ouvrez un terminal dans le répertoire du projet</li>
                                        <li>Exécutez : <code>composer require phpoffice/phpspreadsheet</code></li>
                                        <li>Attendez la fin de l'installation</li>
                                        <li>Rechargez cette page</li>
                                    </ol>
                                    
                                    <div class="alert alert-warning">
                                        <strong>Note :</strong> Assurez-vous que Composer est installé sur votre système.
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

