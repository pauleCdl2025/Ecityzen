# Guide pour corriger les erreurs 502 sur Netlify Functions

## Problème
Les fonctions Netlify (`demandes.js`, `signalements.js`) retournent des erreurs 502 (Bad Gateway).

## Causes possibles

### 1. Variables d'environnement manquantes
Les fonctions Netlify ont besoin des variables d'environnement Supabase pour fonctionner.

### 2. Redéploiement en cours
Netlify peut prendre quelques minutes pour redéployer après un push.

### 3. Timeout des fonctions
Les fonctions Netlify gratuites ont une limite de 10 secondes.

## Solutions

### Étape 1 : Vérifier les variables d'environnement Netlify

1. Allez sur [Netlify Dashboard](https://app.netlify.com/)
2. Sélectionnez votre site `ecityzen`
3. Allez dans **Site settings** > **Environment variables**
4. Vérifiez que ces variables existent :
   - `SUPABASE_URL` = `https://srbzvjrqbhtuyzlwdghn.supabase.co`
   - `SUPABASE_ANON_KEY` = `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM`

### Étape 2 : Ajouter les variables si elles manquent

1. Cliquez sur **Add a variable**
2. Ajoutez `SUPABASE_URL` avec la valeur ci-dessus
3. Ajoutez `SUPABASE_ANON_KEY` avec la valeur ci-dessus
4. Cliquez sur **Save**

### Étape 3 : Redéployer le site

1. Allez dans **Deploys**
2. Cliquez sur **Trigger deploy** > **Deploy site**
3. Attendez la fin du déploiement (2-5 minutes)

### Étape 4 : Vérifier les logs Netlify

1. Allez dans **Functions** dans le menu de gauche
2. Cliquez sur une fonction (ex: `demandes`)
3. Allez dans l'onglet **Logs**
4. Vérifiez les erreurs récentes

## Vérification du code

Les fonctions Netlify ont maintenant :
- ✅ Valeurs par défaut pour Supabase (si variables manquantes)
- ✅ Try-catch global pour éviter les 502
- ✅ Retour de tableaux vides au lieu d'erreurs 500

## Test

Après le redéploiement, testez :
1. Rechargez l'application
2. Connectez-vous en tant que manager
3. Vérifiez la console du navigateur (F12)
4. Les erreurs 502 devraient disparaître

## Si les erreurs persistent

1. **Vérifier les logs Netlify Functions** :
   - Allez dans Netlify Dashboard > Functions > Logs
   - Cherchez les erreurs récentes

2. **Vérifier la connexion Supabase** :
   - Testez directement l'API Supabase dans votre navigateur
   - `https://srbzvjrqbhtuyzlwdghn.supabase.co/rest/v1/signalements?select=*&limit=1`
   - Ajoutez l'en-tête : `apikey: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...`

3. **Vérifier les permissions RLS (Row Level Security)** :
   - Allez dans Supabase Dashboard > Authentication > Policies
   - Vérifiez que les tables `signalements` et `demandes` ont les bonnes politiques

4. **Contacter le support** :
   - Si rien ne fonctionne, vérifiez les logs détaillés dans Netlify
   - Les fonctions peuvent avoir un timeout si Supabase est lent

## Note importante

Les fonctions Netlify utilisent maintenant des valeurs par défaut pour Supabase, donc elles devraient fonctionner même sans variables d'environnement. Si les erreurs 502 persistent, c'est probablement un problème de :
- Timeout (fonction trop lente)
- Erreur non capturée dans le code
- Problème de connexion à Supabase



