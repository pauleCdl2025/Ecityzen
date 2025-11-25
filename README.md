# e-cityzen Gabon - Application Web Complète

Application de gestion des services citoyens et administration numérique pour le Gabon.

## 📋 Prérequis

- **WAMP Server** (Windows, Apache, MySQL, PHP)
  - Télécharger depuis: https://www.wampserver.com/
  - Version PHP recommandée: 7.4 ou supérieure
  - Extension PDO MySQL activée

## 🚀 Installation

### Étape 1: Installer WAMP
1. Téléchargez et installez WAMP Server
2. Démarrez WAMP (icône verte dans la barre des tâches)
3. Vérifiez que Apache et MySQL sont démarrés (icônes vertes)

### Étape 2: Configurer la base de données
1. Ouvrez phpMyAdmin: http://localhost/phpmyadmin
2. Créez une nouvelle base de données nommée `ecityzen_gabon`
3. Importez le fichier `database.sql`:
   - Cliquez sur la base de données `ecityzen_gabon`
   - Onglet "Importer"
   - Choisissez le fichier `database.sql`
   - Cliquez sur "Exécuter"

### Étape 3: Configurer l'application
1. Placez tous les fichiers dans le dossier `www` de WAMP:
   - Chemin par défaut: `C:\wamp64\www\Ecityzen\`
2. Vérifiez la configuration dans `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'ecityzen_gabon');
   define('DB_USER', 'root');
   define('DB_PASS', ''); // Laissez vide par défaut pour WAMP
   ```

### Étape 4: Accéder à l'application
1. Ouvrez votre navigateur
2. Accédez à: `http://localhost/Ecityzen/ECITYZEN.html`

## 👥 Utilisateurs de démonstration

Tous les utilisateurs ont le mot de passe par défaut: **password**

| Rôle | Nom | Email |
|------|-----|-------|
| Citoyen | MBANG Pierre | pierre.mbang@example.com |
| Commerçant | OBAME Marie | marie.obame@example.com |
| Agent | NGOUA Marie | marie.ngoua@example.com |
| Manager | MENGUE Paul | paul.mengue@example.com |
| Super Admin | MINTSA NGUEMA Claude | claude.mintsa@example.com |

## 📁 Structure des fichiers

```
Ecityzen/
├── ECITYZEN.html          # Interface principale
├── logoMarieAkanda.webp  # Logo de l'application
├── database.sql           # Script de création de la base de données
├── config/
│   └── database.php      # Configuration de la connexion DB
├── api/
│   ├── login.php         # API de connexion
│   ├── logout.php        # API de déconnexion
│   ├── signalements.php  # API des signalements
│   ├── demandes.php      # API des demandes administratives
│   ├── paiements.php     # API des paiements
│   └── stats.php         # API des statistiques
└── README.md             # Ce fichier
```

## 🔧 Configuration avancée

### Changer le mot de passe MySQL
Si vous avez configuré un mot de passe pour MySQL, modifiez `config/database.php`:
```php
define('DB_PASS', 'votre_mot_de_passe');
```

### Permissions des dossiers
Assurez-vous que le serveur web a les permissions d'écriture pour:
- Le dossier `api/` (pour les logs d'erreur)
- Le dossier de stockage des photos (si vous implémentez l'upload)

## 🐛 Dépannage

### Erreur "Erreur de connexion à la base de données"
- Vérifiez que MySQL est démarré dans WAMP
- Vérifiez les identifiants dans `config/database.php`
- Vérifiez que la base de données `ecityzen_gabon` existe

### Erreur 404 sur les API
- Vérifiez que les fichiers sont dans le bon dossier
- Vérifiez l'URL dans le navigateur (doit être `http://localhost/Ecityzen/...`)
- Vérifiez que Apache est démarré

### Les données ne s'affichent pas
- Ouvrez la console du navigateur (F12) pour voir les erreurs
- Vérifiez que les tables sont créées dans la base de données
- Vérifiez les logs PHP dans WAMP

## 🔒 Sécurité

⚠️ **Important pour la production:**
- Changez tous les mots de passe par défaut
- Utilisez des mots de passe forts et uniques
- Activez HTTPS
- Configurez les permissions de fichiers correctement
- Implémentez une validation côté serveur plus stricte
- Ajoutez une protection CSRF
- Limitez les tentatives de connexion

## 📝 Notes

- Les mots de passe sont hashés avec bcrypt (démo: "password")
- Les sessions PHP sont utilisées pour l'authentification
- Les photos sont stockées en base64 pour la démo (en production, utilisez des fichiers)

## 🆘 Support

Pour toute question ou problème, vérifiez:
1. Les logs PHP dans WAMP
2. La console du navigateur (F12)
3. Les logs MySQL dans phpMyAdmin

---

**Version:** 1.0  
**Dernière mise à jour:** 2025

