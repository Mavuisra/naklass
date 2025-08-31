<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

// Vérifier qu'un ID de matière est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de matière invalide.');
    redirect('index.php');
}

$matiere_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

    // Récupérer les détails de la matière
    try {
        $matiere_query = "SELECT id, ecole_id, code_cours, nom_cours, description, coefficient, 
                                 cycles_applicables, ponderation_defaut, statut, created_at, updated_at, 
                                 created_by, updated_by, version, notes_internes, bareme_max, volume_horaire_hebdo
                          FROM cours WHERE id = :id AND ecole_id = :ecole_id";
        $stmt = $db->prepare($matiere_query);
        $stmt->execute([
            'id' => $matiere_id,
            'ecole_id' => $_SESSION['ecole_id']
        ]);
        $matiere = $stmt->fetch();
        
        if (!$matiere) {
            setFlashMessage('error', 'Matière non trouvée.');
            redirect('index.php');
        }
        
    } catch (Exception $e) {
        setFlashMessage('error', 'Erreur lors de la récupération des données.');
        redirect('index.php');
    }

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $nom_cours = sanitize($_POST['nom_cours'] ?? '');
        $code = sanitize($_POST['code'] ?? '');
        $coefficient = floatval($_POST['coefficient'] ?? 1.0);
        $type_cours = sanitize($_POST['type_cours'] ?? 'tronc_commun');
        $description = sanitize($_POST['description'] ?? '');
        $actif = isset($_POST['actif']) ? 1 : 0;
        
        // Validation des champs obligatoires
        $errors = [];
        
        if (empty($nom_cours)) {
            $errors[] = "Le nom de la matière est obligatoire.";
        }
        
        if (empty($code)) {
            $errors[] = "Le code de la matière est obligatoire.";
        }
        
        if ($coefficient <= 0 || $coefficient > 10) {
            $errors[] = "Le coefficient doit être compris entre 0.1 et 10.";
        }
        
        // Vérifier l'unicité du code (sauf pour la matière actuelle)
        if (empty($errors)) {
            $check_query = "SELECT id FROM cours WHERE code = :code AND ecole_id = :ecole_id AND id != :current_id";
            $stmt = $db->prepare($check_query);
            $stmt->execute([
                'code' => $code,
                'ecole_id' => $_SESSION['ecole_id'],
                'current_id' => $matiere_id
            ]);
            
            if ($stmt->fetch()) {
                $errors[] = "Ce code de matière existe déjà.";
            }
        }
        
        // Traitement de la pondération par défaut (JSON)
        $ponderation_defaut = null;
        if (!empty($_POST['ponderation_json'])) {
            $ponderation_json = $_POST['ponderation_json'];
            $decoded = json_decode($ponderation_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $ponderation_defaut = $ponderation_json;
            } else {
                $errors[] = "Format JSON invalide pour la pondération par défaut.";
            }
        }
        
        if (empty($errors)) {
            // Mettre à jour la matière
            $update_query = "UPDATE cours SET 
                                code_cours = :code,
                                nom_cours = :nom_cours,
                                coefficient = :coefficient,
                                cycles_applicables = :type_cours,
                                ponderation_defaut = :ponderation_defaut,
                                description = :description,
                                statut = :actif,
                                updated_by = :updated_by,
                                updated_at = NOW(),
                                version = version + 1
                             WHERE id = :id AND ecole_id = :ecole_id";
            
            $stmt = $db->prepare($update_query);
            $result = $stmt->execute([
                'code' => $code,
                'nom_cours' => $nom_cours,
                'coefficient' => $coefficient,
                'type_cours' => $type_cours,
                'ponderation_defaut' => $ponderation_defaut,
                'description' => $description,
                'actif' => $actif ? 'actif' : 'archivé',
                'updated_by' => $_SESSION['user_id'],
                'id' => $matiere_id,
                'ecole_id' => $_SESSION['ecole_id']
            ]);
            
            if ($result) {
                setFlashMessage('success', "La matière '{$nom_cours}' a été mise à jour avec succès.");
                redirect("view.php?id={$matiere_id}");
            } else {
                $errors[] = "Erreur lors de la mise à jour de la matière.";
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur lors de la mise à jour de la matière: " . $e->getMessage();
        error_log("Erreur mise à jour matière: " . $e->getMessage());
    }
} else {
    // Pré-remplir les champs avec les données existantes
    $_POST = [
        'nom_cours' => $matiere['nom_cours'],
        'code' => $matiere['code_cours'],
        'coefficient' => $matiere['coefficient'],
        'type_cours' => $matiere['cycles_applicables'],
        'description' => $matiere['description'],
        'ponderation_json' => $matiere['ponderation_defaut'],
        'actif' => $matiere['statut'] === 'actif' ? 1 : 0
    ];
}

