# Guide d'Installation e-cityzen Gabon

## Prérequis

1. **WAMP Server** (ou XAMPP/LAMP) installé et démarré
2. **PHP 7.4+** avec extensions :
   - PDO MySQL
   - JSON
   - Session
3. **MySQL 5.7+** ou **MariaDB 10.3+**
4. **Navigateur moderne** (Chrome, Firefox, Edge)

## Installation

### Étape 1 : Copier les fichiers

1. Copiez tout le dossier `Ecityzen` dans `C:\wamp64\www\` (ou votre répertoire www)
2. Le chemin final doit être : `C:\wamp64\www\Ecityzen\`

### Étape 2 : Créer la base de données

1. Ouvrez **phpMyAdmin** : http://localhost/phpmyadmin
2. Créez une nouvelle base de données nommée : `ecityzen_gabon`
3. Sélectionnez la base de données créée
4. Allez dans l'onglet **Importer**
5. Importez le fichier `database.sql`
6. Importez ensuite le fichier `database_demo_data.sql` pour ajouter des données de démonstration

### Étape 3 : Configurer la connexion à la base de données

Vérifiez le fichier `config/database.php` :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecityzen_gabon');
define('DB_USER', 'root');  // Par défaut dans WAMP
define('DB_PASS', '');      // Par défaut vide dans WAMP
```

### Étape 4 : Accéder à l'application

1. Assurez-vous que WAMP est démarré (icône verte)
2. Ouvrez votre navigateur
3. Accédez à : **http://localhost/Ecityzen/ECITYZEN.html**

## Comptes de démonstration

Après avoir importé `database_demo_data.sql`, vous pouvez vous connecter avec :

| Rôle | Téléphone | Mot de passe |
|------|-----------|--------------|
| Citoyen | +241 06 12 34 56 | password |
| Commerçant | +241 07 23 45 67 | password |
| Agent | +241 05 34 56 78 | password |
| Manager | +241 04 45 67 89 | password |
| Hôpital | +241 02 67 89 01 | password |
| Super Admin | +241 03 56 78 90 | password |

## Fonctionnalités connectées à la base de données

### ✅ Modules entièrement fonctionnels :

1. **Authentification** - Connexion/Inscription avec session PHP
2. **Signalements** - CRUD complet, assignation automatique aux agents
3. **Demandes administratives** - CRUD complet, assignation automatique
4. **Paiements** - Enregistrement en base de données
5. **Licences commerciales** - Gestion complète pour les commerçants
6. **Emplacements marché** - Réservation et gestion
7. **Missions agents** - Planification et suivi
8. **Budget municipal** - Consultation publique avec données réelles
9. **Travaux publics** - Chantiers géolocalisés sur carte
10. **Notifications** - Système complet avec préférences
11. **Chefs de quartier** - Signalements collectifs
12. **Réservations marchés** - Stands avec QR codes
13. **Assistance** - FAQ et messages
14. **Feedback** - Notations et commentaires
15. **Statistiques** - KPIs en temps réel pour managers

## Structure des APIs

Toutes les APIs sont dans le dossier `api/` :

- `login.php` - Authentification
- `register.php` - Inscription
- `logout.php` - Déconnexion
- `demandes.php` - Gestion des demandes
- `signalements.php` - Gestion des signalements
- `paiements.php` - Gestion des paiements
- `licences.php` - Licences commerciales
- `emplacements.php` - Emplacements marché
- `missions.php` - Missions agents
- `stats.php` - Statistiques (managers)
- `budget.php` - Budget municipal
- `chantiers.php` - Travaux publics
- `chefs_quartier.php` - Signalements chefs de quartier
- `reservations.php` - Réservations stands
- `notifications.php` - Notifications
- `preferences_notifications.php` - Préférences notifications
- `assistance.php` - Assistance et FAQ
- `feedback.php` - Feedback et notations
- `mobile_money.php` - Intégration Mobile Money (structure)

## Vérification du fonctionnement

### Test 1 : Connexion
1. Connectez-vous avec un compte de démo
2. Vérifiez que le dashboard s'affiche correctement

### Test 2 : Données réelles
1. Créez un nouveau signalement
2. Vérifiez qu'il apparaît dans le dashboard de l'agent
3. L'agent peut le valider

### Test 3 : Budget municipal
1. Cliquez sur "Budget Municipal"
2. Vérifiez que les données s'affichent (après import de `database_demo_data.sql`)

### Test 4 : Travaux publics
1. Cliquez sur "Travaux Publics"
2. Vérifiez que les chantiers s'affichent sur la carte

## Dépannage

### Erreur : "API non disponible"
- Vérifiez que WAMP est démarré
- Vérifiez que la base de données est créée
- Vérifiez les paramètres dans `config/database.php`

### Erreur : "Table doesn't exist"
- Importez le fichier `database.sql` dans phpMyAdmin
- Vérifiez que toutes les tables sont créées

### Erreur : "Non autorisé" (401/403)
- Vérifiez que vous êtes bien connecté
- Vérifiez que votre session PHP est active
- Essayez de vous reconnecter

### Données vides
- Importez le fichier `database_demo_data.sql`
- Vérifiez que les données sont bien insérées dans phpMyAdmin

## Production

Pour mettre en production :

1. **Sécurité** :
   - Changez tous les mots de passe par défaut
   - Configurez HTTPS
   - Activez les validations côté serveur
   - Limitez les tentatives de connexion

2. **Base de données** :
   - Utilisez un utilisateur MySQL dédié (pas root)
   - Configurez des sauvegardes automatiques
   - Activez les logs

3. **APIs Mobile Money** :
   - Configurez les clés API Airtel/Moov dans `api/mobile_money.php`
   - Testez les callbacks

4. **Notifications** :
   - Configurez un service SMS (Twilio, etc.)
   - Configurez l'envoi d'emails (SMTP)
   - Configurez les notifications push (Service Worker)

## Support

Pour toute question ou problème, vérifiez :
1. Les logs PHP dans `C:\wamp64\logs\php_error.log`
2. Les logs Apache dans `C:\wamp64\logs\apache_error.log`
3. La console du navigateur (F12) pour les erreurs JavaScript



