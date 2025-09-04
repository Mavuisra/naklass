# Guide - Création d'École par Visiteur

## Vue d'ensemble

Le système de création d'école par visiteur permet aux nouveaux utilisateurs de créer leur établissement scolaire directement depuis la page d'accueil, avec validation automatique et envoi des identifiants d'administration par email.

## Fonctionnalités

### ✅ Création Automatique
- **Validation immédiate** : L'école est créée et validée automatiquement
- **Activation instantanée** : L'école est immédiatement active
- **Compte administrateur** : Création automatique du compte admin

### ✅ Envoi d'Identifiants
- **Email automatique** : Les identifiants sont envoyés par email
- **Template professionnel** : Email HTML avec instructions
- **Sécurité** : Mot de passe généré par l'utilisateur

### ✅ Configuration Complète
- **Niveaux par défaut** : Maternelle, Primaire, Collège, Lycée
- **Structure prête** : Tables et relations configurées
- **Accès immédiat** : Connexion possible dès la création

## Flux de Création

### 1. Détection du Visiteur
```
Visiteur arrive sur index.php
↓
Détection automatique (cookie)
↓
Redirection vers visitor_create_school.php
```

### 2. Formulaire de Création
```
Informations École:
- Nom de l'école *
- Adresse *
- Ville *
- Province *
- Téléphone *
- Email *

Directeur & Administration:
- Nom du directeur *
- Prénom du directeur *
- Téléphone du directeur
- Email du directeur *
- Mot de passe administrateur *
```

### 3. Traitement Automatique
```
Validation des données
↓
Création de l'école (statut: validée, active)
↓
Création du compte administrateur
↓
Création des niveaux par défaut
↓
Envoi des identifiants par email
↓
Affichage des identifiants à l'écran
```

## Structure de la Base de Données

### Table `ecoles`
```sql
- validation_status: 'validée' (automatique)
- statut_ecole: 'active' (automatique)
- created_by_visitor: ID du visiteur
- date_creation_ecole: NOW()
- date_validation: NOW()
- validee_par: 'system_auto'
```

### Table `utilisateurs`
```sql
- role: 'admin'
- statut: 'actif'
- nom_utilisateur: 'admin_[nom_ecole]'
- mot_de_passe: hash du mot de passe fourni
- created_by_visitor: ID du visiteur
```

### Table `niveaux`
```sql
Niveaux créés automatiquement:
- Maternelle (ordre: 1)
- Primaire (ordre: 2)
- Collège (ordre: 3)
- Lycée (ordre: 4)
```

## Fichiers Impliqués

### Pages Principales
- `visitor_create_school.php` - Page de création d'école
- `index.php` - Redirection automatique
- `auth/login.php` - Connexion après création

### Gestionnaires
- `includes/EmailManager.php` - Envoi des emails
- `config/email.php` - Configuration SMTP

### Tests
- `test_visitor_school_creation.php` - Tests du système

## Configuration Email

### Variables Requises
```php
SMTP_HOST = 'smtp.gmail.com'
SMTP_USERNAME = 'votre-email@gmail.com'
SMTP_PASSWORD = 'votre-mot-de-passe-app'
SMTP_PORT = 587
SMTP_SECURE = 'tls'
EMAIL_FROM = 'noreply@naklass.com'
```

### Template Email
- **Sujet** : "Votre école a été créée - Identifiants d'administration"
- **Contenu** : HTML + texte avec identifiants
- **Lien** : Direct vers la page de connexion

## Sécurité

### Validation des Données
- **Champs obligatoires** : Validation côté client et serveur
- **Emails uniques** : Vérification d'unicité
- **Mot de passe** : Minimum 6 caractères
- **Sanitisation** : Toutes les entrées sont nettoyées

### Gestion des Erreurs
- **Transactions** : Rollback en cas d'erreur
- **Logs** : Enregistrement des erreurs
- **Messages** : Affichage des erreurs à l'utilisateur

## Utilisation

### Pour les Visiteurs
1. **Accès** : Aller sur la page d'accueil
2. **Redirection** : Automatique vers la création d'école
3. **Formulaire** : Remplir les informations
4. **Validation** : Clic sur "Créer mon École"
5. **Réception** : Email avec identifiants
6. **Connexion** : Utiliser les identifiants reçus

### Pour les Administrateurs
1. **Test** : Utiliser `test_visitor_school_creation.php`
2. **Monitoring** : Vérifier les logs d'email
3. **Support** : Aider les utilisateurs si nécessaire

## Tests

### Script de Test
```bash
http://votre-domaine/naklass/test_visitor_school_creation.php
```

### Vérifications
- ✅ Structure des tables
- ✅ Configuration email
- ✅ Fichiers nécessaires
- ✅ Méthodes EmailManager
- ✅ Permissions et accès

## Dépannage

### Problèmes Courants

#### Email non envoyé
- Vérifier la configuration SMTP
- Tester avec `test_visitor_school_creation.php`
- Vérifier les logs d'erreur

#### Erreur de création
- Vérifier les permissions de base de données
- Vérifier la structure des tables
- Consulter les logs PHP

#### Redirection incorrecte
- Vérifier les cookies
- Nettoyer le cache du navigateur
- Vérifier la configuration des sessions

## Avantages

### Pour les Utilisateurs
- **Simplicité** : Création en une seule étape
- **Rapidité** : Validation automatique
- **Sécurité** : Identifiants par email
- **Immédiat** : Accès instantané

### Pour les Administrateurs
- **Automatisation** : Moins de travail manuel
- **Traçabilité** : Logs complets
- **Scalabilité** : Gestion de nombreux utilisateurs
- **Professionnalisme** : Processus fluide

## Statut
✅ **SYSTÈME OPÉRATIONNEL** - Le système de création d'école par visiteur est entièrement fonctionnel et prêt à être utilisé.
