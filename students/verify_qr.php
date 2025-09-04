<?php
/**
 * Système de vérification des QR codes des cartes d'élèves
 * 
 * Cette page permet de scanner et valider les QR codes des cartes d'élèves
 * pour vérifier leur authenticité et récupérer les informations de l'élève.
 * 
 * @author Naklass Team
 * @version 1.0.0
 * @since 2024
 */

require_once '../includes/functions.php';
require_once '../includes/QRCodeManager.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire', 'enseignant']);

$database = new Database();
$db = $database->getConnection();
$qrManager = new QRCodeManager();

$verificationResult = null;
$studentInfo = null;
$errorMessage = null;

// Traitement de la vérification si un QR code est fourni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    try {
        $qrData = $_POST['qr_data'];
        
        // Décoder les données JSON du QR code
        $decodedData = json_decode($qrData, true);
        
        if (!$decodedData) {
            throw new Exception('Données QR code invalides');
        }
        
        // Vérifier le type de QR code
        if (!isset($decodedData['type']) || $decodedData['type'] !== 'student_card') {
            throw new Exception('Ce QR code n\'est pas une carte d\'élève valide');
        }
        
        // Vérifier la version
        if (!isset($decodedData['version']) || $decodedData['version'] !== '1.0') {
            throw new Exception('Version de QR code non supportée');
        }
        
        // Vérifier l'expiration
        if (isset($decodedData['expires'])) {
            $expiresDate = new DateTime($decodedData['expires']);
            $now = new DateTime();
            if ($now > $expiresDate) {
                throw new Exception('Ce QR code a expiré');
            }
        }
        
        // Récupérer les informations de l'élève depuis la base de données
        $studentId = $decodedData['student']['id'];
        $query = "SELECT e.*, ec.nom_ecole as ecole_nom, ec.adresse as ecole_adresse, 
                         ec.directeur_nom, ec.type_etablissement,
                         i.date_inscription, i.annee_scolaire, i.statut_inscription,
                         c.nom_classe as classe_nom, c.niveau, c.niveau_detaille, c.cycle, c.option_section
                  FROM eleves e
                  JOIN ecoles ec ON e.ecole_id = ec.id
                  LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut_inscription IN ('validée', 'en_cours')
                  LEFT JOIN classes c ON i.classe_id = c.id
                  WHERE e.id = :student_id AND e.ecole_id = :ecole_id
                  ORDER BY i.created_at DESC
                  LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            'student_id' => $studentId,
            'ecole_id' => $_SESSION['ecole_id']
        ]);
        
        $studentInfo = $stmt->fetch();
        
        if (!$studentInfo) {
            throw new Exception('Élève non trouvé ou non autorisé');
        }
        
        // Vérifier la cohérence des données
        if ($studentInfo['matricule'] !== $decodedData['student']['matricule']) {
            throw new Exception('Données incohérentes - Matricule ne correspond pas');
        }
        
        if ($studentInfo['ecole_id'] != $decodedData['student']['ecole_id']) {
            throw new Exception('Données incohérentes - École ne correspond pas');
        }
        
        // Calculer l'âge
        $age = date_diff(date_create($studentInfo['date_naissance']), date_create('today'))->y;
        
        $verificationResult = [
            'success' => true,
            'student' => $studentInfo,
            'age' => $age,
            'qr_data' => $decodedData,
            'verified_at' => date('Y-m-d H:i:s'),
            'verified_by' => $_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']
        ];
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        $verificationResult = [
            'success' => false,
            'error' => $errorMessage,
            'verified_at' => date('Y-m-d H:i:s')
        ];
    }
}

