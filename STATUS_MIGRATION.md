# Statut de la Migration vers Supabase

## ✅ Fait

1. **Configuration Supabase**
   - ✅ `config/supabase.php` créé avec fonction `supabaseCall()`
   - ✅ Fonction `enrichWithUserNames()` pour les JOINs
   - ✅ Support des options (order, limit, select)

2. **Schema SQL**
   - ✅ `supabase_schema.sql` créé avec toutes les tables
   - ✅ Script exécuté dans Supabase

3. **APIs Migrées**
   - ✅ `api/login.php` - Utilise Supabase
   - ✅ `api/register.php` - Utilise Supabase
   - ✅ `api/demandes.php` - Utilise Supabase
   - ✅ `api/signalements.php` - Utilise Supabase

4. **Frontend**
   - ✅ Client Supabase JS intégré dans `ECITYZEN.html`
   - ✅ Fonction `supabaseCall()` JavaScript disponible

## ⏳ À Faire

Les APIs suivantes doivent encore être migrées :

1. `api/users.php`
2. `api/notifications.php`
3. `api/paiements.php`
4. `api/budget.php`
5. `api/chantiers.php`
6. `api/missions.php`
7. `api/licences.php`
8. `api/emplacements.php`
9. `api/chefs_quartier.php`
10. `api/feedback.php`
11. `api/assistance.php`
12. `api/stats.php`
13. `api/preferences_notifications.php`

## 📝 Notes

- Les APIs migrées utilisent maintenant `supabaseCall()` au lieu de PDO
- La fonction `enrichWithUserNames()` remplace les JOINs SQL
- Les fichiers uploadés sont toujours gérés localement dans `uploads/`
- Les sessions PHP sont toujours utilisées pour l'authentification

## 🚀 Test

Pour tester :
1. Se connecter avec `api/login.php`
2. Créer une demande avec `api/demandes.php`
3. Créer un signalement avec `api/signalements.php`
4. Vérifier que les données apparaissent dans Supabase

