<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/RiotAPI.php';
require_once __DIR__ . '/GestionJoueurs.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $gestionJoueurs = new GestionJoueurs($pdo, $riotAPI);
    
    if (isset($_GET['id'])) {
        $result = $gestionJoueurs->synchroniserJoueur($_GET['id']);
        echo json_encode($result);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "ID du joueur non fourni"
        ]);
    }
} catch (Exception $e) {
    error_log("Erreur dans sync_player_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 