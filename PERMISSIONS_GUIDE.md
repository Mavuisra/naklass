# 🔒 Guide des Permissions par Rôle

## 📋 **Principe de Sécurité**

Chaque utilisateur n'a accès qu'aux fonctionnalités nécessaires à son rôle. Cette approche garantit la sécurité et l'intégrité des données.

---

## 👑 **Administrateur (admin)**

### **✅ Accès Complet**
- **Gestion des Classes** : Créer, modifier, supprimer, voir toutes les classes
- **Gestion des Élèves** : Inscrire, désinscrire, modifier les informations
- **Gestion des Cours** : Créer, modifier, supprimer, assigner aux classes
- **Gestion des Utilisateurs** : Créer, modifier, supprimer tous les comptes
- **Gestion des Enseignants** : Assigner, réassigner, lier les comptes
- **Gestion des Notes** : Accès à toutes les notes de toutes les classes
- **Gestion de la Présence** : Accès à toutes les présences
- **Gestion Financière** : Accès complet aux finances
- **Paramètres** : Configuration complète de l'école

### **🔧 Actions Disponibles**
- Tous les boutons d'action sont visibles
- Accès à toutes les pages d'administration
- Possibilité de modifier la structure de l'école

---

## 🎓 **Direction (direction)**

### **✅ Même niveau que l'Administrateur**
- **Gestion des Classes** : Créer, modifier, supprimer, voir toutes les classes
- **Gestion des Élèves** : Inscrire, désinscrire, modifier les informations
- **Gestion des Cours** : Créer, modifier, supprimer, assigner aux classes
- **Gestion des Utilisateurs** : Créer, modifier, supprimer tous les comptes
- **Gestion des Enseignants** : Assigner, réassigner, lier les comptes
- **Gestion des Notes** : Accès à toutes les notes de toutes les classes
- **Gestion de la Présence** : Accès à toutes les présences
- **Gestion Financière** : Accès complet aux finances
- **Paramètres** : Configuration complète de l'école

### **🔧 Actions Disponibles**
- Tous les boutons d'action sont visibles
- Accès à toutes les pages d'administration
- Possibilité de modifier la structure de l'école

---

## 👨‍🏫 **Enseignant (enseignant)**

### **✅ Accès Pédagogique Limité**
- **Voir ses Classes** : Seulement les classes où il enseigne
- **Voir ses Cours** : Seulement les cours qui lui sont assignés
- **Gestion des Notes** : Saisir et modifier les notes de ses élèves
- **Gestion de la Présence** : Marquer la présence dans ses classes
- **Voir les Élèves** : Lecture seule des listes d'élèves de ses classes

### **❌ Actions NON Autorisées**
- **PAS** de création de classes
- **PAS** de modification de classes
- **PAS** d'inscription d'élèves
- **PAS** de gestion des inscriptions
- **PAS** de création/modification de cours
- **PAS** d'assignation de cours aux classes
- **PAS** de gestion des utilisateurs
- **PAS** d'accès aux finances
- **PAS** de modification des paramètres

### **🔧 Actions Disponibles**
- Bouton "Voir" pour les classes (lecture seule)
- Bouton "Gérer les Notes" pour ses cours
- Bouton "Gérer la Présence" pour ses classes
- Bouton "Voir les Élèves" (lecture seule)

---

## 📝 **Secrétaire (secretaire)**

### **✅ Accès Administratif Intermédiaire**
- **Voir les Classes** : Lecture de toutes les classes
- **Gestion des Élèves** : Inscrire, désinscrire, modifier les informations
- **Gestion des Inscriptions** : Valider, annuler les inscriptions
- **Voir les Cours** : Lecture des cours assignés aux classes
- **Gestion des Présences** : Accès aux présences de toutes les classes

### **❌ Actions NON Autorisées**
- **PAS** de création de classes
- **PAS** de modification de classes
- **PAS** de création/modification de cours
- **PAS** d'assignation de cours aux classes
- **PAS** de gestion des utilisateurs
- **PAS** d'accès aux finances
- **PAS** de modification des paramètres

### **🔧 Actions Disponibles**
- Bouton "Gérer les Élèves" pour toutes les classes
- Bouton "Voir" pour les classes (lecture seule)
- Bouton "Gérer la Présence" pour toutes les classes

---

## 💰 **Caissier (caissier)**

### **✅ Accès Financier Limité**
- **Gestion des Paiements** : Enregistrer, modifier, annuler les paiements
- **Gestion des Factures** : Créer, modifier, envoyer les factures
- **Gestion des Élèves** : Voir les informations financières des élèves
- **Rapports Financiers** : Générer des rapports de paiement

### **❌ Actions NON Autorisées**
- **PAS** d'accès aux classes
- **PAS** d'accès aux cours
- **PAS** de gestion des inscriptions
- **PAS** de gestion des utilisateurs
- **PAS** de modification des paramètres

---

## 🔐 **Système de Sécurité**

### **1. Vérification des Rôles**
```php
// Exemple de vérification
requireRole(['admin', 'direction']); // Seuls admin et direction
requireRole(['enseignant']);         // Seuls les enseignants
```

### **2. Contrôle d'Accès aux Données**
```php
// Les enseignants ne voient que leurs classes
if (hasRole('enseignant')) {
    $query = "WHERE enseignant_id = :enseignant_id";
}
```

### **3. Interface Conditionnelle**
```php
// Boutons visibles selon le rôle
<?php if (hasRole(['admin', 'direction'])): ?>
    <a href="edit.php">Modifier</a>
<?php endif; ?>
```

---

## 📱 **Pages et Permissions**

| Page | Admin | Direction | Enseignant | Secrétaire | Caissier |
|------|-------|-----------|------------|------------|----------|
| `classes/index.php` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `classes/create.php` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `classes/edit.php` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `classes/view.php` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `classes/students.php` | ✅ | ✅ | ❌ | ✅ | ❌ |
| `classes/my_classes.php` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `grades/` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `presence/` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `finance/` | ✅ | ✅ | ❌ | ❌ | ✅ |
| `settings/` | ✅ | ✅ | ❌ | ❌ | ❌ |

---

## 🚨 **Sécurité Implémentée**

### **1. Vérification des Sessions**
- Authentification obligatoire sur toutes les pages
- Vérification de l'école de l'utilisateur
- Expiration automatique des sessions

### **2. Validation des Données**
- Sanitisation des entrées utilisateur
- Validation des paramètres GET/POST
- Protection contre les injections SQL

### **3. Contrôle d'Accès**
- Vérification des rôles avant affichage
- Redirection automatique si non autorisé
- Logs des tentatives d'accès non autorisées

---

## 🧪 **Test des Permissions**

Pour tester que les permissions fonctionnent correctement :

1. **Connectez-vous avec un compte enseignant**
2. **Accédez à** `classes/test_permissions.php`
3. **Vérifiez que seules les actions autorisées sont visibles**

---

## 📞 **Support et Questions**

Si vous avez des questions sur les permissions ou si vous constatez un problème de sécurité :

1. **Vérifiez d'abord** ce guide
2. **Testez avec** `classes/test_permissions.php`
3. **Contactez l'administration** si le problème persiste

---

## 🔄 **Mise à Jour des Permissions**

Les permissions sont définies dans :
- `includes/functions.php` - Fonctions de vérification
- Chaque page individuelle - Vérifications spécifiques
- `includes/sidebar.php` - Affichage conditionnel du menu

**⚠️ Ne modifiez jamais les permissions sans comprendre les implications de sécurité !**











