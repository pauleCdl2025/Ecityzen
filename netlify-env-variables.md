# Variables d'environnement Netlify - e-cityzen Gabon

## 🔐 Variables à configurer dans Netlify

### Accéder aux variables d'environnement

1. Allez sur https://app.netlify.com
2. Sélectionnez votre site
3. Allez dans **Site settings** > **Environment variables**
4. Cliquez sur **Add a variable**

## 📋 Liste des variables

### Variables Supabase (OBLIGATOIRES)

```
SUPABASE_URL=https://srbzvjrqbhtuyzlwdghn.supabase.co
SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM
```

### Variables API (si vous utilisez un proxy)

```
NETLIFY_API_BASE_URL=https://votre-serveur-api.com
```

### Variables optionnelles

```
NODE_ENV=production
API_TIMEOUT=30000
```

## 🔧 Configuration dans Netlify

### Via l'interface Netlify

1. **Site settings** > **Environment variables**
2. Cliquez sur **Add a variable**
3. Ajoutez chaque variable :
   - **Key** : `SUPABASE_URL`
   - **Value** : `https://srbzvjrqbhtuyzlwdghn.supabase.co`
   - **Scopes** : Sélectionnez "All scopes" ou "Production"

4. Répétez pour toutes les variables

### Via netlify.toml (Recommandé pour le développement)

```toml
[build.environment]
  SUPABASE_URL = "https://srbzvjrqbhtuyzlwdghn.supabase.co"
  SUPABASE_ANON_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

⚠️ **Note** : Les clés sensibles ne doivent PAS être dans `netlify.toml` qui est public. Utilisez les variables d'environnement Netlify.

## 📝 Utilisation dans le code

### Frontend (ECITYZEN.html)

Les variables Supabase sont déjà codées en dur dans le fichier. Pour utiliser les variables d'environnement Netlify dans le frontend, vous devez les injecter lors du build.

### Backend (APIs PHP)

Si vous utilisez Netlify Functions, vous pouvez accéder aux variables via :

```javascript
const supabaseUrl = process.env.SUPABASE_URL;
const supabaseKey = process.env.SUPABASE_ANON_KEY;
```

## 🔒 Sécurité

⚠️ **Important** :
- Ne commitez JAMAIS les clés secrètes dans le repository
- Utilisez les variables d'environnement Netlify pour les valeurs sensibles
- Les clés Supabase Anon sont publiques par design (protégées par RLS)
- Mais utilisez quand même les variables d'environnement pour la flexibilité

## 🎯 Variables nécessaires pour chaque environnement

### Production
- `SUPABASE_URL` ✅
- `SUPABASE_ANON_KEY` ✅
- `NETLIFY_API_BASE_URL` (si proxy) ✅

### Développement
- Même variables avec valeurs de dev si nécessaire

## 📌 Quick Setup

1. Copiez les variables ci-dessus
2. Allez dans Netlify > Site settings > Environment variables
3. Ajoutez chaque variable
4. Redéployez le site

