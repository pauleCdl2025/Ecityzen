<?php
/**
 * API d'inscription
 * POST: /api/register.php
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Validation des données
if (!isset($data['nom']) || empty(trim($data['nom']))) {
    sendJSONResponse(false, null, 'Le nom est requis', 400);
}

if (!isset($data['telephone']) || empty(trim($data['telephone']))) {
    sendJSONResponse(false, null, 'Le numéro de téléphone est requis', 400);
}

if (!isset($data['mot_de_passe']) || empty(trim($data['mot_de_passe']))) {
    sendJSONResponse(false, null, 'Le mot de passe est requis', 400);
}

if (strlen($data['mot_de_passe']) < 6) {
    sendJSONResponse(false, null, 'Le mot de passe doit contenir au moins 6 caractères', 400);
}

if (!isset($data['role']) || !in_array($data['role'], ['citoyen', 'commercant', 'hopital', 'agent', 'manager', 'superadmin'])) {
    sendJSONResponse(false, null, 'Rôle invalide', 400);
}

// Rôles qui nécessitent une validation (seuls les superadmins peuvent créer des agents/managers/hôpitaux)
if (in_array($data['role'], ['hopital', 'agent', 'manager', 'superadmin'])) {
    // Pour l'instant, on autorise la création mais on peut ajouter une vérification de session admin ici
    // En production, vérifiez que l'utilisateur connecté est un superadmin
}

try {
    // Vérifier si le téléphone existe déjà
    $checkResult = supabaseCall('utilisateurs', 'GET', null, ['telephone' => trim($data['telephone'])]);
    
    if ($checkResult['success'] && !empty($checkResult['data'])) {
        sendJSONResponse(false, null, 'Ce numéro de téléphone est déjà utilisé', 409);
    }
    
    // Hasher le mot de passe
    $mot_de_passe_hash = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);
    
    // Préparer les données
    $nom = trim($data['nom']);
    $telephone = trim($data['telephone']);
    $role = $data['role'];
    $localisation = isset($data['localisation']) ? trim($data['localisation']) : null;
    $latitude = isset($data['latitude']) && is_numeric($data['latitude']) ? floatval($data['latitude']) : null;
    $longitude = isset($data['longitude']) && is_numeric($data['longitude']) ? floatval($data['longitude']) : null;
    $secteur = isset($data['secteur']) ? trim($data['secteur']) : null;
    $entreprise = isset($data['entreprise']) ? trim($data['entreprise']) : null;
    
    // Générer un email factice basé sur le téléphone pour la compatibilité avec la base de données
    $email = 'user_' . preg_replace('/[^0-9]/', '', $telephone) . '@ecityzen.ga';
    
    // Préparer les données pour insertion
    $userData = [
        'nom' => $nom,
        'email' => $email,
        'telephone' => $telephone,
        'role' => $role,
        'mot_de_passe' => $mot_de_passe_hash,
        'statut' => 'actif'
    ];
    
    if ($localisation) $userData['localisation'] = $localisation;
    if ($latitude !== null) $userData['latitude'] = $latitude;
    if ($longitude !== null) $userData['longitude'] = $longitude;
    if ($secteur) $userData['secteur'] = $secteur;
    if ($entreprise) $userData['entreprise'] = $entreprise;
    
    // Insérer l'utilisateur dans Supabase
    $result = supabaseCall('utilisateurs', 'POST', $userData);
    
    if (!$result['success'] || empty($result['data'])) {
        error_log("Erreur création utilisateur Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
        sendJSONResponse(false, null, 'Erreur lors de la création du compte', 500);
    }
    
    $user = $result['data'][0];
    
    // Démarrer la session
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_nom'] = $user['nom'];
    
    // Préparer les données utilisateur pour le frontend
    $userResponse = [
        'id' => $user['id'],
        'name' => $user['nom'],
        'telephone' => $user['telephone'],
        'email' => $user['email'] ?? null,
        'role' => $user['role'],
        'location' => $user['localisation'] ?? null,
        'sector' => $user['secteur'] ?? null,
        'business' => $user['entreprise'] ?? null
    ];
    
    sendJSONResponse(true, $userResponse, 'Compte créé avec succès');
    
} catch (Exception $e) {
    error_log("Erreur inscription: " . $e->getMessage());
    sendJSONResponse(false, null, 'Erreur serveur', 500);
}

