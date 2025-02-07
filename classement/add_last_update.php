<?php
require_once 'config.php';

try {
    $sql = "ALTER TABLE joueurs ADD COLUMN IF NOT EXISTS last_update DATETIME DEFAULT NULL";
    $pdo->exec($sql);
    echo "Colonne last_update ajoutée avec succès!";
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
} 