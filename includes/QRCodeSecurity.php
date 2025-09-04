<?php
/**
 * QRCodeSecurity - Système de sécurité avancé pour les QR codes
 * 
 * Cette classe gère le chiffrement, la signature numérique et la validation
 * de sécurité des QR codes pour assurer l'authenticité et l'intégrité.
 * 
 * @author Naklass Team
 * @version 1.0.0
 * @since 2024
 */

class QRCodeSecurity
{
    private $encryptionKey;
    private $signatureKey;
    private $hmacKey;
    private $algorithm;
    
    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->encryptionKey = $this->getEncryptionKey();
        $this->signatureKey = $this->getSignatureKey();
        $this->hmacKey = $this->getHmacKey();
        $this->algorithm = 'AES-256-GCM';
    }
    
    /**
     * Chiffre et signe les données du QR code
     * 
     * @param array $data Données à sécuriser
     * @return array Données sécurisées
     */
    public function secureQRData($data)
    {
        try {
            // Ajouter des métadonnées de sécurité
            $secureData = [
                'version' => '2.0',
                'type' => 'secure_student_card',
                'timestamp' => time(),
                'nonce' => $this->generateNonce(),
                'data' => $data
            ];
            
            // Chiffrer les données
            $encryptedData = $this->encryptData(json_encode($secureData));
            
            // Créer la signature HMAC
            $signature = $this->createSignature($encryptedData);
            
            // Créer le token de sécurité
            $securityToken = $this->createSecurityToken($secureData);
            
            return [
                'encrypted_data' => $encryptedData,
                'signature' => $signature,
                'security_token' => $securityToken,
                'algorithm' => $this->algorithm,
                'timestamp' => $secureData['timestamp']
            ];
            
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la sécurisation des données: ' . $e->getMessage());
        }
    }
    
    /**
     * Valide et déchiffre les données sécurisées
     * 
     * @param array $secureData Données sécurisées
     * @return array Données déchiffrées et validées
     */
    public function validateSecureQRData($secureData)
    {
        try {
            // Vérifier la structure des données
            if (!isset($secureData['encrypted_data']) || !isset($secureData['signature'])) {
                throw new Exception('Structure de données invalide');
            }
            
            // Vérifier la signature
            if (!$this->verifySignature($secureData['encrypted_data'], $secureData['signature'])) {
                throw new Exception('Signature invalide - Données corrompues');
            }
            
            // Déchiffrer les données
            $decryptedJson = $this->decryptData($secureData['encrypted_data']);
            $decryptedData = json_decode($decryptedJson, true);
            
            if (!$decryptedData) {
                throw new Exception('Données déchiffrées invalides');
            }
            
            // Vérifier le token de sécurité
            if (isset($secureData['security_token'])) {
                if (!$this->verifySecurityToken($secureData['security_token'], $decryptedData)) {
                    throw new Exception('Token de sécurité invalide');
                }
            }
            
            // Vérifier l'expiration
            if (isset($decryptedData['timestamp'])) {
                $age = time() - $decryptedData['timestamp'];
                $maxAge = 24 * 60 * 60; // 24 heures
                
                if ($age > $maxAge) {
                    throw new Exception('QR code expiré (plus de 24h)');
                }
            }
            
            return [
                'success' => true,
                'data' => $decryptedData['data'],
                'metadata' => [
                    'version' => $decryptedData['version'],
                    'type' => $decryptedData['type'],
                    'timestamp' => $decryptedData['timestamp'],
                    'age_seconds' => $age ?? 0
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Chiffre les données avec AES-256-GCM
     * 
     * @param string $data Données à chiffrer
     * @return string Données chiffrées (base64)
     */
    private function encryptData($data)
    {
        $iv = random_bytes(16);
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $data,
            $this->algorithm,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($encrypted === false) {
            throw new Exception('Erreur de chiffrement');
        }
        
        return base64_encode($iv . $tag . $encrypted);
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
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->algorithm,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($decrypted === false) {
            throw new Exception('Erreur de déchiffrement');
        }
        
        return $decrypted;
    }
    
    /**
     * Crée une signature HMAC
     * 
     * @param string $data Données à signer
     * @return string Signature (hex)
     */
    private function createSignature($data)
    {
        return hash_hmac('sha256', $data, $this->hmacKey);
    }
    
    /**
     * Vérifie une signature HMAC
     * 
     * @param string $data Données originales
     * @param string $signature Signature à vérifier
     * @return bool True si valide
     */
    private function verifySignature($data, $signature)
    {
        $expectedSignature = $this->createSignature($data);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Crée un token de sécurité
     * 
     * @param array $data Données originales
     * @return string Token de sécurité
     */
    private function createSecurityToken($data)
    {
        $tokenData = [
            'timestamp' => $data['timestamp'],
            'nonce' => $data['nonce'],
            'hash' => hash('sha256', json_encode($data['data']))
        ];
        
        return base64_encode(json_encode($tokenData));
    }
    
    /**
     * Vérifie un token de sécurité
     * 
     * @param string $token Token à vérifier
     * @param array $data Données originales
     * @return bool True si valide
     */
    private function verifySecurityToken($token, $data)
    {
        try {
            $tokenData = json_decode(base64_decode($token), true);
            
            if (!$tokenData || !isset($tokenData['timestamp'], $tokenData['nonce'], $tokenData['hash'])) {
                return false;
            }
            
            // Vérifier le timestamp
            if ($tokenData['timestamp'] !== $data['timestamp']) {
                return false;
            }
            
            // Vérifier le nonce
            if ($tokenData['nonce'] !== $data['nonce']) {
                return false;
            }
            
            // Vérifier le hash des données
            $expectedHash = hash('sha256', json_encode($data['data']));
            return hash_equals($tokenData['hash'], $expectedHash);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Génère un nonce aléatoire
     * 
     * @return string Nonce
     */
    private function generateNonce()
    {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Obtient la clé de chiffrement
     * 
     * @return string Clé de chiffrement
     */
    private function getEncryptionKey()
    {
        $key = defined('QR_ENCRYPTION_KEY') ? QR_ENCRYPTION_KEY : 'naklass_qr_encryption_key_2024_secure';
        return hash('sha256', $key, true);
    }
    
    /**
     * Obtient la clé de signature
     * 
     * @return string Clé de signature
     */
    private function getSignatureKey()
    {
        $key = defined('QR_SIGNATURE_KEY') ? QR_SIGNATURE_KEY : 'naklass_qr_signature_key_2024_secure';
        return hash('sha256', $key, true);
    }
    
    /**
     * Obtient la clé HMAC
     * 
     * @return string Clé HMAC
     */
    private function getHmacKey()
    {
        $key = defined('QR_HMAC_KEY') ? QR_HMAC_KEY : 'naklass_qr_hmac_key_2024_secure';
        return hash('sha256', $key, true);
    }
    
    /**
     * Génère un QR code sécurisé pour un élève
     * 
     * @param array $studentData Données de l'élève
     * @return array Données sécurisées pour QR code
     */
    public function generateSecureStudentQR($studentData)
    {
        $qrData = [
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
        
        return $this->secureQRData($qrData);
    }
    
    /**
     * Valide un QR code sécurisé d'élève
     * 
     * @param array $secureQRData Données QR sécurisées
     * @return array Résultat de la validation
     */
    public function validateSecureStudentQR($secureQRData)
    {
        $validation = $this->validateSecureQRData($secureQRData);
        
        if (!$validation['success']) {
            return $validation;
        }
        
        // Vérifications spécifiques aux cartes d'élèves
        $data = $validation['data'];
        
        if (!isset($data['student']['id']) || !isset($data['student']['matricule'])) {
            return [
                'success' => false,
                'error' => 'Données d\'élève manquantes'
            ];
        }
        
        // Vérifier l'expiration spécifique
        if (isset($data['expires'])) {
            $expiresDate = new DateTime($data['expires']);
            $now = new DateTime();
            if ($now > $expiresDate) {
                return [
                    'success' => false,
                    'error' => 'Carte d\'élève expirée'
                ];
            }
        }
        
        return $validation;
    }
    
    /**
     * Obtient les statistiques de sécurité
     * 
     * @return array Statistiques
     */
    public function getSecurityStats()
    {
        return [
            'algorithm' => $this->algorithm,
            'encryption_key_length' => strlen($this->encryptionKey),
            'signature_key_length' => strlen($this->signatureKey),
            'hmac_key_length' => strlen($this->hmacKey),
            'supported_algorithms' => [
                'AES-256-GCM',
                'HMAC-SHA256'
            ],
            'security_features' => [
                'encryption' => true,
                'signature' => true,
                'timestamp_validation' => true,
                'nonce_protection' => true,
                'integrity_check' => true
            ]
        ];
    }
}
