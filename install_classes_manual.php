<?php
/**
 * Script d'installation manuelle du module Classes
 * Exécute les requêtes SQL une par une avec gestion d'erreurs détaillée
 */

require_once 'config/database.php';

// Vérifier que l'utilisateur est connecté et est un administrateur
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Accès refusé. Vous devez être connecté en tant qu'administrateur.");
}

$database = new Database();
$db = $database->getConnection();

echo "<h2>Installation Manuelle du Module Classes</h2>";
echo "<pre>";

// Liste des requêtes SQL à exécuter
$queries = [
    "Création table niveaux" => "
        CREATE TABLE IF NOT EXISTS niveaux (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ecole_id BIGINT NULL,
            nom VARCHAR(100) NOT NULL,
            description TEXT,
            ordre INT DEFAULT 0,
            actif BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE,
            INDEX idx_niveaux_ecole (ecole_id),
            INDEX idx_niveaux_ordre (ordre)
        )",
    
    "Création table sections" => "
        CREATE TABLE IF NOT EXISTS sections (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ecole_id BIGINT NULL,
            nom VARCHAR(100) NOT NULL,
            description TEXT,
            actif BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE,
            INDEX idx_sections_ecole (ecole_id)
        )",
    
    "Création table enseignants" => "
        CREATE TABLE IF NOT EXISTS enseignants (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ecole_id BIGINT NOT NULL,
            matricule VARCHAR(50) UNIQUE,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            date_naissance DATE,
            sexe ENUM('M', 'F') DEFAULT 'M',
            telephone VARCHAR(20),
            email VARCHAR(100),
            adresse TEXT,
            date_embauche DATE,
            specialite VARCHAR(100),
            diplome VARCHAR(200),
            statut ENUM('actif', 'inactif', 'conge', 'suspendu') DEFAULT 'actif',
            photo VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE,
            INDEX idx_enseignants_ecole (ecole_id),
            INDEX idx_enseignants_statut (statut)
        )",
    
    "Création table classes" => "
        CREATE TABLE IF NOT EXISTS classes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ecole_id BIGINT NOT NULL,
            nom VARCHAR(100) NOT NULL,
            niveau_id BIGINT NULL,
            section_id BIGINT NULL,
            description TEXT,
            capacite_max INT NULL,
            enseignant_principal_id BIGINT NULL,
            salle VARCHAR(50) NULL,
            horaire_debut TIME NULL,
            horaire_fin TIME NULL,
            statut ENUM('active', 'preparation', 'suspendue', 'fermee') DEFAULT 'active',
            created_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE,
            FOREIGN KEY (niveau_id) REFERENCES niveaux(id) ON DELETE SET NULL,
            FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
            FOREIGN KEY (enseignant_principal_id) REFERENCES enseignants(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL,
            
            UNIQUE KEY unique_classe_nom_ecole (ecole_id, nom),
            INDEX idx_classes_ecole (ecole_id),
            INDEX idx_classes_niveau (niveau_id),
            INDEX idx_classes_section (section_id),
            INDEX idx_classes_enseignant (enseignant_principal_id),
            INDEX idx_classes_statut (statut)
        )",
    
    "Création table inscriptions" => "
        CREATE TABLE IF NOT EXISTS inscriptions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            eleve_id BIGINT NOT NULL,
            classe_id BIGINT NOT NULL,
            date_inscription DATE NOT NULL DEFAULT (CURRENT_DATE),
            date_fin DATE NULL,
            statut ENUM('validée', 'en_attente', 'annulée', 'suspendue') DEFAULT 'validée',
            notes TEXT,
            created_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
            FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES utilisateurs(id) ON DELETE SET NULL,
            
            INDEX idx_inscriptions_eleve (eleve_id),
            INDEX idx_inscriptions_classe (classe_id),
            INDEX idx_inscriptions_statut (statut),
            INDEX idx_inscriptions_date (date_inscription)
        )",
    
    "Insertion niveau Maternelle" => "
        INSERT IGNORE INTO niveaux (ecole_id, nom, description, ordre) VALUES
        (NULL, 'Maternelle', 'Petite section, Moyenne section, Grande section', 1)",
    
    "Insertion niveau Primaire" => "
        INSERT IGNORE INTO niveaux (ecole_id, nom, description, ordre) VALUES
        (NULL, 'Primaire', 'CP, CE1, CE2, CM1, CM2', 2)",
    
    "Insertion niveau Collège" => "
        INSERT IGNORE INTO niveaux (ecole_id, nom, description, ordre) VALUES
        (NULL, 'Collège', '6ème, 5ème, 4ème, 3ème', 3)",
    
    "Insertion niveau Lycée" => "
        INSERT IGNORE INTO niveaux (ecole_id, nom, description, ordre) VALUES
        (NULL, 'Lycée', 'Seconde, Première, Terminale', 4)",
    
    "Insertion niveau Supérieur" => "
        INSERT IGNORE INTO niveaux (ecole_id, nom, description, ordre) VALUES
        (NULL, 'Supérieur', 'Études supérieures', 5)",
    
    "Insertion section Générale" => "
        INSERT IGNORE INTO sections (ecole_id, nom, description) VALUES
        (NULL, 'Générale', 'Section générale standard')",
    
    "Insertion section Scientifique" => "
        INSERT IGNORE INTO sections (ecole_id, nom, description) VALUES
        (NULL, 'Scientifique', 'Section à dominante scientifique')",
    
    "Insertion section Littéraire" => "
        INSERT IGNORE INTO sections (ecole_id, nom, description) VALUES
        (NULL, 'Littéraire', 'Section à dominante littéraire')",
    
    "Insertion section Technique" => "
        INSERT IGNORE INTO sections (ecole_id, nom, description) VALUES
        (NULL, 'Technique', 'Section technique et technologique')",
    
    "Insertion section Professionnelle" => "
        INSERT IGNORE INTO sections (ecole_id, nom, description) VALUES
        (NULL, 'Professionnelle', 'Section professionnelle')",
    
    "Insertion section Bilingue" => "
        INSERT IGNORE INTO sections (ecole_id, nom, description) VALUES
        (NULL, 'Bilingue', 'Section bilingue')",
    
    "Insertion section Internationale" => "
        INSERT IGNORE INTO sections (ecole_id, nom, description) VALUES
        (NULL, 'Internationale', 'Section internationale')"
];

