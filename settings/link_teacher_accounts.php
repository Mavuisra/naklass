<?php
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireRole(['admin', 'direction']);

// Vérifier la configuration de l'école
requireSchoolSetup();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

// Traitement de la liaison d'un enseignant à un compte utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'link_teacher') {
            $enseignant_id = intval($_POST['enseignant_id']);
            $utilisateur_id = intval($_POST['utilisateur_id']);
            
            // Vérifier que l'enseignant et l'utilisateur existent
            $check_enseignant = "SELECT * FROM enseignants WHERE id = :enseignant_id AND ecole_id = :ecole_id";
            $stmt = $db->prepare($check_enseignant);
            $stmt->execute(['enseignant_id' => $enseignant_id, 'ecole_id' => $_SESSION['ecole_id']]);
            $enseignant = $stmt->fetch();
            
            if (!$enseignant) {
                throw new Exception("Enseignant non trouvé.");
            }
            
            $check_utilisateur = "SELECT * FROM utilisateurs WHERE id = :utilisateur_id AND ecole_id = :ecole_id";
            $stmt = $db->prepare($check_utilisateur);
            $stmt->execute(['utilisateur_id' => $utilisateur_id, 'ecole_id' => $_SESSION['ecole_id']]);
            $utilisateur = $stmt->fetch();
            
            if (!$utilisateur) {
                throw new Exception("Utilisateur non trouvé.");
            }
            
            // Mettre à jour l'enseignant avec l'ID de l'utilisateur
            $update_query = "UPDATE enseignants SET utilisateur_id = :utilisateur_id, updated_at = NOW() WHERE id = :enseignant_id";
            $stmt = $db->prepare($update_query);
            $stmt->execute(['utilisateur_id' => $utilisateur_id, 'enseignant_id' => $enseignant_id]);
            
            $success = "Enseignant {$enseignant['prenom']} {$enseignant['nom']} lié avec succès au compte utilisateur {$utilisateur['email']}.";
            
        } elseif ($_POST['action'] === 'create_and_link') {
            $enseignant_id = intval($_POST['enseignant_id']);
            $email = sanitize($_POST['email']);
            $mot_de_passe = $_POST['mot_de_passe'];
            $confirmation = $_POST['confirmation'];
            
            // Validation
            if (empty($email) || empty($mot_de_passe)) {
                throw new Exception("Tous les champs sont obligatoires.");
            }
            
            if ($mot_de_passe !== $confirmation) {
                throw new Exception("Les mots de passe ne correspondent pas.");
            }
            
            if (strlen($mot_de_passe) < 6) {
                throw new Exception("Le mot de passe doit contenir au moins 6 caractères.");
            }
            
            // Vérifier que l'email n'existe pas déjà
            $check_email = "SELECT id FROM utilisateurs WHERE email = :email AND ecole_id = :ecole_id";
            $stmt = $db->prepare($check_email);
            $stmt->execute(['email' => $email, 'ecole_id' => $_SESSION['ecole_id']]);
            if ($stmt->fetch()) {
                throw new Exception("Cet email est déjà utilisé.");
            }
            
            // Récupérer les informations de l'enseignant
            $enseignant_query = "SELECT * FROM enseignants WHERE id = :enseignant_id AND ecole_id = :ecole_id";
            $stmt = $db->prepare($enseignant_query);
            $stmt->execute(['enseignant_id' => $enseignant_id, 'ecole_id' => $_SESSION['ecole_id']]);
            $enseignant = $stmt->fetch();
            
            if (!$enseignant) {
                throw new Exception("Enseignant non trouvé.");
            }
            
            // Créer le compte utilisateur
            $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $role_id = 3; // ID du rôle enseignant
            
            $insert_user = "INSERT INTO utilisateurs (ecole_id, role_id, nom, prenom, email, telephone, mot_de_passe_hash, actif, created_at, created_by) 
                           VALUES (:ecole_id, :role_id, :nom, :prenom, :email, :telephone, :mot_de_passe_hash, 1, NOW(), :created_by)";
            
            $stmt = $db->prepare($insert_user);
            $stmt->execute([
                'ecole_id' => $_SESSION['ecole_id'],
                'role_id' => $role_id,
                'nom' => $enseignant['nom'],
                'prenom' => $enseignant['prenom'],
                'email' => $email,
                'telephone' => $enseignant['telephone'],
                'mot_de_passe_hash' => $mot_de_passe_hash,
                'created_by' => $_SESSION['user_id']
            ]);
            
            $utilisateur_id = $db->lastInsertId();
            
            // Lier l'enseignant au compte utilisateur
            $update_enseignant = "UPDATE enseignants SET utilisateur_id = :utilisateur_id, updated_at = NOW() WHERE id = :enseignant_id";
            $stmt = $db->prepare($update_enseignant);
            $stmt->execute(['utilisateur_id' => $utilisateur_id, 'enseignant_id' => $enseignant_id]);
            
            $success = "Compte utilisateur créé et lié avec succès à l'enseignant {$enseignant['prenom']} {$enseignant['nom']}.";
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Récupérer les enseignants non liés
try {
    $enseignants_non_lies = "SELECT e.* FROM enseignants e 
                             WHERE e.ecole_id = :ecole_id 
                             AND e.statut = 'actif' 
                             AND e.utilisateur_id IS NULL
                             ORDER BY e.nom, e.prenom";
    $stmt = $db->prepare($enseignants_non_lies);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
    $enseignants_non_lies = $stmt->fetchAll();
} catch (Exception $e) {
    $enseignants_non_lies = [];
    $errors[] = "Erreur lors de la récupération des enseignants: " . $e->getMessage();
}

// Récupérer les utilisateurs non liés (rôle enseignant)
try {
    $utilisateurs_non_lies = "SELECT u.* FROM utilisateurs u 
                              WHERE u.ecole_id = :ecole_id 
                              AND u.role_id = 3 
                              AND u.id NOT IN (
                                  SELECT e.utilisateur_id FROM enseignants e 
                                  WHERE e.ecole_id = :ecole_id2 AND e.utilisateur_id IS NOT NULL
                              )
                              ORDER BY u.nom, u.prenom";
    $stmt = $db->prepare($utilisateurs_non_lies);
    $stmt->execute(['ecole_id' => $_SESSION['ecole_id'], 'ecole_id2' => $_SESSION['ecole_id']]);
    $utilisateurs_non_lies = $stmt->fetchAll();
} catch (Exception $e) {
    $utilisateurs_non_lies = [];
    $errors[] = "Erreur lors de la récupération des utilisateurs: " . $e->getMessage();
}

$page_title = "Lier les Comptes Enseignants";
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
                <h1>
                    <i class="bi bi-link me-2"></i>
                    <?php echo $page_title; ?>
                </h1>
                <p class="text-muted">Lier les enseignants existants à des comptes utilisateurs</p>
            </div>
            
            <div class="topbar-actions">
                <a href="users.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Retour
                </a>
            </div>
        </header>
        
        <!-- Contenu -->
        <div class="content-area">
            <!-- Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Enseignants non liés -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person-badge me-2"></i>
                        Enseignants sans compte utilisateur (<?php echo count($enseignants_non_lies); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($enseignants_non_lies)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle display-4 text-success"></i>
                            <h5 class="text-success mt-3">Tous les enseignants ont un compte utilisateur !</h5>
                            <p class="text-muted">Aucune action nécessaire.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Email</th>
                                        <th>Téléphone</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enseignants_non_lies as $enseignant): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo htmlspecialchars($enseignant['matricule_enseignant']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($enseignant['nom']); ?></td>
                                            <td><?php echo htmlspecialchars($enseignant['prenom']); ?></td>
                                            <td>
                                                <?php if ($enseignant['email']): ?>
                                                    <?php echo htmlspecialchars($enseignant['email']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Non renseigné</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($enseignant['telephone']): ?>
                                                    <?php echo htmlspecialchars($enseignant['telephone']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Non renseigné</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#createAccountModal"
                                                            data-enseignant-id="<?php echo $enseignant['id']; ?>"
                                                            data-enseignant-nom="<?php echo htmlspecialchars($enseignant['nom']); ?>"
                                                            data-enseignant-prenom="<?php echo htmlspecialchars($enseignant['prenom']); ?>"
                                                            data-enseignant-email="<?php echo htmlspecialchars($enseignant['email'] ?? ''); ?>">
                                                        <i class="bi bi-person-plus"></i> Créer un compte
                                                    </button>
                                                    
                                                    <?php if (!empty($utilisateurs_non_lies)): ?>
                                                        <button type="button" class="btn btn-outline-success" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#linkAccountModal"
                                                                data-enseignant-id="<?php echo $enseignant['id']; ?>"
                                                                data-enseignant-nom="<?php echo htmlspecialchars($enseignant['nom']); ?>"
                                                                data-enseignant-prenom="<?php echo htmlspecialchars($enseignant['prenom']); ?>">
                                                            <i class="bi bi-link"></i> Lier un compte
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Utilisateurs non liés -->
            <?php if (!empty($utilisateurs_non_lies)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person me-2"></i>
                        Comptes utilisateurs non liés (<?php echo count($utilisateurs_non_lies); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($utilisateurs_non_lies as $utilisateur): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($utilisateur['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($utilisateur['prenom']); ?></td>
                                        <td><?php echo htmlspecialchars($utilisateur['email']); ?></td>
                                        <td>
                                            <?php if ($utilisateur['telephone']): ?>
                                                <?php echo htmlspecialchars($utilisateur['telephone']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Non renseigné</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $utilisateur['actif'] ? 'success' : 'danger'; ?>">
                                                <?php echo $utilisateur['actif'] ? 'Actif' : 'Inactif'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Modal Créer un compte -->
    <div class="modal fade" id="createAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create_and_link">
                    <input type="hidden" name="enseignant_id" id="create_enseignant_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-person-plus me-2"></i>
                            Créer un compte utilisateur
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Enseignant</label>
                            <input type="text" class="form-control" id="create_enseignant_nom" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="create_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="create_email" name="email" required>
                            <div class="form-text">L'email servira d'identifiant de connexion.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="create_mot_de_passe" class="form-label">Mot de passe *</label>
                            <input type="password" class="form-control" id="create_mot_de_passe" name="mot_de_passe" required minlength="6">
                        </div>
                        
                        <div class="mb-3">
                            <label for="create_confirmation" class="form-label">Confirmation du mot de passe *</label>
                            <input type="password" class="form-control" id="create_confirmation" name="confirmation" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus me-2"></i>Créer le compte
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Lier un compte -->
    <div class="modal fade" id="linkAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="link_teacher">
                    <input type="hidden" name="enseignant_id" id="link_enseignant_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-link me-2"></i>
                            Lier à un compte existant
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Enseignant</label>
                            <input type="text" class="form-control" id="link_enseignant_nom" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="link_utilisateur_id" class="form-label">Compte utilisateur *</label>
                            <select class="form-select" id="link_utilisateur_id" name="utilisateur_id" required>
                                <option value="">Sélectionner un compte...</option>
                                <?php foreach ($utilisateurs_non_lies as $utilisateur): ?>
                                    <option value="<?php echo $utilisateur['id']; ?>">
                                        <?php echo htmlspecialchars($utilisateur['prenom'] . ' ' . $utilisateur['nom'] . ' (' . $utilisateur['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-link me-2"></i>Lier le compte
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Gérer les modals
        document.addEventListener('DOMContentLoaded', function() {
            // Modal création de compte
            const createModal = document.getElementById('createAccountModal');
            if (createModal) {
                createModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const enseignantId = button.getAttribute('data-enseignant-id');
                    const enseignantNom = button.getAttribute('data-enseignant-nom');
                    const enseignantPrenom = button.getAttribute('data-enseignant-prenom');
                    const enseignantEmail = button.getAttribute('data-enseignant-email');
                    
                    document.getElementById('create_enseignant_id').value = enseignantId;
                    document.getElementById('create_enseignant_nom').value = enseignantPrenom + ' ' + enseignantNom;
                    document.getElementById('create_email').value = enseignantEmail;
                });
            }
            
            // Modal liaison de compte
            const linkModal = document.getElementById('linkAccountModal');
            if (linkModal) {
                linkModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const enseignantId = button.getAttribute('data-enseignant-id');
                    const enseignantNom = button.getAttribute('data-enseignant-nom');
                    const enseignantPrenom = button.getAttribute('data-enseignant-prenom');
                    
                    document.getElementById('link_enseignant_id').value = enseignantId;
                    document.getElementById('link_enseignant_nom').value = enseignantPrenom + ' ' + enseignantNom;
                });
            }
        });
    </script>
</body>
</html>

