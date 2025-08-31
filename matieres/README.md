# Module de Gestion des Matières

## Vue d'ensemble

Le module de gestion des matières permet de créer, modifier et assigner les cours/matières aux différentes classes de l'établissement. Il constitue un élément central du système de gestion scolaire.

## Fonctionnalités

### 📚 Gestion des Matières
- **Création** : Ajout de nouvelles matières avec code, nom, coefficient et type
- **Modification** : Édition des propriétés des matières existantes
- **Visualisation** : Vue détaillée avec statistiques et assignations
- **Recherche et filtrage** : Par nom, code, type ou statut

### 🔗 Assignation aux Classes
- **Assignation flexible** : Attribution des matières aux classes
- **Gestion des enseignants** : Affectation d'enseignants par matière/classe
- **Configuration personnalisée** : Coefficient et volume horaire par classe
- **Désassignation** : Suppression des assignations existantes

### 📊 Statistiques et Suivi
- **Tableau de bord** : Vue d'ensemble des matières et assignations
- **Métriques** : Nombre de classes, enseignants et élèves concernés
- **Historique** : Traçabilité des modifications

## Structure des Fichiers

```
matieres/
├── index.php          # Liste et recherche des matières
├── create.php         # Création d'une nouvelle matière
├── view.php          # Visualisation détaillée d'une matière
├── edit.php          # Modification d'une matière
├── assign.php        # Assignation aux classes
└── README.md         # Documentation du module
```

## Base de Données

### Table `cours`
```sql
- id                : Identifiant unique
- ecole_id         : Référence à l'école
- code             : Code unique de la matière (ex: MATH, FR)
- nom_cours        : Nom complet de la matière
- coefficient      : Coefficient par défaut (0.1 à 10)
- type_cours       : 'tronc_commun' ou 'optionnel'
- ponderation_defaut : Configuration JSON pour l'évaluation
- description      : Description optionnelle
- actif           : Matière active/inactive
```

### Table `classe_cours`
```sql
- id              : Identifiant unique de l'assignation
- classe_id       : Référence à la classe
- cours_id        : Référence à la matière
- enseignant_id   : Enseignant assigné (optionnel)
- coefficient     : Coefficient spécifique à cette classe
- volume_horaire  : Volume horaire hebdomadaire
- actif          : Assignation active/inactive
```

## Guide d'Utilisation

### 1. Créer une Nouvelle Matière

1. Accédez à **Matières** > **Nouvelle Matière**
2. Remplissez les informations obligatoires :
   - **Nom** : Nom complet (ex: "Mathématiques")
   - **Code** : Code court unique (ex: "MATH")
   - **Coefficient** : Poids dans la moyenne (ex: 2.0)
3. Configurez les options avancées :
   - **Type** : Tronc commun ou optionnel
   - **Pondération** : Configuration JSON pour les évaluations
4. Cliquez sur **Créer la Matière**

### 2. Assigner une Matière à une Classe

1. Depuis la liste des matières, cliquez sur **Assigner**
2. Utilisez l'**Assignation Rapide** :
   - Sélectionnez la classe
   - Choisissez un enseignant (optionnel)
   - Ajustez le coefficient si nécessaire
   - Définissez le volume horaire
3. Cliquez sur **Assigner**

### 3. Gérer les Assignations Existantes

- **Modifier** : Cliquez sur l'icône crayon pour ajuster les paramètres
- **Désassigner** : Utilisez le bouton rouge X pour supprimer l'assignation
- **Voir les détails** : Accédez à la vue détaillée de la matière

## Conseils et Bonnes Pratiques

### Nomenclature des Codes
- Utilisez des codes courts et clairs (3-5 caractères)
- Évitez les caractères spéciaux
- Exemples recommandés :
  - MATH (Mathématiques)
  - FR (Français)
  - ANG (Anglais)
  - PHYS (Physique)
  - SVT (Sciences de la Vie et de la Terre)

### Gestion des Coefficients
- **Matières principales** : Coefficient 2-4
- **Matières secondaires** : Coefficient 1-1.5
- **Matières d'éveil** : Coefficient 0.5-1
- **Spécialisations** : Coefficient 3-5

### Types de Matières
- **Tronc Commun** : Obligatoire pour tous les élèves d'un niveau
- **Optionnel** : Choisi selon la filière ou les préférences

### Pondération des Évaluations

Exemple de configuration JSON :
```json
{
  "interrogations": 0.3,
  "devoirs": 0.4,
  "examens": 0.3
}
```

Configurations suggérées :
- **Matières théoriques** : {"interrogations": 0.3, "devoirs": 0.4, "examens": 0.3}
- **Matières pratiques** : {"pratique": 0.5, "theorie": 0.3, "projets": 0.2}
- **Langues** : {"oral": 0.3, "ecrit": 0.4, "comprehension": 0.3}

## Permissions et Sécurité

### Accès au Module
- **Administrateurs** : Accès complet (création, modification, suppression)
- **Direction** : Accès complet aux matières et assignations
- **Secrétaires** : Lecture seule, assignations limitées

### Contrôles de Sécurité
- Vérification de l'école de rattachement
- Validation des données d'entrée
- Contrôle d'unicité des codes
- Traçabilité des modifications

## Intégrations

### Modules Connectés
- **Classes** : Assignation des matières aux classes
- **Enseignants** : Affectation des professeurs
- **Notes** : Saisie des évaluations par matière
- **Bulletins** : Calcul des moyennes avec coefficients

### API et Données
- Export des matières et assignations
- Import en lot depuis fichiers CSV/Excel
- Synchronisation avec systèmes externes

## Dépannage

### Problèmes Courants

**Erreur "Code déjà existant"**
- Vérifiez l'unicité du code dans l'école
- Utilisez un code différent ou modifiez l'existant

**Matière non visible dans les bulletins**
- Vérifiez que la matière est active
- Confirmez l'assignation à la classe concernée
- Contrôlez les dates de validité

**Problème de coefficient**
- Assurez-vous que le coefficient est entre 0.1 et 10
- Vérifiez la configuration au niveau classe

### Support Technique
- Consultez les logs d'erreur PHP
- Vérifiez la structure de la base de données
- Contactez l'équipe de développement pour les cas complexes

## Évolutions Futures

### Fonctionnalités Prévues
- Import/Export automatisé
- Gestion des prérequis entre matières
- Planning automatique des cours
- Évaluations standardisées
- Système de recommandations intelligentes

### Améliorations en Cours
- Interface mobile optimisée
- Notifications automatiques
- Reporting avancé
- Intégration LMS

