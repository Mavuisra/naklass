# Gestion de l'École - Naklass

Ce module permet aux administrateurs et membres de la direction de gérer les informations de leur école et les années scolaires.

## Fonctionnalités

### 📊 Tableau de bord principal (`index.php`)
- **Vue d'ensemble** : Affichage des informations de base de l'école
- **Statistiques** : Nombre d'élèves, classes et enseignants
- **Année scolaire active** : Statut de l'année en cours
- **Actions rapides** : Accès direct aux principales fonctionnalités

### ✏️ Modification des informations (`edit.php`)
- **Informations de base** : Nom, sigle, adresse, téléphone, email, site web
- **Informations supplémentaires** : Fax, boîte postale, régime, devise
- **Types d'enseignement** : Maternelle, primaire, secondaire, technique, professionnel, université
- **Langues d'enseignement** : Français, anglais, lingala, kikongo, tshiluba, swahili
- **Direction** : Nom, téléphone et email du directeur
- **Autorisation** : Numéro et date d'autorisation d'ouverture
- **Description** : Description détaillée de l'établissement

### 📅 Gestion des années scolaires (`annees_scolaires.php`)
- **Création** : Créer de nouvelles années scolaires avec dates et description
- **Modification** : Modifier les informations des années existantes
- **Activation** : Activer une année scolaire (désactive automatiquement les autres)
- **Gestion** : Vue d'ensemble de toutes les années scolaires

### 🚪 Clôture de l'année (`end_year.php`)
- **Clôture sécurisée** : Processus de fin d'année avec confirmation
- **Archivage automatique** : 
  - Désactivation de l'année scolaire active
  - Archivage de l'année scolaire
  - Archivage de toutes les classes de cette année
  - Archivage de toutes les inscriptions de cette année
- **Préparation** : Mise en place pour la création d'une nouvelle année

### 📈 Rapports et statistiques (`reports.php`)
- **Statistiques générales** : Effectifs, classes, enseignants
- **Répartition par sexe** : Élèves et enseignants
- **Répartition par cycle** : Classes par niveau d'enseignement
- **Effectifs par classe** : Taux de remplissage et statut des classes
- **Visualisation** : Tableaux et indicateurs colorés

## Structure de la base de données

### Table `ecoles`
- Informations de base de l'école
- Champs étendus pour la configuration complète
- Support multi-écoles avec validation

### Table `annees_scolaires`
- Gestion des années scolaires
- Dates de début et fin
- Statut actif/archivé
- Une seule année active par école

### Tables liées
- `classes` : Classes avec référence à l'année scolaire
- `inscriptions` : Inscriptions des élèves par année
- `eleves` : Élèves de l'école
- `enseignants` : Personnel enseignant

## Sécurité et permissions

### Rôles autorisés
- **Admin** : Accès complet à toutes les fonctionnalités
- **Direction** : Accès complet à toutes les fonctionnalités
- **Autres rôles** : Accès refusé

### Vérifications
- Authentification utilisateur
- Association à une école
- Droits d'accès appropriés
- Validation des données d'entrée

## Utilisation

### 1. Accès au module
- Se connecter avec un compte admin ou direction
- Cliquer sur "École" dans la sidebar
- Accéder au tableau de bord principal

### 2. Configuration initiale
- Remplir les informations de base de l'école
- Créer la première année scolaire
- Activer l'année scolaire

### 3. Gestion courante
- Modifier les informations de l'école si nécessaire
- Créer de nouvelles années scolaires
- Gérer les transitions entre années

### 4. Fin d'année
- Clôturer l'année scolaire active
- Archiver automatiquement les données
- Préparer la nouvelle année

## Notes importantes

### ⚠️ Clôture d'année
- **Action irréversible** : La clôture archive définitivement l'année
- **Confirmation requise** : Double validation avant exécution
- **Archivage complet** : Toutes les données de l'année sont archivées

### 🔄 Années scolaires
- **Une seule active** : Seule une année peut être active à la fois
- **Activation automatique** : L'activation d'une année désactive les autres
- **Gestion des conflits** : Prévention des doublons et incohérences

### 📊 Statistiques
- **Temps réel** : Les statistiques sont calculées à la demande
- **Performance** : Requêtes optimisées avec index appropriés
- **Fiabilité** : Gestion des erreurs et fallbacks

## Support technique

### Logs d'erreur
- Toutes les erreurs sont loggées dans les logs système
- Messages d'erreur utilisateur appropriés
- Traçabilité des actions importantes

### Débogage
- Vérification des permissions utilisateur
- Validation des données de session
- Contrôle de l'intégrité des données

## Évolutions futures

### Fonctionnalités prévues
- Export des rapports en PDF/Excel
- Graphiques et visualisations avancées
- Historique des modifications
- Notifications automatiques

### Améliorations techniques
- Cache des statistiques
- API REST pour l'intégration
- Interface mobile responsive
- Système de sauvegarde automatique
