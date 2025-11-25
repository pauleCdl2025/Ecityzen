<?php
/**
 * API des statistiques avec Supabase
 * GET: /api/stats.php - Récupérer les statistiques
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(false, null, 'Méthode non autorisée', 405);
}

$role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$userId || !in_array($role, ['manager', 'superadmin'])) {
    sendJSONResponse(false, null, 'Non autorisé', 401);
}

try {
    $stats = [];
    $currentMonth = date('Y-m');
    
    // Statistiques demandes
    try {
        $result = supabaseCall('demandes', 'GET');
        $allDemandes = $result['success'] ? $result['data'] : [];
        
        // Demandes ce mois
        $demandesMois = array_filter($allDemandes, function($d) use ($currentMonth) {
            return isset($d['date_creation']) && strpos($d['date_creation'], $currentMonth) === 0;
        });
        $stats['demandes_mois'] = count($demandesMois);
        
        // Demandes validées
        $demandesValidees = array_filter($allDemandes, function($d) {
            return isset($d['statut']) && $d['statut'] === 'valide';
        });
        $stats['demandes_validees'] = count($demandesValidees);
        
        // Calcul délai moyen
        $delais = [];
        foreach ($allDemandes as $d) {
            if (isset($d['date_validation']) && isset($d['date_creation'])) {
                $dateCreation = new DateTime($d['date_creation']);
                $dateValidation = new DateTime($d['date_validation']);
                $delais[] = $dateCreation->diff($dateValidation)->days;
            }
        }
        $stats['delai_moyen'] = !empty($delais) ? round(array_sum($delais) / count($delais), 1) : 0;
        
        // Taux de satisfaction
        $totalDemandes = count($allDemandes);
        $stats['taux_satisfaction'] = $totalDemandes > 0 ? round((count($demandesValidees) / $totalDemandes) * 100, 1) : 0;
        
    } catch (Exception $e) {
        $stats['demandes_mois'] = 0;
        $stats['demandes_validees'] = 0;
        $stats['delai_moyen'] = 0;
        $stats['taux_satisfaction'] = 0;
    }
    
    // Statistiques paiements
    try {
        $result = supabaseCall('paiements', 'GET', null, ['statut' => 'confirme']);
        $paiementsConfirmes = $result['success'] ? $result['data'] : [];
        
        // Recettes ce mois
        $recettesMois = 0;
        foreach ($paiementsConfirmes as $p) {
            if (isset($p['date_paiement']) && strpos($p['date_paiement'], $currentMonth) === 0) {
                $recettesMois += floatval($p['montant'] ?? 0);
            }
        }
        $stats['recettes_mois'] = $recettesMois;
        
    } catch (Exception $e) {
        $stats['recettes_mois'] = 0;
    }
    
    // Statistiques utilisateurs
    try {
        $result = supabaseCall('utilisateurs', 'GET', null, ['statut' => 'actif']);
        $stats['utilisateurs_actifs'] = $result['success'] ? count($result['data']) : 0;
        
        // Agents actifs
        $agentsResult = supabaseCall('utilisateurs', 'GET', null, ['role' => 'agent', 'statut' => 'actif']);
        $agents = $agentsResult['success'] ? $agentsResult['data'] : [];
        $stats['agents_actifs'] = count($agents);
        
        // Performance des agents (basée sur les demandes validées)
        $stats['agents_performance'] = [];
        foreach ($agents as $agent) {
            $demandesAgent = supabaseCall('demandes', 'GET', null, ['agent_assigné_id' => $agent['id'], 'statut' => 'valide']);
            $count = $demandesAgent['success'] ? count($demandesAgent['data']) : 0;
            if ($count > 0) {
                $stats['agents_performance'][] = [
                    'id' => $agent['id'],
                    'nom' => $agent['nom'],
                    'demandes_validees' => $count
                ];
            }
        }
        // Trier par performance décroissante
        usort($stats['agents_performance'], function($a, $b) {
            return $b['demandes_validees'] - $a['demandes_validees'];
        });
        $stats['agents_performance'] = array_slice($stats['agents_performance'], 0, 10);
        
    } catch (Exception $e) {
        $stats['utilisateurs_actifs'] = 0;
        $stats['agents_actifs'] = 0;
        $stats['agents_performance'] = [];
    }
    
    // Statistiques signalements
    try {
        $result = supabaseCall('signalements', 'GET', null, ['statut' => 'en_attente']);
        $stats['signalements_en_attente'] = $result['success'] ? count($result['data']) : 0;
        
    } catch (Exception $e) {
        $stats['signalements_en_attente'] = 0;
    }
    
    // Statistiques emplacements marché
    try {
        $result = supabaseCall('emplacements_marche', 'GET', null, ['statut' => 'actif']);
        $emplacementsActifs = $result['success'] ? $result['data'] : [];
        $stats['emplacements_occupes'] = count($emplacementsActifs);
        
        // Total emplacements
        $allResult = supabaseCall('emplacements_marche', 'GET');
        $totalEmplacements = $allResult['success'] ? count($allResult['data']) : 0;
        $stats['taux_occupation'] = $totalEmplacements > 0 ? round((count($emplacementsActifs) / $totalEmplacements) * 100, 1) : 0;
        
    } catch (Exception $e) {
        $stats['emplacements_occupes'] = 0;
        $stats['taux_occupation'] = 0;
        $stats['recettes_marches'] = 0;
    }
    
    sendJSONResponse(true, $stats, 'Statistiques récupérées');
    
} catch (Exception $e) {
    error_log("Erreur récupération stats: " . $e->getMessage());
    // Retourner des stats vides plutôt qu'une erreur
    sendJSONResponse(true, [
        'demandes_mois' => 0,
        'demandes_validees' => 0,
        'delai_moyen' => 0,
        'recettes_mois' => 0,
        'utilisateurs_actifs' => 0,
        'signalements_en_attente' => 0,
        'emplacements_occupes' => 0
    ], 'Statistiques récupérées');
}
