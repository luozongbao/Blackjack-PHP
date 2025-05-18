-- Database creation script for Blackjack-PHP
-- Created on May 18, 2025

-- -- Create and use the blackjack database
-- CREATE DATABASE IF NOT EXISTS blackjack;
-- USE blackjack;

-- Drop tables if they exist to avoid conflicts
DROP TABLE IF EXISTS session_stats;
DROP TABLE IF EXISTS all_time_stats;
DROP TABLE IF EXISTS user_settings;
DROP TABLE IF EXISTS game_history;
DROP TABLE IF EXISTS users;

-- Users table for authentication and profile information
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL, -- Stores hashed password
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User game settings
CREATE TABLE user_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    deck_count INT NOT NULL DEFAULT 6, -- Number of decks per shoe
    shuffle_method ENUM('auto', 'shoe') NOT NULL DEFAULT 'shoe', -- 'auto' for every game, 'shoe' for per penetration
    deck_penetration INT NOT NULL DEFAULT 80, -- Percentage of cards played before shuffling (0-100)
    deal_style ENUM('american', 'european', 'macau') NOT NULL DEFAULT 'american',
    dealer_draw_to ENUM('any17', 'hard17') NOT NULL DEFAULT 'hard17',
    blackjack_pay ENUM('3:2', '1:1') NOT NULL DEFAULT '3:2',
    allow_surrender ENUM('early', 'late', 'none') NOT NULL DEFAULT 'late',
    allow_double_after_split BOOLEAN NOT NULL DEFAULT TRUE,
    allow_insurance BOOLEAN NOT NULL DEFAULT TRUE,
    allow_double ENUM('any', '9-11') NOT NULL DEFAULT 'any', -- 'any' for any two cards, '9-11' for hand values 9,10,11
    max_splits INT NOT NULL DEFAULT 3, -- Maximum number of splits allowed
    initial_money DECIMAL(15,2) NOT NULL DEFAULT 10000.00, -- Starting money for a new session
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Session statistics (reset when the player restarts session)
CREATE TABLE session_stats (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    current_money DECIMAL(15,2) NOT NULL DEFAULT 10000.00,
    total_won DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_loss DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_bet DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    games_played INT NOT NULL DEFAULT 0,
    games_won INT NOT NULL DEFAULT 0,
    games_push INT NOT NULL DEFAULT 0,
    games_loss INT NOT NULL DEFAULT 0,
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_played TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- All-time statistics (cumulative, never reset unless explicitly requested)
CREATE TABLE all_time_stats (
    stats_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_won DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_loss DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_bet DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    games_played INT NOT NULL DEFAULT 0,
    games_won INT NOT NULL DEFAULT 0,
    games_push INT NOT NULL DEFAULT 0,
    games_loss INT NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Game history table to track individual games
CREATE TABLE game_history (
    game_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id INT NOT NULL,
    bet_amount DECIMAL(15,2) NOT NULL,
    insurance_bet DECIMAL(15,2) DEFAULT 0.00,
    result ENUM('win', 'loss', 'push', 'blackjack', 'surrender') NOT NULL,
    profit_loss DECIMAL(15,2) NOT NULL, -- Positive for wins, negative for losses
    player_cards TEXT NOT NULL, -- JSON format storing dealt cards
    dealer_cards TEXT NOT NULL, -- JSON format storing dealt cards
    game_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES session_stats(session_id)
);

-- Calculated views for easy retrieval of ROI and winning per game
CREATE OR REPLACE VIEW user_session_stats_view AS
SELECT 
    s.*,
    CASE 
        WHEN s.total_bet > 0 THEN (s.total_won - s.total_loss) / s.total_bet * 100 
        ELSE 0 
    END AS roi_percentage,
    CASE 
        WHEN s.games_played > 0 THEN (s.total_won - s.total_loss) / s.games_played 
        ELSE 0 
    END AS winning_per_game
FROM 
    session_stats s;

CREATE OR REPLACE VIEW user_all_time_stats_view AS
SELECT 
    a.*,
    CASE 
        WHEN a.total_bet > 0 THEN (a.total_won - a.total_loss) / a.total_bet * 100 
        ELSE 0 
    END AS roi_percentage,
    CASE 
        WHEN a.games_played > 0 THEN (a.total_won - a.total_loss) / a.games_played 
        ELSE 0 
    END AS winning_per_game
FROM 
    all_time_stats a;

-- Trigger to create default settings and stats entries when a new user is created
DELIMITER //
CREATE TRIGGER after_user_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    -- Insert default settings
    INSERT INTO user_settings (user_id) VALUES (NEW.user_id);
    
    -- Insert default session stats
    INSERT INTO session_stats (user_id) VALUES (NEW.user_id);
    
    -- Insert default all-time stats
    INSERT INTO all_time_stats (user_id) VALUES (NEW.user_id);
END//
DELIMITER ;

-- Sample data for testing (optional, can be commented out in production)
INSERT INTO users (username, display_name, password, email)
VALUES 
    ('player1', 'John Doe', '$2y$10$abcdefghijklmnopqrstuv', 'john@example.com'),
    ('player2', 'Jane Smith', '$2y$10$abcdefghijklmnopqrstuv', 'jane@example.com');