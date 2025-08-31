# Module de Rapports - Naklass

## Vue d'ensemble

Le module de rapports de Naklass fournit un tableau de bord complet et synthétique de toutes les informations essentielles de l'école. Il permet aux administrateurs, directions et secrétaires d'avoir une vue d'ensemble rapide et d'exporter les données selon leurs besoins.

## Fonctionnalités

### 📊 Tableau de Bord Principal (`index.php`)

Le tableau de bord affiche en temps réel :

- **Informations générales de l'école** : Nom, adresse
- **Statistiques des classes** : Total, actives, inactives
- **Statistiques des inscriptions** : Total, actives, terminées, annulées
- **Statistiques des cours** : Total, actifs, terminés
- **Statistiques des notes** : Moyenne générale, note min/max
- **Statistiques des finances** : Entrées, sorties, solde
- **Statistiques de présence** : Présences, absences, retards
- **Statistiques des utilisateurs** : Total, enseignants, administrateurs

### 📈 Visualisations

- **Graphique linéaire** : Évolution des inscriptions sur 6 mois
- **Graphique circulaire** : Répartition financière (entrées vs sorties)
- **Barres de progression** : Taux de présence
- **Indicateurs colorés** : Statuts et pourcentages

### 📤 Export des Données (`export.php`)

- **Format PDF** : Document portable et lisible
- **Format Excel/CSV** : Tableau de données modifiable
- **Sélection des sections** : Choix des données à exporter
- **Aperçu des données** : Vérification avant export

## Accès et Permissions

### Rôles autorisés
- `admin` : Accès complet
- `direction` : Accès complet
- `secretaire` : Accès complet

### Sécurité
- Vérification de l'authentification
- Contrôle des permissions par rôle
- Isolation des données par école
- Protection contre l'accès non autorisé

## Structure des Fichiers

```
reports/
├── index.php          # Tableau de bord principal
├── export.php         # Page d'export des données
└── README.md          # Documentation
```

## Utilisation

### 1. Accès au Tableau de Bord

1. Connectez-vous avec un compte autorisé
2. Naviguez vers `/reports/`
3. Le tableau de bord s'affiche automatiquement

### 2. Export des Données

1. Cliquez sur "Exporter" dans le tableau de bord
2. Choisissez le format (PDF ou Excel)
3. Sélectionnez les sections à inclure
4. Cliquez sur "Générer l'Export"

### 3. Navigation

- **Accueil** : Retour à la page principale
- **Retour** : Retour au tableau de bord
- **Actions rapides** : Accès direct aux modules

## Personnalisation

### Ajout de nouvelles métriques

Pour ajouter de nouvelles statistiques :

1. Modifiez la requête SQL dans `index.php`
2. Ajoutez l'affichage dans l'interface
3. Mettez à jour la fonction d'export

### Modification des graphiques

Les graphiques utilisent Chart.js. Pour les modifier :

1. Localisez la section `<script>` en bas de `index.php`
2. Modifiez les configurations des graphiques
3. Ajoutez de nouveaux types de visualisations

### Ajout de nouveaux formats d'export

Pour ajouter de nouveaux formats :

1. Créez une nouvelle fonction d'export dans `export.php`
2. Ajoutez l'option dans l'interface
3. Intégrez une bibliothèque appropriée (TCPDF, PhpSpreadsheet, etc.)

## Dépendances

### Frontend
- Bootstrap 5.3.0
- Bootstrap Icons 1.10.0
- Chart.js (via CDN)

### Backend
- PHP 7.4+
- PDO MySQL
- Sessions PHP

### Base de données
- Tables : `ecoles`, `classes`, `inscriptions`, `cours`, `notes`, `transactions_financieres`, `presence`, `sessions_presence`, `utilisateurs`, `roles`

## Maintenance

### Vérification des données
- Les requêtes SQL incluent des vérifications d'erreur
- Les données manquantes affichent "N/A" ou "0"
- Les erreurs sont affichées dans des alertes

### Performance
- Requêtes préparées pour la sécurité
- Index recommandés sur `ecole_id` et `statut`
- Pagination possible pour de gros volumes de données

### Sauvegarde
- Les exports génèrent des fichiers temporaires
- Suppression automatique après téléchargement
- Pas de stockage permanent des rapports

## Support et Développement

### Ajout de fonctionnalités
- Suivez la structure existante
- Maintenez la cohérence des permissions
- Testez avec différents rôles et écoles

### Dépannage
- Vérifiez les logs d'erreur PHP
- Contrôlez la structure de la base de données
- Testez les permissions utilisateur

### Évolutions futures
- Rapports périodiques automatiques
- Notifications par email
- API REST pour intégrations externes
- Tableau de bord personnalisable

## Licence

Ce module fait partie de Naklass et suit les mêmes conditions d'utilisation.
