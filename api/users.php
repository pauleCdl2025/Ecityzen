<?php
/**
 * API de gestion des utilisateurs avec Supabase
 * GET: /api/users.php - Liste des utilisateurs (admin/manager)
 * PUT: /api/users.php - Mettre à jour le profil utilisateur
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Vérifier l'authentification
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    // Seuls les managers et superadmins peuvent voir tous les utilisateurs
    if (!in_array($role, ['manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Accès non autorisé', 403);
    }
    
    try {
        $result = supabaseCall('utilisateurs', 'GET', null, [], [
            'order' => ['date_creation' => 'desc']
        ]);
        
        $users = $result['success'] ? $result['data'] : [];
        
        // Formater les données
        foreach ($users as &$user) {
            $user['name'] = $user['nom'];
            unset($user['mot_de_passe']); // Ne jamais envoyer le mot de passe
        }
        
        sendJSONResponse(true, $users, 'Utilisateurs récupérés');
        
    } catch (Exception $e) {
        error_log("Erreur récupération utilisateurs: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Vérifier l'authentification
    if (!isset($_SESSION['user_id'])) {
        sendJSONResponse(false, null, 'Non authentifié', 401);
    }
    
    $userId = $_SESSION['user_id'];
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJSONResponse(false, null, 'Données invalides', 400);
    }
    
    // Si un admin met à jour un autre utilisateur
    if (isset($input['id']) && in_array($role, ['manager', 'superadmin'])) {
        $userId = $input['id'];
    }
    
    try {
        // Vérifier que l'utilisateur existe
        $result = supabaseCall('utilisateurs', 'GET', null, ['id' => $userId]);
        
        if (!$result['success'] || empty($result['data'])) {
            sendJSONResponse(false, null, 'Utilisateur non trouvé', 404);
        }
        
        $user = $result['data'][0];
        
        // Préparer les données de mise à jour
        $updateData = [];
        
        if (isset($input['nom'])) {
            $updateData['nom'] = trim($input['nom']);
        }
        
        if (isset($input['telephone'])) {
            // Vérifier que le téléphone n'est pas déjà utilisé par un autre utilisateur
            $checkResult = supabaseCall('utilisateurs', 'GET', null, ['telephone' => trim($input['telephone'])]);
            if ($checkResult['success'] && !empty($checkResult['data'])) {
                $existingUser = $checkResult['data'][0];
                if ($existingUser['id'] != $userId) {
                    sendJSONResponse(false, null, 'Ce numéro de téléphone est déjà utilisé', 400);
                }
            }
            $updateData['telephone'] = trim($input['telephone']);
        }
        
        if (isset($input['email'])) {
            $updateData['email'] = !empty($input['email']) ? trim($input['email']) : null;
        }
        
        if (isset($input['localisation'])) {
            $updateData['localisation'] = !empty($input['localisation']) ? trim($input['localisation']) : null;
        }
        
        if (isset($input['secteur'])) {
            $updateData['secteur'] = !empty($input['secteur']) ? trim($input['secteur']) : null;
        }
        
        if (isset($input['entreprise'])) {
            $updateData['entreprise'] = !empty($input['entreprise']) ? trim($input['entreprise']) : null;
        }
        
        // Gestion du changement de mot de passe
        if (isset($input['ancien_mot_de_passe']) && isset($input['nouveau_mot_de_passe'])) {
            // Vérifier l'ancien mot de passe
            if (!password_verify($input['ancien_mot_de_passe'], $user['mot_de_passe'])) {
                sendJSONResponse(false, null, 'Ancien mot de passe incorrect', 400);
            }
            
            // Vérifier la longueur du nouveau mot de passe
            if (strlen($input['nouveau_mot_de_passe']) < 6) {
                sendJSONResponse(false, null, 'Le nouveau mot de passe doit contenir au moins 6 caractères', 400);
            }
            
            $updateData['mot_de_passe'] = password_hash($input['nouveau_mot_de_passe'], PASSWORD_DEFAULT);
        }
        
        // Permettre aux admins de changer le statut et le rôle
        if (in_array($role, ['manager', 'superadmin'])) {
            if (isset($input['statut'])) {
                $updateData['statut'] = $input['statut'];
            }
            if (isset($input['role'])) {
                $updateData['role'] = $input['role'];
            }
        }
        
        if (empty($updateData)) {
            sendJSONResponse(false, null, 'Aucune modification à effectuer', 400);
        }
        
        // Mettre à jour l'utilisateur
        $updateResult = supabaseCall('utilisateurs', 'PATCH', $updateData, ['id' => $userId]);
        
        if (!$updateResult['success']) {
            error_log("Erreur mise à jour utilisateur Supabase: " . ($updateResult['error'] ?? 'Erreur inconnue'));
            sendJSONResponse(false, null, 'Erreur lors de la mise à jour', 500);
        }
        
        // Récupérer les données mises à jour
        $getResult = supabaseCall('utilisateurs', 'GET', null, ['id' => $userId]);
        
        if (!$getResult['success'] || empty($getResult['data'])) {
            sendJSONResponse(false, null, 'Erreur lors de la récupération des données mises à jour', 500);
        }
        
        $updatedUser = $getResult['data'][0];
        unset($updatedUser['mot_de_passe']); // Ne jamais envoyer le mot de passe
        
        // Formater pour la compatibilité
        $updatedUser['name'] = $updatedUser['nom'];
        
        // Mettre à jour la session
        $_SESSION['user_nom'] = $updatedUser['nom'];
        if (isset($updateData['role'])) {
            $_SESSION['user_role'] = $updatedUser['role'];
        }
        
        sendJSONResponse(true, $updatedUser, 'Profil mis à jour avec succès');
        
    } catch (Exception $e) {
        error_log("Erreur mise à jour utilisateur: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
}

sendJSONResponse(false, null, 'Méthode non autorisée', 405);
