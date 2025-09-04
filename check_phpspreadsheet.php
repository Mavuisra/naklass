<?php
/**
 * Vérification rapide de PhpSpreadsheet
 */

// Inclure l'autoloader si disponible
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

// Vérification simple
$phpspreadsheet_ok = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');

if ($phpspreadsheet_ok) {
    echo "✅ PhpSpreadsheet est installé et fonctionnel !";
    echo "<br><a href='students/index.php'>→ Aller à l'exportation Excel</a>";
} else {
    echo "❌ PhpSpreadsheet n'est pas installé ou non accessible.";
    echo "<br><a href='students/export_csv.php'>→ Utiliser l'exportation CSV</a>";
    echo "<br><a href='test_phpspreadsheet.php'>→ Diagnostic détaillé</a>";
}
?>

