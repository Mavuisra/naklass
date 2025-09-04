<?php
/**
 * Gestionnaire de comptes pour les nouvelles écoles
 * 
 * Cette classe gère la création automatique de comptes utilisateur
 * lors de la création d'une nouvelle école.
 * 
 * @author Naklass Team
 * @version 1.0.0
 * @since 2024
 */

require_once __DIR__ . '/../config/database.php';

class SchoolAccountManager
{
    private $db;
    
    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Crée un compte utilisateur pour le responsable de l'école
     * 
     * @param array $ecoleData Données de l'école
     * @return array Résultat de la création du compte
     */
    public function createSchoolAccount($ecoleData)
    {
        try {
            // Générer un mot de passe temporaire sécurisé
            $tempPassword = $this->generateTemporaryPassword();
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
                    // Préparer les données utilisateur
        $userData = [
            'nom' => $ecoleData['directeur_nom'] ?? 'Administrateur',
            'prenom' => 'Responsable',
            'email' => $ecoleData['email'],
            'password' => $hashedPassword,
            'temp_password' => $tempPassword,
            'role_id' => 2, // ID du rôle admin (à adapter selon votre structure)
            'ecole_id' => $ecoleData['ecole_id'],
            'statut' => 'actif',
            'niveau_acces' => 'school_admin',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Insérer l'utilisateur dans la base de données
        $insertQuery = "INSERT INTO utilisateurs (
            nom, prenom, email, mot_de_passe_hash, role_id, ecole_id, 
            statut, niveau_acces, created_at
        ) VALUES (
            :nom, :prenom, :email, :password, :role_id, :ecole_id,
            :statut, :niveau_acces, :created_at
        )";
        
        $stmt = $this->db->prepare($insertQuery);
        $result = $stmt->execute([
            'nom' => $userData['nom'],
            'prenom' => $userData['prenom'],
            'email' => $userData['email'],
            'password' => $userData['password'],
            'role_id' => $userData['role_id'],
            'ecole_id' => $userData['ecole_id'],
            'statut' => $userData['statut'],
            'niveau_acces' => $userData['niveau_acces'],
            'created_at' => $userData['created_at']
        ]);
            
            if ($result) {
                $userId = $this->db->lastInsertId();
                
                // Mettre à jour les données de l'école avec l'ID de l'utilisateur
                $this->updateSchoolWithUserId($ecoleData['ecole_id'], $userId);
                
                return [
                    'success' => true,
                    'user_id' => $userId,
                    'email' => $userData['email'],
                    'temp_password' => $userData['temp_password'],
                    'login_url' => $this->generateLoginUrl(),
                    'user_data' => $userData
                ];
            } else {
                throw new Exception('Erreur lors de la création du compte utilisateur');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Génère un mot de passe temporaire sécurisé
     * 
     * @return string Mot de passe temporaire
     */
    public function generateTemporaryPassword()
    {
        // Générer un mot de passe de 12 caractères avec lettres, chiffres et symboles
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Génère un identifiant unique basé sur l'école et l'email
     * 
     * @param string $nomEcole Nom de l'école
     * @param string $email Email de l'école
     * @return string Identifiant unique
     */
    public function generateUsername($nomEcole, $email)
    {
        // Extraire le nom de domaine de l'email
        $domain = substr($email, 0, strpos($email, '@'));
        
        // Nettoyer le nom de l'école
        $cleanNom = preg_replace('/[^A-Za-z0-9]/', '', $nomEcole);
        $cleanNom = strtolower(substr($cleanNom, 0, 8));
        
        // Créer l'identifiant de base
        $baseUsername = $cleanNom . '_' . $domain;
        
        // Ajouter un timestamp pour l'unicité
        $username = $baseUsername . '_' . time();
        
        return $username;
    }
    
    /**
     * Vérifie si un email existe déjà
     * 
     * @param string $email Email à vérifier
     * @return bool True si existe
     */
    private function emailExists($email)
    {
        $query = "SELECT COUNT(*) FROM utilisateurs WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['email' => $email]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Met à jour l'école avec l'ID de l'utilisateur créé
     * 
     * @param int $ecoleId ID de l'école
     * @param int $userId ID de l'utilisateur
     */
    private function updateSchoolWithUserId($ecoleId, $userId)
    {
        // Vérifier si la colonne created_by_user_id existe
        $checkColumn = $this->db->query("SHOW COLUMNS FROM ecoles LIKE 'created_by_user_id'");
        if ($checkColumn->rowCount() > 0) {
            $query = "UPDATE ecoles SET created_by_user_id = :user_id WHERE id = :ecole_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'user_id' => $userId,
                'ecole_id' => $ecoleId
            ]);
        }
        // Si la colonne n'existe pas, on ignore cette étape
    }
    
    /**
     * Génère l'URL de connexion
     * 
     * @return string URL de connexion
     */
    private function generateLoginUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        
        return $protocol . '://' . $host . $path . '/auth/login.php';
    }
    
    /**
     * Valide les données de l'école avant création du compte
     * 
     * @param array $ecoleData Données de l'école
     * @return array Résultat de la validation
     */
    public function validateSchoolData($ecoleData)
    {
        $errors = [];
        
        // Vérifier les champs obligatoires
        if (empty($ecoleData['nom_ecole'])) {
            $errors[] = 'Le nom de l\'école est obligatoire';
        }
        
        if (empty($ecoleData['email'])) {
            $errors[] = 'L\'email de l\'école est obligatoire';
        } elseif (!filter_var($ecoleData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'email de l\'école n\'est pas valide';
        }
        
        if (empty($ecoleData['ecole_id'])) {
            $errors[] = 'L\'ID de l\'école est manquant';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Obtient les informations du compte créé
     * 
     * @param int $userId ID de l'utilisateur
     * @return array Informations du compte
     */
    public function getAccountInfo($userId)
    {
        $query = "SELECT u.*, e.nom_ecole, e.code_ecole 
                  FROM utilisateurs u 
                  JOIN ecoles e ON u.ecole_id = e.id 
                  WHERE u.id = :user_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
