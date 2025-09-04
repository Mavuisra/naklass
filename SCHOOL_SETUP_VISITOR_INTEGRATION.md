# Intégration de la Création d'École par Visiteur dans school_setup.php

## Vue d'ensemble

La page `auth/school_setup.php` a été modifiée pour permettre aux visiteurs de créer leur école directement, en plus de sa fonction existante de configuration d'école pour les utilisateurs connectés.

## Modifications Apportées

### 1. Détection du Mode Visiteur
- **Paramètre URL** : `?visitor=1` pour activer le mode visiteur
- **Gestion des cookies** : Création automatique d'un ID visiteur
- **Logique conditionnelle** : Différents comportements selon le mode

### 2. Validation et Traitement
- **Champs supplémentaires** : Prénom directeur, email directeur, mot de passe admin
- **Validation renforcée** : Vérification d'unicité des emails
- **Création automatique** : École, compte admin, niveaux par défaut

### 3. Interface Utilisateur
- **Titre adaptatif** : "Créer votre École" vs "Configuration de votre École"
- **Champs conditionnels** : Affichage des champs spécifiques aux visiteurs
- **Bouton adaptatif** : "Créer mon École" vs "Terminer la configuration"

### 4. Gestion des Identifiants
- **Affichage immédiat** : Identifiants montrés à l'écran
- **Envoi par email** : Utilisation de l'EmailManager existant
- **Cookie de suivi** : Marquage de la création d'école

## Flux de Fonctionnement

### Pour les Visiteurs
```
1. Accès via index.php → redirection vers school_setup.php?visitor=1
2. Remplissage du formulaire complet
3. Validation des données
4. Création de l'école (statut: validée, active)
5. Création du compte administrateur
6. Création des niveaux par défaut
7. Envoi des identifiants par email
8. Affichage des identifiants à l'écran
9. Possibilité de se connecter immédiatement
```

### Pour les Utilisateurs Connectés
```
1. Accès normal via dashboard
2. Configuration des informations existantes
3. Validation par super admin (processus existant)
```

## Champs du Formulaire

### Champs Communs
- Nom de l'école *
- Sigle *
- Adresse *
- Téléphone *
- Email *
- Site web
- Fax
- Boîte postale
- Régime *
- Types d'enseignement *
- Langues d'enseignement *
- Devise principale *
- Nom du directeur *
- Téléphone du directeur *
- Email du directeur
- Numéro d'autorisation
- Date d'autorisation
- Description de l'établissement

### Champs Spécifiques aux Visiteurs
- **Prénom du directeur** * (obligatoire)
- **Email du directeur** * (obligatoire)
- **Mot de passe administrateur** * (minimum 6 caractères)

## Sécurité et Validation

### Validation des Données
- **Champs obligatoires** : Validation côté client et serveur
- **Emails uniques** : Vérification d'unicité pour les visiteurs
- **Mot de passe** : Minimum 6 caractères
- **Sanitisation** : Toutes les entrées sont nettoyées

### Gestion des Erreurs
- **Transactions** : Rollback en cas d'erreur
- **Messages d'erreur** : Affichage clair des problèmes
- **Logs** : Enregistrement des erreurs

## Configuration Email

### Utilisation de l'EmailManager
- **Méthode** : `sendSchoolCreationWithCredentials()`
- **Template** : Email HTML avec identifiants
- **Fallback** : Affichage des identifiants si email échoue

### Contenu de l'Email
- **Sujet** : "Votre école a été créée - Identifiants d'administration"
- **Contenu** : Identifiants, instructions, lien de connexion
- **Format** : HTML + texte

## Base de Données

### Tables Modifiées
- **ecoles** : Création avec validation automatique
- **utilisateurs** : Création du compte admin
- **niveaux** : Création des niveaux par défaut

### Statuts Automatiques
- **validation_status** : 'validée'
- **statut_ecole** : 'active'
- **configuration_complete** : TRUE
- **validee_par** : 'system_auto'

## Tests et Validation

### Script de Test
- **Fichier** : `test_school_setup_visitor.php`
- **Vérifications** : Fichiers, configuration, base de données
- **Instructions** : Guide de test manuel

### Tests Automatiques
- **Configuration email** : Vérification des constantes
- **EmailManager** : Test de l'instanciation
- **Base de données** : Vérification des tables

## Utilisation

### Pour les Visiteurs
1. **Accès** : Aller sur la page d'accueil
2. **Redirection** : Automatique vers `school_setup.php?visitor=1`
3. **Formulaire** : Remplir toutes les informations
4. **Validation** : Clic sur "Créer mon École"
5. **Réception** : Email avec identifiants + affichage à l'écran
6. **Connexion** : Utiliser les identifiants reçus

### Pour les Administrateurs
1. **Accès** : Via le dashboard existant
2. **Configuration** : Mise à jour des informations
3. **Validation** : Processus de validation par super admin

## Avantages

### Pour les Utilisateurs
- **Simplicité** : Un seul formulaire pour tout
- **Rapidité** : Validation et activation automatiques
- **Sécurité** : Identifiants par email
- **Immédiat** : Accès instantané au système

### Pour les Développeurs
- **Réutilisation** : Code existant adapté
- **Maintenance** : Une seule page à maintenir
- **Cohérence** : Interface uniforme
- **Flexibilité** : Support des deux modes

## Statut
✅ **INTÉGRATION TERMINÉE** - La création d'école par visiteur est maintenant intégrée dans `auth/school_setup.php` et fonctionne en parallèle avec la configuration d'école existante.

## Fichiers Modifiés
- ✅ `auth/school_setup.php` - Intégration du mode visiteur
- ✅ `index.php` - Redirection vers school_setup.php
- ✅ `includes/EmailManager.php` - Support des emails de création

## Fichiers Créés
- ✅ `test_school_setup_visitor.php` - Script de test
- ✅ `SCHOOL_SETUP_VISITOR_INTEGRATION.md` - Ce guide
