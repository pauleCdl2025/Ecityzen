# Instructions pour ajouter les tables manquantes

## Problème détecté

Votre base de données manque plusieurs tables nécessaires pour le fonctionnement complet de l'application :
- budget_municipal
- chantiers_travaux
- signalements_chefs_quartier
- reservations_marches
- stands_marche
- notifications
- preferences_notifications
- feedbacks
- messages_assistance
- faq

## Solution rapide

### Option 1 : Importer le script SQL (Recommandé)

1. Ouvrez **phpMyAdmin** : http://localhost/phpmyadmin
2. Sélectionnez la base de données **ecityzen_gabon**
3. Cliquez sur l'onglet **SQL**
4. Copiez-collez le contenu du fichier **add_missing_tables.sql**
5. Cliquez sur **Exécuter**

### Option 2 : Réimporter database.sql complet

Si vous préférez repartir de zéro :

1. **ATTENTION** : Cela va supprimer vos données existantes !
2. Dans phpMyAdmin, sélectionnez **ecityzen_gabon**
3. Allez dans l'onglet **Importer**
4. Sélectionnez le fichier **database.sql**
5. Cochez **"Remplacer les données existantes"**
6. Cliquez sur **Exécuter**

### Option 3 : Importer via la ligne de commande

```bash
cd C:\wamp64\www\Ecityzen
mysql -u root -p ecityzen_gabon < add_missing_tables.sql
```

## Après l'importation

1. **Vérifiez** que les tables sont créées :
   - Allez sur : http://localhost/Ecityzen/test_connections.php
   - Toutes les tables doivent maintenant être marquées ✓

2. **Importez les données de démonstration** (optionnel) :
   - Importez le fichier **database_demo_data.sql** dans phpMyAdmin
   - Cela ajoutera des données de test pour tous les modules

3. **Testez l'application** :
   - Accédez à : http://localhost/Ecityzen/ECITYZEN.html
   - Connectez-vous avec un compte de démo
   - Testez les fonctionnalités (Budget, Travaux Publics, etc.)

## Vérification

Après avoir exécuté `add_missing_tables.sql`, vous devriez voir dans le test de connexions :

```
✓ budget_municipal - Existe
✓ chantiers_travaux - Existe
✓ signalements_chefs_quartier - Existe
✓ reservations_marches - Existe
✓ stands_marche - Existe
✓ notifications - Existe
✓ preferences_notifications - Existe
✓ feedbacks - Existe
✓ messages_assistance - Existe
✓ faq - Existe
```

## Note importante

Le script `add_missing_tables.sql` utilise `CREATE TABLE IF NOT EXISTS`, donc :
- Il ne supprimera pas vos données existantes
- Il créera uniquement les tables qui manquent
- Vos utilisateurs et données actuelles seront préservés



