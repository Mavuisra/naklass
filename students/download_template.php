<?php
/**
 * Téléchargement du modèle Excel pour l'importation des élèves
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier que PhpSpreadsheet est installé
if (!file_exists('../vendor/autoload.php')) {
    setFlashMessage('error', 'PhpSpreadsheet n\'est pas installé. Contactez l\'administrateur.');
    redirect('add.php');
}

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    // Créer un nouveau classeur Excel
    $spreadsheet = new Spreadsheet();
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Définir le titre de la feuille
    $worksheet->setTitle('Modèle Import Élèves');
    
    // En-têtes des colonnes
    $headers = [
        'Matricule', 'Nom', 'Post-nom', 'Prénom', 'Sexe', 'Date de naissance', 
        'Lieu de naissance', 'Nationalité', 'Téléphone', 'Email', 'Adresse', 'Quartier'
    ];
    
    // Ajouter les en-têtes
    foreach ($headers as $col => $header) {
        $column = chr(65 + $col); // A, B, C, etc.
        $worksheet->setCellValue($column . '1', $header);
        
        // Style des en-têtes
        $worksheet->getStyle($column . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);
    }
    
    // Ajouter des exemples de données
    $examples = [
        ['EL240001', 'Doe', 'Smith', 'John', 'M', '2008-05-15', 'Kinshasa', 'Congolaise', '+243 123 456 789', 'john.doe@email.com', '123 Avenue de la Paix', 'Gombe'],
        ['EL240002', 'Martin', 'Brown', 'Marie', 'F', '2009-03-22', 'Lubumbashi', 'Congolaise', '+243 987 654 321', 'marie.martin@email.com', '456 Boulevard du Progrès', 'Annexe'],
        ['EL240003', 'Bernard', 'Wilson', 'Pierre', 'M', '2008-11-08', 'Goma', 'Congolaise', '+243 555 123 456', 'pierre.bernard@email.com', '789 Rue de l\'Avenir', 'Virunga']
    ];
    
    foreach ($examples as $row => $example) {
        $row_num = $row + 2; // Commencer à la ligne 2
        foreach ($example as $col => $value) {
            $column = chr(65 + $col);
            $worksheet->setCellValue($column . $row_num, $value);
        }
        
        // Style des exemples
        $worksheet->getStyle('A' . $row_num . ':L' . $row_num)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0F8FF']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);
    }
    
    // Ajouter des instructions
    $instructions = [
        'Instructions importantes :',
        '- Le matricule doit être unique pour chaque élève',
        '- Le sexe doit être M (Masculin) ou F (Féminin)',
        '- La date de naissance doit être au format AAAA-MM-JJ',
        '- L\'email est optionnel mais doit être unique s\'il est fourni',
        '- Supprimez les lignes d\'exemple avant l\'importation',
        '- Maximum 100 élèves par importation'
    ];
    
    $start_row = count($examples) + 4;
    foreach ($instructions as $row => $instruction) {
        $worksheet->setCellValue('A' . ($start_row + $row), $instruction);
        $worksheet->getStyle('A' . ($start_row + $row))->getFont()->setBold(true);
    }
    
    // Ajuster la largeur des colonnes
    foreach (range('A', 'L') as $column) {
        $worksheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Créer le fichier Excel
    $writer = new Xlsx($spreadsheet);
    
    // Définir les en-têtes HTTP pour le téléchargement
    $filename = 'modele_import_eleves_' . date('Y-m-d') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Écrire le fichier dans la sortie
    $writer->save('php://output');
    
} catch (Exception $e) {
    // En cas d'erreur, rediriger avec un message d'erreur
    setFlashMessage('error', 'Erreur lors de la génération du modèle Excel : ' . $e->getMessage());
    redirect('add.php');
}
?>

