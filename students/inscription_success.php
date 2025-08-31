<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier les paramètres
if (!isset($_GET['id']) || !isset($_GET['matricule'])) {
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();

try {
    // Récupérer les informations de l'élève inscrit
    $query = "SELECT e.*, ec.nom as ecole_nom 
              FROM eleves e
              JOIN ecoles ec ON e.ecole_id = ec.id
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
    
    // Calculer l'âge
    $age = date_diff(date_create($eleve['date_naissance']), date_create('today'))->y;
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération des données.');
    redirect('index.php');
}

$page_title = "Inscription Réussie";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <style>
        /* Variables CSS pour la cohérence */
        :root {
            --primary-green: #10b981;
            --primary-blue: #3b82f6;
            --gradient-success: linear-gradient(135deg,rgb(11, 136, 209) 0%,rgb(22, 71, 205) 100%);
            --gradient-info: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        body {
            background: linear-gradient(135deg,rgb(242, 242, 245) 0%,rgb(255, 255, 255) 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .main-content {
            background: transparent;
        }

        .content-area {
            position: relative;
            z-index: 2;
        }

        /* Particules flottantes en arrière-plan */
        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .particle:nth-child(1) { width: 20px; height: 20px; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 15px; height: 15px; left: 20%; animation-delay: 1s; }
        .particle:nth-child(3) { width: 25px; height: 25px; left: 30%; animation-delay: 2s; }
        .particle:nth-child(4) { width: 18px; height: 18px; left: 40%; animation-delay: 3s; }
        .particle:nth-child(5) { width: 22px; height: 22px; left: 50%; animation-delay: 4s; }
        .particle:nth-child(6) { width: 16px; height: 16px; left: 60%; animation-delay: 5s; }
        .particle:nth-child(7) { width: 24px; height: 24px; left: 70%; animation-delay: 2.5s; }
        .particle:nth-child(8) { width: 19px; height: 19px; left: 80%; animation-delay: 1.5s; }
        .particle:nth-child(9) { width: 21px; height: 21px; left: 90%; animation-delay: 3.5s; }

        @keyframes float {
            0%, 100% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10%, 90% { opacity: 1; }
            50% { transform: translateY(-10vh) rotate(180deg); }
        }

        /* Card de succès principal avec animation */
        .success-hero {
            background: var(--gradient-success);
            color: white;
            border-radius: 25px;
            box-shadow: var(--shadow-xl);
            border: none;
            position: relative;
            overflow: hidden;
            animation: slideInUp 1s ease-out;
        }

        .success-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .celebration-container {
            position: relative;
            margin-bottom: 2rem;
        }

        .celebration-icon {
            font-size: 6rem;
            color: rgba(255, 255, 255, 0.9);
            animation: celebrationPulse 2s ease-in-out infinite;
            position: relative;
            z-index: 2;
        }

        @keyframes celebrationPulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            25% { transform: scale(1.1) rotate(-5deg); }
            75% { transform: scale(1.1) rotate(5deg); }
        }

        /* Effet de cercles concentriques */
        .celebration-ripple {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100px;
            height: 100px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            animation: ripple 2s infinite;
        }

        .celebration-ripple:nth-child(2) {
            animation-delay: 0.5s;
        }

        .celebration-ripple:nth-child(3) {
            animation-delay: 1s;
        }

        @keyframes ripple {
            0% { transform: translate(-50%, -50%) scale(0); opacity: 1; }
            100% { transform: translate(-50%, -50%) scale(3); opacity: 0; }
        }

        /* Matricule avec effet néon */
        .matricule-display {
            font-size: 2.5rem;
            font-weight: 900;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 20px;
            text-align: center;
            margin: 2rem 0;
            border: 2px solid rgba(255, 255, 255, 0.3);
            position: relative;
            animation: glowPulse 3s ease-in-out infinite;
        }

        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 0 20px rgba(255, 255, 255, 0.3); }
            50% { box-shadow: 0 0 40px rgba(255, 255, 255, 0.6), 0 0 60px rgba(255, 255, 255, 0.4); }
        }

        /* Cards améliorées */
        .enhanced-card {
            border: none;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
            animation: slideInUp 1s ease-out;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .enhanced-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .student-summary {
            background: var(--gradient-info);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .student-summary::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 10s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .student-info-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .student-info-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.02);
        }

        /* Section des prochaines étapes */
        .next-steps-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: none;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
        }

        .step-item {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-green);
        }

        .step-item:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .step-number {
            width: 35px;
            height: 35px;
            background: var(--gradient-success);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }

        /* Boutons améliorés */
        .enhanced-btn {
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .enhanced-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .enhanced-btn:hover::before {
            left: 100%;
        }

        .enhanced-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-primary-enhanced {
            background: var(--gradient-info);
            color: white;
        }

        .btn-success-enhanced {
            background: var(--gradient-success);
            color: white;
        }

        /* Alertes améliorées */
        .enhanced-alert {
            border: none;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .enhanced-alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: currentColor;
        }

        /* Animations d'entrée */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-delay-1 { animation-delay: 0.2s; animation-fill-mode: both; }
        .animate-delay-2 { animation-delay: 0.4s; animation-fill-mode: both; }
        .animate-delay-3 { animation-delay: 0.6s; animation-fill-mode: both; }
        .animate-delay-4 { animation-delay: 0.8s; animation-fill-mode: both; }

        /* Confetti CSS */
        .confetti {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1000;
        }

        .confetti-piece {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #f59e0b;
            animation: confettiFall 3s linear infinite;
        }

        @keyframes confettiFall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .celebration-icon {
                font-size: 4rem;
            }
            
            .matricule-display {
                font-size: 1.8rem;
                padding: 1rem;
            }

            .enhanced-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation latérale -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Particules flottantes en arrière-plan -->
    <div class="floating-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- Contenu principal -->
    <main class="main-content">
        <!-- Barre supérieure -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-check-circle-fill me-2 text-success"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">L'élève a été inscrit avec succès dans votre établissement</p>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Message de succès principal -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-8">
                    <div class="success-hero card">
                        <div class="card-body text-center p-5">
                            <div class="celebration-container">
                                <div class="celebration-ripple"></div>
                                <div class="celebration-ripple"></div>
                                <div class="celebration-ripple"></div>
                                <div class="celebration-icon">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            </div>
                            
                            <h2 class="mb-3">🎉 Inscription Réussie !</h2>
                            <p class="lead mb-4">
                                L'élève <strong><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></strong> 
                                a été inscrit avec succès dans votre établissement.
                            </p>
                            
                            <!-- Affichage du matricule généré avec effet néon -->
                            <div class="matricule-display">
                                <div class="small mb-2 opacity-75">✨ Numéro Matricule Généré ✨</div>
                                <div><?php echo htmlspecialchars($eleve['matricule']); ?></div>
                            </div>
                            
                            <p class="mb-0 opacity-90">
                                <i class="bi bi-calendar-check me-2"></i>
                                Date d'inscription : <?php echo date('d/m/Y H:i'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informations de l'élève -->
            <div class="row animate-delay-1">
                <div class="col-lg-6 mb-4">
                    <div class="enhanced-card card h-100">
                        <div class="card-header border-0 bg-transparent">
                            <h5 class="mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>Résumé de l'Élève</h5>
                        </div>
                        <div class="card-body">
                            <div class="student-summary">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="bg-white bg-opacity-20 rounded-circle p-3">
                                            <i class="bi bi-person-fill fs-2"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="mb-1"><?php echo htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']); ?></h4>
                                        <?php if ($eleve['postnom']): ?>
                                            <p class="mb-0 opacity-75"><?php echo htmlspecialchars($eleve['postnom']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="student-info-card">
                                            <small class="opacity-75">📋 Matricule</small>
                                            <div class="fw-bold"><?php echo htmlspecialchars($eleve['matricule']); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="student-info-card">
                                            <small class="opacity-75">🎂 Âge</small>
                                            <div class="fw-bold"><?php echo $age; ?> ans</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="student-info-card">
                                            <small class="opacity-75">👤 Sexe</small>
                                            <div class="fw-bold"><?php echo $eleve['sexe'] == 'M' ? '🧑 Masculin' : ($eleve['sexe'] == 'F' ? '👩 Féminin' : $eleve['sexe']); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="student-info-card">
                                            <small class="opacity-75">📅 Naissance</small>
                                            <div class="fw-bold"><?php echo date('d/m/Y', strtotime($eleve['date_naissance'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div class="enhanced-card next-steps-card card h-100">
                        <div class="card-header border-0 bg-transparent">
                            <h5 class="mb-0"><i class="bi bi-list-check me-2 text-success"></i>Prochaines Étapes</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="text-primary mb-3"><i class="bi bi-lightbulb me-2"></i>Actions recommandées</h6>
                            
                            <div class="step-item d-flex align-items-center">
                                <div class="step-number">1</div>
                                <div>
                                    <div class="fw-semibold">Générer la carte d'élève</div>
                                    <small class="text-muted">Créer une carte avec QR code</small>
                                </div>
                            </div>
                            
                            <div class="step-item d-flex align-items-center">
                                <div class="step-number">2</div>
                                <div>
                                    <div class="fw-semibold">Assigner à une classe</div>
                                    <small class="text-muted">Choisir la classe appropriée</small>
                                </div>
                            </div>
                            
                            <div class="step-item d-flex align-items-center">
                                <div class="step-number">3</div>
                                <div>
                                    <div class="fw-semibold">Configurer les frais</div>
                                    <small class="text-muted">Définir la grille tarifaire</small>
                                </div>
                            </div>
                            
                            <div class="step-item d-flex align-items-center">
                                <div class="step-number">4</div>
                                <div>
                                    <div class="fw-semibold">Vérifier les tuteurs</div>
                                    <small class="text-muted">Contrôler les contacts</small>
                                </div>
                            </div>
                            
                            <!-- Actions rapides -->
                            <div class="d-grid gap-2 mt-4">
                                <a href="generate_card.php?id=<?php echo $eleve['id']; ?>" class="enhanced-btn btn-primary-enhanced">
                                    <i class="bi bi-card-heading me-2"></i>Générer la Carte d'Élève
                                </a>
                                
                                <a href="view.php?id=<?php echo $eleve['id']; ?>" class="enhanced-btn btn btn-outline-primary">
                                    <i class="bi bi-eye me-2"></i>Voir le Profil Complet
                                </a>
                                
                                <a href="<?php echo createSecureLink('../classes/assign.php', $eleve['id'], 'student_id'); ?>" class="enhanced-btn btn-success-enhanced">
                                    <i class="bi bi-building me-2"></i>Assigner à une Classe
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informations supplémentaires -->
            <div class="row animate-delay-2">
                <div class="col-12">
                    <div class="enhanced-card card">
                        <div class="card-header border-0 bg-transparent">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2 text-info"></i>Informations Importantes</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="enhanced-alert alert alert-info">
                                        <h6><i class="bi bi-shield-check me-2"></i>🔒 Matricule Sécurisé</h6>
                                        <p class="mb-0 small">
                                            Le numéro matricule <strong class="text-primary"><?php echo htmlspecialchars($eleve['matricule']); ?></strong> 
                                            a été généré automatiquement et est unique dans votre établissement.
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <div class="enhanced-alert alert alert-warning">
                                        <h6><i class="bi bi-exclamation-triangle me-2"></i>💳 Carte d'Élève</h6>
                                        <p class="mb-0 small">
                                            N'oubliez pas de générer et d'imprimer la carte d'élève. 
                                            Elle contient un QR code pour l'identification rapide.
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <div class="enhanced-alert alert alert-success">
                                        <h6><i class="bi bi-calendar-plus me-2"></i>📚 Année Académique</h6>
                                        <p class="mb-0 small">
                                            L'élève est inscrit pour l'année académique 
                                            <strong><?php echo date('Y'); ?>-<?php echo date('Y') + 1; ?></strong>.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions finales -->
            <div class="row mt-4 animate-delay-3">
                <div class="col-12">
                    <div class="enhanced-card card">
                        <div class="card-body">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center text-center text-md-start">
                                <div class="mb-3 mb-md-0">
                                    <h6 class="mb-1 text-success">✅ Inscription terminée avec succès !</h6>
                                    <small class="text-muted">
                                        Vous pouvez maintenant procéder aux étapes suivantes ou inscrire un autre élève.
                                    </small>
                                </div>
                                <div class="d-flex flex-column flex-sm-row gap-2">
                                    <a href="add.php" class="enhanced-btn btn btn-outline-primary">
                                        <i class="bi bi-person-plus me-2"></i>Inscrire un Autre Élève
                                    </a>
                                    <a href="index.php" class="enhanced-btn btn btn-secondary">
                                        <i class="bi bi-list me-2"></i>Liste des Élèves
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Animation de célébration avancée
        document.addEventListener('DOMContentLoaded', function() {
            // Créer l'effet confetti
            createConfetti();
            
            // Animer les éléments d'entrée
            animateElements();
            
            // Son de succès (si supporté)
            playSuccessSound();
            
            // Message de bienvenue après 2 secondes
            setTimeout(() => {
                showWelcomeMessage();
            }, 2000);
        });

        function createConfetti() {
            const confettiContainer = document.createElement('div');
            confettiContainer.className = 'confetti';
            document.body.appendChild(confettiContainer);

            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#f0932b', '#eb4d4b', '#6c5ce7'];
            
            for (let i = 0; i < 50; i++) {
                const confettiPiece = document.createElement('div');
                confettiPiece.className = 'confetti-piece';
                confettiPiece.style.left = Math.random() * 100 + '%';
                confettiPiece.style.background = colors[Math.floor(Math.random() * colors.length)];
                confettiPiece.style.animationDelay = Math.random() * 3 + 's';
                confettiPiece.style.animationDuration = (Math.random() * 3 + 2) + 's';
                confettiContainer.appendChild(confettiPiece);
            }

            // Supprimer le confetti après 5 secondes
            setTimeout(() => {
                confettiContainer.remove();
            }, 5000);
        }

        function animateElements() {
            // Animer les cartes avec un délai progressif
            const cards = document.querySelectorAll('.enhanced-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.2) + 's';
                card.classList.add('animate-delay-' + (index + 1));
            });

            // Effet de typewriter pour le matricule
            const matriculeElement = document.querySelector('.matricule-display div:last-child');
            if (matriculeElement) {
                const matriculeText = matriculeElement.textContent;
                matriculeElement.textContent = '';
                let i = 0;
                const typeInterval = setInterval(() => {
                    matriculeElement.textContent += matriculeText[i];
                    i++;
                    if (i >= matriculeText.length) {
                        clearInterval(typeInterval);
                    }
                }, 100);
            }
        }

        function playSuccessSound() {
            // Créer un son de succès simple avec Web Audio API
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.setValueAtTime(523.25, audioContext.currentTime); // Do
                oscillator.frequency.setValueAtTime(659.25, audioContext.currentTime + 0.1); // Mi
                oscillator.frequency.setValueAtTime(783.99, audioContext.currentTime + 0.2); // Sol
                
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.5);
            } catch (e) {
                // Silently fail if Web Audio API is not supported
                console.log('Web Audio API not supported');
            }
        }

        function showWelcomeMessage() {
            // Créer une notification toast de bienvenue
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 p-3';
            toast.style.zIndex = '1060';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header bg-success text-white">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong class="me-auto">Naklass</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        🎉 Bienvenue dans la famille Naklass ! L'inscription s'est déroulée parfaitement.
                    </div>
                </div>
            `;
            document.body.appendChild(toast);

            // Supprimer le toast après 5 secondes
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        // Effet parallax léger pour les particules
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const particles = document.querySelectorAll('.particle');
            particles.forEach((particle, index) => {
                const speed = 0.5 + (index * 0.1);
                particle.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });

        // Interaction avec les boutons
        document.querySelectorAll('.enhanced-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Animation des step items au hover
        document.querySelectorAll('.step-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.background = 'linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%)';
                this.style.borderLeft = '4px solid #10b981';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.background = 'white';
                this.style.borderLeft = '4px solid var(--primary-green)';
            });
        });
    </script>
</body>
</html>
