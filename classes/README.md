# 🏫 Module Gestion des Classes

## 📋 Vue d'ensemble

Le module de gestion des classes permet aux établissements scolaires de créer, organiser et gérer leurs classes, niveaux scolaires et l'assignation des élèves.

## ✨ Fonctionnalités

### 🎯 **Gestion des Classes**
- **Création de classes** avec informations complètes
- **Assignation d'enseignants** principaux
- **Gestion des capacités** et occupation
- **Horaires et salles** de classe
- **Statuts de classe** (active, préparation, suspendue)

### 📚 **Niveaux et Sections**
- **Niveaux scolaires** : Maternelle, Primaire, Collège, Lycée, Supérieur
- **Sections/Filières** : Générale, Scientifique, Littéraire, Technique, etc.
- **Classification flexible** selon les besoins de l'établissement

### 👥 **Assignation d'Élèves**
- **Interface intuitive** pour assigner élèves aux classes
- **Contrôle de capacité** automatique
- **Prévention double inscription** dans plusieurs classes
- **Historique des assignations** avec dates

### 🔐 **Sécurité Avancée**
- **Chiffrement des IDs** dans toutes les URLs
- **Validation stricte** des permissions
- **Protection contre IDOR** et énumération
- **Logs automatiques** des actions

## 🗄️ Structure de Base de Données

### Tables Principales

#### **`niveaux`** - Niveaux Scolaires
```sql
- id: Identifiant unique
- ecole_id: NULL pour standards, spécifique sinon
- nom: Nom du niveau (ex: "Primaire")
- ordre: Ordre d'affichage (1, 2, 3...)
```

#### **`sections`** - Sections/Filières
```sql
- id: Identifiant unique
- ecole_id: NULL pour standards, spécifique sinon
- nom: Nom de la section (ex: "Scientifique")
```

#### **`classes`** - Classes
```sql
- id: Identifiant unique
- ecole_id: École propriétaire
- nom: Nom de la classe (unique par école)
- niveau_id: Référence vers le niveau
- section_id: Référence vers la section
- capacite_max: Nombre maximum d'élèves
- enseignant_principal_id: Enseignant responsable
- salle: Numéro/nom de la salle
- horaire_debut/fin: Horaires des cours
- statut: active, preparation, suspendue, fermee
```

#### **`inscriptions`** - Inscriptions Élèves
```sql
- id: Identifiant unique
- eleve_id: Référence vers l'élève
- classe_id: Référence vers la classe
- date_inscription: Date d'inscription
- date_fin: Date de fin (si applicable)
- statut: validée, en_attente, annulée, suspendue
```

### Vues Utiles

#### **`vue_classes_completes`**
Vue enrichie avec toutes les informations de classe :
- Données de base de la classe
- Informations niveau et section
- Détails enseignant principal
- Statistiques d'occupation
- Indicateur de disponibilité

#### **`vue_eleves_classes`**
Vue des élèves avec leur classe actuelle :
- Informations complètes de l'élève
- Classe assignée avec détails
- Date d'inscription dans la classe

### Triggers de Sécurité

#### **Contrôle de Capacité**
```sql
before_inscription_insert: Vérifie que la capacité max n'est pas dépassée
```

#### **Prévention Double Inscription**
```sql
before_inscription_insert_unique: Empêche l'inscription simultanée dans plusieurs classes
```

## 🔧 Pages et Fonctionnalités

### **📁 Pages Principales**

#### **`index.php`** - Liste des Classes
- **Vue d'ensemble** de toutes les classes
- **Statistiques** : nombre d'élèves, capacité, occupation
- **Actions rapides** : voir, modifier, gérer élèves
- **Filtrage** par niveau, section, statut

#### **`create.php`** - Création de Classe
- **Formulaire complet** avec validation temps réel
- **Aperçu en direct** des informations saisies
- **Sélection niveaux/sections** depuis base de données
- **Assignation enseignant** principal
- **Configuration horaires** et salle

#### **`view.php`** - Détails de Classe
- **Informations complètes** de la classe
- **Liste des élèves** inscrits avec photos
- **Statistiques d'occupation** avec barres visuelles
- **Actions élèves** : carte, profil, retirer
- **Liens vers édition** et gestion

#### **`assign.php`** - Assignation d'Élèves
- **Interface sécurisée** avec IDs chiffrés
- **Sélection interactive** de classe
- **Validation capacité** en temps réel
- **Prévention classes pleines**
- **Notes d'assignation** pour traçabilité

### **🔧 Installation**

#### **Prérequis**
- Tables de base Naklass installées
- Permissions administrateur
- PHP 8.0+ avec OpenSSL
- MySQL/MariaDB 5.7+

