# 🚀 Guide d'hébergement des APIs PHP

## ⚠️ Problème

**Netlify ne peut pas exécuter du PHP.** Netlify est un service d'hébergement statique qui ne supporte que les fichiers HTML, CSS, JavaScript et les Netlify Functions (Node.js).

Votre application e-cityzen utilise des APIs PHP pour:
- Gestion des signalements
- Gestion des demandes administratives
- Notifications
- Chantiers publics
- Géocodage
- Et bien d'autres...

## ✅ Solutions disponibles

### Solution 1 : Héberger les APIs PHP sur un serveur séparé (Recommandé)

Vous devez héberger vos APIs PHP sur un serveur qui supporte PHP, puis configurer l'URL dans `ECITYZEN.html`.

#### Option A : Utiliser votre serveur WAMP existant (si accessible publiquement)

1. **Configurer WAMP pour être accessible depuis Internet** (nécessite configuration réseau/port forwarding)
2. **Modifier `ECITYZEN.html` ligne 3037-3039** :
   ```javascript
   const API_BASE_URL = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
       ? 'api'  // Local
       : 'https://votre-serveur-wamp.com/api';  // Production
   ```
3. **Configurer CORS sur votre serveur PHP** (ajouter dans `config/supabase.php` ou un fichier `.htaccess`) :
   ```php
   header('Access-Control-Allow-Origin: https://votre-site.netlify.app');
   header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
   header('Access-Control-Allow-Headers: Content-Type, Authorization');
   header('Access-Control-Allow-Credentials: true');
   ```

#### Option B : Héberger sur un service cloud gratuit/payant

**Services recommandés :**

1. **Railway** (https://railway.app)
   - Gratuit au départ, puis payant selon usage
   - Support PHP natif
   - Déploiement facile depuis GitHub

2. **Render** (https://render.com)
   - Plan gratuit disponible
   - Support PHP
   - Déploiement automatique

3. **Heroku** (https://www.heroku.com)
   - Payant (plus de plan gratuit)
   - Excellent support PHP

4. **Hostinger / OVH / etc.** (hébergement web traditionnel)
   - Plans PHP bon marché
   - Support complet PHP/MySQL

#### Étapes pour héberger sur Railway (exemple)

1. **Créer un compte Railway**
2. **Créer un nouveau projet**
3. **Connecter votre repository GitHub**
4. **Configurer le service** :
   - Type : Web Service
   - Build Command : (laisser vide ou `echo "No build"`
   - Start Command : `php -S 0.0.0.0:$PORT`
   - Root Directory : `/`
5. **Ajouter les variables d'environnement** :
   - `SUPABASE_URL`
   - `SUPABASE_ANON_KEY`
6. **Déployer**
7. **Obtenir l'URL** (ex: `https://votre-app.railway.app`)
8. **Mettre à jour `ECITYZEN.html`** :
   ```javascript
   const API_BASE_URL = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
       ? 'api'
       : 'https://votre-app.railway.app/api';
   ```

### Solution 2 : Créer toutes les Netlify Functions (Complexe)

Vous pourriez créer des Netlify Functions pour tous vos endpoints PHP, mais cela nécessiterait:
- Convertir toute la logique PHP en JavaScript/Node.js
- Recréer toutes les fonctions (`supabaseCall`, `enrichWithUserNames`, etc.)
- Gérer les sessions différemment (cookies vs JWT)
- Beaucoup de travail de migration

### Solution 3 : Utiliser Supabase directement depuis le frontend (Recommandé pour l'avenir)

Vous pourriez réécrire l'application pour utiliser Supabase directement depuis le frontend JavaScript, mais cela nécessiterait:
- Une refonte importante du code frontend
- Gérer l'authentification avec Supabase Auth (au lieu de sessions PHP)
- Recréer toute la logique métier côté client
- Beaucoup de temps de développement

## 📝 Configuration CORS (Important !)

Une fois vos APIs PHP hébergées, vous DEVEZ configurer CORS pour autoriser les requêtes depuis Netlify.

### Option 1 : Dans chaque fichier API PHP

Ajoutez en haut de chaque fichier `api/*.php` :
```php
header('Access-Control-Allow-Origin: https://votre-site.netlify.app');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
```

### Option 2 : Dans un fichier `.htaccess` (si Apache)

Créez un fichier `.htaccess` dans le dossier `api/` :
```apache
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "https://votre-site.netlify.app"
    Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type, Authorization"
    Header set Access-Control-Allow-Credentials "true"
</IfModule>
```

## 🔧 Configuration actuelle

Actuellement, l'application est configurée ainsi :

- **Local (localhost)** : Utilise les APIs PHP locales (`api/*.php`)
- **Production (Netlify)** : 
  - ✅ `login` et `register` fonctionnent via Netlify Functions
  - ❌ Tous les autres endpoints nécessitent un serveur PHP séparé

## 🎯 Prochaines étapes

1. **Choisissez une solution** parmi les options ci-dessus
2. **Hébergez vos APIs PHP** sur un serveur accessible publiquement
3. **Configurez CORS** pour autoriser les requêtes depuis Netlify
4. **Mettez à jour `API_BASE_URL`** dans `ECITYZEN.html`
5. **Testez** que tout fonctionne correctement

## 💡 Recommandation

Pour un déploiement rapide, je recommande **Railway** ou **Render** :
- Facile à configurer
- Gratuit au départ
- Support PHP natif
- Déploiement automatique depuis GitHub

Une fois hébergé, mettez simplement à jour l'URL dans `ECITYZEN.html` et votre application sera complètement fonctionnelle !

