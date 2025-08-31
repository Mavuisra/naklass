# 🎓 Système de Gestion des Cycles d'Enseignement

## 📋 Vue d'ensemble

Ce système permet de gérer de manière détaillée les cycles d'enseignement selon la structure du système éducatif congolais, avec support complet des options et sections pour le cycle Humanités.

## 🏫 Cycles d'Enseignement Supportés

### 1. **Maternelle (3 ans)**
- **1ʳᵉ année maternelle** → `1ere_maternelle`
- **2ᵉ année maternelle** → `2eme_maternelle`
- **3ᵉ année maternelle** → `3eme_maternelle`

### 2. **Primaire (6 ans)**
- **1ᵉ année primaire** → `1eme_primaire`
- **2ᵉ année primaire** → `2eme_primaire`
- **3ᵉ année primaire** → `3eme_primaire`
- **4ᵉ année primaire** → `4eme_primaire`
- **5ᵉ année primaire** → `5eme_primaire`
- **6ᵉ année primaire** → `6eme_primaire`

**Examen :** TENAFEP (Test National de Fin d'Études Primaires)

### 3. **Secondaire (6 ans)**

#### **Tronc Commun**
- **7ᵉ secondaire** → `7eme_secondaire`
- **8ᵉ secondaire** → `8eme_secondaire`

#### **Humanités (4 ans)**
- **1ʳᵉ humanités** → `1ere_humanites`
- **2ᵉ humanités** → `2eme_humanites`
- **3ᵉ humanités** → `3eme_humanites`
- **4ᵉ humanités** → `4eme_humanites`

**Diplôme :** Diplôme d'État

#### **Options et Sections pour Humanités**

##### **Sections Générales**
- **Scientifique** → `scientifique`
- **Littéraire** → `litteraire`
- **Commerciale** → `commerciale`
- **Pédagogique** → `pedagogique`

##### **Sections Techniques**
- **Technique de Construction** → `technique_construction`
- **Technique Électricité** → `technique_electricite`
- **Technique Mécanique** → `technique_mecanique`
- **Technique Informatique** → `technique_informatique`

##### **Sections Professionnelles**
- **Secrétariat** → `secretariat`
- **Comptabilité** → `comptabilite`
- **Hôtellerie** → `hotellerie`
- **Couture** → `couture`

## 🗄️ Structure de Base de Données

### Table `classes` - Nouvelles Colonnes

```sql
-- Colonnes existantes
id, ecole_id, nom_classe, niveau, cycle, annee_scolaire, capacite_max, 
effectif_actuel, professeur_principal_id, salle_classe, statut, 
created_at, updated_at, created_by, updated_by, version, notes_internes

-- Nouvelles colonnes ajoutées
niveau_detaille VARCHAR(100) NULL,    -- Niveau spécifique (ex: 1ere_primaire)
option_section VARCHAR(100) NULL,     -- Option/section (ex: scientifique)
cycle_complet TEXT NULL               -- Description complète pour affichage
```

### Exemples de Données Stockées

```sql
-- Exemple 1: Classe de 6ᵉ primaire
INSERT INTO classes (
    nom_classe, niveau, cycle, niveau_detaille, option_section, cycle_complet
) VALUES (
    '6ème A', '6ème', 'primaire', '6eme_primaire', NULL, 
    'Primaire (6 ans) - 6ᵉ année primaire'
);

-- Exemple 2: Classe de 3ᵉ humanités scientifique
INSERT INTO classes (
    nom_classe, niveau, cycle, niveau_detaille, option_section, cycle_complet
) VALUES (
    '3ᵉ S', '3ᵉ', 'secondaire', '3eme_humanites', 'scientifique',
    'Secondaire (6 ans) - 3ᵉ humanités (Scientifique)'
);
```

## 🚀 Installation et Configuration

### Étape 1: Mettre à jour la base de données

Exécutez le script de mise à jour :

```bash
# Via navigateur
http://votre-domaine.com/update_classes_table.php

# Ou via ligne de commande
php update_classes_table.php
```

### Étape 2: Vérifier la structure

Le script affichera la structure mise à jour de la table avec les nouvelles colonnes.

## 💻 Utilisation dans le Code

### Création d'une Classe

```php
// Données du formulaire
$data = [
    'nom_classe' => '3ᵉ S',
    'niveau' => '3ᵉ',
    'cycle' => 'secondaire',
    'niveau_detaille' => '3eme_humanites',
    'option_section' => 'scientifique',
    'annee_scolaire' => '2024-2025',
    'capacite_max' => 40
];

// Génération automatique du cycle_complet
$cycle_complet = 'Secondaire (6 ans) - 3ᵉ humanités (Scientifique)';

// Insertion en base
$insert_query = "INSERT INTO classes (
    ecole_id, nom_classe, niveau, cycle, niveau_detaille, option_section, 
    cycle_complet, annee_scolaire, capacite_max, created_by
) VALUES (
    :ecole_id, :nom_classe, :niveau, :cycle, :niveau_detaille, :option_section,
    :cycle_complet, :annee_scolaire, :capacite_max, :created_by
)";
```

### Récupération des Données

```php
// Récupérer toutes les classes d'un cycle spécifique
$query = "SELECT * FROM classes WHERE cycle = 'secondaire' AND ecole_id = :ecole_id";

// Récupérer les classes d'une section spécifique
$query = "SELECT * FROM classes WHERE option_section = 'scientifique' AND ecole_id = :ecole_id";

// Récupérer les classes d'un niveau détaillé
$query = "SELECT * FROM classes WHERE niveau_detaille = '3eme_humanites' AND ecole_id = :ecole_id";
```

## 🔍 Fonctionnalités Avancées

### 1. **Validation Automatique**
- Vérification que le niveau détaillé correspond au cycle
- Validation obligatoire des options/sections pour les Humanités
- Messages d'erreur contextuels

### 2. **Interface Dynamique**
- Affichage/masquage automatique des champs selon le contexte
- Suggestions de niveaux basées sur le cycle sélectionné
- Aperçu en temps réel de la configuration

### 3. **Gestion des Cycles Mixtes**
- Support des écoles avec plusieurs cycles d'enseignement
- Configuration flexible selon les besoins de l'établissement
- Validation adaptative selon le contexte

## 📊 Exemples d'Utilisation

### École Primaire + Secondaire
```sql
-- Configuration de l'école
type_enseignement = 'primaire,secondaire'

-- Classes possibles
- 6ème A (Primaire - 6ᵉ année primaire)
- 7ᵉ A (Secondaire - 7ᵉ secondaire)
- 1ʳᵉ S (Secondaire - 1ʳᵉ humanités - Scientifique)
- 2ᵉ L (Secondaire - 2ᵉ humanités - Littéraire)
```

### École Technique
```sql
-- Configuration de l'école
type_enseignement = 'secondaire,technique'

-- Classes possibles
- 1ʳᵉ TC (Secondaire - 1ʳᵉ humanités - Technique de Construction)
- 2ᵉ TE (Secondaire - 2ᵉ humanités - Technique Électricité)
- 3ᵉ TM (Secondaire - 3ᵉ humanités - Technique Mécanique)
```

## 🛠️ Maintenance et Évolutions

### Ajout de Nouvelles Sections
Pour ajouter une nouvelle section, modifiez :

1. **Le tableau des options** dans `classes/create.php`
2. **Les labels d'affichage** dans la génération du `cycle_complet`
3. **La validation** si nécessaire

### Ajout de Nouveaux Cycles
Pour ajouter un nouveau cycle :

1. **Ajouter dans `type_enseignement`** de la table `ecoles`
2. **Créer la logique** dans `updateNiveauxDetaille()`
3. **Ajouter les labels** correspondants

## 🔐 Sécurité et Validation

- **Sanitisation** de toutes les entrées utilisateur
- **Validation** stricte des données avant insertion
- **Contrôle d'accès** basé sur les rôles et permissions
- **Logs** automatiques de toutes les actions

## 📝 Notes Importantes

1. **Compatibilité** : Le système est rétrocompatible avec les données existantes
2. **Performance** : Index créés sur les nouvelles colonnes pour optimiser les requêtes
3. **Flexibilité** : Support des écoles avec configurations mixtes
4. **Standards** : Respect des normes du système éducatif congolais

---

**Développé pour le système Naklass** 🎓  
**Version :** 1.0  
**Date :** 2025  
**Compatibilité :** PHP 7.4+, MySQL 5.7+
