# 📚 Guide d'Inscription des Élèves

## 🎯 Vue d'ensemble

Le système d'inscription des élèves de Naklass a été conçu pour automatiser et simplifier le processus d'inscription. Il génère automatiquement un matricule unique et permet de créer une carte d'élève professionnelle.

## ✨ Fonctionnalités Principales

### 📝 **Inscription Complète**
- **Formulaire détaillé** avec toutes les informations nécessaires
- **Validation automatique** des données saisies
- **Gestion des tuteurs/parents** avec informations de contact
- **Upload de photo** pour la carte d'élève

### 🎫 **Génération Automatique de Matricule**
- **Format standardisé** : `EL24XXXX` (EL + année + numéro séquentiel)
- **Unicité garantie** au niveau de l'établissement
- **Génération automatique** lors de l'inscription
- **Pas de doublons** possible

### 💳 **Carte d'Élève Automatique**
- **Design professionnel** avec logo de l'école
- **QR Code** pour identification rapide
- **Informations essentielles** : nom, matricule, photo, âge
- **Format imprimable** optimisé pour plastification

## 🚀 Processus d'Inscription

### Étape 1: Accès à l'inscription
```
Navigation : Élèves > Inscrire un Élève
URL : /students/add.php
```

### Étape 2: Saisie des informations
1. **Informations personnelles**
   - Nom, prénom, post-nom
   - Sexe, date et lieu de naissance
   - Nationalité, groupe sanguin

2. **Contact et adresse**
   - Téléphone, email
   - Adresse complète
   - Quartier, commune, ville, province

3. **Informations médicales**
   - Allergies connues
   - Handicaps ou besoins spéciaux
   - Dernier établissement fréquenté

4. **Photo de l'élève**
   - Format accepté : JPG, PNG (max 5MB)
   - Prévisualisation en temps réel

5. **Tuteurs/Parents**
   - Au moins un tuteur obligatoire
   - Informations complètes de contact
   - Autorisations de sortie

### Étape 3: Validation et inscription
- Validation automatique des champs
- Génération du matricule unique
- Enregistrement en base de données
- Redirection vers la page de succès

### Étape 4: Génération de la carte
- Carte générée automatiquement
- QR Code avec identité numérique
- Format prêt pour impression
- Possibilité de plastification

## 🔧 Fonctionnalités Techniques

### Génération de Matricule
```php
// Format : EL + Année (2 chiffres) + Numéro séquentiel (4 chiffres)
$matricule = generateMatricule('EL', $db);
// Exemple : EL240001, EL240002, etc.
```

### Gestion des Photos
```
Répertoire : /uploads/students/
Format : {student_id}.jpg
Fallback : /assets/images/default-avatar.png
```

### QR Code de la Carte
```json
{
    "matricule": "EL240001",
    "nom": "Doe",
    "prenom": "John",
    "ecole_id": 1,
    "id": 123
}
```

## 📋 Actions Disponibles

### Dans la Liste des Élèves
- 🎫 **Générer Carte** : Créer/réimprimer la carte d'élève
- 👁️ **Voir** : Consulter le profil complet
- ✏️ **Modifier** : Éditer les informations
- 🗑️ **Supprimer** : Supprimer l'élève (avec confirmation)

### Après Inscription
- 🎉 **Page de succès** avec récapitulatif
- 🎫 **Génération immédiate** de la carte
- 🏫 **Assignment à une classe** directe
- 📋 **Prochaines étapes** suggérées

## 🎨 Design et Interface

### Page d'Inscription
- **Interface moderne** avec cards organisées
- **Validation en temps réel** JavaScript
- **Responsive design** pour tous les écrans
- **Sauvegarde brouillon** pour reprendre plus tard

### Carte d'Élève
- **Design gradient** professionnel
- **Logo de l'école** intégré
- **QR Code** en bas à droite
- **Informations optimisées** pour l'espace disponible

## 🔒 Sécurité et Validations

### Validations Côté Serveur
- **Champs obligatoires** vérifiés
- **Format email** validé
- **Unicité matricule** garantie
- **Sécurisation uploads** (types de fichiers)

### Validations Côté Client
- **Formulaires interactifs** avec feedback
- **Prévisualisation photo** avant upload
- **Validation tuteurs** obligatoires
- **Messages d'erreur** contextuels

## 📊 Statistiques et Suivi

### Informations Automatiques
- **Date d'inscription** enregistrée
- **Utilisateur créateur** tracé
- **Année académique** associée
- **Logs d'actions** générés

### Rapports Disponibles
- Matricules générés par période
- Cartes d'élèves créées
- Inscriptions par mois
- Statistiques des tuteurs

## 🎯 Bonnes Pratiques

### Pour les Administrateurs
1. **Vérifiez les photos** avant génération de carte
2. **Plastifiez les cartes** pour la durabilité
3. **Conservez une copie numérique** des cartes
4. **Mettez à jour** les informations si nécessaire

### Pour la Saisie
1. **Informations complètes** dès l'inscription
2. **Photos de qualité** pour les cartes
3. **Coordonnées tuteurs** à jour
4. **Vérification orthographe** des noms

## 🔄 Maintenance

### Fichiers Principaux
- `add.php` : Formulaire d'inscription
- `inscription_success.php` : Page de confirmation
- `generate_card.php` : Génération de cartes
- `index.php` : Liste des élèves

### Répertoires
- `uploads/students/` : Photos des élèves
- `assets/images/` : Images par défaut

## 📞 Support

En cas de problème avec l'inscription ou la génération de cartes :

1. Vérifiez les **permissions du répertoire** `uploads/students/`
2. Contrôlez la **taille des fichiers** uploadés
3. Validez la **configuration de la base de données**
4. Consultez les **logs d'erreurs** PHP

---

*Guide mis à jour pour Naklass v2.0*
