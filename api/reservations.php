<?php
/**
 * API des réservations de stands marchés
 * GET: /api/reservations.php - Liste des réservations
 * POST: /api/reservations.php - Créer une réservation
 * PUT: /api/reservations.php - Annuler une réservation
 */

require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    $marche = isset($_GET['marche']) ? $_GET['marche'] : null;
    $date = isset($_GET['date']) ? $_GET['date'] : null;
    
    try {
        $pdo = getDBConnection();
        
        // Récupérer les stands disponibles
        if ($marche && $date) {
            // Vérifier disponibilités pour une date spécifique
            $stmt = $pdo->prepare("
                SELECT s.*,
                       CASE 
                           WHEN EXISTS (
                               SELECT 1 FROM reservations_marches r 
                               WHERE r.marche = s.marche 
                               AND r.numero_stand = s.numero_stand
                               AND r.statut_reservation IN ('confirmee', 'en_attente')
                               AND ? BETWEEN r.date_debut AND r.date_fin
                           ) THEN 'occupe'
                           ELSE 'disponible'
                       END as disponibilite
                FROM stands_marche s
                WHERE s.marche = ? AND s.statut = 'disponible'
            ");
            $stmt->execute([$date, $marche]);
            $stands = $stmt->fetchAll();
            
            sendJSONResponse(true, $stands, 'Stands disponibles');
        } else {
            // Récupérer les réservations de l'utilisateur
            if ($userId) {
                $stmt = $pdo->prepare("
                    SELECT r.*
                    FROM reservations_marches r
                    WHERE r.utilisateur_id = ?
                    ORDER BY r.date_creation DESC
                ");
                $stmt->execute([$userId]);
                $reservations = $stmt->fetchAll();
                sendJSONResponse(true, $reservations, 'Réservations récupérées');
            } else {
                sendJSONResponse(false, null, 'Non authentifié', 401);
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erreur récupération réservations: " . $e->getMessage());
        sendJSONResponse(true, [], 'Aucune réservation disponible');
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['marche', 'numero_stand', 'type_stand', 'date_debut', 'date_fin', 'tarif', 'mode_paiement'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        $pdo = getDBConnection();
        
        // Vérifier disponibilité
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM reservations_marches
            WHERE marche = ? AND numero_stand = ?
            AND statut_reservation IN ('confirmee', 'en_attente')
            AND (
                (? BETWEEN date_debut AND date_fin) OR
                (? BETWEEN date_debut AND date_fin) OR
                (date_debut BETWEEN ? AND ?)
            )
        ");
        $checkStmt->execute([
            $data['marche'],
            $data['numero_stand'],
            $data['date_debut'],
            $data['date_fin'],
            $data['date_debut'],
            $data['date_fin']
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            sendJSONResponse(false, null, 'Stand déjà réservé pour cette période', 409);
        }
        
        $numeroReservation = 'RES-MKT-' . date('Y') . '-' . str_pad($pdo->query("SELECT COUNT(*) FROM reservations_marches")->fetchColumn() + 1, 6, '0', STR_PAD_LEFT);
        
        // Générer QR code (en production, utiliser une bibliothèque QR)
        $qrCodeData = json_encode([
            'reservation' => $numeroReservation,
            'marche' => $data['marche'],
            'stand' => $data['numero_stand'],
            'date' => $data['date_debut']
        ]);
        $qrCode = base64_encode($qrCodeData);
        
        $stmt = $pdo->prepare("
            INSERT INTO reservations_marches 
            (utilisateur_id, marche, numero_stand, type_stand, date_debut, date_fin, 
             horaire, tarif, mode_paiement, numero_reservation, qr_code_acces, statut_reservation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $data['marche'],
            $data['numero_stand'],
            $data['type_stand'],
            $data['date_debut'],
            $data['date_fin'],
            $data['horaire'] ?? null,
            $data['tarif'],
            $data['mode_paiement'],
            $numeroReservation,
            $qrCode
        ]);
        
        $reservationId = $pdo->lastInsertId();
        
        // Récupérer la réservation créée
        $getStmt = $pdo->prepare("SELECT * FROM reservations_marches WHERE id = ?");
        $getStmt->execute([$reservationId]);
        $reservation = $getStmt->fetch();
        
        sendJSONResponse(true, $reservation, 'Réservation créée avec succès');
        
    } catch (PDOException $e) {
        error_log("Erreur création réservation: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['action'])) {
        sendJSONResponse(false, null, 'Champs manquants', 400);
    }
    
    try {
        $pdo = getDBConnection();
        
        // Vérifier que l'utilisateur est propriétaire de la réservation
        $checkStmt = $pdo->prepare("SELECT utilisateur_id FROM reservations_marches WHERE id = ?");
        $checkStmt->execute([$data['id']]);
        $reservation = $checkStmt->fetch();
        
        if (!$reservation || $reservation['utilisateur_id'] != $_SESSION['user_id']) {
            sendJSONResponse(false, null, 'Non autorisé', 403);
        }
        
        if ($data['action'] === 'annuler') {
            // Calculer frais d'annulation si < 24h
            $resStmt = $pdo->prepare("SELECT date_debut FROM reservations_marches WHERE id = ?");
            $resStmt->execute([$data['id']]);
            $res = $resStmt->fetch();
            
            $dateDebut = new DateTime($res['date_debut']);
            $maintenant = new DateTime();
            $diff = $maintenant->diff($dateDebut);
            $heuresRestantes = $diff->days * 24 + $diff->h;
            
            $statut = $heuresRestantes < 24 ? 'annulee' : 'annulee';
            
            $stmt = $pdo->prepare("UPDATE reservations_marches SET statut_reservation = ? WHERE id = ?");
            $stmt->execute([$statut, $data['id']]);
            
            sendJSONResponse(true, [
                'frais_annulation' => $heuresRestantes < 24 ? 25 : 0
            ], 'Réservation annulée');
        } else if ($data['action'] === 'confirmer') {
            $stmt = $pdo->prepare("UPDATE reservations_marches SET statut_reservation = 'confirmee' WHERE id = ?");
            $stmt->execute([$data['id']]);
            sendJSONResponse(true, null, 'Réservation confirmée');
        }
        
    } catch (PDOException $e) {
        error_log("Erreur mise à jour réservation: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}



