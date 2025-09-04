# 📊 Guide d'Exportation et d'Importation Excel - Naklass

## 🎯 **Fonctionnalités disponibles**

### **📤 Exportation Excel**

#### **1. Exportation avec filtres avancés**
- **Accès** : Page des élèves → Menu "Exporter" → "Excel avec filtres"
- **Filtres disponibles** :
  - **Classe** : Exporter une classe spécifique
  - **Statut d'inscription** : validée, en_cours, annulée, archivé
  - **Année scolaire** : Filtrer par année
  - **Élèves inactifs** : Option pour inclure les élèves inactifs

#### **2. Exportation complète**
- **Accès** : Page des élèves → Menu "Exporter" → "Excel (tous les élèves)"
- **Contenu** : Tous les élèves de l'école avec toutes les informations

### **📥 Importation Excel**

#### **1. Importation avec gestion des doublons**
- **Accès** : Page d'inscription → Onglet "Importation Excel"
- **Stratégies de doublons** :
  - **Ignorer** : Les élèves existants sont ignorés
  - **Mettre à jour** : Les informations des élèves existants sont mises à jour
  - **Demander** : Le système demande confirmation pour chaque doublon

#### **2. Template Excel**
- **Téléchargement** : Page d'inscription → Onglet "Importation Excel" → "Télécharger le modèle"
- **Structure** : Fichier pré-formaté avec les colonnes requises

## 📋 **Structure du fichier Excel**

### **Colonnes obligatoires :**
1. **Matricule** - Identifiant unique de l'élève
2. **Nom** - Nom de famille
3. **Post-nom** - Post-nom (optionnel)
4. **Prénom** - Prénom de l'élève
5. **Sexe** - M ou F
6. **Date de naissance** - Format : YYYY-MM-DD
7. **Lieu de naissance** - Ville/pays de naissance
8. **Nationalité** - Nationalité de l'élève
9. **Téléphone** - Numéro de téléphone
10. **Email** - Adresse email (optionnel)
11. **Adresse** - Adresse complète
12. **Quartier** - Quartier de résidence

### **Exemple de données :**
```
Matricule | Nom    | Post-nom | Prénom | Sexe | Date de naissance | Lieu de naissance | Nationalité | Téléphone | Email | Adresse | Quartier
ELEV001   | MULUMBA| KABONGO  | Jean   | M    | 2010-05-15        | Kinshasa         | Congolaise  | +243...   | jean@...| Av. ... | Matonge
```

## 🛡️ **Sécurités implémentées**

### **Exportation :**
- ✅ **Isolation des écoles** : Seuls les élèves de votre école sont exportés
- ✅ **Permissions** : Seuls les utilisateurs autorisés peuvent exporter
- ✅ **Traçabilité** : Toutes les exportations sont enregistrées

### **Importation :**
- ✅ **Vérification de l'école** : Les élèves ne peuvent être importés que dans leur école
- ✅ **Validation des classes** : Seules les classes actives sont autorisées
- ✅ **Unicité des matricules** : Chaque matricule est unique par école
- ✅ **Statut automatique** : Tous les élèves importés sont au statut "validée"

## 📊 **Types d'exportation**

### **1. Exportation par classe**
```
URL: export_excel.php?classe_id=123
Résultat: Tous les élèves de la classe 123
```

### **2. Exportation par statut**
```
URL: export_excel.php?statut=validée
Résultat: Tous les élèves avec le statut "validée"
```

### **3. Exportation par année**
```
URL: export_excel.php?annee_scolaire=2024-2025
Résultat: Tous les élèves de l'année 2024-2025
```

### **4. Exportation combinée**
```
URL: export_excel.php?classe_id=123&statut=validée&annee_scolaire=2024-2025
Résultat: Élèves de la classe 123, statut "validée", année 2024-2025
```

## 🔧 **Configuration requise**

### **PhpSpreadsheet**
- **Installation** : `composer require phpoffice/phpspreadsheet`
- **Extensions PHP** : ZIP, XML, GD
- **Vérification** : Utilisez `install_phpspreadsheet.php`

### **Permissions**
- **Rôles autorisés** : admin, direction, secretaire
- **École configurée** : L'utilisateur doit être connecté à une école

## 📈 **Statistiques d'exportation**

### **Informations incluses dans le fichier :**
- Date et heure d'exportation
- Nombre d'élèves exportés
- École source
- Critères de filtrage appliqués
- Classe (si filtré par classe)
- Statut (si filtré par statut)

## 💡 **Bonnes pratiques**

### **Exportation :**
1. **Utilisez les filtres** pour des exports ciblés
2. **Vérifiez les critères** avant l'exportation
3. **Sauvegardez régulièrement** vos données
4. **Respectez la confidentialité** des données exportées

### **Importation :**
1. **Utilisez le template** fourni
2. **Vérifiez les données** avant l'importation
3. **Testez avec un petit échantillon** d'abord
4. **Choisissez la bonne stratégie** de gestion des doublons
5. **Vérifiez les résultats** après l'importation

## 🚨 **Gestion des erreurs**

### **Erreurs courantes :**
- **Fichier non trouvé** : Vérifiez que le fichier est bien uploadé
- **Format incorrect** : Utilisez le template fourni
- **Données manquantes** : Vérifiez les colonnes obligatoires
- **Matricule dupliqué** : Chaque matricule doit être unique dans l'école
- **Classe invalide** : La classe doit appartenir à votre école

### **Solutions :**
1. **Vérifiez les logs** d'erreur
2. **Utilisez le template** officiel
3. **Contactez l'administrateur** si nécessaire
4. **Testez avec des données simples** d'abord

## 📞 **Support**

En cas de problème :
1. Vérifiez ce guide
2. Consultez les logs d'erreur
3. Testez avec le template fourni
4. Contactez l'administrateur système

---

*Dernière mise à jour : Exportation et importation Excel complètes avec filtres avancés*

