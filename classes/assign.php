<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction', 'secretaire']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

// Récupérer et valider l'ID de l'élève (chiffré)
$student_id = validateSecureId('student_id', 'eleves', ['ecole_id' => $_SESSION['ecole_id']]);

if (!$student_id) {
    setFlashMessage('error', 'Élève introuvable ou accès non autorisé.');
    redirect('../students/index.php');
}

// Récupérer les informations de l'élève
try {
    $student_query = "SELECT e.*, 
                             COALESCE(ic.classe_id, 0) as classe_actuelle_id,
                             c.nom as classe_actuelle_nom,
                             ic.statut as inscription_statut,
                             ic.date_inscription as date_inscription_classe
                      FROM eleves e
                      LEFT JOIN inscriptions ic ON e.id = ic.eleve_id AND ic.statut = 'validée'
                      LEFT JOIN classes c ON ic.classe_id = c.id
                      WHERE e.id = :student_id AND e.ecole_id = :ecole_id";
    
    $stmt = $db->prepare($student_query);
    $stmt->execute([
        'student_id' => $student_id,
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    
    $student = $stmt->fetch();
    
    if (!$student) {
        setFlashMessage('error', 'Élève introuvable.');
        redirect('../students/index.php');
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Erreur lors de la récupération des données de l\'élève.');
    redirect('../students/index.php');
}

// Récupérer toutes les classes disponibles
try {
    $classes_query = "SELECT c.*, 
                             COUNT(i.eleve_id) as nombre_eleves,
                             c.capacite_max,
                             n.nom as niveau_nom,
                             s.nom as section_nom
                      FROM classes c
                      LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut = 'validée'
                      LEFT JOIN niveaux n ON c.niveau_id = n.id
                      LEFT JOIN sections s ON c.section_id = s.id
                      WHERE c.ecole_id = :ecole_id AND c.statut = 'active'
                      GROUP BY c.id
                      ORDER BY n.ordre ASC, c.nom_classe ASC";
    
    $stmt = $db->prepare($classes_query);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $classes = $stmt->fetchAll();
    
} catch (Exception $e) {
    $classes = [];
    $errors[] = "Erreur lors de la récupération des classes: " . $e->getMessage();
}

// Traitement du formulaire d'assignation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_class'])) {
    $nouvelle_classe_id = intval($_POST['classe_id'] ?? 0);
    $notes_assignation = sanitize($_POST['notes'] ?? '');
    
    if ($nouvelle_classe_id <= 0) {
        $errors[] = "Veuillez sélectionner une classe valide.";
    }
    
    // Vérifier que la classe existe et appartient à l'école
    if ($nouvelle_classe_id > 0) {
        $class_check_query = "SELECT id, nom, capacite_max FROM classes WHERE id = :classe_id AND ecole_id = :ecole_id AND statut = 'active'";
        $stmt = $db->prepare($class_check_query);
        $stmt->execute(['classe_id' => $nouvelle_classe_id, 'ecole_id' => $_SESSION['ecole_id']]);
        $classe_info = $stmt->fetch();
        
        if (!$classe_info) {
            $errors[] = "Classe sélectionnée invalide.";
        } else {
            // Vérifier la capacité de la classe
            $count_query = "SELECT COUNT(*) as nb FROM inscriptions WHERE classe_id = :classe_id AND statut = 'validée'";
            $stmt = $db->prepare($count_query);
            $stmt->execute(['classe_id' => $nouvelle_classe_id]);
            $current_count = $stmt->fetch()['nb'];
            
            if ($classe_info['capacite_max'] > 0 && $current_count >= $classe_info['capacite_max']) {
                $errors[] = "La classe sélectionnée a atteint sa capacité maximale ({$classe_info['capacite_max']} élèves).";
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Si l'élève est déjà dans une classe, désactiver l'ancienne inscription
            if ($student['classe_actuelle_id'] > 0) {
                $deactivate_query = "UPDATE inscriptions 
                                   SET statut = 'annulée', 
                                       date_fin = CURRENT_DATE,
                                       notes = CONCAT(COALESCE(notes, ''), ' - Désinscrit le ', NOW(), ' pour réassignation')
                                   WHERE eleve_id = :student_id AND classe_id = :classe_id AND statut = 'validée'";
                
                $stmt = $db->prepare($deactivate_query);
                $stmt->execute([
                    'student_id' => $student_id,
                    'classe_id' => $student['classe_actuelle_id']
                ]);
            }
            
            // Créer la nouvelle inscription
            $assign_query = "INSERT INTO inscriptions (
                                eleve_id, classe_id, date_inscription, statut, notes, created_by, created_at
                             ) VALUES (
                                :eleve_id, :classe_id, CURRENT_DATE, 'validée', :notes, :created_by, NOW()
                             )";
            
            $stmt = $db->prepare($assign_query);
            $stmt->execute([
                'eleve_id' => $student_id,
                'classe_id' => $nouvelle_classe_id,
                'notes' => $notes_assignation,
                'created_by' => $_SESSION['user_id']
            ]);
            
            $db->commit();
            
            // Log de l'action
            logUserAction('ASSIGN_STUDENT_TO_CLASS', "Assignation de l'élève {$student['prenom']} {$student['nom']} (ID: $student_id) à la classe ID: $nouvelle_classe_id");
            
            setFlashMessage('success', "L'élève {$student['prenom']} {$student['nom']} a été assigné avec succès à la classe {$classe_info['nom']}.");
            
            // Redirection avec ID chiffré
            redirect(createSecureLink('assign.php', $student_id, 'student_id'));
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Erreur lors de l'assignation: " . $e->getMessage();
        }
    }
}

$page_title = "Assigner à une Classe";
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
        .student-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        
        .class-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        
        .class-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .class-card.selected {
            border: 2px solid #0d6efd;
            background-color: #f8f9ff;
        }
        
        .capacity-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .capacity-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .capacity-low { background: #28a745; }
        .capacity-medium { background: #ffc107; }
        .capacity-high { background: #dc3545; }
        
        .current-class {
            background: #d1e7dd;
            border: 2px solid #0f5132;
        }
    </style>
</head>
<body>
    <!-- Navigation latérale -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Contenu principal -->
    <main class="main-content">
        <!-- Barre supérieure -->
        <header class="topbar">
            <button class="sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-title">
                <h1><i class="bi bi-diagram-3 me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Assigner l'élève à une classe pour l'année scolaire en cours</p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../auth/dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="../students/index.php">Élèves</a></li>
                        <li class="breadcrumb-item active">Assigner à une classe</li>
                    </ol>
                </nav>
            </div>
            
            <div class="topbar-actions">
                <a href="../students/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour aux élèves
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Affichage des erreurs -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Affichage des messages flash -->
            <?php
            $flash_messages = getFlashMessages();
            foreach ($flash_messages as $type => $message):
            ?>
                <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            
            <div class="row">
                <!-- Informations de l'élève -->
                <div class="col-lg-4 mb-4">
                    <div class="student-card card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0 me-3">
                                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                                        <i class="bi bi-person-fill fs-2"></i>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-white mb-1"><?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?></h4>
                                    <p class="text-white-50 mb-0">Matricule: <?php echo htmlspecialchars($student['matricule']); ?></p>
                                </div>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="bg-white bg-opacity-10 rounded p-2">
                                        <small class="text-white-50">Âge</small>
                                        <div class="text-white fw-bold">
                                            <?php 
                                            $age = date_diff(date_create($student['date_naissance']), date_create('today'))->y;
                                            echo $age; 
                                            ?> ans
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-white bg-opacity-10 rounded p-2">
                                        <small class="text-white-50">Sexe</small>
                                        <div class="text-white fw-bold">
                                            <?php echo $student['sexe'] == 'M' ? 'Masculin' : ($student['sexe'] == 'F' ? 'Féminin' : $student['sexe']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="bg-white bg-opacity-10 rounded p-2">
                                        <small class="text-white-50">Classe actuelle</small>
                                        <div class="text-white fw-bold">
                                            <?php if ($student['classe_actuelle_id'] > 0): ?>
                                                <i class="bi bi-check-circle me-1"></i>
                                                <?php echo htmlspecialchars($student['classe_actuelle_nom']); ?>
                                                <br>
                                                <small class="text-white-50">
                                                    Inscrit le <?php echo date('d/m/Y', strtotime($student['date_inscription_classe'])); ?>
                                                </small>
                                            <?php else: ?>
                                                <i class="bi bi-exclamation-circle me-1"></i>
                                                Aucune classe assignée
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions rapides -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5><i class="bi bi-lightning me-2"></i>Actions rapides</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?php echo createSecureLink('../students/generate_card.php', $student_id, 'id'); ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-card-heading me-2"></i>Générer Carte d'Élève
                                </a>
                                <a href="<?php echo createSecureLink('../students/view.php', $student_id, 'id'); ?>" class="btn btn-outline-info">
                                    <i class="bi bi-eye me-2"></i>Voir Profil Complet
                                </a>
                                <a href="../finance/student_fees.php?student_id=<?php echo encryptId($student_id); ?>" class="btn btn-outline-success">
                                    <i class="bi bi-cash-coin me-2"></i>Gérer Frais
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sélection de classe -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-building me-2"></i>Sélectionner une Classe</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($classes)): ?>
                                <div class="text-center p-4">
                                    <i class="bi bi-building display-1 text-muted mb-3"></i>
                                    <h5>Aucune classe disponible</h5>
                                    <p class="text-muted">Il n'y a actuellement aucune classe active dans votre établissement.</p>
                                    <a href="create.php" class="btn btn-primary">
                                        <i class="bi bi-plus me-2"></i>Créer une classe
                                    </a>
                                </div>
                            <?php else: ?>
                                <form method="POST" id="assignForm">
                                    <div class="row g-3">
                                        <?php foreach ($classes as $classe): ?>
                                            <?php
                                            $capacity_percentage = $classe['capacite_max'] > 0 ? ($classe['nombre_eleves'] / $classe['capacite_max']) * 100 : 0;
                                            $capacity_class = 'capacity-low';
                                            if ($capacity_percentage >= 80) $capacity_class = 'capacity-high';
                                            elseif ($capacity_percentage >= 60) $capacity_class = 'capacity-medium';
                                            
                                            $is_current = ($classe['id'] == $student['classe_actuelle_id']);
                                            $is_full = $classe['capacite_max'] > 0 && $classe['nombre_eleves'] >= $classe['capacite_max'];
                                            ?>
                                            
                                            <div class="col-md-6 col-lg-4">
                                                <div class="class-card card h-100 <?php echo $is_current ? 'current-class' : ''; ?>" 
                                                     data-class-id="<?php echo $classe['id']; ?>"
                                                     <?php echo $is_full && !$is_current ? 'data-disabled="true"' : ''; ?>>
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="card-title mb-0">
                                                                <?php echo htmlspecialchars($classe['nom']); ?>
                                                                <?php if ($is_current): ?>
                                                                    <span class="badge bg-success ms-1">Actuelle</span>
                                                                <?php endif; ?>
                                                                <?php if ($is_full && !$is_current): ?>
                                                                    <span class="badge bg-danger ms-1">Complète</span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <input type="radio" 
                                                                   name="classe_id" 
                                                                   value="<?php echo $classe['id']; ?>"
                                                                   class="form-check-input"
                                                                   <?php echo $is_current ? 'checked' : ''; ?>
                                                                   <?php echo $is_full && !$is_current ? 'disabled' : ''; ?>>
                                                        </div>
                                                        
                                                        <?php if ($classe['niveau_nom'] || $classe['section_nom']): ?>
                                                            <p class="card-text small text-muted mb-2">
                                                                <?php echo htmlspecialchars($classe['niveau_nom'] ?? ''); ?>
                                                                <?php if ($classe['niveau_nom'] && $classe['section_nom']) echo ' - '; ?>
                                                                <?php echo htmlspecialchars($classe['section_nom'] ?? ''); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <div class="mb-2">
                                                            <small class="text-muted">
                                                                Élèves: <?php echo $classe['nombre_eleves']; ?>
                                                                <?php if ($classe['capacite_max'] > 0): ?>
                                                                    / <?php echo $classe['capacite_max']; ?>
                                                                <?php endif; ?>
                                                            </small>
                                                            
                                                            <?php if ($classe['capacite_max'] > 0): ?>
                                                                <div class="capacity-bar mt-1">
                                                                    <div class="capacity-fill <?php echo $capacity_class; ?>" 
                                                                         style="width: <?php echo min(100, $capacity_percentage); ?>%"></div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <?php if ($classe['description']): ?>
                                                            <p class="card-text small">
                                                                <?php echo htmlspecialchars(substr($classe['description'], 0, 80)) . (strlen($classe['description']) > 80 ? '...' : ''); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Notes d'assignation -->
                                    <div class="mt-4">
                                        <label for="notes" class="form-label">Notes sur l'assignation (optionnel)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                  placeholder="Ajoutez des notes sur cette assignation..."></textarea>
                                    </div>
                                    
                                    <!-- Boutons d'action -->
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="../students/index.php" class="btn btn-secondary">
                                            <i class="bi bi-arrow-left me-2"></i>Annuler
                                        </a>
                                        
                                        <button type="submit" name="assign_class" class="btn btn-primary" id="assignBtn" disabled>
                                            <i class="bi bi-check-lg me-2"></i>Assigner à la Classe
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const classCards = document.querySelectorAll('.class-card[data-class-id]');
            const assignBtn = document.getElementById('assignBtn');
            const radioButtons = document.querySelectorAll('input[name="classe_id"]');
            
            // Gestion de la sélection de classe
            classCards.forEach(card => {
                if (card.dataset.disabled === 'true') return;
                
                card.addEventListener('click', function() {
                    const classId = this.dataset.classId;
                    const radio = this.querySelector('input[type="radio"]');
                    
                    if (radio && !radio.disabled) {
                        // Décocher tous les autres
                        radioButtons.forEach(r => r.checked = false);
                        document.querySelectorAll('.class-card').forEach(c => c.classList.remove('selected'));
                        
                        // Cocher celui-ci
                        radio.checked = true;
                        this.classList.add('selected');
                        
                        // Activer le bouton d'assignation
                        assignBtn.disabled = false;
                    }
                });
            });
            
            // Gestion directe des radio buttons
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.class-card').forEach(c => c.classList.remove('selected'));
                    
                    if (this.checked) {
                        const card = this.closest('.class-card');
                        if (card) card.classList.add('selected');
                        assignBtn.disabled = false;
                    }
                });
                
                // Vérifier l'état initial
                if (radio.checked) {
                    const card = radio.closest('.class-card');
                    if (card) card.classList.add('selected');
                    assignBtn.disabled = false;
                }
            });
            
            // Validation du formulaire
            document.getElementById('assignForm').addEventListener('submit', function(e) {
                const selectedClass = document.querySelector('input[name="classe_id"]:checked');
                
                if (!selectedClass) {
                    e.preventDefault();
                    alert('Veuillez sélectionner une classe avant de continuer.');
                    return;
                }
                
                // Confirmation pour réassignation
                const currentClassCard = document.querySelector('.class-card.current-class');
                if (currentClassCard && selectedClass.value !== currentClassCard.dataset.classId) {
                    const className = selectedClass.closest('.class-card').querySelector('.card-title').textContent.trim();
                    if (!confirm(`Êtes-vous sûr de vouloir réassigner cet élève à la classe "${className}" ? L'ancienne assignation sera annulée.`)) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>
