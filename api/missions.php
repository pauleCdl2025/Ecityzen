<?php
/**
 * API des missions agents avec Supabase
 * GET: /api/missions.php - Liste des missions
 * POST: /api/missions.php - Créer une mission
 * PUT: /api/missions.php - Mettre à jour une mission
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
    
    if (!$userId || !in_array($role, ['agent', 'manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Non autorisé', 401);
    }
    
    try {
        $filters = [];
        $options = ['order' => ['date_mission' => 'asc']];
        
        if ($role === 'agent') {
            // Les agents voient seulement leurs missions
            $filters['agent_id'] = $userId;
        }
        
        $result = supabaseCall('missions', 'GET', null, $filters, $options);
        $missions = $result['success'] ? $result['data'] : [];
        
        // Enrichir avec les noms d'agents si manager
        if ($role !== 'agent') {
            $missions = enrichWithUserNames($missions, 'agent_id', null);
        }
        
        sendJSONResponse(true, $missions, 'Missions récupérées');
        
    } catch (Exception $e) {
        error_log("Erreur récupération missions: " . $e->getMessage());
        sendJSONResponse(true, [], 'Aucune mission disponible');
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['agent_id', 'titre', 'description'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        $missionData = [
            'agent_id' => $data['agent_id'],
            'titre' => $data['titre'],
            'description' => $data['description'],
            'localisation' => $data['localisation'] ?? null,
            'latitude' => isset($data['latitude']) ? floatval($data['latitude']) : null,
            'longitude' => isset($data['longitude']) ? floatval($data['longitude']) : null,
            'statut' => $data['statut'] ?? 'assignee',
            'date_mission' => $data['date_mission'] ?? null
        ];
        
        $result = supabaseCall('missions', 'POST', $missionData);
        
        if ($result['success'] && !empty($result['data'])) {
            sendJSONResponse(true, $result['data'][0], 'Mission créée avec succès');
        } else {
            error_log("Erreur création mission Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la création', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur création mission: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['agent', 'manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['statut'])) {
        sendJSONResponse(false, null, 'Champs manquants', 400);
    }
    
    try {
        // Vérifier que l'agent ne peut modifier que ses propres missions
        if ($_SESSION['user_role'] === 'agent') {
            $checkResult = supabaseCall('missions', 'GET', null, ['id' => $data['id']]);
            if ($checkResult['success'] && !empty($checkResult['data'])) {
                $mission = $checkResult['data'][0];
                if ($mission['agent_id'] != $_SESSION['user_id']) {
                    sendJSONResponse(false, null, 'Non autorisé', 403);
                }
            }
        }
        
        $updateData = ['statut' => $data['statut']];
        
        $result = supabaseCall('missions', 'PATCH', $updateData, ['id' => $data['id']]);
        
        if ($result['success']) {
            sendJSONResponse(true, null, 'Statut mis à jour');
        } else {
            sendJSONResponse(false, null, 'Erreur lors de la mise à jour', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur mise à jour mission: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
