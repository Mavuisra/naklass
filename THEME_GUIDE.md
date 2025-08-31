# 🎨 Guide du Thème Naklass

## Couleur de Base : #0077b6

Naklass utilise une palette de couleurs cohérente basée sur la couleur principale **#0077b6** (bleu océan professionnel).

## 🎯 Palette de Couleurs

### Couleurs Principales
- **Primary:** `#0077b6` - Couleur principale de l'application
- **Primary Dark:** `#005577` - Version foncée pour les hovers et contrastes
- **Primary Darker:** `#004466` - Version très foncée pour les états actifs
- **Primary Light:** `#0088cc` - Version claire pour les accents
- **Primary Lighter:** `#3399dd` - Version très claire pour les backgrounds

### Couleurs avec Transparence
- **10%:** `rgba(0, 119, 182, 0.1)` - Backgrounds très légers
- **20%:** `rgba(0, 119, 182, 0.2)` - Borders et ombres légères
- **25%:** `rgba(0, 119, 182, 0.25)` - Focus states
- **30%:** `rgba(0, 119, 182, 0.3)` - Ombres moyennes
- **50%:** `rgba(0, 119, 182, 0.5)` - Overlays
- **80%:** `rgba(0, 119, 182, 0.8)` - Backgrounds semi-opaques

### Couleurs Complémentaires
- **Orange:** `#ff8500` - Couleur d'accent pour les éléments importants
- **Success:** `#28a745` - Vert pour les succès
- **Warning:** `#ffc107` - Jaune pour les avertissements
- **Danger:** `#dc3545` - Rouge pour les erreurs

## 🖌️ Utilisation dans le Code

### Variables CSS
```css
:root {
    --naklass-primary: #0077b6;
    --naklass-primary-dark: #005577;
    --naklass-gradient-primary: linear-gradient(135deg, #0077b6 0%, #005577 100%);
}
```

### Classes CSS Utilitaires
```css
.bg-naklass-primary       /* Background couleur principale */
.text-naklass-primary     /* Texte couleur principale */
.border-naklass-primary   /* Border couleur principale */
.btn-naklass             /* Bouton avec gradient Naklass */
.card-naklass            /* Card avec thème Naklass */
```

### Exemples d'Utilisation
```html
<!-- Bouton principal -->
<button class="btn btn-naklass">Action Principale</button>

<!-- Card avec thème -->
<div class="card card-naklass">
    <div class="card-header">Titre</div>
    <div class="card-body">Contenu</div>
</div>

<!-- Texte avec couleur de base -->
<h2 class="text-naklass-primary">Titre Important</h2>
```

## 🎨 Gradients Prédéfinis

### Gradient Principal
```css
background: linear-gradient(135deg, #0077b6 0%, #005577 100%);
```

### Gradient Inversé
```css
background: linear-gradient(135deg, #005577 0%, #0077b6 100%);
```

### Gradient Léger
```css
background: linear-gradient(135deg, #0088cc 0%, #0077b6 100%);
```

## 📱 États et Interactions

### États des Boutons
- **Normal:** `#0077b6`
- **Hover:** `#006699` avec `transform: translateY(-2px)`
- **Active:** `#004466`
- **Focus:** Box-shadow avec `rgba(0, 119, 182, 0.25)`

### États des Formulaires
- **Focus:** Border `#0077b6` + box-shadow
- **Valid:** Border verte avec `#28a745`
- **Invalid:** Border rouge avec `#dc3545`

## 🌊 Animations et Effets

### Effet de Pulse
```css
.pulse-naklass {
    animation: naklass-pulse 2s infinite;
}
```

### Effet de Shimmer (Loading)
```css
.loading-naklass::after {
    background: linear-gradient(90deg, transparent, rgba(0, 119, 182, 0.2), transparent);
    animation: loading-shimmer 1.5s infinite;
}
```

### Hover Scale
```css
.hover-naklass:hover {
    color: #0077b6;
    transform: scale(1.05);
}
```

## 🎯 Applications Spécifiques

### Sidebar
- Background: Gradient principal
- Items actifs: Border orange `#ff8500`
- Hover: Background avec transparence

### Dashboard
- Cards: Ombre avec couleur principale en transparence
- Graphiques: Couleur principale pour les données
- Statistiques: Icônes avec couleurs thématiques

### Formulaires
- Focus states: Couleur principale
- Boutons: Gradient principal
- Validation: Couleurs d'état appropriées

### Navigation
- Links actifs: Couleur principale
- Hover: Transition vers couleur principale
- Breadcrumb: Couleur principale pour les éléments actifs

## 📋 Checklist d'Implémentation

### ✅ Fichiers Mis à Jour
- [x] `assets/css/common.css` - Variables globales
- [x] `assets/css/dashboard.css` - Interface principale
- [x] `assets/css/auth.css` - Pages d'authentification
- [x] `assets/css/naklass-theme.css` - Thème complet
- [x] `assets/js/dashboard.js` - Graphiques et interactions

### 🎨 Éléments Stylisés
- [x] Boutons primaires
- [x] Formulaires (focus, validation)
- [x] Navigation et sidebar
- [x] Cards et containers
- [x] Modales et alertes
- [x] Graphiques Chart.js
- [x] États hover et active

## 🔧 Maintenance

### Ajout de Nouveaux Composants
1. Utiliser les variables CSS `--naklass-*`
2. Respecter les guidelines de couleurs
3. Tester les états hover/focus/active
4. Valider l'accessibilité des contrastes

### Modification de la Couleur de Base
1. Modifier `--naklass-primary` dans `naklass-theme.css`
2. Régénérer les couleurs dérivées avec un outil de palette
3. Tester tous les composants
4. Valider l'accessibilité

---

**Note:** Ce thème respecte les standards d'accessibilité WCAG 2.1 AA pour les contrastes de couleurs.
