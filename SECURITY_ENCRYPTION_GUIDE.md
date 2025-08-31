# 🔐 Guide de Sécurité - Chiffrement des IDs

## 🎯 Vue d'ensemble

Pour renforcer la sécurité de l'application Naklass, un système de chiffrement des identifiants (IDs) a été implémenté. Ce système protège les données sensibles en chiffrant les IDs dans les URLs et paramètres.

## 🔧 Fonctionnement

### Principe de Base
Au lieu d'utiliser des URLs comme :
```
❌ http://localhost/naklass/classes/assign.php?student_id=2
```

Le système génère des URLs sécurisées comme :
```
✅ http://localhost/naklass/classes/assign.php?student_id=U2FsdGVkX1-abc123def456...
```

### Avantages de Sécurité
- **🛡️ Masquage des IDs** : Empêche la découverte des IDs par énumération
- **🔒 Protection contre IDOR** : Prévient les attaques d'accès direct aux objets
- **⏰ Expiration temporelle** : Les clés changent selon l'année
- **🎯 Validation stricte** : Vérification de permissions à chaque utilisation

## 📋 Fonctions Disponibles

### 1. `encryptId($id)`
Chiffre un ID pour utilisation dans les URLs.

```php
$student_id = 123;
$encrypted_id = encryptId($student_id);
// Résultat : "U2FsdGVkX1-abc123def456..."
```

### 2. `decryptId($encrypted_id)`
Déchiffre un ID chiffré.

```php
$encrypted_id = "U2FsdGVkX1-abc123def456...";
$original_id = decryptId($encrypted_id);
// Résultat : 123 (ou false si erreur)
```

### 3. `createSecureLink($base_url, $id, $param_name)`
Crée un lien sécurisé avec ID chiffré.

```php
$secure_url = createSecureLink('assign.php', 123, 'student_id');
// Résultat : "assign.php?student_id=U2FsdGVkX1-abc123def456..."
```

### 4. `getSecureId($param_name)`
Récupère et déchiffre un ID depuis les paramètres GET.

```php
// URL : assign.php?student_id=U2FsdGVkX1-abc123def456...
$student_id = getSecureId('student_id');
// Résultat : 123 (ou false si invalide)
```

### 5. `validateSecureId($param_name, $table, $where_conditions)`
Valide un ID sécurisé avec vérifications de permissions.

```php
$student_id = validateSecureId('student_id', 'eleves', [
    'ecole_id' => $_SESSION['ecole_id']
]);
// Vérifie que l'élève appartient bien à l'école de l'utilisateur
```

## 🔐 Spécifications Techniques

### Algorithme de Chiffrement
- **Méthode** : AES-256-CBC
- **Clé** : SHA-256 de `APP_NAME + '_encryption_key_' + année`
- **IV** : Vecteur d'initialisation aléatoire pour chaque chiffrement
- **Encodage** : Base64 URL-safe (compatible URLs)

### Structure des Données Chiffrées
```
[16 bytes IV][Données chiffrées] → Base64 → URL-safe
```

