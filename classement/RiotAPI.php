<?php
class RiotAPI {
    private $apiKey;
    private $region;
    private $baseUrl;
    private $requestDelay = 0.5; // Délai réduit
    
    public function __construct($apiKey, $region = 'euw1') {
        $this->apiKey = $apiKey;
        $this->region = $region;
        $this->baseUrl = "https://{$region}.api.riotgames.com/lol/";
    }
    
    public function getSummonerByName($gameName, $tagLine) {
        try {
            // Nettoyer et encoder correctement le nom et le tag
            $gameName = trim($gameName);
            $tagLine = trim($tagLine);
            
            // Encoder l'URL en préservant les espaces
            $encodedGameName = rawurlencode($gameName);
            $encodedTagLine = rawurlencode($tagLine);

            error_log("Recherche du joueur: '$gameName#$tagLine' (encodé: $encodedGameName#$encodedTagLine)");

            $url = "https://europe.api.riotgames.com/riot/account/v1/accounts/by-riot-id/{$encodedGameName}/{$encodedTagLine}";
            $accountData = $this->makeRequest($url);

            if (!$accountData || !isset($accountData['puuid'])) {
                error_log("Compte non trouvé pour {$gameName}#{$tagLine}");
                return null;
            }

            error_log("PUUID trouvé: " . $accountData['puuid']);

            $summonerUrl = "https://{$this->region}.api.riotgames.com/lol/summoner/v4/summoners/by-puuid/{$accountData['puuid']}";
            $summonerData = $this->makeRequest($summonerUrl);

            if (!$summonerData) {
                error_log("Données du summoner non trouvées pour {$gameName}#{$tagLine}");
                return null;
            }

            error_log("Données du summoner récupérées avec succès");
            return $summonerData;

        } catch (Exception $e) {
            error_log("Erreur dans getSummonerByName: " . $e->getMessage());
            return null;
        }
    }
    
    public function getMatchHistory($puuid, $params = []) {
        $regionV5 = $this->getRegionV5($this->region);
        $url = "https://{$regionV5}.api.riotgames.com/lol/match/v5/matches/by-puuid/{$puuid}/ids";
        
        // Paramètres pour solo/duo uniquement
        $defaultParams = [
            'queue' => 420, // 420 = Solo/Duo Queue
            'type' => 'ranked',
            'start' => 0,
            'count' => 100
        ];
        
        $params = array_merge($defaultParams, $params);
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url);
    }
    
    public function getMatchDetails($matchId) {
        $regionV5 = $this->getRegionV5($this->region);
        $url = "https://{$regionV5}.api.riotgames.com/lol/match/v5/matches/{$matchId}";
        return $this->makeRequest($url);
    }
    
    public function getRankedStats($summonerId) {
        try {
            $url = "https://{$this->region}.api.riotgames.com/lol/league/v4/entries/by-summoner/{$summonerId}";
            $rankData = $this->makeRequest($url);
            
            error_log("Données ranked reçues: " . print_r($rankData, true));
            
            if (!$rankData || !is_array($rankData)) {
                return [];
            }

            // Chercher spécifiquement les données de ranked solo/duo
            foreach ($rankData as $queue) {
                if ($queue['queueType'] === 'RANKED_SOLO_5x5') {
                    return [$queue]; // Retourner uniquement les données solo/duo
                }
            }

            // Si aucune donnée ranked solo n'est trouvée
            return [
                [
                    'queueType' => 'RANKED_SOLO_5x5',
                    'tier' => 'UNRANKED',
                    'rank' => '',
                    'leaguePoints' => 0,
                    'wins' => 0,
                    'losses' => 0
                ]
            ];
        } catch (Exception $e) {
            error_log("Erreur dans getRankedStats: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLastMatch($puuid) {
        try {
            error_log("Récupération du dernier match pour PUUID: " . $puuid);
            
            // Récupérer uniquement les parties classées solo/duo (queue=420)
            $matchListUrl = "https://europe.api.riotgames.com/lol/match/v5/matches/by-puuid/{$puuid}/ids?queue=420&type=ranked&start=0&count=1";
            $matchIds = $this->makeRequest($matchListUrl);
            
            if (empty($matchIds)) {
                error_log("Aucune partie classée solo/duo trouvée");
                return null;
            }

            $matchId = $matchIds[0];
            error_log("Dernier match ID trouvé: " . $matchId);
            
            // Récupérer les détails du match
            $matchUrl = "https://europe.api.riotgames.com/lol/match/v5/matches/{$matchId}";
            $matchData = $this->makeRequest($matchUrl);
            
            if (!$matchData) {
                error_log("Impossible de récupérer les détails du match");
                return null;
            }

            return $matchData;
        } catch (Exception $e) {
            error_log("Erreur dans getLastMatch: " . $e->getMessage());
            return null;
        }
    }
    
    public function getCurrentPatch() {
        try {
            // Récupérer les versions depuis l'API Data Dragon
            $url = "https://ddragon.leagueoflegends.com/api/versions.json";
            $versions = $this->makeRequest($url);
            
            if (!$versions || empty($versions)) {
                error_log("Impossible de récupérer la version actuelle");
                return "14.4.1"; // Version par défaut si erreur
            }

            // La première version dans la liste est la plus récente
            return $versions[0];
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de la version: " . $e->getMessage());
            return "14.4.1"; // Version par défaut si erreur
        }
    }
    
    private function getRegionV5($region) {
        $regionMapping = [
            'euw1' => 'europe',
            'na1' => 'americas',
            'kr' => 'asia'
        ];
        return $regionMapping[$region] ?? 'europe';
    }
    
    private function makeRequest($url) {
        error_log("\n=== Début makeRequest ===");
        error_log("URL: " . $url);
        
        $headers = [
            'X-Riot-Token: ' . $this->apiKey
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        error_log("Code HTTP: " . $httpCode);
        error_log("Réponse: " . substr($response, 0, 1000)); // Log les 1000 premiers caractères
        
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Erreur API Riot (HTTP $httpCode): $url");
            if ($httpCode === 429) {
                error_log("Limite d'API dépassée");
                throw new Exception("Limite d'API dépassée");
            }
            return null;
        }

        return json_decode($response, true);
    }

    public function getAccountByRiotId($gameName, $tagLine) {
        $url = "https://europe.api.riotgames.com/riot/account/v1/accounts/by-riot-id/{$gameName}/{$tagLine}";
        return $this->makeRequest($url);
    }

    public function getSummonerByPUUID($puuid) {
        $url = "https://euw1.api.riotgames.com/lol/summoner/v4/summoners/by-puuid/{$puuid}";
        return $this->makeRequest($url);
    }
}
?>
