<?php
require_once 'config.php';
require_once 'GestionJoueurs.php';
require_once 'SeasonManager.php';
require_once 'RiotAPI.php';

// Initialiser les objets nécessaires
$riotAPI = new RiotAPI(RIOT_API_KEY, 'euw1');
$seasonManager = new SeasonManager($pdo);
$gestionJoueurs = new GestionJoueurs($pdo, $riotAPI, $seasonManager);

$playerId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$debug = isset($_GET['debug']) && $_GET['debug'] === 'true';

$selectedSeasonId = $_GET['season'] ?? null;
$selectedSeason = null;

if ($selectedSeasonId) {
    $selectedSeason = $seasonManager->getSeasonById($selectedSeasonId);
} else {
    $selectedSeason = $seasonManager->getCurrentSeason();
}

$period = [
    'start_date' => $selectedSeason['start_date'],
    'end_date' => $selectedSeason['end_date'] ?? date('Y-m-d H:i:s')
];

error_log("ID reçu dans player_stats.php: " . $_GET['id']);

try {
    if ($playerId === 0) {
        throw new Exception("ID du joueur non spécifié");
    }

    $playerStats = $gestionJoueurs->getPlayerStats($playerId, $period);
    
    if (!$playerStats['success']) {
        throw new Exception($playerStats['message']);
    }

    if ($debug) {
        $debugInfo = $gestionJoueurs->debugLastMatches($playerId);
        error_log("Debug info pour joueur $playerId: " . print_r($debugInfo, true));
    }

    // Calculer le winrate pour l'affichage
    $winrate = $playerStats['stats']['total_games'] > 0 
        ? ($playerStats['stats']['wins'] / $playerStats['stats']['total_games']) * 100 
        : 0;

    $championStats = $gestionJoueurs->getChampionStats($playerId);

    $lpHistory = $gestionJoueurs->getLPHistory($playerId);

} catch (Exception $e) {
    // Afficher une page d'erreur stylisée
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Joueur non trouvé</title>
        <link rel="stylesheet" href="player_stats.css">
        <style>
            .error-container {
                text-align: center;
                padding: 50px 20px;
                color: #00FFC2;
            }
            .error-title {
                font-size: 24px;
                margin-bottom: 20px;
            }
            .error-message {
                font-size: 16px;
                margin-bottom: 30px;
                opacity: 0.8;
            }
            .back-button {
                display: inline-block;
                padding: 10px 20px;
                background: rgba(0, 255, 194, 0.1);
                border: 2px solid #00FFC2;
                color: #00FFC2;
                text-decoration: none;
                border-radius: 5px;
                transition: all 0.3s ease;
            }
            .back-button:hover {
                background: rgba(0, 255, 194, 0.2);
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-title">Joueur non trouvé</div>
            <div class="error-message"><?php echo htmlspecialchars($e->getMessage()); ?></div>
            <a href="index.php" class="back-button">Retour au classement</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($playerStats['name']); ?> - Statistiques</title>
    <link rel="stylesheet" href="player_stats.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.1/dist/apexcharts.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta http-equiv="Cross-Origin-Opener-Policy" content="same-origin">
    <meta http-equiv="Cross-Origin-Embedder-Policy" content="require-corp">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="player_stats.js"></script>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($playerStats['player']['nom']); ?></h1>
        
        <div class="buttons-container">
            <a href="index.php" class="btn">Retour au classement</a>
            <button class="btn refresh-btn">
                <i class="fas fa-sync-alt"></i> Actualiser
            </button>
            <?php if ($debug): ?>
                <a href="?id=<?php echo $playerId; ?>" class="btn">Désactiver Debug</a>
            <?php else: ?>
                <a href="?id=<?php echo $playerId; ?>&debug=true" class="btn">Mode Debug</a>
            <?php endif; ?>
        </div>

        <div class="stats-layout">
            <div class="stats-column">
                <div class="kda-section">
                    <h2>KDA</h2>
                    <div class="kda-box">
                        <div class="kda-value"><?php echo number_format($playerStats['stats']['kda'], 2); ?></div>
                        <div class="kda-details">
                            <span class="kills"><?php echo $playerStats['stats']['kills']; ?></span> /
                            <span class="deaths"><?php echo $playerStats['stats']['deaths']; ?></span> /
                            <span class="assists"><?php echo $playerStats['stats']['assists']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="champions-section">
                    <h2>Champions les plus joués</h2>
                    <div class="champion-list">
                        <?php foreach ($championStats as $champion): ?>
                            <?php 
                            // Normaliser le nom du champion (première lettre en majuscule, reste en minuscule)
                            $championName = ucfirst(strtolower($champion['champion_name']));
                            error_log("Champion name (normalisé): " . $championName); 
                            ?>
                            <div class="champion-card">
                                <img src="assets/champion/<?php echo $championName; ?>.png" 
                                     alt="<?php echo $championName; ?>"
                                     onerror="console.error('Erreur de chargement pour: <?php echo $championName; ?>')">
                                <div class="champion-info">
                                    <div class="champion-name"><?php echo $championName; ?></div>
                                    <div class="champion-stats">
                                        <span class="games"><?php echo $champion['games_played']; ?> parties</span>
                                        <span class="winrate"><?php echo round($champion['winrate'], 1); ?>%</span>
                                        <span class="kda"><?php echo number_format($champion['kda'], 1); ?> KDA</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="stats-column">
                <div class="evolution-lp">
                    <h2>Évolution des LP</h2>
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <!-- Le graphique sera inséré ici -->
                    </div>
                </div>

                <div class="winrate-section">
                    <h2>Winrate</h2>
                    <div class="winrate-stats">
                        <div class="winrate-stat main">
                            <div class="winrate-label">Winrate</div>
                            <div class="winrate-value"><?php echo number_format($winrate, 1); ?>%</div>
                        </div>
                        <div class="winrate-stat">
                            <div class="winrate-label">Victoires</div>
                            <div class="winrate-value"><?php echo $playerStats['stats']['wins']; ?></div>
                        </div>
                        <div class="winrate-stat">
                            <div class="winrate-label">Défaites</div>
                            <div class="winrate-value"><?php echo $playerStats['stats']['losses']; ?></div>
                        </div>
                        <div class="winrate-stat">
                            <div class="winrate-label">Parties jouées</div>
                            <div class="winrate-value"><?php echo $playerStats['stats']['total_games']; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-column">
                <div class="roles-section">
                    <h2>Rôles préférés</h2>
                    <div class="role-list">
                        <?php foreach ($playerStats['role_stats'] as $role => $data): ?>
                            <div class="role-card">
                                <img src="assets/role-icons/<?php echo strtoupper($role); ?>.png" class="role-icon">
                                <div class="role-info">
                                    <div class="role-name">
                                        <?php 
                                            $roleDisplay = [
                                                'TOP' => 'Top',
                                                'JUNGLE' => 'Jungle',
                                                'MID' => 'Mid',
                                                'ADC' => 'ADC',
                                                'SUPPORT' => 'Support',
                                                'MIDDLE' => 'Mid',
                                                'BOTTOM' => 'ADC',
                                                'UTILITY' => 'Support'
                                            ];
                                            $roleKey = strtoupper($role);
                                            echo $roleDisplay[$roleKey] ?? $role;
                                        ?>
                                    </div>
                                    <div class="role-stats">
                                        <span class="games"><?php echo $data['games']; ?> parties</span>
                                        <span class="winrate"><?php echo round($data['winrate'], 1); ?>%</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="multikills-section">
                    <h2>Multi Kills</h2>
                    <div class="multikills-grid">
                        <div class="multikill-card">
                            <div class="multikill-value"><?php echo $playerStats['stats']['double_kills']; ?></div>
                            <div class="multikill-label">Double Kills</div>
                        </div>
                        <div class="multikill-card">
                            <div class="multikill-value"><?php echo $playerStats['stats']['triple_kills']; ?></div>
                            <div class="multikill-label">Triple Kills</div>
                        </div>
                        <div class="multikill-card">
                            <div class="multikill-value"><?php echo $playerStats['stats']['quadra_kills']; ?></div>
                            <div class="multikill-label">Quadra Kills</div>
                        </div>
                        <div class="multikill-card">
                            <div class="multikill-value"><?php echo $playerStats['stats']['penta_kills']; ?></div>
                            <div class="multikill-label">Penta Kills</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>
    <script>
        window.lpData = <?php echo json_encode($playerStats['lp_history'] ?? []); ?>;
        window.playerData = <?php echo json_encode($playerStats); ?>;
        window.lpHistory = <?php echo json_encode($lpHistory['data']); ?>;
    </script>
    <script src="refresh_stats.js"></script>
</body>
</html> 