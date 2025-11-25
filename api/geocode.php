<?php
/**
 * API de géocodage inverse
 * GET: /api/geocode.php?lat=X&lng=Y - Obtenir l'adresse complète depuis les coordonnées GPS
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
    $lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
    
    if (!$lat || !$lng) {
        echo json_encode([
            'success' => false,
            'message' => 'Coordonnées GPS requises'
        ]);
        exit;
    }
    
    try {
        // Utiliser Nominatim pour le géocodage inverse
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'e-cityzen-gabon/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['address'])) {
                $address = $data['address'];
                
                // Extraire les informations pertinentes
                $result = [
                    'success' => true,
                    'data' => [
                        'display_name' => $data['display_name'] ?? '',
                        'quartier' => $address['suburb'] ?? $address['neighbourhood'] ?? $address['quarter'] ?? '',
                        'ville' => $address['city'] ?? $address['town'] ?? $address['municipality'] ?? 'Libreville',
                        'arrondissement' => $address['city_district'] ?? $address['district'] ?? '',
                        'province' => $address['state'] ?? $address['region'] ?? 'Estuaire',
                        'pays' => $address['country'] ?? 'Gabon',
                        'code_postal' => $address['postcode'] ?? '',
                        'rue' => $address['road'] ?? $address['street'] ?? '',
                        'numero' => $address['house_number'] ?? '',
                        'coordonnees' => [
                            'lat' => $lat,
                            'lng' => $lng
                        ]
                    ]
                ];
                
                // Construire l'adresse complète formatée
                $adresseComplete = [];
                if (!empty($result['data']['rue'])) {
                    if (!empty($result['data']['numero'])) {
                        $adresseComplete[] = $result['data']['numero'] . ' ' . $result['data']['rue'];
                    } else {
                        $adresseComplete[] = $result['data']['rue'];
                    }
                }
                if (!empty($result['data']['quartier'])) {
                    $adresseComplete[] = 'Quartier ' . $result['data']['quartier'];
                }
                if (!empty($result['data']['arrondissement'])) {
                    $adresseComplete[] = $result['data']['arrondissement'];
                }
                if (!empty($result['data']['ville'])) {
                    $adresseComplete[] = $result['data']['ville'];
                }
                if (!empty($result['data']['province'])) {
                    $adresseComplete[] = $result['data']['province'];
                }
                
                $result['data']['adresse_complete'] = implode(', ', $adresseComplete);
                if (empty($result['data']['adresse_complete'])) {
                    $result['data']['adresse_complete'] = $result['data']['display_name'];
                }
                
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Adresse non trouvée'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'adresse'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur serveur: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
}

