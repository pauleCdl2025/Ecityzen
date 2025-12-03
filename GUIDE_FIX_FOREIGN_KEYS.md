# Guide : Correction des contraintes de clé étrangère Supabase

## Problème

Lorsque vous essayez de supprimer un utilisateur dans Supabase, vous obtenez l'erreur :
```
Unable to delete rows as one of them is currently referenced by a foreign key constraint
```

Cela se produit parce que les contraintes de clé étrangère n'ont pas de comportement `ON DELETE` défini.

## Solution

Le script `fix_foreign_keys.sql` modifie toutes les contraintes de clé étrangère pour utiliser `ON DELETE SET NULL`. Cela signifie que :

- **Pour `agent_assigné_id`** : Quand un agent est supprimé, les signalements/demandes assignés à cet agent auront `agent_assigné_id = NULL` (au lieu d'empêcher la suppression)
- **Pour `utilisateur_id`** : Quand un utilisateur est supprimé, ses signalements/demandes seront conservés avec `utilisateur_id = NULL` (pour garder l'historique)

## Étapes pour appliquer la correction

### 1. Accéder à l'éditeur SQL de Supabase

1. Connectez-vous à votre projet Supabase : https://supabase.com/dashboard
2. Sélectionnez votre projet
3. Allez dans **SQL Editor** dans le menu de gauche
4. Cliquez sur **New Query**

### 2. Exécuter le script

1. Ouvrez le fichier `fix_foreign_keys.sql` dans votre éditeur
2. Copiez tout le contenu du fichier
3. Collez-le dans l'éditeur SQL de Supabase
4. Cliquez sur **Run** (ou appuyez sur `Ctrl+Enter`)

### 3. Vérifier les résultats

Le script affichera à la fin toutes les contraintes de clé étrangère modifiées avec leur comportement `delete_rule`. Vous devriez voir `SET NULL` pour toutes les contraintes liées à `utilisateurs`.

## Comportements disponibles

### ON DELETE SET NULL (recommandé pour ce cas)
- Les enregistrements liés sont conservés
- Les références à l'utilisateur supprimé deviennent `NULL`
- **Avantage** : Conserve l'historique des données
- **Utilisé pour** : `agent_assigné_id`, `utilisateur_id` (pour garder l'historique)

### ON DELETE CASCADE
- Les enregistrements liés sont automatiquement supprimés
- **Avantage** : Nettoie automatiquement les données orphelines
- **Inconvénient** : Perte de l'historique
- **Utilisé pour** : Relations où on veut supprimer les données liées

### ON DELETE RESTRICT (par défaut)
- Empêche la suppression si des enregistrements liés existent
- **C'est ce qui cause votre erreur actuelle**

## Tables modifiées

Le script modifie les contraintes pour les tables suivantes :

1. **signalements**
   - `agent_assigné_id` → `ON DELETE SET NULL`
   - `utilisateur_id` → `ON DELETE SET NULL`

2. **demandes**
   - `agent_assigné_id` → `ON DELETE SET NULL`
   - `utilisateur_id` → `ON DELETE SET NULL`

3. **missions**
   - `agent_id` → `ON DELETE SET NULL`

4. **paiements**
   - `utilisateur_id` → `ON DELETE SET NULL`

5. **licences_commerciales**
   - `utilisateur_id` → `ON DELETE SET NULL`

6. **emplacements_marche**
   - `utilisateur_id` → `ON DELETE SET NULL`

## Après l'exécution

Une fois le script exécuté, vous pourrez :
- ✅ Supprimer des utilisateurs même s'ils sont assignés à des signalements/demandes
- ✅ Les signalements/demandes conserveront leur historique avec `agent_assigné_id = NULL`
- ✅ L'historique des données sera préservé

## Note importante

Si vous préférez supprimer automatiquement les signalements/demandes quand un utilisateur est supprimé (au lieu de les garder avec `NULL`), changez `ON DELETE SET NULL` par `ON DELETE CASCADE` dans le script.

## Vérification

Pour vérifier que les contraintes sont bien modifiées, exécutez cette requête dans Supabase :

```sql
SELECT
    tc.table_name, 
    kcu.column_name, 
    ccu.table_name AS foreign_table_name,
    rc.delete_rule
FROM 
    information_schema.table_constraints AS tc 
    JOIN information_schema.key_column_usage AS kcu
      ON tc.constraint_name = kcu.constraint_name
    JOIN information_schema.constraint_column_usage AS ccu
      ON ccu.constraint_name = tc.constraint_name
    JOIN information_schema.referential_constraints AS rc
      ON rc.constraint_name = tc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY' 
    AND tc.table_schema = 'public'
    AND ccu.table_name = 'utilisateurs';
```

Toutes les contraintes devraient avoir `delete_rule = 'SET NULL'`.



