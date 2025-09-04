<?php
/**
 * Téléchargement du template CSV pour l'importation des élèves
 * Alternative au template Excel qui fonctionne sans PhpSpreadsheet
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

try {
    // Générer le nom du fichier
    $filename = 'template_import_eleves_' . date('Y-m-d') . '.csv';
    
    // En-têtes HTTP pour le téléchargement
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Créer le fichier CSV
    $output = fopen('php://output', 'w');
    
    // Ajouter BOM UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-têtes CSV
    $headers = [
        'Matricule',
        'Nom',
        'Post-nom',
        'Prénom',
        'Sexe',
        'Date de naissance',
        'Lieu de naissance',
        'Nationalité',
        'Téléphone',
        'Email',
        'Adresse',
        'Quartier'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Exemples de données
    $examples = [
        [
            'ELEV001',
            'MULUMBA',
            'KABONGO',
            'Jean',
            'M',
            '2010-05-15',
            'Kinshasa',
            'Congolaise',
            '+243 123 456 789',
            'jean.mulumba@email.com',
            'Avenue de la Paix, 123',
            'Matonge'
        ],
        [
            'ELEV002',
            'KASONGO',
            'MUKENDI',
            'Marie',
            'F',
            '2011-03-22',
            'Lubumbashi',
            'Congolaise',
            '+243 987 654 321',
            'marie.kasongo@email.com',
            'Boulevard du 30 Juin, 456',
            'Gombe'
        ],
        [
            'ELEV003',
            'KABONGO',
            'TSHILOMBO',
            'Pierre',
            'M',
            '2009-12-10',
            'Kinshasa',
            'Congolaise',
            '+243 555 123 456',
            'pierre.kabongo@email.com',
            'Rue de la Victoire, 789',
            'Lemba'
        ]
    ];
    
    foreach ($examples as $example) {
        fputcsv($output, $example, ';');
    }
    
    // Ajouter des instructions
    fputcsv($output, [], ';'); // Ligne vide
    fputcsv($output, ['INSTRUCTIONS:'], ';');
    fputcsv($output, ['1. Remplissez les colonnes avec les données des élèves'], ';');
    fputcsv($output, ['2. Le matricule doit être unique pour chaque élève'], ';');
    fputcsv($output, ['3. Le sexe doit être M (Masculin) ou F (Féminin)'], ';');
    fputcsv($output, ['4. La date de naissance doit être au format YYYY-MM-DD'], ';');
    fputcsv($output, ['5. Supprimez les lignes d\'exemple avant l\'importation'], ';');
    fputcsv($output, ['6. Sauvegardez le fichier en format CSV'], ';');
    
    fclose($output);
    
    // Log de l'action
    logUserAction('DOWNLOAD_TEMPLATE_CSV', "Téléchargement du template CSV pour l'importation");
    
} catch (Exception $e) {
    die('Erreur lors de la génération du template: ' . $e->getMessage());
}
?>

