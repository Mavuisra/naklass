# 🔗 Guide de Liaison des Comptes Enseignants - Naklass

## 🚨 **Problème Identifié**

L'erreur **"Enseignant non trouvé ou non autorisé"** se produit parce que les enseignants existent dans la base de données mais n'ont pas de compte utilisateur associé.

### **Cause du problème :**
- Les enseignants sont créés dans la table `enseignants`
- Mais leur champ `utilisateur_id` est `NULL`
- La page "Mes Classes" cherche un enseignant lié à l'utilisateur connecté
- Sans liaison, l'enseignant n'est pas trouvé

## ✅ **Solution Implémentée**

J'ai créé une page dédiée **`settings/link_teacher_accounts.php`** qui permet de :

### 1. **Voir les enseignants non liés**
- Liste tous les enseignants sans compte utilisateur
- Affiche leurs informations (matricule, nom, prénom, email, téléphone)

### 2. **Créer et lier un compte**
- Créer un nouveau compte utilisateur pour un enseignant
- Définir un email et mot de passe
- Lier automatiquement le compte à l'enseignant

### 3. **Lier à un compte existant**
- Si un compte utilisateur existe déjà
- Le lier directement à l'enseignant

## 🛠️ **Comment Résoudre le Problème**

### **Étape 1 : Accéder à la page de liaison**
1. Connectez-vous en tant qu'**administrateur** ou **direction**
2. Allez dans **Paramètres** → **Lier comptes enseignants**
3. Ou accédez directement : `settings/link_teacher_accounts.php`

### **Étape 2 : Choisir l'action appropriée**

#### **Option A : Créer un nouveau compte**
1. Cliquez sur **"Créer un compte"** pour l'enseignant
2. Remplissez :
   - **Email** : L'email servira d'identifiant de connexion
   - **Mot de passe** : Minimum 6 caractères
   - **Confirmation** : Retapez le mot de passe
3. Cliquez sur **"Créer le compte"**

#### **Option B : Lier à un compte existant**
1. Si un compte utilisateur existe déjà
2. Cliquez sur **"Lier un compte"**
3. Sélectionnez le compte dans la liste
4. Cliquez sur **"Lier le compte"**

### **Étape 3 : Vérifier la liaison**
1. L'enseignant apparaîtra maintenant dans la liste des enseignants liés
2. Il pourra se connecter avec son email et mot de passe
3. La page "Mes Classes" fonctionnera correctement

## 📊 **Structure de la Base de Données**

### **Table `enseignants`**
```sql
CREATE TABLE enseignants (
    id BIGINT PRIMARY KEY,
    ecole_id BIGINT NOT NULL,
    utilisateur_id BIGINT NULL,  -- ← Ce champ doit être lié
    matricule_enseignant VARCHAR(50),
    nom VARCHAR(255),
    prenom VARCHAR(255),
    -- ... autres champs
);
```

### **Table `utilisateurs`**
```sql
CREATE TABLE utilisateurs (
    id BIGINT PRIMARY KEY,
    ecole_id BIGINT NOT NULL,
    role_id INT NOT NULL,        -- 3 = enseignant
    nom VARCHAR(255),
    prenom VARCHAR(255),
    email VARCHAR(255),
    mot_de_passe_hash VARCHAR(255),
    -- ... autres champs
);
```

## 🔐 **Rôles et Permissions**

### **Qui peut utiliser cette fonctionnalité :**
- ✅ **Administrateurs** : Accès complet
- ✅ **Direction** : Accès complet
- ❌ **Enseignants** : Pas d'accès
- ❌ **Autres rôles** : Pas d'accès

### **Sécurité :**
- Vérification des permissions par rôle
- Validation des données d'entrée
- Protection contre les injections SQL
- Hachage sécurisé des mots de passe

## 📝 **Exemple d'Utilisation**

### **Scénario : Enseignant "Christelle Nzuzi"**

1. **État initial :**
   - Enseignant existe dans la table `enseignants`
   - `utilisateur_id = NULL`
   - Pas de compte de connexion

2. **Action : Créer un compte**
   - Email : `christelle.nzuzi@ecole.com`
   - Mot de passe : `password123`
   - Rôle : Enseignant

3. **Résultat :**
   - Nouveau compte créé dans `utilisateurs`
   - `utilisateur_id` mis à jour dans `enseignants`
   - L'enseignant peut maintenant se connecter

## 🚀 **Test de la Solution**

### **Après la liaison :**
1. **L'enseignant se connecte** avec son email/mot de passe
2. **La page "Mes Classes" fonctionne** correctement
3. **Il voit ses classes et cours** assignés
4. **Plus d'erreur** "Enseignant non trouvé"

### **Vérification :**
```sql
-- Vérifier que l'enseignant est lié
SELECT e.*, u.email, u.actif 
FROM enseignants e 
JOIN utilisateurs u ON e.utilisateur_id = u.id 
WHERE e.ecole_id = 1 AND e.statut = 'actif';
```

## 🔧 **Dépannage**

### **Problèmes courants :**

#### **1. "Email déjà utilisé"**
- Un compte avec cet email existe déjà
- Utilisez l'option "Lier un compte" à la place

#### **2. "Enseignant non trouvé"**
- Vérifiez que l'enseignant existe dans la table `enseignants`
- Vérifiez que `ecole_id` correspond

#### **3. "Erreur de base de données"**
- Vérifiez la connexion à la base de données
- Vérifiez les permissions sur les tables

### **Logs et Debug :**
- Vérifiez les erreurs PHP dans les logs
- Utilisez la page `classes/debug_my_classes.php` pour diagnostiquer

## 📚 **Fichiers Implémentés**

### **1. `settings/link_teacher_accounts.php`**
- Interface de liaison des comptes
- Gestion des formulaires
- Validation et sécurité

### **2. `classes/my_classes.php`** (corrigé)
- Gestion des erreurs de liaison
- Affichage conditionnel selon le rôle
- Plus de warnings PHP

### **3. `settings/index.php`** (mis à jour)
- Lien vers la page de liaison
- Intégration dans les actions rapides

## 🎯 **Prochaines Étapes**

### **Après la liaison des comptes :**
1. **Les enseignants peuvent se connecter**
2. **La page "Mes Classes" fonctionne**
3. **Gestion des notes et présence** accessible
4. **Système complet** opérationnel

### **Maintenance :**
- Vérifier régulièrement les nouveaux enseignants
- Lier automatiquement lors de la création
- Former les administrateurs à cette procédure

---

## 📞 **Support**

Si vous rencontrez des difficultés :
1. Vérifiez ce guide étape par étape
2. Utilisez la page de debug : `classes/debug_my_classes.php`
3. Contactez l'équipe technique
4. Consultez les logs d'erreur

**La solution est maintenant complète et fonctionnelle !** 🎉








