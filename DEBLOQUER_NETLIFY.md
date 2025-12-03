# Guide rapide pour débloquer les fonctions Netlify

## Problème : Erreurs 502 (Bad Gateway) - Fonctions bloquées

## Solution rapide

### 1. Vérifier le redéploiement Netlify
1. Allez sur https://app.netlify.com/
2. Sélectionnez votre site `ecityzen`
3. Allez dans **Deploys**
4. Vérifiez que le dernier déploiement est **Published** (vert)
5. Si ce n'est pas le cas, attendez 2-5 minutes

### 2. Forcer un redéploiement
1. Dans **Deploys**, cliquez sur **Trigger deploy** > **Deploy site**
2. Attendez la fin du déploiement

### 3. Vérifier les variables d'environnement
1. **Site settings** > **Environment variables**
2. Vérifiez que ces variables existent :
   - `SUPABASE_URL` = `https://srbzvjrqbhtuyzlwdghn.supabase.co`
   - `SUPABASE_ANON_KEY` = `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM`
3. Si elles manquent, ajoutez-les et redéployez

### 4. Vérifier les logs Netlify Functions
1. **Functions** dans le menu de gauche
2. Cliquez sur `demandes` ou `signalements`
3. Allez dans l'onglet **Logs**
4. Vérifiez les erreurs récentes

## Modifications récentes

Les fonctions Netlify ont maintenant :
- ✅ **Timeout de 8 secondes** pour éviter les blocages
- ✅ **Valeurs par défaut** pour Supabase (fonctionne même sans variables)
- ✅ **Try-catch global** pour capturer toutes les erreurs
- ✅ **Retour immédiat** en cas de timeout ou d'erreur

## Si ça ne fonctionne toujours pas

### Option 1 : Utiliser les APIs PHP directement
Si les fonctions Netlify ne fonctionnent pas, vous pouvez utiliser les APIs PHP directement :
- Changez l'URL dans `ECITYZEN.html` pour pointer vers vos APIs PHP
- Les APIs PHP sont dans le dossier `api/`

### Option 2 : Vérifier Supabase
1. Testez directement l'API Supabase :
   ```
   https://srbzvjrqbhtuyzlwdghn.supabase.co/rest/v1/signalements?select=*&limit=1
   ```
2. Ajoutez l'en-tête : `apikey: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...`
3. Si ça ne fonctionne pas, il y a un problème avec Supabase

### Option 3 : Vérifier les permissions RLS
1. Allez dans Supabase Dashboard
2. **Authentication** > **Policies**
3. Vérifiez que les tables `signalements` et `demandes` ont les bonnes politiques

## Test rapide

Après le redéploiement :
1. Rechargez l'application (Ctrl+F5)
2. Connectez-vous en tant que manager
3. Ouvrez la console (F12)
4. Les erreurs 502 devraient disparaître
5. Les signalements devraient se charger (même si vides)

## Contact support

Si rien ne fonctionne :
1. Vérifiez les logs Netlify Functions (détaillés)
2. Vérifiez les logs Supabase
3. Vérifiez que votre compte Netlify n'est pas en limite



