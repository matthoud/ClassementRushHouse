<?php
// Ajouter ces lignes au début du fichier pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoriser l'accès depuis le même domaine
header('Access-Control-Allow-Origin: https://mhoudin.fr');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../RiotAPI.php';
require_once __DIR__ . '/../GestionJoueurs.php';

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
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
