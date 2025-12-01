# 🔧 Guide rapide : Corriger les contraintes de clé étrangère

## ⚠️ Problème actuel

Vous ne pouvez pas supprimer un utilisateur car il est référencé dans la table `signalements` (ou autres tables).

## ✅ Solution rapide (5 minutes)

### Étape 1 : Ouvrir l'éditeur SQL Supabase

1. Allez sur https://supabase.com/dashboard
2. Sélectionnez votre projet
3. Cliquez sur **SQL Editor** dans le menu de gauche
4. Cliquez sur **New Query**

### Étape 2 : Copier et exécuter le script

1. Ouvrez le fichier `fix_foreign_keys.sql` dans ce projet
2. **Copiez TOUT le contenu** du fichier
3. **Collez-le** dans l'éditeur SQL de Supabase
4. Cliquez sur **Run** (ou `Ctrl+Enter`)

### Étape 3 : Vérifier

Le script devrait s'exécuter sans erreur. À la fin, vous verrez un tableau avec toutes les contraintes modifiées.

## 🎯 Ce que fait le script

Le script modifie toutes les contraintes de clé étrangère pour utiliser `ON DELETE SET NULL` :

- ✅ Quand vous supprimez un utilisateur, les signalements/demandes liés sont **conservés**
- ✅ Les références (`utilisateur_id`, `agent_assigné_id`) deviennent `NULL`
- ✅ **L'historique est préservé**

## 📋 Script à exécuter

Voici le script complet (déjà dans `fix_foreign_keys.sql`) :

```sql
-- Supprimer les anciennes contraintes
ALTER TABLE signalements DROP CONSTRAINT IF EXISTS signalements_agent_assigné_id_fkey;
ALTER TABLE signalements DROP CONSTRAINT IF EXISTS signalements_utilisateur_id_fkey;
ALTER TABLE demandes DROP CONSTRAINT IF EXISTS demandes_agent_assigné_id_fkey;
ALTER TABLE demandes DROP CONSTRAINT IF EXISTS demandes_utilisateur_id_fkey;
ALTER TABLE missions DROP CONSTRAINT IF EXISTS missions_agent_id_fkey;
ALTER TABLE paiements DROP CONSTRAINT IF EXISTS paiements_utilisateur_id_fkey;
ALTER TABLE licences_commerciales DROP CONSTRAINT IF EXISTS licences_commerciales_utilisateur_id_fkey;
ALTER TABLE emplacements_marche DROP CONSTRAINT IF EXISTS emplacements_marche_utilisateur_id_fkey;

-- Recréer avec ON DELETE SET NULL
ALTER TABLE signalements
ADD CONSTRAINT signalements_agent_assigné_id_fkey 
FOREIGN KEY (agent_assigné_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;

ALTER TABLE signalements
ADD CONSTRAINT signalements_utilisateur_id_fkey 
FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;

ALTER TABLE demandes
ADD CONSTRAINT demandes_agent_assigné_id_fkey 
FOREIGN KEY (agent_assigné_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;

ALTER TABLE demandes
ADD CONSTRAINT demandes_utilisateur_id_fkey 
FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;

ALTER TABLE missions
ADD CONSTRAINT missions_agent_id_fkey 
FOREIGN KEY (agent_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;

ALTER TABLE paiements
ADD CONSTRAINT paiements_utilisateur_id_fkey 
FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;

ALTER TABLE licences_commerciales
ADD CONSTRAINT licences_commerciales_utilisateur_id_fkey 
FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;

ALTER TABLE emplacements_marche
ADD CONSTRAINT emplacements_marche_utilisateur_id_fkey 
FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;
```

## ✅ Après l'exécution

Vous pourrez maintenant :
- ✅ Supprimer n'importe quel utilisateur
- ✅ Les signalements/demandes liés seront conservés avec `utilisateur_id = NULL`
- ✅ L'historique sera préservé

## 🚨 Important

- Le script est **sûr** : il ne supprime aucune donnée
- Il modifie seulement les **contraintes** pour permettre la suppression
- Les données existantes sont **conservées**

## 📞 Si vous avez des erreurs

Si le script échoue, vérifiez :
1. Que vous êtes connecté au bon projet Supabase
2. Que vous avez les permissions d'administrateur
3. Que les noms de tables sont corrects (vérifiez dans Table Editor)

