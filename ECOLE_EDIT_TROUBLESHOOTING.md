# Guide de Dépannage - Édition des Écoles

## 🔍 Problème Identifié

**Erreur :** "Erreur lors de la mise à jour des informations" lors de la tentative de mise à jour des informations de l'école.

## ✅ Diagnostic Effectué

### 1. Tests de Base de Données
- ✅ Structure de la table `ecoles` : Correcte
- ✅ Connexion à la base de données : Fonctionnelle
- ✅ Requêtes de mise à jour : Fonctionnelles
- ✅ Contraintes et relations : Aucun problème

### 2. Tests du Code PHP
- ✅ Fonction `sanitize()` : Fonctionnelle
- ✅ Validation des données : Correcte
- ✅ Préparation des requêtes : Fonctionnelle
- ✅ Exécution des mises à jour : Fonctionnelle

### 3. Tests des Permissions
- ✅ Fonction `isLoggedIn()` : Fonctionnelle
- ✅ Fonction `hasRole()` : Fonctionnelle
- ✅ Vérification des rôles admin/direction : Fonctionnelle

## 🚨 Causes Possibles

### 1. Problèmes de Session
- **Variable de session manquante :** `$_SESSION['user_role']` au lieu de `$_SESSION['role']`
- **Session expirée** ou corrompue
- **École non associée** au compte utilisateur

### 2. Problèmes de Permissions
- **Rôle insuffisant** (nécessite admin ou direction)
- **Compte non validé** ou suspendu
- **École non configurée** ou inactive

### 3. Problèmes de Cache/Validation
- **Cache du navigateur** obsolète
- **Validation côté client** échouée
- **Données de formulaire** corrompues

## 🔧 Solutions

### Solution 1: Vérification des Variables de Session
```php
// Vérifier que ces variables sont définies
$_SESSION['user_id']        // ID de l'utilisateur
$_SESSION['ecole_id']       // ID de l'école
$_SESSION['user_role']      // Rôle de l'utilisateur (admin/direction)
```

### Solution 2: Vérification des Permissions
```php
// L'utilisateur doit avoir l'un de ces rôles
if (!hasRole(['admin', 'direction'])) {
    // Accès refusé
}
```

### Solution 3: Vérification de l'École
```php
// L'utilisateur doit être associé à une école
if (!isset($_SESSION['ecole_id'])) {
    // Aucune école associée
}
```

## 🛠️ Outils de Diagnostic

### 1. Script de Diagnostic en Temps Réel
```bash
# Accéder à ce script depuis le navigateur
ecole/debug_real_time.php
```

### 2. Script de Test des Permissions
```bash
# Exécuter en ligne de commande
php debug_ecole_edit.php
```

### 3. Vérification des Logs
```bash
# Vérifier les logs d'erreur PHP
tail -f /var/log/php_errors.log
```

## 📋 Checklist de Résolution

### Étape 1: Vérification de la Session
- [ ] Être connecté avec un compte valide
- [ ] Vérifier que `$_SESSION['user_id']` est défini
- [ ] Vérifier que `$_SESSION['ecole_id']` est défini
- [ ] Vérifier que `$_SESSION['user_role']` est défini

### Étape 2: Vérification des Permissions
- [ ] Vérifier que le rôle est 'admin' ou 'direction'
- [ ] Vérifier que le compte est actif
- [ ] Vérifier que l'école est active

### Étape 3: Vérification de l'Environnement
- [ ] Vider le cache du navigateur
- [ ] Se déconnecter et se reconnecter
- [ ] Vérifier la connexion internet
- [ ] Vérifier les extensions du navigateur

### Étape 4: Test du Formulaire
- [ ] Remplir tous les champs obligatoires
- [ ] Vérifier la validité des emails
- [ ] Vérifier la longueur des champs
- [ ] Soumettre le formulaire

## 🔍 Messages d'Erreur Courants

### 1. "Aucune école associée à votre compte"
**Cause :** `$_SESSION['ecole_id']` n'est pas défini
**Solution :** Contacter l'administrateur pour associer une école

### 2. "Vous devez être administrateur ou membre de la direction"
**Cause :** Rôle insuffisant
**Solution :** Utiliser un compte avec les bonnes permissions

### 3. "Veuillez remplir tous les champs obligatoires"
**Cause :** Validation côté serveur échouée
**Solution :** Vérifier tous les champs marqués d'un *

### 4. "Veuillez saisir des adresses email valides"
**Cause :** Format d'email invalide
**Solution :** Vérifier le format des adresses email

## 📞 Support

### En Cas de Problème Persistant
1. **Exécuter le diagnostic** : `ecole/debug_real_time.php`
2. **Vérifier les logs** d'erreur PHP
3. **Contacter l'administrateur** avec les informations du diagnostic
4. **Fournir les captures d'écran** des erreurs

### Informations à Fournir
- Message d'erreur exact
- Rôle de l'utilisateur
- ID de l'école
- Résultats du diagnostic
- Navigateur et système d'exploitation

## 📚 Ressources Additionnelles

- [Guide d'Installation](../README.md)
- [Guide de Configuration](../SCHOOL_SETUP_GUIDE.md)
- [Guide de Sécurité](../SECURITY_ENCRYPTION_GUIDE.md)
- [Documentation de la Base de Données](../database/naklass_db.sql)

---

*Dernière mise à jour : <?php echo date('d/m/Y H:i:s'); ?>*
