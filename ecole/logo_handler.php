<?php
/**
 * Gestionnaire de logo pour les écoles
 * Gère l'upload, la validation et la suppression des logos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class LogoHandler {
    private $upload_dir;
    private $max_size = 2 * 1024 * 1024; // 2MB
    private $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    public function __construct() {
        $this->upload_dir = __DIR__ . '/../uploads/logos/';
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    /**
     * Traite l'upload d'un logo
     */
    public function handleLogoUpload($file, $ecole_id, $old_logo_path = null) {
        // Vérifier si un fichier a été uploadé
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Aucun fichier uploadé ou erreur lors de l\'upload.',
                'path' => $old_logo_path
            ];
        }
        
        // Vérifier le type de fichier
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);
        
        if (!in_array($extension, $this->allowed_extensions)) {
            return [
                'success' => false,
                'message' => 'Format de fichier non autorisé. Utilisez JPG, PNG ou GIF.',
                'path' => $old_logo_path
            ];
        }
        
        // Vérifier la taille
        if ($file['size'] > $this->max_size) {
            return [
                'success' => false,
                'message' => 'Le fichier est trop volumineux. Taille maximum : 2MB.',
                'path' => $old_logo_path
            ];
        }
        
        // Générer un nom unique
        $new_filename = 'logo_' . $ecole_id . '_' . time() . '.' . $extension;
        $upload_path = $this->upload_dir . $new_filename;
        
        // Upload du fichier
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $logo_path = 'uploads/logos/' . $new_filename;
            
            // Supprimer l'ancien logo s'il existe
            if ($old_logo_path && file_exists(__DIR__ . '/../' . $old_logo_path)) {
                unlink(__DIR__ . '/../' . $old_logo_path);
            }
            
            return [
                'success' => true,
                'message' => 'Logo uploadé avec succès.',
                'path' => $logo_path
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'upload du logo.',
                'path' => $old_logo_path
            ];
        }
    }
    
    /**
     * Supprime un logo
     */
    public function deleteLogo($logo_path) {
        if ($logo_path && file_exists(__DIR__ . '/../' . $logo_path)) {
            if (unlink(__DIR__ . '/../' . $logo_path)) {
                return [
                    'success' => true,
                    'message' => 'Logo supprimé avec succès.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la suppression du logo.'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Logo non trouvé.'
        ];
    }
    
    /**
     * Vérifie si un logo existe
     */
    public function logoExists($logo_path) {
        return $logo_path && file_exists(__DIR__ . '/../' . $logo_path);
    }
    
    /**
     * Obtient l'URL complète d'un logo
     */
    public function getLogoUrl($logo_path) {
        if ($this->logoExists($logo_path)) {
            return '../' . $logo_path;
        }
        return null;
    }
    
    /**
     * Obtient le répertoire d'upload
     */
    public function getUploadDir() {
        return $this->upload_dir;
    }
    
    /**
     * Obtient les extensions autorisées
     */
    public function getAllowedExtensions() {
        return $this->allowed_extensions;
    }
    
    /**
     * Obtient la taille maximum
     */
    public function getMaxSize() {
        return $this->max_size;
    }
}

// Test de la classe si appelée directement
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "<h2>Test du gestionnaire de logo</h2>";
    
    $handler = new LogoHandler();
    
    echo "<p>Répertoire d'upload : " . $handler->getUploadDir() . "</p>";
    echo "<p>Extensions autorisées : " . implode(', ', $handler->getAllowedExtensions()) . "</p>";
    echo "<p>Taille maximum : " . ($handler->getMaxSize() / 1024 / 1024) . " MB</p>";
    
    // Vérifier le répertoire
    if (is_dir($handler->getUploadDir())) {
        echo "<p style='color: green;'>✅ Répertoire d'upload accessible</p>";
    } else {
        echo "<p style='color: red;'>❌ Répertoire d'upload inaccessible</p>";
    }
}
?>
