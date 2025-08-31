# Module des Paramètres - Naklass

## Vue d'ensemble

Le module des paramètres permet aux administrateurs et à la direction de configurer et gérer le système Naklass. Il offre une interface centralisée pour la gestion des utilisateurs, la configuration de l'école, la sécurité et la sauvegarde.

## Pages disponibles

### 1. **index.php** - Page principale des paramètres
- **URL**: `/settings/`
- **Permissions**: Admin, Direction
- **Fonctionnalités**:
  - Tableau de bord avec statistiques système
  - Actions rapides vers les différents modules
  - Vue d'ensemble de la configuration

### 2. **users.php** - Gestion des utilisateurs
- **URL**: `/settings/users.php`
- **Permissions**: Admin, Direction
- **Fonctionnalités**:
  - Liste des utilisateurs avec filtres
  - Statistiques par rôle
  - Gestion des permissions
  - Création et modification d'utilisateurs

### 3. **school.php** - Configuration de l'école
- **URL**: `/settings/school.php`
- **Permissions**: Admin, Direction
- **Fonctionnalités**:
  - Informations générales de l'établissement
  - Statistiques de l'école
  - Actions rapides vers la gestion

### 4. **security.php** - Paramètres de sécurité
- **URL**: `/settings/security.php`
- **Permissions**: Admin, Direction
- **Fonctionnalités**:
  - Configuration de l'authentification
  - Gestion des autorisations
  - Protection des données
  - Surveillance et audit

### 5. **backup.php** - Sauvegarde du système
- **URL**: `/settings/backup.php`
- **Permissions**: Admin, Direction
- **Fonctionnalités**:
  - Création de sauvegardes manuelles
  - Configuration de sauvegardes automatiques
  - Gestion du stockage
  - Historique des sauvegardes

### 6. **create_user.php** - Création d'utilisateur
- **URL**: `/settings/create_user.php`
- **Permissions**: Admin, Direction
- **Fonctionnalités**:
  - Formulaire de création d'utilisateur
  - Attribution de rôles
  - Configuration des permissions

### 7. **edit_school.php** - Modification de l'école
- **URL**: `/settings/edit_school.php`
- **Permissions**: Admin, Direction
- **Fonctionnalités**:
  - Modification des informations de l'établissement
  - Mise à jour des coordonnées
  - Configuration des paramètres

## Structure des fichiers

```
settings/
├── index.php          # Page principale
├── users.php          # Gestion des utilisateurs
├── school.php         # Configuration de l'école
├── security.php       # Paramètres de sécurité
├── backup.php         # Sauvegarde du système
├── create_user.php    # Création d'utilisateur
├── edit_school.php    # Modification de l'école
└── README.md          # Documentation
```

## Fonctionnalités principales

### Gestion des utilisateurs
- **Création** de nouveaux utilisateurs
- **Modification** des informations existantes
- **Gestion des rôles** et permissions
- **Activation/désactivation** des comptes
- **Audit** des actions utilisateur

### Configuration de l'école
- **Informations générales** (nom, code, type)
- **Coordonnées** (adresse, téléphone, email)
- **Paramètres** spécifiques à l'établissement
- **Statistiques** de l'école

### Sécurité
- **Authentification** sécurisée
- **Gestion des rôles** et permissions
- **Protection des données** sensibles
- **Surveillance** et audit en temps réel
- **Logs** de sécurité

### Sauvegarde
- **Sauvegardes automatiques** programmées
- **Sauvegardes manuelles** à la demande
- **Configuration** du stockage
- **Restauration** des données
- **Historique** des sauvegardes

## Permissions et rôles

### Administrateur (admin)
- Accès complet à tous les modules
- Création et suppression d'utilisateurs
- Configuration système avancée
- Gestion des sauvegardes

### Direction
- Accès à la plupart des modules
- Gestion des utilisateurs (lecture/modification)
- Configuration de l'école
- Consultation des paramètres de sécurité

### Autres rôles
- Accès limité ou inexistant selon les besoins

## Intégration avec le système

### Sidebar
Le module est accessible via l'onglet "Paramètres" dans la sidebar principale, visible uniquement pour les administrateurs et la direction.

### Navigation
- Navigation cohérente avec le reste du système
- Boutons de retour vers les pages parentes
- Liens vers les modules connexes

### Style
- Utilisation du même design que le dashboard principal
- Cartes de statistiques cohérentes
- Animations et transitions fluides
- Interface responsive

## Développement futur

### Fonctionnalités prévues
- **Import/Export** d'utilisateurs
- **Synchronisation** avec des services externes
- **API** pour la gestion des paramètres
- **Notifications** de sécurité
- **Rapports** détaillés

### Améliorations techniques
- **Cache** des paramètres
- **Validation** avancée des données
- **Tests** automatisés
- **Documentation** API

## Support et maintenance

### Dépendances
- `../includes/functions.php` - Fonctions utilitaires
- `../assets/css/dashboard.css` - Styles du dashboard
- `../assets/css/common.css` - Styles communs
- `../assets/css/naklass-theme.css` - Thème Naklass

### Base de données
- Table `utilisateurs` - Gestion des utilisateurs
- Table `ecoles` - Configuration des écoles
- Tables de logs pour l'audit

### Sécurité
- Vérification des permissions à chaque page
- Validation des entrées utilisateur
- Protection contre les injections SQL
- Gestion sécurisée des sessions

## Utilisation

1. **Accéder** au module via la sidebar
2. **Naviguer** entre les différentes sections
3. **Configurer** les paramètres selon les besoins
4. **Sauvegarder** les modifications
5. **Vérifier** que les changements sont appliqués

## Dépannage

### Problèmes courants
- **Permissions insuffisantes** : Vérifier le rôle de l'utilisateur
- **Erreurs de base de données** : Vérifier la connexion et les tables
- **Problèmes d'affichage** : Vérifier les fichiers CSS et JavaScript

### Logs
- Les erreurs sont enregistrées dans les logs PHP
- Consulter les logs de sécurité pour les tentatives d'accès
- Vérifier les logs de sauvegarde pour les opérations de sauvegarde

---

*Dernière mise à jour : Août 2025*
*Version : 1.0*