$page_title = "Modifier : " . $matiere['nom_cours'];
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
    <link href="../assets/css/naklass-theme.css" rel="stylesheet">
    
    <style>
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 600;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
        }
        
        .coefficient-helper {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            border-left: 4px solid #667eea;
        }
        
        .json-editor {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
        }
        
        .danger-zone {
            background: rgba(220, 53, 69, 0.05);
            border: 1px solid rgba(220, 53, 69, 0.2);
            border-radius: 10px;
            padding: 20px;
        }
        
        .version-info {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 12px;
            border-left: 4px solid #2196f3;
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
                <h1><i class="bi bi-pencil me-2"></i><?php echo $page_title; ?></h1>
                <p class="text-muted">Code: <?php echo htmlspecialchars($matiere['code_cours']); ?></p>
            </div>
            
            <div class="topbar-actions">
                <a href="view.php?id=<?php echo $matiere['id']; ?>" class="btn btn-outline-primary me-2">
                    <i class="bi bi-eye me-2"></i>Voir
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
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
            
            <!-- Informations sur la version -->
            <div class="version-info mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="mb-1"><i class="bi bi-info-circle me-2"></i>Informations de Version</h6>
                        <small>
                            Version actuelle: <strong><?php echo $matiere['version']; ?></strong> • 
                            Dernière modification: <strong><?php echo date('d/m/Y à H:i', strtotime($matiere['updated_at'])); ?></strong>
                        </small>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <span class="badge <?php echo $matiere['statut'] === 'actif' ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $matiere['statut'] === 'actif' ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Formulaire -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="form-container p-4">
                        <form method="POST" novalidate>
                            <!-- Informations de base -->
                            <div class="mb-4">
                                <h5 class="section-title mb-3">
                                    <i class="bi bi-info-circle me-2"></i>Informations de Base
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="nom_cours" class="form-label">
                                                Nom de la Matière <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="nom_cours" name="nom_cours" 
                                                   value="<?php echo htmlspecialchars($_POST['nom_cours'] ?? ''); ?>" 
                                                   placeholder="Ex: Mathématiques, Français, Histoire..." required>
                                            <div class="form-text">Nom complet de la matière tel qu'il apparaîtra dans les bulletins</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="code" class="form-label">
                                                Code <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="code" name="code" 
                                                   value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>" 
                                                   placeholder="Ex: MATH, FR, HIST" 
                                                   style="text-transform: uppercase;" required>
                                            <div class="form-text">Code unique de la matière</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Description optionnelle de la matière..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <div class="form-text">Description détaillée du contenu et des objectifs de la matière</div>
                                </div>
                            </div>
                            
                            <!-- Configuration pédagogique -->
                            <div class="mb-4">
                                <h5 class="section-title mb-3">
                                    <i class="bi bi-gear me-2"></i>Configuration Pédagogique
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="type_cours" class="form-label">Type de Matière</label>
                                            <select class="form-select" id="type_cours" name="type_cours">
                                                <option value="tronc_commun" <?php echo ($_POST['type_cours'] ?? '') === 'tronc_commun' ? 'selected' : ''; ?>>
                                                    Tronc Commun
                                                </option>
                                                <option value="optionnel" <?php echo ($_POST['type_cours'] ?? '') === 'optionnel' ? 'selected' : ''; ?>>
                                                    Optionnel
                                                </option>
                                            </select>
                                            <div class="form-text">
                                                <strong>Tronc Commun :</strong> Obligatoire pour tous les élèves<br>
                                                <strong>Optionnel :</strong> Choisi par l'élève selon sa filière
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="coefficient" class="form-label">Coefficient</label>
                                            <input type="number" class="form-control" id="coefficient" name="coefficient" 
                                                   value="<?php echo htmlspecialchars($_POST['coefficient'] ?? ''); ?>" 
                                                   min="0.1" max="10" step="0.1" required>
                                            <div class="form-text">Poids de la matière dans le calcul de la moyenne générale</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configuration avancée -->
                            <div class="mb-4">
                                <h5 class="section-title mb-3">
                                    <i class="bi bi-sliders me-2"></i>Configuration Avancée
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="ponderation_json" class="form-label">Pondération par Défaut (JSON)</label>
                                    <textarea class="form-control json-editor" id="ponderation_json" name="ponderation_json" rows="5" 
                                              placeholder='{"interrogations": 0.3, "devoirs": 0.4, "examens": 0.3}'><?php echo htmlspecialchars($_POST['ponderation_json'] ?? ''); ?></textarea>
                                    <div class="form-text">
                                        Configuration JSON optionnelle pour la pondération des types d'évaluations.
                                        <br><strong>Exemple :</strong> {"interrogations": 0.3, "devoirs": 0.4, "examens": 0.3}
                                    </div>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="actif" name="actif" 
                                           <?php echo ($_POST['actif'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="actif">
                                        Matière active
                                    </label>
                                    <div class="form-text">Une matière inactive n'apparaîtra pas dans les nouveaux bulletins</div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="d-flex justify-content-between">
                                <a href="view.php?id=<?php echo $matiere['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-2"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Enregistrer les Modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Zone de danger -->
            <div class="row justify-content-center mt-4">
                <div class="col-lg-8">
                    <div class="danger-zone">
                        <h6 class="text-danger mb-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>Zone de Danger
                        </h6>
                        <p class="small text-muted mb-3">
                            Ces actions sont irréversibles. Assurez-vous de comprendre les conséquences avant de procéder.
                        </p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="toggleMatiere(<?php echo $matiere['id']; ?>, <?php echo $matiere['statut'] === 'actif' ? 'false' : 'true'; ?>)">
                                <i class="bi bi-power me-1"></i>
                                <?php echo $matiere['statut'] === 'actif' ? 'Désactiver' : 'Activer'; ?> la Matière
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="archiveMatiere(<?php echo $matiere['id']; ?>)">
                                <i class="bi bi-archive me-1"></i>Archiver la Matière
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Aide et conseils -->
            <div class="row justify-content-center mt-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Conseils de Modification</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="bi bi-exclamation-circle text-warning me-2"></i>Attention</h6>
                                    <ul class="small mb-3">
                                        <li>Modifier le coefficient affecte les calculs de moyenne</li>
                                        <li>Changer le code peut affecter les intégrations</li>
                                        <li>Désactiver une matière la cache des nouveaux bulletins</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="bi bi-check-circle text-success me-2"></i>Bonnes Pratiques</h6>
                                    <ul class="small mb-3">
                                        <li>Testez les modifications sur un petit groupe</li>
                                        <li>Informez les enseignants des changements</li>
                                        <li>Sauvegardez avant les modifications importantes</li>
                                    </ul>
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
        // Conversion automatique du code en majuscules
        document.getElementById('code').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Validation du JSON
        document.getElementById('ponderation_json').addEventListener('blur', function() {
            const value = this.value.trim();
            if (value) {
                try {
                    const parsed = JSON.parse(value);
                    if (typeof parsed !== 'object' || Array.isArray(parsed)) {
                        throw new Error('Doit être un objet JSON');
                    }
                    Object.values(parsed).forEach(val => {
                        if (typeof val !== 'number' || val < 0 || val > 1) {
                            throw new Error('Les valeurs doivent être des nombres entre 0 et 1');
                        }
                    });
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } catch (e) {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
        
        // Fonction pour basculer le statut actif/inactif
        function toggleMatiere(id, activate) {
            const action = activate ? 'activer' : 'désactiver';
            if (confirm(`Êtes-vous sûr de vouloir ${action} cette matière ?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'toggle_status.php';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                const activateInput = document.createElement('input');
                activateInput.type = 'hidden';
                activateInput.name = 'activate';
                activateInput.value = activate;
                
                form.appendChild(idInput);
                form.appendChild(activateInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Fonction pour archiver la matière
        function archiveMatiere(id) {
            if (confirm('Êtes-vous sûr de vouloir archiver cette matière ? Cette action peut être difficile à annuler.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'archive.php';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Suggestions de pondération
        function suggestPonderation(type) {
            const textarea = document.getElementById('ponderation_json');
            let suggestion = '';
            
            switch(type) {
                case 'standard':
                    suggestion = '{\n  "interrogations": 0.3,\n  "devoirs": 0.4,\n  "examens": 0.3\n}';
                    break;
                case 'pratique':
                    suggestion = '{\n  "pratique": 0.5,\n  "theorie": 0.3,\n  "projets": 0.2\n}';
                    break;
                case 'langues':
                    suggestion = '{\n  "oral": 0.3,\n  "ecrit": 0.4,\n  "comprehension": 0.3\n}';
                    break;
            }
            
            if (suggestion && (!textarea.value || confirm('Remplacer la configuration actuelle ?'))) {
                textarea.value = suggestion;
                textarea.focus();
                textarea.dispatchEvent(new Event('blur'));
            }
        }
        
        // Ajouter des boutons de suggestion
        const pondDiv = document.getElementById('ponderation_json').parentElement;
        const suggestions = document.createElement('div');
        suggestions.className = 'mt-2';
        suggestions.innerHTML = `
            <small class="text-muted">Suggestions rapides :</small><br>
            <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="suggestPonderation('standard')">Standard</button>
            <button type="button" class="btn btn-outline-success btn-sm me-2" onclick="suggestPonderation('pratique')">Pratique</button>
            <button type="button" class="btn btn-outline-info btn-sm" onclick="suggestPonderation('langues')">Langues</button>
        `;
        pondDiv.appendChild(suggestions);
    </script>
</body>
</html>

