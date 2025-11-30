# 🔐 Guide d'Authentification Git avec Token d'Accès Personnel

## Problème
GitHub demande une vérification par email mais vous n'arrivez pas à vérifier.

## Solution : Utiliser un Token d'Accès Personnel (PAT)

Un token d'accès personnel est plus fiable et ne nécessite pas de vérification par email.

### Étape 1 : Créer un Token d'Accès Personnel

1. **Allez sur GitHub** : https://github.com
2. **Connectez-vous** avec votre compte `pauleCdl2025`
3. **Cliquez sur votre avatar** (en haut à droite)
4. **Settings** > **Developer settings** (en bas du menu de gauche)
5. **Personal access tokens** > **Tokens (classic)**
6. **Generate new token** > **Generate new token (classic)**
7. **Note** : Donnez un nom au token (ex: "Ecityzen Git Push")
8. **Expiration** : Choisissez une durée (90 jours, 1 an, ou no expiration)
9. **Scopes** : Cochez au minimum :
   - ✅ `repo` (Full control of private repositories)
   - ✅ `workflow` (si vous utilisez GitHub Actions)
10. **Generate token**
11. **⚠️ IMPORTANT** : Copiez le token immédiatement ! Il ne sera affiché qu'une seule fois.

### Étape 2 : Utiliser le Token pour Push

#### Option A : Utiliser le token dans l'URL (recommandé)

```bash
git remote set-url origin https://pauleCdl2025:VOTRE_TOKEN@github.com/pauleCdl2025/Ecityzen.git
```

Remplacez `VOTRE_TOKEN` par le token que vous avez copié.

#### Option B : Utiliser le token comme mot de passe

1. Quand Git vous demande le mot de passe, utilisez le **token** au lieu du mot de passe
2. Le nom d'utilisateur reste `pauleCdl2025`

### Étape 3 : Tester le Push

```bash
git push origin main
```

## Alternative : Utiliser SSH (plus sécurisé à long terme)

### Étape 1 : Générer une clé SSH

```bash
ssh-keygen -t ed25519 -C "pauleCdl2025@github.com"
```

Appuyez sur Entrée pour accepter l'emplacement par défaut.

### Étape 2 : Ajouter la clé SSH à GitHub

1. Copiez le contenu de la clé publique :
   ```bash
   cat ~/.ssh/id_ed25519.pub
   ```
   (Sur Windows : `type C:\Users\VotreNom\.ssh\id_ed25519.pub`)

2. Sur GitHub :
   - **Settings** > **SSH and GPG keys**
   - **New SSH key**
   - **Title** : "Ecityzen Development"
   - **Key** : Collez le contenu de la clé publique
   - **Add SSH key**

### Étape 3 : Changer l'URL du remote en SSH

```bash
git remote set-url origin git@github.com:pauleCdl2025/Ecityzen.git
```

### Étape 4 : Tester

```bash
git push origin main
```

## Vérification de la Configuration

### Vérifier l'URL du remote
```bash
git remote -v
```

### Vérifier l'utilisateur Git
```bash
git config user.name
git config user.email
```

## Dépannage

### Si le token ne fonctionne pas

1. Vérifiez que le token n'a pas expiré
2. Vérifiez que le scope `repo` est bien coché
3. Régénérez un nouveau token si nécessaire

### Si SSH ne fonctionne pas

1. Testez la connexion SSH :
   ```bash
   ssh -T git@github.com
   ```
   Vous devriez voir : `Hi pauleCdl2025! You've successfully authenticated...`

2. Vérifiez que la clé SSH est bien ajoutée à GitHub

### Effacer les credentials Windows

Si vous avez des problèmes avec les credentials stockées :

1. Ouvrez **Credential Manager** (Gestionnaire d'identification)
2. **Windows Credentials**
3. Cherchez `git:https://github.com`
4. Supprimez l'entrée
5. Réessayez le push

## Recommandation

Pour un usage à long terme, **utilisez SSH** car :
- ✅ Plus sécurisé
- ✅ Pas besoin de renouveler le token
- ✅ Pas de vérification par email nécessaire
- ✅ Plus rapide

Pour un usage immédiat, **utilisez un PAT** car :
- ✅ Plus rapide à configurer
- ✅ Fonctionne immédiatement
- ✅ Pas besoin de clés SSH

