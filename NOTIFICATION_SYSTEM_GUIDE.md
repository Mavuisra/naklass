# 🔔 Guide du Système de Notifications Naklass

## 📋 Table des Matières

1. [Vue d'ensemble](#vue-densemble)
2. [Installation](#installation)
3. [Types de Notifications](#types-de-notifications)
4. [Interface Utilisateur](#interface-utilisateur)
5. [Intégration dans les Modules](#intégration-dans-les-modules)
6. [API et Fonctions](#api-et-fonctions)
7. [Configuration](#configuration)
8. [Dépannage](#dépannage)

## 🎯 Vue d'ensemble

Le système de notifications Naklass est un système complet qui permet de notifier les utilisateurs des événements importants dans l'application. Il supporte plusieurs canaux de notification et offre une interface utilisateur intuitive.

### Fonctionnalités principales :
- ✅ Notifications en temps réel
- ✅ Support multi-canaux (Web, Email, Push, SMS)
- ✅ Templates personnalisables
- ✅ Priorités et catégories
- ✅ Interface utilisateur moderne
- ✅ API complète
- ✅ Intégration automatique dans les modules existants

## 🚀 Installation

### Étape 1: Installation des Tables
```bash
# Accéder à l'URL d'installation
http://localhost/naklass/install_notification_system.php
```

### Étape 2: Vérification de l'Installation
```bash
# Tester le système
http://localhost/naklass/test_notification_system.php
```

### Étape 3: Vérification de l'Interface
- Le widget de notifications apparaît dans la sidebar
- Le lien "Notifications" est ajouté au menu
- La page des notifications est accessible

## 📢 Types de Notifications

### Académiques
- `student_registered` - Nouvelle inscription d'élève
- `student_class_changed` - Changement de classe
- `course_assigned` - Cours assigné
- `teacher_assigned` - Enseignant assigné
- `student_promoted` - Promotion d'élève
- `student_graduated` - Diplômé

### Financières
- `payment_received` - Paiement reçu
- `payment_overdue` - Paiement en retard
- `fees_updated` - Frais mis à jour
- `refund_processed` - Remboursement effectué
- `invoice_generated` - Facture générée

### Utilisateurs
- `user_created` - Nouveau compte créé
- `user_updated` - Profil modifié
- `user_deleted` - Compte supprimé
- `login_suspicious` - Connexion suspecte
- `password_changed` - Mot de passe modifié

### École
- `school_created` - Nouvelle école créée
- `school_validated` - École validée
- `school_updated` - Paramètres modifiés
- `school_logo_changed` - Logo changé

### Alertes
- `student_absent` - Absence répétée
- `class_capacity` - Capacité atteinte
- `low_grades` - Notes faibles
- `system_maintenance` - Maintenance système
- `backup_completed` - Sauvegarde terminée

## 🎨 Interface Utilisateur

### Widget de Notifications
- **Emplacement** : Sidebar (en bas)
- **Fonctionnalités** :
  - Badge avec nombre de notifications non lues
  - Dropdown avec les dernières notifications
  - Actions rapides (marquer comme lu, supprimer)
  - Liens vers les actions

### Page des Notifications
- **URL** : `/notifications.php`
- **Fonctionnalités** :
  - Vue complète de toutes les notifications
  - Filtres par statut, type, catégorie
  - Statistiques en temps réel
  - Actions en masse
  - Interface responsive

### Actions Disponibles
- ✅ Marquer comme lu
- ✅ Marquer toutes comme lues
- ✅ Archiver
- ✅ Supprimer
- ✅ Filtrer
- ✅ Rechercher

## 🔧 Intégration dans les Modules

### Classes (`classes/create.php`)
```php
// Notification lors de la création d'une classe
$notificationManager->createNotificationFromTemplate('course_assigned', [
    'class_name' => $data['nom_classe'],
    'cycle_complet' => $cycle_complet,
    'professeur_name' => $data['professeur_principal_id'] ? 'Professeur assigné' : 'Aucun professeur assigné',
    'capacite_max' => $data['capacite_max'],
    'salle_classe' => $data['salle_classe'] ?: 'Non spécifiée'
], [
    'priority' => 'normal',
    'channels' => ['web'],
    'action_url' => createSecureLink('view.php', $classe_id, 'id'),
    'action_text' => 'Voir la classe'
]);
```

### Élèves (`students/add.php`)
```php
// Notification lors de l'inscription d'un élève
$notificationManager->createNotificationFromTemplate('student_registered', [
    'student_name' => $data['prenom'] . ' ' . $data['nom'],
    'class_name' => $classe_info['nom_classe'],
    'matricule' => $matricule,
    'niveau' => $classe_info['niveau'],
    'cycle' => $classe_info['cycle']
], [
    'priority' => 'normal',
    'channels' => ['web'],
    'action_url' => 'view.php?id=' . $eleve_id,
    'action_text' => 'Voir l\'élève'
]);
```

### Finance (`finance/payment.php`)
```php
// Notification lors d'un paiement
$notificationManager->createNotificationFromTemplate('payment_received', [
    'student_name' => $eleve['prenom'] . ' ' . $eleve['nom'],
    'amount' => formatAmount($montant_total, $monnaie),
    'payment_type' => ucfirst($mode_paiement),
    'receipt_number' => $numero_recu,
    'matricule' => $eleve['matricule']
], [
    'priority' => 'normal',
    'channels' => ['web'],
    'action_url' => "print_receipt.php?id=$paiement_id",
    'action_text' => 'Imprimer le reçu'
]);
```

## 🛠️ API et Fonctions

### NotificationManager Class

#### Création de Notifications
```php
// Notification simple
$notificationManager->createNotification(
    $type,           // Type de notification
    $title,          // Titre
    $message,        // Message
    $data,           // Données supplémentaires (JSON)
    $priority,       // Priorité (low, normal, high, urgent)
    $channels,       // Canaux ['web', 'email', 'push', 'sms']
    $action_url,     // URL d'action
    $action_text,    // Texte du bouton d'action
    $expires_in_minutes // Minutes avant expiration
);

// Notification avec template
$notificationManager->createNotificationFromTemplate(
    $type,           // Type de notification
    $variables,      // Variables pour le template
    $options         // Options supplémentaires
);

// Notification en masse
$notificationManager->createBulkNotification(
    $user_ids,       // IDs des utilisateurs
    $type,           // Type de notification
    $title,          // Titre
    $message,        // Message
    $options         // Options
);
```

#### Gestion des Notifications
```php
// Récupérer les notifications
$notifications = $notificationManager->getUserNotifications($user_id, $filters);

// Marquer comme lue
$notificationManager->markAsRead($notification_id);

// Marquer toutes comme lues
$notificationManager->markAllAsRead($user_id, $type);

// Archiver
$notificationManager->archiveNotification($notification_id);

// Supprimer
$notificationManager->deleteNotification($notification_id);

// Obtenir le nombre de notifications non lues
$count = $notificationManager->getUnreadCount($user_id, $type);

// Obtenir les statistiques
$stats = $notificationManager->getNotificationStats($user_id);
```

#### Maintenance
```php
// Nettoyer les anciennes notifications
$notificationManager->cleanOldNotifications($days_old);
```

### AJAX Endpoints

#### Marquer comme lue
```javascript
fetch('ajax/mark_notification_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ notification_id: id })
});
```

#### Marquer toutes comme lues
```javascript
fetch('ajax/mark_all_notifications_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' }
});
```

#### Supprimer
```javascript
fetch('ajax/delete_notification.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ notification_id: id })
});
```

#### Obtenir le compteur
```javascript
fetch('ajax/get_notification_count.php')
.then(response => response.json())
.then(data => {
    // Mettre à jour le badge
});
```

## ⚙️ Configuration

### Préférences Utilisateur
Les utilisateurs peuvent configurer leurs préférences de notification dans la table `notification_preferences` :

- `email_enabled` - Activer les emails
- `web_enabled` - Activer les notifications web
- `push_enabled` - Activer les notifications push
- `frequency` - Fréquence (immediate, daily, weekly, never)
- `quiet_hours_start` - Début des heures silencieuses
- `quiet_hours_end` - Fin des heures silencieuses

### Templates
Les templates sont stockés dans la table `notification_templates` et peuvent être personnalisés :

- `title_template` - Template du titre
- `message_template` - Template du message
- `email_subject_template` - Template du sujet email
- `email_body_template` - Template du corps email
- `variables` - Variables disponibles (JSON)

### Canaux
Les canaux sont configurés dans la table `notification_channels` :

- `web` - Notifications web (toujours actif)
- `email` - Notifications par email
- `push` - Notifications push mobiles
- `sms` - Notifications par SMS

## 🔍 Dépannage

### Problèmes Courants

#### 1. Widget de notifications ne s'affiche pas
**Solution** :
- Vérifier que `includes/notification_widget.php` existe
- Vérifier que `includes/NotificationManager.php` est inclus
- Vérifier les permissions de l'utilisateur

#### 2. Notifications ne se créent pas
**Solution** :
- Vérifier que les tables sont créées
- Vérifier les logs d'erreur PHP
- Vérifier la connexion à la base de données

#### 3. AJAX ne fonctionne pas
**Solution** :
- Vérifier que les fichiers AJAX existent dans `/ajax/`
- Vérifier les permissions des fichiers
- Vérifier la console JavaScript

#### 4. Templates ne fonctionnent pas
**Solution** :
- Vérifier que les templates sont insérés dans la base
- Vérifier la syntaxe des variables `{variable}`
- Vérifier que les variables sont fournies

### Logs et Debug
```php
// Activer les logs d'erreur
error_log("Erreur notification: " . $e->getMessage());

// Vérifier les notifications
$notifications = $notificationManager->getUserNotifications();
var_dump($notifications);

// Vérifier les statistiques
$stats = $notificationManager->getNotificationStats();
var_dump($stats);
```

### Tests
```bash
# Test complet du système
http://localhost/naklass/test_notification_system.php

# Test d'installation
http://localhost/naklass/install_notification_system.php
```

## 📚 Exemples d'Utilisation

### Créer une notification personnalisée
```php
$notificationManager = new NotificationManager();

$notification_id = $notificationManager->createNotification(
    'custom_event',
    'Événement personnalisé',
    'Ceci est un événement personnalisé créé par l\'utilisateur.',
    [
        'user_id' => $_SESSION['user_id'],
        'timestamp' => time(),
        'custom_data' => 'valeur'
    ],
    'high',
    ['web', 'email'],
    'custom_page.php?id=123',
    'Voir l\'événement',
    1440 // 24 heures
);
```

### Filtrer les notifications
```php
$notifications = $notificationManager->getUserNotifications(null, [
    'status' => 'unread',
    'category' => 'financial',
    'type' => 'payment_received',
    'limit' => 10,
    'offset' => 0
]);
```

### Créer un template personnalisé
```sql
INSERT INTO notification_templates (
    type, category, title_template, message_template, 
    email_subject_template, email_body_template
) VALUES (
    'custom_event',
    'system',
    'Événement personnalisé: {event_name}',
    'L\'événement {event_name} s\'est produit à {timestamp}.',
    'Notification: {event_name}',
    'Bonjour,\n\nL\'événement {event_name} s\'est produit.\n\nCordialement,\nSystème Naklass'
);
```

## 🎉 Conclusion

Le système de notifications Naklass est maintenant complètement intégré et opérationnel. Il offre :

- ✅ **Fonctionnalité complète** : Création, gestion, affichage des notifications
- ✅ **Interface moderne** : Widget et page dédiée avec design responsive
- ✅ **Intégration automatique** : Notifications dans les modules existants
- ✅ **API robuste** : Fonctions PHP et endpoints AJAX
- ✅ **Extensibilité** : Facilement personnalisable et extensible

Le système est prêt à être utilisé en production et peut être étendu selon les besoins futurs.

---

**Développé par l'équipe Naklass** 🚀
