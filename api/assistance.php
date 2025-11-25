<?php
/**
 * API d'assistance en ligne avec Supabase
 * GET: /api/assistance.php - Liste des messages/FAQ
 * POST: /api/assistance.php - Créer un message
 * PUT: /api/assistance.php - Répondre à un message
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    $type = isset($_GET['type']) ? $_GET['type'] : 'messages';
    
    try {
        if ($type === 'faq') {
            // Récupérer FAQ
            $result = supabaseCall('faq', 'GET', null, [], [
                'order' => ['ordre' => 'asc']
            ]);
            $faq = $result['success'] ? $result['data'] : [];
            sendJSONResponse(true, $faq, 'FAQ récupérée');
            
        } else {
            // Récupérer messages d'assistance
            if (!$userId) {
                sendJSONResponse(false, null, 'Non authentifié', 401);
            }
            
            $filters = [];
            $options = ['order' => ['date_creation' => 'desc'], 'limit' => 50];
            
            if (!in_array($role, ['agent', 'manager', 'superadmin'])) {
                $filters['utilisateur_id'] = $userId;
            }
            
            $result = supabaseCall('messages_assistance', 'GET', null, $filters, $options);
            $messages = $result['success'] ? $result['data'] : [];
            
            // Enrichir avec les noms d'utilisateurs
            $messages = enrichWithUserNames($messages, 'utilisateur_id', null);
            
            sendJSONResponse(true, $messages, 'Messages récupérés');
        }
        
    } catch (Exception $e) {
        error_log("Erreur récupération assistance: " . $e->getMessage());
        sendJSONResponse(true, [], 'Aucune donnée disponible');
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['message'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        $messageData = [
            'utilisateur_id' => $_SESSION['user_id'],
            'sujet' => $data['sujet'] ?? null,
            'message' => $data['message'],
            'statut' => 'ouvert'
        ];
        
        $result = supabaseCall('messages_assistance', 'POST', $messageData);
        
        if ($result['success'] && !empty($result['data'])) {
            sendJSONResponse(true, ['id' => $result['data'][0]['id']], 'Message envoyé');
        } else {
            error_log("Erreur création message assistance Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de l\'envoi', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur création message assistance: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    if (!$userId || !in_array($role, ['agent', 'manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['statut'])) {
        sendJSONResponse(false, null, 'Champs manquants', 400);
    }
    
    try {
        $updateData = ['statut' => $data['statut']];
        
        $result = supabaseCall('messages_assistance', 'PATCH', $updateData, ['id' => $data['id']]);
        
        if ($result['success']) {
            sendJSONResponse(true, null, 'Statut mis à jour');
        } else {
            sendJSONResponse(false, null, 'Erreur lors de la mise à jour', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur réponse assistance: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
