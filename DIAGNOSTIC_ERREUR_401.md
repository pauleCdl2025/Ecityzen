# 🔍 Diagnostic Erreur 401 - Fonction Netlify Login

## Problème
```
POST https://ecityzen.netlify.app/.netlify/functions/login 401 (Unauthorized)
```

## Causes possibles

### 1. Variables d'environnement non configurées ⚠️ (Le plus probable)

Les variables d'environnement Supabase ne sont pas configurées dans Netlify.

**Solution :**
1. Allez sur https://app.netlify.com
2. Sélectionnez votre site `ecityzen`
3. **Site settings** > **Environment variables**
4. Ajoutez :
   - `SUPABASE_URL` = `https://srbzvjrqbhtuyzlwdghn.supabase.co`
   - `SUPABASE_ANON_KEY` = `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM`
5. **Redéployez** le site (Deploys > Trigger deploy)

### 2. Identifiants incorrects

Le numéro de téléphone ou le mot de passe est incorrect.

**Solution :**
- Vérifiez que vous utilisez le bon numéro de téléphone
- Vérifiez que le mot de passe est correct
- Utilisateurs de démo :
  - Citoyen: `+241 06 12 34 56` / `password`
  - Agent: `+241 05 34 56 78` / `password`
  - Manager: `+241 04 45 67 89` / `password`

### 3. Utilisateur non actif

Le compte utilisateur n'est pas actif dans la base de données.

**Solution :**
- Vérifiez dans Supabase que `statut = 'actif'` pour votre utilisateur
- Table `utilisateurs` > Vérifiez le champ `statut`

### 4. Problème avec bcryptjs

Le package `bcryptjs` n'est pas installé correctement.

**Solution :**
- Vérifiez que `package.json` contient `bcryptjs`
- Netlify devrait installer automatiquement, mais vérifiez les logs de déploiement

## Corrections apportées

### Améliorations dans `netlify/functions/login.js` :

1. ✅ **Valeurs par défaut** : Si les variables d'environnement ne sont pas définies, utilisation de valeurs par défaut
2. ✅ **Meilleure gestion d'erreurs** : Logs détaillés pour le diagnostic
3. ✅ **Validation améliorée** : Vérification du format JSON et des données
4. ✅ **Support mot de passe non hashé** : Fallback pour développement (avec warning)

## Test rapide

### 1. Vérifier les variables d'environnement

Dans Netlify :
- Site settings > Environment variables
- Vérifiez que `SUPABASE_URL` et `SUPABASE_ANON_KEY` sont présents

### 2. Vérifier les logs Netlify

1. Allez dans **Site settings** > **Functions**
2. Cliquez sur **login** dans la liste
3. Regardez les **Logs** pour voir les erreurs détaillées

### 3. Tester avec curl

```bash
curl -X POST https://ecityzen.netlify.app/.netlify/functions/login \
  -H "Content-Type: application/json" \
  -d '{"telephone":"+241 06 12 34 56","mot_de_passe":"password"}'
```

## Solution temporaire

Si les variables d'environnement ne sont pas configurées, le code utilise maintenant des valeurs par défaut. Cependant, **il est recommandé de configurer les variables d'environnement** pour la sécurité.

## Prochaines étapes

1. ✅ Configurer les variables d'environnement dans Netlify
2. ✅ Redéployer le site
3. ✅ Tester la connexion
4. ✅ Vérifier les logs si l'erreur persiste

## Logs à vérifier

Dans Netlify Functions logs, cherchez :
- `Configuration Supabase manquante` → Variables d'environnement non configurées
- `Erreur Supabase fetch` → Problème de connexion à Supabase
- `Erreur bcrypt` → Problème avec la vérification du mot de passe
- `Erreur login` → Erreur générale (voir le stack trace)

