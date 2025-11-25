<?php
/**
 * API du budget municipal avec Supabase
 * GET: /api/budget.php - Consultation du budget
 * POST: /api/budget.php - Créer/modifier budget (admin)
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    $exercice = isset($_GET['exercice']) ? (int)$_GET['exercice'] : date('Y');
    
    // Consultation réservée aux citoyens inscrits
    if (!$userId || !in_array($role, ['citoyen', 'commercant', 'chef_quartier', 'agent', 'manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Connexion requise pour consulter le budget', 401);
    }
    
    try {
        // Récupérer les postes budgétaires pour l'exercice
        $result = supabaseCall('budget_municipal', 'GET', null, ['exercice_budgetaire' => $exercice], [
            'order' => ['categorie' => 'asc', 'poste_budgetaire' => 'asc']
        ]);
        
        $postes = $result['success'] ? $result['data'] : [];
        
        // Calculer les totaux
        $totalBudget = 0;
        $totalDepenses = 0;
        
        foreach ($postes as &$poste) {
            $budgetInitial = floatval($poste['budget_initial'] ?? 0);
            $budgetRectificatif = floatval($poste['budget_rectificatif'] ?? 0);
            $depensesEngagees = floatval($poste['depenses_engagees'] ?? 0);
            
            $totalBudget += $budgetInitial + $budgetRectificatif;
            $totalDepenses += $depensesEngagees;
            
            // Calculer le taux d'exécution par poste
            $budgetTotal = $budgetInitial + $budgetRectificatif;
            $poste['taux_execution'] = $budgetTotal > 0 ? round(($depensesEngagees / $budgetTotal) * 100, 1) : 0;
        }
        
        $tauxExecutionGlobal = $totalBudget > 0 ? round(($totalDepenses / $totalBudget) * 100, 1) : 0;
        
        sendJSONResponse(true, [
            'exercice' => $exercice,
            'postes' => $postes,
            'total_budget' => $totalBudget,
            'total_depenses' => $totalDepenses,
            'taux_execution_global' => $tauxExecutionGlobal
        ], 'Budget récupéré');
        
    } catch (Exception $e) {
        error_log("Erreur récupération budget: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Créer/modifier budget (admin uniquement)
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'superadmin'])) {
        sendJSONResponse(false, null, 'Non autorisé', 403);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        if (isset($data['id'])) {
            // Mise à jour
            $updateData = [];
            if (isset($data['budget_rectificatif'])) {
                $updateData['budget_rectificatif'] = floatval($data['budget_rectificatif']);
            }
            if (isset($data['depenses_engagees'])) {
                $updateData['depenses_engagees'] = floatval($data['depenses_engagees']);
            }
            
            if (!empty($updateData)) {
                $result = supabaseCall('budget_municipal', 'PATCH', $updateData, ['id' => $data['id']]);
                if ($result['success']) {
                    sendJSONResponse(true, null, 'Budget mis à jour');
                } else {
                    sendJSONResponse(false, null, 'Erreur lors de la mise à jour', 500);
                }
            } else {
                sendJSONResponse(false, null, 'Aucune donnée à mettre à jour', 400);
            }
        } else {
            // Création
            $required = ['exercice_budgetaire', 'poste_budgetaire', 'categorie', 'budget_initial'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    sendJSONResponse(false, null, "Champ manquant: $field", 400);
                }
            }
            
            $budgetData = [
                'exercice_budgetaire' => intval($data['exercice_budgetaire']),
                'poste_budgetaire' => $data['poste_budgetaire'],
                'categorie' => $data['categorie'],
                'budget_initial' => floatval($data['budget_initial']),
                'budget_rectificatif' => isset($data['budget_rectificatif']) ? floatval($data['budget_rectificatif']) : 0,
                'depenses_engagees' => isset($data['depenses_engagees']) ? floatval($data['depenses_engagees']) : 0
            ];
            
            $result = supabaseCall('budget_municipal', 'POST', $budgetData);
            
            if ($result['success'] && !empty($result['data'])) {
                sendJSONResponse(true, ['id' => $result['data'][0]['id']], 'Budget créé');
            } else {
                error_log("Erreur création budget Supabase: " . ($result['error'] ?? 'Erreur inconnue'));
                sendJSONResponse(false, null, 'Erreur lors de la création', 500);
            }
        }
        
    } catch (Exception $e) {
        error_log("Erreur création budget: " . $e->getMessage());
        sendJSONResponse(false, null, 'Erreur serveur', 500);
    }
} else {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}
