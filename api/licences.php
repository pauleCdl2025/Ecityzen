<?php
/**
 * API des licences commerciales avec Supabase
 * GET: /api/licences.php - Liste des licences
 * POST: /api/licences.php - Créer une licence
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
        $options = ['order' => ['date_expiration' => 'desc']];
        
        if ($role === 'commercant') {
            // Les commerçants voient seulement leurs licences
            $filters['utilisateur_id'] = $userId;
        }
        
        $result = supabaseCall('licences_commerciales', 'GET', null, $filters, $options);
        $licences = $result['success'] ? $result['data'] : [];
        
        // Enrichir avec les noms d'utilisateurs si manager
        if ($role !== 'commercant') {
            $licences = enrichWithUserNames($licences, 'utilisateur_id', null);
        }
        
        sendJSONResponse(true, $licences, 'Licences récupérées');
        
    } catch (Exception $e) {
        error_log("Erreur récupération licences: " . $e->getMessage());
        sendJSONResponse(true, [], 'Aucune licence disponible');
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'commercant') {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['type_activite', 'adresse'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        $licenceData = [
            'utilisateur_id' => $_SESSION['user_id'],
            'type_activite' => $data['type_activite'],
            'adresse' => $data['adresse'],
            'numero_licence' => $data['numero_licence'] ?? null,
            'date_emission' => $data['date_emission'] ?? date('Y-m-d'),
            'date_expiration' => $data['date_expiration'] ?? null,
            'statut' => $data['statut'] ?? 'active'
        ];
        
        $result = supabaseCall('licences_commerciales', 'POST', $licenceData);
        
        if ($result['success'] && !empty($result['data'])) {
            sendJSONResponse(true, $result['data'][0], 'Licence créée avec succès');
        } else {
            error_log("Erreur création licence Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la création', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur création licence: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
