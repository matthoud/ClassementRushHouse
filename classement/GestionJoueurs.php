<?php
class GestionJoueurs {
    private $pdo;
    private $riotAPI;
    private $seasonManager;
    
    public function __construct($pdo, $riotAPI = null, $seasonManager = null) {
        $this->pdo = $pdo;
        $this->riotAPI = $riotAPI;
        $this->seasonManager = $seasonManager;
    }

    public function getClassement($period = null) {
        try {
            $sql = "
                SELECT j.*,
                COALESCE(SUM(CASE WHEN m.win = 1 THEN 1 ELSE 0 END), 0) as victoires,
                COALESCE(SUM(CASE WHEN m.win = 0 AND m.game_duration >= 300 THEN 1 ELSE 0 END), 0) as defaites,
                COALESCE(SUM(CASE WHEN m.game_duration >= 300 THEN 1 ELSE 0 END), 0) as total_games,
                CASE 
                    WHEN COALESCE(SUM(CASE WHEN m.game_duration >= 300 THEN 1 ELSE 0 END), 0) > 0 
                    THEN (SUM(CASE WHEN m.win = 1 THEN 1 ELSE 0 END) * 100.0 / 
                         SUM(CASE WHEN m.game_duration >= 300 THEN 1 ELSE 0 END))
                    ELSE 0 
                END as pourcentage_victoire,
                MAX(m.game_creation) as last_match_time,
                (
                    SELECT JSON_OBJECT(
                        'champion_name', m2.champion_name,
                        'kills', m2.kills,
                        'deaths', m2.deaths,
                        'assists', m2.assists,
                        'win', m2.win,
                        'game_duration', m2.game_duration,
                        'cs', m2.cs,
                        'vision_score', m2.vision_score,
                        'kda', ROUND((m2.kills + m2.assists) / IF(m2.deaths = 0, 1, m2.deaths), 2),
                        'cs_per_min', ROUND(m2.cs / (m2.game_duration / 60), 1),
                        'game_creation', DATE_FORMAT(m2.game_creation, '%Y-%m-%d %H:%i:%s'),
                        'game_type', m2.queue_type,
                        'multi_kills', CASE 
                            WHEN m2.penta_kills > 0 THEN 'Penta élimination'
                            WHEN m2.quadra_kills > 0 THEN 'Quadra élimination'
                            WHEN m2.triple_kills > 0 THEN 'Triple élimination'
                            WHEN m2.double_kills > 0 THEN 'Double élimination'
                            ELSE NULL 
                        END,
                        'badges', JSON_ARRAY(
                            CASE 
                                WHEN m2.kills >= 10 OR (m2.kills + m2.assists) / IF(m2.deaths = 0, 1, m2.deaths) >= 5 THEN 'CARRY'
                                WHEN m2.deaths = 0 AND (m2.kills + m2.assists) >= 3 THEN 'Perfect'
                                WHEN m2.vision_score >= 50 THEN 'Vision'
                                WHEN m2.cs / (m2.game_duration / 60) >= 8 THEN 'Farming'
                                ELSE NULL 
                            END
                        )
                    )
                    FROM matches m2 
                    WHERE m2.player_id = j.id 
                    AND m2.game_duration >= 300
                    ORDER BY m2.game_creation DESC 
                    LIMIT 1
                ) as last_match_info
                FROM joueurs j
                LEFT JOIN matches m ON j.id = m.player_id";

            if ($period) {
                $sql .= " AND m.game_creation BETWEEN :start_date AND :end_date";
            }

            $sql .= " WHERE j.deleted_at IS NULL
                      GROUP BY j.id
                      ORDER BY pourcentage_victoire DESC, victoires DESC";

            $stmt = $this->pdo->prepare($sql);

            if ($period) {
                $stmt->bindParam(':start_date', $period['start_date']);
                $stmt->bindParam(':end_date', $period['end_date']);
            }

            $stmt->execute();
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("Erreur dans getClassement: " . $e->getMessage());
            return [];
        }
    }

