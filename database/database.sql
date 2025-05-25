-- Blackjack Game Database Schema

-- Create users table for user authentication and profile information
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,  -- Will store hashed password
    display_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    reset_token VARCHAR(255) NULL,
    reset_token_expiry TIMESTAMP NULL
);

-- Create user settings table
CREATE TABLE IF NOT EXISTS user_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    decks_per_shoe INT NOT NULL DEFAULT 6,
    shuffle_method ENUM('auto', 'shoe') NOT NULL DEFAULT 'auto',
    deck_penetration INT NOT NULL DEFAULT 80,  -- Percentage value (default 80%)
    deal_style ENUM('american', 'european', 'macau') NOT NULL DEFAULT 'american',
    dealer_draw_to ENUM('any17', 'hard17') NOT NULL DEFAULT 'any17',
    blackjack_payout ENUM('3:2', '1:1') NOT NULL DEFAULT '3:2',
    surrender_option ENUM('early', 'late', 'none') NOT NULL DEFAULT 'early',
    double_after_split BOOLEAN NOT NULL DEFAULT TRUE,
    allow_insurance BOOLEAN NOT NULL DEFAULT TRUE,
    double_on ENUM('any', '9-10-11') NOT NULL DEFAULT 'any',
    max_splits INT NOT NULL DEFAULT 3,
    initial_money DECIMAL(12,2) NOT NULL DEFAULT 10000.00,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create game sessions table
CREATE TABLE IF NOT EXISTS game_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    
    -- Session Money Stats
    current_money DECIMAL(12,2) NOT NULL DEFAULT 10000.00,
    session_total_loss DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    session_total_won DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    session_total_bet DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    
    -- Session Game Stats
    session_games_played INT NOT NULL DEFAULT 0,
    session_games_won INT NOT NULL DEFAULT 0,
    session_games_push INT NOT NULL DEFAULT 0,
    session_games_lost INT NOT NULL DEFAULT 0,

    -- Previous Game Stats (for display purposes)
    previous_game_won DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- Actual winnings from previous game (excludes original bet)
    accumulated_previous_wins DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- Running total of all previous game actual winnings
    
    -- All-time Money Stats (updated at session end)
    all_time_total_loss DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    all_time_total_won DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    all_time_total_bet DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    
    -- All-time Game Stats (updated at session end)
    all_time_games_played INT NOT NULL DEFAULT 0,
    all_time_games_won INT NOT NULL DEFAULT 0,
    all_time_games_push INT NOT NULL DEFAULT 0,
    all_time_games_lost INT NOT NULL DEFAULT 0,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create game hands table to track individual game states
CREATE TABLE IF NOT EXISTS game_hands (
    hand_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    game_number INT NOT NULL,  -- Incremental game number within a session
    start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    hand_status ENUM('active', 'won', 'lost', 'push', 'blackjack', 'surrender') NULL,
    
    -- Game state
    dealer_cards TEXT NULL,  -- JSON array of cards
    dealer_score INT NULL,
    dealer_has_blackjack BOOLEAN DEFAULT FALSE,
    
    player_hands TEXT NULL,  -- JSON array of hands with cards and bets
    initial_bet DECIMAL(12,2) NOT NULL,
    insurance_bet DECIMAL(12,2) DEFAULT 0.00,
    total_bet DECIMAL(12,2) NOT NULL,
    total_won DECIMAL(12,2) DEFAULT 0.00,
    
    -- Settings used for this game (snapshot)
    settings_snapshot TEXT NOT NULL,  -- JSON of settings at game time
    
    FOREIGN KEY (session_id) REFERENCES game_sessions(session_id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_sessions_user_id ON game_sessions(user_id);
CREATE INDEX idx_game_hands_session_id ON game_hands(session_id);

-- Create password reset attempts table to track and limit attempts
CREATE TABLE IF NOT EXISTS password_reset_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (ip_address, attempt_time)
);