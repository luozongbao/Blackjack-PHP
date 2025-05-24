<?php
session_start();

// Set up error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Blackjack Game Debug Script</h1>";

// Test database connection
echo "<h2>1. Database Connection Test</h2>";
try {
    require_once 'includes/database.php';
    $db = getDb();
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Test user session
echo "<h2>2. User Session Test</h2>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User logged in: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "❌ User not logged in<br>";
}

// Test game session
echo "<h2>3. Game Session Test</h2>";
if (isset($_SESSION['game'])) {
    echo "✅ Game object exists in session<br>";
    echo "Game object type: " . get_class($_SESSION['game']) . "<br>";
    
    // Try to access the game object
    try {
        $game = $_SESSION['game'];
        $gameState = $game->getGameState();
        echo "✅ Game state retrieved successfully<br>";
        echo "Current game state: " . $gameState['gameState'] . "<br>";
    } catch (Exception $e) {
        echo "❌ Error accessing game object: " . $e->getMessage() . "<br>";
        echo "Error details:<br>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "❌ No game object in session<br>";
}

// Test BlackjackGame class loading
echo "<h2>4. BlackjackGame Class Test</h2>";
try {
    require_once 'classes/game_class.php';
    echo "✅ BlackjackGame class loaded successfully<br>";
    
    // Test creating a new game object
    $settings = [
        'decks_per_shoe' => 6,
        'deck_penetration' => 75,
        'shuffle_method' => 'shoe'
    ];
    $testGame = new BlackjackGame($settings, 1, $db);
    echo "✅ BlackjackGame object created successfully<br>";
    
} catch (Exception $e) {
    echo "❌ Error with BlackjackGame class: " . $e->getMessage() . "<br>";
    echo "Error details:<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Test database queries
echo "<h2>5. Database Query Test</h2>";
if (isset($_SESSION['user_id'])) {
    try {
        // Test game sessions query
        $stmt = $db->prepare("SELECT * FROM game_sessions WHERE user_id = ? AND is_active = 1 ORDER BY start_time DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sessionData) {
            echo "✅ Game session found: " . $sessionData['session_id'] . "<br>";
            echo "Current money: $" . $sessionData['current_money'] . "<br>";
            echo "Game state stored: " . (!empty($sessionData['game_state']) ? 'Yes' : 'No') . "<br>";
        } else {
            echo "❌ No active game session found<br>";
        }
        
        // Test user settings query
        $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings) {
            echo "✅ User settings found<br>";
        } else {
            echo "❌ No user settings found<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Database query error: " . $e->getMessage() . "<br>";
    }
}

// Test serialization
echo "<h2>6. Serialization Test</h2>";
if (isset($_SESSION['game'])) {
    try {
        $serialized = serialize($_SESSION['game']);
        echo "✅ Game object serialization successful<br>";
        
        $unserialized = unserialize($serialized);
        echo "✅ Game object unserialization successful<br>";
        
        // Test accessing the unserialized object
        $gameState = $unserialized->getGameState();
        echo "✅ Unserialized game object functional<br>";
        
    } catch (Exception $e) {
        echo "❌ Serialization error: " . $e->getMessage() . "<br>";
        echo "Error details:<br>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
}

echo "<h2>7. Current Session Contents</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

?>
