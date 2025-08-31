# Configuration du Système d'Emails - Naklass

## Vue d'ensemble

Ce système permet d'envoyer automatiquement des emails de confirmation quand un visiteur crée une école. Il utilise PHPMailer avec une configuration SMTP pour l'envoi d'emails.

## Configuration Requise

### 1. Serveur SMTP

**Serveur principal :**
- Hôte : `mail.impact-entreprises.net`
- Port : 465 (SSL) ou 587 (TLS)
- Sécurité : SSL/TLS
- Authentification : Oui

**Serveur alternatif :**
- Hôte : `mail70.lwspanel.com`
- Port : 587 (TLS)
- Sécurité : TLS
- Authentification : Oui

### 2. Compte Email

- **Email :** `naklasse@impact-entreprises.net`
- **Mot de passe :** [À configurer dans `config/email.php`]

## Installation

### Option 1 : Via Composer (Recommandé)

```bash
# Installer Composer si pas déjà installé
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Installer les dépendances
composer install
```

### Option 2 : Installation Manuelle

1. Télécharger PHPMailer depuis [GitHub](https://github.com/PHPMailer/PHPMailer)
2. Créer le dossier `lib/PHPMailer/`
3. Copier les fichiers `PHPMailer.php`, `SMTP.php`, et `Exception.php`

## Configuration

### 1. Fichier de Configuration Email

Modifiez `config/email.php` :

```php
// Ajouter le mot de passe réel
define('SMTP_PASSWORD', 'votre_mot_de_passe_ici');

// Activer le debug si nécessaire
define('EMAIL_DEBUG', true);
```

### 2. Vérification des Permissions

```bash
# Créer le dossier de logs
mkdir -p logs
chmod 755 logs

# Vérifier que PHP peut écrire dans le dossier
chown www-data:www-data logs  # Sur Linux/Unix
```

## Test de la Configuration

### 1. Test de Connexion

Accédez à `test_email_config.php` pour :
- Vérifier la configuration SMTP
- Tester la connexion
- Voir les logs d'erreurs

### 2. Test d'Envoi

Ajoutez `?test_send=1` à l'URL pour tester l'envoi d'emails.

## Fonctionnalités

### 1. Email de Confirmation au Visiteur

- **Sujet :** "Confirmation de création d'école - [Nom de l'école]"
- **Contenu :** 
  - Informations de l'école
  - Code d'école
  - Prochaines étapes
  - Lien vers Naklass

### 2. Notification à l'Administrateur

- **Sujet :** "Nouvelle demande de création d'école - [Nom de l'école]"
- **Contenu :**
  - Détails de la demande
  - Lien direct vers la validation
  - Informations du visiteur

### 3. Gestion des Erreurs

- Tentative avec le serveur principal
- Basculement automatique vers le serveur alternatif
- Logs détaillés des erreurs
- Gestion gracieuse des échecs

## Structure des Fichiers

```
config/
├── email.php              # Configuration SMTP
└── database.php           # Configuration base de données

includes/
└── EmailManager.php       # Classe de gestion des emails

templates/emails/          # Templates d'emails (optionnel)
logs/                      # Logs d'emails
composer.json              # Dépendances PHP
```

## Dépannage

### Problèmes Courants

1. **Erreur de connexion SMTP**
   - Vérifier le mot de passe
   - Vérifier les ports (465/587)
   - Vérifier la sécurité SSL/TLS

2. **Email non reçu**
   - Vérifier le dossier spam
   - Vérifier les logs d'erreurs
   - Tester avec un autre serveur SMTP

3. **Erreur PHPMailer**
   - Vérifier l'installation de PHPMailer
   - Vérifier les permissions des dossiers
   - Activer le mode debug

### Logs et Debug

- **Fichier de log :** `logs/email.log`
- **Debug SMTP :** Activer dans `config/email.php`
- **Erreurs PHP :** Vérifier les logs d'erreurs du serveur

## Sécurité

### Bonnes Pratiques

1. **Ne jamais commiter le mot de passe SMTP**
2. **Utiliser des variables d'environnement en production**
3. **Limiter l'accès au dossier de logs**
4. **Valider toutes les entrées utilisateur**

### Variables d'Environnement (Production)

```php
// Dans config/email.php
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? '');
```

## Support

Pour toute question ou problème :
1. Vérifier les logs d'erreurs
2. Tester la configuration avec `test_email_config.php`
3. Vérifier la documentation PHPMailer
4. Contacter l'équipe de support

## Mise à Jour

```bash
# Mettre à jour les dépendances
composer update

# Vérifier la compatibilité
composer check-platform-reqs
```
