<?php
/**
 * API des notifications avec Supabase
 * GET: /api/notifications.php - Liste des notifications utilisateur
 * POST: /api/notifications.php - Créer une notification (admin)
 * PUT: /api/notifications.php - Marquer comme lu
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
    
    if (!$userId) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    try {
        // Récupérer les notifications de l'utilisateur
        $result = supabaseCall('notifications', 'GET', null, ['utilisateur_id' => $userId], [
            'order' => ['date_envoi' => 'desc'],
            'limit' => 50
        ]);
        
        $notifications = $result['success'] ? $result['data'] : [];
        
        // Compter les non lues
        $nonLues = 0;
        foreach ($notifications as $notif) {
            if (!isset($notif['statut_lecture']) || $notif['statut_lecture'] === 'non_lu') {
                $nonLues++;
            }
        }
        
        sendJSONResponse(true, [
            'notifications' => $notifications,
            'non_lues' => $nonLues
        ], 'Notifications récupérées');
        
    } catch (Exception $e) {
        error_log("Erreur récupération notifications: " . $e->getMessage());
        sendJSONResponse(true, ['notifications' => [], 'non_lues' => 0], 'Aucune notification');
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    if (!$userId || !in_array($role, ['manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['titre', 'message'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        $notificationData = [
            'utilisateur_id' => $data['utilisateur_id'] ?? $userId,
            'titre' => $data['titre'],
            'message' => $data['message'],
            'categorie' => $data['categorie'] ?? 'informative',
            'statut_lecture' => 'non_lu'
        ];
        
        $result = supabaseCall('notifications', 'POST', $notificationData);
        
        if (!$result['success'] || empty($result['data'])) {
            error_log("Erreur création notification Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la création de la notification', 500);
        }
        
        sendJSONResponse(true, $result['data'][0], 'Notification créée');
        
    } catch (Exception $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        sendJSONResponse(false, null, 'ID notification manquant', 400);
    }
    
    try {
        // Marquer comme lu
        $updateData = [
            'statut_lecture' => 'lu'
        ];
        
        $result = supabaseCall('notifications', 'PATCH', $updateData, ['id' => $data['id'], 'utilisateur_id' => $_SESSION['user_id']]);
        
        if ($result['success']) {
            sendJSONResponse(true, null, 'Notification marquée comme lue');
        } else {
            sendJSONResponse(false, null, 'Erreur lors de la mise à jour', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur mise à jour notification: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
