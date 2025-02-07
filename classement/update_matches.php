<?php
require_once 'config.php';

try {
    // Ajouter la colonne role si elle n'existe pas
    $sql = "ALTER TABLE matches ADD COLUMN IF NOT EXISTS role VARCHAR(10) DEFAULT NULL AFTER champion_name";
    $pdo->exec($sql);
    
    echo "Colonne 'role' ajoutée avec succès à la table matches!";
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
} 