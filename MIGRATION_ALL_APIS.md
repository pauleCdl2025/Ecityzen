# Migration Complète de Toutes les APIs vers Supabase

## ✅ APIs Déjà Migrées

1. ✅ api/login.php
2. ✅ api/register.php  
3. ✅ api/demandes.php
4. ✅ api/signalements.php
5. ✅ api/users.php
6. ✅ api/notifications.php
7. ✅ api/paiements.php
8. ✅ api/budget.php

## ⏳ APIs à Migrer

Les APIs suivantes doivent être adaptées pour utiliser Supabase au lieu de MySQL :

- api/chantiers.php
- api/missions.php
- api/licences.php
- api/emplacements.php
- api/chefs_quartier.php
- api/feedback.php
- api/assistance.php
- api/stats.php
- api/preferences_notifications.php
- api/logout.php (simple, déjà fait)
- api/geocode.php (pas de DB, OK)
- api/mobile_money.php (peut rester tel quel)
- api/mobile_money_callback.php (peut rester tel quel)

## Changements Principaux

1. Remplacer `require_once '../config/database.php';` par `require_once '../config/supabase.php';`
2. Remplacer `$pdo = getDBConnection();` par des appels à `supabaseCall()`
3. Adapter les requêtes SQL pour utiliser l'API REST Supabase
4. Utiliser `enrichWithUserNames()` pour les JOINs

