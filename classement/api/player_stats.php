<?php
require_once '../config.php';
require_once '../GestionJoueurs.php';
require_once '../SeasonManager.php';
require_once '../RiotAPI.php';

header('Content-Type: application/json');

$riotAPI = new RiotAPI(RIOT_API_KEY, 'euw1');
$seasonManager = new SeasonManager($pdo);
$gestion = new GestionJoueurs($pdo, $riotAPI, $seasonManager);

$playerId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$refresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';

try {
    if ($refresh) {
        // Synchroniser les données du joueur
        $gestion->synchroniserJoueur($playerId);
    }
    
    // Récupérer les statistiques
    $stats = $gestion->getPlayerStats($playerId);
    
    if (!$stats) {
        http_response_code(404);
        echo json_encode(['error' => 'Joueur non trouvé']);
        exit;
    }
    
    echo json_encode($stats);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 