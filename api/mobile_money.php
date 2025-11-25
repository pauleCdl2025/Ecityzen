<?php
/**
 * API Mobile Money - Structure pour intégration Airtel/Moov
 * POST: /api/mobile_money.php - Initier un paiement
 * GET: /api/mobile_money.php?reference=XXX - Vérifier statut paiement
 */

require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initier un paiement Mobile Money
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['montant', 'methode', 'telephone', 'demande_id'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        $pdo = getDBConnection();
        
        // Générer une référence unique
        $reference = 'MM-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
        
        // Créer l'enregistrement de paiement
        $stmt = $pdo->prepare("
            INSERT INTO paiements 
            (utilisateur_id, demande_id, type_paiement, montant, methode, reference_paiement, statut)
            VALUES (?, ?, 'service_administratif', ?, ?, ?, 'en_attente')
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $data['demande_id'],
            $data['montant'],
            $data['methode'],
            $reference
        ]);
        
        $paiementId = $pdo->lastInsertId();
        
        // En production, ici on appellerait l'API Airtel/Moov
        // Pour la démo, on simule la réponse
        
        $response = [
            'paiement_id' => $paiementId,
            'reference' => $reference,
            'montant' => $data['montant'],
            'methode' => $data['methode'],
            'telephone' => $data['telephone'],
            'statut' => 'en_attente',
            'message' => 'Paiement initié. Vous recevrez un SMS de confirmation.',
            'callback_url' => 'http://localhost/Ecityzen/api/mobile_money_callback.php'
        ];
        
        // Si Airtel Money
        if ($data['methode'] === 'airtel') {
            // TODO: Appel API Airtel Money
            // $airtelResponse = callAirtelMoneyAPI($data['telephone'], $data['montant'], $reference);
            $response['instruction'] = 'Composez *150*60# et suivez les instructions';
        }
        
        // Si Moov Money
        if ($data['methode'] === 'moov') {
            // TODO: Appel API Moov Money
            // $moovResponse = callMoovMoneyAPI($data['telephone'], $data['montant'], $reference);
            $response['instruction'] = 'Composez *133*1# et suivez les instructions';
        }
        
        sendJSONResponse(true, $response, 'Paiement initié');
        
    } catch (PDOException $e) {
        error_log("Erreur paiement mobile money: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Vérifier le statut d'un paiement
    $reference = isset($_GET['reference']) ? $_GET['reference'] : null;
    
    if (!$reference) {
        sendJSONResponse(false, null, 'Référence manquante', 400);
    }
    
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM paiements WHERE reference_paiement = ?");
        $stmt->execute([$reference]);
        $paiement = $stmt->fetch();
        
        if (!$paiement) {
            sendJSONResponse(false, null, 'Paiement non trouvé', 404);
        }
        
        sendJSONResponse(true, $paiement, 'Statut récupéré');
        
    } catch (PDOException $e) {
        error_log("Erreur vérification paiement: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}

/**
 * Fonction helper pour appeler l'API Airtel Money (à implémenter)
 */
function callAirtelMoneyAPI($telephone, $montant, $reference) {
    // TODO: Implémenter l'appel à l'API Airtel Money
    // Exemple de structure:
    /*
    $url = 'https://api.airtelmoney.ga/collect';
    $data = [
        'msisdn' => $telephone,
        'amount' => $montant,
        'reference' => $reference,
        'callback_url' => 'http://votre-domaine.com/api/mobile_money_callback.php'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AIRTEL_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
    */
    
    return ['status' => 'pending', 'transaction_id' => uniqid()];
}

/**
 * Fonction helper pour appeler l'API Moov Money (à implémenter)
 */
function callMoovMoneyAPI($telephone, $montant, $reference) {
    // TODO: Implémenter l'appel à l'API Moov Money
    // Structure similaire à Airtel
    
    return ['status' => 'pending', 'transaction_id' => uniqid()];
}



