<?php
/**
 * API des signalements chefs de quartier avec Supabase
 * GET: /api/chefs_quartier.php - Liste des signalements
 * POST: /api/chefs_quartier.php - Créer un signalement
 * PUT: /api/chefs_quartier.php - Mettre à jour statut
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
    
    if (!$userId) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    try {
        $filters = [];
        $options = ['order' => ['date_signalement' => 'desc']];
        
        if ($role === 'chef_quartier') {
            $filters['chef_quartier_id'] = $userId;
        } else if (!in_array($role, ['manager', 'superadmin', 'agent'])) {
            sendJSONResponse(false, null, 'Non autorisé', 403);
        }
        
        $result = supabaseCall('signalements_chefs_quartier', 'GET', null, $filters, $options);
        $signalements = $result['success'] ? $result['data'] : [];
        
        // Enrichir avec les noms des chefs de quartier
        if ($role !== 'chef_quartier') {
            $signalements = enrichWithUserNames($signalements, 'chef_quartier_id', null);
        }
        
        sendJSONResponse(true, $signalements, 'Signalements récupérés');
        
    } catch (Exception $e) {
        error_log("Erreur récupération signalements chefs quartier: " . $e->getMessage());
        sendJSONResponse(true, [], 'Aucun signalement disponible');
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    if (!$userId || $role !== 'chef_quartier') {
        sendJSONResponse(false, null, 'Non autorisé - Réservé aux chefs de quartier', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['type', 'description'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        $signalementData = [
            'chef_quartier_id' => $userId,
            'type' => $data['type'],
            'description' => $data['description'],
            'localisation' => $data['localisation'] ?? null,
            'latitude' => isset($data['latitude']) ? floatval($data['latitude']) : null,
            'longitude' => isset($data['longitude']) ? floatval($data['longitude']) : null,
            'statut' => 'en_attente'
        ];
        
        $result = supabaseCall('signalements_chefs_quartier', 'POST', $signalementData);
        
        if ($result['success'] && !empty($result['data'])) {
            sendJSONResponse(true, $result['data'][0], 'Signalement créé avec succès');
        } else {
            error_log("Erreur création signalement Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la création', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur création signalement chef quartier: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    if (!$userId || !in_array($role, ['manager', 'superadmin', 'agent'])) {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['statut'])) {
        sendJSONResponse(false, null, 'Champs manquants', 400);
    }
    
    try {
        $updateData = ['statut' => $data['statut']];
        
        $result = supabaseCall('signalements_chefs_quartier', 'PATCH', $updateData, ['id' => $data['id']]);
        
        if ($result['success']) {
            sendJSONResponse(true, null, 'Statut mis à jour');
        } else {
            sendJSONResponse(false, null, 'Erreur lors de la mise à jour', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur mise à jour signalement: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
