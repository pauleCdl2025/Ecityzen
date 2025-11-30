<?php
/**
 * API des demandes administratives avec Supabase
 * GET: /api/demandes.php - Liste des demandes
 * POST: /api/demandes.php - Créer une demande
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupérer les demandes
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    try {
        if ($role === 'agent' || $role === 'manager' || $role === 'superadmin') {
            // Les agents/managers voient toutes les demandes
            $result = supabaseCall('demandes', 'GET', null, [], [
                'order' => ['date_creation' => 'desc'],
                'limit' => 50
            ]);
            $demandes = $result['success'] ? $result['data'] : [];
        } else {
            // Les citoyens voient seulement leurs demandes
            if (!$userId) {
                sendJSONResponse(false, null, 'Non authentifié', 401);
            }
            $result = supabaseCall('demandes', 'GET', null, ['utilisateur_id' => $userId], [
                'order' => ['date_creation' => 'desc']
            ]);
            $demandes = $result['success'] ? $result['data'] : [];
        }
        
        // Enrichir avec les noms d'utilisateurs
        $demandes = enrichWithUserNames($demandes);
        
        // Décoder les documents JSON pour chaque demande
        foreach ($demandes as &$demande) {
            if (isset($demande['documents']) && $demande['documents']) {
                if (is_string($demande['documents'])) {
                    // Décoder le JSON string
                    $decoded = json_decode($demande['documents'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $demande['documents'] = $decoded;
                    } else {
                        // Si le décodage échoue, essayer de le traiter comme un tableau
                        error_log("Erreur décodage documents JSON pour demande " . ($demande['id'] ?? 'N/A') . ": " . json_last_error_msg());
                        $demande['documents'] = [];
                    }
                } elseif (!is_array($demande['documents'])) {
                    // Si ce n'est ni une string ni un array, initialiser à vide
                    $demande['documents'] = [];
                }
                // Si c'est déjà un array, on le garde tel quel
            } else {
                // S'assurer que documents existe même si vide
                $demande['documents'] = [];
            }
        }
        
        sendJSONResponse(true, $demandes, 'Demandes récupérées');
        
    } catch (Exception $e) {
        error_log("Erreur récupération demandes: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Créer une demande
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    // Détecter si c'est un FormData (multipart/form-data) ou JSON
    $isFormData = isset($_POST['type']) || (isset($_FILES) && count($_FILES) > 0);
    
    if ($isFormData) {
        // Traitement FormData (avec fichiers)
        $type = $_POST['type'] ?? null;
        $service = $_POST['service'] ?? null;
        $motif = $_POST['motif'] ?? null;
        $cout = isset($_POST['cout']) ? floatval($_POST['cout']) : null;
        
        if (!$type || !$service || $cout === null) {
            sendJSONResponse(false, null, 'Champs manquants: type, service, cout', 400);
        }
        
        // Créer le dossier d'upload s'il n'existe pas
        $uploadDir = '../uploads/demandes/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $documents = [];
        $documentIndex = 0;
        
        // Traiter tous les fichiers uploadés
        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'document_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
                $documentName = $_POST['document_name_' . $documentIndex] ?? 'document_' . $documentIndex;
                
                // Générer un nom de fichier unique
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'doc_' . $_SESSION['user_id'] . '_' . time() . '_' . $documentIndex . '.' . $extension;
                $filePath = $uploadDir . $fileName;
                
                // Déplacer le fichier
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $documents[] = [
                        'nom' => $documentName,
                        'fichier' => 'uploads/demandes/' . $fileName,
                        'taille' => $file['size'],
                        'type' => $file['type']
                    ];
                } else {
                    error_log("Erreur upload fichier: " . $file['name']);
                }
                
                $documentIndex++;
            }
        }
        
        // Vérifier que tous les documents requis ont été fournis
        $expectedDocs = [];
        for ($i = 0; isset($_POST["document_name_$i"]); $i++) {
            $expectedDocs[] = $_POST["document_name_$i"];
        }
        
        if (count($expectedDocs) > 0 && count($documents) < count($expectedDocs)) {
            sendJSONResponse(false, null, 'Tous les documents requis doivent être fournis', 400);
        }
        
    } else {
        // Traitement JSON (sans fichiers - pour compatibilité)
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            sendJSONResponse(false, null, 'Données invalides', 400);
        }
        
        $type = $data['type'] ?? null;
        $service = $data['service'] ?? null;
        $motif = $data['motif'] ?? null;
        $cout = isset($data['cout']) ? floatval($data['cout']) : null;
        $documents = [];
    }
    
    $required = ['type', 'service', 'cout'];
    $fields = ['type' => $type, 'service' => $service, 'cout' => $cout];
    foreach ($required as $field) {
        if (!isset($fields[$field]) || $fields[$field] === null) {
            sendJSONResponse(false, null, "Champ manquant: $field", 400);
        }
    }
    
    try {
        // Préparer les données pour Supabase
        $demandeData = [
            'utilisateur_id' => $_SESSION['user_id'],
            'type' => $type,
            'service' => $service,
            'motif' => $motif,
            'montant' => $cout,
            'statut' => 'en_attente'
        ];
        
        // Ajouter les documents en JSON si présents
        if (!empty($documents)) {
            $demandeData['documents'] = json_encode($documents, JSON_UNESCAPED_UNICODE);
        }
        
        // Ne pas assigner automatiquement - le manager assignera
        // Les demandes arrivent d'abord chez le manager
        $demandeData['agent_assigné_id'] = null;
        
        // Créer la demande dans Supabase
        $result = supabaseCall('demandes', 'POST', $demandeData);
        
        if (!$result['success'] || empty($result['data'])) {
            error_log("Erreur création demande Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la création de la demande', 500);
        }
        
        $demande = $result['data'][0];
        
        // Décoder les documents JSON pour la réponse
        if (isset($demande['documents']) && $demande['documents']) {
            if (is_string($demande['documents'])) {
                $demande['documents'] = json_decode($demande['documents'], true);
            }
        }
        
        sendJSONResponse(true, $demande, 'Demande créée avec succès' . (count($documents) > 0 ? ' et documents uploadés' : ''));
        
    } catch (Exception $e) {
        error_log("Erreur création demande: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur: ' . $e->getMessage(), 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Mettre à jour le statut d'une demande (pour les agents)
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['agent', 'manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['statut'])) {
        sendJSONResponse(false, null, 'Champs manquants', 400);
    }
    
    try {
        $updateData = [];
        
        // Mettre à jour le statut si fourni
        if (isset($data['statut'])) {
            $updateData['statut'] = $data['statut'];
            if ($data['statut'] === 'valide') {
                $updateData['date_validation'] = date('Y-m-d H:i:s');
            }
            if ($data['statut'] === 'dossier_incomplet') {
                $updateData['date_modification'] = date('Y-m-d H:i:s');
            }
        }
        
        // Mettre à jour l'agent assigné si fourni (pour le manager)
        if (isset($data['agent_assigné_id'])) {
            $updateData['agent_assigné_id'] = intval($data['agent_assigné_id']);
        }
        
        // Mettre à jour le commentaire si fourni
        if (isset($data['commentaire_agent']) && !empty($data['commentaire_agent'])) {
            $updateData['commentaire_agent'] = trim($data['commentaire_agent']);
        }
        
        if (empty($updateData)) {
            sendJSONResponse(false, null, 'Aucune donnée à mettre à jour', 400);
        }
        
        $result = supabaseCall('demandes', 'PATCH', $updateData, ['id' => $data['id']]);
        
        if ($result['success']) {
            sendJSONResponse(true, $result['data'][0] ?? null, 'Statut mis à jour');
        } else {
            sendJSONResponse(false, null, 'Erreur lors de la mise à jour', 500);
        }
        
    } catch (Exception $e) {
        error_log("Erreur mise à jour demande: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
