<?php
session_start();
require_once 'config.php';

// Vérification de sécurité
$authorized = false;

// Autoriser si exécuté en ligne de commande
if (php_sapi_name() === 'cli') {
    $authorized = true;
}
// Autoriser si admin connecté
elseif (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    $authorized = true;
}
// Demander un mot de passe si accès via navigateur
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === 'votre_mot_de_passe_secret') {  // Changez ce mot de passe!
        $authorized = true;
    }
}

if (!$authorized) {
    if (php_sapi_name() !== 'cli') {
        // Afficher un formulaire de connexion
        echo '<form method="post">
            <p>Entrez le mot de passe pour recréer la table :</p>
            <input type="password" name="password" required>
            <button type="submit">Recréer la table</button>
        </form>';
    }
    exit;
}

try {
    // Désactiver les contraintes de clé étrangère
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Sauvegarder les données existantes et les relations
    $oldData = $pdo->query("SELECT * FROM joueurs")->fetchAll(PDO::FETCH_ASSOC);
    
    // Sauvegarder les données des tables liées
    $oldLpHistory = $pdo->query("SELECT * FROM lp_history")->fetchAll(PDO::FETCH_ASSOC);
    $oldMatches = $pdo->query("SELECT * FROM matches")->fetchAll(PDO::FETCH_ASSOC);
    
    // Supprimer la table existante
    $pdo->exec("DROP TABLE IF EXISTS joueurs");
    
    // Créer la nouvelle table avec toutes les colonnes nécessaires
    $createTable = "CREATE TABLE joueurs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nom VARCHAR(255) NOT NULL,
        puuid VARCHAR(78),
        summoner_id VARCHAR(63),
        tier VARCHAR(20) DEFAULT 'UNRANKED',
        rank VARCHAR(5),
        lp INT DEFAULT 0,
        victoires INT DEFAULT 0,
        defaites INT DEFAULT 0,
        total_games INT DEFAULT 0,
        pourcentage_victoire DECIMAL(5,2) DEFAULT 0.00,
        match_details TEXT,
        last_match_time DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTable);
    
    // Réinsérer les anciennes données
    if (!empty($oldData)) {
        foreach ($oldData as $row) {
            $columns = array_keys($row);
            $values = array_map(function($col) { return ":$col"; }, $columns);
            
            $sql = "INSERT INTO joueurs (" . implode(", ", $columns) . ") 
                    VALUES (" . implode(", ", $values) . ")";
            
            $stmt = $pdo->prepare($sql);
            foreach ($row as $column => $value) {
                $stmt->bindValue(":$column", $value);
            }
            $stmt->execute();
        }
        echo count($oldData) . " joueurs restaurés.<br>";
    }

    // Réactiver les contraintes de clé étrangère
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    
    // Restaurer les données des tables liées
    if (!empty($oldLpHistory)) {
        foreach ($oldLpHistory as $row) {
            $sql = "INSERT INTO lp_history SET player_id = :player_id, lp = :lp, tier = :tier, rank = :rank, timestamp = :timestamp";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($row);
        }
        echo count($oldLpHistory) . " entrées d'historique LP restaurées.<br>";
    }

    if (!empty($oldMatches)) {
        foreach ($oldMatches as $row) {
            $columns = array_keys($row);
            $values = array_map(function($col) { return ":$col"; }, $columns);
            
            $sql = "INSERT INTO matches (" . implode(", ", $columns) . ") 
                    VALUES (" . implode(", ", $values) . ")";
            
            $stmt = $pdo->prepare($sql);
            foreach ($row as $column => $value) {
                $stmt->bindValue(":$column", $value);
            }
            $stmt->execute();
        }
        echo count($oldMatches) . " matches restaurés.<br>";
    }
    
    echo "Table recréée avec succès!";
    
} catch (PDOException $e) {
    // En cas d'erreur, réactiver les contraintes de clé étrangère
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "Erreur lors de la recréation de la table : " . $e->getMessage();
} 