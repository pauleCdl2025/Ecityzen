# Déploiement sur Netlify - e-cityzen Gabon

## ⚠️ Important : Limitation Netlify

**Netlify ne supporte pas PHP natif**. Il y a deux options pour déployer votre application :

## Option 1 : Frontend seulement sur Netlify (Recommandé)

Déployer uniquement le frontend HTML sur Netlify et héberger les APIs PHP ailleurs.

### Avantages
- ✅ Déploiement rapide du frontend
- ✅ CDN global pour le frontend
- ✅ Les APIs PHP restent sur votre serveur actuel (WAMP)

### Étapes

1. **Déployer le frontend sur Netlify** :
   - Connecter le repository GitHub à Netlify
   - Configurer le build :
     - Build command: (laisser vide)
     - Publish directory: `.` (racine)
   - Netlify détectera automatiquement `netlify.toml`

2. **Modifier les URLs d'API dans le frontend** :
   - Mettre à jour `ECITYZEN.html` pour pointer vers votre serveur d'APIs
   - Ou utiliser un proxy Netlify

## Option 2 : Convertir les APIs en Netlify Functions

Convertir toutes les APIs PHP en fonctions serverless Node.js.

### Avantages
- ✅ Tout hébergé sur Netlify
- ✅ Scalabilité automatique

### Inconvénients
- ❌ Nécessite de réécrire toutes les APIs en Node.js
- ❌ Plus complexe

## Option 3 : Utiliser Vercel (Alternative)

Vercel supporte mieux les applications full-stack.

## 🚀 Déploiement Rapide (Option 1)

1. **Allez sur** : https://app.netlify.com
2. **Cliquez sur** "Add new site" > "Import an existing project"
3. **Connectez GitHub** et sélectionnez `pauleCdl2025/Ecityzen`
4. **Configuration** :
   - Build command: (laisser vide)
   - Publish directory: `.`
   - Branch to deploy: `main`
5. **Variables d'environnement** (si nécessaire) :
   - Ajoutez vos variables dans Netlify > Site settings > Environment variables

## 📝 Modifications nécessaires

Pour que les APIs fonctionnent, vous devez :

1. **Héberger les APIs PHP** sur un serveur qui supporte PHP (ex: votre WAMP actuel, Heroku, Railway, etc.)

2. **Modifier `ECITYZEN.html`** pour pointer vers l'URL de vos APIs :
   ```javascript
   const API_BASE_URL = 'https://votre-serveur-api.com/api';
   ```

3. **Configurer CORS** sur votre serveur d'APIs pour autoriser Netlify

## 🔧 Configuration actuelle

Le fichier `netlify.toml` est déjà configuré pour :
- Déployer les fichiers statiques
- Rediriger les appels API (à adapter selon votre setup)
- Sécurité (headers)

## 💡 Recommandation

Pour l'instant, déployez le frontend sur Netlify et gardez les APIs PHP sur votre serveur actuel ou migrez-les vers Supabase Edge Functions.

