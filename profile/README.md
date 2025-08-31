# 👤 Module de Profil Utilisateur

Le module de profil utilisateur de Naklass permet aux utilisateurs de gérer leurs informations personnelles, leur sécurité et leurs préférences.

## 🎯 Fonctionnalités

### 📋 **Page de Profil (index.php)**
- **Visualisation complète** des informations personnelles
- **Informations d'établissement** et de rôle
- **Statistiques de sécurité** (tentatives de connexion, dernière connexion)
- **Aperçu de la photo** de profil
- **Actions rapides** vers les pages de modification

### ✏️ **Modification du Profil (edit.php)**
- **Édition des informations** personnelles (nom, prénom, email, téléphone)
- **Upload de photo** de profil avec validation
- **Prévisualisation** en temps réel
- **Suppression** de photo de profil
- **Validation côté client** et serveur

### 🔒 **Changement de Mot de Passe (change-password.php)**
- **Vérification** du mot de passe actuel
- **Indicateur de force** du nouveau mot de passe
- **Validation en temps réel** de la correspondance
- **Conseils de sécurité** intégrés
- **Logging** des tentatives de modification

## 🛡️ Sécurité

### **Validation des Données**
- Vérification de format email
- Contrôle de la force du mot de passe
- Validation des types de fichiers (images uniquement)
- Protection contre les uploads malveillants

### **Gestion des Tentatives**
- Compteur de tentatives de connexion échouées
- Logging des actions utilisateur
- Réinitialisation automatique après succès

### **Upload Sécurisé**
- Types de fichiers autorisés : JPG, PNG, GIF
- Taille maximale : 5MB
- Noms de fichiers uniques avec timestamp
- Suppression automatique des anciennes photos

## 📁 Structure des Fichiers

```
profile/
├── index.php              # Page principale du profil
├── edit.php               # Modification du profil
├── change-password.php    # Changement de mot de passe
├── profile.css           # Styles spécifiques
├── profile.js            # JavaScript interactif
├── uploads/              # Dossier des photos de profil
└── README.md             # Documentation
```

## 🎨 Design et UX

### **Interface Utilisateur**
- Design moderne avec couleurs Naklass (#0077b6)
- Interface responsive (mobile-first)
- Animations fluides et microinteractions
- Feedback visuel en temps réel

### **Expérience Utilisateur**
- Navigation intuitive
- Validation en temps réel
- Messages de confirmation clairs
- Prévisualisation immédiate

## 💻 Technologies Utilisées

### **Backend (PHP)**
- **PDO** pour la base de données
- **Password hashing** sécurisé (bcrypt)
- **File upload** validation
- **Session management**

### **Frontend**
- **Bootstrap 5.3** pour la responsivité
- **JavaScript ES6+** pour l'interactivité
- **CSS Grid/Flexbox** pour la mise en page
- **CSS Variables** pour le theming

### **Base de Données**
- Colonnes utilisateur étendues (photo_path, tentatives_connexion)
- Relations avec ecoles et roles
- Soft delete avec statut
- Timestamps automatiques

## 🚀 Installation et Configuration

### **1. Structure de Base de Données**
Assurez-vous que les colonnes suivantes existent dans la table `utilisateurs` :
```sql
ALTER TABLE utilisateurs 
ADD COLUMN photo_path VARCHAR(500) NULL,
ADD COLUMN tentatives_connexion INT DEFAULT 0,
ADD COLUMN derniere_tentative DATETIME NULL;
```

### **2. Permissions de Dossier**
```bash
chmod 755 profile/uploads/
chown www-data:www-data profile/uploads/
```

### **3. Configuration PHP**
Vérifiez les paramètres dans `php.ini` :
```ini
file_uploads = On
upload_max_filesize = 5M
post_max_size = 5M
max_file_uploads = 1
```

## 🔧 Utilisation

### **Accès au Profil**
Les utilisateurs peuvent accéder à leur profil via :
- **Sidebar** : Section utilisateur en bas
- **URL directe** : `/profile/`
- **Navigation** : Boutons dans l'en-tête

### **Modification du Profil**
1. Cliquer sur "Modifier" dans la page de profil
2. Remplir les champs souhaités
3. Valider et enregistrer

### **Upload de Photo**
1. Aller dans la section "Photo de Profil"
2. Sélectionner un fichier (ou drag & drop)
3. Prévisualiser et confirmer
4. Cliquer sur "Télécharger"

### **Changement de Mot de Passe**
1. Accéder à "Changer le mot de passe"
2. Saisir le mot de passe actuel
3. Créer un nouveau mot de passe fort
4. Confirmer et valider

## 🎯 Fonctionnalités Avancées

### **JavaScript Interactif**
- **Drag & Drop** pour l'upload de photos
- **Validation temps réel** des formulaires
- **Indicateur de force** du mot de passe
- **Notifications** toast personnalisées
- **Auto-logout** sur inactivité

### **Responsive Design**
- **Mobile-first** approach
- **Breakpoints** Bootstrap optimisés
- **Touch-friendly** interfaces
- **Adaptive** typography et spacing

### **Accessibilité**
- **ARIA labels** appropriés
- **Keyboard navigation** complète
- **Screen reader** friendly
- **High contrast** support

## 🔍 API et Intégrations

### **Fonctions Utilitaires**
```php
// Vérifier si une colonne existe
columnExists('utilisateurs', 'photo_path', $db)

// Mise à jour sécurisée des tentatives
updateLoginAttempts($user_id, $success, $db)

// Logging des actions
logUserAction('PROFILE_UPDATE', 'Description')
```

### **JavaScript Global**
```javascript
// Gestionnaire de profil
ProfileManager.init()

// Notifications
ProfileManager.showNotification(message, type)

// Validation de fichiers
ProfileManager.validateFileUpload(event)
```

## 📊 Monitoring et Logs

### **Actions Trackées**
- Connexions/déconnexions
- Modifications de profil
- Changements de mot de passe
- Uploads de photos
- Tentatives échouées

### **Métriques de Sécurité**
- Nombre de tentatives de connexion
- Dernière activité utilisateur
- Historique des modifications
- Alertes de sécurité

## 🐛 Résolution de Problèmes

### **Problèmes Courants**

**Upload de photo échoue :**
- Vérifier les permissions du dossier `uploads/`
- Contrôler la taille du fichier (max 5MB)
- Vérifier les paramètres PHP `upload_max_filesize`

**Colonnes manquantes :**
- Exécuter le script `update_database_structure.php`
- Ou utiliser les requêtes SQL d'ajout de colonnes

**Session expirée :**
- Vérifier la configuration `SESSION_LIFETIME`
- Contrôler les cookies de session
- Adapter les paramètres de sécurité

## 📈 Améliorations Futures

### **Fonctionnalités Prévues**
- **Authentification à deux facteurs** (2FA)
- **Historique des modifications** détaillé
- **Préférences utilisateur** avancées
- **Thèmes personnalisés** par utilisateur
- **Notifications push** en temps réel

### **Optimisations Techniques**
- **Cache des avatars** pour les performances
- **Compression d'images** automatique
- **CDN** pour les ressources statiques
- **Progressive Web App** (PWA) features

---

**Version :** 1.0.0  
**Dernière mise à jour :** <?= date('d/m/Y') ?>  
**Développé pour :** Naklass - Système de Gestion Scolaire
