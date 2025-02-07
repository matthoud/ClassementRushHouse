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

    // Récupérer le nom du joueur pour le titre
    $playerName = explode('#', $playerStats['player']['nom'])[0];

    if ($debug) {
        $debugInfo = $gestionJoueurs->debugLastMatches($playerId);
        error_log("Debug info pour joueur $playerId: " . print_r($debugInfo, true));
    }

    // Calculer le winrate pour l'affichage
    $winrate = $playerStats['stats']['total_games'] > 0 
        ? ($playerStats['stats']['wins'] / $playerStats['stats']['total_games']) * 100 
        : 0;

    $championStats = $gestionJoueurs->getChampionStats($playerId, $period);

    $lpHistory = $gestionJoueurs->getLPHistory($playerId);

    // Initialiser les variables avec des valeurs par défaut
    $stats = $playerStats['stats'] ?? [];
    $kills = $stats['avg_kills'] ?? 0;
    $deaths = $stats['avg_deaths'] ?? 0;
    $assists = $stats['avg_assists'] ?? 0;
    $kda = $stats['kda'] ?? 0;
    $cs = $stats['avg_cs'] ?? 0;
    $csPerMin = $stats['avg_cs_per_min'] ?? 0;
    $vision = $stats['avg_vision'] ?? 0;
    $damagePerMin = $stats['avg_damage_per_min'] ?? 0;
    $totalGames = $stats['total_games'] ?? 0;
    $wins = $stats['wins'] ?? 0;
    $losses = $stats['losses'] ?? 0;
    $winrate = $stats['winrate'] ?? 0;
    $doubleKills = $stats['double_kills'] ?? 0;
    $tripleKills = $stats['triple_kills'] ?? 0;
    $quadraKills = $stats['quadra_kills'] ?? 0;
    $pentaKills = $stats['penta_kills'] ?? 0;

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
    <title><?php echo htmlspecialchars($playerName); ?> - Statistiques</title>
    <link rel="stylesheet" href="player_stats.css">
    <link rel="stylesheet" href="styles.css">
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
                        <div class="kda-value"><?php echo number_format($kda, 2); ?></div>
                        <div class="kda-details">
                            <span class="kills"><?php echo number_format($kills, 1); ?></span> /
                            <span class="deaths"><?php echo number_format($deaths, 1); ?></span> /
                            <span class="assists"><?php echo number_format($assists, 1); ?></span>
                        </div>
                    </div>
                    <div class="additional-stats">
                        <div class="stat-box">
                            <div class="stat-label">CS par minute</div>
                            <div class="stat-value"><?php echo number_format($csPerMin, 1); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Dégâts par minute</div>
                            <div class="stat-value"><?php echo number_format($damagePerMin, 0); ?></div>
                        </div>
                    </div>
                </div>

                <div class="champions-section">
                    <h2>Champions les plus joués</h2>
                    <div class="champion-list">
                        <?php 
                        // Vérifier si championStats est un tableau et contient des données
                        if (isset($championStats['champions']) && is_array($championStats['champions'])) {
                            foreach ($championStats['champions'] as $champion) {
                                if (isset($champion['champion_name']) && !empty($champion['champion_name'])) {
                                    // Normaliser le nom du champion
                                    $championName = ucfirst(strtolower($champion['champion_name']));
                                    ?>
                                    <div class="champion-card">
                                        <img src="assets/champion/<?php echo htmlspecialchars($championName); ?>.png" 
                                             alt="<?php echo htmlspecialchars($championName); ?>"
                                             onerror="this.src='assets/champion/default.png'">
                                        <div class="champion-info">
                                            <div class="champion-name"><?php echo htmlspecialchars($championName); ?></div>
                                            <div class="champion-stats">
                                                <span class="games"><?php echo isset($champion['games_played']) ? $champion['games_played'] : 0; ?> parties</span>
                                                <span class="winrate"><?php echo isset($champion['winrate']) ? round($champion['winrate'], 1) : '0'; ?>%</span>
                                                <span class="kda"><?php echo isset($champion['kda']) ? number_format($champion['kda'], 1) : '0.0'; ?> KDA</span>
                                            </div>
                                            <?php if (!empty($champion['badges'])): ?>
                                                <div class="champion-badges">
                                                    <?php foreach ($champion['badges'] as $badge): ?>
                                                        <span class="badge"><?php echo htmlspecialchars($badge); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                        } else {
                            echo '<div class="no-data">Aucune donnée disponible sur les champions</div>';
                        }
                        ?>
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
                            <div class="winrate-value"><?php echo $wins; ?></div>
                        </div>
                        <div class="winrate-stat">
                            <div class="winrate-label">Défaites</div>
                            <div class="winrate-value"><?php echo $losses; ?></div>
                        </div>
                        <div class="winrate-stat">
                            <div class="winrate-label">Parties jouées</div>
                            <div class="winrate-value"><?php echo $totalGames; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-column">
                <div class="roles-section">
                    <h2>Rôles préférés</h2>
                    <div class="role-list">
                        <?php 
                        if (isset($playerStats['role_stats']) && is_array($playerStats['role_stats'])) {
                            foreach ($playerStats['role_stats'] as $role) {
                                if (isset($role['team_position']) && !empty($role['team_position'])) {
                                    $roleKey = strtoupper($role['team_position']);
                                    $roleDisplay = [
                                        'TOP' => 'Top',
                                        'JUNGLE' => 'Jungle',
                                        'MIDDLE' => 'Mid',
                                        'BOTTOM' => 'ADC',
                                        'UTILITY' => 'Support'
                                    ];
                                    ?>
                                    <div class="role-card">
                                        <img src="assets/role-icons/<?php echo htmlspecialchars($roleKey); ?>.png" 
                                             class="role-icon"
                                             onerror="this.src='assets/role-icons/default.png'">
                                        <div class="role-info">
                                            <div class="role-name">
                                                <?php echo $roleDisplay[$roleKey] ?? $roleKey; ?>
                                            </div>
                                            <div class="role-stats">
                                                <span class="games">
                                                    <?php echo isset($role['games_played']) ? $role['games_played'] : 0; ?> parties
                                                </span>
                                                <span class="winrate">
                                                    <?php 
                                                    if (isset($role['games_played']) && $role['games_played'] > 0) {
                                                        $winrate = ($role['wins'] / $role['games_played']) * 100;
                                                        echo round($winrate, 1);
                                                    } else {
                                                        echo '0';
                                                    }
                                                    ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                        } else {
                            echo '<div class="no-data">Aucune donnée disponible sur les rôles</div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="multikills-section">
                    <h2>Multi Kills</h2>
                    <div class="multikills-grid">
                        <div class="multikill-card">
                            <div class="multikill-value"><?php echo $doubleKills; ?></div>
                            <div class="multikill-label">Double Kills</div>
                        </div>
                        <div class="multikill-card">
                            <div class="multikill-value"><?php echo $tripleKills; ?></div>
                            <div class="multikill-label">Triple Kills</div>
                        </div>
                        <div class="multikill-card">
                            <div class="multikill-value"><?php echo $quadraKills; ?></div>
                            <div class="multikill-label">Quadra Kills</div>
                        </div>
                        <div class="multikill-card">
                            <div class="multikill-value"><?php echo $pentaKills; ?></div>
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