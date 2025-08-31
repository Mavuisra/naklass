# 🚀 Guide d'Utilisation Rapide - Cycles d'Enseignement

## 📋 Étapes pour Activer les Nouvelles Fonctionnalités

### 1️⃣ **Mise à jour de la base de données**
Exécutez ce fichier dans votre navigateur :
```
http://votre-domaine.com/update_classes_table.php
```

**Résultat attendu :** ✅ Toutes les colonnes ajoutées avec succès

### 2️⃣ **Test du système**
Vérifiez que tout fonctionne :
```
http://votre-domaine.com/test_cycles_enseignement.php
```

**Résultat attendu :** 🎉 Tests terminés avec succès !

### 3️⃣ **Utilisation du formulaire**
Allez sur la page de création de classe :
```
http://votre-domaine.com/classes/create.php
```

### 4️⃣ **Test de la page d'édition**
Testez la page d'édition des classes :
```
http://votre-domaine.com/test_edit_classes.php
```

### 5️⃣ **Test simple (en cas de problème)**
Si vous avez des erreurs, testez d'abord la syntaxe :
```
http://votre-domaine.com/test_edit_simple.php
```

## 🎯 **Comment Utiliser le Nouveau Système**

### **Création de Classes**
#### **Étape 1 : Sélectionner le Cycle**
- Choisissez le cycle principal (Maternelle, Primaire, Secondaire, etc.)
- Le champ "Niveau détaillé" apparaîtra automatiquement

#### **Étape 2 : Choisir le Niveau Détaillé**
- **Maternelle** : 1ʳᵉ, 2ᵉ, 3ᵉ année
- **Primaire** : 1ᵉ à 6ᵉ année
- **Secondaire** : 7ᵉ, 8ᵉ (Tronc commun) + 1ʳᵉ à 4ᵉ (Humanités)

#### **Étape 3 : Option/Section (Humanités uniquement)**
Si vous sélectionnez une année d'Humanités, choisissez :
- **Scientifique, Littéraire, Commerciale, Pédagogique**
- **Technique** (Construction, Électricité, Mécanique, Informatique)
- **Professionnel** (Secrétariat, Comptabilité, Hôtellerie, Couture)

### **Édition de Classes Existantes**
- Accédez à la page d'édition via `classes/edit.php?id=X`
- Tous les champs existants sont automatiquement remplis
- Modifiez les cycles, niveaux et options selon vos besoins
- La validation et l'interface dynamique fonctionnent identiquement

## 📚 **Exemples Concrets**

### **Classe de 6ᵉ Primaire**
- Cycle : **Primaire**
- Niveau détaillé : **6ᵉ année primaire**
- Option : *Aucune*

### **Classe de 3ᵉ Humanités Scientifique**
- Cycle : **Secondaire**
- Niveau détaillé : **3ᵉ humanités**
- Option : **Scientifique**

### **Classe de 1ʳᵉ Technique Électricité**
- Cycle : **Secondaire**
- Niveau détaillé : **1ʳᵉ humanités**
- Option : **Technique Électricité**

## 🔧 **En Cas de Problème**

### **Erreur "Colonnes manquantes"**
```
❌ Colonnes manquantes: niveau_detaille, option_section, cycle_complet
```
**Solution :** Exécutez d'abord `update_classes_table.php`

### **Erreur de connexion à la base**
```
❌ Erreur critique: [message d'erreur]
```
**Solution :** Vérifiez la configuration de la base de données dans `config/database.php`

### **Formulaire ne fonctionne pas**
**Solution :** Vérifiez que tous les fichiers JavaScript sont bien chargés

## 📞 **Support**

Si vous rencontrez des problèmes :
1. Vérifiez les messages d'erreur affichés
2. Consultez le fichier `CYCLES_ENSEIGNEMENT_README.md`
3. Vérifiez que tous les fichiers sont bien présents

## ✅ **Vérification Finale**

Après avoir créé une classe, vérifiez en base que :
- `niveau_detaille` contient la valeur (ex: `3eme_humanites`)
- `option_section` contient la valeur (ex: `scientifique`)
- `cycle_complet` contient la description complète

---

**🎓 Système Naklass - Cycles d'Enseignement**  
**Version :** 1.0  
**Dernière mise à jour :** 2025
