# 📚 Guide "Mes Classes" pour les Enseignants - Naklass

## 🎯 Vue d'ensemble

La fonctionnalité **"Mes Classes"** permet aux enseignants de voir uniquement les classes et cours qu'ils enseignent, offrant une vue personnalisée et sécurisée de leurs responsabilités pédagogiques.

## 🔐 Sécurité et Permissions

### Accès restreint
- **Enseignants** : Voient uniquement leurs classes et cours assignés
- **Administrateurs/Direction** : Voient toutes les classes et cours de l'école
- **Autres rôles** : Pas d'accès à cette fonctionnalité

### Filtrage automatique
- Les données sont automatiquement filtrées selon le rôle de l'utilisateur connecté
- Impossible d'accéder aux classes/cours d'autres enseignants
- Respect strict de la séparation des données entre enseignants

## 📊 Fonctionnalités disponibles

### 1. **Vue d'ensemble des classes**
- **Nombre total de classes** assignées
- **Effectif des élèves** par classe
- **Nombre de cours** par classe
- **Informations de base** : niveau, cycle, salle

### 2. **Liste détaillée des cours**
- **Nom et code** du cours
- **Classe associée** et niveau
- **Coefficient** du cours
- **Actions rapides** : notes, présence

### 3. **Statistiques personnalisées**
- Total des classes assignées
- Total des cours enseignés
- Total des élèves sous responsabilité
- Année scolaire en cours

## 🚀 Comment accéder

### Pour les enseignants
1. **Connexion** à la plateforme avec un compte enseignant
2. **Navigation** : Menu latéral → "Mes Classes"
3. **Accès direct** : `classes/my_classes.php`

### Pour les administrateurs
1. **Connexion** avec un compte admin/direction
2. **Navigation** : Menu latéral → "Classes" → "Toutes les Classes"
3. **Vue complète** de toutes les classes de l'école

## 🔗 Intégration avec d'autres modules

### Gestion des notes
- **Lien direct** vers la saisie des notes pour chaque cours
- **Filtrage automatique** par classe et cours assignés
- **Accès sécurisé** aux évaluations des élèves

### Gestion de la présence
- **Lien direct** vers la gestion de présence par classe
- **Filtrage automatique** des classes assignées
- **Suivi des absences** pour les classes concernées

### Bulletins et rapports
- **Génération de bulletins** pour les classes assignées
- **Statistiques personnalisées** par enseignant
- **Rapports de performance** des élèves

## 📱 Interface utilisateur

### Design responsive
- **Compatible mobile** et tablette
- **Cartes interactives** pour chaque classe
- **Tableau détaillé** pour les cours
- **Navigation intuitive** avec icônes

### Informations affichées
- **Badges colorés** pour les niveaux et cycles
- **Indicateurs visuels** pour le nombre d'élèves
- **Actions contextuelles** selon les permissions
- **Statut en temps réel** des classes et cours

## 🔧 Fonctions techniques

### Requêtes optimisées
- **Jointures efficaces** entre les tables
- **Filtrage côté base de données** pour la sécurité
- **Index appropriés** pour les performances
- **Cache des résultats** pour éviter les requêtes répétées

### Gestion des erreurs
- **Validation des permissions** à chaque accès
- **Gestion gracieuse** des erreurs de base de données
- **Messages d'erreur** explicites pour l'utilisateur
- **Logs de sécurité** pour le suivi des accès

## 📋 Structure des données

### Table `classe_cours`
```sql
CREATE TABLE classe_cours (
    id BIGINT PRIMARY KEY,
    classe_id BIGINT NOT NULL,
    cours_id BIGINT NOT NULL,
    enseignant_id BIGINT NOT NULL,
    annee_scolaire VARCHAR(20) NOT NULL,
    coefficient_classe DECIMAL(5,2),
    statut ENUM('actif', 'archivé', 'supprimé_logique'),
    -- autres champs...
);
```

### Relations clés
- **classe_id** → Table `classes`
- **cours_id** → Table `cours`
- **enseignant_id** → Table `enseignants`

## 🚨 Cas d'usage et limitations

### Cas d'usage typiques
1. **Enseignant connecté** : Voir ses classes et cours
2. **Administrateur** : Vue d'ensemble complète
3. **Direction** : Supervision de toutes les classes
4. **Consultation rapide** : Accès aux informations essentielles

### Limitations actuelles
- **Pas de modification** des affectations depuis cette interface
- **Lecture seule** pour les enseignants
- **Pas d'export** des données (futur développement)
- **Pas de notifications** automatiques (futur développement)

## 🔄 Workflow recommandé

### 1. **Consultation quotidienne**
- Vérifier les classes du jour
- Consulter le nombre d'élèves présents
- Préparer les cours à enseigner

### 2. **Gestion des notes**
- Accéder directement à la saisie des notes
- Vérifier les évaluations en cours
- Consulter les moyennes des élèves

### 3. **Suivi de présence**
- Marquer la présence des élèves
- Consulter les statistiques d'absence
- Suivre l'assiduité des classes

### 4. **Préparation des bulletins**
- Consulter les performances des élèves
- Préparer les appréciations
- Valider les moyennes calculées

## 🚀 Développements futurs

### Fonctionnalités prévues
- **Notifications** de nouveaux élèves assignés
- **Export PDF** des listes de classes
- **Calendrier** des cours par enseignant
- **Statistiques avancées** de performance

### Améliorations techniques
- **Cache intelligent** pour les performances
- **API REST** pour l'intégration mobile
- **Webhooks** pour les notifications
- **Synchronisation** avec d'autres systèmes

## 📞 Support et assistance

### En cas de problème
1. **Vérifier les permissions** de votre compte
2. **Contacter l'administration** pour les affectations
3. **Signaler les bugs** avec des captures d'écran
4. **Consulter les logs** d'erreur si disponible

### Ressources utiles
- **Guide utilisateur** : Ce document
- **Documentation technique** : `README.md`
- **Support technique** : [Contact de l'équipe]
- **Base de connaissances** : [Lien vers la KB]

---

## 📝 Notes de version

- **Version 1.0** : Fonctionnalité de base "Mes Classes"
- **Version 1.1** : Ajout des statistiques et actions rapides
- **Version 1.2** : Amélioration de l'interface et de la sécurité
- **Version 1.3** : Intégration complète avec les modules notes et présence

---

*Dernière mise à jour : <?php echo date('d/m/Y'); ?>*
*Version du document : 1.3*

