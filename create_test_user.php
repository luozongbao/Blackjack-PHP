<?php
/**
 * Quick setup script to create a test user for debugging
 */

require_once 'includes/database.php';

try {
    $db = getDb();
    
    // Check if test user already exists
    $stmt = $db->prepare("SELECT user_id FROM users WHERE username = 'test'");
    $stmt->execute();
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "Test user already exists (ID: " . $existingUser['user_id'] . ")\n";
        echo "Username: test\n";
        echo "Password: test123\n";
    } else {
        // Create test user
        $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (username, password, email, display_name, created_at) 
            VALUES ('test', ?, 'test@example.com', 'Test User', NOW())
        ");
        $stmt->execute([$hashedPassword]);
        
        $userId = $db->lastInsertId();
        
        // Create user settings
        $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        
        echo "Test user created successfully!\n";
        echo "User ID: $userId\n";
        echo "Username: test\n";
        echo "Password: test123\n";
        echo "Email: test@example.com\n";
    }
    
    echo "\nYou can now login at: http://localhost:8002/login.php\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
