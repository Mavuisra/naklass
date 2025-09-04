<?php
/**
 * Exportation des élèves en CSV (compatible Excel)
 * Alternative à PhpSpreadsheet qui fonctionne sans extensions supplémentaires
 */
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

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
    
    // Générer le nom du fichier
    $filename = 'eleves_export_' . date('Y-m-d_H-i-s');
    if ($classe_id) {
        $filename .= '_classe_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $eleves[0]['nom_classe'] ?? '');
    }
    if ($statut) {
        $filename .= '_' . $statut;
    }
    $filename .= '.csv';
    
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
        'Quartier',
        'Classe',
        'Statut',
        'Date d\'inscription',
        'Année scolaire',
        'École'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Données des élèves
    foreach ($eleves as $eleve) {
        $row = [
            $eleve['matricule'],
            $eleve['nom'],
            $eleve['postnom'],
            $eleve['prenom'],
            $eleve['sexe'],
            $eleve['date_naissance'],
            $eleve['lieu_naissance'],
            $eleve['nationalite'],
            $eleve['telephone'],
            $eleve['email'],
            $eleve['adresse_complete'],
            $eleve['quartier'],
            $eleve['nom_classe'],
            $eleve['statut_inscription'],
            $eleve['date_inscription'],
            $eleve['annee_scolaire'],
            $eleve['nom_ecole']
        ];
        
        fputcsv($output, $row, ';');
    }
    
    // Ajouter des informations sur l'export
    fputcsv($output, [], ';'); // Ligne vide
    fputcsv($output, ['Informations sur l\'export:'], ';');
    fputcsv($output, ['Date d\'export', date('d/m/Y H:i:s')], ';');
    fputcsv($output, ['Nombre d\'élèves', count($eleves)], ';');
    fputcsv($output, ['École', $eleves[0]['nom_ecole'] ?? 'Non définie'], ';');
    
    if ($classe_id) {
        fputcsv($output, ['Classe', $eleves[0]['nom_classe'] ?? 'Non définie'], ';');
    }
    
    if ($statut) {
        fputcsv($output, ['Statut', $statut], ';');
    }
    
    fclose($output);
    
    // Log de l'action
    logUserAction('EXPORT_STUDENTS_CSV', "Exportation de " . count($eleves) . " élève(s) en CSV");
    
} catch (Exception $e) {
    die('Erreur lors de l\'exportation: ' . $e->getMessage());
}
?>

