# 🚨 Guide d'Installation Super Admin - Solution Simple

## Problème rencontré

Le script `setup_super_admin.php` contient des erreurs de syntaxe SQL complexes. Voici la solution simple et efficace.

## ✅ Solution recommandée

### Étape 1: Utilisez le script simple
```
http://localhost/naklass/quick_super_admin_setup.php
```

**Pourquoi ce script ?**
- ✅ SQL simple et compatible
- ✅ Pas de requêtes préparées complexes
- ✅ Gestion d'erreurs robuste
- ✅ Interface claire

### Étape 2: Vérifiez les résultats
Le script va :
1. ✅ Ajouter les colonnes `is_super_admin` et `niveau_acces`
2. ✅ Ajouter les colonnes `activee`, `date_activation`, `activee_par` à `ecoles`
3. ✅ Créer le rôle Super Admin
4. ✅ Créer l'utilisateur Super Admin

### Étape 3: Connectez-vous
```
URL: http://localhost/naklass/superadmin/login.php
Email: superadmin@naklass.cd
Mot de passe: SuperAdmin2024!
```

## 🔧 Alternative manuelle (si nécessaire)

Si le script automatique ne fonctionne pas, exécutez ces commandes SQL directement :

```sql
-- 1. Ajouter les colonnes utilisateurs
ALTER TABLE utilisateurs ADD COLUMN is_super_admin BOOLEAN DEFAULT FALSE;
ALTER TABLE utilisateurs ADD COLUMN niveau_acces ENUM('super_admin', 'school_admin', 'user') DEFAULT 'user';

-- 2. Ajouter les colonnes écoles
ALTER TABLE ecoles ADD COLUMN activee BOOLEAN DEFAULT FALSE;
ALTER TABLE ecoles ADD COLUMN date_activation DATETIME NULL;
ALTER TABLE ecoles ADD COLUMN activee_par BIGINT NULL;

-- 3. Créer le rôle Super Admin
INSERT IGNORE INTO roles (id, code, libelle, permissions, niveau_hierarchie) 
VALUES (0, 'super_admin', 'Super Administrateur', '{"all": true, "multi_school": true}', 0);

-- 4. Créer le Super Admin
INSERT IGNORE INTO utilisateurs (
    id, ecole_id, nom, prenom, email, 
    mot_de_passe_hash, role_id, is_super_admin, niveau_acces, created_by
) VALUES (
    0, NULL, 'Super', 'Administrateur', 'superadmin@naklass.cd',
    '$2y$12$gkTF8Zzb4Kb8FzF5UzHzxu5.WZh4uCw5sVJNZa4F7xQrHb9EsThcW',
    0, TRUE, 'super_admin', 0
);

-- 5. Activer l'école de démonstration
UPDATE ecoles SET activee = TRUE, date_activation = NOW(), activee_par = 0 WHERE id = 1;
```

## 🎯 Test de fonctionnement

### 1. Vérifier la structure
```sql
SHOW COLUMNS FROM utilisateurs LIKE '%super%';
SHOW COLUMNS FROM ecoles LIKE 'activee';
```

### 2. Vérifier le Super Admin
```sql
SELECT id, nom, prenom, email, is_super_admin, niveau_acces 
FROM utilisateurs 
WHERE is_super_admin = TRUE;
```

### 3. Tester la connexion
- Aller sur `/superadmin/login.php`
- Utiliser les identifiants par défaut
- Vérifier l'accès à `/superadmin/index.php`

## 🚫 Erreurs communes et solutions

### Erreur : "Column already exists"
✅ **Normal** - La colonne existe déjà, continuez

### Erreur : "Duplicate entry"
✅ **Normal** - L'utilisateur existe déjà

### Erreur : "Access denied"
❌ **Vérifiez** les permissions MySQL

### Erreur : "Table doesn't exist"
❌ **Assurez-vous** que la base `naklass_db` existe

## 📞 Support

Si les problèmes persistent :
1. Utilisez le script `quick_super_admin_setup.php`
2. Ou exécutez les commandes SQL manuellement
3. Vérifiez les permissions de base de données

---

**Important :** Le script simple `quick_super_admin_setup.php` est la solution recommandée car il évite les problèmes de syntaxe SQL complexe du script principal.
