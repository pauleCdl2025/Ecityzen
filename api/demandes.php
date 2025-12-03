<?php
/**
 * API des demandes administratives avec Supabase
 * GET: /api/demandes.php - Liste des demandes
 * POST: /api/demandes.php - Créer une demande
 */

require_once '../config/supabase.php';

setupCORS('GET, POST, PUT, OPTIONS');
startSecureSession();

/**
 * Fonction pour obtenir les documents requis pour un service donné
 * Retourne un tableau avec les informations des documents requis (nom, format, tailleMax)
 */
function getDocumentsRequis($type, $service) {
    // Structure des documents requis (identique au frontend)
    $documentsRequis = [
        'etat-civil' => [
            'Acte de Naissance' => [
                ['nom' => 'Pièce d\'identité', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Photocopie pièce d\'identité', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Acte de Décès' => [
                ['nom' => 'Certificat médical de décès', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Pièce d\'identité du défunt', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Acte de naissance du défunt', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Attestation / Certificat de Retraite' => [
                ['nom' => 'Bulletin de pension', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Pièce d\'identité', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Justificatifs de services', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 10]
            ],
            'Certificat de Résidence' => [
                ['nom' => 'Justificatif de domicile', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Facture récente', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Pièce d\'identité', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Autres démarches (Attestation de vie, légalisation, changement d\'état civil)' => [
                ['nom' => 'Documents originaux à légaliser', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 10],
                ['nom' => 'Acte de naissance', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Pièce d\'identité', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ]
        ],
        'cadastre-urbanisme' => [
            'Permis de Construire' => [
                ['nom' => 'Plans architecturaux visés', 'format' => ['PDF'], 'tailleMax' => 20],
                ['nom' => 'Étude géotechnique', 'format' => ['PDF'], 'tailleMax' => 10],
                ['nom' => 'Certificat de propriété', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Permis de Démolir' => [
                ['nom' => 'Rapport technique', 'format' => ['PDF'], 'tailleMax' => 10],
                ['nom' => 'Étude d\'impact', 'format' => ['PDF'], 'tailleMax' => 10],
                ['nom' => 'Plans de démolition', 'format' => ['PDF'], 'tailleMax' => 20]
            ],
            'ODDC (Occupation du Domaine Communal)' => [
                ['nom' => 'Plan d\'implantation', 'format' => ['PDF'], 'tailleMax' => 10],
                ['nom' => 'Description des activités', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5],
                ['nom' => 'Attestation d\'assurance', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ]
        ],
        'service-social' => [
            'Demande d\'aide solidaire individuelle' => [
                ['nom' => 'Lettre motivée', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5],
                ['nom' => 'Pièce d\'identité', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Justificatifs de revenus', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Demande d\'aide solidaire collective' => [
                ['nom' => 'Lettre des représentants', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5],
                ['nom' => 'Liste des bénéficiaires', 'format' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX'], 'tailleMax' => 5],
                ['nom' => 'Budget prévisionnel', 'format' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX'], 'tailleMax' => 5]
            ],
            'Accompagnement social d\'urgence' => [
                ['nom' => 'Rapport d\'assistante sociale', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5],
                ['nom' => 'Pièce d\'identité', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Justificatifs d\'urgence', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ]
        ],
        'voiries' => [
            'Contrôles et visites quotidiennes' => [
                ['nom' => 'Planning de tournée', 'format' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX'], 'tailleMax' => 5],
                ['nom' => 'Liste des axes à inspecter', 'format' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX'], 'tailleMax' => 5]
            ],
            'Actions ponctuelles planifiées' => [
                ['nom' => 'Ordre de mission', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5],
                ['nom' => 'Fiches techniques des travaux', 'format' => ['PDF'], 'tailleMax' => 10]
            ],
            'Interventions sur interpellation/dénonciation' => [
                ['nom' => 'Lettre d\'interpellation / signalement citoyen', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5],
                ['nom' => 'Coordonnées du site', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 2]
            ]
        ],
        'finances' => [
            'Gestion du transport municipal' => [
                ['nom' => 'Planning des navettes', 'format' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX'], 'tailleMax' => 5],
                ['nom' => 'Engagement de dépense', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5],
                ['nom' => 'Liste du personnel', 'format' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX'], 'tailleMax' => 5]
            ],
            'Traitement de la paie des agents' => [
                ['nom' => 'États de présence', 'format' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX'], 'tailleMax' => 5],
                ['nom' => 'Fiche agent', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5],
                ['nom' => 'Relevés bancaires', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Gestion des régies municipales' => [
                ['nom' => 'Registres des régies', 'format' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX'], 'tailleMax' => 10],
                ['nom' => 'Rapports de caisse', 'format' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX'], 'tailleMax' => 5],
                ['nom' => 'Pièces justificatives', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 10]
            ],
            'Ordres de recettes' => [
                ['nom' => 'Pièces comptables', 'format' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX'], 'tailleMax' => 10],
                ['nom' => 'Engagement budgétaire', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5],
                ['nom' => 'Visa du contrôleur', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Traitement des fournisseurs' => [
                ['nom' => 'Bon de commande', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5],
                ['nom' => 'Facture', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Certificat de service fait', 'format' => ['PDF', 'DOC', 'DOCX'], 'tailleMax' => 5]
            ]
        ],
        'hopital' => [
            'Acte de Naissance (Déclaration)' => [
                ['nom' => 'Fiche de naissance remplie', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Pièce d\'identité parents', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Acte de mariage (si mariés)', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Acte de Décès (Déclaration)' => [
                ['nom' => 'Certificat médical de décès', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Pièce d\'identité défunt', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Acte de naissance défunt', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Certificat médical de naissance' => [
                ['nom' => 'Rapport médical de naissance signé par médecin', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Certificat médical de décès' => [
                ['nom' => 'Rapport médical de décès signé par médecin', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ],
            'Attestation d\'hospitalisation' => [
                ['nom' => 'Justificatif d\'hospitalisation', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5],
                ['nom' => 'Pièce d\'identité patient', 'format' => ['PDF', 'JPG', 'PNG'], 'tailleMax' => 5]
            ]
        ]
    ];
    
    if (isset($documentsRequis[$type][$service])) {
        return $documentsRequis[$type][$service];
    }
    
    return [];
}

/**
 * Valide un fichier uploadé selon les critères du document requis
 */
function validerDocument($file, $documentRequis) {
    $errors = [];
    
    // Vérifier que le fichier existe
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Erreur lors de l\'upload du fichier';
        return $errors;
    }
    
    // Vérifier la taille minimale (les documents officiels ont généralement une taille minimale)
    $minSize = 50 * 1024; // 50 KB minimum
    if ($file['size'] < $minSize) {
        $fileSizeKB = round($file['size'] / 1024, 2);
        $errors[] = "Fichier trop petit pour '{$documentRequis['nom']}' ({$fileSizeKB} KB). Les documents officiels sont généralement plus volumineux. Veuillez vérifier que c'est bien le bon document.";
    }
    
    // Vérifier le format
    $extension = strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION));
    $formatMap = [
        'PDF' => 'PDF',
        'JPG' => 'JPG',
        'JPEG' => 'JPG',
        'PNG' => 'PNG',
        'DOC' => 'DOC',
        'DOCX' => 'DOCX',
        'XLS' => 'XLS',
        'XLSX' => 'XLSX'
    ];
    
    $fileFormat = isset($formatMap[$extension]) ? $formatMap[$extension] : $extension;
    
    if (!in_array($fileFormat, $documentRequis['format'])) {
        $errors[] = "Format non accepté pour '{$documentRequis['nom']}'. Formats acceptés: " . implode(', ', $documentRequis['format']);
    }
    
    // Vérifier la taille maximale
    $maxSize = $documentRequis['tailleMax'] * 1024 * 1024; // Convertir en bytes
    if ($file['size'] > $maxSize) {
        $fileSizeMB = round($file['size'] / 1024 / 1024, 2);
        $errors[] = "Taille trop grande pour '{$documentRequis['nom']}' ({$fileSizeMB} Mo). Taille maximale: {$documentRequis['tailleMax']} Mo";
    }
    
    // Validation supplémentaire pour les images : vérifier les dimensions
    if (in_array($fileFormat, ['JPG', 'PNG'])) {
        $imageInfo = @getimagesize($file['tmp_name']);
        
        if ($imageInfo === false) {
            $errors[] = "Le fichier n'est pas une image valide pour '{$documentRequis['nom']}'";
        } else {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $aspectRatio = $width / $height;
            
            $documentType = strtolower($documentRequis['nom']);
            
            // Validation pour les pièces d'identité
            if (strpos($documentType, 'pièce d\'identité') !== false || 
                strpos($documentType, 'identité') !== false || 
                strpos($documentType, 'carte') !== false) {
                
                // Pièce d'identité : format rectangulaire (ratio entre 1.2 et 2.5)
                if ($aspectRatio < 1.2 || $aspectRatio > 2.5) {
                    $errors[] = "Format d'image suspect pour une pièce d'identité (ratio {$aspectRatio}). Les pièces d'identité ont généralement un format rectangulaire. Veuillez vérifier que c'est bien le bon document.";
                }
                
                // Dimensions minimales pour une pièce d'identité
                if ($width < 300 || $height < 200) {
                    $errors[] = "Image trop petite pour une pièce d'identité ({$width}x{$height}px). Dimensions minimales requises: 300x200px. Veuillez vérifier que c'est bien le bon document.";
                }
            } 
            // Validation pour les actes et certificats
            elseif (strpos($documentType, 'acte') !== false || 
                    strpos($documentType, 'certificat') !== false) {
                
                // Actes et certificats : format généralement rectangulaire vertical ou carré
                if ($aspectRatio < 0.4 || $aspectRatio > 2.5) {
                    $errors[] = "Format d'image suspect pour un acte/certificat (ratio {$aspectRatio}). Veuillez vérifier que c'est bien le bon document.";
                }
            }
        }
    }
    
    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupérer les demandes
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    
    // Si un ID spécifique est demandé, récupérer seulement cette demande (optimisation)
    $demandeId = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    try {
        if ($demandeId) {
            // Récupérer une seule demande par ID (beaucoup plus rapide)
            $result = supabaseCall('demandes', 'GET', null, ['id' => $demandeId]);
            $demandes = $result['success'] && !empty($result['data']) ? $result['data'] : [];
            
            // Vérifier les permissions
            if (!empty($demandes)) {
                $demande = $demandes[0];
                // Les agents/managers peuvent voir toutes les demandes
                if (!in_array($role, ['agent', 'manager', 'superadmin'])) {
                    // Les autres utilisateurs voient seulement leurs demandes
                    if (!$userId || $demande['utilisateur_id'] != $userId) {
                        sendJSONResponse(false, null, 'Non autorisé', 403);
                    }
                }
            }
        } elseif ($role === 'agent' || $role === 'manager' || $role === 'superadmin') {
            // Optimisation : si un agent_id est spécifié, filtrer directement
            $agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : null;
            
            if ($agentId && $role === 'agent') {
                // Agent : charger seulement ses demandes assignées (beaucoup plus rapide)
                $result = supabaseCall('demandes', 'GET', null, ['agent_assigné_id' => $agentId], [
                    'order' => ['date_creation' => 'desc'],
                    'limit' => 100
                ]);
            } else {
                // Manager/Superadmin ou agent sans filtre : voir toutes les demandes
            $result = supabaseCall('demandes', 'GET', null, [], [
                'order' => ['date_creation' => 'desc'],
                'limit' => 50
            ]);
            }
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
        
        // Récupérer les documents requis pour ce service
        $documentsRequis = getDocumentsRequis($type, $service);
        
        if (empty($documentsRequis)) {
            sendJSONResponse(false, null, 'Service non trouvé ou documents requis non définis', 400);
        }
        
        // Vérifier que tous les documents requis ont été fournis
        $expectedDocs = [];
        for ($i = 0; isset($_POST["document_name_$i"]); $i++) {
            $expectedDocs[] = $_POST["document_name_$i"];
        }
        
        if (count($expectedDocs) !== count($documentsRequis)) {
            sendJSONResponse(false, null, 'Nombre de documents incorrect. Documents requis: ' . count($documentsRequis) . ', documents fournis: ' . count($expectedDocs), 400);
        }
        
        // Créer le dossier d'upload s'il n'existe pas
        $uploadDir = '../uploads/demandes/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Initialiser le tableau des documents dans l'ordre
        $documents = array_fill(0, count($documentsRequis), null);
        $allErrors = [];
        
        // Vérifier d'abord que tous les documents requis sont présents
        for ($i = 0; $i < count($documentsRequis); $i++) {
            if (!isset($_POST["document_name_$i"])) {
                $allErrors[] = "Document manquant à la position " . ($i + 1) . ". Document requis: '{$documentsRequis[$i]['nom']}'";
            }
        }
        
        // Traiter et valider tous les fichiers uploadés dans l'ordre strict
        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'document_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
                // Extraire l'index du nom de la clé (document_0, document_1, etc.)
                $fileIndex = intval(str_replace('document_', '', $key));
                $documentName = $_POST['document_name_' . $fileIndex] ?? null;
                
                // Vérifier que l'index est valide
                if ($fileIndex < 0 || $fileIndex >= count($documentsRequis)) {
                    $allErrors[] = "Index de document invalide: {$fileIndex}. Nombre de documents requis: " . count($documentsRequis);
                    continue;
                }
                
                $documentRequis = $documentsRequis[$fileIndex];
                
                // Vérification STRICTE: le document doit correspondre exactement au document requis à cet index
                if (!$documentName || $documentRequis['nom'] !== $documentName) {
                    $allErrors[] = "Document incorrect à la position " . ($fileIndex + 1) . ". Document requis: '{$documentRequis['nom']}', document fourni: '" . ($documentName ?? 'non spécifié') . "'. Veuillez vous assurer que vous avez mis le bon document dans le bon champ.";
                    continue;
                }
                
                // Valider le format et la taille
                $validationErrors = validerDocument($file, $documentRequis);
                if (!empty($validationErrors)) {
                    $allErrors = array_merge($allErrors, $validationErrors);
                    continue;
                }
                
                // Générer un nom de fichier unique
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'doc_' . $_SESSION['user_id'] . '_' . time() . '_' . $fileIndex . '.' . $extension;
                $filePath = $uploadDir . $fileName;
                
                // Déplacer le fichier
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $documents[$fileIndex] = [
                        'nom' => $documentName,
                        'fichier' => 'uploads/demandes/' . $fileName,
                        'taille' => $file['size'],
                        'type' => $file['type']
                    ];
                } else {
                    $allErrors[] = "Erreur lors de l'upload du fichier: {$file['name']}";
                    error_log("Erreur upload fichier: " . $file['name']);
                }
            }
        }
        
        // Si des erreurs de validation ont été détectées, retourner une erreur
        if (!empty($allErrors)) {
            sendJSONResponse(false, ['errors' => $allErrors], 'Documents non conformes: ' . implode('; ', $allErrors), 400);
        }
        
        // Vérification STRICTE: tous les documents doivent être présents dans le bon ordre
        $missing = [];
        for ($i = 0; $i < count($documentsRequis); $i++) {
            if (!isset($documents[$i]) || $documents[$i] === null) {
                $missing[] = "Position " . ($i + 1) . ": '{$documentsRequis[$i]['nom']}'";
            } elseif ($documents[$i]['nom'] !== $documentsRequis[$i]['nom']) {
                $allErrors[] = "Document incorrect à la position " . ($i + 1) . ". Attendu: '{$documentsRequis[$i]['nom']}', Reçu: '{$documents[$i]['nom']}'. Veuillez vous assurer que chaque document est dans le bon champ.";
            }
        }
        
        if (!empty($missing)) {
            sendJSONResponse(false, null, 'Documents manquants: ' . implode(', ', $missing), 400);
        }
        
        // Filtrer les valeurs null et réindexer
        $documents = array_values(array_filter($documents, function($doc) {
            return $doc !== null;
        }));
        
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
