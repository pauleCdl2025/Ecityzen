# Guide de Migration vers Supabase - e-cityzen Gabon

## 📋 Configuration Supabase

- **URL**: https://srbzvjrqbhtuyzlwdghn.supabase.co
- **Anon Key**: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM

## 🚀 Étapes de Migration

### 1. Créer les Tables dans Supabase

1. Connectez-vous à votre dashboard Supabase : https://supabase.com/dashboard
2. Sélectionnez votre projet
3. Allez dans **SQL Editor**
4. Exécutez le script `supabase_schema.sql` pour créer toutes les tables

### 2. Configuration

Les fichiers suivants ont été créés/modifiés :

- ✅ `config/supabase.php` - Configuration PHP pour Supabase
- ✅ `ECITYZEN.html` - Client JavaScript Supabase intégré
- ✅ `api/login.php` - Adapté pour utiliser Supabase

### 3. Migration des APIs

Toutes les APIs doivent être adaptées. Exemple avec `api/login.php` :

**Avant (MySQL)** :
```php
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE telephone = ?");
$stmt->execute([$telephone]);
$user = $stmt->fetch();
```

**Après (Supabase)** :
```php
$result = supabaseCall('utilisateurs', 'GET', null, ['telephone' => $telephone]);
$user = $result['data'][0];
```

## 📝 Utilisation

### Côté Frontend (JavaScript)

Le client Supabase est déjà intégré. Vous pouvez utiliser :

```javascript
// Méthode 1 : Via l'API PHP (recommandé)
const result = await apiCall('login.php', 'POST', { telephone, mot_de_passe });

// Méthode 2 : Directement avec Supabase JS (si besoin)
const { data, error } = await supabaseClient
    .from('utilisateurs')
    .select('*')
    .eq('telephone', telephone)
    .single();
```

### Côté Backend (PHP)

Utilisez la fonction `supabaseCall()` :

```php
// SELECT
$result = supabaseCall('utilisateurs', 'GET', null, ['telephone' => $telephone]);

// INSERT
$result = supabaseCall('utilisateurs', 'POST', [
    'nom' => 'John Doe',
    'telephone' => '074027173',
    'mot_de_passe' => password_hash('password', PASSWORD_DEFAULT),
    'role' => 'citoyen'
]);

// UPDATE
$result = supabaseCall('utilisateurs', 'PATCH', [
    'nom' => 'John Updated'
], ['id' => $userId]);

// DELETE
$result = supabaseCall('utilisateurs', 'DELETE', null, ['id' => $userId]);
```

## 🔄 Migration Progressive

Pour migrer progressivement :

1. **Phase 1** : Créer les tables dans Supabase
2. **Phase 2** : Migrer les APIs une par une
3. **Phase 3** : Tester chaque fonctionnalité
4. **Phase 4** : Migrer les données existantes (si nécessaire)

## 📊 Structure des Tables

Toutes les tables sont définies dans `supabase_schema.sql` :
- utilisateurs
- signalements
- demandes
- paiements
- licences_commerciales
- emplacements_marche
- missions
- budget_municipal
- chantiers_travaux
- signalements_chefs_quartier
- stands_marche
- notifications
- preferences_notifications
- feedbacks
- messages_assistance
- faq

## ⚠️ Notes Importantes

1. **Sécurité** : L'anon key est publique mais limitée par les politiques RLS
2. **Performance** : Supabase utilise PostgreSQL, très performant
3. **Scalabilité** : Supabase gère automatiquement la scalabilité
4. **Backup** : Les backups sont automatiques avec Supabase

## 🔐 Row Level Security (RLS)

Par défaut, RLS est désactivé. Pour l'activer :

```sql
ALTER TABLE utilisateurs ENABLE ROW LEVEL SECURITY;

-- Exemple de politique
CREATE POLICY "Users can view own data" 
ON utilisateurs FOR SELECT 
USING (auth.uid() = id);
```

