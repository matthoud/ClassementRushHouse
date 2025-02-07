<?php
class SeasonManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getCurrentSeason() {
        $sql = "SELECT * FROM seasons WHERE is_active = TRUE LIMIT 1";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllSeasons() {
        $sql = "SELECT * FROM seasons ORDER BY season_number DESC, split_number DESC";
        return $this->pdo->query($sql)->fetchAll();
    }
    
    public function getSeasonById($id) {
        try {
            $sql = "SELECT * FROM seasons WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur dans getSeasonById: " . $e->getMessage());
            return null;
        }
    }
    
    public function addNewSeason($seasonNumber, $splitNumber, $startDate, $patchStart, $patchCurrent = null) {
        // Désactiver toutes les saisons actuelles
        $sql = "UPDATE seasons SET is_active = FALSE";
        $this->pdo->exec($sql);
        
        if ($patchCurrent === null) {
            $patchCurrent = $patchStart;
        }
        
        $sql = "INSERT INTO seasons (
                    season_number, 
                    split_number, 
                    start_date, 
                    patch_start, 
                    patch_current, 
                    is_active
                ) VALUES (
                    :season,
                    :split,
                    :start,
                    :patch_start,
                    :patch_current,
                    TRUE
                )";
                
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'season' => $seasonNumber,
            'split' => $splitNumber,
            'start' => $startDate,
            'patch_start' => $patchStart,
            'patch_current' => $patchCurrent
        ]);
    }
    
    public function updatePatch($seasonId, $newPatch) {
        $sql = "UPDATE seasons SET patch_current = :patch WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'patch' => $newPatch,
            'id' => $seasonId
        ]);
    }
    
    public function getSeasonTimestamp($seasonId = null) {
        if ($seasonId) {
            $season = $this->getSeasonById($seasonId);
        } else {
            $season = $this->getCurrentSeason();
        }
        return strtotime($season['start_date']);
    }
    
    public function getSeasons() {
        $sql = "SELECT * FROM seasons ORDER BY start_date DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPlayerStats($playerId, $period = 'global') {
        $seasons = $this->getSeasons();
        $periodInfo = $seasons[$period] ?? $seasons['global'];
        
        $sql = "SELECT * FROM matches WHERE player_id = :player_id";
        $params = ['player_id' => $playerId];
        
        if ($periodInfo['start_date'] && $periodInfo['end_date']) {
            $sql .= " AND game_creation BETWEEN :start_date AND :end_date";
            $params['start_date'] = $periodInfo['start_date'];
            $params['end_date'] = $periodInfo['end_date'];
        }
        
        // ... reste de la logique pour récupérer les stats
    }
}
?>