#### **Étapes d'Installation**

1. **Accéder au script d'installation**
   ```
   http://localhost/naklass/install_classes_module.php
   ```

2. **Exécution automatique**
   - Vérification des dépendances
   - Création des tables
   - Insertion des données de référence
   - Validation post-installation

3. **Vérification**
   ```
   http://localhost/naklass/classes/index.php
   ```

## 🛡️ Sécurité

### **Chiffrement des IDs**
Tous les liens sensibles utilisent des IDs chiffrés :
```php
// Au lieu de : assign.php?student_id=123
// Utilise : assign.php?student_id=U2FsdGVkX1-abc123def456...
```

### **Validation Stricte**
```php
// Validation avec permissions
$student_id = validateSecureId('student_id', 'eleves', [
    'ecole_id' => $_SESSION['ecole_id']
]);
```

### **Protection Niveaux**
- **École** : Isolation complète des données par établissement
- **Permissions** : Rôles admin, direction, secrétaire requis
- **Validation** : Vérification systematic des accès

## 🎨 Interface Utilisateur

### **Design Moderne**
- **Cards interactives** avec effets hover
- **Gradients colorés** pour différencier les sections
- **Icônes Bootstrap** pour clarté visuelle
- **Responsive design** mobile-first

### **Expérience Utilisateur**
- **Feedback visuel** immédiat
- **Validation temps réel** des formulaires
- **Messages d'erreur** contextuels
- **Navigation intuitive** avec breadcrumbs

### **Accessibilité**
- **Contraste** suffisant pour lisibilité
- **Navigation clavier** complète
- **Écrans lecteurs** compatibles
- **Textes alternatifs** sur images

## 🔄 Workflow Type

### **Création d'une Classe**
1. **Navigation** : Classes → Créer une Classe
2. **Configuration** : Nom, niveau, section, capacité
3. **Assignation** : Enseignant principal, salle, horaires
4. **Validation** : Vérification unicité nom
5. **Création** : Enregistrement avec logs
6. **Redirection** : Vers page de détails

### **Assignation d'Élève**
1. **Sélection** : Élève depuis liste ou inscription
2. **Sécurisation** : ID chiffré dans URL
3. **Affichage** : Classes disponibles avec capacités
4. **Sélection** : Classe avec validation temps réel
5. **Confirmation** : Vérification capacité et unicité
6. **Assignation** : Création inscription avec historique

## 📊 Statistiques et Rapports

### **Métriques Disponibles**
- **Occupation par classe** : Nombre/Capacité/Pourcentage
- **Distribution par niveau** : Répartition des élèves
- **Utilisation enseignants** : Classes assignées par enseignant
- **Évolution temporelle** : Inscriptions par période

### **Exports**
- **Liste classes** : Excel, PDF, CSV
- **Liste élèves par classe** : Avec photos et détails
- **Statistiques** : Graphiques et tableaux
- **Rapports** : Personnalisables selon besoins

## 🔧 Maintenance

### **Sauvegarde**
- **Tables critiques** : classes, inscriptions, niveaux, sections
- **Données liées** : Élèves, enseignants associés
- **Logs** : Actions utilisateurs pour audit

### **Monitoring**
- **Performance** : Temps de réponse pages
- **Utilisation** : Statistiques d'accès
- **Erreurs** : Logs automatiques des problèmes
- **Sécurité** : Tentatives d'accès non autorisé

### **Mises à Jour**
- **Structure** : Évolution schema base données
- **Fonctionnalités** : Nouvelles capacités
- **Sécurité** : Patches et améliorations
- **Interface** : Améliorations UX/UI

## 🆘 Support et Dépannage

### **Problèmes Courants**

#### **Erreur "Classe introuvable"**
- Vérifier que l'ID n'est pas corrompu
- Contrôler les permissions d'accès
- Valider l'appartenance à l'école

#### **"Capacité maximale atteinte"**
- Vérifier le nombre d'élèves actuels
- Ajuster la capacité si nécessaire
- Retirer élèves inactifs

#### **Problèmes de chiffrement**
- Vérifier la configuration OpenSSL
- Contrôler la constante APP_NAME
- Regenerer les clés si nécessaire

### **Logs et Débogage**
```php
// Activer les logs détaillés
error_log("Debug: ID déchiffré = " . $decrypted_id);
logUserAction('DEBUG', 'Information de débogage');
```

### **Contacts Support**
- **Documentation** : Ce fichier README
- **Guide sécurité** : SECURITY_ENCRYPTION_GUIDE.md
- **Code source** : Commenté et documenté

---

*Module Classes v2.0 - Système complet de gestion des classes avec sécurité avancée*
