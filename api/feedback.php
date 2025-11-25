<?php
/**
 * API des feedbacks et notations avec Supabase
 * GET: /api/feedback.php - Liste des feedbacks
 * POST: /api/feedback.php - Créer un feedback
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    try {
        $filters = [];
        $options = ['order' => ['date_creation' => 'desc'], 'limit' => 50];
        
        if ($userId && !in_array($role, ['manager', 'superadmin'])) {
            $filters['utilisateur_id'] = $userId;
        }
        
        $result = supabaseCall('feedbacks', 'GET', null, $filters, $options);
        $feedbacks = $result['success'] ? $result['data'] : [];
        
        // Enrichir avec les noms d'utilisateurs
        $feedbacks = enrichWithUserNames($feedbacks, 'utilisateur_id', null);
        
        sendJSONResponse(true, ['feedbacks' => $feedbacks], 'Feedbacks récupérés');
        
    } catch (Exception $e) {
        error_log("Erreur récupération feedbacks: " . $e->getMessage());
        sendJSONResponse(true, ['feedbacks' => []], 'Aucun feedback');
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['note', 'commentaire'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    if ($data['note'] < 1 || $data['note'] > 5) {
        sendJSONResponse(false, null, 'Note doit être entre 1 et 5', 400);
    }
    
    try {
        $feedbackData = [
            'utilisateur_id' => $_SESSION['user_id'],
            'note' => intval($data['note']),
            'commentaire' => $data['commentaire'] ?? null
        ];
        
        $result = supabaseCall('feedbacks', 'POST', $feedbackData);
        
        if ($result['success'] && !empty($result['data'])) {
            sendJSONResponse(true, $result['data'][0], 'Feedback créé');
        } else {
            error_log("Erreur création feedback Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la création', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur création feedback: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
