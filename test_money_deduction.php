<?php
/**
 * Test script to verify money deduction and bet tracking
 */

session_start();

require_once 'includes/database.php';
require_once 'classes/game_class.php';

$db = getDb();

// Test user ID (assuming user 1 exists)
$testUserId = 1;
$_SESSION['user_id'] = $testUserId;

try {
    echo "=== Money Deduction Test ===\n";
    
    // Get or create test session
    $stmt = $db->prepare("SELECT * FROM game_sessions WHERE user_id = ? AND is_active = 1 ORDER BY start_time DESC LIMIT 1");
    $stmt->execute([$testUserId]);
    $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sessionData) {
        echo "Creating new test session...\n";
        $stmt = $db->prepare("INSERT INTO game_sessions (user_id, current_money) VALUES (?, ?)");
        $stmt->execute([$testUserId, 10000]);
        $sessionId = $db->lastInsertId();
        
        $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $sessionId = $sessionData['session_id'];
    }
    
    echo "Initial session state:\n";
    echo "- Current Money: $" . number_format($sessionData['current_money'], 2) . "\n";
    echo "- Session Total Bet: $" . number_format($sessionData['session_total_bet'], 2) . "\n";
    
    // Get user settings
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$testUserId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        echo "Creating default settings...\n";
        $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmt->execute([$testUserId]);
        
        $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$testUserId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Start a test game with $100 bet
    echo "\nStarting game with $100 bet...\n";
    $game = new BlackjackGame($settings, $sessionId, $db);
    $gameState = $game->startGame(100);
    
    // Check session state after bet
    $stmt = $db->prepare("SELECT * FROM game_sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $sessionDataAfterBet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "After placing $100 bet:\n";
    echo "- Current Money: $" . number_format($sessionDataAfterBet['current_money'], 2) . "\n";
    echo "- Session Total Bet: $" . number_format($sessionDataAfterBet['session_total_bet'], 2) . "\n";
    echo "- Money Change: $" . number_format($sessionDataAfterBet['current_money'] - $sessionData['current_money'], 2) . "\n";
    echo "- Total Bet Change: $" . number_format($sessionDataAfterBet['session_total_bet'] - $sessionData['session_total_bet'], 2) . "\n";
    
    // Check game state
    echo "\nGame State:\n";
    echo "- Game State: " . $gameState['gameState'] . "\n";
    echo "- Player Hands: " . count($gameState['playerHands']) . "\n";
    echo "- First Hand Bet: $" . number_format($gameState['playerHands'][0]['bet'], 2) . "\n";
    
    // Test action buttons availability
    echo "- Can Hit: " . ($gameState['canHit'] ? 'Yes' : 'No') . "\n";
    echo "- Can Stand: " . ($gameState['canStand'] ? 'Yes' : 'No') . "\n";
    echo "- Can Double: " . ($gameState['canDouble'] ? 'Yes' : 'No') . "\n";
    echo "- Can Split: " . ($gameState['canSplit'] ? 'Yes' : 'No') . "\n";
    echo "- Can Surrender: " . ($gameState['canSurrender'] ? 'Yes' : 'No') . "\n";
    
    echo "\n=== Test Completed Successfully ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
