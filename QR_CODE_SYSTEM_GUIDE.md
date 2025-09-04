# Guide du Système QR Code - Naklass

## 📋 Vue d'ensemble

Le système QR code de Naklass utilise une bibliothèque PHP dédiée (`endroid/qr-code`) pour générer, valider et sécuriser les QR codes des cartes d'élèves. Ce système offre une solution robuste et sécurisée pour l'identification numérique des étudiants.

## 🚀 Installation et Configuration

### 1. Dépendances Installées

```json
{
    "endroid/qr-code": "^6.0.9",
    "bacon/bacon-qr-code": "^3.0.1",
    "dasprid/enum": "^1.0.6"
}
```

### 2. Structure des Fichiers

```
naklass/
├── includes/
│   ├── QRCodeManager.php      # Gestionnaire principal des QR codes
│   └── QRCodeSecurity.php     # Système de sécurité avancé
├── students/
│   ├── generate_card.php      # Génération de cartes (modifié)
│   └── verify_qr.php          # Interface de vérification
├── uploads/
│   └── qr_codes/              # Cache des QR codes générés
└── test_qr_system.php         # Script de test complet
```

## 🔧 Fonctionnalités Principales

### 1. QRCodeManager

**Responsabilités :**
- Génération de QR codes simples et complexes
- Gestion du cache des QR codes
- Validation des QR codes existants
- Optimisation pour impression et web

**Méthodes principales :**
```php
// Génération simple
$result = $qrManager->generateSimpleQRCode($text, $options);

// Génération pour élève
$result = $qrManager->generateStudentQRCode($studentData, $options);

// Génération optimisée
$printResult = $qrManager->generatePrintQRCode($studentData);
$webResult = $qrManager->generateWebQRCode($studentData);

// Validation
$validation = $qrManager->validateQRCode($filePath);
```

### 2. QRCodeSecurity

**Responsabilités :**
- Chiffrement des données sensibles
- Signature numérique des QR codes
- Validation de l'intégrité
- Protection contre la falsification

**Méthodes principales :**
```php
// Sécurisation des données
$secureData = $qrSecurity->secureQRData($data);

// Validation sécurisée
$validation = $qrSecurity->validateSecureQRData($secureData);

// Génération sécurisée pour élève
$secureQR = $qrSecurity->generateSecureStudentQR($studentData);
```

## 📱 Utilisation dans les Cartes d'Élèves

### 1. Génération Automatique

Les QR codes sont maintenant générés automatiquement lors de la création des cartes d'élèves :

```php
// Dans generate_card.php
$qrResult = generateQrCodeForStudent($eleve, $qrManager);
if ($qrResult['success']) {
    echo '<img src="' . $qrResult['web_url'] . '" alt="QR Code">';
}
```

### 2. Données Encodées

Chaque QR code contient :
```json
{
    "type": "student_card",
    "version": "1.0",
    "student": {
        "id": 123,
        "matricule": "ELEV001",
        "nom": "Dupont",
        "prenom": "Jean",
        "ecole_id": 1,
        "classe": "6ème A",
        "annee_scolaire": "2024-2025",
        "statut": "actif"
    },
    "school": {
        "id": 1,
        "nom": "École Primaire",
        "type": "Primaire"
    },
    "generated": "2024-01-15 10:30:00",
    "expires": "2025-01-15 10:30:00"
}
```

## 🔒 Sécurité Avancée

### 1. Chiffrement AES-256-GCM

- **Algorithme :** AES-256-GCM
- **Clé :** Générée à partir de la configuration
- **IV :** Aléatoire pour chaque QR code
- **Tag d'authentification :** Inclus pour vérifier l'intégrité

### 2. Signature HMAC-SHA256

- **Fonction :** HMAC-SHA256
- **Clé :** Séparée de la clé de chiffrement
- **Protection :** Contre la modification des données

### 3. Token de Sécurité

- **Timestamp :** Horodatage de génération
- **Nonce :** Valeur aléatoire unique
- **Hash :** Vérification de l'intégrité des données

## 🖥️ Interface de Vérification

### 1. Page de Vérification (`students/verify_qr.php`)

**Fonctionnalités :**
- Interface de saisie des données QR
- Validation en temps réel
- Affichage des informations de l'élève
- Gestion des erreurs de validation

**Utilisation :**
1. Accéder à `/students/verify_qr.php`
2. Coller les données JSON du QR code
3. Cliquer sur "Vérifier le QR Code"
4. Consulter les résultats de validation

### 2. Types de Validation

**QR Code Valide :**
- ✅ Données cohérentes
- ✅ Signature valide
- ✅ Non expiré
- ✅ Élève trouvé en base

