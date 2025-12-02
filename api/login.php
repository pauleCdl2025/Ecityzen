<?php
/**
 * API de connexion
 * POST: /api/login.php
 */

// Utiliser Supabase au lieu de MySQL
require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}

// BLOQUER TOUTES LES CONNEXIONS TEMPORAIREMENT
sendJSONResponse(false, null, 'Les connexions sont temporairement désactivées. Veuillez réessayer plus tard.', 503);

$data = json_decode(file_get_contents('php://input'), true);

// Validation des données
if (!isset($data['telephone']) || empty(trim($data['telephone']))) {
    sendJSONResponse(false, null, 'Le numéro de téléphone est requis', 400);
}

if (!isset($data['mot_de_passe']) || empty(trim($data['mot_de_passe']))) {
    sendJSONResponse(false, null, 'Le mot de passe est requis', 400);
}

$telephone = trim($data['telephone']);
$mot_de_passe = $data['mot_de_passe'];

try {
    // Récupérer l'utilisateur par téléphone depuis Supabase
    $result = supabaseCall('utilisateurs', 'GET', null, ['telephone' => $telephone]);
    
    if (!$result['success'] || empty($result['data'])) {
        sendJSONResponse(false, null, 'Numéro de téléphone ou mot de passe incorrect', 401);
    }
    
    $user = $result['data'][0];
    
    // Vérifier le statut
    if ($user['statut'] !== 'actif') {
        if ($user['statut'] === 'en_attente' && $user['role'] === 'agent') {
            sendJSONResponse(false, null, 'Votre demande d\'inscription est en attente de validation par un manager', 403);
        } else {
            sendJSONResponse(false, null, 'Votre compte est désactivé', 403);
        }
    }
    
    // Vérifier le mot de passe
    if (!password_verify($mot_de_passe, $user['mot_de_passe'])) {
        sendJSONResponse(false, null, 'Numéro de téléphone ou mot de passe incorrect', 401);
    }
    
    // Mettre à jour la dernière connexion
    supabaseCall('utilisateurs', 'PATCH', [
        'derniere_connexion' => date('Y-m-d H:i:s')
    ], ['id' => $user['id']]);
    
    // Démarrer la session
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_nom'] = $user['nom'];
    
    // Préparer les données utilisateur pour le frontend
    $userData = [
        'id' => $user['id'],
        'name' => $user['nom'],
        'telephone' => $user['telephone'],
        'email' => $user['email'] ?? null,
        'role' => $user['role'],
        'location' => $user['localisation'] ?? null,
        'sector' => $user['secteur'] ?? null,
        'business' => $user['entreprise'] ?? null
    ];
    
    sendJSONResponse(true, $userData, 'Connexion réussie');
    
} catch (Exception $e) {
    error_log("Erreur login: " . $e->getMessage());
    sendJSONResponse(false, null, 'Erreur serveur', 500);
}

