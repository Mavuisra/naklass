# 🔧 Guide de Résolution des Problèmes - Module de Rapports

## 🚨 Problèmes Courants et Solutions

### 1. Erreur de Redirection vers le Dashboard

**Symptôme :** Vous êtes redirigé vers le dashboard même en tant qu'admin.

**Cause :** Problème avec la vérification des permissions ou la session.

**Solutions :**

#### A. Vérifier l'Authentification
```bash
# Accédez à ce fichier pour diagnostiquer
reports/test_auth.php
```

#### B. Vérifier la Structure des Tables
```bash
# Vérifiez la structure de votre base de données
reports/check_table_structure.php
```

#### C. Vérifier les Variables de Session
Dans votre navigateur, ouvrez les outils de développement (F12) et vérifiez :
- **Application** > **Session Storage** > **localhost**
- Vérifiez que `user_role` ou `role_code` est bien défini

#### D. Corriger le Fichier auth_check.php
Le fichier `includes/auth_check.php` a été corrigé pour gérer les différentes variables de session :
- `$_SESSION['user_role']` (utilisé par le système de connexion)
- `$_SESSION['role_code']` (fallback)
- `$_SESSION['role']` (fallback)

### 2. Erreur SQL : "Column not found: ecole_id"

**Symptôme :** Erreur lors de la récupération des données.

**Cause :** La colonne `ecole_id` n'existe pas dans certaines tables.

**Solutions :**

#### A. Utiliser la Version Corrigée
```bash
# Utilisez ce fichier au lieu de index.php
reports/index_fixed.php
```

#### B. Vérifier la Structure des Tables
```bash
# Exécutez ce script pour voir quelles colonnes existent
reports/check_table_structure.php
```

#### C. Ajouter les Colonnes Manquantes
Si les colonnes `ecole_id` sont manquantes, vous pouvez les ajouter :

```sql
-- Exemple pour la table classes
ALTER TABLE classes ADD COLUMN ecole_id INT;
ALTER TABLE classes ADD FOREIGN KEY (ecole_id) REFERENCES ecoles(id);

-- Exemple pour la table inscriptions
ALTER TABLE inscriptions ADD COLUMN ecole_id INT;
ALTER TABLE inscriptions ADD FOREIGN KEY (ecole_id) REFERENCES ecoles(id);
```

### 3. Problème de Permissions

**Symptôme :** Accès refusé même avec un rôle admin.

**Solutions :**

#### A. Vérifier les Rôles dans la Base de Données
```sql
-- Vérifiez que votre utilisateur a bien le rôle 'admin'
SELECT u.nom, u.prenom, r.code as role_code 
FROM utilisateurs u 
JOIN roles r ON u.role_id = r.id 
WHERE u.email = 'votre_email@example.com';
```

#### B. Vérifier la Session
```php
// Ajoutez ce code temporairement au début de reports/index.php
session_start();
var_dump($_SESSION);
exit;
```

#### C. Rôles Autorisés
Les rôles suivants peuvent accéder aux rapports :
- ✅ `admin`
- ✅ `direction` 
- ✅ `secretaire`
- ❌ `enseignant`
- ❌ `caissier`

### 4. Problème de Connexion à la Base de Données

**Symptôme :** Erreur de connexion à la base de données.

**Solutions :**

#### A. Vérifier la Configuration
```php
// Vérifiez le fichier config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'naklass_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

#### B. Tester la Connexion
```bash
# Exécutez ce script pour tester la connexion
php reports/test_auth.php
```

### 5. Problème d'Affichage des Données

**Symptôme :** Les statistiques affichent 0 ou N/A.

**Solutions :**

#### A. Vérifier les Données
```sql
-- Vérifiez qu'il y a des données dans vos tables
SELECT COUNT(*) FROM classes;
SELECT COUNT(*) FROM inscriptions;
SELECT COUNT(*) FROM utilisateurs;
```

#### B. Vérifier les Jointures
Si les données existent mais ne s'affichent pas, vérifiez les jointures dans les requêtes SQL.

#### C. Utiliser la Version Adaptative
Le fichier `index_fixed.php` utilise des requêtes SQL adaptatives qui :
- Vérifient l'existence des colonnes avant de les utiliser
- Utilisent des fallbacks si les colonnes n'existent pas
- Affichent des badges "Données globales" quand les données ne sont pas filtrées par école

## 🛠️ Scripts de Diagnostic

### 1. Test d'Authentification
```bash
# Diagnostique les problèmes d'authentification
reports/test_auth.php
```

### 2. Vérification de la Structure
```bash
# Vérifie la structure des tables
reports/check_table_structure.php
```

### 3. Test des Rapports
```bash
# Teste le module complet
reports/test_reports.php
```

## 📋 Checklist de Résolution

- [ ] Vérifier que vous êtes bien connecté
- [ ] Vérifier votre rôle dans la base de données
- [ ] Vérifier la structure des tables
- [ ] Utiliser la version corrigée (`index_fixed.php`)
- [ ] Vérifier les variables de session
- [ ] Tester la connexion à la base de données
- [ ] Vérifier l'existence des colonnes `ecole_id`

## 🔍 Debug Avancé

### A. Ajouter du Debug Temporaire
```php
// Ajoutez ceci au début de reports/index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug des sessions
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
exit;
```

### B. Vérifier les Logs
```bash
# Vérifiez les logs PHP
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log
```

### C. Tester les Requêtes SQL
```php
// Testez vos requêtes SQL directement
try {
    $stmt = $db->query("VOTRE_REQUETE_SQL");
    $result = $stmt->fetch();
    var_dump($result);
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
```

## 📞 Support

Si vous rencontrez encore des problèmes après avoir suivi ce guide :

1. **Vérifiez les logs d'erreur** de votre serveur web
2. **Exécutez les scripts de diagnostic** fournis
3. **Documentez l'erreur exacte** avec les messages d'erreur
4. **Vérifiez la version de PHP** et de MySQL
5. **Testez sur un environnement propre** si possible

## 🎯 Fichiers de Récupération

- **`index_fixed.php`** : Version corrigée qui gère les colonnes manquantes
- **`test_auth.php`** : Diagnostic de l'authentification
- **`check_table_structure.php`** : Vérification de la structure des tables
- **`test_reports.php`** : Test complet du module

---

**💡 Conseil :** Commencez toujours par exécuter `test_auth.php` pour diagnostiquer les problèmes d'authentification !
