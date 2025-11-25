# Guide de Déploiement sur Netlify - e-cityzen Gabon

## 📋 Vue d'ensemble

Votre application utilise :
- **Frontend** : HTML/CSS/JavaScript (statique) ✅ Compatible Netlify
- **Backend** : APIs PHP ❌ Non compatible Netlify (PHP non supporté)
- **Base de données** : Supabase ✅ Déjà hébergée

## 🎯 Solution Recommandée

### Option A : Frontend Netlify + APIs sur autre serveur

1. **Déployer le frontend sur Netlify** (fichiers statiques)
2. **Héberger les APIs PHP** sur un service qui supporte PHP :
   - Votre serveur WAMP actuel (pour développement)
   - Heroku (gratuit avec limitations)
   - Railway.app
   - Render.com
   - Votre propre serveur VPS

### Option B : Tout sur Netlify avec Edge Functions

Convertir les APIs PHP en Netlify Edge Functions (Deno) - plus complexe mais tout centralisé.

## 🚀 Déploiement sur Netlify (Option A)

### Étape 1 : Préparer le repository

✅ Déjà fait - tous les fichiers sont sur GitHub

### Étape 2 : Connecter à Netlify

1. **Allez sur** https://app.netlify.com
2. **Connectez-vous** avec votre compte GitHub
3. **Cliquez** sur "Add new site" > "Import an existing project"
4. **Sélectionnez** le repository `pauleCdl2025/Ecityzen`

### Étape 3 : Configurer le build

Dans les paramètres de déploiement :

```
Build command: (laisser vide ou mettre: echo "No build required")
Publish directory: .
Branch to deploy: main
```

### Étape 4 : Variables d'environnement (Optionnel)

Dans **Site settings** > **Environment variables**, ajoutez :

```
NETLIFY_API_BASE_URL=https://votre-serveur-api.com
```

### Étape 5 : Modifier les URLs d'API

Avant de déployer, modifiez `ECITYZEN.html` ligne ~3034 :

```javascript
// Ancien
const API_BASE_URL = 'api';

// Nouveau (pointer vers votre serveur d'APIs)
const API_BASE_URL = 'https://votre-serveur-api.com/api';
// Ou utiliser la variable d'environnement si vous utilisez le proxy
const API_BASE_URL = window.location.hostname === 'localhost' 
  ? 'api' 
  : '/.netlify/functions/api-proxy';
```

### Étape 6 : Déployer

1. **Cliquez** sur "Deploy site"
2. Netlify va cloner votre repository et déployer
3. Votre site sera disponible à : `https://votre-site.netlify.app`

## 🔧 Hébergement des APIs PHP

### Option 1 : Votre serveur actuel (WAMP)

- Gardez les APIs sur `http://localhost/Ecityzen/api/`
- Pour la production, utilisez un domaine public
- Configurez CORS pour autoriser Netlify

### Option 2 : Heroku

1. Créer un `composer.json` :
```json
{}
```

2. Créer un `Procfile` :
```
web: vendor/bin/heroku-php-apache2
```

3. Déployer sur Heroku

### Option 3 : Railway.app ou Render.com

- Uploader votre dossier `api/`
- Configurer le runtime PHP
- Obtenir l'URL de déploiement

## 🔐 Configuration CORS

Dans vos APIs PHP, ajoutez dans `config/supabase.php` :

```php
header('Access-Control-Allow-Origin: https://votre-site.netlify.app');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
```

## 📝 Fichiers de configuration créés

- ✅ `netlify.toml` - Configuration Netlify
- ✅ `_redirects` - Règles de redirection
- ✅ `package.json` - Configuration Node.js (pour Netlify Functions si nécessaire)
- ✅ `netlify/functions/api-proxy.js` - Proxy pour les APIs (optionnel)

## ✅ Checklist de déploiement

- [ ] Repository connecté à Netlify
- [ ] Build configuré (publish directory: `.`)
- [ ] Variables d'environnement ajoutées (si nécessaire)
- [ ] URLs d'API modifiées dans `ECITYZEN.html`
- [ ] APIs PHP hébergées sur un serveur accessible
- [ ] CORS configuré sur le serveur d'APIs
- [ ] Site déployé et testé

## 🆘 Problèmes courants

### Les APIs ne fonctionnent pas

- Vérifiez que votre serveur d'APIs est accessible publiquement
- Vérifiez les CORS headers
- Vérifiez les URLs dans le frontend

### Erreur 404 sur les routes

- Vérifiez le fichier `_redirects`
- Vérifiez `netlify.toml`

## 💡 Astuce

Pour tester localement avec Netlify :

```bash
npm install -g netlify-cli
netlify dev
```

Cela lance un serveur local avec la même configuration que Netlify.

