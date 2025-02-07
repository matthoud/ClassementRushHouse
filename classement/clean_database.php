<?php
require_once 'config.php';

try {
    // 1. Supprimer les doublons dans matches en gardant l'entrée la plus récente
    $sql = "DELETE m1 FROM matches m1
            INNER JOIN matches m2 
            WHERE m1.player_id = m2.player_id 
            AND m1.game_creation = m2.game_creation 
            AND m1.id < m2.id";
    $pdo->exec($sql);

    // 2. Supprimer les matches avec game_duration < 300 (parties annulées)
    $sql = "DELETE FROM matches WHERE game_duration < 300";
    $pdo->exec($sql);

    // 3. Mettre à jour les statistiques des joueurs
    $sql = "UPDATE joueurs j 
            SET 
            total_games = (
                SELECT COUNT(*) 
                FROM matches m 
                WHERE m.player_id = j.id 
                AND m.game_duration >= 300
            ),
            victoires = (
                SELECT COUNT(*) 
                FROM matches m 
                WHERE m.player_id = j.id 
                AND m.win = 1 
                AND m.game_duration >= 300
            ),
            defaites = (
                SELECT COUNT(*) 
                FROM matches m 
                WHERE m.player_id = j.id 
                AND m.win = 0 
                AND m.game_duration >= 300
            ),
            pourcentage_victoire = (
                SELECT (SUM(CASE WHEN m.win = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*))
                FROM matches m 
                WHERE m.player_id = j.id 
                AND m.game_duration >= 300
            )";
    $pdo->exec($sql);

    echo "Nettoyage de la base de données terminé avec succès.\n";

} catch (PDOException $e) {
    echo "Erreur lors du nettoyage : " . $e->getMessage() . "\n";
}
?> 