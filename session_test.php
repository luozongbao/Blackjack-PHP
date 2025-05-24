<?php
/**
 * Direct test of the blackjack game session fix
 * This will test the specific scenario that was causing HTTP 500
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Test Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .test { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }
        .pass { background: #d4edda; border-color: #c3e6cb; }
        .fail { background: #f8d7da; border-color: #f5c6cb; }
        .info { background: #cce7ff; border-color: #99d3ff; }
        h3 { margin-top: 0; }
    </style>
</head>
<body>
<h1>Blackjack Session Deserialization Test</h1>

<?php
// Test 1: Basic includes
echo '<div class="test">';
echo '<h3>Test 1: Include Files</h3>';
try {
    require_once 'includes/database.php';
    require_once 'classes/game_class.php';
    echo '<p class="pass">✓ All includes loaded successfully</p>';
} catch (Exception $e) {
    echo '<p class="fail">✗ Include error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div></body></html>';
    exit;
}
echo '</div>';

// Test 2: Session start
echo '<div class="test">';
echo '<h3>Test 2: Session Management</h3>';
try {
    session_start();
    echo '<p class="pass">✓ Session started successfully</p>';
    echo '<p class="info">Session ID: ' . session_id() . '</p>';
} catch (Exception $e) {
    echo '<p class="fail">✗ Session error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

// Test 3: Database connection
echo '<div class="test">';
echo '<h3>Test 3: Database Connection</h3>';
try {
    $db = getDb();
    echo '<p class="pass">✓ Database connection successful</p>';
} catch (Exception $e) {
    echo '<p class="fail">✗ Database error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div></body></html>';
    exit;
}
echo '</div>';

// Test 4: Game object creation
echo '<div class="test">';
echo '<h3>Test 4: Game Object Creation</h3>';
try {
    $settings = [
        'initial_money' => 10000,
        'min_bet' => 100,
        'max_bet' => 1000,
        'deck_count' => 6,
        'shuffle_method' => 'auto',
        'deck_penetration' => 0.75
    ];
    
    $game = new BlackjackGame($settings, 1, $db);
    echo '<p class="pass">✓ Game object created successfully</p>';
} catch (Exception $e) {
    echo '<p class="fail">✗ Game creation error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

// Test 5: Session storage and retrieval
echo '<div class="test">';
echo '<h3>Test 5: Session Storage and Retrieval</h3>';
try {
    $_SESSION['test_game'] = $game;
    echo '<p class="pass">✓ Game object stored in session</p>';
    
    $retrieved_game = $_SESSION['test_game'];
    echo '<p class="pass">✓ Game object retrieved from session</p>';
    
    // Test if the retrieved game works
    $gameState = $retrieved_game->getGameState();
    echo '<p class="pass">✓ Game state accessible after retrieval</p>';
    echo '<p class="info">Game state: ' . htmlspecialchars(json_encode($gameState)) . '</p>';
} catch (Exception $e) {
    echo '<p class="fail">✗ Session storage/retrieval error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

// Test 6: Serialization test (simulates what happens on page reload)
echo '<div class="test">';
echo '<h3>Test 6: Serialization/Deserialization Test</h3>';
try {
    // Serialize the game object
    $serialized = serialize($_SESSION['test_game']);
    echo '<p class="pass">✓ Game object serialized successfully</p>';
    
    // Clear from session
    unset($_SESSION['test_game']);
    
    // Deserialize back
    $_SESSION['test_game'] = unserialize($serialized);
    echo '<p class="pass">✓ Game object deserialized successfully</p>';
    
    // Test functionality after deserialization
    $game_after = $_SESSION['test_game'];
    $state_after = $game_after->getGameState();
    echo '<p class="pass">✓ Game object functional after deserialization</p>';
    
} catch (Exception $e) {
    echo '<p class="fail">✗ Serialization error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p class="info">This is the root cause of HTTP 500 errors</p>';
}
echo '</div>';

// Test 7: __wakeup method test
echo '<div class="test">';
echo '<h3>Test 7: __wakeup Method Test</h3>';
try {
    $game_final = $_SESSION['test_game'];
    $game_final->__wakeup(); // Force call to __wakeup
    echo '<p class="pass">✓ __wakeup method executed without errors</p>';
    
    // Test database operations after __wakeup
    $final_state = $game_final->getGameState();
    echo '<p class="pass">✓ Database operations work after __wakeup</p>';
    
} catch (Exception $e) {
    echo '<p class="fail">✗ __wakeup error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

echo '<div class="test info">';
echo '<h3>Summary</h3>';
echo '<p>If all tests above show ✓ (green), then the session deserialization fix is working.</p>';
echo '<p>This means the HTTP 500 errors should be resolved for:</p>';
echo '<ul>';
echo '<li>Returning to game.php after leaving the page</li>';
echo '<li>Refreshing the game.php page</li>';
echo '<li>Any scenario where the game object needs to be deserialized from session</li>';
echo '</ul>';
echo '<p><strong>Next steps:</strong> Test the actual game interface to verify player card visibility.</p>';
echo '</div>';
?>

</body>
</html>
