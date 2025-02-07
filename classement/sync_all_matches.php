<?php
require_once 'config.php';
require_once 'RiotAPI.php';
require_once 'GestionJoueurs.php';
require_once 'SeasonManager.php';

$riotAPI = new RiotAPI(RIOT_API_KEY, 'euw1');
$seasonManager = new SeasonManager($pdo);
$gestionJoueurs = new GestionJoueurs($pdo, $riotAPI, $seasonManager);

// Récupérer tous les joueurs actifs
$sql = "SELECT id, nom FROM joueurs WHERE deleted_at IS NULL";
$stmt = $pdo->query($sql);
$joueurs = $stmt->fetchAll();

foreach ($joueurs as $joueur) {
    echo "Synchronisation de {$joueur['nom']}...\n";
    try {
        // Attendre 2 minutes entre chaque joueur
        sleep(120);
        
        $result = $gestionJoueurs->synchroniserHistoriqueComplet($joueur['id']);
        if ($result['success']) {
            echo "✓ Succès\n";
        } else {
            echo "✗ Erreur: {$result['message']}\n";
        }
    } catch (Exception $e) {
        echo "✗ Erreur: {$e->getMessage()}\n";
    }
    echo "Attente de 2 minutes avant le prochain joueur...\n";
}

echo "Synchronisation terminée!\n"; 