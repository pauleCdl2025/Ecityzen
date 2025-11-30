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
    
    // Paramètres de pagination et filtres
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 100) : 50; // Max 100, défaut 50
    $offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
    $statut = isset($_GET['statut']) ? $_GET['statut'] : null;
    $date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : null;
    $date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : null;
    
    try {
        // Construire les filtres
        $filters = [];
        
        // Gestion des rôles
        if ($role === 'manager' || $role === 'superadmin' || $role === 'hopital') {
            // Les managers, superadmins et hôpitaux voient tous les paiements
            // Pas de filtre utilisateur_id
        } else {
            // Les autres utilisateurs voient seulement leurs paiements
            if (!$userId) {
                sendJSONResponse(false, null, 'Non authentifié', 401);
            }
            $filters['utilisateur_id'] = $userId;
        }
        
        // Filtres optionnels
        if ($statut) {
            $filters['statut'] = $statut;
        }
        
        // Options de requête
        $options = [
            'order' => ['date_paiement' => 'desc'],
            'limit' => $limit
        ];
        
        // Appel Supabase optimisé
        $result = supabaseCall('paiements', 'GET', null, $filters, $options);
        $paiements = $result['success'] ? $result['data'] : [];
        
        // Filtrer par date côté PHP (plus simple que via Supabase pour les plages de dates)
        if ($date_debut && !empty($paiements)) {
            $paiements = array_filter($paiements, function($p) use ($date_debut) {
                $datePaiement = substr($p['date_paiement'] ?? '', 0, 10);
                return $datePaiement >= $date_debut;
            });
            $paiements = array_values($paiements);
        }
        if ($date_fin && !empty($paiements)) {
            $paiements = array_filter($paiements, function($p) use ($date_fin) {
                $datePaiement = substr($p['date_paiement'] ?? '', 0, 10);
                return $datePaiement <= $date_fin;
            });
            $paiements = array_values($paiements);
        }
        
        // Appliquer la pagination après filtres
        if ($offset > 0) {
            $paiements = array_slice($paiements, $offset);
        }
        $paiements = array_slice($paiements, 0, $limit);
        
        // Enrichir avec les noms d'utilisateurs (optimisé avec requête groupée)
        if (count($paiements) > 0) {
            $paiements = enrichWithUserNames($paiements, 'utilisateur_id');
        }
        
        // Formater les références
        foreach ($paiements as &$paiement) {
            if (!isset($paiement['reference_transaction']) || !$paiement['reference_transaction']) {
                $dateField = $paiement['date_paiement'] ?? date('Y-m-d');
                $paiement['reference_transaction'] = 'PAY' . date('Y', strtotime($dateField)) . '-' . str_pad($paiement['id'], 6, '0', STR_PAD_LEFT);
            }
            // Compatibilité avec l'ancien format
            $paiement['reference_paiement'] = $paiement['reference_transaction'] ?? '';
            $paiement['methode'] = $paiement['mode_paiement'] ?? 'espece';
        }
        
        // Retourner les données (compatibilité avec l'ancien format)
        // Si on demande le format paginé, retourner avec pagination, sinon format simple
        if (isset($_GET['format']) && $_GET['format'] === 'paginated') {
            sendJSONResponse(true, [
                'paiements' => $paiements,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($paiements),
                    'has_more' => count($paiements) === $limit
                ]
            ], 'Paiements récupérés');
        } else {
            // Format simple pour compatibilité avec le frontend existant
            sendJSONResponse(true, $paiements, 'Paiements récupérés');
        }
        
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
