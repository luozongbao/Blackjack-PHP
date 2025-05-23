<?php
/**
 * Game Page
 * 
 * The main blackjack game interface.
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

// Set page title
$pageTitle = 'Game Table';

// Get current session info
try {
    $db = getDb();
    
    // Get user ID
    $userId = $_SESSION['user_id'];
    
    // Get current active session
    $sessionStmt = $db->prepare("
        SELECT * FROM game_sessions 
        WHERE user_id = :user_id AND is_active = 1
        ORDER BY start_time DESC LIMIT 1
    ");
    $sessionStmt->bindParam(':user_id', $userId);
    $sessionStmt->execute();
    $session = $sessionStmt->fetch();
    
    // Get user settings
    $settingsStmt = $db->prepare("
        SELECT * FROM user_settings 
        WHERE user_id = :user_id
    ");
    $settingsStmt->bindParam(':user_id', $userId);
    $settingsStmt->execute();
    $settings = $settingsStmt->fetch();
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

include_once 'includes/header.php';
?>

<div class="card mb-3">
    <h1>Blackjack Table</h1>
    
    <div class="balance-display">
        <span>Current Balance:</span>
        <span class="balance-amount">$<?php echo number_format($session['current_money'], 2); ?></span>
    </div>
</div>

<div class="game-container">
    <!-- Dealer Section -->
    <div class="dealer-section">
        <div class="section-title">Dealer</div>
        <div class="dealer-score-display">
            <span class="score-label">Score:</span>
            <span class="dealer-score">--</span>
        </div>
        <div class="cards-container dealer-cards">
            <!-- Dealer cards will be added here dynamically -->
        </div>
    </div>
    
    <!-- Player Section -->
    <div class="player-section">
        <div class="section-title">Player</div>
        <div class="player-hands">
            <!-- Player hands will be added here dynamically -->
        </div>
    </div>
    
    <!-- Action Section -->
    <div class="action-section">
        <!-- Bet Controls - Shown before game starts -->
        <div class="bet-controls">
            <div class="bet-amount-group">
                <label for="bet-amount">Bet Amount: $</label>
                <input type="number" id="bet-amount" min="10" max="1000" step="10" value="50">
            </div>
            <button id="place-bet-btn" class="action-button">Place Bet</button>
        </div>
        
        <!-- Game Controls - Shown during gameplay -->
        <div class="game-controls" style="display: none;">
            <!-- Insurance controls -->
            <div class="insurance-controls" style="display: none;">
                <p class="message">Dealer is showing an Ace. Would you like insurance?</p>
                <div class="insurance-buttons">
                    <button id="insurance-yes-btn" class="action-button">Yes (Half Bet)</button>
                    <button id="insurance-no-btn" class="action-button">No Thanks</button>
                </div>
            </div>
            
            <!-- Regular game action buttons -->
            <button id="hit-btn" class="action-button">Hit</button>
            <button id="stand-btn" class="action-button">Stand</button>
            <button id="double-btn" class="action-button">Double</button>
            <button id="split-btn" class="action-button">Split</button>
            <button id="surrender-btn" class="action-button">Surrender</button>
        </div>
        
        <!-- Game Over Controls - Shown after game ends -->
        <div class="game-over-controls" style="display: none;">
            <div class="result-message"></div>
            <button id="new-game-btn" class="action-button">New Game</button>
        </div>
    </div>
    
    <!-- Game Messages -->
    <div class="game-messages">
        <!-- Messages will be displayed here -->
    </div>
</div>

<script>
    // Store game settings for JavaScript
    const gameSettings = {
        dealStyle: "<?php echo $settings['deal_style']; ?>",
        dealerDrawTo: "<?php echo $settings['dealer_draw_to']; ?>",
        blackjackPayout: "<?php echo $settings['blackjack_payout']; ?>",
        surrenderOption: "<?php echo $settings['surrender_option']; ?>",
        doubleAfterSplit: <?php echo $settings['double_after_split'] ? 'true' : 'false'; ?>,
        allowInsurance: <?php echo $settings['allow_insurance'] ? 'true' : 'false'; ?>,
        doubleOn: "<?php echo $settings['double_on']; ?>",
        maxSplits: <?php echo $settings['max_splits']; ?>,
        currentMoney: <?php echo $session['current_money']; ?>
    };
</script>
<script src="assets/js/game.js"></script>

<?php include_once 'includes/footer.php'; ?>