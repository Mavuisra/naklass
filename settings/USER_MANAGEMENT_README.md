# 📚 Guide de Gestion des Utilisateurs - Naklass

## 🎯 Vue d'ensemble

Le système de gestion des utilisateurs de Naklass permet aux administrateurs d'école de créer, modifier et gérer tous les types d'utilisateurs nécessaires au bon fonctionnement de l'établissement scolaire.

## 👥 Types d'utilisateurs disponibles

### 1. **Administrateur** (`admin`)
- **Accès complet** au système de l'école
- **Gestion des utilisateurs** : création, modification, suppression
- **Configuration de l'école** : paramètres, structure
- **Supervision** de tous les modules

### 2. **Direction** (`direction`)
- **Gestion administrative** et pédagogique
- **Accès aux rapports** et statistiques
- **Gestion des utilisateurs** (lecture et modification)
- **Configuration avancée** de l'école

### 3. **Enseignant** (`enseignant`)
- **Gestion des notes** et évaluations
- **Gestion de la présence** des élèves
- **Consultation des élèves** de leurs classes
- **Génération de bulletins** pour leurs classes

### 4. **Secrétaire** (`secretaire`)
- **Gestion des élèves** : inscriptions, modifications
- **Gestion des classes** et matières
- **Gestion des enseignants** (consultation)
- **Consultation des paiements** (lecture seule)

### 5. **Caissier** (`caissier`)
- **Gestion des paiements** et frais
- **Création de reçus** et factures
- **Rapports financiers** et statistiques
- **Gestion des retards** de paiement

## 🚀 Comment créer un nouvel utilisateur

### Étape 1 : Accéder à la création
1. Connectez-vous en tant qu'**Administrateur** ou **Direction**
2. Allez dans **Paramètres** → **Utilisateurs**
3. Cliquez sur **"Nouvel Utilisateur"**

### Étape 2 : Remplir le formulaire
- **Informations personnelles** : Nom, prénom, email, téléphone
- **Sélection du rôle** : Choisir parmi les rôles disponibles
- **Mot de passe** : Définir un mot de passe sécurisé
- **Notes internes** : Informations supplémentaires (optionnel)

### Étape 3 : Validation et création
- Le système vérifie l'unicité de l'email
- L'utilisateur est créé avec les permissions du rôle sélectionné
- Un message de confirmation s'affiche

## ✏️ Comment modifier un utilisateur existant

### Étape 1 : Accéder à la modification
1. Dans la liste des utilisateurs, cliquez sur l'icône **"Modifier"** (crayon)
2. Ou utilisez le lien direct : `edit_user.php?id=USER_ID`

### Étape 2 : Modifier les informations
- **Informations personnelles** : Nom, prénom, email, téléphone
- **Rôle** : Changer le rôle si nécessaire
- **Statut** : Activer/désactiver le compte
- **Notes** : Mettre à jour les informations internes

### Étape 3 : Sauvegarder
- Cliquez sur **"Enregistrer les modifications"**
- Les changements sont appliqués immédiatement

## 🔧 Actions rapides disponibles

### Dans la liste des utilisateurs
- **Modifier** : Accéder au formulaire de modification
- **Activer/Désactiver** : Changer le statut du compte
- **Réinitialiser mot de passe** : Générer un nouveau mot de passe temporaire
- **Supprimer** : Suppression logique (réversible)

### Dans la page de modification
- **Actions rapides** : Boutons pour les opérations courantes
- **Informations en temps réel** : Statut, dernière connexion, etc.

## 🔒 Sécurité et permissions

### Règles de sécurité
- **Impossible de modifier son propre compte** depuis l'interface admin
- **Vérification des permissions** à chaque action
- **Logs de toutes les actions** pour traçabilité
- **Validation des données** côté serveur

### Gestion des mots de passe
- **Hachage sécurisé** avec Bcrypt
- **Réinitialisation sécurisée** avec tokens temporaires
- **Force du mot de passe** vérifiée (minimum 6 caractères)
- **Confirmation obligatoire** lors de la création

