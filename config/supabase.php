<?php
/**
 * Configuration Supabase
 * e-cityzen Gabon
 */

define('SUPABASE_URL', 'https://srbzvjrqbhtuyzlwdghn.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNyYnp2anJxYmh0dXl6bHdkZ2huIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQwNTg3NzQsImV4cCI6MjA3OTYzNDc3NH0.5KOkXAANWV_WLWPx02ozeC_xPCINd6boVtm3ia9iSmM');
define('SUPABASE_TABLE_PREFIX', '');

/**
 * Appel API Supabase
 */
function supabaseCall($table, $method = 'GET', $data = null, $filters = [], $options = []) {
    $url = SUPABASE_URL . '/rest/v1/' . $table;
    
    $ch = curl_init();
    
    
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    
    // Ajouter les options de select si spécifiées
    $queryParams = [];
    
    if (isset($options['select'])) {
        $queryParams[] = 'select=' . urlencode($options['select']);
    }
    
    if (isset($options['order'])) {
        if (is_array($options['order'])) {
            // Format: ['field' => 'desc'] ou ['date_creation' => 'desc', 'id' => 'asc']
            foreach ($options['order'] as $field => $direction) {
                $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
                $queryParams[] = 'order=' . urlencode($field . '.' . $direction);
            }
        } else {
            // Format simple: 'field.desc'
            $queryParams[] = 'order=' . urlencode($options['order']);
        }
    }
    
    if (isset($options['limit'])) {
        $queryParams[] = 'limit=' . intval($options['limit']);
    }
    
    // Ajouter les filtres pour GET
    if ($method === 'GET' && !empty($filters)) {
        foreach ($filters as $key => $value) {
            if ($value !== null) {
                // Support des opérateurs Supabase (eq, in, gte, lte, etc.)
                if (is_string($value) && strpos($value, '.') !== false) {
                    // Format déjà avec opérateur: "gte.2024-01-01"
                    $queryParams[] = $key . '=' . urlencode($value);
                } else {
                    // Format simple: égalité
                $queryParams[] = $key . '=eq.' . urlencode($value);
                }
            }
        }
    }
    
    // Pour PUT/PATCH/DELETE, ajouter les filtres dans l'URL
    if (in_array($method, ['PUT', 'PATCH', 'DELETE']) && !empty($filters)) {
        foreach ($filters as $key => $value) {
            if ($value !== null) {
                $queryParams[] = $key . '=eq.' . urlencode($value);
            }
        }
    }
    
    if (!empty($queryParams)) {
        $url .= '?' . implode('&', $queryParams);
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    switch ($method) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
            
        case 'PUT':
        case 'PATCH':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
            
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Erreur cURL Supabase: " . $error);
        return [
            'success' => false,
            'error' => $error
        ];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        // Pour les requêtes qui retournent un tableau vide, retourner un tableau
        if (is_array($result) && empty($result)) {
            return [
                'success' => true,
                'data' => []
            ];
        }
        return [
            'success' => true,
            'data' => $result
        ];
    } else {
        error_log("Erreur Supabase HTTP $httpCode: " . $response);
        $errorMessage = 'Erreur Supabase';
        if (is_array($result) && isset($result['message'])) {
            $errorMessage = $result['message'];
        } elseif (is_string($result)) {
            $errorMessage = $result;
        }
        return [
            'success' => false,
            'error' => $errorMessage,
            'code' => $httpCode
        ];
    }
}

/**
 * Enrichir les données avec les noms d'utilisateurs
 */
function enrichWithUserNames($items, $userIdField = 'utilisateur_id', $agentIdField = 'agent_assigné_id') {
    if (empty($items)) {
        return $items;
    }
    
    // Collecter tous les IDs d'utilisateurs uniques
    $userIds = [];
    foreach ($items as $item) {
        if (isset($item[$userIdField]) && $item[$userIdField]) {
            $userIds[$item[$userIdField]] = true;
        }
        if (isset($item[$agentIdField]) && $item[$agentIdField]) {
            $userIds[$item[$agentIdField]] = true;
        }
    }
    
    $userIds = array_keys($userIds);
    
    if (empty($userIds)) {
        return $items;
    }
    
    // Récupérer les noms des utilisateurs - OPTIMISATION : requête groupée
    $users = [];
    
    // Si on a beaucoup d'utilisateurs, faire des requêtes groupées (max 100 par requête)
    $chunks = array_chunk($userIds, 100);
    foreach ($chunks as $chunk) {
        // Utiliser l'opérateur 'in' de Supabase pour récupérer plusieurs utilisateurs en une requête
        // Format: id=in.(1,2,3)
        $idsList = implode(',', array_map('intval', $chunk)); // S'assurer que ce sont des entiers
        $url = SUPABASE_URL . '/rest/v1/utilisateurs?id=in.(' . $idsList . ')&select=id,nom';
        
        $ch = curl_init();
        $headers = [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $result = json_decode($response, true);
            if (is_array($result)) {
                foreach ($result as $user) {
                    if (isset($user['id']) && isset($user['nom'])) {
                        $users[$user['id']] = $user['nom'];
                    }
                }
            }
        }
    }
    
    // Enrichir les items
    foreach ($items as &$item) {
        if (isset($item[$userIdField]) && isset($users[$item[$userIdField]])) {
            $item['utilisateur_nom'] = $users[$item[$userIdField]];
        }
        if (isset($item[$agentIdField]) && isset($users[$item[$agentIdField]])) {
            $item['agent_nom'] = $users[$item[$agentIdField]];
        }
    }
    
    return $items;
}

/**
 * Configurer les headers CORS correctement
 */
function setupCORS($allowedMethods = 'GET, POST, PUT, DELETE, OPTIONS') {
    // CORS: Si credentials est true, on ne peut pas utiliser '*', il faut spécifier l'origine
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 
              (isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_SCHEME) . '://' . parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : '*');
    
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: ' . $allowedMethods);
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // Cache preflight pour 24h
    
    // Gérer les requêtes OPTIONS (preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Démarrer la session avec configuration améliorée
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configuration de session pour améliorer la sécurité et la compatibilité
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        // Augmenter la durée de vie de la session (8 heures)
        ini_set('session.gc_maxlifetime', 28800);
        session_set_cookie_params([
            'lifetime' => 28800, // 8 heures
            'path' => '/',
            'domain' => '',
            'secure' => false, // Mettre à true en HTTPS
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/**
 * Réponse JSON standardisée (compatible avec l'existant)
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

