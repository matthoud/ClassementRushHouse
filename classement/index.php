<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'RiotAPI.php';
require_once 'GestionJoueurs.php';
require_once 'SeasonManager.php';

$riotAPI = new RiotAPI(RIOT_API_KEY, 'euw1');
$seasonManager = new SeasonManager($pdo);
$gestion = new GestionJoueurs($pdo, $riotAPI, $seasonManager);

$message = null;
$messageClass = null;

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageClass = $_SESSION['messageClass'];
    unset($_SESSION['message']);
    unset($_SESSION['messageClass']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['nouveau_joueur'])) {
            $nom = trim($_POST['nom']);
            $tag = trim($_POST['tag']);
            $riotId = $nom . '#' . $tag;
            $result = $gestion->ajouterJoueur($riotId);
        } elseif (isset($_POST['sync_riot']) && isset($_POST['id'])) {
            error_log("Tentative de synchronisation pour le joueur ID: " . $_POST['id']);
            $result = $gestion->synchroniserJoueur($_POST['id']);
            if ($result['success']) {
                $_SESSION['message'] = "Synchronisation réussie";
                $_SESSION['messageClass'] = "success";
            } else {
                $_SESSION['message'] = $result['message'] ?? "Erreur lors de la synchronisation";
                $_SESSION['messageClass'] = "error";
            }
            // Si c'est une requête AJAX, renvoyer le résultat en JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            }
        } elseif (isset($_POST['supprimer'])) {
            $result = $gestion->supprimerJoueur($_POST['id']);
        }
        
        if (isset($result)) {
            $_SESSION['message'] = $result['message'];
            $_SESSION['messageClass'] = $result['success'] ? 'success' : 'error';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Erreur: " . $e->getMessage();
        $_SESSION['messageClass'] = 'error';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$selectedSeasonId = $_GET['season'] ?? null;
$selectedSeason = null;

if ($selectedSeasonId) {
    $selectedSeason = $seasonManager->getSeasonById($selectedSeasonId);
} else {
    $selectedSeason = $seasonManager->getCurrentSeason();
}

$period = null;
if ($selectedSeason) {
    $period = [
        'start_date' => $selectedSeason['start_date'],
        'end_date' => $selectedSeason['end_date'] ?? date('Y-m-d H:i:s')
    ];
}

$classement = $gestion->getClassement($period);

$currentPatch = $riotAPI->getCurrentPatch();

$currentPeriod = $_GET['period'] ?? 'global';

// Ajouter temporairement au début du fichier
error_log("Données des joueurs : " . print_r($classement, true));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Classement League of Legends</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="player_stats.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="header-section">
        <h1>CLASSEMENT</h1>
        <div class="season-info">
            <div class="current-season">
                <h2>RUSH HOUSE</h2>
                <div class="patch-info">
                    PATCH <?php echo $currentPatch; ?>
                </div>
            </div>
            <div class="season-selector">
                <form method="get" action="">
                    <select name="season" onchange="this.form.submit()">
                        <?php
                        $seasons = $seasonManager->getSeasons();
                        usort($seasons, function($a, $b) {
                            if ($a['split_number'] == 0) return -1;
                            if ($b['split_number'] == 0) return 1;
                            return $a['split_number'] - $b['split_number'];
                        });
                        
                        foreach ($seasons as $season) {
                            $selected = ($selectedSeason && $season['id'] == $selectedSeason['id']) ? 'selected' : '';
                            $isGlobal = $season['split_number'] == 0 ? '🏆 ' : '';
                            echo "<option value=\"{$season['id']}\" {$selected}>{$isGlobal}{$season['name']}</option>";
                        }
                        ?>
                    </select>
                </form>
            </div>
        </div>
    </div>
    
    <?php if (isset($message)): ?>
        <div class="message <?php echo $messageClass; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <table>
        <tr>
            <th>#</th>
            <th>JOUEURS</th>
            <th>ELO</th>
            <th>PARTIES</th>
            <th>VICTOIRES</th>
            <th>DÉFAITES</th>
            <th>WINRATE</th>
            <th>DERNIÈRE PARTIE</th>
            <th>ACTIONS</th>
        </tr>
        <?php
        foreach ($classement as $position => $joueur):
        ?>
        <tr>
            <td><?php echo $position + 1; ?></td>
            <td class="player-info">
                <div class="player-card">
                    <a href="player_stats.php?id=<?php echo $joueur['id']; ?>" class="player-name">
                        <?php 
                        $parts = explode('#', $joueur['nom']);
                        echo htmlspecialchars($parts[0]); 
                        ?>
                    </a>
                </div>
            </td>
            <td class="rank-cell">
                <?php if ($joueur['tier'] != 'UNRANKED'): ?>
                    <div class="rank-info">
                        <img src="assets/ranks/<?php echo strtolower($joueur['tier']); ?>.png" 
                             class="rank-icon" 
                             alt="<?php echo $joueur['tier']; ?>">
                        <div class="rank-details">
                            <div class="tier-rank">
                                <?php echo "{$joueur['tier']} {$joueur['rank']}"; ?>
                            </div>
                            <div class="lp-info">
                                <?php echo "{$joueur['lp']} LP"; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="rank-info unranked">
                        <div class="rank-details">
                            <div class="tier-rank">UNRANKED</div>
                        </div>
                    </div>
                <?php endif; ?>
            </td>
            <td class="games-cell">
                <span class="games-played"><?php echo $joueur['total_games']; ?></span>
            </td>
            <td><?php echo $joueur['victoires']; ?></td>
            <td><?php echo $joueur['defaites']; ?></td>
            <td class="winrate-cell">
                <div class="winrate-info">
                    <div class="winrate-percentage <?php echo $joueur['pourcentage_victoire'] >= 50 ? 'positive' : 'negative'; ?>">
                        <?php echo round($joueur['pourcentage_victoire'], 1); ?>%
                    </div>
                </div>
            </td>
            <td class="last-match">
                <?php 
                $lastMatch = json_decode($joueur['last_match_info'], true);
                if ($lastMatch): 
                    $kdaRatio = $lastMatch['kda'];
                    $csPerMin = $lastMatch['cs_per_min'];
                    $gameType = $lastMatch['game_type'] === 'RANKED_SOLO_5x5' ? 'Classé Solo/Duo' : $lastMatch['game_type'];
                    $gameDate = new DateTime($lastMatch['game_creation']);
                ?>
                    <div class="match-info <?php echo $lastMatch['win'] ? 'victory' : 'defeat'; ?>">
                        <div class="match-left">
                            <div class="champion-icon">
                                <img src="https://ddragon.leagueoflegends.com/cdn/<?php echo $currentPatch; ?>/img/champion/<?php echo htmlspecialchars($lastMatch['champion_name']); ?>.png" 
                                     alt="<?php echo htmlspecialchars($lastMatch['champion_name']); ?>">
                                <?php if (!empty($lastMatch['placement'])): ?>
                                    <div class="placement" data-place="<?php echo $lastMatch['placement']; ?>">
                                        <?php echo $lastMatch['placement']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="match-details">
                                <div class="match-type"><?php echo $gameType; ?></div>
                                <div class="match-time"><?php echo $gameDate->format('d/m/Y H:i'); ?></div>
                            </div>
                        </div>
                        <div class="match-center">
                            <div class="kda-container">
                                <div class="kda"><?php echo "{$lastMatch['kills']}/{$lastMatch['deaths']}/{$lastMatch['assists']}"; ?></div>
                                <div class="kda-ratio"><?php echo $kdaRatio; ?> KDA</div>
                            </div>
                            <div class="match-stats">
                                <span class="cs"><?php echo $lastMatch['cs']; ?> CS (<?php echo $csPerMin; ?>/min)</span>
                                <span class="vision">Vision: <?php echo $lastMatch['vision_score']; ?></span>
                            </div>
                        </div>
                        <div class="match-indicators">
                            <?php if (!empty($lastMatch['multi_kills']) || !empty($lastMatch['badges'])): ?>
                                <?php if (!empty($lastMatch['multi_kills'])): ?>
                                    <div class="multi-kills"><?php echo $lastMatch['multi_kills']; ?></div>
                                <?php endif; ?>
                                <?php if (!empty($lastMatch['badges'])): ?>
                                    <div class="performance-badges">
                                        <?php foreach ($lastMatch['badges'] as $badge): 
                                            if ($badge !== null):
                                        ?>
                                            <span class="badge <?php echo strtolower($badge); ?>">
                                                <?php echo $badge; ?>
                                            </span>
                                        <?php 
                                            endif;
                                        endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="empty-indicators"></div>
                            <?php endif; ?>
                        </div>
                        <div class="match-right">
                            <div class="game-result"><?php echo $lastMatch['win'] ? 'Victoire' : 'Défaite'; ?></div>
                            <div class="match-duration">
                                <?php 
                                $minutes = floor($lastMatch['game_duration'] / 60);
                                $seconds = $lastMatch['game_duration'] % 60;
                                echo sprintf('%d:%02d', $minutes, $seconds);
                                ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <span class="no-match">Pas de partie solo/duo récente</span>
                <?php endif; ?>
            </td>
            <td class="actions">
                <form method="POST" class="sync-form" style="display: inline;">
                    <input type="hidden" name="sync_riot" value="1">
                    <input type="hidden" name="id" value="<?php echo $joueur['id']; ?>">
                    <button type="submit" class="btn btn-primary" title="Synchroniser">
                        <i class="fas fa-sync"></i>
                    </button>
                </form>
                <form method="post" class="action-form" style="display: inline;">
                    <input type="hidden" name="id" value="<?php echo $joueur['id']; ?>">
                    <button type="submit" name="supprimer" class="btn btn-danger" 
                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce joueur ?')">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <form method="post" class="add-form">
        <input type="text" name="nom" required placeholder="Nom du joueur">
        <span class="separator">#</span>
        <input type="text" name="tag" value="EUW" style="width: 80px;">
        <button type="submit" name="nouveau_joueur" class="btn btn-ajouter">Ajouter un joueur</button>
    </form>

    <div class="loading">
        <div class="loading-spinner"></div>
        <div class="loading-text">Synchronisation en cours...</div>
    </div>
</body>
</html>
