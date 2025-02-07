<?php
require_once 'config.php';

try {
    // Vérifier d'abord si les colonnes existent
    $existingColumns = [];
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM joueurs");
    while($column = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $column['Field'];
    }

    // Ajouter uniquement les colonnes manquantes
    $columnsToAdd = [];
    
    if (!in_array('puuid', $existingColumns)) {
        $columnsToAdd[] = "ADD COLUMN puuid VARCHAR(78) AFTER nom";
    }
    
    if (!in_array('summoner_id', $existingColumns)) {
        $columnsToAdd[] = "ADD COLUMN summoner_id VARCHAR(63) AFTER puuid";
    }
    
    if (!in_array('match_details', $existingColumns)) {
        $columnsToAdd[] = "ADD COLUMN match_details TEXT AFTER total_games";
    }
    
    if (!in_array('last_match_time', $existingColumns)) {
        $columnsToAdd[] = "ADD COLUMN last_match_time DATETIME AFTER match_details";
    }

    // Exécuter les modifications si nécessaire
    if (!empty($columnsToAdd)) {
        $alterQuery = "ALTER TABLE joueurs " . implode(", ", $columnsToAdd);
        $pdo->exec($alterQuery);
        echo "Colonnes ajoutées avec succès : " . implode(", ", $columnsToAdd) . "<br>";
    } else {
        echo "Aucune modification nécessaire, toutes les colonnes existent déjà.<br>";
    }

    echo "Base de données mise à jour avec succès!";
} catch (PDOException $e) {
    echo "Erreur lors de la mise à jour de la base de données : " . $e->getMessage();
} 