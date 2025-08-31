# Guide de Dépannage - Module Classes

## Problème : "Erreur lors de la récupération des élèves"

### Cause
Cette erreur se produit généralement lorsque les tables nécessaires pour la gestion des élèves n'existent pas encore dans la base de données.

## Problème : "Column not found: 1054 Unknown column 'notes' in 'field list'"

### Cause
Cette erreur indique que la colonne `notes` (ou d'autres colonnes) est manquante dans la table `inscriptions`. Cela peut arriver si la table a été créée avec une structure incomplète.

### Solution
1. Allez sur : `http://localhost/naklass/classes/fix_inscriptions_table.php`
2. Ce script vérifiera et ajoutera automatiquement les colonnes manquantes :
   - `notes` (TEXT NULL) - pour les notes d'inscription
   - `created_by` (BIGINT NULL) - pour l'utilisateur qui a créé l'inscription
   - `updated_at` (TIMESTAMP) - pour la date de mise à jour
3. Il créera aussi les index nécessaires pour optimiser les performances

### Solution

#### Étape 1 : Vérifier et créer les tables
1. Allez sur la page : `http://localhost/naklass/classes/check_tables.php`
2. Ce script vérifiera automatiquement l'existence des tables suivantes :
   - `eleves` (table des élèves)
   - `inscriptions` (table des inscriptions élèves-classes)
   - `niveaux` (table des niveaux scolaires)
   - `sections` (table des sections/filières)
3. Si des tables manquent, elles seront créées automatiquement

#### Étape 2 : Ajouter des élèves de test
1. Une fois les tables créées, allez sur : `http://localhost/naklass/classes/add_test_students.php`
2. Ce script ajoutera automatiquement 5 élèves de test pour que vous puissiez tester le système

#### Étape 3 : Tester l'inscription
1. Retournez sur la page des classes : `http://localhost/naklass/classes/index.php`
2. Cliquez sur "Élèves" pour une classe
3. Vous devriez maintenant pouvoir inscrire des élèves

### Tables créées automatiquement

#### Table `eleves`
```sql
CREATE TABLE eleves (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ecole_id BIGINT NOT NULL,
    matricule VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE NOT NULL,
    sexe ENUM('M', 'F') NOT NULL,
    telephone VARCHAR(20),
    email VARCHAR(255),
    adresse TEXT,
    photo VARCHAR(255),
    statut ENUM('actif', 'inactif', 'suspendu') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Table `inscriptions`
```sql
CREATE TABLE inscriptions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    eleve_id BIGINT NOT NULL,
    classe_id BIGINT NOT NULL,
    date_inscription DATE NOT NULL DEFAULT (CURRENT_DATE),
    date_fin DATE NULL,
    statut ENUM('validée', 'en_attente', 'annulée', 'suspendue') DEFAULT 'validée',
    notes TEXT,
    created_by BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Élèves de test ajoutés
- **ELE001** : Marie Dupont (F)
- **ELE002** : Pierre Martin (M)
- **ELE003** : Sophie Bernard (F)
- **ELE004** : Thomas Petit (M)
- **ELE005** : Emma Robert (F)

### Vérification manuelle
Si vous préférez vérifier manuellement, vous pouvez exécuter ces requêtes SQL :

```sql
-- Vérifier l'existence des tables
SHOW TABLES LIKE 'eleves';
SHOW TABLES LIKE 'inscriptions';
SHOW TABLES LIKE 'niveaux';
SHOW TABLES LIKE 'sections';

-- Vérifier le contenu des tables
SELECT COUNT(*) FROM eleves;
SELECT COUNT(*) FROM inscriptions;
SELECT COUNT(*) FROM niveaux;
SELECT COUNT(*) FROM sections;
```

### Problèmes courants

#### 1. Permissions insuffisantes
- Assurez-vous d'être connecté avec un compte ayant les permissions `admin`, `direction` ou `secretaire`

#### 2. Base de données non configurée
- Vérifiez que votre école est configurée dans le système
- Assurez-vous que `$_SESSION['ecole_id']` est défini

#### 3. Erreurs SQL
- Vérifiez les logs d'erreur PHP
- Assurez-vous que l'utilisateur MySQL a les droits de création de tables

### Support
Si le problème persiste après avoir suivi ces étapes, vérifiez :
1. Les logs d'erreur PHP
2. Les logs MySQL
3. La configuration de la base de données dans `config/database.php`
