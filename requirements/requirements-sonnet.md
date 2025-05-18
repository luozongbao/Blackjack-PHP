# BlackJack Game Requirements (PHP)

## Database Schema
### User Table
```sql
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP
);
```

### Session Stats Table
```sql
CREATE TABLE session_stats (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    current_money DECIMAL(10,2) NOT NULL,
    total_won DECIMAL(10,2) DEFAULT 0,
    total_loss DECIMAL(10,2) DEFAULT 0,
    total_bet DECIMAL(10,2) DEFAULT 0,
    games_played INT DEFAULT 0,
    games_won INT DEFAULT 0,
    games_push INT DEFAULT 0,
    games_loss INT DEFAULT 0,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);
```

### All-Time Stats Table
```sql
CREATE TABLE all_time_stats (
    user_id INT PRIMARY KEY,
    total_won DECIMAL(10,2) DEFAULT 0,
    total_loss DECIMAL(10,2) DEFAULT 0,
    total_bet DECIMAL(10,2) DEFAULT 0,
    games_played INT DEFAULT 0,
    games_won INT DEFAULT 0,
    games_push INT DEFAULT 0,
    games_loss INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);
```

### User Settings Table
```sql
CREATE TABLE user_settings (
    user_id INT PRIMARY KEY,
    deck_count INT DEFAULT 6,
    shuffle_method ENUM('auto', 'shoe') DEFAULT 'auto',
    deck_penetration INT DEFAULT 80,
    deal_style ENUM('american', 'european', 'macau') DEFAULT 'american',
    dealer_draw_to ENUM('any17', 'hard17') DEFAULT 'hard17',
    blackjack_payout DECIMAL(3,2) DEFAULT 1.5,
    surrender_type ENUM('early', 'late', 'none') DEFAULT 'late',
    double_after_split BOOLEAN DEFAULT true,
    allow_insurance BOOLEAN DEFAULT true,
    double_rules ENUM('any', 'nine_to_eleven') DEFAULT 'any',
    max_splits INT DEFAULT 3,
    initial_money DECIMAL(10,2) DEFAULT 10000.00,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);
```

## Security Requirements (Added)
1. Authentication
   - Password Requirements:
     - Minimum 8 characters
     - Must contain at least one uppercase letter
     - Must contain at least one number
     - Must contain at least one special character
   - Password hashing using PHP's password_hash() with BCRYPT
   - Session management with PHP sessions
   - CSRF protection for all forms

2. Input Validation
   - Server-side validation for all form inputs
   - Prepared statements for all database queries
   - XSS protection through HTML escaping
   - Validation of all game actions and bets

## Game Decision Logic (Added)
### Win Conditions
1. Player Wins (1.5x payout for natural blackjack, 1x for others):
   - Player has blackjack, dealer doesn't
   - Player's final hand value <= 21 and higher than dealer's
   - Dealer busts (>21) and player <= 21

2. Dealer Wins:
   - Dealer has blackjack, player doesn't
   - Dealer's final hand value <= 21 and higher than player's
   - Player busts (>21)

3. Push (Original bet returned):
   - Both player and dealer have blackjack
   - Both player and dealer have same final hand value

### Insurance Resolution
1. If dealer has blackjack:
   - Insurance pays 2:1
   - Original bet loses unless player also has blackjack
2. If dealer doesn't have blackjack:
   - Insurance bet loses
   - Game continues normally

### Error Handling (Added)
1. Client-Side Errors:
   - Invalid bet amounts (less than minimum or more than current money)
   - Invalid game actions (attempting unavailable moves)
   - Connection issues during game

2. Server-Side Errors:
   - Database connection failures
   - Invalid session states
   - Concurrent game access attempts

3. Recovery Procedures:
   - Auto-save game state after each action
   - Ability to restore interrupted games
   - Transaction rollback for failed bet operations

## API Endpoints (Added)
```
POST /api/game/start         # Start new game
POST /api/game/bet          # Place bet
POST /api/game/hit          # Request additional card
POST /api/game/stand        # Stand current hand
POST /api/game/double       # Double down
POST /api/game/split        # Split pairs
POST /api/game/surrender    # Surrender hand
POST /api/game/insurance    # Place insurance bet
GET  /api/game/state       # Get current game state
GET  /api/user/stats       # Get user statistics
POST /api/user/settings    # Update user settings
```

## Performance Requirements (Added)
1. Response Times:
   - Card dealing animation: < 500ms
   - Action button response: < 200ms
   - Statistics updates: < 1 second

2. Concurrent Users:
   - Support minimum 100 simultaneous games
   - Handle 1000 concurrent database connections

3. Data Persistence:
   - Transaction logging for all bets
   - Automatic backup of user statistics
   - Session recovery system

## Testing Requirements (Added)
1. Unit Tests:
   - Card dealing logic
   - Scoring calculations
   - Bet processing

2. Integration Tests:
   - Game flow scenarios
   - Database transactions
   - User session management

3. Performance Tests:
   - Concurrent user simulation
   - Database load testing
   - Network latency handling