$page_title = "Vérification QR Code - Carte d'Élève";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Naklass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Montserrat', sans-serif;
        }
        
        .verification-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            margin: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .qr-scanner-area {
            border: 3px dashed #007BFF;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: rgba(0, 123, 255, 0.05);
            margin: 20px 0;
            transition: all 0.3s ease;
        }
        
        .qr-scanner-area:hover {
            border-color: #0056b3;
            background: rgba(0, 123, 255, 0.1);
        }
        
        .student-card-preview {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 2px solid #e9ecef;
            margin: 20px 0;
        }
        
        .verification-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .verification-error {
            background: linear-gradient(135deg, #dc3545, #e83e8c);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .student-photo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #007BFF;
        }
        
        .qr-code-display {
            width: 100px;
            height: 100px;
            border: 2px solid #007BFF;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            margin: 0 auto;
        }
        
        .btn-modern {
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success-modern {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn-danger-modern {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            color: white;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .info-value {
            color: #007BFF;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-valid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-invalid {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-expired {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verification-container">
            <div class="text-center mb-4">
                <h1 class="display-6 fw-bold text-primary">
                    <i class="bi bi-qr-code-scan me-3"></i>
                    Vérification QR Code
                </h1>
                <p class="lead text-muted">
                    Scannez ou saisissez le QR code d'une carte d'élève pour vérifier son authenticité
                </p>
            </div>
            
            <!-- Zone de saisie du QR code -->
            <div class="qr-scanner-area">
                <i class="bi bi-qr-code-scan display-1 text-primary mb-3"></i>
                <h4 class="mb-3">Scanner le QR Code</h4>
                <p class="text-muted mb-4">
                    Utilisez un scanner QR ou collez les données du QR code ci-dessous
                </p>
                
                <form method="POST" class="row g-3">
                    <div class="col-12">
                        <label for="qr_data" class="form-label">Données du QR Code</label>
                        <textarea 
                            class="form-control" 
                            id="qr_data" 
                            name="qr_data" 
                            rows="4" 
                            placeholder="Collez ici les données JSON du QR code..."
                            required
                        ></textarea>
                        <div class="form-text">
                            Les données du QR code doivent être au format JSON
                        </div>
                    </div>
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-modern btn-primary-modern">
                            <i class="bi bi-search me-2"></i>
                            Vérifier le QR Code
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Résultat de la vérification -->
            <?php if ($verificationResult): ?>
                <?php if ($verificationResult['success']): ?>
                    <div class="verification-success">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-check-circle-fill me-3" style="font-size: 2rem;"></i>
                            <div>
                                <h4 class="mb-1">QR Code Valide</h4>
                                <p class="mb-0">Carte d'élève authentifiée avec succès</p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="info-item">
                                    <span class="info-label">Vérifié le :</span>
                                    <span class="info-value"><?php echo $verificationResult['verified_at']; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Vérifié par :</span>
                                    <span class="info-value"><?php echo $verificationResult['verified_by']; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Statut :</span>
                                    <span class="status-badge status-valid">Valide</span>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="qr-code-display">
                                    <i class="bi bi-qr-code text-primary" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informations de l'élève -->
                    <div class="student-card-preview">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <?php 
                                $photo_path = getStudentCardPhotoPath($verificationResult['student']);
                                ?>
                                <img src="<?php echo $photo_path; ?>" alt="Photo Élève" class="student-photo mb-3">
                            </div>
                            <div class="col-md-9">
                                <h3 class="mb-3">
                                    <?php echo htmlspecialchars($verificationResult['student']['prenom'] . ' ' . $verificationResult['student']['nom']); ?>
                                </h3>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">Matricule :</span>
                                            <span class="info-value"><?php echo htmlspecialchars($verificationResult['student']['matricule']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Sexe :</span>
                                            <span class="info-value">
                                                <?php echo $verificationResult['student']['sexe'] == 'M' ? 'Masculin' : 'Féminin'; ?>
                                            </span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Âge :</span>
                                            <span class="info-value"><?php echo $verificationResult['age']; ?> ans</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <span class="info-label">Classe :</span>
                                            <span class="info-value"><?php echo htmlspecialchars($verificationResult['student']['classe_nom'] ?? 'Non définie'); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Année scolaire :</span>
                                            <span class="info-value"><?php echo htmlspecialchars($verificationResult['student']['annee_scolaire'] ?? 'Non définie'); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Statut :</span>
                                            <span class="info-value"><?php echo ucfirst($verificationResult['student']['statut_scolaire'] ?? 'Actif'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 p-3 bg-light rounded">
                                    <h6 class="mb-2">Informations de l'établissement</h6>
                                    <div class="info-item">
                                        <span class="info-label">École :</span>
                                        <span class="info-value"><?php echo htmlspecialchars($verificationResult['student']['ecole_nom']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Directeur :</span>
                                        <span class="info-value"><?php echo htmlspecialchars($verificationResult['student']['directeur_nom'] ?? 'Non spécifié'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="verification-error">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 2rem;"></i>
                            <div>
                                <h4 class="mb-1">QR Code Invalide</h4>
                                <p class="mb-0">Impossible de vérifier ce QR code</p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="info-item">
                                    <span class="info-label">Erreur :</span>
                                    <span class="info-value"><?php echo htmlspecialchars($verificationResult['error']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Vérifié le :</span>
                                    <span class="info-value"><?php echo $verificationResult['verified_at']; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Statut :</span>
                                    <span class="status-badge status-invalid">Invalide</span>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="qr-code-display">
                                    <i class="bi bi-x-circle text-danger" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Actions -->
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-modern btn-primary-modern me-3">
                    <i class="bi bi-arrow-left me-2"></i>
                    Retour aux Élèves
                </a>
                <button onclick="clearForm()" class="btn btn-modern btn-success-modern">
                    <i class="bi bi-arrow-clockwise me-2"></i>
                    Nouvelle Vérification
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function clearForm() {
            document.getElementById('qr_data').value = '';
            location.reload();
        }
        
        // Auto-focus sur le textarea
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('qr_data').focus();
        });
    </script>
</body>
</html>

<?php
// Fonction pour obtenir le chemin de la photo (réutilisée depuis generate_card.php)
function getStudentCardPhotoPath($eleve) {
    // Inclure la configuration des photos
    require_once '../config/photo_config.php';
    
    // Si l'élève a une photo dans la base de données
    if (!empty($eleve['photo_path'])) {
        // Vérifier si le photo_path contient déjà un chemin complet
        if (strpos($eleve['photo_path'], 'uploads/') === 0) {
            // Le chemin est déjà complet, ajouter juste ../
            $photo_path = '../' . $eleve['photo_path'];
            if (file_exists($photo_path)) {
                return $photo_path;
            }
        } else {
            // Le chemin est juste le nom du fichier, construire le chemin complet
            $photo_path = 'uploads/students/photos/' . $eleve['photo_path'];
            if (file_exists($photo_path)) {
                return $photo_path;
            }
            
            // Essayer le chemin avec PHOTO_CONFIG
            $photo_path = '../' . PHOTO_CONFIG['UPLOAD_DIR'] . $eleve['photo_path'];
            if (file_exists($photo_path)) {
                return $photo_path;
            }
            
            // Essayer le chemin direct dans uploads/students/photos
            $photo_path = '../uploads/students/photos/' . $eleve['photo_path'];
            if (file_exists($photo_path)) {
                return $photo_path;
            }
            
            // Essayer le chemin direct dans uploads/students
            $photo_path = '../uploads/students/' . $eleve['photo_path'];
            if (file_exists($photo_path)) {
                return $photo_path;
            }
        }
    }
    
    // Essayer les anciens chemins pour compatibilité
    $photo_paths = [
        '../uploads/students/photos/' . $eleve['id'] . '.jpg',
        '../uploads/students/photos/' . $eleve['id'] . '.png',
        '../uploads/students/photos/' . $eleve['id'] . '.jpeg',
        '../uploads/students/photos/' . $eleve['matricule'] . '.jpg',
        '../uploads/students/photos/' . $eleve['matricule'] . '.png',
        '../uploads/students/photos/' . $eleve['matricule'] . '.jpeg',
        '../uploads/students/' . $eleve['id'] . '.jpg',
        '../uploads/students/' . $eleve['id'] . '.png',
        '../uploads/students/' . $eleve['id'] . '.jpeg',
        '../uploads/students/' . $eleve['matricule'] . '.jpg',
        '../uploads/students/' . $eleve['matricule'] . '.png',
        '../uploads/students/' . $eleve['matricule'] . '.jpeg'
    ];
    
    foreach ($photo_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Photo par défaut avec l'initiale de l'élève
    $initials = strtoupper(substr($eleve['prenom'], 0, 1) . substr($eleve['nom'], 0, 1));
    return 'https://ui-avatars.com/api/?name=' . urlencode($initials) . '&background=007BFF&color=fff&size=200&bold=true';
}
?>