## 📊 Statistiques et monitoring

### Tableau de bord
- **Nombre total d'utilisateurs**
- **Utilisateurs actifs/inactifs**
- **Répartition par rôle**
- **Dernières connexions**

### Filtres et recherche
- **Recherche par nom, prénom ou email**
- **Filtrage par rôle**
- **Filtrage par statut** (actif/inactif)
- **Pagination** pour les grandes listes

## 🚨 Gestion des erreurs

### Erreurs courantes
- **Email déjà utilisé** : Vérifier l'unicité dans l'école
- **Rôle invalide** : S'assurer que le rôle existe et est actif
- **Permissions insuffisantes** : Vérifier le niveau d'accès
- **Données manquantes** : Remplir tous les champs obligatoires

### Solutions
- **Vérification des données** avant soumission
- **Messages d'erreur clairs** et explicatifs
- **Validation en temps réel** côté client
- **Rollback automatique** en cas d'erreur

## 📧 Notifications et communications

### Après la création
- **Message de confirmation** immédiat
- **Log de l'action** enregistré
- **Prêt à la connexion** immédiatement
- **Permissions appliquées** automatiquement

### Informations utilisateur
- **Email de connexion** : L'utilisateur peut se connecter immédiatement
- **Mot de passe temporaire** : Affiché lors de la réinitialisation
- **Rôle et permissions** : Appliqués automatiquement

## 🔄 Workflow recommandé

### 1. **Planification**
- Identifier les besoins en utilisateurs
- Définir les rôles appropriés
- Préparer les informations nécessaires

### 2. **Création**
- Créer les comptes avec des mots de passe temporaires
- Vérifier la création réussie
- Noter les informations de connexion

### 3. **Formation**
- Former les utilisateurs sur leurs rôles
- Expliquer les fonctionnalités disponibles
- Encourager le changement de mot de passe

### 4. **Suivi**
- Monitorer l'activité des nouveaux utilisateurs
- Vérifier l'utilisation correcte des permissions
- Ajuster les rôles si nécessaire

## 📱 Interface utilisateur

### Design responsive
- **Compatible mobile** et tablette
- **Navigation intuitive** avec icônes
- **Couleurs cohérentes** avec le thème Naklass
- **Animations fluides** pour une meilleure UX

### Accessibilité
- **Labels clairs** pour tous les champs
- **Messages d'erreur** explicites
- **Navigation au clavier** supportée
- **Contraste suffisant** pour la lisibilité

## 🚀 Fonctionnalités avancées

### Gestion en lot (futur)
- **Import CSV** d'utilisateurs
- **Modification multiple** de rôles
- **Export des données** utilisateurs
- **Synchronisation** avec d'autres systèmes

### Intégrations (futur)
- **LDAP/Active Directory** pour l'authentification
- **SSO** (Single Sign-On)
- **API** pour la gestion programmatique
- **Webhooks** pour les notifications

## 📞 Support et assistance

### En cas de problème
1. **Vérifier les logs** d'erreur
2. **Consulter la documentation** technique
3. **Contacter l'équipe** de développement
4. **Signaler les bugs** avec des captures d'écran

### Ressources utiles
- **Guide d'installation** : `INSTALLATION_SUPER_ADMIN.md`
- **Documentation technique** : `README.md`
- **Forum de support** : [Lien vers le support]
- **Base de connaissances** : [Lien vers la KB]

---

## 📝 Notes de version

- **Version 1.0** : Système de base de gestion des utilisateurs
- **Version 1.1** : Ajout de la modification et suppression
- **Version 1.2** : Amélioration de l'interface et de la sécurité
- **Version 1.3** : Ajout des statistiques et filtres avancés

---

*Dernière mise à jour : <?php echo date('d/m/Y'); ?>*
*Version du document : 1.3*

