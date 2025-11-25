<?php
/**
 * API des emplacements de marché avec Supabase
 * GET: /api/emplacements.php - Liste des emplacements
 * POST: /api/emplacements.php - Réserver un emplacement
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
    
    if (!$userId) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    try {
        $filters = [];
        $options = ['order' => ['date_attribution' => 'desc']];
        
        if ($role === 'commercant') {
            // Les commerçants voient seulement leurs emplacements
            $filters['utilisateur_id'] = $userId;
        }
        
        $result = supabaseCall('emplacements_marche', 'GET', null, $filters, $options);
        $emplacements = $result['success'] ? $result['data'] : [];
        
        // Enrichir avec les noms d'utilisateurs si manager
        if ($role !== 'commercant') {
            $emplacements = enrichWithUserNames($emplacements, 'utilisateur_id', null);
        }
        
        sendJSONResponse(true, $emplacements, 'Emplacements récupérés');
        
    } catch (Exception $e) {
        error_log("Erreur récupération emplacements: " . $e->getMessage());
        sendJSONResponse(true, [], 'Aucun emplacement disponible');
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'commercant') {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['marche', 'numero_stand'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        $emplacementData = [
            'utilisateur_id' => $_SESSION['user_id'],
            'marche' => $data['marche'],
            'numero_stand' => $data['numero_stand'],
            'type_stand' => $data['type_stand'] ?? null,
            'statut' => $data['statut'] ?? 'actif',
            'date_attribution' => $data['date_attribution'] ?? date('Y-m-d')
        ];
        
        $result = supabaseCall('emplacements_marche', 'POST', $emplacementData);
        
        if ($result['success'] && !empty($result['data'])) {
            sendJSONResponse(true, $result['data'][0], 'Emplacement réservé avec succès');
        } else {
            error_log("Erreur réservation emplacement Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la réservation', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur réservation emplacement: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