$success = 0;
$errors = 0;
$warnings = 0;

foreach ($queries as $description => $query) {
    echo "\n[$description] ... ";
    try {
        $db->exec($query);
        echo "✅ OK";
        $success++;
    } catch (PDOException $e) {
        $error_msg = $e->getMessage();
        if (strpos($error_msg, 'already exists') !== false) {
            echo "⚠️ Déjà existant (ignoré)";
            $warnings++;
        } elseif (strpos($error_msg, 'Duplicate entry') !== false) {
            echo "⚠️ Donnée déjà présente (ignorée)";
            $warnings++;
        } else {
            echo "❌ ERREUR: " . $error_msg;
            $errors++;
        }
    }
}

// Créer les vues (peuvent échouer si elles existent déjà)
echo "\n\n--- Création des vues ---";

$view_queries = [
    "Vue classes complètes" => "
        CREATE OR REPLACE VIEW vue_classes_completes AS
        SELECT 
            c.*,
            n.nom as niveau_nom,
            n.ordre as niveau_ordre,
            s.nom as section_nom,
            e.prenom as enseignant_prenom,
            e.nom as enseignant_nom,
            e.telephone as enseignant_telephone,
            e.email as enseignant_email,
            COUNT(DISTINCT i.eleve_id) as nombre_eleves,
            ROUND((COUNT(DISTINCT i.eleve_id) / NULLIF(c.capacite_max, 0)) * 100, 2) as pourcentage_occupation,
            CASE 
                WHEN c.capacite_max IS NULL THEN 'illimitee'
                WHEN COUNT(DISTINCT i.eleve_id) >= c.capacite_max THEN 'complete'
                WHEN COUNT(DISTINCT i.eleve_id) >= (c.capacite_max * 0.8) THEN 'presque_complete'
                ELSE 'disponible'
            END as disponibilite,
            uc.prenom as created_by_prenom,
            uc.nom as created_by_nom
        FROM classes c
        LEFT JOIN niveaux n ON c.niveau_id = n.id
        LEFT JOIN sections s ON c.section_id = s.id
        LEFT JOIN enseignants e ON c.enseignant_principal_id = e.id
        LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut = 'validée'
        LEFT JOIN utilisateurs uc ON c.created_by = uc.id
        GROUP BY c.id",
    
    "Vue élèves avec classes" => "
        CREATE OR REPLACE VIEW vue_eleves_classes AS
        SELECT 
            el.*,
            c.id as classe_id,
            c.nom as classe_nom,
            n.nom as niveau_nom,
            s.nom as section_nom,
            i.date_inscription as date_inscription_classe,
            i.statut as statut_inscription
        FROM eleves el
        LEFT JOIN inscriptions i ON el.id = i.eleve_id AND i.statut = 'validée'
        LEFT JOIN classes c ON i.classe_id = c.id
        LEFT JOIN niveaux n ON c.niveau_id = n.id
        LEFT JOIN sections s ON c.section_id = s.id"
];

foreach ($view_queries as $description => $query) {
    echo "\n[$description] ... ";
    try {
        $db->exec($query);
        echo "✅ OK";
        $success++;
    } catch (PDOException $e) {
        echo "❌ ERREUR: " . $e->getMessage();
        $errors++;
    }
}

// Résumé
echo "\n\n========================================";
echo "\nRÉSUMÉ DE L'INSTALLATION";
echo "\n========================================";
echo "\n✅ Réussites: $success";
echo "\n⚠️  Avertissements: $warnings";
echo "\n❌ Erreurs: $errors";

if ($errors == 0) {
    echo "\n\n🎉 Installation terminée avec succès !";
    echo "\n\nVous pouvez maintenant :";
    echo "\n- Accéder au module Classes : <a href='classes/index.php'>Voir les Classes</a>";
    echo "\n- Créer une nouvelle classe : <a href='classes/create.php'>Créer une Classe</a>";
    
    // Note sur les triggers
    echo "\n\n📝 Note: Les triggers de sécurité (contrôle de capacité et prévention double inscription)";
    echo "\n   peuvent être installés manuellement depuis phpMyAdmin en exécutant le fichier :";
    echo "\n   database/07_module_classes_triggers.sql";
} else {
    echo "\n\n⚠️ L'installation a rencontré des erreurs.";
    echo "\nVeuillez vérifier les messages d'erreur ci-dessus et corriger les problèmes.";
}

echo "\n</pre>";
?>

<style>
    pre {
        background: #f5f5f5;
        padding: 20px;
        border-radius: 5px;
        font-family: 'Courier New', monospace;
        line-height: 1.5;
    }
    a {
        color: #007bff;
        text-decoration: none;
    }
    a:hover {
        text-decoration: underline;
    }
</style>
