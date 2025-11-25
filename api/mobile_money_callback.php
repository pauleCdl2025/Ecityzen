<?php
/**
 * Callback pour les paiements Mobile Money
 * Appelé par les APIs Airtel/Moov après traitement du paiement
 */

require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// En production, vérifier la signature/authentification du callback
// $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
// if (!verifyCallbackSignature($signature, $_POST)) {
//     http_response_code(401);
//     exit;
// }

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$reference = $data['reference'] ?? $data['transaction_reference'] ?? null;
$statut = $data['status'] ?? $data['statut'] ?? 'unknown';
$transactionId = $data['transaction_id'] ?? null;

if (!$reference) {
    http_response_code(400);
    echo json_encode(['error' => 'Référence manquante']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Trouver le paiement
    $stmt = $pdo->prepare("SELECT * FROM paiements WHERE reference_paiement = ?");
    $stmt->execute([$reference]);
    $paiement = $stmt->fetch();
    
    if (!$paiement) {
        http_response_code(404);
        echo json_encode(['error' => 'Paiement non trouvé']);
        exit;
    }
    
    // Mettre à jour le statut
    $nouveauStatut = ($statut === 'success' || $statut === 'completed' || $statut === 'confirmed') ? 'confirme' : 'echec';
    
    $updateStmt = $pdo->prepare("
        UPDATE paiements 
        SET statut = ?, date_confirmation = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$nouveauStatut, $paiement['id']]);
    
    // Si le paiement est confirmé, mettre à jour la demande associée
    if ($nouveauStatut === 'confirme' && $paiement['demande_id']) {
        $demandeStmt = $pdo->prepare("
            UPDATE demandes 
            SET statut = 'en_traitement'
            WHERE id = ? AND statut = 'en_attente'
        ");
        $demandeStmt->execute([$paiement['demande_id']]);
    }
    
    // Logger le callback
    error_log("Mobile Money Callback: Reference=$reference, Status=$statut, Transaction=$transactionId");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'reference' => $reference,
        'statut' => $nouveauStatut
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur callback mobile money: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}



