<?php
// Fichier sidebar inclus dans toutes les pages du dashboard
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Déterminer la page active
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Récupérer le logo de l'école
$ecole_logo = null;
if (isset($_SESSION['ecole_id'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT logo_path FROM ecoles WHERE id = :ecole_id";
        $stmt = $db->prepare($query);
        $stmt->execute(['ecole_id' => $_SESSION['ecole_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['logo_path']) {
            $ecole_logo = $result['logo_path'];
        }
    } catch (Exception $e) {
        // En cas d'erreur, on continue sans logo
        error_log('Erreur lors de la récupération du logo: ' . $e->getMessage());
    }
}
?>

<style>
.sidebar-logo-img {
    width: 40px;
    height: 40px;
    object-fit: contain;
    border-radius: 8px;
    margin-right: 10px;
}
</style>

<!-- Navigation latérale -->
<nav class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <?php if ($ecole_logo): ?>
                <img src="../<?php echo htmlspecialchars($ecole_logo); ?>" alt="Logo de l'école" class="sidebar-logo-img">
            <?php else: ?>
                <i class="bi bi-mortarboard-fill"></i>
            <?php endif; ?>
            <span>Naklass</span>
        </div>
        <button class="sidebar-toggle d-lg-none" type="button">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    
    <div class="sidebar-user">
        <div class="user-avatar">
            <i class="bi bi-person-circle"></i>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo $_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']; ?></div>
            <div class="user-role"><?php echo ROLES[$_SESSION['user_role']] ?? $_SESSION['user_role']; ?></div>
            <div class="user-school"><?php echo $_SESSION['ecole_nom']; ?></div>
        </div>
    </div>
    
    <ul class="sidebar-menu">
        <?php if (!hasRole(['enseignant'])): ?>
        <li class="menu-item <?php echo ($current_dir == 'auth' && $current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <a href="../auth/dashboard.php" class="menu-link">
                <i class="bi bi-speedometer2"></i>
                <span>Tableau de bord</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole(['enseignant'])): ?>
        <li class="menu-item <?php echo ($current_dir == 'teachers' && $current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <a href="../teachers/dashboard.php" class="menu-link">
                <i class="bi bi-speedometer2"></i>
                <span>Mon Tableau de bord</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole(['admin', 'direction', 'secretaire'])): ?>
        <li class="menu-item <?php echo ($current_dir == 'students') ? 'active' : ''; ?>">
            <a href="../students/" class="menu-link">
                <i class="bi bi-people"></i>
                <span>Élèves</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo ($current_dir == 'classes') ? 'active' : ''; ?>">
            <a href="../classes/" class="menu-link">
                <i class="bi bi-building"></i>
                <span>Classes</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo ($current_dir == 'matieres') ? 'active' : ''; ?>">
            <a href="../matieres/" class="menu-link">
                <i class="bi bi-book"></i>
                <span>Matières</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
        <li class="menu-item <?php echo ($current_dir == 'teachers') ? 'active' : ''; ?>">
            <a href="../teachers/" class="menu-link">
                <i class="bi bi-person-badge"></i>
                <span>Enseignants</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
        <li class="menu-item <?php echo ($current_dir == 'emploi_du_temps') ? 'active' : ''; ?>">
            <a href="../emploi_du_temps/" class="menu-link">
                <i class="bi bi-calendar-week"></i>
                <span>Emploi du Temps</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole(['enseignant'])): ?>
        <li class="menu-item <?php echo ($current_dir == 'classes' && $current_page == 'my_classes.php') ? 'active' : ''; ?>">
            <a href="../classes/my_classes.php" class="menu-link">
                <i class="bi bi-building-check"></i>
                <span>Mes Classes</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole(['admin', 'direction', 'caissier'])): ?>
        <li class="menu-item <?php echo ($current_dir == 'finance') ? 'active' : ''; ?>">
            <a href="../finance/" class="menu-link">
                <i class="bi bi-cash-coin"></i>
                <span>Finances</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole(['admin', 'direction', 'enseignant'])): ?>
        <li class="menu-item <?php echo ($current_dir == 'grades') ? 'active' : ''; ?>">
            <a href="../grades/" class="menu-link">
                <i class="bi bi-journal-text"></i>
                <span>Notes & Bulletins</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo ($current_dir == 'presence') ? 'active' : ''; ?>">
            <a href="../presence/" class="menu-link">
                <i class="bi bi-clipboard-check"></i>
                <span>Présence</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole(['admin', 'direction'])): ?>
        <li class="menu-item <?php echo ($current_dir == 'ecole') ? 'active' : ''; ?>">
            <a href="../ecole/" class="menu-link">
                <i class="bi bi-building"></i>
                <span>École</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo ($current_dir == 'reports') ? 'active' : ''; ?>">
            <a href="../reports/" class="menu-link">
                <i class="bi bi-graph-up"></i>
                <span>Rapports</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo ($current_dir == 'settings') ? 'active' : ''; ?>">
            <a href="../settings/" class="menu-link">
                <i class="bi bi-gear"></i>
                <span>Paramètres</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
    
    <div class="sidebar-footer">
        <div class="user-profile-section">
            <a href="../profile/" class="profile-link">
                <div class="profile-info">
                    <div class="profile-avatar">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div class="profile-details">
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']) ?></span>
                        <span class="profile-role"><?= htmlspecialchars($_SESSION['user_role']) ?></span>
                    </div>
                </div>
            </a>
        </div>
        <a href="../auth/logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i>
            <span>Déconnexion</span>
        </a>
    </div>
</nav>
