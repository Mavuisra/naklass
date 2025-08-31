# Guide de Gestion des Périodes et Trimestres - Naklass

## Vue d'ensemble

Le module de gestion des périodes et trimestres de Naklass permet aux administrateurs et directeurs d'écoles de :

- **Créer et gérer des années scolaires** avec leurs périodes associées
- **Organiser les trimestres et périodes d'évaluation** de manière hiérarchique
- **Configurer les dates et coefficients** pour chaque période
- **Verrouiller les périodes** pour empêcher la modification des notes
- **Visualiser les statistiques** des évaluations par période

## Accès au Module

**URL :** `http://localhost/naklass/grades/periodes.php`

**Permissions requises :** Administrateur ou Direction

**Navigation :** Notes & Bulletins → Gérer périodes

## Structure Hiérarchique

### 1. Années Scolaires
- **Libellé** : Ex. "2024-2025"
- **Dates** : Début et fin de l'année
- **Description** : Optionnelle
- **Statut** : Une seule année peut être active à la fois

### 2. Trimestres
- **Nombre** : 3 trimestres par année (créés automatiquement)
- **Noms** : "1er Trimestre", "2ème Trimestre", "3ème Trimestre"
- **Ordre** : Numérotation séquentielle (1, 2, 3)

### 3. Périodes d'Évaluation
- **Nombre** : 2 périodes par trimestre (créées automatiquement)
- **Noms** : "Période 1", "Période 2", etc.
- **Parent** : Chaque période appartient à un trimestre
- **Coefficient** : Poids de la période dans le calcul des moyennes

## Fonctionnalités Principales

### Création d'Année Scolaire

1. Cliquer sur **"Nouvelle Année"**
2. Remplir les informations :
   - **Libellé** (obligatoire) : Format recommandé "YYYY-YYYY"
   - **Date de début** (obligatoire)
   - **Date de fin** (obligatoire)
   - **Description** (optionnelle)
3. **Création automatique** :
   - 3 trimestres sont créés
   - 6 périodes d'évaluation (2 par trimestre)

### Gestion des Périodes

#### Modification d'une Période
- Cliquer sur **"Modifier"** sur une période
- Possibilité de changer :
  - Nom de la période
  - Ordre dans la séquence
  - Dates de début et fin
  - Coefficient
  - Statut de verrouillage

#### Suppression d'une Période
- Cliquer sur **"Supprimer"** 
- **Attention** : La suppression d'un trimestre supprime aussi ses périodes enfants
- **Restriction** : Impossible de supprimer une période contenant des évaluations

#### Création Manuelle de Période
- Cliquer sur **"Nouvelle Période"**
- Choisir le type : Trimestre ou Période d'évaluation
- Pour les périodes d'évaluation, sélectionner le trimestre parent

### Activation d'Année Scolaire

- Une seule année peut être **active** à la fois
- L'année active est utilisée par défaut dans le système
- Cliquer sur **"Activer"** pour changer l'année active

## Interface Utilisateur

### Page Principale
- **Liste des années scolaires** avec leurs statuts
- **Détails de l'année sélectionnée** avec ses périodes
- **Actions rapides** : Créer, modifier, supprimer

### Codes Couleur
- **Vert** : Année active, périodes d'évaluation
- **Bleu** : Trimestres
- **Orange** : Périodes verrouillées
- **Rouge** : Actions de suppression

### Badges et Indicateurs
- **"Active"** : Année scolaire en cours
- **"Verrouillé"** : Période dont les notes ne peuvent plus être modifiées
- **Ordre** : Position dans la séquence
- **Coefficient** : Poids pour les calculs de moyenne

## Base de Données

### Tables Utilisées

#### `annees_scolaires`
```sql
- id (PK)
- ecole_id (FK)
- libelle (VARCHAR 50)
- date_debut (DATE)
- date_fin (DATE)
- description (TEXT)
- active (BOOLEAN)
```

#### `periodes_scolaires`
```sql
- id (PK)
- annee_scolaire_id (FK)
- periode_parent_id (FK, nullable)
- nom (VARCHAR 100)
- type_periode ('trimestre' | 'periode')
- ordre_periode (INT)
- date_debut (DATE, nullable)
- date_fin (DATE, nullable)
- coefficient (DECIMAL 3,2)
- verrouillee (BOOLEAN)
```

### Relations
- **1 École** → **N Années scolaires**
- **1 Année** → **N Périodes scolaires**
- **1 Trimestre** → **N Périodes d'évaluation**

## Intégration avec les Modules

### Module Notes
- Les évaluations sont liées aux périodes via `periode_scolaire_id`
- Le verrouillage d'une période empêche la saisie de notes

### Module Bulletins
- Les bulletins sont générés par période
- Les moyennes sont calculées selon les coefficients des périodes

### Module Paiements
- Les frais peuvent être configurés par période (trimestriel, etc.)

## Bonnes Pratiques

### Planification
1. **Créer l'année scolaire** avant le début de l'année
2. **Définir les dates** précises des trimestres et périodes
3. **Configurer les coefficients** selon la politique pédagogique

### Gestion Courante
1. **Vérifier l'année active** régulièrement
2. **Verrouiller les périodes** une fois les notes validées
3. **Ne pas supprimer** les périodes contenant des données

### Sécurité
- Seuls les **Administrateurs** et **Directeurs** peuvent gérer les périodes
- Les **modifications** sont tracées (created_by, updated_by)
- Le **verrouillage** protège l'intégrité des données

## Messages d'Erreur Courants

### "Une année scolaire avec ce libellé existe déjà"
- **Cause** : Tentative de création d'une année avec un nom déjà utilisé
- **Solution** : Utiliser un libellé unique

### "Impossible de supprimer cette période car elle contient des évaluations"
- **Cause** : Tentative de suppression d'une période liée à des évaluations
- **Solution** : Supprimer d'abord les évaluations ou archiver la période

### "Période non trouvée"
- **Cause** : Accès à une période supprimée ou inexistante
- **Solution** : Vérifier l'URL et l'existence de la période

## Maintenance

### Archivage
- Les périodes sont supprimées **logiquement** (statut = 'supprimé_logique')
- Les données restent en base pour la traçabilité

### Performance
- **Index** sur les clés étrangères et dates
- **Requêtes optimisées** pour l'affichage des listes

### Sauvegarde
- Sauvegarder régulièrement les tables `annees_scolaires` et `periodes_scolaires`
- Tester la restauration avant les opérations critiques

## Support

Pour toute question ou problème avec le module de gestion des périodes :

1. Consulter ce guide
2. Vérifier les permissions utilisateur
3. Contrôler l'intégrité de la base de données
4. Contacter l'équipe de développement Naklass

---

**Naklass** - Système de Gestion Scolaire
Version : 1.0
Dernière mise à jour : Décembre 2024

