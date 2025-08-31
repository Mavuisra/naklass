<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

// Mode d'affichage : single, class, or multiple
$mode = $_GET['mode'] ?? 'single';
$eleves = [];

try {
    if ($mode === 'single') {
        // Mode carte simple
        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            redirect('index.php');
        }
        
        $query = "SELECT e.*, ec.nom_ecole as ecole_nom, ec.adresse as ecole_adresse, ec.logo_path as logo_url, ec.directeur_nom,
                         ec.type_etablissement, ec.devise_principale, ec.description_etablissement,
                         i.date_inscription, c.nom_classe as classe_nom, c.niveau, c.niveau_detaille
                  FROM eleves e
                  JOIN ecoles ec ON e.ecole_id = ec.id
                  LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'validée'
                  LEFT JOIN classes c ON i.classe_id = c.id
                  WHERE e.id = :id AND e.ecole_id = :ecole_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            'id' => $_GET['id'],
            'ecole_id' => $_SESSION['ecole_id']
        ]);
        
        $eleve = $stmt->fetch();
        if (!$eleve) {
            setFlashMessage('error', 'Élève non trouvé.');
            redirect('index.php');
        }
        $eleves = [$eleve];
        
    } elseif ($mode === 'class') {
        // Mode cartes par classe
        if (!isset($_GET['class_id']) || !is_numeric($_GET['class_id'])) {
            redirect('index.php');
        }
        
        $query = "SELECT e.*, ec.nom_ecole as ecole_nom, ec.adresse as ecole_adresse, ec.logo_path as logo_url, ec.directeur_nom,
                         ec.type_etablissement, ec.devise_principale, ec.description_etablissement,
                         i.date_inscription, c.nom_classe as classe_nom, c.niveau, c.niveau_detaille
                  FROM eleves e
                  JOIN ecoles ec ON e.ecole_id = ec.id
                  JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'validée'
                  JOIN classes c ON i.classe_id = c.id
                  WHERE c.id = :class_id AND e.ecole_id = :ecole_id
                  ORDER BY e.nom, e.prenom";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            'class_id' => $_GET['class_id'],
            'ecole_id' => $_SESSION['ecole_id']
        ]);
        
        $eleves = $stmt->fetchAll();
        if (empty($eleves)) {
            setFlashMessage('error', 'Aucun élève trouvé dans cette classe.');
            redirect('../classes/index.php');
        }
        
    } elseif ($mode === 'multiple') {
        // Mode cartes multiples (IDs spécifiés)
        $ids = explode(',', $_GET['ids'] ?? '');
        $ids = array_filter(array_map('intval', $ids));
        
        if (empty($ids)) {
            redirect('index.php');
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $query = "SELECT e.*, ec.nom_ecole as ecole_nom, ec.adresse as ecole_adresse, ec.logo_path as logo_url, ec.directeur_nom,
                         ec.type_etablissement, ec.devise_principale, ec.description_etablissement,
                         i.date_inscription, c.nom_classe as classe_nom, c.niveau, c.niveau_detaille
                  FROM eleves e
                  JOIN ecoles ec ON e.ecole_id = ec.id
                  LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'validée'
                  LEFT JOIN classes c ON i.classe_id = c.id
                  WHERE e.id IN ($placeholders) AND e.ecole_id = :ecole_id
                  ORDER BY e.nom, e.prenom";
        
        $params = array_merge($ids, [$_SESSION['ecole_id']]);
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        $eleves = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération des données.');
    redirect('index.php');
}

// Fonction pour générer le QR Code
function generateQrData($eleve) {
    return json_encode([
        'matricule' => $eleve['matricule'],
        'nom' => $eleve['nom'],
        'prenom' => $eleve['prenom'],
        'ecole_id' => $eleve['ecole_id'],
        'id' => $eleve['id'],
        'classe' => $eleve['classe_nom'] ?? '',
        'generated' => date('Y-m-d H:i:s')
    ]);
}

// Fonction pour obtenir le chemin de la photo pour la carte
function getStudentCardPhotoPath($eleve) {
    // Inclure la configuration des photos
    require_once '../config/photo_config.php';
    
    // Si l'élève a une photo dans la base de données
    if (!empty($eleve['photo_path'])) {
        // Essayer d'abord le chemin avec PHOTO_CONFIG
        $photo_path = '../' . PHOTO_CONFIG['UPLOAD_DIR'] . $eleve['photo_path'];
        if (file_exists($photo_path)) {
            return $photo_path;
        }
        
        // Essayer le chemin direct dans uploads/students
        $photo_path = '../uploads/students/' . $eleve['photo_path'];
        if (file_exists($photo_path)) {
            return $photo_path;
        }
    }
    
    // Essayer les anciens chemins pour compatibilité
    $photo_paths = [
        '../uploads/students/' . $eleve['id'] . '.jpg',
        '../uploads/students/' . $eleve['id'] . '.png',
        '../uploads/students/' . $eleve['matricule'] . '.jpg',
        '../uploads/students/' . $eleve['matricule'] . '.png'
    ];
    
    foreach ($photo_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Photo par défaut avec l'initiale de l'élève
    return 'https://ui-avatars.com/api/?name=' . urlencode($eleve['prenom'] . '+' . $eleve['nom']) . '&background=667eea&color=fff&size=200';
}

// Fonction pour récupérer les informations des tuteurs
function getTuteursInfo($db, $eleve_id) {
    try {
        $query = "SELECT t.*, et.lien_parente, et.tuteur_principal, et.autorisation_sortie
                  FROM tuteurs t 
                  JOIN eleves_tuteurs et ON t.id = et.tuteur_id
                  WHERE et.eleve_id = :eleve_id AND t.statut = 'actif' AND et.statut = 'actif'
                  ORDER BY et.tuteur_principal DESC, t.nom, t.prenom";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['eleve_id' => $eleve_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Fonction pour obtenir le logo de l'école
function getSchoolLogo($ecole_data) {
    if (!empty($ecole_data['logo_url']) && file_exists($ecole_data['logo_url'])) {
        return $ecole_data['logo_url'];
    }
    
    // Logo par défaut avec l'initiale de l'école
    $initiale = strtoupper(substr($ecole_data['ecole_nom'] ?? 'E', 0, 1));
    return 'data:image/svg+xml;base64,' . base64_encode('
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
            <rect width="100" height="100" rx="50" fill="#667eea"/>
            <text x="50" y="65" font-family="Arial, sans-serif" font-size="40" font-weight="bold" text-anchor="middle" fill="white">' . $initiale . '</text>
        </svg>
    ');
}

$page_title = count($eleves) > 1 ? 
    "Cartes d'élèves (" . count($eleves) . " cartes)" : 
    "Carte d'élève - " . $eleves[0]['prenom'] . ' ' . $eleves[0]['nom'];

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Naklass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <style>
        /* Variables CSS pour la cohérence et l'édition */
        :root {
            --card-width: 320px;
            --card-height: 200px;
            --card-radius: 16px;
            --primary-gradient: linear-gradient(135deg, #667eea 0%,rgb(25, 88, 176) 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            --card-shadow-hover: 0 25px 50px rgba(0, 0, 0, 0.3);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Carte d'étudiant moderne et éditable */
        .student-card {
            width: var(--card-width);
            height: var(--card-height);
            background: var(--primary-gradient);
            border-radius: var(--card-radius);
            position: relative;
            color: white;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin: 15px;
            display: inline-block;
            vertical-align: top;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
        }

        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }

        .student-card.editing {
            transform: scale(1.02);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.5);
        }

        /* Structures de cartes alternatives */
        .student-card.structure-compact {
            --card-height: 180px;
        }

        .student-card.structure-compact .card-body {
            padding: 10px 12px;
            gap: 8px;
        }

        .student-card.structure-compact .student-photo {
            width: 50px;
            height: 50px;
        }

        .student-card.structure-compact .student-name {
            font-size: 11px;
        }

        .student-card.structure-compact .student-details {
            font-size: 7px;
        }

        .student-card.structure-wide {
            --card-width: 400px;
            --card-height: 150px;
        }

        .student-card.structure-wide .card-body {
            flex-direction: row;
            align-items: center;
        }

        .student-card.structure-wide .student-photo {
            width: 80px;
            height: 80px;
        }

        .student-card.structure-wide .student-info {
            flex-direction: row;
            gap: 20px;
        }

        .student-card.structure-vertical {
            --card-width: 280px;
            --card-height: 350px;
        }

        .student-card.structure-vertical .card-body {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .student-card.structure-vertical .student-photo {
            width: 100px;
            height: 100px;
            margin-bottom: 15px;
        }

        .student-card.structure-vertical .student-info {
            text-align: center;
        }

        .student-card.structure-vertical .student-name {
            font-size: 16px;
            margin-bottom: 10px;
        }

        /* Indicateur d'édition */
        .edit-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 10;
        }

        .student-card.editing .edit-indicator {
            opacity: 1;
        }

        /* Motifs d'arrière-plan de l'école */
        .school-patterns {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
            opacity: 0.1;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .pattern-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
        }

        /* Section des tuteurs */
        .tutors-section {
            position: relative;
            z-index: 2;
            margin: 8px 12px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .tutors-header {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
            font-size: 9px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tutors-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .tutor-item {
            padding: 4px 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.2s ease;
        }

        .tutor-item.principal {
            background: rgba(255, 215, 0, 0.2);
            border-color: rgba(255, 215, 0, 0.4);
        }

        .tutor-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .tutor-name {
            font-size: 8px;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .badge-principal {
            background: rgba(255, 215, 0, 0.8);
            color: #333;
            padding: 1px 4px;
            border-radius: 4px;
            font-size: 6px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .tutor-details {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .tutor-relation {
            font-size: 7px;
            color: rgba(255, 255, 255, 0.8);
            font-style: italic;
        }

        .tutor-phone {
            font-size: 7px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        /* Devise de l'école */
        .school-motto {
            font-size: 6px;
            color: rgba(255, 255, 255, 0.7);
            font-style: italic;
            margin-top: 1px;
            text-align: center;
        }

        /* Amélioration du logo de l'école */
        .school-logo {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.9);
            padding: 2px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* Pattern décoratif en arrière-plan */
        .student-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .student-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 80px;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(-30%, 30%);
        }

        /* En-tête de la carte */
        .card-header {
            text-align: center;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 2;
        }

        .school-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .school-logo {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.9);
            padding: 2px;
        }

        .school-name {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-type {
            font-size: 7px;
            opacity: 0.8;
            margin-top: 1px;
        }

        /* Corps de la carte */
        .card-body {
            padding: 12px 15px;
            display: flex;
            gap: 12px;
            position: relative;
            z-index: 2;
        }

        .student-photo {
            width: 65px;
            height: 65px;
            border-radius: 12px;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.1);
        }

        .student-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .student-name {
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.2;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .matricule {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 6px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .student-details {
            font-size: 8px;
            line-height: 1.3;
            opacity: 0.95;
        }

        .student-details div {
            margin-bottom: 2px;
        }

        .detail-label {
            font-weight: 600;
            opacity: 0.8;
        }

        /* QR Code */
        .qr-code {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            z-index: 3;
        }

        /* Pied de carte */
        .card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            padding: 4px 8px;
            font-size: 7px;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(5px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 2;
        }

        .academic-year {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        /* Conteneur des cartes multiples */
        .cards-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            padding: 20px;
            max-width: 100%;
        }

        /* Grille pour impression (3x3 = 9 cartes par page) */
        .print-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            padding: 20px;
            page-break-after: always;
        }

        .print-grid:last-child {
            page-break-after: avoid;
        }

        /* Panneau d'édition des cartes */
        .card-editor-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: sticky;
            top: 20px;
            z-index: 1000;
        }

        .editor-section {
            margin-bottom: 25px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .editor-section h6 {
            color: #333;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .color-picker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .color-option {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .color-option:hover {
            transform: scale(1.1);
            border-color: #333;
        }

        .color-option.active {
            border-color: #333;
            box-shadow: 0 0 0 3px rgba(51, 51, 51, 0.3);
        }

        .color-option::before {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            font-size: 18px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .color-option.active::before {
            opacity: 1;
        }

        .gradient-primary { background: var(--primary-gradient); }
        .gradient-secondary { background: var(--secondary-gradient); }
        .gradient-success { background: var(--success-gradient); }
        .gradient-warning { background: var(--warning-gradient); }
        .gradient-danger { background: var(--danger-gradient); }
        .gradient-info { background: var(--info-gradient); }

        .structure-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .structure-option {
            padding: 15px;
            background: white;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-weight: 600;
            color: #495057;
        }

        .structure-option:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .structure-option.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .btn-modern:active {
            transform: translateY(0);
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-success-modern {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-warning-modern {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #333;
        }

        .btn-danger-modern {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #333;
        }

        /* Contrôles d'interface */
        .controls-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn-modern:hover::before {
            left: 100%;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-success-modern {
            background: var(--success-gradient);
            color: white;
        }

        .btn-secondary-modern {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }

        /* Styles d'impression */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .student-card {
                box-shadow: 0 0 0 1px #ddd !important;
                margin: 5px !important;
                page-break-inside: avoid;
                width: 300px !important;
                height: 190px !important;
            }
            
            .print-grid {
                grid-template-columns: repeat(3, 300px);
                gap: 10px;
                padding: 10px;
                justify-content: center;
            }
            
            .cards-container {
                display: none;
            }
            
            .print-layout {
                display: block !important;
            }
        }

        /* Mode sombre */
        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            }
            
            .controls-panel {
                background: rgba(255, 255, 255, 0.1);
                color: white;
            }
        }

        /* Animations */
        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(30px) rotateX(45deg);
            }
            to {
                opacity: 1;
                transform: translateY(0) rotateX(0);
            }
        }

        .student-card {
            animation: cardAppear 0.6s ease-out forwards;
        }

        .student-card:nth-child(1) { animation-delay: 0.1s; }
        .student-card:nth-child(2) { animation-delay: 0.2s; }
        .student-card:nth-child(3) { animation-delay: 0.3s; }
        .student-card:nth-child(4) { animation-delay: 0.4s; }
        .student-card:nth-child(5) { animation-delay: 0.5s; }
        .student-card:nth-child(6) { animation-delay: 0.6s; }
        .student-card:nth-child(7) { animation-delay: 0.7s; }
        .student-card:nth-child(8) { animation-delay: 0.8s; }
        .student-card:nth-child(9) { animation-delay: 0.9s; }

        /* Responsive */
        @media (max-width: 768px) {
            .cards-container {
                padding: 10px;
            }
            
            .student-card {
                width: calc(100vw - 40px);
                max-width: 320px;
            }
            
            .controls-panel {
                margin: 10px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Panneau de contrôle -->
    <div class="controls-panel no-print">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="mb-1">
                    <i class="bi bi-card-heading me-2"></i><?php echo htmlspecialchars($page_title); ?>
                </h4>
                <p class="text-muted mb-0">
                    <?php if (count($eleves) > 1): ?>
                        <?php echo count($eleves); ?> cartes d'élèves prêtes à imprimer
                    <?php else: ?>
                        Carte d'élève de <?php echo htmlspecialchars($eleves[0]['classe_nom'] ?? 'classe non définie'); ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="d-flex gap-2 flex-wrap">
                <button onclick="exportToPDF()" class="btn btn-modern btn-primary-modern">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Exporter PDF
                </button>
                <button onclick="window.print()" class="btn btn-modern btn-success-modern">
                    <i class="bi bi-printer me-2"></i>Imprimer
                </button>
                <button onclick="downloadAllCards()" class="btn btn-modern btn-secondary-modern">
                    <i class="bi bi-download me-2"></i>Télécharger Tout
                </button>
                <a href="<?php echo $mode === 'single' ? 'view.php?id=' . $eleves[0]['id'] : '../classes/index.php'; ?>" 
                   class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </div>
        
        <?php if (count($eleves) > 1): ?>
        <div class="mt-3 p-3 bg-light rounded">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Impression optimisée :</strong> 9 cartes par page A4
                    </small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">
                        Pages nécessaires : <strong><?php echo ceil(count($eleves) / 9); ?></strong>
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Panneau d'édition des cartes -->
    <div class="row">
        <div class="col-lg-4">
            <div class="card-editor-panel">
                <h4 class="mb-4"><i class="bi bi-palette me-2"></i>Éditeur de cartes</h4>
                
                <!-- Sélecteur de couleurs -->
                <div class="editor-section">
                    <h6><i class="bi bi-palette-fill me-2"></i>Couleurs des cartes</h6>
                    <div class="color-picker-grid">
                        <div class="color-option gradient-primary active" data-gradient="primary"></div>
                        <div class="color-option gradient-secondary" data-gradient="secondary"></div>
                        <div class="color-option gradient-success" data-gradient="success"></div>
                        <div class="color-option gradient-warning" data-gradient="warning"></div>
                        <div class="color-option gradient-danger" data-gradient="danger"></div>
                        <div class="color-option gradient-info" data-gradient="info"></div>
                    </div>
                </div>
                
                <!-- Sélecteur de structure -->
                <div class="editor-section">
                    <h6><i class="bi bi-layout-text-window me-2"></i>Structure des cartes</h6>
                    <div class="structure-controls">
                        <div class="structure-option active" data-structure="default">Standard</div>
                        <div class="structure-option" data-structure="compact">Compacte</div>
                        <div class="structure-option" data-structure="wide">Large</div>
                        <div class="structure-option" data-structure="vertical">Verticale</div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="editor-section">
                    <h6><i class="bi bi-gear me-2"></i>Actions</h6>
                    <div class="d-grid gap-2">
                        <button class="btn btn-modern btn-primary-modern" onclick="applyChanges()">
                            <i class="bi bi-check-circle me-2"></i>Appliquer
                        </button>
                        <button class="btn btn-modern btn-success-modern" onclick="exportToPDF()">
                            <i class="bi bi-file-pdf me-2"></i>Exporter PDF
                        </button>
                        <button class="btn btn-modern btn-warning-modern" onclick="downloadAllCards()">
                            <i class="bi bi-download me-2"></i>Télécharger
                        </button>
                        <button class="btn btn-modern btn-danger-modern" onclick="resetToDefault()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Réinitialiser
                        </button>
                    </div>
                </div>
                
                <!-- Informations -->
                <div class="editor-section">
                    <h6><i class="bi bi-info-circle me-2"></i>Informations</h6>
                    <small class="text-muted">
                        <strong>Cliquez sur une carte</strong> pour la modifier individuellement.<br>
                        <strong>Utilisez les contrôles</strong> pour personnaliser toutes les cartes.<br>
                        <strong>Appuyez sur Appliquer</strong> pour sauvegarder vos modifications.
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
    <!-- Conteneur des cartes pour affichage -->
    <div class="cards-container">
        <?php foreach ($eleves as $index => $eleve): ?>
            <?php 
            $age = date_diff(date_create($eleve['date_naissance']), date_create('today'))->y;
            $photo_path = getStudentCardPhotoPath($eleve);
            ?>
            
                    <div class="student-card" data-student-id="<?php echo $eleve['id']; ?>" 
                         style="background: var(--primary-gradient);">
                        <div class="edit-indicator">Éditer</div>
                        
                <!-- En-tête avec logo école -->
                <div class="card-header">
                    <div class="school-info">
                                <img src="<?php echo getSchoolLogo($eleve); ?>" alt="Logo école" class="school-logo">
                        <div>
                            <div class="school-name"><?php echo htmlspecialchars(strtoupper($eleve['ecole_nom'])); ?></div>
                            <div class="card-type">CARTE D'ÉLÈVE</div>
                                    <?php if (!empty($eleve['devise_principale'])): ?>
                                    <div class="school-motto"><?php echo htmlspecialchars($eleve['devise_principale']); ?></div>
                                    <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Corps de la carte -->
                <div class="card-body">
                    <!-- Photo de l'élève -->
                    <img src="<?php echo $photo_path; ?>" alt="Photo élève" class="student-photo">
                    
                    <!-- Informations de l'élève -->
                    <div class="student-info">
                        <div>
                            <div class="student-name">
                                <?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?>
                            </div>
                            
                            <div class="matricule">
                                N° <?php echo htmlspecialchars($eleve['matricule']); ?>
                            </div>
                        </div>
                        
                        <div class="student-details">
                            <div>
                                <span class="detail-label">Né(e) le:</span> 
                                <?php echo date('d/m/Y', strtotime($eleve['date_naissance'])); ?>
                            </div>
                            <div>
                                <span class="detail-label">Âge:</span> <?php echo $age; ?> ans
                            </div>
                            <div>
                                <span class="detail-label">Sexe:</span> 
                                <?php echo $eleve['sexe'] == 'M' ? 'Masculin' : ($eleve['sexe'] == 'F' ? 'Féminin' : $eleve['sexe']); ?>
                            </div>
                            <?php if ($eleve['classe_nom']): ?>
                            <div>
                                <span class="detail-label">Classe:</span> <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($eleve['niveau']): ?>
                            <div>
                                <span class="detail-label">Niveau:</span> <?php echo htmlspecialchars($eleve['niveau']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($eleve['niveau_detaille']): ?>
                            <div>
                                <span class="detail-label">Niveau détaillé:</span> <?php echo htmlspecialchars($eleve['niveau_detaille']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($eleve['type_etablissement']): ?>
                            <div>
                                <span class="detail-label">Type:</span> <?php echo htmlspecialchars($eleve['type_etablissement']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($eleve['groupe_sanguin']): ?>
                            <div>
                                <span class="detail-label">Groupe:</span> <?php echo htmlspecialchars($eleve['groupe_sanguin']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Section des tuteurs -->
                <?php 
                $tuteurs = getTuteursInfo($db, $eleve['id']);
                if (!empty($tuteurs)): 
                ?>
                <div class="tutors-section">
                    <div class="tutors-header">
                        <i class="bi bi-people-fill"></i>
                        <span>Tuteurs</span>
                    </div>
                    <div class="tutors-list">
                        <?php foreach ($tuteurs as $tuteur): ?>
                        <div class="tutor-item <?php echo $tuteur['tuteur_principal'] ? 'principal' : ''; ?>">
                            <div class="tutor-info">
                                <div class="tutor-name">
                                    <?php echo htmlspecialchars($tuteur['prenom'] . ' ' . $tuteur['nom']); ?>
                                    <?php if ($tuteur['tuteur_principal']): ?>
                                        <span class="badge-principal">Principal</span>
                                    <?php endif; ?>
                                </div>
                                <div class="tutor-details">
                                    <span class="tutor-relation"><?php echo ucfirst($tuteur['lien_parente']); ?></span>
                                    <?php if (!empty($tuteur['telephone'])): ?>
                                        <span class="tutor-phone">📞 <?php echo htmlspecialchars($tuteur['telephone']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- QR Code -->
                <div class="qr-code">
                    <canvas id="qrcode-<?php echo $eleve['id']; ?>" width="30" height="30"></canvas>
                </div>
                
                <!-- Footer -->
                <div class="card-footer">
                    <div class="academic-year">
                        Année <?php echo date('Y'); ?>-<?php echo date('Y') + 1; ?>
                        <?php if ($eleve['directeur_nom']): ?>
                            • <?php echo htmlspecialchars($eleve['directeur_nom']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Layout d'impression (9 cartes par page) -->
    <div class="print-layout" style="display: none;">
        <?php 
        $chunks = array_chunk($eleves, 9);
        foreach ($chunks as $pageIndex => $pageEleves): 
        ?>
            <div class="print-grid">
                <?php foreach ($pageEleves as $eleve): ?>
                    <?php 
                    $age = date_diff(date_create($eleve['date_naissance']), date_create('today'))->y;
                    $photo_path = getStudentCardPhotoPath($eleve);
                    ?>
                    
                    <div class="student-card" 
                         style="background: var(--primary-gradient);">
                        
                        <div class="card-header">
                            <div class="school-info">
                                <img src="<?php echo getSchoolLogo($eleve); ?>" alt="Logo" class="school-logo">
                                <div>
                                    <div class="school-name"><?php echo htmlspecialchars(strtoupper($eleve['ecole_nom'])); ?></div>
                                    <div class="card-type">CARTE D'ÉLÈVE</div>
                                    <?php if (!empty($eleve['devise_principale'])): ?>
                                    <div class="school-motto"><?php echo htmlspecialchars($eleve['devise_principale']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <img src="<?php echo $photo_path; ?>" alt="Photo élève" class="student-photo">
                            
                            <div class="student-info">
                                <div>
                                    <div class="student-name">
                                        <?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?>
                                    </div>
                                    <div class="matricule">
                                        N° <?php echo htmlspecialchars($eleve['matricule']); ?>
                                    </div>
                                </div>
                                
                                <div class="student-details">
                                    <div><span class="detail-label">Né(e) le:</span> <?php echo date('d/m/Y', strtotime($eleve['date_naissance'])); ?></div>
                                    <div><span class="detail-label">Âge:</span> <?php echo $age; ?> ans</div>
                                    <div><span class="detail-label">Sexe:</span> <?php echo $eleve['sexe'] == 'M' ? 'M' : 'F'; ?></div>
                                    <?php if ($eleve['classe_nom']): ?>
                                    <div><span class="detail-label">Classe:</span> <?php echo htmlspecialchars($eleve['classe_nom']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($eleve['niveau']): ?>
                                    <div><span class="detail-label">Niveau:</span> <?php echo htmlspecialchars($eleve['niveau']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($eleve['niveau_detaille']): ?>
                                    <div><span class="detail-label">Niveau détaillé:</span> <?php echo htmlspecialchars($eleve['niveau_detaille']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($eleve['type_etablissement']): ?>
                                    <div><span class="detail-label">Type:</span> <?php echo htmlspecialchars($eleve['type_etablissement']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section des tuteurs pour l'impression -->
                        <?php 
                        $tuteurs = getTuteursInfo($db, $eleve['id']);
                        if (!empty($tuteurs)): 
                        ?>
                        <div class="tutors-section">
                            <div class="tutors-header">
                                <i class="bi bi-people-fill"></i>
                                <span>Tuteurs</span>
                            </div>
                            <div class="tutors-list">
                                <?php foreach ($tuteurs as $tuteur): ?>
                                <div class="tutor-item <?php echo $tuteur['tuteur_principal'] ? 'principal' : ''; ?>">
                                    <div class="tutor-info">
                                        <div class="tutor-name">
                                            <?php echo htmlspecialchars($tuteur['prenom'] . ' ' . $tuteur['nom']); ?>
                                            <?php if ($tuteur['tuteur_principal']): ?>
                                                <span class="badge-principal">Principal</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="tutor-details">
                                            <span class="tutor-relation"><?php echo ucfirst($tuteur['lien_parente']); ?></span>
                                            <?php if (!empty($tuteur['telephone'])): ?>
                                                <span class="tutor-phone">📞 <?php echo htmlspecialchars($tuteur['telephone']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="qr-code">
                            <canvas class="qr-print" data-student-id="<?php echo $eleve['id']; ?>" width="30" height="30"></canvas>
                        </div>
                        
                        <div class="card-footer">
                            <div class="academic-year">
                                Année <?php echo date('Y'); ?>-<?php echo date('Y') + 1; ?>
                                <?php if ($eleve['directeur_nom']): ?>
                                    • <?php echo htmlspecialchars($eleve['directeur_nom']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Informations supplémentaires (carte simple seulement) -->
    <?php if (count($eleves) === 1): ?>
    <div class="container mt-4 no-print">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="controls-panel">
                    <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>Informations sur la carte</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><strong>Date de génération:</strong> <?php echo date('d/m/Y H:i'); ?></li>
                                <li><strong>Générée par:</strong> <?php echo htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?></li>
                                <li><strong>Validité:</strong> Année académique en cours</li>
                                <li><strong>Statut:</strong> <?php echo ucfirst($eleves[0]['statut_scolaire'] ?? 'Actif'); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><strong>Code QR:</strong> Identité numérique sécurisée</li>
                                <li><strong>Matricule:</strong> Unique dans l'établissement</li>
                                <li><strong>Format:</strong> 320x200 pixels optimisé</li>
                                <li><strong>Impression:</strong> Recommandée sur papier cartonné</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Conseils d'utilisation:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Plastifiez la carte pour une meilleure durabilité</li>
                            <li>Le code QR permet une identification rapide et sécurisée</li>
                            <li>Conservez une copie numérique en cas de perte</li>
                            <li>Renouvelez la carte en cas de changement d'informations importantes</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Données des élèves pour JavaScript
        const studentsData = <?php echo json_encode($eleves); ?>;
        
        // Générer tous les QR codes
        document.addEventListener('DOMContentLoaded', function() {
            studentsData.forEach(function(eleve) {
                const qrData = JSON.stringify({
                    matricule: eleve.matricule,
                    nom: eleve.nom,
                    prenom: eleve.prenom,
                    ecole_id: eleve.ecole_id,
                    id: eleve.id,
                    classe: eleve.classe_nom || '',
                    generated: new Date().toISOString()
                });
                
                // QR code pour l'affichage
                const canvas = document.getElementById('qrcode-' + eleve.id);
                if (canvas) {
                    QRCode.toCanvas(canvas, qrData, {
                        width: 30,
                        height: 30,
                        margin: 0,
                        color: { dark: '#000000', light: '#FFFFFF' }
                    });
                }
                
                // QR code pour l'impression
                const printCanvas = document.querySelector('.qr-print[data-student-id="' + eleve.id + '"]');
                if (printCanvas) {
                    QRCode.toCanvas(printCanvas, qrData, {
                        width: 30,
                        height: 30,
                        margin: 0,
                        color: { dark: '#000000', light: '#FFFFFF' }
                    });
                }
            });
        });

        // Fonction d'exportation PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });

            const cards = document.querySelectorAll('.student-card');
            const cardsPerPage = 9;
            const cardWidth = 85; // mm
            const cardHeight = 54; // mm
            const marginX = 15;
            const marginY = 20;
            const spacingX = 5;
            const spacingY = 8;

            let currentPage = 0;
            let cardIndex = 0;

            function addCardToPage(card, position) {
                const row = Math.floor(position / 3);
                const col = position % 3;
                const x = marginX + col * (cardWidth + spacingX);
                const y = marginY + row * (cardHeight + spacingY);

                return html2canvas(card, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true
                }).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    pdf.addImage(imgData, 'PNG', x, y, cardWidth, cardHeight);
                });
            }

            function processPage(startIndex) {
                const promises = [];
                const endIndex = Math.min(startIndex + cardsPerPage, cards.length);

                for (let i = startIndex; i < endIndex; i++) {
                    const position = i - startIndex;
                    promises.push(addCardToPage(cards[i], position));
                }

                return Promise.all(promises).then(() => {
                    if (endIndex < cards.length) {
                        pdf.addPage();
                        return processPage(endIndex);
                    }
                });
            }

            // Afficher un indicateur de chargement
            const loadingToast = document.createElement('div');
            loadingToast.className = 'position-fixed top-0 end-0 m-3 alert alert-info';
            loadingToast.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Génération du PDF en cours...';
            document.body.appendChild(loadingToast);

            processPage(0).then(() => {
                const filename = studentsData.length === 1 
                    ? `carte-${studentsData[0].prenom}-${studentsData[0].nom}.pdf`
                    : `cartes-eleves-${new Date().toISOString().split('T')[0]}.pdf`;
                
                pdf.save(filename);
                document.body.removeChild(loadingToast);
                
                // Toast de succès
                const successToast = document.createElement('div');
                successToast.className = 'position-fixed top-0 end-0 m-3 alert alert-success';
                successToast.innerHTML = '<i class="bi bi-check-circle me-2"></i>PDF généré avec succès !';
                document.body.appendChild(successToast);
                setTimeout(() => document.body.removeChild(successToast), 3000);
            }).catch(error => {
                console.error('Erreur lors de la génération du PDF:', error);
                document.body.removeChild(loadingToast);
                
                const errorToast = document.createElement('div');
                errorToast.className = 'position-fixed top-0 end-0 m-3 alert alert-danger';
                errorToast.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Erreur lors de la génération du PDF';
                document.body.appendChild(errorToast);
                setTimeout(() => document.body.removeChild(errorToast), 5000);
            });
        }

        // Fonction de téléchargement individuel
        function downloadAllCards() {
            const cards = document.querySelectorAll('.student-card');
            let downloadCount = 0;
            
            cards.forEach((card, index) => {
                const studentName = studentsData[index].prenom + '-' + studentsData[index].nom;
                
                html2canvas(card, {
                    scale: 3,
                    useCORS: true,
                    allowTaint: true
                }).then(canvas => {
                    const link = document.createElement('a');
                    link.download = `carte-${studentName}.png`;
                    link.href = canvas.toDataURL();
                    link.click();
                    
                    downloadCount++;
                    if (downloadCount === cards.length) {
                        const successToast = document.createElement('div');
                        successToast.className = 'position-fixed top-0 end-0 m-3 alert alert-success';
                        successToast.innerHTML = `<i class="bi bi-download me-2"></i>${cards.length} cartes téléchargées !`;
                        document.body.appendChild(successToast);
                        setTimeout(() => document.body.removeChild(successToast), 3000);
                    }
                });
            });
        }

        // Améliorer l'impression
        window.addEventListener('beforeprint', function() {
            document.querySelector('.cards-container').style.display = 'none';
            document.querySelector('.print-layout').style.display = 'block';
        });

        window.addEventListener('afterprint', function() {
            document.querySelector('.cards-container').style.display = 'flex';
            document.querySelector('.print-layout').style.display = 'none';
        });

        // ===== SYSTÈME D'ÉDITION DES CARTES =====
        
        // Variables globales pour l'édition
        let currentGradient = 'primary';
        let currentStructure = 'default';
        let editingCard = null;

        // Initialiser l'éditeur
        document.addEventListener('DOMContentLoaded', function() {
            initializeCardEditor();
            initializeCardClickEvents();
        });

        // Initialiser l'éditeur de cartes
        function initializeCardEditor() {
            // Gestionnaire pour les couleurs
            document.querySelectorAll('.color-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    currentGradient = this.dataset.gradient;
                    previewChanges();
                });
            });

            // Gestionnaire pour les structures
            document.querySelectorAll('.structure-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.structure-option').forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    currentStructure = this.dataset.structure;
                    previewChanges();
                });
            });
        }

        // Initialiser les événements de clic sur les cartes
        function initializeCardClickEvents() {
            document.querySelectorAll('.student-card').forEach(card => {
                card.addEventListener('click', function() {
                    if (editingCard === this) {
                        // Désélectionner la carte
                        this.classList.remove('editing');
                        editingCard = null;
                    } else {
                        // Sélectionner la carte
                        if (editingCard) {
                            editingCard.classList.remove('editing');
                        }
                        this.classList.add('editing');
                        editingCard = this;
                        
                        // Appliquer les modifications à cette carte uniquement
                        applyChangesToCard(this);
                    }
                });
            });
        }

        // Prévisualiser les changements
        function previewChanges() {
            const cards = document.querySelectorAll('.student-card');
            cards.forEach(card => {
                if (!card.classList.contains('editing')) {
                    applyChangesToCard(card);
                }
            });
        }

        // Appliquer les changements à une carte spécifique
        function applyChangesToCard(card) {
            // Appliquer le gradient
            card.style.background = getGradientValue(currentGradient);
            
            // Appliquer la structure
            card.className = `student-card structure-${currentStructure}`;
            
            // Ajouter l'attribut data-student-id s'il n'existe pas
            if (!card.dataset.studentId) {
                card.dataset.studentId = card.querySelector('canvas')?.id?.replace('qrcode-', '') || 'unknown';
            }
        }

        // Obtenir la valeur du gradient
        function getGradientValue(gradientName) {
            const gradients = {
                primary: 'var(--primary-gradient)',
                secondary: 'var(--secondary-gradient)',
                success: 'var(--success-gradient)',
                warning: 'var(--warning-gradient)',
                danger: 'var(--danger-gradient)',
                info: 'var(--info-gradient)'
            };
            return gradients[gradientName] || gradients.primary;
        }

        // Appliquer tous les changements
        function applyChanges() {
            const cards = document.querySelectorAll('.student-card');
            cards.forEach(card => {
                applyChangesToCard(card);
            });
            
            // Afficher un message de succès
            showToast('Modifications appliquées avec succès !', 'success');
        }

        // Réinitialiser à la configuration par défaut
        function resetToDefault() {
            currentGradient = 'primary';
            currentStructure = 'default';
            
            // Réinitialiser les sélecteurs
            document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector('.color-option[data-gradient="primary"]').classList.add('active');
            
            document.querySelectorAll('.structure-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector('.structure-option[data-structure="default"]').classList.add('active');
            
            // Réinitialiser toutes les cartes
            const cards = document.querySelectorAll('.student-card');
            cards.forEach(card => {
                card.style.background = '';
                card.className = 'student-card';
                card.classList.remove('editing');
            });
            
            editingCard = null;
            showToast('Configuration réinitialisée !', 'info');
        }

        // Afficher un toast de notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `position-fixed top-0 end-0 m-3 alert alert-${type} alert-dismissible fade show`;
            toast.innerHTML = `
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 5000);
        }

        // Sauvegarder la configuration dans le localStorage
        function saveConfiguration() {
            const config = {
                gradient: currentGradient,
                structure: currentStructure,
                timestamp: new Date().toISOString()
            };
            localStorage.setItem('cardEditorConfig', JSON.stringify(config));
        }

        // Charger la configuration depuis le localStorage
        function loadConfiguration() {
            const saved = localStorage.getItem('cardEditorConfig');
            if (saved) {
                try {
                    const config = JSON.parse(saved);
                    currentGradient = config.gradient;
                    currentStructure = config.structure;
                    
                    // Appliquer la configuration
                    document.querySelector(`.color-option[data-gradient="${currentGradient}"]`)?.classList.add('active');
                    document.querySelector(`.structure-option[data-structure="${currentStructure}"]`)?.classList.add('active');
                    
                    previewChanges();
                } catch (e) {
                    console.error('Erreur lors du chargement de la configuration:', e);
                }
            }
        }

        // Charger la configuration au démarrage
        loadConfiguration();
    </script>
</body>
</html>
