<?php
session_start();
require_once 'config.php';
require_once 'RiotAPI.php';
require_once 'GestionJoueurs.php';

// Vérification de sécurité (à adapter selon votre système d'authentification)
$authorized = false;
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    $authorized = true;
}

// Si exécuté en ligne de commande, autoriser
if (php_sapi_name() === 'cli') {
    $authorized = true;
}

if (!$authorized) {
    die("Accès non autorisé");
}

// Initialisation
$riotAPI = new RiotAPI(RIOT_API_KEY, 'euw1');
$gestion = new GestionJoueurs($pdo, $riotAPI);

// Mode web avec affichage propre
if (php_sapi_name() !== 'cli') {
    echo "<html><head><title>Réparation des joueurs</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .log { margin: 5px 0; padding: 5px; }
        .success { color: green; }
        .error { color: red; }
    </style></head><body>";
    echo "<h1>Réparation des joueurs</h1>";
}

// Récupérer tous les joueurs
$sql = "SELECT id, nom FROM joueurs";
$joueurs = $pdo->query($sql)->fetchAll();

$results = [];
foreach ($joueurs as $joueur) {
    $message = "Vérification du joueur {$joueur['nom']} (ID: {$joueur['id']})";
    
    if (php_sapi_name() === 'cli') {
        echo $message . "\n";
    } else {
        echo "<div class='log'>" . htmlspecialchars($message) . "... ";
    }
    
    try {
        $success = $gestion->verifierJoueur($joueur['id']);
        if ($success) {
            $status = "OK";
            $class = "success";
        } else {
            $status = "Échec";
            $class = "error";
        }
    } catch (Exception $e) {
        $status = "Erreur: " . $e->getMessage();
        $class = "error";
    }
    
    if (php_sapi_name() === 'cli') {
        echo "Statut: $status\n";
    } else {
        echo "<span class='$class'>$status</span></div>";
    }
    
    // Attendre un peu entre chaque requête pour éviter de surcharger l'API
    usleep(500000); // 0.5 seconde
}

$message = "Réparation terminée";
if (php_sapi_name() === 'cli') {
    echo "\n$message\n";
} else {
    echo "<h2>$message</h2></body></html>";
} 