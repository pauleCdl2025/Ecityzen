# 🚀 Configuration des Variables Netlify - Guide Rapide

## 📍 Où configurer les variables

1. **Allez sur** : https://app.netlify.com
2. **Sélectionnez votre site** e-cityzen
3. **Cliquez sur** : **Site settings** (⚙️)
4. **Dans le menu de gauche**, cliquez sur **Environment variables**
5. **Cliquez sur** : **Add a variable**

## ✅ Variables à ajouter

### 1. SUPABASE_URL
- **Key** : `SUPABASE_URL`
- **Value** : `https://srbzvjrqbhtuyzlwdghn.supabase.co`
- **Scopes** : ✅ All scopes (Production, Deploy previews, Branch deploys)

### 2. SUPABASE_ANON_KEY
- **Key** : `SUPABASE_ANON_KEY`
- **Value** : `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM`
- **Scopes** : ✅ All scopes

### 3. NETLIFY_API_BASE_URL (Optionnel - si vous utilisez un proxy)
- **Key** : `NETLIFY_API_BASE_URL`
- **Value** : `https://votre-serveur-api.com` (remplacez par votre URL réelle)
- **Scopes** : ✅ All scopes

## 🎯 Exemple visuel

```
┌─────────────────────────────────────┐
│ Environment variables                │
├─────────────────────────────────────┤
│ Key              │ Value            │
├──────────────────┼──────────────────┤
│ SUPABASE_URL     │ https://srb...   │
│ SUPABASE_ANON_KEY│ eyJhbGc...       │
│ NETLIFY_API...   │ https://...      │
└─────────────────────────────────────┘
```

## 📝 Instructions détaillées

### Étape par étape :

1. **Connectez-vous à Netlify**
   - Allez sur https://app.netlify.com
   - Connectez-vous avec votre compte

2. **Sélectionnez votre site**
   - Si pas encore créé : Importez depuis GitHub
   - Si déjà créé : Cliquez sur le nom du site

3. **Accédez aux paramètres**
   - Cliquez sur **Site settings** (icône ⚙️ en haut)
   - Dans le menu latéral, cherchez **Build & deploy**
   - Cliquez sur **Environment variables**

4. **Ajoutez les variables**
   - Cliquez sur **Add a variable**
   - Saisissez la **Key** : `SUPABASE_URL`
   - Saisissez la **Value** : `https://srbzvjrqbhtuyzlwdghn.supabase.co`
   - Sélectionnez les **Scopes** : Cochez toutes les cases
   - Cliquez sur **Save**

5. **Répétez pour SUPABASE_ANON_KEY**
   - Cliquez sur **Add a variable**
   - **Key** : `SUPABASE_ANON_KEY`
   - **Value** : `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM`
   - **Scopes** : Toutes les cases
   - **Save**

6. **Redéployez votre site**
   - Allez dans **Deploys**
   - Cliquez sur **Trigger deploy** > **Deploy site**
   - Ou faites un nouveau commit/push sur GitHub

## 🔍 Vérification

Pour vérifier que les variables sont bien configurées :

1. Allez dans **Site settings** > **Environment variables**
2. Vous devriez voir :
   - ✅ `SUPABASE_URL`
   - ✅ `SUPABASE_ANON_KEY`
   - ✅ `NETLIFY_API_BASE_URL` (si ajouté)

## ⚠️ Notes importantes

- Les variables sont disponibles après un **redéploiement**
- Utilisez **All scopes** pour que les variables soient disponibles partout
- Les variables sont **sécurisées** et ne sont pas visibles dans le code source
- Pour le frontend, vous devrez peut-être injecter les variables via un build step

## 🆘 Besoin d'aide ?

Si vous avez des problèmes :
1. Vérifiez que les variables sont bien sauvegardées
2. Redéployez le site
3. Vérifiez les logs de déploiement dans Netlify

