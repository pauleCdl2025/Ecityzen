# Migration vers Supabase - e-cityzen Gabon

## Configuration Supabase

- **URL**: https://srbzvjrqbhtuyzlwdghn.supabase.co
- **Anon Key**: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM

## Étapes de migration

### 1. Créer les tables dans Supabase

Exécutez le script SQL dans l'éditeur SQL de Supabase (Dashboard > SQL Editor).

### 2. Configuration

Les fichiers de configuration sont déjà créés :
- `config/supabase.php` - Configuration PHP pour Supabase
- Le client JavaScript Supabase est intégré dans `ECITYZEN.html`

### 3. Migration des APIs

Les APIs doivent être adaptées pour utiliser Supabase au lieu de MySQL.

## Utilisation

### Côté Frontend (JavaScript)

```javascript
// Utiliser supabaseCall au lieu de apiCall pour les opérations Supabase
const result = await supabaseCall('utilisateurs', 'SELECT', null, { telephone: '074027173' });
```

### Côté Backend (PHP)

```php
// Utiliser supabaseCall au lieu de PDO
$result = supabaseCall('utilisateurs', 'GET', null, ['telephone' => $telephone]);
```

