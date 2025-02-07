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
    if (!isset($_GET['id'])) {
        throw new Exception("ID du joueur non fourni");
    }

    $playerId = intval($_GET['id']);
    $riotAPI = new RiotAPI(RIOT_API_KEY, 'euw1');
    $gestionJoueurs = new GestionJoueurs($pdo, $riotAPI);
    
    error_log("Début de la synchronisation pour le joueur ID: " . $playerId);
    
    $result = $gestionJoueurs->synchroniserJoueur($playerId);
    
    error_log("Résultat de la synchronisation: " . json_encode($result));
    
    echo json_encode($result);

} catch (Exception $e) {
    error_log("Erreur dans sync_ranking.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 