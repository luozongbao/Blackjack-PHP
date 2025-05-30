<?php
/**
 * Settings Page
 * 
 * Allows users to configure game settings and manage game sessions.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'includes/database.php';
require_once 'classes/analytics_class.php';

// Track user activity
$analytics = new Analytics();
$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$analytics->trackUserSession($_SESSION['user_id'], $ipAddress, $userAgent);

// Set page title
$pageTitle = 'Game Settings';

// Initialize variables
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

try {
    $db = getDb();
    
    // Get user settings
    $settingsStmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = :user_id");
    $settingsStmt->bindParam(':user_id', $userId);
    $settingsStmt->execute();
    $settings = $settingsStmt->fetch();
    
    // Get current session info
    $sessionStmt = $db->prepare("
        SELECT * FROM game_sessions 
        WHERE user_id = :user_id AND is_active = 1
        ORDER BY start_time DESC LIMIT 1
    ");
    $sessionStmt->bindParam(':user_id', $userId);
    $sessionStmt->execute();
    $session = $sessionStmt->fetch();
    
    // If no active session, create one
    if (!$session) {
        // Create new session with initial money from settings
        $createSessionStmt = $db->prepare("
            INSERT INTO game_sessions (user_id, start_time, is_active, current_money) 
            VALUES (:user_id, CURRENT_TIMESTAMP, 1, :initial_money)
        ");
        $createSessionStmt->bindParam(':user_id', $userId);
        $createSessionStmt->bindParam(':initial_money', $settings['initial_money']);
        $createSessionStmt->execute();
        
        // Reload the session data
        $sessionStmt->execute();
        $session = $sessionStmt->fetch();
    }
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update game settings
    if (isset($_POST['update_settings'])) {
        try {
            // Extract form data
            $decksPerShoe = intval($_POST['decks_per_shoe']);
            $shuffleMethod = $_POST['shuffle_method'];
            $deckPenetration = intval($_POST['deck_penetration']);
            $dealStyle = $_POST['deal_style'];
            $dealerDrawTo = $_POST['dealer_draw_to'];
            $blackjackPayout = $_POST['blackjack_payout'];
            $surrenderOption = $_POST['surrender_option'];
            $doubleAfterSplit = isset($_POST['double_after_split']) ? 1 : 0;
            $allowInsurance = isset($_POST['allow_insurance']) ? 1 : 0;
            $doubleOn = $_POST['double_on'];
            $maxSplits = intval($_POST['max_splits']);
            $initialMoney = intval($_POST['initial_money']);
            $tableMinBet = intval($_POST['table_min_bet']);
            $tableMaxBet = intval($_POST['table_max_bet']);
            
            // Basic validation
            if ($decksPerShoe < 1 || $decksPerShoe > 8) {
                $error = 'Number of decks must be between 1 and 8.';
            } elseif ($deckPenetration < 50 || $deckPenetration > 100) {
                $error = 'Deck penetration must be between 50% and 100%.';
            } elseif ($maxSplits < 1 || $maxSplits > 4) {
                $error = 'Maximum splits must be between 1 and 4.';
            } elseif ($initialMoney < 100 || $initialMoney > 1000000) {
                $error = 'Initial money must be between $100 and $1,000,000.';
            } elseif ($initialMoney % 100 !== 0) {
                $error = 'Initial money must be a multiple of $100.';
            } elseif ($tableMinBet < 100) {
                $error = 'Table minimum bet must be at least $100.';
            } elseif ($tableMinBet % 100 !== 0) {
                $error = 'Table minimum bet must be a multiple of $100.';
            } elseif ($tableMaxBet < (2 * $tableMinBet)) {
                $error = 'Table maximum bet must be at least 2 times the minimum bet ($' . number_format(2 * $tableMinBet, 2) . ').';
            } elseif ($tableMaxBet % 100 !== 0) {
                $error = 'Table maximum bet must be a multiple of $100.';
            } elseif ($tableMinBet > $tableMaxBet) {
                $error = 'Table minimum bet cannot be greater than maximum bet.';
            } else {
                // Update settings
                $updateStmt = $db->prepare("
                    UPDATE user_settings SET 
                    decks_per_shoe = :decks_per_shoe,
                    shuffle_method = :shuffle_method,
                    deck_penetration = :deck_penetration,
                    deal_style = :deal_style,
                    dealer_draw_to = :dealer_draw_to,
                    blackjack_payout = :blackjack_payout,
                    surrender_option = :surrender_option,
                    double_after_split = :double_after_split,
                    allow_insurance = :allow_insurance,
                    double_on = :double_on,
                    max_splits = :max_splits,
                    initial_money = :initial_money,
                    table_min_bet = :table_min_bet,
                    table_max_bet = :table_max_bet
                    WHERE user_id = :user_id
                ");
                
                $updateStmt->bindParam(':decks_per_shoe', $decksPerShoe);
                $updateStmt->bindParam(':shuffle_method', $shuffleMethod);
                $updateStmt->bindParam(':deck_penetration', $deckPenetration);
                $updateStmt->bindParam(':deal_style', $dealStyle);
                $updateStmt->bindParam(':dealer_draw_to', $dealerDrawTo);
                $updateStmt->bindParam(':blackjack_payout', $blackjackPayout);
                $updateStmt->bindParam(':surrender_option', $surrenderOption);
                $updateStmt->bindParam(':double_after_split', $doubleAfterSplit);
                $updateStmt->bindParam(':allow_insurance', $allowInsurance);
                $updateStmt->bindParam(':double_on', $doubleOn);
                $updateStmt->bindParam(':max_splits', $maxSplits);
                $updateStmt->bindParam(':initial_money', $initialMoney);
                $updateStmt->bindParam(':table_min_bet', $tableMinBet);
                $updateStmt->bindParam(':table_max_bet', $tableMaxBet);
                $updateStmt->bindParam(':user_id', $userId);
                $updateStmt->execute();
                
                // Reload settings
                $settingsStmt->execute();
                $settings = $settingsStmt->fetch();
                
                $success = 'Game settings updated successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error updating settings: ' . $e->getMessage();
        }
    }
    
    // Restart session
    if (isset($_POST['restart_session'])) {
        try {
            // Check if there's an ongoing game
            $ongoingGame = false;
            if ($session && $session['game_state']) {
                $gameStateData = json_decode($session['game_state'], true);
                if ($gameStateData && isset($gameStateData['gameState']) && 
                    in_array($gameStateData['gameState'], ['player_turn', 'dealer_turn', 'dealing'])) {
                    $ongoingGame = true;
                }
            }
            
            // If ongoing game and no explicit confirmation, return error
            if ($ongoingGame && !isset($_POST['confirm_ongoing_game'])) {
                $error = 'There is an ongoing game in progress. Are you sure you want to restart the session? This will forfeit the current game.';
            } else {
                // Clear any ongoing game from session
                if ($ongoingGame) {
                    // Clear game state
                    $clearGameStmt = $db->prepare("
                        UPDATE game_sessions 
                        SET game_state = NULL
                        WHERE session_id = :session_id
                    ");
                    $clearGameStmt->bindParam(':session_id', $session['session_id']);
                    $clearGameStmt->execute();
                }
                
                // End current session
                $endSessionStmt = $db->prepare("
                    UPDATE game_sessions 
                    SET is_active = 0, end_time = CURRENT_TIMESTAMP 
                    WHERE session_id = :session_id
                ");
                $endSessionStmt->bindParam(':session_id', $session['session_id']);
                $endSessionStmt->execute();
            
            // Create new session with initial money
            $createSessionStmt = $db->prepare("
                INSERT INTO game_sessions (
                    user_id, start_time, is_active, current_money,
                    all_time_total_loss, all_time_total_won, all_time_total_bet,
                    all_time_games_played, all_time_games_won, all_time_games_push, all_time_games_lost
                ) VALUES (
                    :user_id, CURRENT_TIMESTAMP, 1, :initial_money,
                    :all_time_total_loss, :all_time_total_won, :all_time_total_bet,
                    :all_time_games_played, :all_time_games_won, :all_time_games_push, :all_time_games_lost
                )
            ");
            
            $createSessionStmt->bindParam(':user_id', $userId);
            $createSessionStmt->bindParam(':initial_money', $settings['initial_money']);
            $createSessionStmt->bindParam(':all_time_total_loss', $session['all_time_total_loss']);
            $createSessionStmt->bindParam(':all_time_total_won', $session['all_time_total_won']);
            $createSessionStmt->bindParam(':all_time_total_bet', $session['all_time_total_bet']);
            $createSessionStmt->bindParam(':all_time_games_played', $session['all_time_games_played']);
            $createSessionStmt->bindParam(':all_time_games_won', $session['all_time_games_won']);
            $createSessionStmt->bindParam(':all_time_games_push', $session['all_time_games_push']);
            $createSessionStmt->bindParam(':all_time_games_lost', $session['all_time_games_lost']);
            
            $createSessionStmt->execute();
            
            // Reload session data
            $sessionStmt->execute();
            $session = $sessionStmt->fetch();
            
            $success = 'Session restarted successfully! Your balance has been reset to $' . number_format($settings['initial_money'], 2);
            }
        } catch (PDOException $e) {
            $error = 'Error restarting session: ' . $e->getMessage();
        }
    }
    }
    
    // Reset all-time stats
    if (isset($_POST['reset_all_time_stats'])) {
        try {
            // Update current session to reset all-time stats
            $resetStatsStmt = $db->prepare("
                UPDATE game_sessions 
                SET all_time_total_loss = 0,
                    all_time_total_won = 0,
                    all_time_total_bet = 0,
                    all_time_games_played = 0,
                    all_time_games_won = 0,
                    all_time_games_push = 0,
                    all_time_games_lost = 0
                WHERE session_id = :session_id
            ");
            $resetStatsStmt->bindParam(':session_id', $session['session_id']);
            $resetStatsStmt->execute();
            
            // Reload session data
            $sessionStmt->execute();
            $session = $sessionStmt->fetch();
            
            $success = 'All-time statistics have been reset successfully!';
        } catch (PDOException $e) {
            $error = 'Error resetting statistics: ' . $e->getMessage();
        }
    }

include_once 'includes/header.php';
?>

<div class="card mb-3">
    <h1>Game Settings</h1>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
</div>

<div class="settings-container">
    <form method="post" action="" id="settingsForm">
        <div class="row">
            <div class="col-md-6">
                <!-- Card Deck Settings -->
                <div class="card mb-3">
                    <h3>Deck Settings</h3>
                    
                    <div class="form-group">
                        <label for="decks_per_shoe">Number of Decks per Shoe:</label>
                        <select id="decks_per_shoe" name="decks_per_shoe" class="form-control">
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($settings['decks_per_shoe'] == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?> <?php echo ($i == 1) ? 'Deck' : 'Decks'; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="shuffle_method">Shuffling Method:</label>
                        <select id="shuffle_method" name="shuffle_method" class="form-control">
                            <option value="auto" <?php echo ($settings['shuffle_method'] == 'auto') ? 'selected' : ''; ?>>
                                Auto-shuffle Machine (Shuffle after every game)
                            </option>
                            <option value="shoe" <?php echo ($settings['shuffle_method'] == 'shoe') ? 'selected' : ''; ?>>
                                Shuffle Every Shoe
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="penetrationGroup">
                        <label for="deck_penetration">Deck Penetration (%):</label>
                        <input type="range" id="deck_penetration" name="deck_penetration" min="50" max="100" 
                               value="<?php echo $settings['deck_penetration']; ?>" class="form-range">
                        <div class="range-value">
                            <span id="penetrationValue"><?php echo $settings['deck_penetration']; ?>%</span>
                        </div>
                        <small>How much of the shoe is dealt before reshuffling (only applies to "Shuffle Every Shoe" method)</small>
                    </div>
                </div>
                
                <!-- Deal Style -->
                <div class="card mb-3">
                    <h3>Deal Style</h3>
                    
                    <div class="form-group">
                        <div class="radio-option">
                            <input type="radio" id="deal_american" name="deal_style" value="american" 
                                   <?php echo ($settings['deal_style'] == 'american') ? 'checked' : ''; ?>>
                            <label for="deal_american">American Style</label>
                            <small>Dealer gets two cards initially (one face up, one face down). Dealer checks for blackjack if face up card is 10 or Ace.</small>
                        </div>
                        
                        <div class="radio-option">
                            <input type="radio" id="deal_european" name="deal_style" value="european" 
                                   <?php echo ($settings['deal_style'] == 'european') ? 'checked' : ''; ?>>
                            <label for="deal_european">European Style</label>
                            <small>Dealer gets only one card initially. If dealer gets blackjack, player loses all bets including splits and doubles.</small>
                        </div>
                        
                        <div class="radio-option">
                            <input type="radio" id="deal_macau" name="deal_style" value="macau" 
                                   <?php echo ($settings['deal_style'] == 'macau') ? 'checked' : ''; ?>>
                            <label for="deal_macau">Macau Style</label>
                            <small>Dealer gets only one card initially. If dealer gets blackjack, player loses only original bet (not split or double bets).</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- Game Rules -->
                <div class="card mb-3">
                    <h3>Game Rules</h3>
                    
                    <div class="form-group">
                        <label for="dealer_draw_to">Dealer draws to:</label>
                        <select id="dealer_draw_to" name="dealer_draw_to" class="form-control">
                            <option value="any17" <?php echo ($settings['dealer_draw_to'] == 'any17') ? 'selected' : ''; ?>>
                                Any 17 (Dealer stands on any 17, hard or soft)
                            </option>
                            <option value="hard17" <?php echo ($settings['dealer_draw_to'] == 'hard17') ? 'selected' : ''; ?>>
                                Hard 17 (Dealer hits on soft 17)
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="blackjack_payout">Blackjack payout:</label>
                        <select id="blackjack_payout" name="blackjack_payout" class="form-control">
                            <option value="3:2" <?php echo ($settings['blackjack_payout'] == '3:2') ? 'selected' : ''; ?>>3:2 (Traditional payout)</option>
                            <option value="1:1" <?php echo ($settings['blackjack_payout'] == '1:1') ? 'selected' : ''; ?>>1:1 (Even money)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="surrender_option">Surrender option:</label>
                        <select id="surrender_option" name="surrender_option" class="form-control">
                            <option value="early" <?php echo ($settings['surrender_option'] == 'early') ? 'selected' : ''; ?>>Allow Early Surrender</option>
                            <option value="late" <?php echo ($settings['surrender_option'] == 'late') ? 'selected' : ''; ?>>Allow Late Surrender</option>
                            <option value="none" <?php echo ($settings['surrender_option'] == 'none') ? 'selected' : ''; ?>>No Surrender</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-option">
                            <input type="checkbox" id="double_after_split" name="double_after_split" value="1" 
                                   <?php echo ($settings['double_after_split']) ? 'checked' : ''; ?>>
                            <label for="double_after_split">Allow Double After Split</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-option">
                            <input type="checkbox" id="allow_insurance" name="allow_insurance" value="1" 
                                   <?php echo ($settings['allow_insurance']) ? 'checked' : ''; ?>>
                            <label for="allow_insurance">Allow Insurance</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="double_on">Allow Double:</label>
                        <select id="double_on" name="double_on" class="form-control">
                            <option value="any" <?php echo ($settings['double_on'] == 'any') ? 'selected' : ''; ?>>On Any Two Cards</option>
                            <option value="9-10-11" <?php echo ($settings['double_on'] == '9-10-11') ? 'selected' : ''; ?>>Only on 9, 10, or 11 Total</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_splits">Maximum Splits:</label>
                        <select id="max_splits" name="max_splits" class="form-control">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($settings['max_splits'] == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Money Settings -->
                <div class="card mb-3">
                    <h3>Money Settings</h3>
                    
                    <div class="form-group">
                        <label for="initial_money">Initial Money ($):</label>
                        <input type="number" id="initial_money" name="initial_money" value="<?php echo $settings['initial_money']; ?>" 
                               min="100" max="1000000" step="100" class="form-control">
                        <small>This amount is used when starting a new session. Must be a multiple of $100.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="table_min_bet">Table Minimum Bet ($):</label>
                        <input type="number" id="table_min_bet" name="table_min_bet" value="<?php echo $settings['table_min_bet']; ?>" 
                               min="100" step="100" class="form-control">
                        <small>Minimum bet allowed at the table (must be at least $100 and a multiple of $100).</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="table_max_bet">Table Maximum Bet ($):</label>
                        <input type="number" id="table_max_bet" name="table_max_bet" value="<?php echo $settings['table_max_bet']; ?>" 
                               min="200" step="100" class="form-control">
                        <small>Maximum bet allowed at the table (must be at least 2 times the minimum bet and a multiple of $100).</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Save Settings Button -->
        <div class="card mb-3">
            <button type="submit" name="update_settings" class="btn btn-block">Save Settings</button>
        </div>
    </form>
    
    <!-- Session Management Buttons -->
    <div class="card mb-3">
        <h3>Session Management</h3>
        <p>Current Money: <span class="text-primary">$<?php echo number_format($session['current_money'], 2); ?></span></p>
        
        <form method="post" action="" class="mb-3" onsubmit="return confirmSessionRestart();">
            <input type="hidden" name="confirm_ongoing_game" id="confirm_ongoing_game" value="">
            <button type="submit" name="restart_session" class="btn btn-secondary">Restart Session</button>
            <small>This will reset your current money to the initial amount and clear all session statistics.</small>
        </form>
        
        <form method="post" action="" onsubmit="return confirm('WARNING: Are you absolutely sure you want to reset ALL-TIME statistics? This cannot be undone!');">
            <button type="submit" name="reset_all_time_stats" class="btn btn-danger">Reset All-Time Statistics</button>
            <small>This will permanently erase all of your historical game statistics.</small>
        </form>
    </div>
</div>

<script>
// Enhanced session restart confirmation
function confirmSessionRestart() {
    <?php if ($session && $session['game_state']): ?>
        <?php 
        $gameStateData = json_decode($session['game_state'], true);
        $isOngoingGame = $gameStateData && isset($gameStateData['gameState']) && 
                        in_array($gameStateData['gameState'], ['player_turn', 'dealer_turn', 'dealing']);
        ?>
        <?php if ($isOngoingGame): ?>
            const confirmed = confirm('WARNING: You have an ongoing game in progress!\n\nRestarting the session will forfeit your current game and reset your money to the initial amount.\n\nAre you sure you want to continue?');
            if (confirmed) {
                document.getElementById('confirm_ongoing_game').value = '1';
                return true;
            }
            return false;
        <?php else: ?>
            return confirm('Are you sure you want to restart the session? This will reset your current money to the initial amount and clear all session statistics.');
        <?php endif; ?>
    <?php else: ?>
        return confirm('Are you sure you want to restart the session? This will reset your current money to the initial amount and clear all session statistics.');
    <?php endif; ?>
}

// Validate table betting limits
function validateTableLimits() {
    const minBet = parseInt(document.getElementById('table_min_bet').value) || 0;
    const maxBet = parseInt(document.getElementById('table_max_bet').value) || 0;
    const initialMoney = parseInt(document.getElementById('initial_money').value) || 0;
    const minBetField = document.getElementById('table_min_bet');
    const maxBetField = document.getElementById('table_max_bet');
    const initialMoneyField = document.getElementById('initial_money');
    
    // Remove existing error styling
    minBetField.classList.remove('is-invalid');
    maxBetField.classList.remove('is-invalid');
    initialMoneyField.classList.remove('is-invalid');
    
    // Remove existing error messages
    const existingErrors = document.querySelectorAll('.table-bet-error');
    existingErrors.forEach(error => error.remove());
    
    let isValid = true;
    
    // Validate initial money
    if (initialMoney % 100 !== 0) {
        initialMoneyField.classList.add('is-invalid');
        const errorMsg = document.createElement('div');
        errorMsg.className = 'invalid-feedback table-bet-error';
        errorMsg.textContent = 'Initial money must be a multiple of $100';
        initialMoneyField.parentNode.appendChild(errorMsg);
        isValid = false;
    }
    
    // Validate minimum bet
    if (minBet < 100) {
        minBetField.classList.add('is-invalid');
        const errorMsg = document.createElement('div');
        errorMsg.className = 'invalid-feedback table-bet-error';
        errorMsg.textContent = 'Minimum bet must be at least $100';
        minBetField.parentNode.appendChild(errorMsg);
        isValid = false;
    }
    
    if (minBet % 100 !== 0) {
        minBetField.classList.add('is-invalid');
        const errorMsg = document.createElement('div');
        errorMsg.className = 'invalid-feedback table-bet-error';
        errorMsg.textContent = 'Minimum bet must be a multiple of $100';
        minBetField.parentNode.appendChild(errorMsg);
        isValid = false;
    }
    
    // Validate maximum bet
    if (maxBet < (2 * minBet)) {
        maxBetField.classList.add('is-invalid');
        const errorMsg = document.createElement('div');
        errorMsg.className = 'invalid-feedback table-bet-error';
        errorMsg.textContent = `Maximum bet must be at least $${(2 * minBet)}`;
        maxBetField.parentNode.appendChild(errorMsg);
        isValid = false;
    }
    
    if (maxBet % 100 !== 0) {
        maxBetField.classList.add('is-invalid');
        const errorMsg = document.createElement('div');
        errorMsg.className = 'invalid-feedback table-bet-error';
        errorMsg.textContent = 'Maximum bet must be a multiple of $100';
        maxBetField.parentNode.appendChild(errorMsg);
        isValid = false;
    }
    
    return isValid;
}

// Show/hide deck penetration based on shuffle method
document.getElementById('shuffle_method').addEventListener('change', function() {
    const penetrationGroup = document.getElementById('penetrationGroup');
    penetrationGroup.style.display = (this.value === 'shoe') ? 'block' : 'none';
});

// Initialize visibility
document.addEventListener('DOMContentLoaded', function() {
    const shuffleMethod = document.getElementById('shuffle_method').value;
    const penetrationGroup = document.getElementById('penetrationGroup');
    penetrationGroup.style.display = (shuffleMethod === 'shoe') ? 'block' : 'none';
    
    // Update range value display
    const deckPenetration = document.getElementById('deck_penetration');
    const penetrationValue = document.getElementById('penetrationValue');
    
    deckPenetration.addEventListener('input', function() {
        penetrationValue.textContent = this.value + '%';
    });
    
    // Add table limits validation
    const tableMinBet = document.getElementById('table_min_bet');
    const tableMaxBet = document.getElementById('table_max_bet');
    
    tableMinBet.addEventListener('input', validateTableLimits);
    tableMaxBet.addEventListener('input', validateTableLimits);
    
    // Validate on form submission
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        if (!validateTableLimits()) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>