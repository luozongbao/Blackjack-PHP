<?php
/**
 * Quick login script for testing - bypasses normal authentication
 */
session_start();

require_once 'includes/database.php';

try {
    $db = getDb();
    
    // Create or find test user
    $stmt = $db->prepare("SELECT user_id FROM users WHERE username = 'test' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        // Create test user
        $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, email, display_name) VALUES (?, ?, ?, ?)");
        $stmt->execute(['test', $hashedPassword, 'test@example.com', 'Test User']);
        $userId = $db->lastInsertId();
        
        // Create user settings
        $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        
        echo "Created test user with ID: $userId<br>";
    } else {
        $userId = $user['user_id'];
        echo "Found existing test user with ID: $userId<br>";
    }
    
    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = 'test';
    $_SESSION['display_name'] = 'Test User';
    
    echo "Authentication set successfully!<br>";
    echo "Session user_id: " . $_SESSION['user_id'] . "<br>";
    echo "<a href='game.php'>Go to Game</a><br>";
    echo "<a href='lobby.php'>Go to Lobby</a><br>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
