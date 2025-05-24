<?php
/**
 * Test script to verify the session deserialization fix
 * This simulates the exact scenario that was causing HTTP 500 errors
 */

echo "<h2>Blackjack Session Deserialization Test</h2>\n";

// Test 1: Include classes and start session (like game.php does)
echo "<h3>Test 1: Session Initialization</h3>\n";
try {
    require_once 'includes/database.php';
    require_once 'classes/game_class.php';
    session_start();
    echo "✓ Classes loaded and session started successfully<br>\n";
} catch (Exception $e) {
    echo "✗ Error in session initialization: " . $e->getMessage() . "<br>\n";
    exit;
}

// Test 2: Database connection
echo "<h3>Test 2: Database Connection</h3>\n";
try {
    $db = getDb();
    echo "✓ Database connection successful<br>\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>\n";
    exit;
}

// Test 3: Create a game object and store in session
echo "<h3>Test 3: Game Object Creation and Session Storage</h3>\n";
try {
    // Create default settings for test
    $settings = [
        'initial_money' => 10000,
        'min_bet' => 100,
        'max_bet' => 1000
    ];
    
    // Create a new game
    $game = new BlackjackGame(1, $settings, $db); // sessionId = 1 for test
    $_SESSION['test_game'] = $game;
    echo "✓ Game object created and stored in session<br>\n";
} catch (Exception $e) {
    echo "✗ Error creating game object: " . $e->getMessage() . "<br>\n";
    exit;
}

// Test 4: Simulate session serialization/deserialization
echo "<h3>Test 4: Session Serialization Test</h3>\n";
try {
    // Get the session data as it would be serialized
    $serialized = serialize($_SESSION['test_game']);
    echo "✓ Game object serialized successfully<br>\n";
    
    // Clear the game from memory
    unset($_SESSION['test_game']);
    
    // Deserialize it back (this is what happens when page is reloaded)
    $_SESSION['test_game'] = unserialize($serialized);
    echo "✓ Game object deserialized successfully<br>\n";
} catch (Exception $e) {
    echo "✗ Error in serialization/deserialization: " . $e->getMessage() . "<br>\n";
}

// Test 5: Test game object functionality after deserialization
echo "<h3>Test 5: Game Object Functionality Test</h3>\n";
try {
    $game = $_SESSION['test_game'];
    $gameState = $game->getGameState();
    echo "✓ Game state retrieved successfully after deserialization<br>\n";
    echo "Game state: " . json_encode($gameState) . "<br>\n";
} catch (Exception $e) {
    echo "✗ Error accessing game state after deserialization: " . $e->getMessage() . "<br>\n";
}

// Test 6: Test the __wakeup method
echo "<h3>Test 6: __wakeup Method Test</h3>\n";
try {
    // Force call to __wakeup to test it
    $game = $_SESSION['test_game'];
    $game->__wakeup();
    echo "✓ __wakeup method executed successfully<br>\n";
    
    // Test database connectivity after wakeup
    $gameState = $game->getGameState();
    echo "✓ Database operations work after __wakeup<br>\n";
} catch (Exception $e) {
    echo "✗ Error in __wakeup method: " . $e->getMessage() . "<br>\n";
}

echo "<h3>Test Summary</h3>\n";
echo "If all tests above show ✓, then the session deserialization fix is working correctly.<br>\n";
echo "This means the HTTP 500 errors should no longer occur when:<br>\n";
echo "- Leaving the game page and returning<br>\n";
echo "- Refreshing the game page<br>\n";
echo "- Accessing game.php after the session exists<br>\n";
?>