### Rotation des Clés
- **Fréquence** : Annuelle (basée sur l'année courante)
- **Impact** : Les liens de l'année précédente deviennent invalides
- **Sécurité** : Limite la durée de vie des liens sensibles

## 🚀 Utilisation Pratique

### Dans les Contrôleurs
```php
// Récupération sécurisée d'un ID d'élève
$student_id = validateSecureId('student_id', 'eleves', [
    'ecole_id' => $_SESSION['ecole_id']
]);

if (!$student_id) {
    setFlashMessage('error', 'Élève introuvable ou accès non autorisé.');
    redirect('../students/index.php');
}
```

### Dans les Vues (Templates)
```php
<!-- Lien sécurisé vers assignation -->
<a href="<?php echo createSecureLink('../classes/assign.php', $eleve['id'], 'student_id'); ?>">
    Assigner à une classe
</a>

<!-- Lien avec ID chiffré -->
<a href="view.php?id=<?php echo encryptId($student['id']); ?>">
    Voir profil
</a>
```

### Dans les Formulaires
```html
<!-- Champ caché avec ID chiffré -->
<input type="hidden" name="student_id" value="<?php echo encryptId($student_id); ?>">
```

## 🛡️ Mesures de Sécurité

### Validation Stricte
```php
// ✅ Correct : Validation avec contraintes
$student_id = validateSecureId('student_id', 'eleves', [
    'ecole_id' => $_SESSION['ecole_id'],
    'statut_scolaire' => 'inscrit'
]);

// ❌ Incorrect : Déchiffrement sans validation
$student_id = decryptId($_GET['student_id']);
```

### Gestion d'Erreurs
```php
try {
    $student_id = validateSecureId('student_id', 'eleves', [
        'ecole_id' => $_SESSION['ecole_id']
    ]);
    
    if (!$student_id) {
        throw new Exception('Accès non autorisé');
    }
    
    // Traitement sécurisé...
    
} catch (Exception $e) {
    logUserAction('SECURITY_VIOLATION', 'Tentative d\'accès non autorisé');
    redirect('error.php');
}
```

### Logs de Sécurité
Tous les échecs de déchiffrement sont automatiquement loggés :
```
[2024-01-15 10:30:45] Erreur lors du déchiffrement d'ID: Invalid format
[2024-01-15 10:30:46] Erreur lors de la validation d'ID sécurisé: Access denied
```

## 📁 Fichiers Impactés

### Pages Sécurisées
- ✅ `classes/assign.php` - Assignation d'élèves aux classes
- ✅ `students/inscription_success.php` - Liens sécurisés
- 🔄 `students/view.php` - À sécuriser
- 🔄 `students/edit.php` - À sécuriser
- 🔄 Autres pages sensibles

### Fonctions Ajoutées
- `includes/functions.php` - Fonctions de chiffrement/déchiffrement

## 🔄 Migration des Pages Existantes

### Étapes de Sécurisation
1. **Identifier les URLs sensibles** avec IDs exposés
2. **Remplacer les liens** par `createSecureLink()`
3. **Modifier les contrôleurs** pour utiliser `validateSecureId()`
4. **Tester l'accès** et les permissions
5. **Vérifier les logs** d'erreurs

### Exemple de Migration
```php
// AVANT
<a href="edit.php?id=<?php echo $student['id']; ?>">Modifier</a>
$student_id = intval($_GET['id']);

// APRÈS
<a href="<?php echo createSecureLink('edit.php', $student['id']); ?>">Modifier</a>
$student_id = validateSecureId('id', 'eleves', ['ecole_id' => $_SESSION['ecole_id']]);
```

## ⚠️ Bonnes Pratiques

### À Faire ✅
- Toujours utiliser `validateSecureId()` au lieu de `getSecureId()`
- Ajouter des contraintes de validation appropriées
- Logger les tentatives d'accès non autorisé
- Tester les liens après implémentation

### À Éviter ❌
- Utiliser directement `decryptId()` sans validation
- Exposer les IDs originaux dans le code client
- Réutiliser des IDs chiffrés entre sessions
- Ignorer les erreurs de déchiffrement

## 🧪 Tests de Sécurité

### Tests à Effectuer
1. **Manipulation d'URLs** : Modifier manuellement les IDs chiffrés
2. **Accès cross-école** : Tenter d'accéder aux données d'autres écoles
3. **IDs invalides** : Utiliser des IDs qui n'existent pas
4. **Expiration** : Tester avec d'anciens liens (changement d'année)

### Vérifications
```bash
# Test de manipulation d'URL
http://localhost/naklass/classes/assign.php?student_id=INVALID_ID

# Test d'accès cross-école (doit échouer)
http://localhost/naklass/classes/assign.php?student_id=[ID_AUTRE_ECOLE]
```

## 📊 Monitoring

### Métriques à Surveiller
- Nombre d'erreurs de déchiffrement par jour
- Tentatives d'accès non autorisé
- Performance du chiffrement/déchiffrement
- Rotation réussie des clés annuelles

---

*Ce système de chiffrement renforce significativement la sécurité de Naklass en protégeant les identifiants sensibles contre les attaques par énumération et l'accès non autorisé.*
