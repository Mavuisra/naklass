<?php
/**
 * Exportation des élèves en Excel
 * Permet d'exporter la liste des élèves avec filtres optionnels
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Vérifier que PhpSpreadsheet est disponible
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    // Rediriger vers l'exportation CSV si PhpSpreadsheet n'est pas disponible
    $csv_url = 'export_csv.php?' . http_build_query($_GET);
    header('Location: ' . $csv_url);
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    // Récupérer les paramètres de filtrage
    $classe_id = $_GET['classe_id'] ?? null;
    $statut = $_GET['statut'] ?? 'validée';
    $annee_scolaire = $_GET['annee_scolaire'] ?? null;
    
    // Construire la requête avec filtres
    $where_conditions = ["e.ecole_id = :ecole_id"];
    $params = ['ecole_id' => $_SESSION['ecole_id']];
    
    if ($classe_id) {
        $where_conditions[] = "i.classe_id = :classe_id";
        $params['classe_id'] = $classe_id;
    }
    
    if ($statut) {
        $where_conditions[] = "i.statut_inscription = :statut";
        $params['statut'] = $statut;
    }
    
    if ($annee_scolaire) {
        $where_conditions[] = "i.annee_scolaire = :annee_scolaire";
        $params['annee_scolaire'] = $annee_scolaire;
    }
    
    // Requête principale pour récupérer les élèves
    $query = "SELECT 
        e.id,
        e.matricule,
        e.nom,
        e.postnom,
        e.prenom,
        e.sexe,
        e.date_naissance,
        e.lieu_naissance,
        e.nationalite,
        e.telephone,
        e.email,
        e.adresse_complete,
        e.quartier,
        e.date_premiere_inscription,
        c.nom_classe,
        i.statut_inscription,
        i.date_inscription,
        i.annee_scolaire,
        ec.nom_ecole
    FROM eleves e
    LEFT JOIN inscriptions i ON e.id = i.eleve_id
    LEFT JOIN classes c ON i.classe_id = c.id
    LEFT JOIN ecoles ec ON e.ecole_id = ec.id
    WHERE " . implode(' AND ', $where_conditions) . "
    ORDER BY c.nom_classe, e.nom, e.prenom";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $eleves = $stmt->fetchAll();
    
    if (empty($eleves)) {
        die('Aucun élève trouvé avec les critères sélectionnés.');
    }
    
    // Créer un nouveau spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Définir le titre de la feuille
    $sheet->setTitle('Liste des Élèves');
    
    // En-têtes avec style (même format que l'importation)
    $headers = [
        'A1' => 'Matricule',
        'B1' => 'Nom',
        'C1' => 'Post-nom',
        'D1' => 'Prénom',
        'E1' => 'Sexe',
        'F1' => 'Date de naissance',
        'G1' => 'Lieu de naissance',
        'H1' => 'Nationalité',
        'I1' => 'Téléphone',
        'J1' => 'Email',
        'K1' => 'Adresse',
        'L1' => 'Quartier',
        'M1' => 'Classe',
        'N1' => 'Statut',
        'O1' => 'Date d\'inscription',
        'P1' => 'Année scolaire',
        'Q1' => 'École'
    ];
    
    // Appliquer les en-têtes
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }
    
    // Style des en-têtes
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2E86AB']
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
    ];
    
    $sheet->getStyle('A1:Q1')->applyFromArray($headerStyle);
    
    // Remplir les données
    $row = 2;
    foreach ($eleves as $eleve) {
        $sheet->setCellValue('A' . $row, $eleve['matricule']);
        $sheet->setCellValue('B' . $row, $eleve['nom']);
        $sheet->setCellValue('C' . $row, $eleve['postnom']);
        $sheet->setCellValue('D' . $row, $eleve['prenom']);
        $sheet->setCellValue('E' . $row, $eleve['sexe']);
        $sheet->setCellValue('F' . $row, $eleve['date_naissance']);
        $sheet->setCellValue('G' . $row, $eleve['lieu_naissance']);
        $sheet->setCellValue('H' . $row, $eleve['nationalite']);
        $sheet->setCellValue('I' . $row, $eleve['telephone']);
        $sheet->setCellValue('J' . $row, $eleve['email']);
        $sheet->setCellValue('K' . $row, $eleve['adresse_complete']);
        $sheet->setCellValue('L' . $row, $eleve['quartier']);
        $sheet->setCellValue('M' . $row, $eleve['nom_classe']);
        $sheet->setCellValue('N' . $row, $eleve['statut_inscription']);
        $sheet->setCellValue('O' . $row, $eleve['date_inscription']);
        $sheet->setCellValue('P' . $row, $eleve['annee_scolaire']);
        $sheet->setCellValue('Q' . $row, $eleve['nom_ecole']);
        
        $row++;
    }
    
    // Style des données
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    
    $sheet->getStyle('A2:Q' . ($row - 1))->applyFromArray($dataStyle);
    
    // Ajuster la largeur des colonnes
    foreach (range('A', 'Q') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Ajouter des informations sur l'export
    $infoRow = $row + 2;
    $sheet->setCellValue('A' . $infoRow, 'Informations sur l\'export:');
    $sheet->setCellValue('A' . ($infoRow + 1), 'Date d\'export: ' . date('d/m/Y H:i:s'));
    $sheet->setCellValue('A' . ($infoRow + 2), 'Nombre d\'élèves: ' . count($eleves));
    $sheet->setCellValue('A' . ($infoRow + 3), 'École: ' . ($eleves[0]['nom_ecole'] ?? 'Non définie'));
    
    if ($classe_id) {
        $sheet->setCellValue('A' . ($infoRow + 4), 'Classe: ' . ($eleves[0]['nom_classe'] ?? 'Non définie'));
    }
    
    if ($statut) {
        $sheet->setCellValue('A' . ($infoRow + 5), 'Statut: ' . $statut);
    }
    
    // Générer le nom du fichier
    $filename = 'eleves_export_' . date('Y-m-d_H-i-s');
    if ($classe_id) {
        $filename .= '_classe_' . $eleves[0]['nom_classe'];
    }
    if ($statut) {
        $filename .= '_' . $statut;
    }
    $filename .= '.xlsx';
    
    // Envoyer le fichier au navigateur
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    // Log de l'action
    logUserAction('EXPORT_STUDENTS', "Exportation de " . count($eleves) . " élève(s) en Excel");
    
} catch (Exception $e) {
    die('Erreur lors de l\'exportation: ' . $e->getMessage());
}
?>
