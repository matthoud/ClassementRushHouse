<?php
require_once 'config.php';
require_once 'GestionJoueurs.php';
require_once 'SeasonManager.php';
require_once 'RiotAPI.php';

$playerId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$gestionJoueurs = new GestionJoueurs($pdo);

try {
    $stats = $gestionJoueurs->getPlayerStats($playerId);
    echo "<pre>";
    print_r($stats);
    echo "</pre>";
    
    // Vérifier les requêtes SQL
    echo "<h2>Vérification des tables</h2>";
    
    // Vérifier matches
    $result = $pdo->query("SELECT COUNT(*) as count FROM matches WHERE player_id = $playerId");
    $matchCount = $result->fetch()['count'];
    echo "Nombre de matches: $matchCount<br>";
    
    // Vérifier lp_history
    $result = $pdo->query("SELECT COUNT(*) as count FROM lp_history WHERE player_id = $playerId");
    $lpCount = $result->fetch()['count'];
    echo "Nombre d'entrées LP: $lpCount<br>";
    
    // Vérifier joueur
    $result = $pdo->query("SELECT * FROM joueurs WHERE id = $playerId");
    $player = $result->fetch();
    echo "Données joueur:<br>";
    print_r($player);
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
} 