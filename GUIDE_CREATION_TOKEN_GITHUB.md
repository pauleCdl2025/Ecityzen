# 🔐 Guide Étape par Étape - Créer un Token d'Accès Personnel GitHub

## 📋 Prérequis
- Compte GitHub : `pauleCdl2025`
- Accès à votre compte GitHub

---

## 🚀 ÉTAPE 1 : Accéder à la page de création de token

### Option A : Lien direct (le plus rapide)
1. **Cliquez sur ce lien** : https://github.com/settings/tokens/new
2. Si vous n'êtes pas connecté, connectez-vous avec votre compte `pauleCdl2025`
3. Passez à l'ÉTAPE 2

### Option B : Navigation manuelle
1. Allez sur **https://github.com**
2. Cliquez sur votre **avatar** (en haut à droite)
3. Cliquez sur **Settings**
4. Dans le menu de gauche, allez tout en bas
5. Cliquez sur **Developer settings**
6. Cliquez sur **Personal access tokens**
7. Cliquez sur **Tokens (classic)**
8. Cliquez sur **Generate new token** > **Generate new token (classic)**

---

## ✏️ ÉTAPE 2 : Remplir le formulaire

### 2.1 Note (nom du token)
- **Champ** : "Note" ou "Token description"
- **Valeur** : `Ecityzen Git Push`
- **Pourquoi** : Pour identifier facilement ce token plus tard

### 2.2 Expiration
- **Options disponibles** :
  - `No expiration` (recommandé pour un usage personnel)
  - `90 days`
  - `60 days`
  - `30 days`
  - `7 days`
- **Recommandation** : Choisissez `No expiration` ou `90 days` selon vos préférences

### 2.3 Scopes (permissions)
Cochez **uniquement** ce dont vous avez besoin :

#### ✅ OBLIGATOIRE :
- **`repo`** - Full control of private repositories
  - ✅ repo
  - ✅ repo:status
  - ✅ repo_deployment
  - ✅ public_repo
  - ✅ repo:invite
  - ✅ security_events

#### ⚠️ Optionnel (selon vos besoins) :
- **`workflow`** - Update GitHub Action workflows (si vous utilisez GitHub Actions)
- **`write:packages`** - Upload packages (si vous publiez des packages)

#### ❌ NE PAS COCHER (pour la sécurité) :
- Tous les autres scopes sauf si vous en avez vraiment besoin

---

## 🔑 ÉTAPE 3 : Générer le token

1. **Faites défiler vers le bas** de la page
2. Cliquez sur le bouton vert **"Generate token"**
3. **⚠️ ATTENTION** : GitHub va vous montrer le token **UNE SEULE FOIS**
4. **COPIEZ LE TOKEN IMMÉDIATEMENT** avant de fermer la page

### Le token ressemble à ceci :
```
ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

## 💾 ÉTAPE 4 : Sauvegarder le token (IMPORTANT)

### Option A : Dans un gestionnaire de mots de passe
- Utilisez un gestionnaire de mots de passe (1Password, LastPass, Bitwarden, etc.)
- Créez une entrée :
  - **Titre** : "GitHub Token - Ecityzen"
  - **Token** : (collez le token)
  - **URL** : https://github.com/pauleCdl2025/Ecityzen

### Option B : Dans un fichier texte sécurisé
- Créez un fichier texte sur votre ordinateur
- Notez le token
- **⚠️ Ne partagez JAMAIS ce fichier**
- **⚠️ Ne le commitez JAMAIS dans Git**

---

## ⚙️ ÉTAPE 5 : Configurer Git avec le token

### 5.1 Ouvrir le terminal
- Ouvrez PowerShell ou l'invite de commande
- Naviguez vers votre projet :
  ```bash
  cd C:\wamp64\www\Ecityzen
  ```

### 5.2 Configurer l'URL du remote avec le token

**Remplacez `VOTRE_TOKEN` par le token que vous avez copié :**

```bash
git remote set-url origin https://pauleCdl2025:VOTRE_TOKEN@github.com/pauleCdl2025/Ecityzen.git
```

**Exemple** (ne copiez pas cet exemple, utilisez votre vrai token) :
```bash
git remote set-url origin https://pauleCdl2025:ghp_abc123xyz456@github.com/pauleCdl2025/Ecityzen.git
```

### 5.3 Vérifier la configuration

```bash
git remote -v
```

Vous devriez voir :
```
origin  https://pauleCdl2025:ghp_xxxxx@github.com/pauleCdl2025/Ecityzen.git (fetch)
origin  https://pauleCdl2025:ghp_xxxxx@github.com/pauleCdl2025/Ecityzen.git (push)
```

---

## 🚀 ÉTAPE 6 : Tester le push

```bash
git push origin main
```

Si tout fonctionne, vous devriez voir :
```
Enumerating objects: X, done.
Counting objects: 100% (X/X), done.
Writing objects: 100% (X/X), done.
To https://github.com/pauleCdl2025/Ecityzen.git
   xxxxx..xxxxx  main -> main
```

---

## ✅ Vérification finale

### Vérifier que le push a réussi
1. Allez sur : https://github.com/pauleCdl2025/Ecityzen
2. Vérifiez que vos derniers commits sont présents
3. Vérifiez la date du dernier commit

---

## 🔒 Sécurité

### ✅ Bonnes pratiques
- ✅ Ne partagez JAMAIS votre token
- ✅ Ne commitez JAMAIS le token dans le code
- ✅ Utilisez un gestionnaire de mots de passe
- ✅ Révoquez le token si vous pensez qu'il a été compromis

### ❌ À éviter
- ❌ Partager le token par email
- ❌ Le mettre dans un fichier commité
- ❌ Le partager sur des forums/publications
- ❌ Utiliser le même token pour plusieurs projets (créez-en un par projet)

---

## 🆘 Dépannage

### Erreur : "remote: Invalid username or password"
- Vérifiez que vous avez bien copié tout le token
- Vérifiez qu'il n'y a pas d'espaces avant/après le token
- Vérifiez que le token n'a pas expiré

### Erreur : "remote: Permission denied"
- Vérifiez que vous avez coché le scope `repo`
- Vérifiez que vous avez les droits sur le dépôt

### Le token ne fonctionne plus
- Le token a peut-être expiré
- Créez un nouveau token et reconfigurez Git

### Révoquer un token
1. Allez sur : https://github.com/settings/tokens
2. Trouvez votre token "Ecityzen Git Push"
3. Cliquez sur **Revoke**
4. Créez un nouveau token si nécessaire

---

## 📝 Résumé rapide

1. ✅ Créer le token : https://github.com/settings/tokens/new
2. ✅ Nom : "Ecityzen Git Push"
3. ✅ Scope : `repo`
4. ✅ Copier le token
5. ✅ Configurer Git : `git remote set-url origin https://pauleCdl2025:TOKEN@github.com/pauleCdl2025/Ecityzen.git`
6. ✅ Tester : `git push origin main`

---

**Besoin d'aide ?** Si vous rencontrez un problème à une étape, dites-moi à quelle étape vous êtes bloqué !

