# 📋 Guide des Statuts d'Inscription - Naklass

## 🎯 **Statuts disponibles**

### **1. "validée" ✅ (Recommandé)**
- **Description** : Inscription complète et approuvée
- **Utilisation** : 
  - Inscriptions manuelles
  - Importations Excel
  - Élèves transférés et approuvés
- **Avantages** :
  - L'élève peut participer aux cours
  - Compte dans l'effectif de la classe
  - Accès complet aux fonctionnalités

### **2. "en_cours" ⏳**
- **Description** : Inscription en cours de traitement
- **Utilisation** : 
  - Inscriptions en attente de validation
  - Documents manquants
  - Paiements en cours
- **Limitations** :
  - L'élève ne peut pas encore participer aux cours
  - N'est pas compté dans l'effectif
  - Accès limité

### **3. "annulée" ❌**
- **Description** : Inscription annulée ou refusée
- **Utilisation** :
  - Inscriptions rejetées
  - Élèves retirés
  - Erreurs de saisie
- **Effets** :
  - L'élève n'a aucun accès
  - N'est pas compté dans l'effectif
  - Peut être réactivée si nécessaire

### **4. "archivé" 📁**
- **Description** : Inscription archivée (fin d'année)
- **Utilisation** :
  - Clôture de l'année scolaire
  - Données historiques
- **Caractéristiques** :
  - Lecture seule
  - Pas de modification possible
  - Conservation des données

## 🔄 **Changements de statut**

### **Progression normale :**
```
en_cours → validée → archivé
```

### **Cas d'annulation :**
```
en_cours → annulée
validée → annulée
```

## ⚙️ **Configuration actuelle**

**Dans Naklass, toutes les nouvelles inscriptions sont automatiquement au statut "validée" :**

- ✅ **Inscriptions manuelles** → Statut "validée"
- ✅ **Importations Excel** → Statut "validée"
- ✅ **Transferts d'élèves** → Statut "validée"

## 🛡️ **Sécurités implémentées**

1. **Vérification de l'école** : Les élèves ne peuvent être inscrits que dans leur école
2. **Validation des classes** : Seules les classes actives sont autorisées
3. **Unicité des matricules** : Chaque matricule est unique par école
4. **Traçabilité** : Toutes les actions sont enregistrées avec l'utilisateur responsable

## 📊 **Impact sur les statistiques**

- **Effectif de classe** : Seuls les élèves "validée" sont comptés
- **Rapports** : Incluent tous les statuts pour l'historique
- **Présence** : Seuls les élèves "validée" peuvent être marqués présents

## 💡 **Recommandations**

1. **Utilisez "validée"** pour toutes les nouvelles inscriptions
2. **Vérifiez les statuts** avant de générer des rapports
3. **Maintenez la cohérence** entre les différentes méthodes d'inscription
4. **Documentez les changements** de statut importants

---

*Dernière mise à jour : Importation Excel avec statut "validée" automatique*

