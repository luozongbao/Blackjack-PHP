<?php
/**
 * Lobby / Dashboard Page
 *
 * This is the main dashboard page that users see after logging in.
 * It shows overall stats and provides navigation to other parts of the application.
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
require_once '../includes/database.php';

// Set page title
$pageTitle = 'Dashboard';

// Get user stats
$userId = $_SESSION['user_id'];
$stats = [];

try {
    // Get database connection
    $db = getDb();
    
    // Get current active session or create a new one
    $sessionStmt = $db->prepare("
        SELECT * FROM game_sessions 
        WHERE user_id = :user_id AND is_active = 1 
        ORDER BY start_time DESC LIMIT 1
    ");
    $sessionStmt->bindParam(':user_id', $userId);
    $sessionStmt->execute();
    
    $session = $sessionStmt->fetch();
    
    if (!$session) {
        // Create a new session for the user
        $createSessionStmt = $db->prepare("
            INSERT INTO game_sessions (user_id, start_time, is_active) 
            VALUES (:user_id, CURRENT_TIMESTAMP, 1)
        ");
        $createSessionStmt->bindParam(':user_id', $userId);
        $createSessionStmt->execute();
        
        // Get the newly created session
        $sessionStmt->execute();
        $session = $sessionStmt->fetch();
    }
    
    // Get user settings
    $settingsStmt = $db->prepare("
        SELECT * FROM user_settings 
        WHERE user_id = :user_id
    ");
    $settingsStmt->bindParam(':user_id', $userId);
    $settingsStmt->execute();
    
    $settings = $settingsStmt->fetch();
    
    // Store stats for display
    $stats = [
        'session' => $session,
        'settings' => $settings
    ];
    
} catch (PDOException $e) {
    // Log error but don't show to user
    error_log('Database error: ' . $e->getMessage());
}

// Calculate derived stats
$sessionROI = ($stats['session']['session_total_bet'] > 0) 
    ? (($stats['session']['session_total_won'] - $stats['session']['session_total_loss']) / $stats['session']['session_total_bet']) * 100 
    : 0;

$allTimeROI = ($stats['session']['all_time_total_bet'] > 0) 
    ? (($stats['session']['all_time_total_won'] - $stats['session']['all_time_total_loss']) / $stats['session']['all_time_total_bet']) * 100 
    : 0;

$sessionWinPerGame = ($stats['session']['session_games_played'] > 0) 
    ? ($stats['session']['session_total_won'] - $stats['session']['session_total_loss']) / $stats['session']['session_games_played'] 
    : 0;

$allTimeWinPerGame = ($stats['session']['all_time_games_played'] > 0) 
    ? ($stats['session']['all_time_total_won'] - $stats['session']['all_time_total_loss']) / $stats['session']['all_time_games_played'] 
    : 0;
?>

<?php include_once '../includes/header.php'; ?>

<div class="card mb-3">
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['display_name']); ?>!</h1>
    <p class="text-center">Ready to play some Blackjack? Check your stats below or head to the game table.</p>
    
    <div class="text-center mb-3">
        <a href="game.php" class="btn btn-secondary">Play Now</a>
    </div>
</div>

<h2>Your Dashboard</h2>

<div class="stats-container">
    <!-- Current Session Stats -->
    <div class="stats-card">
        <h3 class="stats-title">Current Session</h3>
        
        <div class="stats-group">
            <h4>Money</h4>
            <div class="stat-row">
                <span>Current Balance:</span>
                <span class="text-primary"><?php echo number_format($stats['session']['current_money'], 2); ?> $</span>
            </div>
            <div class="stat-row">
                <span>Total Won:</span>
                <span class="text-success"><?php echo number_format($stats['session']['session_total_won'], 2); ?> $</span>
            </div>
            <div class="stat-row">
                <span>Total Loss:</span>
                <span class="text-danger"><?php echo number_format($stats['session']['session_total_loss'], 2); ?> $</span>
            </div>
            <div class="stat-row">
                <span>Total Bet:</span>
                <span><?php echo number_format($stats['session']['session_total_bet'], 2); ?> $</span>
            </div>
            <div class="stat-row">
                <span>ROI:</span>
                <span class="<?php echo $sessionROI >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo number_format($sessionROI, 2); ?> %
                </span>
            </div>
        </div>
        
        <div class="stats-group mt-2">
            <h4>Games</h4>
            <div class="stat-row">
                <span>Games Played:</span>
                <span><?php echo $stats['session']['session_games_played']; ?></span>
            </div>
            <div class="stat-row">
                <span>Games Won:</span>
                <span class="text-success"><?php echo $stats['session']['session_games_won']; ?></span>
            </div>
            <div class="stat-row">
                <span>Games Push:</span>
                <span><?php echo $stats['session']['session_games_push']; ?></span>
            </div>
            <div class="stat-row">
                <span>Games Lost:</span>
                <span class="text-danger"><?php echo $stats['session']['session_games_lost']; ?></span>
            </div>
            <div class="stat-row">
                <span>Win Per Game:</span>
                <span class="<?php echo $sessionWinPerGame >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo number_format($sessionWinPerGame, 2); ?> $
                </span>
            </div>
        </div>
    </div>
    
    <!-- All-time Stats -->
    <div class="stats-card">
        <h3 class="stats-title">All-time Statistics</h3>
        
        <div class="stats-group">
            <h4>Money</h4>
            <div class="stat-row">
                <span>Total Won:</span>
                <span class="text-success"><?php echo number_format($stats['session']['all_time_total_won'], 2); ?> $</span>
            </div>
            <div class="stat-row">
                <span>Total Loss:</span>
                <span class="text-danger"><?php echo number_format($stats['session']['all_time_total_loss'], 2); ?> $</span>
            </div>
            <div class="stat-row">
                <span>Total Bet:</span>
                <span><?php echo number_format($stats['session']['all_time_total_bet'], 2); ?> $</span>
            </div>
            <div class="stat-row">
                <span>ROI:</span>
                <span class="<?php echo $allTimeROI >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo number_format($allTimeROI, 2); ?> %
                </span>
            </div>
        </div>
        
        <div class="stats-group mt-2">
            <h4>Games</h4>
            <div class="stat-row">
                <span>Games Played:</span>
                <span><?php echo $stats['session']['all_time_games_played']; ?></span>
            </div>
            <div class="stat-row">
                <span>Games Won:</span>
                <span class="text-success"><?php echo $stats['session']['all_time_games_won']; ?></span>
            </div>
            <div class="stat-row">
                <span>Games Push:</span>
                <span><?php echo $stats['session']['all_time_games_push']; ?></span>
            </div>
            <div class="stat-row">
                <span>Games Lost:</span>
                <span class="text-danger"><?php echo $stats['session']['all_time_games_lost']; ?></span>
            </div>
            <div class="stat-row">
                <span>Win Per Game:</span>
                <span class="<?php echo $allTimeWinPerGame >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo number_format($allTimeWinPerGame, 2); ?> $
                </span>
            </div>
        </div>
    </div>
    
    <!-- Game Settings Summary -->
    <div class="stats-card">
        <h3 class="stats-title">Game Settings</h3>
        
        <div class="stats-group">
            <div class="stat-row">
                <span>Decks Per Shoe:</span>
                <span><?php echo $stats['settings']['decks_per_shoe']; ?></span>
            </div>
            <div class="stat-row">
                <span>Deal Style:</span>
                <span><?php echo ucfirst($stats['settings']['deal_style']); ?></span>
            </div>
            <div class="stat-row">
                <span>Dealer Draws To:</span>
                <span><?php echo ($stats['settings']['dealer_draw_to'] === 'any17') ? 'Any 17' : 'Hard 17'; ?></span>
            </div>
            <div class="stat-row">
                <span>Blackjack Payout:</span>
                <span><?php echo $stats['settings']['blackjack_payout']; ?></span>
            </div>
            <div class="stat-row">
                <span>Max Splits:</span>
                <span><?php echo $stats['settings']['max_splits']; ?></span>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <a href="settings.php" class="btn">Change Settings</a>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>