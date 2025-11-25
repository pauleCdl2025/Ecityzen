<?php
/**
 * API des préférences de notifications avec Supabase
 * GET: /api/preferences_notifications.php - Récupérer préférences
 * POST: /api/preferences_notifications.php - Mettre à jour préférences
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
    
    if (!$userId) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    try {
        $result = supabaseCall('preferences_notifications', 'GET', null, ['utilisateur_id' => $userId]);
        
        if ($result['success'] && !empty($result['data'])) {
            sendJSONResponse(true, $result['data'][0], 'Préférences récupérées');
        } else {
            // Retourner préférences par défaut
            $defaults = [
                'notifications_circulation' => true,
                'notifications_evenements' => true,
                'notifications_alertes' => true,
                'notifications_administratives' => true,
                'notifications_commerciales' => false
            ];
            sendJSONResponse(true, $defaults, 'Préférences par défaut');
        }
        
    } catch (Exception $e) {
        error_log("Erreur récupération préférences: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $allowedFields = [
            'notifications_circulation', 'notifications_evenements', 'notifications_alertes',
            'notifications_administratives', 'notifications_commerciales'
        ];
        
        $updateData = ['utilisateur_id' => $_SESSION['user_id']];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field] === true || $data[$field] === 'true' || $data[$field] === 1;
            }
        }
        
        // Vérifier si les préférences existent
        $checkResult = supabaseCall('preferences_notifications', 'GET', null, ['utilisateur_id' => $_SESSION['user_id']]);
        
        if ($checkResult['success'] && !empty($checkResult['data'])) {
            // Mise à jour
            unset($updateData['utilisateur_id']); // Ne pas mettre à jour l'ID
            $result = supabaseCall('preferences_notifications', 'PATCH', $updateData, ['utilisateur_id' => $_SESSION['user_id']]);
        } else {
            // Création
            $result = supabaseCall('preferences_notifications', 'POST', $updateData);
        }
        
        if ($result['success']) {
            sendJSONResponse(true, null, 'Préférences mises à jour');
        } else {
            error_log("Erreur mise à jour préférences Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la mise à jour', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur mise à jour préférences: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
