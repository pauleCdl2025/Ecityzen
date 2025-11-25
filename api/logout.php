<?php
/**
 * API de déconnexion
 * POST: /api/logout.php
 */

require_once '../config/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

session_start();

// Détruire la session
$_SESSION = [];
session_destroy();

sendJSONResponse(true, null, 'Déconnexion réussie');
