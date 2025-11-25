<?php
/**
 * Script de test de connexion à la base de données
 * Accédez à: http://localhost/Ecityzen/test_connection.php
 */

require_once 'config/database.php';

echo "<h1>Test de connexion e-cityzen Gabon</h1>";
echo "<style>body { font-family: Arial, sans-serif; padding: 20px; } .success { color: green; } .error { color: red; }</style>";

// Test 1: Connexion à la base de données
echo "<h2>1. Test de connexion à la base de données</h2>";
try {
    $pdo = getDBConnection();
    echo "<p class='success'>✓ Connexion réussie à la base de données</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Erreur de connexion: " . $e->getMessage() . "</p>";
    exit;
}

// Test 2: Vérification des tables
echo "<h2>2. Vérification des tables</h2>";
$tables = ['utilisateurs', 'signalements', 'demandes', 'paiements', 'licences_commerciales', 'emplacements_marche', 'missions', 'statistiques'];
$missing = [];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p class='success'>✓ Table '$table' existe</p>";
        } else {
            echo "<p class='error'>✗ Table '$table' manquante</p>";
            $missing[] = $table;
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Erreur lors de la vérification de '$table': " . $e->getMessage() . "</p>";
    }
}

if (!empty($missing)) {
    echo "<p class='error'><strong>Action requise:</strong> Importez le fichier database.sql dans phpMyAdmin</p>";
}

// Test 3: Vérification des utilisateurs
echo "<h2>3. Vérification des utilisateurs</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs");
    $result = $stmt->fetch();
    $count = $result['count'];
    
    if ($count > 0) {
        echo "<p class='success'>✓ $count utilisateur(s) trouvé(s)</p>";
        
        // Afficher les utilisateurs
        $stmt = $pdo->query("SELECT nom, email, role FROM utilisateurs LIMIT 5");
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-top: 10px;'>";
        echo "<tr><th>Nom</th><th>Email</th><th>Rôle</th></tr>";
        while ($user = $stmt->fetch()) {
            echo "<tr><td>{$user['nom']}</td><td>{$user['email']}</td><td>{$user['role']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>✗ Aucun utilisateur trouvé. Importez database.sql</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Erreur: " . $e->getMessage() . "</p>";
}

// Test 4: Test des API
echo "<h2>4. Test des endpoints API</h2>";
$endpoints = [
    'api/login.php' => 'POST',
    'api/logout.php' => 'POST',
    'api/signalements.php' => 'GET',
    'api/demandes.php' => 'GET',
    'api/paiements.php' => 'GET',
    'api/stats.php' => 'GET'
];

foreach ($endpoints as $endpoint => $method) {
    $file = __DIR__ . '/' . $endpoint;
    if (file_exists($file)) {
        echo "<p class='success'>✓ $endpoint existe</p>";
    } else {
        echo "<p class='error'>✗ $endpoint manquant</p>";
    }
}

// Résumé
echo "<h2>Résumé</h2>";
if (empty($missing) && $count > 0) {
    echo "<p class='success'><strong>✓ Tout est configuré correctement !</strong></p>";
    echo "<p>Vous pouvez maintenant accéder à l'application: <a href='ECITYZEN.html'>ECITYZEN.html</a></p>";
} else {
    echo "<p class='error'><strong>✗ Configuration incomplète</strong></p>";
    echo "<p>Veuillez:</p>";
    echo "<ul>";
    if (!empty($missing)) {
        echo "<li>Importer le fichier database.sql dans phpMyAdmin</li>";
    }
    if ($count == 0) {
        echo "<li>Vérifier que les données d'exemple ont été importées</li>";
    }
    echo "</ul>";
}

