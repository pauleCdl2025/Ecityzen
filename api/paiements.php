



        
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