    public function synchroniserJoueur($id) {
        try {
            // Récupérer les informations du joueur
            $sql = "SELECT puuid, summoner_id FROM joueurs WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $joueur = $stmt->fetch();

            if (!$joueur) {
                throw new Exception("Joueur non trouvé");
            }

            // Récupérer uniquement le dernier match
            $matchIds = $this->riotAPI->getMatchHistory($joueur['puuid'], ['queue' => 420, 'count' => 1]);
            
            if (!empty($matchIds)) {
                $matchData = $this->riotAPI->getMatchDetails($matchIds[0]);
                if ($matchData && isset($matchData['info'])) {
                    foreach ($matchData['info']['participants'] as $participant) {
                        if ($participant['puuid'] === $joueur['puuid']) {
                            // Vérifier si le match existe déjà
                            $sql = "SELECT COUNT(*) as count FROM matches 
                                   WHERE player_id = :player_id 
                                   AND game_creation = :game_creation";
                            
                            $stmt = $this->pdo->prepare($sql);
                            $stmt->execute([
                                'player_id' => $id,
                                'game_creation' => date('Y-m-d H:i:s', (int)($matchData['info']['gameCreation'] / 1000))
                            ]);
                            
                            $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                            
                            // Si le match n'existe pas, l'insérer
                            if (!$exists) {
                                // Supprimer d'abord tout match potentiellement en double
                                $sql = "DELETE FROM matches 
                                       WHERE player_id = :player_id 
                                       AND game_creation = :game_creation";
                                $stmt = $this->pdo->prepare($sql);
                                $stmt->execute([
                                    'player_id' => $id,
                                    'game_creation' => date('Y-m-d H:i:s', (int)($matchData['info']['gameCreation'] / 1000))
                                ]);

                                // Insérer le nouveau match
                                $sql = "INSERT INTO matches (
                                    player_id, champion_name, role, win, kills, deaths, assists,
                                    cs, vision_score, double_kills, triple_kills, quadra_kills,
                                    penta_kills, game_duration, game_creation, queue_type,
                                    team_position, total_damage_dealt
                                ) VALUES (
                                    :player_id, :champion, :role, :win, :kills, :deaths, :assists,
                                    :cs, :vision, :doubles, :triples, :quadras, :pentas,
                                    :duration, :creation, :queue_type, :team_position, :damage
                                )";
                                
                                $stmt = $this->pdo->prepare($sql);
                                $stmt->execute([
                                    'player_id' => $id,
                                    'champion' => $participant['championName'],
                                    'role' => $participant['teamPosition'] ?? '',
                                    'win' => (int)$participant['win'],
                                    'kills' => (int)$participant['kills'],
                                    'deaths' => (int)$participant['deaths'],
                                    'assists' => (int)$participant['assists'],
                                    'cs' => (int)($participant['totalMinionsKilled'] + $participant['neutralMinionsKilled']),
                                    'vision' => (int)$participant['visionScore'],
                                    'doubles' => (int)$participant['doubleKills'],
                                    'triples' => (int)$participant['tripleKills'],
                                    'quadras' => (int)$participant['quadraKills'],
                                    'pentas' => (int)$participant['pentaKills'],
                                    'duration' => (int)$matchData['info']['gameDuration'],
                                    'creation' => date('Y-m-d H:i:s', (int)($matchData['info']['gameCreation'] / 1000)),
                                    'queue_type' => 'RANKED_SOLO_5x5',
                                    'team_position' => $participant['teamPosition'] ?? '',
                                    'damage' => (int)$participant['totalDamageDealtToChampions']
                                ]);
                            }
                            break;
                        }
                    }
                }
            }

            // Mettre à jour les stats ranked
            $rankData = $this->riotAPI->getRankedStats($joueur['summoner_id']);
            if (!empty($rankData)) {
                foreach ($rankData as $queue) {
                    if ($queue['queueType'] === 'RANKED_SOLO_5x5') {
                        $sql = "UPDATE joueurs SET 
                                tier = :tier,
                                rank = :rank,
                                lp = :lp,
                                last_update = NOW()
                                WHERE id = :id";
                        
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute([
                            'tier' => $queue['tier'],
                            'rank' => $queue['rank'],
                            'lp' => $queue['leaguePoints'],
                            'id' => $id
                        ]);

                        // Ajouter à l'historique LP
                        $sql = "INSERT INTO lp_history (player_id, tier, rank, lp) 
                                VALUES (:player_id, :tier, :rank, :lp)";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute([
                            'player_id' => $id,
                            'tier' => $queue['tier'],
                            'rank' => $queue['rank'],
                            'lp' => $queue['leaguePoints']
                        ]);
                        break;
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Synchronisation réussie'
            ];

        } catch (Exception $e) {
            error_log("Erreur dans synchroniserJoueur: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erreur lors de la synchronisation: " . $e->getMessage()
            ];
        }
    }

    public function ajouterJoueur($riotId) {
        try {
            $parts = explode('#', $riotId);
            if (count($parts) !== 2) {
                return [
                    'success' => false,
                    'message' => "Format invalide. Utilisez NomJoueur#Tag"
                ];
            }
            
            $gameName = trim($parts[0]);
            $tagLine = trim($parts[1]);
            
            $accountData = $this->riotAPI->getAccountByRiotId($gameName, $tagLine);
            if (!$accountData) {
                return [
                    'success' => false,
                    'message' => "Joueur non trouvé"
                ];
            }

            $sql = "INSERT INTO joueurs (nom, puuid, summoner_id) VALUES (:nom, :puuid, :summoner_id)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'nom' => $gameName . '#' . $tagLine,
                'puuid' => $accountData['puuid'],
                'summoner_id' => $accountData['id']
            ]);

            return [
                'success' => true,
                'message' => "Joueur ajouté avec succès"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Erreur lors de l'ajout du joueur: " . $e->getMessage()
            ];
        }
    }

    public function supprimerJoueur($id) {
        try {
            $sql = "UPDATE joueurs SET deleted_at = NOW() WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            return [
                'success' => true,
                'message' => "Joueur supprimé avec succès"
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => "Erreur lors de la suppression du joueur"
            ];
        }
    }

    public function getPlayerStats($playerId, $period = null) {
        try {
            // Récupérer les informations de base du joueur
            $sql = "SELECT * FROM joueurs WHERE id = :id AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $playerId]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$player) {
                return [
                    'success' => false,
                    'message' => "Joueur non trouvé"
                ];
            }

            // Construire la requête pour les statistiques globales
            $sql = "SELECT 
                    COUNT(*) as total_games,
                    SUM(CASE WHEN win = 1 THEN 1 ELSE 0 END) as wins,
                    SUM(CASE WHEN win = 0 AND game_duration >= 300 THEN 1 ELSE 0 END) as losses,
                    COALESCE(AVG(kills), 0) as avg_kills,
                    COALESCE(AVG(deaths), 0) as avg_deaths,
                    COALESCE(AVG(assists), 0) as avg_assists,
                    COALESCE(AVG(cs), 0) as avg_cs,
                    COALESCE(AVG(vision_score), 0) as avg_vision,
                    COALESCE(AVG(cs / (game_duration / 60)), 0) as avg_cs_per_min,
                    COALESCE(AVG(total_damage_dealt / (game_duration / 60)), 0) as avg_damage_per_min,
                    COALESCE(SUM(double_kills), 0) as total_double_kills,
                    COALESCE(SUM(triple_kills), 0) as total_triple_kills,
                    COALESCE(SUM(quadra_kills), 0) as total_quadra_kills,
                    COALESCE(SUM(penta_kills), 0) as total_penta_kills
                    FROM matches 
                    WHERE player_id = :player_id 
                    AND game_duration >= 300";

            if ($period) {
                $sql .= " AND game_creation BETWEEN :start_date AND :end_date";
            }

            $stmt = $this->pdo->prepare($sql);
            $params = ['player_id' => $playerId];

            if ($period) {
                $params['start_date'] = $period['start_date'];
                $params['end_date'] = $period['end_date'];
            }

            $stmt->execute($params);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Récupérer les statistiques par rôle
            $sql = "SELECT 
                    team_position,
                    COUNT(*) as games_played,
                    SUM(CASE WHEN win = 1 THEN 1 ELSE 0 END) as wins
                    FROM matches 
                    WHERE player_id = :player_id 
                    AND game_duration >= 300
                    AND team_position != ''
                    GROUP BY team_position";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['player_id' => $playerId]);
            $role_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculer les statistiques supplémentaires
            $totalGames = (int)$stats['total_games'];
            $winrate = $totalGames > 0 ? ($stats['wins'] / $totalGames) * 100 : 0;
            $kda = ($stats['avg_deaths'] > 0) ? 
                   ($stats['avg_kills'] + $stats['avg_assists']) / $stats['avg_deaths'] : 
                   $stats['avg_kills'] + $stats['avg_assists'];

            return [
                'success' => true,
                'player' => $player,
                'stats' => [
                    'total_games' => $totalGames,
                    'wins' => (int)$stats['wins'],
                    'losses' => (int)$stats['losses'],
                    'winrate' => round($winrate, 2),
                    'avg_kills' => round($stats['avg_kills'], 1),
                    'avg_deaths' => round($stats['avg_deaths'], 1),
                    'avg_assists' => round($stats['avg_assists'], 1),
                    'kda' => round($kda, 2),
                    'avg_cs' => round($stats['avg_cs'], 1),
                    'avg_cs_per_min' => round($stats['avg_cs_per_min'], 1),
                    'avg_vision' => round($stats['avg_vision'], 1),
                    'avg_damage_per_min' => round($stats['avg_damage_per_min'], 1),
                    'double_kills' => (int)$stats['total_double_kills'],
                    'triple_kills' => (int)$stats['total_triple_kills'],
                    'quadra_kills' => (int)$stats['total_quadra_kills'],
                    'penta_kills' => (int)$stats['total_penta_kills']
                ],
                'role_stats' => $role_stats
            ];

        } catch (Exception $e) {
            error_log("Erreur dans getPlayerStats: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erreur lors de la récupération des statistiques: " . $e->getMessage()
            ];
        }
    }

    public function getChampionStats($playerId, $period = null) {
        try {
            $sql = "SELECT 
                    champion_name,
                    COUNT(*) as games_played,
                    SUM(CASE WHEN win = 1 THEN 1 ELSE 0 END) as wins,
                    SUM(CASE WHEN win = 0 THEN 1 ELSE 0 END) as losses,
                    ROUND(AVG(kills), 1) as avg_kills,
                    ROUND(AVG(deaths), 1) as avg_deaths,
                    ROUND(AVG(assists), 1) as avg_assists,
                    ROUND(AVG(cs), 1) as avg_cs,
                    ROUND(AVG(vision_score), 1) as avg_vision,
                    ROUND(AVG(cs / (game_duration / 60)), 1) as avg_cs_per_min,
                    ROUND((SUM(CASE WHEN win = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as winrate,
                    ROUND(AVG((kills + assists) / CASE WHEN deaths = 0 THEN 1 ELSE deaths END), 2) as kda
                FROM matches 
                WHERE player_id = :player_id 
                AND game_duration >= 300";

            if ($period) {
                $sql .= " AND game_creation BETWEEN :start_date AND :end_date";
            }

            $sql .= " GROUP BY champion_name 
                      HAVING games_played > 0 
                      ORDER BY games_played DESC, winrate DESC";

            $stmt = $this->pdo->prepare($sql);
            $params = ['player_id' => $playerId];

            if ($period) {
                $params['start_date'] = $period['start_date'];
                $params['end_date'] = $period['end_date'];
            }

            $stmt->execute($params);
            $champions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Ajouter des badges pour chaque champion
            foreach ($champions as &$champion) {
                $badges = [];
                
                // Badge de maîtrise (plus de 10 parties)
                if ($champion['games_played'] >= 10) {
                    $badges[] = 'MAIN';
                }
                
                // Badge de performance (WR > 60%)
                if ($champion['winrate'] >= 60 && $champion['games_played'] >= 5) {
                    $badges[] = 'PERFORMANCE';
                }
                
                // Badge KDA (KDA > 4)
                if ($champion['kda'] >= 4) {
                    $badges[] = 'KDA';
                }

                $champion['badges'] = $badges;
            }

            return [
                'success' => true,
                'champions' => $champions
            ];

        } catch (Exception $e) {
            error_log("Erreur dans getChampionStats: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erreur lors de la récupération des statistiques des champions: " . $e->getMessage()
            ];
        }
    }

    public function getLPHistory($playerId, $period = null) {
        try {
            $sql = "SELECT 
                    lh.tier,
                    lh.rank,
                    lh.lp,
                    lh.recorded_at,
                    CASE 
                        WHEN lh.tier = 'IRON' THEN 0
                        WHEN lh.tier = 'BRONZE' THEN 400
                        WHEN lh.tier = 'SILVER' THEN 800
                        WHEN lh.tier = 'GOLD' THEN 1200
                        WHEN lh.tier = 'PLATINUM' THEN 1600
                        WHEN lh.tier = 'DIAMOND' THEN 2000
                        WHEN lh.tier = 'MASTER' THEN 2400
                        ELSE 0
                    END + 
                    CASE 
                        WHEN lh.rank = 'IV' THEN 0
                        WHEN lh.rank = 'III' THEN 100
                        WHEN lh.rank = 'II' THEN 200
                        WHEN lh.rank = 'I' THEN 300
                        ELSE 0
                    END + lh.lp as total_lp
                    FROM lp_history lh
                    WHERE lh.player_id = :player_id";

            if ($period) {
                $sql .= " AND lh.recorded_at BETWEEN :start_date AND :end_date";
            }

            $sql .= " ORDER BY lh.recorded_at ASC";

            $stmt = $this->pdo->prepare($sql);
            $params = ['player_id' => $playerId];

            if ($period) {
                $params['start_date'] = $period['start_date'];
                $params['end_date'] = $period['end_date'];
            }

            $stmt->execute($params);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculer les changements de LP
            for ($i = 1; $i < count($history); $i++) {
                $history[$i]['lp_change'] = $history[$i]['total_lp'] - $history[$i-1]['total_lp'];
            }
            if (count($history) > 0) {
                $history[0]['lp_change'] = 0; // Premier enregistrement
            }

            return [
                'success' => true,
                'data' => $history
            ];

        } catch (Exception $e) {
            error_log("Erreur dans getLPHistory: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erreur lors de la récupération de l'historique LP: " . $e->getMessage()
            ];
        }
    }
}
?> 