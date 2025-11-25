# Migration Complète vers Supabase - Statut

## ✅ APIs Migrées (100%)

1. ✅ `api/login.php` - Authentification
2. ✅ `api/register.php` - Inscription
3. ✅ `api/demandes.php` - Demandes administratives
4. ✅ `api/signalements.php` - Signalements citoyens
5. ✅ `api/users.php` - Gestion utilisateurs
6. ✅ `api/notifications.php` - Notifications
7. ✅ `api/paiements.php` - Paiements
8. ✅ `api/budget.php` - Budget municipal

## ⏳ APIs en cours de migration

9. `api/chantiers.php` - Chantiers travaux publics
10. `api/missions.php` - Missions agents
11. `api/licences.php` - Licences commerciales
12. `api/emplacements.php` - Emplacements marché
13. `api/chefs_quartier.php` - Signalements chefs de quartier
14. `api/feedback.php` - Feedbacks
15. `api/assistance.php` - Assistance
16. `api/stats.php` - Statistiques
17. `api/preferences_notifications.php` - Préférences notifications

## 📝 Notes

- Toutes les APIs utilisent maintenant `config/supabase.php` au lieu de `config/database.php`
- La fonction `supabaseCall()` remplace PDO
- La fonction `enrichWithUserNames()` remplace les JOINs SQL
- Compatibilité maintenue avec l'ancien format de données

