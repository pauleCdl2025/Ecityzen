<?php
/**
 * API des signalements avec Supabase
 * GET: /api/signalements.php - Liste des signalements
 * POST: /api/signalements.php - Créer un signalement
 * PUT: /api/signalements.php - Mettre à jour un signalement
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupérer les signalements
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    try {
        if ($role === 'agent' || $role === 'manager' || $role === 'superadmin') {
            // Les agents/managers voient tous les signalements
            $result = supabaseCall('signalements', 'GET', null, [], [
                'order' => ['date_signalement' => 'desc'],
                'limit' => 50
            ]);
            $signalements = $result['success'] ? $result['data'] : [];
        } else {
            // Les citoyens voient seulement leurs signalements
            if (!$userId) {
                sendJSONResponse(false, null, 'Non authentifié', 401);
            }
            $result = supabaseCall('signalements', 'GET', null, ['utilisateur_id' => $userId], [
                'order' => ['date_signalement' => 'desc']
            ]);
            $signalements = $result['success'] ? $result['data'] : [];
        }
        
        // Enrichir avec les noms d'utilisateurs
        $signalements = enrichWithUserNames($signalements);
        
        // Formater les données
        foreach ($signalements as &$sig) {
            $dateField = isset($sig['date_signalement']) ? $sig['date_signalement'] : ($sig['date_creation'] ?? date('Y-m-d'));
            $sig['id_formate'] = 'SIG' . date('Y', strtotime($dateField)) . '-' . str_pad($sig['id'], 6, '0', STR_PAD_LEFT);
            // Assurer la compatibilité avec l'ancien champ
            if (isset($sig['date_signalement'])) {
                $sig['date_creation'] = $sig['date_signalement'];
            }
            // Assurer la compatibilité avec photo_url
            if (isset($sig['photo_url'])) {
                $sig['photo'] = $sig['photo_url'];
            }
        }
        
        sendJSONResponse(true, $signalements, 'Signalements récupérés');
        
    } catch (Exception $e) {
        error_log("Erreur récupération signalements: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Créer un signalement
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['type', 'sous_type', 'description'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        // Gérer l'upload de photo si présent
        $photoUrl = null;
        if (isset($data['photo']) && !empty($data['photo'])) {
            $photoUrl = $data['photo'];
        }
        
        // Préparer les données pour Supabase
        $signalementData = [
            'utilisateur_id' => $_SESSION['user_id'],
            'type' => $data['type'],
            'sous_type' => $data['sous_type'],
            'description' => $data['description'],
            'localisation' => $data['localisation'] ?? null,
            'latitude' => isset($data['latitude']) ? floatval($data['latitude']) : null,
            'longitude' => isset($data['longitude']) ? floatval($data['longitude']) : null,
            'photo_url' => $photoUrl,
            'statut' => 'en_attente'
        ];
        
        // Assigner automatiquement à un agent disponible
        $agentResult = supabaseCall('utilisateurs', 'GET', null, ['role' => 'agent', 'statut' => 'actif']);
        if ($agentResult['success'] && !empty($agentResult['data'])) {
            $agents = $agentResult['data'];
            $randomAgent = $agents[array_rand($agents)];
            $signalementData['agent_assigné_id'] = $randomAgent['id'];
        }
        
        // Créer le signalement dans Supabase
        $result = supabaseCall('signalements', 'POST', $signalementData);
        
        if (!$result['success'] || empty($result['data'])) {
            error_log("Erreur création signalement Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la création du signalement', 500);
        }
        
        $signalement = $result['data'][0];
        $signalementId = $signalement['id'];
        $signalement['id_formate'] = 'SIG' . date('Y') . '-' . str_pad($signalementId, 6, '0', STR_PAD_LEFT);
        
        // Enrichir avec le nom de l'utilisateur
        $signalementsEnriched = enrichWithUserNames([$signalement]);
        $signalement = $signalementsEnriched[0];
        
        // Assurer la compatibilité
        if (isset($signalement['date_signalement'])) {
            $signalement['date_creation'] = $signalement['date_signalement'];
        }
        if (isset($signalement['photo_url'])) {
            $signalement['photo'] = $signalement['photo_url'];
        }
        
        sendJSONResponse(true, $signalement, 'Signalement créé avec succès');
        
    } catch (Exception $e) {
        error_log("Erreur création signalement: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Mettre à jour le statut d'un signalement (pour les agents)
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['agent', 'manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['statut'])) {
        sendJSONResponse(false, null, 'Champs manquants: id, statut', 400);
    }
    
    try {
        $updateData = ['statut' => $data['statut']];
        if ($data['statut'] === 'resolu') {
            $updateData['date_modification'] = date('Y-m-d H:i:s');
            $updateData['date_resolution'] = date('Y-m-d H:i:s');
        }
        
        $result = supabaseCall('signalements', 'PATCH', $updateData, ['id' => $data['id']]);
        
        if (!$result['success']) {
            error_log("Erreur mise à jour signalement Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la mise à jour', 500);
        }
        
        // Récupérer le signalement mis à jour
        $getResult = supabaseCall('signalements', 'GET', null, ['id' => $data['id']]);
        if ($getResult['success'] && !empty($getResult['data'])) {
            $signalement = $getResult['data'][0];
            
            // Enrichir avec les noms
            $signalementsEnriched = enrichWithUserNames([$signalement]);
            $signalement = $signalementsEnriched[0];
            
            $dateField = isset($signalement['date_signalement']) ? $signalement['date_signalement'] : ($signalement['date_creation'] ?? date('Y-m-d'));
            $signalement['id_formate'] = 'SIG' . date('Y', strtotime($dateField)) . '-' . str_pad($signalement['id'], 6, '0', STR_PAD_LEFT);
            
            // Assurer la compatibilité
            if (isset($signalement['date_signalement'])) {
                $signalement['date_creation'] = $signalement['date_signalement'];
            }
            if (isset($signalement['photo_url'])) {
                $signalement['photo'] = $signalement['photo_url'];
            }
            
            sendJSONResponse(true, $signalement, 'Statut mis à jour');
        } else {
            sendJSONResponse(true, null, 'Statut mis à jour');
        }
        
    } catch (Exception $e) {
        error_log("Erreur mise à jour signalement: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
