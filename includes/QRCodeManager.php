<?php
/**
 * QRCodeManager - Gestionnaire de QR codes pour Naklass
 * 
 * Cette classe gère la génération, la validation et la sécurité des QR codes
 * pour les cartes d'élèves et autres documents du système.
 * 
 * @author Naklass Team
 * @version 1.0.0
 * @since 2024
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;

class QRCodeManager
{
    private $defaultSize;
    private $defaultMargin;
    private $errorCorrectionLevel;
    private $encryptionKey;
    private $qrCacheDir;
    
    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->defaultSize = 300;
        $this->defaultMargin = 10;
        $this->errorCorrectionLevel = ErrorCorrectionLevel::High;
        $this->encryptionKey = $this->getEncryptionKey();
        $this->qrCacheDir = __DIR__ . '/../uploads/qr_codes/';
        
        // Créer le répertoire de cache s'il n'existe pas
        if (!is_dir($this->qrCacheDir)) {
            mkdir($this->qrCacheDir, 0755, true);
        }
    }
    
    /**
     * Génère un QR code pour un élève
     * 
     * @param array $studentData Données de l'élève
     * @param array $options Options de génération
     * @return array Informations sur le QR code généré
     */
    public function generateStudentQRCode($studentData, $options = [])
    {
        try {
            // Valider les données de l'élève
            $this->validateStudentData($studentData);
            
            // Préparer les données à encoder
            $qrData = $this->prepareStudentData($studentData);
            
            // Chiffrer les données si nécessaire
            if (isset($options['encrypt']) && $options['encrypt']) {
                $qrData = $this->encryptData($qrData);
            }
            
            // Générer le QR code
            $qrCode = $this->createQRCode($qrData, $options);
            
            // Sauvegarder le QR code
            $filename = $this->generateFilename($studentData['id'], $options);
            $filePath = $this->saveQRCode($qrCode, $filename, $options['format'] ?? 'png');
            
            return [
                'success' => true,
                'file_path' => $filePath,
                'web_path' => 'uploads/qr_codes/' . $filename,
                'filename' => $filename,
                'data' => $qrData,
                'size' => $options['size'] ?? $this->defaultSize,
                'format' => $options['format'] ?? 'png',
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'file_path' => null
            ];
        }
    }
    
    /**
     * Génère un QR code simple avec du texte
     * 
     * @param string $text Texte à encoder
     * @param array $options Options de génération
     * @return array Informations sur le QR code généré
     */
    public function generateSimpleQRCode($text, $options = [])
    {
        try {
            $qrCode = $this->createQRCode($text, $options);
            
            $filename = 'qr_' . md5($text) . '_' . time() . '.' . ($options['format'] ?? 'png');
            $filePath = $this->saveQRCode($qrCode, $filename, $options['format'] ?? 'png');
            
            return [
                'success' => true,
                'file_path' => $filePath,
                'web_path' => 'uploads/qr_codes/' . $filename,
                'filename' => $filename,
                'data' => $text,
                'size' => $options['size'] ?? $this->defaultSize,
                'format' => $options['format'] ?? 'png',
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'file_path' => null
            ];
        }
    }
    
    /**
     * Valide un QR code et retourne les données décodées
     * 
     * @param string $qrCodePath Chemin vers le fichier QR code
     * @return array Résultat de la validation
     */
    public function validateQRCode($qrCodePath)
    {
        try {
            if (!file_exists($qrCodePath)) {
                throw new Exception('Fichier QR code introuvable');
            }
            
            // Pour la validation, nous devons utiliser une bibliothèque de décodage
            // Pour l'instant, nous retournons les informations du fichier
            $fileInfo = pathinfo($qrCodePath);
            $filename = $fileInfo['filename'];
            
            // Extraire l'ID de l'élève du nom de fichier si possible
            if (preg_match('/student_(\d+)_/', $filename, $matches)) {
                $studentId = $matches[1];
                return [
                    'success' => true,
                    'student_id' => $studentId,
                    'file_path' => $qrCodePath,
                    'validated_at' => date('Y-m-d H:i:s')
                ];
            }
            
            return [
                'success' => true,
                'file_path' => $qrCodePath,
                'validated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crée un objet QR code avec les options spécifiées
     * 
     * @param string $data Données à encoder
     * @param array $options Options de génération
     * @return QrCode Objet QR code
     */
    private function createQRCode($data, $options = [])
    {
        $size = $options['size'] ?? $this->defaultSize;
        $margin = $options['margin'] ?? $this->defaultMargin;
        
        // Couleurs personnalisées
        $foregroundColor = new Color(0, 0, 0);
        $backgroundColor = new Color(255, 255, 255);
        
        if (isset($options['foreground_color'])) {
            $color = $this->hexToRgb($options['foreground_color']);
            $foregroundColor = new Color($color['r'], $color['g'], $color['b']);
        }
        
        if (isset($options['background_color'])) {
            $color = $this->hexToRgb($options['background_color']);
            $backgroundColor = new Color($color['r'], $color['g'], $color['b']);
        }
        
        $qrCode = new QrCode(
            data: $data,
            errorCorrectionLevel: $this->errorCorrectionLevel,
            size: $size,
            margin: $margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: $foregroundColor,
            backgroundColor: $backgroundColor
        );
        
        return $qrCode;
    }
    
    /**
     * Sauvegarde le QR code dans un fichier
     * 
     * @param QrCode $qrCode Objet QR code
     * @param string $filename Nom du fichier
     * @param string $format Format de sortie (png, svg)
     * @return string Chemin du fichier sauvegardé
     */
    private function saveQRCode($qrCode, $filename, $format = 'png')
    {
        $filePath = $this->qrCacheDir . $filename;
        
        if ($format === 'svg') {
            $writer = new SvgWriter();
        } else {
            $writer = new PngWriter();
        }
        
        $result = $writer->write($qrCode);
        file_put_contents($filePath, $result->getString());
        
        return $filePath;
    }
    
    /**
     * Convertit une couleur hexadécimale en RGB
     * 
     * @param string $hex Couleur hexadécimale (#RRGGBB)
     * @return array Valeurs RGB
     */
    private function hexToRgb($hex)
    {
        $hex = ltrim($hex, '#');
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
    
    /**
     * Prépare les données de l'élève pour l'encodage
     * 
     * @param array $studentData Données de l'élève
     * @return string Données JSON formatées
     */
    private function prepareStudentData($studentData)
    {
        $data = [
            'type' => 'student_card',
            'version' => '1.0',
            'student' => [
                'id' => $studentData['id'],
                'matricule' => $studentData['matricule'],
                'nom' => $studentData['nom'],
                'prenom' => $studentData['prenom'],
                'ecole_id' => $studentData['ecole_id'],
                'classe' => $studentData['classe_nom'] ?? '',
                'annee_scolaire' => $studentData['annee_scolaire'] ?? '',
                'statut' => $studentData['statut_scolaire'] ?? 'actif'
            ],
            'school' => [
                'id' => $studentData['ecole_id'],
                'nom' => $studentData['ecole_nom'] ?? '',
                'type' => $studentData['type_etablissement'] ?? ''
            ],
            'generated' => date('Y-m-d H:i:s'),
            'expires' => date('Y-m-d H:i:s', strtotime('+1 year'))
        ];
        
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Valide les données de l'élève
     * 
     * @param array $studentData Données de l'élève
     * @throws Exception Si les données sont invalides
     */
    private function validateStudentData($studentData)
    {
        $requiredFields = ['id', 'matricule', 'nom', 'prenom', 'ecole_id'];
        
        foreach ($requiredFields as $field) {
            if (!isset($studentData[$field]) || empty($studentData[$field])) {
                throw new Exception("Champ requis manquant: $field");
            }
        }
        
        if (!is_numeric($studentData['id']) || !is_numeric($studentData['ecole_id'])) {
            throw new Exception("ID et ecole_id doivent être numériques");
        }
    }
    
    /**
     * Chiffre les données avec une clé de chiffrement
     * 
     * @param string $data Données à chiffrer
     * @return string Données chiffrées en base64
     */
    private function encryptData($data)
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Déchiffre les données
     * 
     * @param string $encryptedData Données chiffrées
     * @return string Données déchiffrées
     */
    private function decryptData($encryptedData)
    {
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
    
    /**
     * Génère un nom de fichier unique
     * 
     * @param int $studentId ID de l'élève
     * @param array $options Options
     * @return string Nom de fichier
     */
    private function generateFilename($studentId, $options = [])
    {
        $format = $options['format'] ?? 'png';
        $timestamp = time();
        $hash = substr(md5($studentId . $timestamp), 0, 8);
        
        return "student_{$studentId}_{$hash}.{$format}";
    }
    
    /**
     * Obtient la clé de chiffrement
     * 
     * @return string Clé de chiffrement
     */
    private function getEncryptionKey()
    {
        // Utiliser une clé définie dans la configuration ou générer une clé par défaut
        $key = defined('QR_ENCRYPTION_KEY') ? QR_ENCRYPTION_KEY : 'naklass_qr_default_key_2024';
        return hash('sha256', $key, true);
    }
    
    /**
     * Nettoie les anciens QR codes (plus de 30 jours)
     * 
     * @return int Nombre de fichiers supprimés
     */
    public function cleanupOldQRCodes()
    {
        $deletedCount = 0;
        $files = glob($this->qrCacheDir . '*');
        $cutoffTime = time() - (30 * 24 * 60 * 60); // 30 jours
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }
        
        return $deletedCount;
    }
    
    /**
     * Obtient les statistiques des QR codes
     * 
     * @return array Statistiques
     */
    public function getQRCodeStats()
    {
        $files = glob($this->qrCacheDir . '*');
        $totalFiles = count($files);
        $totalSize = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
            }
        }
        
        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'cache_directory' => $this->qrCacheDir
        ];
    }
    
    /**
     * Génère un QR code pour l'impression (optimisé)
     * 
     * @param array $studentData Données de l'élève
     * @return array Résultat de la génération
     */
    public function generatePrintQRCode($studentData)
    {
        // Vérifier si GD est disponible, sinon utiliser SVG
        $format = extension_loaded('gd') ? 'png' : 'svg';
        
        $options = [
            'size' => 200,
            'margin' => 5,
            'format' => $format,
            'foreground_color' => '#000000',
            'background_color' => '#FFFFFF'
        ];
        
        return $this->generateStudentQRCode($studentData, $options);
    }
    
    /**
     * Génère un QR code pour l'affichage web (haute qualité)
     * 
     * @param array $studentData Données de l'élève
     * @return array Résultat de la génération
     */
    public function generateWebQRCode($studentData)
    {
        // Vérifier si GD est disponible, sinon utiliser SVG
        $format = extension_loaded('gd') ? 'png' : 'svg';
        
        $options = [
            'size' => 300,
            'margin' => 10,
            'format' => $format,
            'foreground_color' => '#000000',
            'background_color' => '#FFFFFF'
        ];
        
        return $this->generateStudentQRCode($studentData, $options);
    }
}
