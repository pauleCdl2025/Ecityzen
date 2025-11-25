<?php
/**
 * API des paiements avec Supabase
 * GET: /api/paiements.php - Liste des paiements
 * POST: /api/paiements.php - Créer un paiement
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupérer les paiements
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    try {
        if ($role === 'manager' || $role === 'superadmin') {
            // Les managers voient tous les paiements
            $result = supabaseCall('paiements', 'GET', null, [], [
                'order' => ['date_paiement' => 'desc'],
                'limit' => 50
            ]);
            $paiements = $result['success'] ? $result['data'] : [];
        } else {
            // Les utilisateurs voient seulement leurs paiements
            if (!$userId) {
                sendJSONResponse(false, null, 'Non authentifié', 401);
            }
            $result = supabaseCall('paiements', 'GET', null, ['utilisateur_id' => $userId], [
                'order' => ['date_paiement' => 'desc']
            ]);
            $paiements = $result['success'] ? $result['data'] : [];
        }
        
        // Enrichir avec les noms d'utilisateurs
        $paiements = enrichWithUserNames($paiements, 'utilisateur_id');
        
        // Formater les références
        foreach ($paiements as &$paiement) {
            if (!$paiement['reference_transaction']) {
                $dateField = $paiement['date_paiement'] ?? date('Y-m-d');
                $paiement['reference_transaction'] = 'PAY' . date('Y', strtotime($dateField)) . '-' . str_pad($paiement['id'], 6, '0', STR_PAD_LEFT);
            }
            // Compatibilité avec l'ancien format
            $paiement['reference_paiement'] = $paiement['reference_transaction'];
            $paiement['methode'] = $paiement['mode_paiement'];
        }
        
        sendJSONResponse(true, $paiements, 'Paiements récupérés');
        
    } catch (Exception $e) {
        error_log("Erreur récupération paiements: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Créer un paiement
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['montant', 'mode_paiement'];
    foreach ($required as $field) {
        // Support des anciens noms de champs
        if (!isset($data[$field]) && !isset($data[str_replace('mode_paiement', 'methode', $field)])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        $referenceTransaction = 'PAY' . date('Y') . '-' . str_pad(time(), 8, '0', STR_PAD_LEFT);
        
        $paiementData = [
            'utilisateur_id' => $_SESSION['user_id'],
            'demande_id' => $data['demande_id'] ?? null,
            'montant' => floatval($data['montant']),
            'mode_paiement' => $data['mode_paiement'] ?? $data['methode'] ?? 'espece',
            'reference_transaction' => $referenceTransaction,
            'statut' => 'en_attente'
        ];
        
        $result = supabaseCall('paiements', 'POST', $paiementData);
        
        if (!$result['success'] || empty($result['data'])) {
            error_log("Erreur création paiement Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la création du paiement', 500);
        }
        
        $paiement = $result['data'][0];
        $paiementId = $paiement['id'];
        
        // Simuler le traitement du paiement (en production, intégrer avec les APIs de paiement)
        sleep(1);
        
        // Mettre à jour le statut
        $updateResult = supabaseCall('paiements', 'PATCH', ['statut' => 'confirme'], ['id' => $paiementId]);
        
        // Si c'est un paiement pour une demande, mettre à jour le statut
        if (isset($data['demande_id'])) {
            supabaseCall('demandes', 'PATCH', ['statut' => 'en_traitement'], ['id' => $data['demande_id']]);
        }
        
        // Récupérer le paiement mis à jour
        $getResult = supabaseCall('paiements', 'GET', null, ['id' => $paiementId]);
        if ($getResult['success'] && !empty($getResult['data'])) {
            $paiement = $getResult['data'][0];
            
            // Enrichir avec le nom de l'utilisateur
            $paiementsEnriched = enrichWithUserNames([$paiement], 'utilisateur_id');
            $paiement = $paiementsEnriched[0];
            
            // Compatibilité
            $paiement['reference_paiement'] = $paiement['reference_transaction'];
            $paiement['methode'] = $paiement['mode_paiement'];
            
            sendJSONResponse(true, $paiement, 'Paiement confirmé');
        } else {
            sendJSONResponse(true, $paiement, 'Paiement confirmé');
        }
        
    } catch (Exception $e) {
        error_log("Erreur création paiement: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
