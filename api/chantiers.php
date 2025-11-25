<?php
/**
 * API des chantiers de travaux publics avec Supabase
 * GET: /api/chantiers.php - Liste des chantiers
 * POST: /api/chantiers.php - Créer un chantier
 * PUT: /api/chantiers.php - Mettre à jour un chantier
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $statut = isset($_GET['statut']) ? $_GET['statut'] : null;
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    
    try {
        $filters = [];
        if ($statut) {
            $filters['statut'] = $statut;
        }
        if ($type) {
            $filters['type'] = $type;
        }
        
        $options = [
            'order' => ['date_debut' => 'desc']
        ];
        
        $result = supabaseCall('chantiers_travaux', 'GET', null, $filters, $options);
        $chantiers = $result['success'] ? $result['data'] : [];
        
        sendJSONResponse(true, $chantiers, 'Chantiers récupérés');
        
    } catch (Exception $e) {
        error_log("Erreur récupération chantiers: " . $e->getMessage());
        sendJSONResponse(true, [], 'Aucun chantier disponible');
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    if (!$userId || !in_array($role, ['manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['titre', 'type', 'description'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        $chantierData = [
            'titre' => $data['titre'],
            'description' => $data['description'],
            'type' => $data['type'],
            'localisation' => $data['localisation'] ?? null,
            'latitude' => isset($data['latitude']) ? floatval($data['latitude']) : null,
            'longitude' => isset($data['longitude']) ? floatval($data['longitude']) : null,
            'statut' => $data['statut'] ?? 'planifie',
            'date_debut' => $data['date_debut'] ?? null,
            'date_fin_prevue' => $data['date_fin_prevue'] ?? null,
            'budget_alloue' => isset($data['budget_alloue']) ? floatval($data['budget_alloue']) : null,
            'entreprise' => $data['entreprise'] ?? null
        ];
        
        $result = supabaseCall('chantiers_travaux', 'POST', $chantierData);
        
        if ($result['success'] && !empty($result['data'])) {
            sendJSONResponse(true, $result['data'][0], 'Chantier créé avec succès');
        } else {
            error_log("Erreur création chantier Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la création', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur création chantier: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    if (!$userId || !in_array($role, ['manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        sendJSONResponse(false, null, 'ID manquant', 400);
    }
    
    try {
        $updateData = [];
        
        $allowedFields = ['statut', 'date_fin_prevue', 'date_fin_reelle', 'description', 'budget_alloue'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            sendJSONResponse(false, null, 'Aucun champ à mettre à jour', 400);
        }
        
        $result = supabaseCall('chantiers_travaux', 'PATCH', $updateData, ['id' => $data['id']]);
        
        if ($result['success']) {
            sendJSONResponse(true, null, 'Chantier mis à jour');
        } else {
            sendJSONResponse(false, null, 'Erreur lors de la mise à jour', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur mise à jour chantier: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