**QR Code Invalide :**
- ❌ Données corrompues
- ❌ Signature invalide
- ❌ Expiré
- ❌ Élève non trouvé

## ⚙️ Configuration

### 1. Clés de Sécurité

Définir dans la configuration :
```php
define('QR_ENCRYPTION_KEY', 'votre_cle_chiffrement_secrete');
define('QR_SIGNATURE_KEY', 'votre_cle_signature_secrete');
define('QR_HMAC_KEY', 'votre_cle_hmac_secrete');
```

### 2. Options de Génération

```php
$options = [
    'size' => 300,                    // Taille en pixels
    'margin' => 15,                   // Marge
    'format' => 'png',                // Format (png, svg)
    'foreground_color' => '#000000',  // Couleur avant-plan
    'background_color' => '#FFFFFF',  // Couleur arrière-plan
    'logo_path' => 'path/to/logo.png', // Logo (optionnel)
    'label' => 'Carte Élève'          // Label (optionnel)
];
```

## 🧪 Tests et Validation

### 1. Script de Test

Exécuter le script de test complet :
```bash
php test_qr_system.php
```

**Tests inclus :**
- ✅ Initialisation des classes
- ✅ Génération de QR codes simples
- ✅ Génération de QR codes d'élèves
- ✅ Validation des QR codes
- ✅ Système de sécurité
- ✅ Performance
- ✅ Nettoyage du cache

### 2. Tests de Sécurité

```php
// Test de chiffrement
$secureData = $qrSecurity->secureQRData($data);
$validation = $qrSecurity->validateSecureQRData($secureData);

// Test de signature
$signature = $qrSecurity->createSignature($data);
$isValid = $qrSecurity->verifySignature($data, $signature);
```

## 📊 Gestion du Cache

### 1. Nettoyage Automatique

```php
// Nettoyer les QR codes de plus de 30 jours
$deletedCount = $qrManager->cleanupOldQRCodes();
```

### 2. Statistiques

```php
$stats = $qrManager->getQRCodeStats();
echo "Fichiers: " . $stats['total_files'];
echo "Taille: " . $stats['total_size_mb'] . " MB";
```

## 🔧 Maintenance

### 1. Surveillance

- **Espace disque :** Surveiller le répertoire `uploads/qr_codes/`
- **Performance :** Tester la génération de QR codes
- **Sécurité :** Vérifier les clés de chiffrement

### 2. Mise à Jour

```bash
# Mettre à jour la bibliothèque QR code
composer update endroid/qr-code
```

### 3. Sauvegarde

- **Configuration :** Sauvegarder les clés de sécurité
- **Cache :** Le cache peut être régénéré automatiquement
- **Base de données :** Les données d'élèves restent en base

## 🚨 Dépannage

### 1. Erreurs Communes

**"Extension GD manquante"**
```bash
# Installer l'extension GD
sudo apt-get install php-gd
```

**"Permissions insuffisantes"**
```bash
# Donner les permissions d'écriture
chmod 755 uploads/qr_codes/
```

**"Clé de chiffrement manquante"**
```php
// Définir les clés dans la configuration
define('QR_ENCRYPTION_KEY', 'votre_cle');
```

### 2. Logs et Debug

```php
// Activer les logs d'erreur
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifier les statistiques
$stats = $qrManager->getQRCodeStats();
var_dump($stats);
```

## 📈 Performance

### 1. Optimisations

- **Cache :** Les QR codes sont mis en cache
- **Taille :** Optimisation pour impression (150px) et web (300px)
- **Format :** PNG pour l'impression, SVG pour le web

### 2. Métriques

- **Génération :** ~50ms par QR code
- **Validation :** ~10ms par QR code
- **Taille fichier :** ~2-5KB par QR code

## 🔮 Évolutions Futures

### 1. Fonctionnalités Prévues

- **Scanner mobile :** Application mobile de scan
- **Géolocalisation :** Vérification de présence
- **Notifications :** Alertes de sécurité
- **Analytics :** Statistiques d'utilisation

### 2. Améliorations Techniques

- **Compression :** Réduction de la taille des QR codes
- **Multi-format :** Support de plus de formats
- **API REST :** Interface de programmation
- **Webhooks :** Notifications en temps réel

## 📞 Support

Pour toute question ou problème :

1. **Documentation :** Consulter ce guide
2. **Tests :** Exécuter `test_qr_system.php`
3. **Logs :** Vérifier les logs d'erreur PHP
4. **Communauté :** Forum de support Naklass

---

**Version :** 1.0.0  
**Dernière mise à jour :** Janvier 2024  
**Auteur :** Équipe Naklass
