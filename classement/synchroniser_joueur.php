<?php
require_once 'config.php';
require_once 'GestionJoueurs.php';

header('Content-Type: application/json');

try {
    $playerId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $source = isset($_GET['source']) ? $_GET['source'] : 'unknown';
    
    if (!$playerId) {
        throw new Exception("ID du joueur non spécifié");
    }

    error_log("Début de la synchronisation pour le joueur ID: $playerId depuis $source");
    
    $gestionJoueurs = new GestionJoueurs($pdo);
    $result = $gestionJoueurs->synchroniserJoueur($playerId, $source);
    
    error_log("Résultat de la synchronisation: " . json_encode($result));

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Erreur dans synchroniser_joueur.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 