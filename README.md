# Naklass - Système de Gestion Scolaire

Une application web complète de gestion scolaire développée avec PHP, HTML, CSS et JavaScript, utilisant MySQL comme base de données.

## 🚀 Fonctionnalités

### 📊 Tableau de Bord
- Vue d'ensemble des statistiques de l'établissement
- Graphiques et indicateurs clés
- Actions rapides et notifications
- Interface responsive et moderne

### 👥 Gestion des Élèves
- Inscription et gestion des élèves
- Informations personnelles et médicales
- Gestion des tuteurs/parents
- Photos et documents joints
- Historique scolaire

### 💰 Gestion Financière
- Enregistrement des paiements
- Types de frais configurables
- Remises et bourses
- Échéanciers personnalisés
- Situation financière des élèves
- Génération de reçus

### 📚 Notes et Bulletins
- Création d'évaluations
- Saisie de notes
- Calcul automatique des moyennes
- Génération de bulletins
- Classements par classe
- Statistiques de performance

### 🏫 Gestion Administrative
- Classes et niveaux
- Enseignants et matières
- Périodes scolaires
- Utilisateurs et rôles
- Rapports et exports

## 🛠️ Technologies Utilisées

- **Backend:** PHP 8.0+
- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS:** Bootstrap 5.3
- **Icônes:** Bootstrap Icons
- **Base de données:** MySQL 8.0+
- **Serveur Web:** Apache/Nginx
- **Charts:** Chart.js

## 📋 Prérequis

- PHP 8.0 ou supérieur
- MySQL 8.0 ou supérieur
- Serveur web (Apache, Nginx)
- Extensions PHP requises :
  - PDO
  - PDO_MySQL
  - mbstring
  - openssl
  - json

## ⚙️ Installation

### 1. Cloner le projet
```bash
git clone https://github.com/votre-repo/naklass.git
cd naklass
```

### 2. Configuration de la base de données

1. Créer une base de données MySQL :
```sql
CREATE DATABASE naklass_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importer le schéma de base de données :
```bash
mysql -u root -p naklass_db < database/naklass_schema.sql
```

### 3. Configuration de l'application

1. Éditer le fichier `config/database.php` :
```php
private $host = 'localhost';
private $db_name = 'naklass_db';
private $username = 'votre_utilisateur';
private $password = 'votre_mot_de_passe';
```

2. Configurer les permissions des dossiers :
```bash
chmod 755 uploads/
chmod 644 config/database.php
```

### 4. Accès à l'application

1. Ouvrir votre navigateur et aller à : `http://localhost/naklass`
2. Utiliser les identifiants par défaut :
   - **Email:** admin@naklass.cd
   - **Mot de passe:** password (à changer lors de la première connexion)

## 📁 Structure du Projet

```
naklass/
├── config/              # Configuration de l'application
│   └── database.php
├── includes/            # Fichiers PHP inclus
│   ├── functions.php
│   └── sidebar.php
├── assets/              # Ressources statiques
│   ├── css/            # Feuilles de style
│   ├── js/             # Scripts JavaScript
│   └── images/         # Images et icônes
├── auth/               # Authentification
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   └── check_session.php
├── students/           # Gestion des élèves
│   ├── index.php
│   ├── add.php
│   ├── view.php
│   └── edit.php
├── finance/            # Gestion financière
│   ├── index.php
│   ├── payment.php
│   └── reports.php
├── grades/             # Notes et bulletins
│   ├── index.php
│   ├── notes_entry.php
│   └── bulletins.php
├── database/           # Scripts SQL
│   ├── naklass_schema.sql
│   ├── 02_module_inscription.sql
│   ├── 03_module_paiement.sql
│   └── 04_module_notes_bulletins.sql
└── uploads/            # Fichiers uploadés
    ├── photos/
    └── documents/
```

## 👤 Rôles et Permissions

### Administrateur
- Accès complet à toutes les fonctionnalités
- Gestion des utilisateurs et paramètres
- Configuration du système

### Direction
- Gestion administrative et pédagogique
- Consultation des rapports
- Validation des bulletins

### Enseignant
- Saisie des notes et évaluations
- Consultation des élèves de ses classes
- Génération des bulletins de ses matières

### Secrétaire
- Gestion des élèves et inscriptions
- Consultation des paiements
- Administration générale

### Caissier
- Gestion des paiements
- Consultation des frais
- Génération des reçus

## 🔧 Configuration Avancée

### Variables d'Environnement

Les principales constantes sont définies dans `config/database.php` :

```php
define('APP_NAME', 'Naklass - Système de Gestion Scolaire');
define('BASE_URL', 'http://localhost/naklass/');
define('UPLOAD_PATH', 'uploads/');
define('BCRYPT_COST', 12);
define('SESSION_LIFETIME', 3600);
```

### Personnalisation

1. **Logo et nom de l'établissement :** Modifier dans la table `ecoles`
2. **Types de frais :** Configurer dans la table `types_frais`
3. **Cycles et niveaux :** Adapter selon votre système éducatif
4. **Devises :** Modifier la constante `CURRENCIES`

## 📱 Interface Mobile

L'application est entièrement responsive et s'adapte aux :
- Smartphones (iOS/Android)
- Tablettes
- Ordinateurs de bureau
- Écrans haute résolution

## 🔒 Sécurité

- Hachage des mots de passe avec bcrypt
- Protection contre les injections SQL (PDO)
- Validation et sanitisation des données
- Sessions sécurisées
- Protection CSRF
- Contrôle d'accès basé sur les rôles

## 📈 Performance

- Requêtes optimisées avec index
- Pagination des listes
- Cache des données fréquentes
- Compression des assets
- Lazy loading des images

## 🐛 Dépannage

### Problèmes courants

1. **Erreur de connexion à la base de données**
   - Vérifier les paramètres dans `config/database.php`
   - S'assurer que MySQL est démarré

2. **Page blanche après installation**
   - Activer l'affichage des erreurs PHP
   - Vérifier les logs du serveur web

3. **Problème d'upload de fichiers**
   - Vérifier les permissions du dossier `uploads/`
   - Augmenter `upload_max_filesize` dans php.ini

### Logs

Les erreurs sont enregistrées dans :
- Logs du serveur web
- Logs PHP
- Table `user_logs` pour les actions utilisateur

## 📞 Support

Pour obtenir de l'aide :
1. Consulter cette documentation
2. Vérifier les [Issues GitHub](https://github.com/votre-repo/naklass/issues)
3. Contacter l'équipe de développement

## 🤝 Contribution

Les contributions sont les bienvenues ! Veuillez :
1. Fork le projet
2. Créer une branche pour votre fonctionnalité
3. Commiter vos changements
4. Pousser vers la branche
5. Ouvrir une Pull Request

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🎯 Roadmap

### Version 2.0
- [ ] API REST complète
- [ ] Application mobile native
- [ ] Notifications push
- [ ] Intégration SMS/Email
- [ ] Module de e-learning
- [ ] Gestion des emplois du temps
- [ ] Module de bibliothèque
- [ ] Système de messagerie interne

### Version 1.1
- [ ] Génération PDF des bulletins
- [ ] Export Excel avancé
- [ ] Sauvegarde automatique
- [ ] Multi-langue (FR/EN)
- [ ] Thèmes personnalisables

## 📊 Statistiques

- **Lignes de code :** ~15,000
- **Fichiers :** 50+
- **Tables de base de données :** 20+
- **Fonctionnalités :** 100+

---

**Développé avec ❤️ par l'équipe Naklass**

Pour toute question technique, contactez : support@naklass.cd
