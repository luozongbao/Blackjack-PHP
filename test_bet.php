<?php
/**
 * Test script to debug the betting error
 */

session_start();

// Set a test user session if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Assuming user ID 1 exists
}

require_once 'includes/database.php';
require_once 'classes/game_class.php';

try {
    $db = getDb();
    echo "Database connection successful\n";
    
    // Get user settings
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        echo "Creating default settings for user ID " . $_SESSION['user_id'] . "\n";
        $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmt->execute([$_SESSION['user_id']]);
        
        $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    echo "User settings loaded\n";
    
    // Get or create active session
    $stmt = $db->prepare("SELECT * FROM game_sessions WHERE user_id = ? AND is_active = 1 ORDER BY start_time DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sessionData) {
        // Create new session
        $stmt = $db->prepare("
            INSERT INTO game_sessions (user_id, current_money) 
            SELECT ?, initial_money FROM user_settings WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $sessionId = $db->lastInsertId();
        
        // Fetch the new session
        $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $sessionId = $sessionData['session_id'];
    }
    
    echo "Session ID: " . $sessionId . "\n";
    echo "Current Money: $" . number_format($sessionData['current_money'], 2) . "\n";
    
    // Test creating a game with $100 bet
    $betAmount = 100.00;
    echo "Testing bet amount: $" . number_format($betAmount, 2) . "\n";
    
    $game = new BlackjackGame($settings, $sessionId, $db);
    $gameState = $game->startGame($betAmount);
    
    echo "Game started successfully!\n";
    echo "Game State: " . $gameState['gameState'] . "\n";
    echo "Player Score: " . $gameState['playerHands'][0]['score'] . "\n";
    echo "Dealer Visible Score: " . $gameState['dealerHand']['cards'][0]['value'] . " + ?\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
