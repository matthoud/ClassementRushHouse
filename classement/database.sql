-- 1. D'abord, supprimer les tables existantes dans l'ordre inverse des dépendances
DROP TABLE IF EXISTS lp_history;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS champion_stats;
DROP TABLE IF EXISTS role_stats;
DROP TABLE IF EXISTS global_stats;
DROP TABLE IF EXISTS joueurs;
DROP TABLE IF EXISTS seasons;

-- 2. Créer la base de données
CREATE DATABASE IF NOT EXISTS classement;
USE classement;

-- 3. Définir l'encodage par défaut
ALTER DATABASE classement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 4. Créer la table joueurs en premier (table principale)
CREATE TABLE joueurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    puuid VARCHAR(78),
    summoner_id VARCHAR(63),
    tier VARCHAR(20) DEFAULT 'UNRANKED',
    rank VARCHAR(10) DEFAULT '',
    lp INT DEFAULT 0,
    victoires INT DEFAULT 0,
    defaites INT DEFAULT 0,
    total_games INT DEFAULT 0,
    pourcentage_victoire DECIMAL(5,2) DEFAULT 0.00,
    match_details TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    last_match_time DATETIME NULL,
    last_update DATETIME,
    deleted_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 5. Créer les autres tables dans l'ordre
CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    champion_name VARCHAR(50),
    role VARCHAR(20),
    win BOOLEAN,
    kills INT,
    deaths INT,
    assists INT,
    cs INT,
    vision_score INT,
    double_kills INT DEFAULT 0,
    triple_kills INT DEFAULT 0,
    quadra_kills INT DEFAULT 0,
    penta_kills INT DEFAULT 0,
    game_duration INT,
    game_creation DATETIME,
    game_date DATETIME DEFAULT NULL,
    FOREIGN KEY (player_id) REFERENCES joueurs(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE lp_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    tier VARCHAR(20),
    rank VARCHAR(10),
    lp INT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES joueurs(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    season_number INT NOT NULL,
    split_number INT NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NULL,
    patch_start VARCHAR(10) NOT NULL,
    patch_current VARCHAR(10) NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE champion_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    champion_name VARCHAR(50) NOT NULL,
    games_played INT DEFAULT 0,
    wins INT DEFAULT 0,
    losses INT DEFAULT 0,
    kills INT DEFAULT 0,
    deaths INT DEFAULT 0,
    assists INT DEFAULT 0,
    total_cs INT DEFAULT 0,
    total_vision INT DEFAULT 0,
    total_duration INT DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES joueurs(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE role_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    games_played INT DEFAULT 0,
    wins INT DEFAULT 0,
    losses INT DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES joueurs(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE global_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    total_games INT DEFAULT 0,
    wins INT DEFAULT 0,
    losses INT DEFAULT 0,
    kills INT DEFAULT 0,
    deaths INT DEFAULT 0,
    assists INT DEFAULT 0,
    double_kills INT DEFAULT 0,
    triple_kills INT DEFAULT 0,
    quadra_kills INT DEFAULT 0,
    penta_kills INT DEFAULT 0,
    total_cs INT DEFAULT 0,
    total_vision INT DEFAULT 0,
    total_duration INT DEFAULT 0,
    last_calculated DATETIME,
    FOREIGN KEY (player_id) REFERENCES joueurs(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 6. Créer les index
CREATE INDEX idx_matches_player ON matches(player_id);
CREATE INDEX idx_champion_stats_player ON champion_stats(player_id);
CREATE INDEX idx_role_stats_player ON role_stats(player_id);
CREATE INDEX idx_global_stats_player ON global_stats(player_id);
CREATE INDEX idx_lp_history_player ON lp_history(player_id);

-- 7. Créer l'utilisateur et attribuer les droits
CREATE USER IF NOT EXISTS 'classement_user'@'localhost' IDENTIFIED BY 'P@ssw0rdAdminCl0pCorp';
GRANT ALL PRIVILEGES ON classement.* TO 'classement_user'@'localhost';
FLUSH PRIVILEGES;

-- 8. Insérer les données initiales
DELETE FROM seasons;
INSERT INTO seasons (name, season_number, split_number, start_date, end_date, patch_start, patch_current, is_active) VALUES
('Classement Global', 15, 0, '2024-01-09 00:00:00', '2025-11-19 23:59:59', '15.1.1', '15.1.2', TRUE),
('Split 1 2025', 15, 1, '2025-01-29 23:30:00', '2025-03-12 01:00:00', '15.1.1', '15.1.2', TRUE),
('Split 2 2025', 15, 2, '2025-03-13 00:00:00', '2025-05-14 23:59:59', '15.1.1', '15.1.2', TRUE),
('Split 3 2025', 15, 3, '2025-05-15 00:00:00', '2025-07-16 23:59:59', '15.1.1', '15.1.2', TRUE),
('Split 4 2025', 15, 4, '2025-07-17 00:00:00', '2025-09-17 23:59:59', '15.1.1', '15.1.2', TRUE),
('Split 5 2025', 15, 5, '2025-09-18 00:00:00', '2025-11-19 23:59:59', '15.1.1', '15.1.2', TRUE);

-- 1. D'abord, vérifier si les colonnes existent
DESCRIBE joueurs;

-- 2. Si les colonnes n'existent pas ou ne sont pas du bon type, les modifier :
ALTER TABLE joueurs 
MODIFY COLUMN match_details TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
MODIFY COLUMN last_match_time DATETIME;

-- 3. S'assurer que les colonnes acceptent les valeurs NULL
ALTER TABLE joueurs 
MODIFY COLUMN match_details TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
MODIFY COLUMN last_match_time DATETIME NULL;

-- 4. Réinitialiser les colonnes pour s'assurer qu'elles sont vides et bien formatées
UPDATE joueurs 
SET match_details = NULL, 
    last_match_time = NULL;

-- 5. Vérifier l'encodage de la table
ALTER TABLE joueurs 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci; 