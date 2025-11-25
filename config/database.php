<?php
/**
 * Configuration de la base de données
 * e-cityzen Gabon
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecityzen_gabon');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Connexion à la base de données
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur de connexion à la base de données: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
        exit;
    }
}

/**
 * Réponse JSON standardisée
 */
function sendJSONResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function checkAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    return $_SESSION['user_id'];
}

/**
 * Générer un numéro de dossier unique
 */
function generateNumeroDossier($prefix = 'DC', $year = null) {
    if ($year === null) {
        $year = date('Y');
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM demandes WHERE numero_dossier LIKE ?");
    $pattern = $prefix . $year . '-%';
    $stmt->execute([$pattern]);
    $result = $stmt->fetch();
    $count = $result['count'] + 1;
    
    return $prefix . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
}

/**
 * Générer une référence de paiement unique
 */
function generateReferencePaiement($prefix = 'PAY') {
    $year = date('Y');
    $pdo = getDBConnection();
    
    do {
        $ref = $prefix . $year . '-' . strtoupper(uniqid());
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM paiements WHERE reference_paiement = ?");
        $stmt->execute([$ref]);
        $result = $stmt->fetch();
    } while ($result['count'] > 0);
    
    return $ref;
}

